<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Shared\Http\PageListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Storefront\Enums\BannerKind;
use App\Tenant\Storefront\Models\Banner;
use Illuminate\Validation\Rule;

/**
 * A page of the storefront's banners, of one kind or in one place, which needs the storefront.view permission.
 */
final class ListBannersRequest extends PageListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Banner::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** "image" or "announcement"; both when left out. */
            'kind' => ['sometimes', 'string', Rule::enum(BannerKind::class)],
            /** Only the image banners in this place, such as "home_hero". */
            'position' => ['sometimes', 'string', 'max:64'],
        ];
    }

    public function kind(): ?BannerKind
    {
        return $this->has('kind') ? $this->enum('kind', BannerKind::class) : null;
    }

    public function position(): ?string
    {
        $position = $this->validated('position');

        return is_string($position) ? $position : null;
    }
}
