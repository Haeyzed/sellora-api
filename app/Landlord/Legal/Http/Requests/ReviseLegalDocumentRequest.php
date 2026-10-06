<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Http\Requests;

use App\Landlord\Identity\Http\Requests\ActsAsPlatformAdmin;
use App\Landlord\Legal\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The new version label, title and text of a draft.
 */
final class ReviseLegalDocumentRequest extends FormRequest
{
    use ActsAsPlatformAdmin;
    use DescribesLegalDocumentText;

    public function authorize(): bool
    {
        $legalDocument = $this->route('legalDocument');

        return $legalDocument instanceof LegalDocument && $this->actor()->can('update', $legalDocument);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->textRules();
    }
}
