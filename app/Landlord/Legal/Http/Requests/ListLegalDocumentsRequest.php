<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Http\Requests;

use App\Landlord\Identity\Http\Requests\ActsAsPlatformAdmin;
use App\Landlord\Legal\Enums\LegalDocumentType;
use App\Landlord\Legal\Models\LegalDocument;
use App\Shared\Http\PaginatedListRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * A page of legal document versions, drafts included, optionally of one type.
 */
final class ListLegalDocumentsRequest extends PaginatedListRequest
{
    use ActsAsPlatformAdmin;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', LegalDocument::class);
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'type' => ['sometimes', Rule::enum(LegalDocumentType::class)],
        ];
    }

    public function type(): ?LegalDocumentType
    {
        return $this->filled('type') ? $this->enum('type', LegalDocumentType::class) : null;
    }
}
