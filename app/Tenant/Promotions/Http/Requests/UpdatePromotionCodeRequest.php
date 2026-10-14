<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Promotions\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Switching a code off or on, or changing its limit. The code itself never changes. Needs promotions.manage.
 */
final class UpdatePromotionCodeRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Promotion::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean:strict'],
            /** How many orders may use this code; null for no limit of its own. */
            'usage_limit' => ['sometimes', 'nullable', 'integer:strict', 'min:1'],
        ];
    }

    /**
     * @return array{is_active?: bool, usage_limit?: int|null}
     */
    public function changes(): array
    {
        $changes = [];

        if ($this->has('is_active')) {
            $changes['is_active'] = $this->boolean('is_active');
        }

        if ($this->has('usage_limit')) {
            $changes['usage_limit'] = $this->filled('usage_limit') ? $this->integer('usage_limit') : null;
        }

        return $changes;
    }
}
