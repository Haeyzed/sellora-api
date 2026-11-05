<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Resources;

use App\Tenant\Payments\Data\OfferedRefundDestination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Somewhere the customer may choose to have a return's refund go: back to how they paid, or a destination a module offers, such as store credit.
 *
 * @property OfferedRefundDestination $resource
 */
final class RefundDestinationResource extends JsonResource
{
    public function __construct(OfferedRefundDestination $destination)
    {
        parent::__construct($destination);
    }

    /**
     * @return array{key: string, label: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** What to send as the refund destination: "original_payment", always offered, or a module's key such as "store_credit". */
            'key' => $this->resource->key,
            /** Its name in the language asked for. */
            'label' => $this->resource->label,
        ];
    }
}
