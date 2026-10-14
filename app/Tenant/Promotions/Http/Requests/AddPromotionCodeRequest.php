<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Models\PromotionCode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Adding one code to a code promotion, such as "WELCOME10". Needs promotions.manage.
 */
final class AddPromotionCodeRequest extends FormRequest
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
            /** 3 to 32 letters, digits, "-" or "_", unique in the store ignoring case. Shown as written; customers may type it in any case. */
            'code' => ['required', 'string', 'regex:'.PromotionCode::FORMAT],
            /** How many orders may use this code; null for no limit of its own (the promotion's limits still apply). */
            'usage_limit' => ['sometimes', 'nullable', 'integer:strict', 'min:1'],
        ];
    }

    public function code(): string
    {
        return trim($this->string('code')->value());
    }

    public function usageLimit(): ?int
    {
        return $this->filled('usage_limit') ? $this->integer('usage_limit') : null;
    }
}
