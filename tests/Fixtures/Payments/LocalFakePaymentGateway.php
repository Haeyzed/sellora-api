<?php

declare(strict_types=1);

namespace Tests\Fixtures\Payments;

use App\Tenant\Payments\Contracts\PaymentGateway;
use App\Tenant\Payments\Data\CredentialCheck;
use App\Tenant\Payments\Data\GatewayCapabilities;
use App\Tenant\Payments\Data\GatewayCredentials;
use App\Tenant\Payments\Data\GatewayPaymentRequest;
use App\Tenant\Payments\Data\GatewayPaymentResult;
use App\Tenant\Payments\Data\GatewayRefundRequest;
use App\Tenant\Payments\Data\GatewayRefundResult;
use App\Tenant\Payments\Data\GatewayWebhookEvent;
use App\Tenant\Payments\Data\IncomingWebhook;
use App\Tenant\Payments\Data\StartedPayment;

/**
 * A second fake gateway that only takes naira from buyers in Nigeria, for choosing between gateways. Otherwise it answers like the fake gateway.
 */
final readonly class LocalFakePaymentGateway implements PaymentGateway
{
    public const string KEY = 'local_fake_gateway';

    public function __construct(private FakePaymentGateway $fake) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function capabilities(): GatewayCapabilities
    {
        return new GatewayCapabilities(['NGN'], ['NG'], ['card', 'ussd']);
    }

    public function credentialFields(): array
    {
        return $this->fake->credentialFields();
    }

    public function checkCredentials(GatewayCredentials $credentials): CredentialCheck
    {
        return $this->fake->checkCredentials($credentials);
    }

    public function startPayment(GatewayCredentials $credentials, GatewayPaymentRequest $request): StartedPayment
    {
        return $this->fake->startPayment($credentials, $request);
    }

    public function verifyPayment(GatewayCredentials $credentials, string $reference): GatewayPaymentResult
    {
        return $this->fake->verifyPayment($credentials, $reference);
    }

    public function refund(GatewayCredentials $credentials, GatewayRefundRequest $request): GatewayRefundResult
    {
        return $this->fake->refund($credentials, $request);
    }

    public function verifyRefund(GatewayCredentials $credentials, GatewayRefundRequest $request, ?string $gatewayRefundReference): GatewayRefundResult
    {
        return $this->fake->verifyRefund($credentials, $request, $gatewayRefundReference);
    }

    public function verifyWebhookSignature(GatewayCredentials $credentials, IncomingWebhook $webhook): bool
    {
        return $this->fake->verifyWebhookSignature($credentials, $webhook);
    }

    public function parseWebhook(IncomingWebhook $webhook): GatewayWebhookEvent
    {
        return $this->fake->parseWebhook($webhook);
    }
}
