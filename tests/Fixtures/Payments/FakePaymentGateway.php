<?php

declare(strict_types=1);

namespace Tests\Fixtures\Payments;

use App\Shared\Money\Money;
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
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Enums\GatewayRefundState;
use App\Tenant\Payments\Enums\GatewayWebhookKind;
use App\Tenant\Payments\Exceptions\GatewayRejectedException;
use App\Tenant\Payments\Exceptions\GatewayUnavailableException;
use Illuminate\Support\Facades\DB;

/**
 * A payment gateway for tests that answers as the test's script says. Keys starting "sk_test_" are test keys and "sk_live_" live ones; anything else is refused. Webhooks are signed with HMAC-SHA256 of the body with the secret key, in "X-Fake-Signature".
 */
final readonly class FakePaymentGateway implements PaymentGateway
{
    public const string KEY = 'fake_gateway';

    public function __construct(private FakeGatewayScript $script) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function capabilities(): GatewayCapabilities
    {
        return new GatewayCapabilities(['NGN', 'USD', 'KWD', 'JPY', 'GBP'], null, ['card', 'bank_transfer'], $this->script->partialRefunds);
    }

    public function credentialFields(): array
    {
        return [new CredentialField('secret_key', ['min:12']), new CredentialField('public_key', ['min:12'], isSecret: false)];
    }

    public function checkCredentials(GatewayCredentials $credentials): CredentialCheck
    {
        $this->call('checkCredentials', '');
        $secret = $credentials->get('secret_key');
        $mode = str_starts_with($secret, 'sk_live_') ? GatewayMode::Live : GatewayMode::Test;
        $isValid = ! $this->script->refusesKeys && (str_starts_with($secret, 'sk_test_') || str_starts_with($secret, 'sk_live_'));

        return new CredentialCheck($isValid, $mode, $this->script->accounts[$secret] ?? 'acct_main', '…'.mb_substr($credentials->get('public_key'), -4));
    }

    public function startPayment(GatewayCredentials $credentials, GatewayPaymentRequest $request): StartedPayment
    {
        $this->call('startPayment', $request->reference);
        $this->script->startedInTransactionLevels[] = DB::transactionLevel();

        if ($this->script->rejectsPayments) {
            throw new GatewayRejectedException(self::KEY, 'startPayment', 'invalid_request');
        }

        return new StartedPayment('https://pay.fake.test/'.$request->reference, 'fake_'.$request->reference);
    }

    public function verifyPayment(GatewayCredentials $credentials, string $reference, Money $expectedAmount): GatewayPaymentResult
    {
        $this->call('verifyPayment', $reference);
        $this->script->expectedAmounts[$reference] = $expectedAmount;

        return $this->script->payments[$reference] ?? new GatewayPaymentResult(GatewayPaymentState::Pending);
    }

    public function refund(GatewayCredentials $credentials, GatewayRefundRequest $request): GatewayRefundResult
    {
        $this->call('refund', $request->refundReference);

        if ($this->script->rejectsRefunds) {
            throw new GatewayRejectedException(self::KEY, 'refund', 'refund_refused');
        }

        return $this->script->refunds[$request->refundReference]
            ?? $this->script->refundAnswer
            ?? new GatewayRefundResult(GatewayRefundState::Succeeded, 'fake_'.$request->refundReference, $request->amount);
    }

    public function verifyRefund(GatewayCredentials $credentials, GatewayRefundRequest $request, ?string $gatewayRefundReference): GatewayRefundResult
    {
        $refundReference = $request->refundReference;
        $this->call('verifyRefund', $refundReference);
        $this->script->refundLookups[] = [$refundReference, $gatewayRefundReference];
        $wasSent = $this->script->timesCalled('refund', $refundReference) > 0;

        return $this->script->refunds[$refundReference]
            ?? ($wasSent ? $this->script->refundAnswer : null)
            ?? new GatewayRefundResult(GatewayRefundState::NotFound);
    }

    public function verifyWebhookSignature(GatewayCredentials $credentials, IncomingWebhook $webhook): bool
    {
        $signature = $webhook->header('x-fake-signature');

        return $signature !== null && hash_equals(hash_hmac('sha256', $webhook->body, $credentials->get('secret_key')), $signature);
    }

    public function parseWebhook(IncomingWebhook $webhook): GatewayWebhookEvent
    {
        /** @var array{id?: string, type?: string, reference?: string} $payload */
        $payload = (array) json_decode($webhook->body, true);

        return new GatewayWebhookEvent(
            (string) ($payload['id'] ?? ''),
            GatewayWebhookKind::tryFrom((string) ($payload['type'] ?? '')) ?? GatewayWebhookKind::Ignored,
            $payload['reference'] ?? null,
        );
    }

    /**
     * @throws GatewayUnavailableException
     */
    private function call(string $operation, string $reference): void
    {
        $this->script->calls[] = [$operation, $reference];

        // A refused key is an answer to a check of the keys, not an error.
        if ($this->script->refusesKeys && $operation !== 'checkCredentials') {
            throw GatewayUnavailableException::keysRefused(self::KEY, $operation);
        }

        if ($this->script->unavailable) {
            throw new GatewayUnavailableException(self::KEY, $operation);
        }
    }
}
