<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Resources;

use App\Shared\Features\FeatureState;
use App\Tenant\Payments\Contracts\PaymentGateway;
use App\Tenant\Payments\Data\CredentialField;
use App\Tenant\Payments\Data\OfferedPaymentGateway;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment gateway the platform offers: what it can do, the credentials it needs, whether the store's plan includes it, and how the store is connected to it.
 *
 * @property-read OfferedPaymentGateway $resource
 */
final class PaymentGatewayResource extends JsonResource
{
    public function __construct(PaymentGateway $gateway, FeatureState $availability, ?PaymentGatewayConnection $connection)
    {
        parent::__construct(new OfferedPaymentGateway($gateway, $availability, $connection));
    }

    /**
     * @return array{key: string, currencies: list<string>, countries: list<string>|null, methods: list<string>, partial_refunds: bool, credential_fields: list<array{name: string, secret: bool}>, availability: string, connection: PaymentGatewayConnectionResource|null}
     */
    public function toArray(Request $request): array
    {
        $gateway = $this->resource->gateway;
        $connection = $this->resource->connection;
        $capabilities = $gateway->capabilities();

        return [
            /** Such as "paystack". */
            'key' => $gateway->key(),
            /** ISO 4217 codes it takes payments in. */
            'currencies' => $capabilities->currencies,
            /** Where buyers may pay from; null for anywhere. */
            'countries' => $capabilities->countries,
            /** How buyers pay on its page, such as "card" or "bank_transfer". */
            'methods' => $capabilities->methods,
            /** Whether it refunds part of a payment. */
            'partial_refunds' => $capabilities->partialRefunds,
            /** What to send in "credentials" to connect it. Secret ones are never shown again. */
            'credential_fields' => array_map(static fn (CredentialField $field): array => ['name' => $field->name, 'secret' => $field->isSecret], $gateway->credentialFields()),
            /** Whether the store's plan includes it: "enabled" to connect it and take new payments; otherwise only payments already under way are looked after. */
            'availability' => $this->resource->availability->value,
            /** Null when the store has never connected it. */
            'connection' => $connection === null ? null : new PaymentGatewayConnectionResource($connection),
        ];
    }
}
