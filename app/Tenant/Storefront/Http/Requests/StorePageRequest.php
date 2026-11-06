<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Storefront\Models\Page;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Adding a page, which needs the storefront.manage permission.
 */
final class StorePageRequest extends FormRequest
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
        return $this->pageTextRules(creating: true);
    }
}
