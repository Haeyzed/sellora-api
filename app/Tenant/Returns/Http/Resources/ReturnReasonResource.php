<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Resources;

use App\Tenant\Returns\Enums\ReturnReason;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One reason a buyer can give for a return.
 *
 * @property ReturnReason $resource
 */
final class ReturnReasonResource extends JsonResource
{
    public function __construct(ReturnReason $reason)
    {
        parent::__construct($reason);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** What to send as a return's reason. */
            'code' => $this->resource->value,
            /** Its name in the language asked for. */
            'label' => $this->resource->label(),
            /** The store's fault (damage or the wrong item): the store may require a photo for it. */
            'stores_fault' => $this->resource->isStoresFault(),
        ];
    }
}
