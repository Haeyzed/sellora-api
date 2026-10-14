<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Promotions\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Generating random codes for a code promotion, such as single-use codes for a mailing. Needs promotions.manage.
 */
final class GeneratePromotionCodesRequest extends FormRequest
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
        $lengths = config()->array('promotions.generated_codes');

        return [
            /** How many. Up to 1,000 are made at once; more are made in the background. */
            'count' => ['required', 'integer:strict', 'min:1', 'max:'.$lengths['max_per_request']],
            /** How many random characters each has, after the prefix. 12 when left out. */
            'length' => ['sometimes', 'integer:strict', 'min:'.$lengths['min_length'], 'max:'.$lengths['max_length']],
            /** Put in front of every code, such as "XMAS-"; up to 12 letters, digits or "-". */
            'prefix' => ['sometimes', 'string', 'max:12', 'regex:/^[A-Za-z0-9-]*$/'],
            /** How many orders may use each code; 1 for single-use codes, null for no limit of their own. */
            'usage_limit' => ['sometimes', 'nullable', 'integer:strict', 'min:1'],
        ];
    }

    public function count(): int
    {
        return $this->integer('count');
    }

    public function length(): int
    {
        return $this->has('length') ? $this->integer('length') : config()->integer('promotions.generated_codes.default_length');
    }

    public function prefix(): string
    {
        return mb_strtoupper($this->string('prefix')->trim()->value());
    }

    public function usageLimit(): ?int
    {
        return $this->filled('usage_limit') ? $this->integer('usage_limit') : null;
    }
}
