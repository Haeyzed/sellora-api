<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Http\Requests;

use App\Landlord\Identity\Http\Requests\ActsAsPlatformAdmin;
use App\Landlord\Legal\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Viewing one legal document version, draft or published.
 */
final class ViewLegalDocumentRequest extends FormRequest
{
    use ActsAsPlatformAdmin;

    public function authorize(): bool
    {
        $legalDocument = $this->route('legalDocument');

        return $legalDocument instanceof LegalDocument && $this->actor()->can('view', $legalDocument);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
