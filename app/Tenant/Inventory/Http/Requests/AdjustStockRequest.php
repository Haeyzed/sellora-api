<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Inventory\Enums\AdjustmentReason;
use App\Tenant\Inventory\Models\StockItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Correcting a variant's stock count, by an amount or to a counted number, which needs the inventory.adjust permission.
 */
final class AdjustStockRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ChoosesStockLocation;

    public function authorize(): bool
    {
        return $this->actor()->can('update', StockItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The location's ID; the store's default location when left out. */
            'location' => $this->locationRules(),
            /** Units added (positive) or removed (negative), such as -2. Send this or count. */
            'change' => ['required_without:count', 'prohibits:count', 'integer:strict', 'not_in:0', 'min:-1000000000', 'max:1000000000'],
            /** The number now on hand after counting, such as 17. Send this or change. */
            'count' => ['required_without:change', 'integer:strict', 'min:0', 'max:1000000000'],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            /** Anything worth remembering, up to 500 characters. */
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function change(): ?int
    {
        return $this->has('change') ? $this->integer('change') : null;
    }

    public function count(): ?int
    {
        return $this->has('count') ? $this->integer('count') : null;
    }

    public function reason(): AdjustmentReason
    {
        return AdjustmentReason::from($this->string('reason')->value());
    }

    public function note(): ?string
    {
        return $this->filled('note') ? trim($this->string('note')->value()) : null;
    }
}
