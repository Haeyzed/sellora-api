<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Shared\Http\PageListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Storefront\Enums\PageStatus;
use App\Tenant\Storefront\Models\Page;
use Illuminate\Validation\Rule;

/**
 * A page of the store's pages, of one status, or of the pages in the trash.
 */
final class ListPagesRequest extends PageListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Page::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** "draft" or "published"; every page when left out. */
            'status' => ['sometimes', 'string', Rule::enum(PageStatus::class)],
            /** True for the pages in the trash instead of the others. */
            'trashed' => ['sometimes', 'boolean'],
        ];
    }

    public function status(): ?PageStatus
    {
        return $this->has('status') ? $this->enum('status', PageStatus::class) : null;
    }

    public function wantsTrashed(): bool
    {
        return $this->boolean('trashed');
    }
}
