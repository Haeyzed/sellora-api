<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Resources;

use App\Shared\Features\FeatureState;
use App\Tenant\Payments\Contracts\PaymentGateway;
use App\Tenant\Payments\Data\CredentialField;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment gateway the platform offers: what it can do, the credentials it needs, whether the store's plan includes it, and how the store is connected to it.
 *
 * @property PaymentGateway $resource
 */
final class PaymentGatewayResource extends JsonResource
{
    public function __construct(
        PaymentGateway $gateway,
        private readonly FeatureState $availability,
        private readonly ?PaymentGatewayConnection $connection,
    ) {
        parent::__construct($gateway);
    }

    /**
     * @return array{key: string, currencies: list<string>, countries: list<string>|null, methods: list<string>, partial_refunds: bool, credential_fields: list<array{name: string, secret: bool}>, availability: string, connection: PaymentGatewayConnectionResource|null}
     */
    public function toArray(Request $request): array
    {
        $gateway = $this->resource;
        $capabilities = $gateway->capabilities();

        return [
            /** @var string Such as "paystack". */
            'key' => $gateway->key(),
            /** @var list<string> ISO 4217 codes it takes payments in. */
            'currencies' => $capabilities->currencies,
            /** @var list<string>|null Where buyers may pay from; null for anywhere. */
            'countries' => $capabilities->countries,
            /** @var list<string> How buyers pay on its page, such as "card" or "bank_transfer". */
            'methods' => $capabilities->methods,
            /** @var bool Whether it refunds part of a payment. */
            'partial_refunds' => $capabilities->partialRefunds,
            /** What to send in "credentials" to connect it. Secret ones are never shown again. */
            'credential_fields' => array_map(static fn (CredentialField $field): array => ['name' => $field->name, 'secret' => $field->isSecret], $gateway->credentialFields()),
            /** Whether the store's plan includes it: "enabled" to connect it and take new payments; otherwise only payments already under way are looked after. */
            'availability' => $this->availability->value,
            /** Null when the store has never connected it. */
            'connection' => $this->connection === null ? null : new PaymentGatewayConnectionResource($this->connection),
        ];
    }
}
