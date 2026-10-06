<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Tenant\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shopper's account as the storefront sees it.
 *
 * @property Customer $resource
 */
final class CustomerResource extends JsonResource
{
    public function __construct(Customer $customer)
    {
        parent::__construct($customer);
    }

    /**
     * @return array{id: string, name: string, email: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
        ];
    }
}
