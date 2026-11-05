<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Shared\Media\EncodedImage;
use App\Shared\Media\PrivateImages;
use App\Tenant\Returns\Enums\ReturnReason;
use App\Tenant\Returns\Enums\ReturnResolution;
use App\Tenant\Returns\Services\ReturnPhotos;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The rules for asking to return units: which lines and how many, why, an optional note and photos, and for an exchange what to send in their place. Sent as multipart form data when there are photos.
 */
trait ValidatesReturnRequest
{
    use ReadsReturnLines;

    /** @var list<EncodedImage> */
    private array $encodedPhotos = [];

    /**
     * @return array<string, list<mixed>>
     */
    protected function returnRules(): array
    {
        return [
            /** The order lines to return and how many units of each. */
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            /** An order line's ID. */
            'lines.*.line' => ['required', 'string', 'max:26', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            /** For an exchange: the ID of the variant to send in place of these units, as many of it; it must be for sale and in stock. Left out for a refund. */
            'lines.*.replacement' => ['sometimes', 'nullable', 'string', 'max:26'],
            /** "refund" (the default), or "exchange": other items sent in place of these, naming a replacement for every line. Any difference in an exchange goes where a refund would. */
            'resolution' => ['sometimes', 'string', Rule::enum(ReturnResolution::class)],
            /** Why: "damaged", "wrong_item", "faulty", "not_as_described", "does_not_fit", "changed_mind" or "other" (GET /returns/reasons names them in the buyer's language). */
            'reason' => ['required', 'string', Rule::enum(ReturnReason::class)],
            /** Anything to add about the reason. */
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            /** Photos of the items, such as the damage: JPEG, PNG or WebP. Required, when the store asks for them, for a reason that claims the store was at fault. */
            'photos' => ['sometimes', 'array', 'max:'.config()->integer('returns.max_photos')],
            'photos.*' => PrivateImages::rules(ReturnPhotos::KIND),
        ];
    }

    /**
     * Re-encodes each photo once the rest is valid, refusing one that can't be decoded.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $images = app(PrivateImages::class);
            $uploads = $this->file('photos');

            foreach (is_array($uploads) ? array_values($uploads) : [] as $index => $upload) {
                $photo = $images->encode($upload, 'return-photo');

                if ($photo === null) {
                    $validator->errors()->add("photos.{$index}", __('validation.image', ['attribute' => "photos.{$index}"]));

                    continue;
                }

                $this->encodedPhotos[] = $photo;
            }

            if ($validator->errors()->isNotEmpty()) {
                ReturnPhotos::discard($this->encodedPhotos);
                $this->encodedPhotos = [];
            }
        }];
    }

    /**
     * @return array<string, int> Units to return, by order line public ID.
     */
    public function returnLines(): array
    {
        $validated = $this->safe();
        $lines = [];

        foreach ($this->linePositions($validated) as $position) {
            $lines[$this->lineOf($validated, $position)] = $validated->integer("lines.{$position}.quantity");
        }

        return $lines;
    }

    public function resolution(): ReturnResolution
    {
        return ReturnResolution::tryFrom($this->string('resolution')->value()) ?? ReturnResolution::Refund;
    }

    public function reason(): ReturnReason
    {
        return ReturnReason::from($this->string('reason')->value());
    }

    public function note(): ?string
    {
        $note = $this->string('note')->trim()->value();

        return $note === '' ? null : $note;
    }

    /**
     * @return list<EncodedImage> The photos, re-encoded without their metadata.
     */
    public function photos(): array
    {
        return $this->encodedPhotos;
    }
}
