<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Http\Resources;

use App\Landlord\Legal\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A legal document version as Sellora's team manages it, drafts included.
 *
 * @property LegalDocument $resource
 */
final class ManagedLegalDocumentResource extends JsonResource
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
            'id' => $this->resource->public_id,
            'type' => $this->resource->type->value,
            'version' => $this->resource->version,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            /** "draft" (can still change) or "published" (never changes). */
            'status' => $this->resource->isPublished() ? 'published' : 'draft',
            'published_at' => $this->resource->published_at?->toIso8601String(),
            /** When it takes, or took, effect. A later published version replaces it from its own effective date. */
            'effective_at' => $this->resource->effective_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
