<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Contracts;

use App\Tenant\Payments\Data\CredentialCheck;
use App\Tenant\Payments\Data\CredentialField;
use App\Tenant\Payments\Data\GatewayCapabilities;
use App\Tenant\Payments\Data\GatewayCredentials;
use App\Tenant\Payments\Data\GatewayPaymentRequest;
use App\Tenant\Payments\Data\GatewayPaymentResult;
use App\Tenant\Payments\Data\GatewayRefundRequest;
use App\Tenant\Payments\Data\GatewayRefundResult;
use App\Tenant\Payments\Data\GatewayWebhookEvent;
use App\Tenant\Payments\Data\IncomingWebhook;
use App\Tenant\Payments\Data\StartedPayment;
use App\Tenant\Payments\Exceptions\GatewayRejectedException;
use App\Tenant\Payments\Exceptions\GatewayUnavailableException;

/**
 * One payment provider a store connects its own account to, such as Paystack, implemented by an integration (integrations/<Name>/).
 *
 * Shoppers pay on the gateway's hosted page, so card details never reach
 * our servers. Every call takes the store's credentials; the integration
 * never stores them. Amounts cross this contract as Money: each integration
 * converts to and from its gateway's own amount format, with tests. A call
 * that times out or gets a server error throws GatewayUnavailableException,
 * meaning the outcome is unknown, never that it failed.
 *
 * Its key is also the plan feature key that gates starting new payments
 * with it. Money already in motion (verifying, refunding, webhooks) is never
 * gated.
 */
interface PaymentGateway
{
    /**
     * The gateway's key, such as "paystack": also its integration's plan feature key.
     */
    public function key(): string;

    /**
     * What it can take payments in, from where, and how.
     */
    public function capabilities(): GatewayCapabilities;

    /**
     * The credentials a store enters to connect its account, with their validation rules.
     *
     * @return list<CredentialField>
     */
    public function credentialFields(): array;

    /**
     * Asks the gateway whether the credentials work, and which mode and account they belong to.
     *
     * @throws GatewayUnavailableException
     */
    public function checkCredentials(GatewayCredentials $credentials): CredentialCheck;

    /**
     * Starts a payment on the gateway's hosted page, with our reference as its reference and idempotency key.
     *
     * @throws GatewayRejectedException When the gateway refused to start it.
     * @throws GatewayUnavailableException
     */
    public function startPayment(GatewayCredentials $credentials, GatewayPaymentRequest $request): StartedPayment;

    /**
     * Asks the gateway, server-side, where a payment stands. The only source of a payment's status.
     *
     * @throws GatewayUnavailableException
     */
    public function verifyPayment(GatewayCredentials $credentials, string $reference): GatewayPaymentResult;

    /**
     * Asks the gateway to refund part or all of a payment, with our refund reference as its idempotency key, so sending it again never refunds twice.
     *
     * @throws GatewayRejectedException When the gateway refused the refund.
     * @throws GatewayUnavailableException
     */
    public function refund(GatewayCredentials $credentials, GatewayRefundRequest $request): GatewayRefundResult;

    /**
     * Asks the gateway where a refund stands, by our refund reference.
     *
     * @throws GatewayUnavailableException
     */
    public function verifyRefund(GatewayCredentials $credentials, string $refundReference): GatewayRefundResult;

    /**
     * Whether a webhook was really sent by the gateway for this account. Checked before anything in it is parsed, stored or acted on.
     */
    public function verifyWebhookSignature(GatewayCredentials $credentials, IncomingWebhook $webhook): bool;

    /**
     * What a verified webhook is about. It only says "go and ask": a status in it is never trusted.
     */
    public function parseWebhook(IncomingWebhook $webhook): GatewayWebhookEvent;
}
