<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Models\PromotionCode;
use Illuminate\Database\Eloquent\Builder;

/**
 * A page of a promotion's codes, newest first, optionally those starting with some text. Needs promotions.view.
 */
final class ListPromotionCodesRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Promotion::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** The start of the code, ignoring case. */
            'search' => ['sometimes', 'string', 'min:1', 'max:32'],
            /** True for codes switched on only, false for those switched off only. */
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  Builder<PromotionCode>  $query
     */
    public function applyFilters(Builder $query): void
    {
        $search = $this->validated('search');

        if (is_string($search)) {
            $query->whereRaw('upper(code) like ?', [addcslashes(mb_strtoupper(trim($search)), '%_\\').'%']);
        }

        if ($this->has('is_active')) {
            $query->where('is_active', $this->boolean('is_active'));
        }
    }
}
