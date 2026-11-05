<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Returns\Data\FaultConfirmation;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff saying what became of a received return's units, at an active location (the store's default when left out). Needs the returns.manage permission.
 */
final class InspectReturnRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ReadsReturnLines;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', OrderReturn::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /** Whether the store was at fault; leave it out to confirm what the buyer's reason claims (damaged, wrong item, faulty and not as described claim it was). Only a confirmed fault gives a free like-for-like exchange and free delivery. */
            'stores_fault' => ['sometimes', 'boolean'],
            /** Why, when stores_fault differs from what the reason claims; required then. Not for personal data. */
            'fault_overruled_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            /** The active location the units were checked at; the store's default location when left out. */
            'location' => ['sometimes', 'string', Rule::exists(StockLocation::class, 'public_id')->where('is_active', true)],
            /** Every line of the return, once each. */
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            /** The order line's ID. */
            'lines.*.line' => ['required', 'string', 'distinct'],
            /** Units back on the shelf, ready to sell again. */
            'lines.*.restocked' => ['required', 'integer', 'min:0', 'max:100000'],
            /** Units written off as damaged. Restocked and written off add up to the line's quantity. */
            'lines.*.written_off' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function stockLocation(): StockLocation
    {
        $location = $this->validated('location');

        return is_string($location)
            ? StockLocation::query()->where('public_id', $location)->sole()
            : StockLocation::query()->where('is_default', true)->sole();
    }

    /**
     * @return array<string, array{restocked: int, written_off: int}>
     */
    public function outcomes(): array
    {
        $validated = $this->safe();
        $outcomes = [];

        foreach ($this->linePositions($validated) as $position) {
            $outcomes[$this->lineOf($validated, $position)] = [
                'restocked' => $validated->integer("lines.{$position}.restocked"),
                'written_off' => $validated->integer("lines.{$position}.written_off"),
            ];
        }

        return $outcomes;
    }

    /**
     * Whether the store was at fault, as staff say: null confirms what the reason claims.
     */
    public function faultConfirmation(): FaultConfirmation
    {
        $reason = $this->validated('fault_overruled_reason');

        return new FaultConfirmation($this->has('stores_fault') ? $this->boolean('stores_fault') : null, is_string($reason) ? $reason : null);
    }
}
