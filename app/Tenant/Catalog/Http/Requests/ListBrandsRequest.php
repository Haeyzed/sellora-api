<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Shared\Http\PaginatedListRequest;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;

/**
 * A page of the store's brands, or of the brands in the trash.
 */
final class ListBrandsRequest extends PaginatedListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Brand::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** True for the brands in the trash instead of the others. */
            'trashed' => ['sometimes', 'boolean'],
        ];
    }

    public function wantsTrashed(): bool
    {
        return $this->boolean('trashed');
    }
}
