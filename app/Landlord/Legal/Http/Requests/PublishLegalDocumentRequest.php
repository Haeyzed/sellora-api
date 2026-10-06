<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Http\Requests;

use App\Landlord\Identity\Http\Requests\ActsAsPlatformAdmin;
use App\Landlord\Legal\Models\LegalDocument;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * When a draft being published takes effect.
 */
final class PublishLegalDocumentRequest extends FormRequest
{
    use ActsAsPlatformAdmin;

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
        return [
            /** When it takes effect, ISO 8601. Now if left out; can't be in the past. */
            'effective_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:now'],
        ];
    }

    public function effectiveAt(): ?CarbonImmutable
    {
        return $this->filled('effective_at') ? CarbonImmutable::parse($this->string('effective_at')->value()) : null;
    }
}
