<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Http\Resources;

use App\Landlord\Legal\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A legal document version as merchants read it before accepting it.
 *
 * @property LegalDocument $resource
 */
final class LegalDocumentResource extends JsonResource
{
    public function __construct(LegalDocument $legalDocument)
    {
        parent::__construct($legalDocument);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** Send this ID to accept the version. */
            'id' => $this->resource->public_id,
            'type' => $this->resource->type->value,
            'version' => $this->resource->version,
            'title' => $this->resource->title,
            /** The full text, in Markdown. */
            'body' => $this->resource->body,
            'effective_at' => $this->resource->effective_at?->toIso8601String(),
        ];
    }
}
