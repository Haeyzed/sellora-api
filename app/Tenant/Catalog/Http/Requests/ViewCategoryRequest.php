<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Models\Category;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Looking at one category, in the trash or not.
 */
final class ViewCategoryRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('view', Category::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
