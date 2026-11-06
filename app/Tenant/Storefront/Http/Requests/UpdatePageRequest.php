<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Storefront\Models\Page;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changing a page, which needs the storefront.manage permission. Send only what changes; for texts, only the languages sent change.
 */
final class UpdatePageRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesPageTexts;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Page::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->pageTextRules(creating: false);
    }
}
