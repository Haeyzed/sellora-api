<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Promotions\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff looking at one promotion. Needs promotions.view.
 */
final class ViewPromotionRequest extends FormRequest
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
        return [];
    }
}
