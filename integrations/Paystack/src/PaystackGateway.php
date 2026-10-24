<?php

declare(strict_types=1);

namespace Integrations\Paystack;

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
use App\Tenant\Payments\Exceptions\GatewayRejectedException;
use App\Tenant\Payments\Exceptions\GatewayUnavailableException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Integrations\Paystack\Exceptions\PaystackAmountException;
use LogicException;

/**
 * Paystack (Africa: cards, bank transfer, USSD, mobile money), for stores that connect their own Paystack account (section 3.3).
 *
 * A store connects with its secret key alone: shoppers pay on Paystack's
 * hosted page, so no public key is needed, and the same key signs Paystack's
 * webhooks. Test or live comes from the key's prefix. The key travels only
 * in the Authorization header, never in a URL, a log or an error.
 *
 * Payments start on Paystack's hosted page and are verified by our
 * reference, strictly: a payment counts only when Paystack took exactly the
 * order's amount, so a merchant who passes Paystack's fees on to buyers gets
 * every such payment in review, for now. Refunds carry our reference as
 * their merchant note and are found again by Paystack's ID for them, or by
 * that note. Webhooks (16b.4) come next; until then each is refused unread.
 */
final readonly class PaystackGateway implements PaymentGateway
{
    public const string KEY = 'paystack';

    private const string SECRET_KEY = 'secret_key';

    public function __construct(
        private HttpFactory $http,
        private PaystackAmounts $amounts,
    ) {}

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

        if (self::refusesKey($response)) {
            return new CredentialCheck(false, $mode);
        }

        // A server error, a rate limit or anything else unexpected says nothing about the key.
        if (! $response->successful() || $response->json('status') !== true) {
            throw new GatewayUnavailableException(self::KEY, 'checkCredentials');
        }

        return new CredentialCheck(true, $mode, hint: '…'.mb_substr($secretKey, -4));
    }

    /**
     * Initializes a Paystack transaction with our reference as its reference, which Paystack refuses to use twice, so it is never started twice. Paystack is sent only the buyer's email and the order number.
     *
     * @throws GatewayRejectedException
     * @throws GatewayUnavailableException
     */
    public function startPayment(GatewayCredentials $credentials, GatewayPaymentRequest $request): StartedPayment
    {
        try {
            $amount = $this->amounts->toPaystack($request->amount);
        } catch (PaystackAmountException) {
            throw new GatewayRejectedException(self::KEY, 'startPayment', 'amount_not_supported');
        }

        $response = $this->send('startPayment', static fn (PendingRequest $paystack): Response => $paystack->post('transaction/initialize', [
            'email' => $request->buyerEmail,
            'amount' => (string) $amount,
            'currency' => $request->amount->currency(),
            'reference' => $request->reference,
            'callback_url' => $request->returnUrl,
            'metadata' => ['order_number' => $request->orderNumber],
        ]), $credentials);

        if (self::isRefusal($response)) {
            throw new GatewayRejectedException(self::KEY, 'startPayment', 'payment_refused');
        }

        $url = $response->json('data.authorization_url');

        // Anything else may still have started it: the payment stays pending, and is asked about.
        if (! self::isAnswer($response) || $response->json('data.reference') !== $request->reference || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new GatewayUnavailableException(self::KEY, 'startPayment');
        }

        return new StartedPayment($url);
    }

    /**
     * Asks Paystack where the transaction with our reference stands. Only the transaction's own status counts ("status": true only says the request worked): success, failed or reversed; anything else, abandoned included (the buyer may still pay), is pending. So is a reference Paystack doesn't know yet (400 "transaction_not_found", read before the status, in any case). A verify is never refused: any other answer but a success leaves the outcome unknown, and a refused key is told apart (send).
     *
     * Strict about the amount, reporting only amounts Paystack gave: when the
     * amount Paystack says it was asked for (requested_amount) is exactly the
     * one expected, what it took ("amount") is reported, so core's exact check
     * applies to it (a merchant passing Paystack's fees on to the buyer is
     * charged more, which core sends to review). When Paystack was asked for
     * another amount, that amount is reported instead, which core's exact
     * check never accepts. Either missing leaves it pending.
     *
     * @throws GatewayUnavailableException
     */
    public function verifyPayment(GatewayCredentials $credentials, string $reference, Money $expectedAmount): GatewayPaymentResult
    {
        $response = $this->send('verifyPayment', static fn (PendingRequest $paystack): Response => $paystack->get('transaction/verify/'.rawurlencode($reference)), $credentials);

        // Paystack answers 400 for a reference it doesn't know (yet): never a refusal, only not there to see.
        if (self::errorCodeOf($response) === 'transaction_not_found') {
            return new GatewayPaymentResult(GatewayPaymentState::Pending);
        }

        $mode = GatewayMode::tryFrom((string) $response->json('data.domain'));

        if (! self::isAnswer($response) || $response->json('data.reference') !== $reference || $mode === null) {
            throw new GatewayUnavailableException(self::KEY, 'verifyPayment');
        }

        /** @var array<string, mixed> $transaction */
        $transaction = $response->json('data');
        $state = match ($transaction['status'] ?? null) {
            'success' => GatewayPaymentState::Succeeded,
            'failed', 'reversed' => GatewayPaymentState::Failed,
            default => GatewayPaymentState::Pending,
        };
        [$cardBrand, $cardLast4] = self::cardOf($transaction);

        return new GatewayPaymentResult(
            state: $state,
            amount: $this->reportedAmountOf($transaction, $expectedAmount),
            mode: $mode,
            gatewayReference: is_int($transaction['id'] ?? null) ? (string) $transaction['id'] : null,
            failureCode: match ($transaction['status'] ?? null) {
                'failed' => 'payment_failed',
                'reversed' => 'payment_reversed',
                default => null,
            },
            cardBrand: $cardBrand,
            cardLast4: $cardLast4,
        );
    }

    /**
     * Asks Paystack to refund part or all of a transaction (POST /refund), with our refund reference as the refund's merchant note, by which it is found again. Paystack makes a second refund if asked twice, even with the same note: core never sends one twice without finding none first.
     *
     * @throws GatewayRejectedException
     * @throws GatewayUnavailableException
     */
    public function refund(GatewayCredentials $credentials, GatewayRefundRequest $request): GatewayRefundResult
    {
        try {
            $amount = $this->amounts->toPaystack($request->amount);
        } catch (PaystackAmountException) {
            throw new GatewayRejectedException(self::KEY, 'refund', 'amount_not_supported');
        }

        $response = $this->send('refund', static fn (PendingRequest $paystack): Response => $paystack->post('refund', [
            'transaction' => $request->paymentReference,
            'amount' => (string) $amount,
            'currency' => $request->amount->currency(),
            'merchant_note' => $request->refundReference,
        ]), $credentials);

        if (self::isRefusal($response)) {
            throw new GatewayRejectedException(self::KEY, 'refund', self::errorCodeOf($response) === 'refund_amount_exceeds_transaction_amount' ? 'refund_exceeds_payment' : 'refund_refused');
        }

        // Anything else may still have made it: it stays pending, and is looked for.
        if (! self::isAnswer($response) || ! is_array($response->json('data'))) {
            throw new GatewayUnavailableException(self::KEY, 'refund');
        }

        /** @var array<string, mixed> $refund */
        $refund = $response->json('data');

        return $this->refundResultOf($refund, $request);
    }

    /**
     * Asks Paystack where a refund stands: by Paystack's ID for it (GET /refund/{id}) once Paystack gave one, otherwise among the transaction's refunds (GET /refund?transaction={id}), by our reference as the merchant note and the same amount.
     *
     * Exactly one match is the refund. More than one, or one for another
     * amount, is unclear, and so is no match when Paystack says less is left
     * to refund than this refund (meta.partialAmountLeft). No match is "not
     * found" only when the whole list arrived; a list that can't be read, or
     * comes in more than one page, leaves it unknown.
     *
     * @throws GatewayUnavailableException
     */
    public function verifyRefund(GatewayCredentials $credentials, GatewayRefundRequest $request, ?string $gatewayRefundReference): GatewayRefundResult
    {
        if ($gatewayRefundReference !== null) {
            return $this->fetchRefund($credentials, $request, $gatewayRefundReference);
        }

        $transactionId = $request->gatewayPaymentReference;

        if ($transactionId === null || ! ctype_digit($transactionId)) {
            throw new GatewayUnavailableException(self::KEY, 'verifyRefund');
        }

        $response = $this->send('verifyRefund', static fn (PendingRequest $paystack): Response => $paystack->get('refund', ['transaction' => $transactionId]), $credentials);
        $refunds = $response->json('data');

        if (! self::isAnswer($response) || ! is_array($refunds) || ! array_is_list($refunds) || $response->json('meta.total') !== count($refunds) || in_array(false, array_map(is_array(...), $refunds), true)) {
            throw new GatewayUnavailableException(self::KEY, 'verifyRefund');
        }

        /** @var list<array<array-key, mixed>> $refunds */
        $matches = array_values(array_filter($refunds, static fn (array $refund): bool => ($refund['merchant_note'] ?? null) === $request->refundReference));

        return match (count($matches)) {
            0 => $this->notFoundUnlessTooLittleLeft($response, $request),
            1 => $this->refundResultOf($matches[0], $request),
            default => new GatewayRefundResult(GatewayRefundState::Unclear),
        };
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

    /**
     * @throws GatewayUnavailableException
     */
    private function fetchRefund(GatewayCredentials $credentials, GatewayRefundRequest $request, string $refundId): GatewayRefundResult
    {
        if (! ctype_digit($refundId)) {
            throw new GatewayUnavailableException(self::KEY, 'verifyRefund');
        }

        $response = $this->send('verifyRefund', static fn (PendingRequest $paystack): Response => $paystack->get('refund/'.$refundId), $credentials);

        if (! self::isAnswer($response) || ! is_array($response->json('data'))) {
            throw new GatewayUnavailableException(self::KEY, 'verifyRefund');
        }

        /** @var array<string, mixed> $refund */
        $refund = $response->json('data');

        return $this->refundResultOf($refund, $request);
    }

    /**
     * None of the transaction's refunds is ours: not found, unless Paystack says less is left to refund than ours, or doesn't say.
     */
    private function notFoundUnlessTooLittleLeft(Response $response, GatewayRefundRequest $request): GatewayRefundResult
    {
        $left = $response->json('meta.partialAmountLeft');

        try {
            $fits = is_int($left) && $left >= $this->amounts->toPaystack($request->amount);
        } catch (PaystackAmountException) {
            $fits = false;
        }

        return new GatewayRefundResult($fits ? GatewayRefundState::NotFound : GatewayRefundState::Unclear);
    }

    /**
     * One Paystack refund as ours: unclear unless it carries our reference as its note and exactly our amount. Processed succeeds, failed fails, anything else is pending.
     *
     * @param  array<array-key, mixed>  $refund
     */
    private function refundResultOf(array $refund, GatewayRefundRequest $request): GatewayRefundResult
    {
        $amount = $refund['amount'] ?? null;
        $currency = $refund['currency'] ?? null;

        try {
            $isOurs = ($refund['merchant_note'] ?? null) === $request->refundReference
                && is_int($amount) && is_string($currency)
                && $this->amounts->fromPaystack($amount, $currency)->isEqualTo($request->amount);
        } catch (PaystackAmountException) {
            $isOurs = false;
        }

        if (! $isOurs) {
            return new GatewayRefundResult(GatewayRefundState::Unclear);
        }

        return new GatewayRefundResult(
            state: match ($refund['status'] ?? null) {
                'processed' => GatewayRefundState::Succeeded,
                'failed' => GatewayRefundState::Failed,
                default => GatewayRefundState::Pending,
            },
            gatewayRefundReference: is_int($refund['id'] ?? null) ? (string) $refund['id'] : null,
            amount: $request->amount,
            failureCode: ($refund['status'] ?? null) === 'failed' ? 'refund_failed' : null,
        );
    }

    /**
     * The amount to report: what the transaction took when Paystack was asked for exactly the amount expected, otherwise what Paystack was asked for, which core's exact check never accepts. Null unless Paystack reports both as whole numbers in a currency whose units are confirmed, so it is never counted as paid on a guess.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function reportedAmountOf(array $transaction, Money $expectedAmount): ?Money
    {
        $taken = $transaction['amount'] ?? null;
        $requested = $transaction['requested_amount'] ?? null;
        $currency = $transaction['currency'] ?? null;

        if (! is_int($taken) || ! is_int($requested) || ! is_string($currency)) {
            return null;
        }

        try {
            $wasAskedForAnother = $requested !== $this->amounts->toPaystack($expectedAmount);

            return $this->amounts->fromPaystack($wasAskedForAnother ? $requested : $taken, $currency);
        } catch (PaystackAmountException) {
            return null;
        }
    }

    /**
     * The card's brand and last four digits, for card payments only, and only in the shape expected.
     *
     * @param  array<string, mixed>  $transaction
     * @return array{string|null, string|null}
     */
    private static function cardOf(array $transaction): array
    {
        $card = $transaction['authorization'] ?? null;

        if (($transaction['channel'] ?? null) !== 'card' || ! is_array($card)) {
            return [null, null];
        }

        $brand = is_string($card['brand'] ?? null) ? mb_strtolower(mb_trim($card['brand'])) : '';
        $last4 = $card['last4'] ?? null;

        return [
            preg_match('/^[a-z ]{1,32}$/', $brand) === 1 ? $brand : null,
            is_string($last4) && preg_match('/^\d{4}$/', $last4) === 1 ? $last4 : null,
        ];
    }

    /**
     * Paystack answered, and said the request worked.
     */
    private static function isAnswer(Response $response): bool
    {
        return $response->successful() && $response->json('status') === true;
    }

    /**
     * Paystack refused the request for good (400 or 422): it was not carried out. Reported with our own codes, never Paystack's words. Anything else unanswered (a timeout, a rate limit, a conflict, a server error) says nothing.
     */
    private static function isRefusal(Response $response): bool
    {
        return in_array($response->status(), [400, 422], true);
    }

    /**
     * Paystack refused the key itself (invalid or revoked), by its error code or its status: nothing was carried out, and nothing is known.
     */
    private static function refusesKey(Response $response): bool
    {
        return self::errorCodeOf($response) === 'invalid_key' || in_array($response->status(), [401, 403], true);
    }

    /**
     * Paystack's error code, in lower case: it isn't consistent about case ("invalid_Key"). Read before the status, which Paystack doesn't keep to its meaning (400 for a reference it doesn't know).
     */
    private static function errorCodeOf(Response $response): ?string
    {
        $code = $response->json('code');

        return is_string($code) ? mb_strtolower($code) : null;
    }

    /**
     * @param  Closure(PendingRequest): Response  $request
     *
     * @throws GatewayUnavailableException When Paystack didn't answer, or refused the key.
     */
    private function send(string $operation, Closure $request, GatewayCredentials $credentials): Response
    {
        try {
            $response = $request($this->client($credentials));
        } catch (ConnectionException $exception) {
            throw new GatewayUnavailableException(self::KEY, $operation, $exception);
        }

        if (self::refusesKey($response)) {
            throw GatewayUnavailableException::keysRefused(self::KEY, $operation);
        }

        return $response;
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
