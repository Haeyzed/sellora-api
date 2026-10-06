<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Http\Requests;

use App\Landlord\Identity\Http\Requests\ActsAsPlatformAdmin;
use App\Landlord\Legal\Enums\LegalDocumentType;
use App\Landlord\Legal\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use LogicException;

/**
 * A new draft version of a legal document.
 */
final class DraftLegalDocumentRequest extends FormRequest
{
    use ActsAsPlatformAdmin;
    use DescribesLegalDocumentText;

    public function authorize(): bool
    {
        return $this->actor()->can('create', LegalDocument::class);
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(LegalDocumentType::class)],
            ...$this->textRules(),
        ];
    }

    public function type(): LegalDocumentType
    {
        return $this->enum('type', LegalDocumentType::class) ?? throw new LogicException('The type is validated as required.');
    }
}
