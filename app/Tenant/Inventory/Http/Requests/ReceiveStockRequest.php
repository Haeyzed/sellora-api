<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Inventory\Models\StockItem;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Recording stock that arrived, which needs the inventory.adjust permission.
 */
final class ReceiveStockRequest extends FormRequest
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
            /** Units that arrived, a whole number from 1. */
            'quantity' => ['required', 'integer:strict', 'min:1', 'max:1000000000'],
            /** Anything worth remembering, such as the supplier's delivery note, up to 500 characters. */
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function quantity(): int
    {
        return $this->integer('quantity');
    }

    public function note(): ?string
    {
        return $this->filled('note') ? trim($this->string('note')->value()) : null;
    }
}
