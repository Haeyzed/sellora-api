<?php

declare(strict_types=1);

namespace Integrations\Paystack;

use App\Tenant\Payments\Contracts\PaymentGateway;
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
use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Exceptions\GatewayRejectedException;
use App\Tenant\Payments\Exceptions\GatewayUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use LogicException;

/**
 * Paystack (Africa: cards, bank transfer, USSD, mobile money), for stores that connect their own Paystack account (section 3.3).
 *
 * A store connects with its secret key alone: shoppers pay on Paystack's
 * hosted page, so no public key is needed, and the same key signs Paystack's
 * webhooks. Test or live comes from the key's prefix. The key travels only
 * in the Authorization header, never in a URL, a log or an error.
 *
 * Step 16b.1 is the skeleton: what it takes, its credentials and checking
 * them. Taking payments (16b.2), refunds (16b.3) and webhooks (16b.4) come
 * next; until then each refuses without calling Paystack, the way that
 * leaves nothing to clean up.
 */
final readonly class PaystackGateway implements PaymentGateway
{
    public const string KEY = 'paystack';

    private const string SECRET_KEY = 'secret_key';

    public function __construct(private HttpFactory $http) {}

    public function key(): string
    {
        return self::KEY;
    }

    /**
     * Only currencies whose Paystack units a real test-mode payment has confirmed (PaystackAmounts), for buyers in the countries Paystack serves them.
     */
    public function capabilities(): GatewayCapabilities
    {
        return new GatewayCapabilities(
            currencies: array_keys(PaystackAmounts::CONFIRMED),
            countries: ['NG', 'GH', 'ZA', 'KE'],
            methods: ['card', 'bank_transfer', 'ussd', 'mobile_money'],
            partialRefunds: true,
        );
    }

    public function credentialFields(): array
    {
        return [new CredentialField(self::SECRET_KEY, ['regex:/^sk_(test|live)_[A-Za-z0-9]+$/'])];
    }

    /**
     * Asks Paystack for the account's balance, which needs a working secret key; a key not shaped like one is refused without being sent anywhere.
     *
     * @throws GatewayUnavailableException
     */
    public function checkCredentials(GatewayCredentials $credentials): CredentialCheck
    {
        $secretKey = $credentials->get(self::SECRET_KEY);
        $mode = self::modeOf($secretKey);

        if ($mode === null) {
            return new CredentialCheck(false, GatewayMode::Test);
        }

        try {
            $response = $this->client($credentials)->get('balance');
        } catch (ConnectionException $exception) {
            throw new GatewayUnavailableException(self::KEY, 'checkCredentials', $exception);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return new CredentialCheck(false, $mode);
        }

        // A server error, a rate limit or anything else unexpected says nothing about the key.
        if (! $response->successful() || $response->json('status') !== true) {
            throw new GatewayUnavailableException(self::KEY, 'checkCredentials');
        }

        return new CredentialCheck(true, $mode, hint: '…'.mb_substr($secretKey, -4));
    }

    /**
     * Not until Step 16b.2: refused without calling Paystack, so nothing was started.
     *
     * @throws GatewayRejectedException
     */
    public function startPayment(GatewayCredentials $credentials, GatewayPaymentRequest $request): StartedPayment
    {
        throw new GatewayRejectedException(self::KEY, 'startPayment', 'not_available_yet');
    }

    /**
     * Not until Step 16b.2. No Paystack payment can exist before then; were one asked about, its outcome stays unknown, never failed.
     *
     * @throws GatewayUnavailableException
     */
    public function verifyPayment(GatewayCredentials $credentials, string $reference): GatewayPaymentResult
    {
        throw new GatewayUnavailableException(self::KEY, 'verifyPayment');
    }

    /**
     * Not until Step 16b.3: refused without calling Paystack, so nothing was refunded.
     *
     * @throws GatewayRejectedException
     */
    public function refund(GatewayCredentials $credentials, GatewayRefundRequest $request): GatewayRefundResult
    {
        throw new GatewayRejectedException(self::KEY, 'refund', 'not_available_yet');
    }

    /**
     * Not until Step 16b.3: its outcome stays unknown, never "not found", so nothing is ever sent again on its strength.
     *
     * @throws GatewayUnavailableException
     */
    public function verifyRefund(GatewayCredentials $credentials, string $refundReference): GatewayRefundResult
    {
        throw new GatewayUnavailableException(self::KEY, 'verifyRefund');
    }

    /**
     * Not until Step 16b.4: every webhook is refused unread, and Paystack sends it again later.
     */
    public function verifyWebhookSignature(GatewayCredentials $credentials, IncomingWebhook $webhook): bool
    {
        return false;
    }

    /**
     * Only ever called for a webhook whose signature checked out, which none does before Step 16b.4.
     */
    public function parseWebhook(IncomingWebhook $webhook): GatewayWebhookEvent
    {
        throw new LogicException('Paystack webhooks are not read before Step 16b.4.');
    }

    /**
     * Test or live from the secret key's prefix; null for anything not shaped like a Paystack secret key.
     */
    private static function modeOf(string $secretKey): ?GatewayMode
    {
        return match (true) {
            preg_match('/^sk_test_[A-Za-z0-9]+$/', $secretKey) === 1 => GatewayMode::Test,
            preg_match('/^sk_live_[A-Za-z0-9]+$/', $secretKey) === 1 => GatewayMode::Live,
            default => null,
        };
    }

    private function client(GatewayCredentials $credentials): PendingRequest
    {
        return $this->http
            ->baseUrl(config()->string('paystack.base_url'))
            ->timeout(config()->integer('paystack.timeout_in_seconds'))
            ->connectTimeout(config()->integer('paystack.connect_timeout_in_seconds'))
            ->withToken($credentials->get(self::SECRET_KEY))
            ->acceptJson();
    }
}
