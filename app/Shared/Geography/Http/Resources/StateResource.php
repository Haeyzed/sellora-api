<?php

declare(strict_types=1);

namespace App\Shared\Geography\Http\Resources;

use App\Shared\Geography\State;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A country's state, province or region, for address forms.
 *
 * @property State $resource
 */
final class StateResource extends JsonResource
{
    public function __construct(State $state)
    {
        parent::__construct($state);
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            /** The code to send in addresses, such as "LA" for Lagos. */
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            /** Such as "state", "province" or "capital territory". */
            'type' => $this->resource->type,
        ];
    }
}
