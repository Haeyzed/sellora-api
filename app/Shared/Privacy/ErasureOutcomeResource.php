<?php

declare(strict_types=1);

namespace App\Shared\Privacy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The answer to an erasure request: what is still kept by law, and until when, in words to show the person.
 *
 * @property ErasureOutcome $resource
 */
final class ErasureOutcomeResource extends JsonResource
{
    public function __construct(ErasureOutcome $outcome)
    {
        parent::__construct($outcome);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $until = $this->resource->keptByLawUntil;

        return [
            /** The last day the store must keep invoices and credit notes with the person's details on them, as tax law requires (YYYY-MM-DD); null when nothing is kept. They are erased after it. */
            'tax_documents_kept_until' => $until?->toDateString(),
            /** What was erased and what is kept, in the request's language, to show the person. */
            'message' => $until === null
                ? __('privacy.erased')
                : __('privacy.erased_except_tax_documents', ['date' => $until->isoFormat('LL')]),
        ];
    }
}
