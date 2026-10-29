<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff saying what became of a received return's units, at an active location (the store's default when left out). Needs the returns.manage permission.
 */
final class InspectReturnRequest extends FormRequest
{
    use ActsAsStaffMember;

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
        $outcomes = [];

        /** @var list<array{line: string, restocked: int|string, written_off: int|string}> $lines */
        $lines = $this->validated('lines');

        foreach ($lines as $line) {
            $outcomes[$line['line']] = ['restocked' => (int) $line['restocked'], 'written_off' => (int) $line['written_off']];
        }

        return $outcomes;
    }
}
