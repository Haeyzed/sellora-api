<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Storefront\Models\Page;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Publishing, unpublishing, trashing or restoring a page, or making it the homepage, which needs the storefront.manage permission.
 */
final class ManagePageRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Page::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
