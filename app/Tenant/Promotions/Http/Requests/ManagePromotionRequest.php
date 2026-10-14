<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Promotions\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff activating, deactivating or archiving a promotion, or deleting or changing a code. Needs promotions.manage.
 */
final class ManagePromotionRequest extends FormRequest
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
        return [];
    }
}
