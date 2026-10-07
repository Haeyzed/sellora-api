<?php

declare(strict_types=1);

namespace App\Shared\Geography\Http\Resources;

use App\Shared\Geography\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An ISO 4217 currency in use today.
 *
 * @property Currency $resource
 */
final class CurrencyResource extends JsonResource
{
    public function __construct(Currency $currency)
    {
        parent::__construct($currency);
    }

    /**
     * @return array<string, string|int>
     */
    public function toArray(Request $request): array
    {
        return [
            /** Such as "NGN". */
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            /** Of its minor unit: 2 for NGN, 0 for JPY, 3 for KWD. */
            'decimal_places' => $this->resource->decimalPlaces,
        ];
    }
}
