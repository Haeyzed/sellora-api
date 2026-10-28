<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Tenant\Delivery\Enums\DeliveryFailureReason;
use App\Tenant\Delivery\Enums\ProofOfDeliveryKind;
use App\Tenant\Delivery\Services\ProofOfDelivery;
use App\Tenant\Delivery\Services\ProofOfDeliveryImages;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * The rules for how a delivery ended: who took the goods and the proof, or why it failed.
 */
trait ValidatesDeliveryOutcome
{
    private ?ProofOfDelivery $proof = null;

    /**
     * The photo and signature rules: at least one of them when $required.
     *
     * @return array<string, list<mixed>>
     */
    protected function proofRules(bool $required, string $prohibited = ''): array
    {
        $rules = [];

        foreach (ProofOfDeliveryKind::cases() as $kind) {
            $other = $kind === ProofOfDeliveryKind::Photo ? ProofOfDeliveryKind::Signature : ProofOfDeliveryKind::Photo;
            $rules[$kind->value] = [
                ...($required ? ['required_without:'.$other->value] : ['nullable']),
                ...($prohibited !== '' ? [$prohibited] : []),
                ...ProofOfDeliveryImages::rules(),
            ];
        }

        return $rules;
    }

    /**
     * Re-encodes each proof image once the rest is valid, refusing one that can't be decoded.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $files = [];
            $images = app(ProofOfDeliveryImages::class);

            foreach (ProofOfDeliveryKind::cases() as $kind) {
                $upload = $this->file($kind->value);

                if (! $upload instanceof UploadedFile) {
                    continue;
                }

                $file = $images->encode($kind, $upload);

                if ($file === null) {
                    $validator->errors()->add($kind->value, __('validation.image', ['attribute' => $kind->value]));

                    continue;
                }

                $files[] = $file;
            }

            $this->proof = new ProofOfDelivery($files);
        }];
    }

    /**
     * The proof images handed over, re-encoded without their metadata; empty when none were.
     */
    public function proofOfDelivery(): ProofOfDelivery
    {
        return $this->proof ?? new ProofOfDelivery;
    }

    /**
     * @return list<string>
     */
    protected function recipientNameRules(string $required = 'required'): array
    {
        return [$required, 'string', 'min:1', 'max:255'];
    }

    /**
     * @return list<string>
     */
    protected function cashCollectedRules(): array
    {
        return ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX];
    }

    /**
     * The cash collected, in minor units of the order's currency; null when none was given.
     */
    public function cashCollected(): ?int
    {
        return $this->filled('cash_collected') ? $this->integer('cash_collected') : null;
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    protected function failureRules(string $required = 'required'): array
    {
        return [
            /** Why it couldn't be delivered. */
            'reason' => [$required, 'string', Rule::enum(DeliveryFailureReason::class)],
            /** What happened, in a few words; required when the reason is "other". */
            'note' => ['nullable', 'string', 'max:500', 'required_if:reason,'.DeliveryFailureReason::Other->value],
        ];
    }

    public function recipientName(): string
    {
        return trim($this->string('recipient_name')->toString());
    }

    public function failureReason(): DeliveryFailureReason
    {
        return DeliveryFailureReason::from($this->string('reason')->toString());
    }

    public function failureNote(): ?string
    {
        $note = $this->input('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}
