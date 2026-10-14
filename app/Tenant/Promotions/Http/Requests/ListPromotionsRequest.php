<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Requests;

use App\Shared\Http\PageListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Promotions\Enums\PromotionMethod;
use App\Tenant\Promotions\Enums\PromotionStatus;
use App\Tenant\Promotions\Enums\PromotionType;
use App\Tenant\Promotions\Models\Promotion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * A page of the store's promotions, newest first, optionally narrowed. Archived ones only when asked for. Needs promotions.view.
 */
final class ListPromotionsRequest extends PageListRequest
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
            /** Where it stands now: running, scheduled, ended, inactive or archived. Without it, every promotion that isn't archived. */
            'status' => ['sometimes', Rule::enum(PromotionStatus::class)],
            'method' => ['sometimes', Rule::enum(PromotionMethod::class)],
            'type' => ['sometimes', Rule::enum(PromotionType::class)],
            /** Text to find in the staff name, ignoring case. */
            'search' => ['sometimes', 'string', 'min:1', 'max:100'],
        ];
    }

    /**
     * @param  Builder<Promotion>  $query
     */
    public function applyFilters(Builder $query): void
    {
        $status = $this->enum('status', PromotionStatus::class);

        if ($status !== null) {
            $query->withStatus($status);
        }

        foreach (['method' => PromotionMethod::class, 'type' => PromotionType::class] as $key => $enum) {
            $value = $this->enum($key, $enum);

            if ($value !== null) {
                $query->where($key, $value->value);
            }
        }

        $search = $this->validated('search');

        if (is_string($search)) {
            $query->whereRaw('lower(name) like ?', ['%'.addcslashes(mb_strtolower(trim($search)), '%_\\').'%']);
        }
    }
}
