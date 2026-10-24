<?php

declare(strict_types=1);

namespace Integrations\Paystack\ManualTest;

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Integrations\Contracts\WebhookEndpoints;
use App\Shared\Money\Money;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\ConnectPaymentGateway;
use App\Tenant\Payments\Actions\RefundOrder;
use App\Tenant\Payments\Actions\StartPayment;
use App\Tenant\Payments\Actions\VerifyPayment;
use App\Tenant\Payments\Actions\VerifyRefund;
use App\Tenant\Payments\Data\RefundRequest;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Enums\RefundStatus;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Integrations\Paystack\PaystackGateway;

/**
 * One real payment and partial refund through Paystack's test mode, run by hand on a developer's machine: connects a local store with the test key from the local .env, starts a payment of one of its test orders, prints Paystack's checkout page, waits while it is paid with Paystack's test card, verifies it, refunds part of it and checks the refund.
 *
 * Refuses any key but a test secret key (sk_test_…), any environment but
 * local, and CI. The key is never printed. With --save-webhooks, each webhook
 * Paystack sends meanwhile is kept, once its signature checks out, in a
 * git-ignored folder (ReceivedWebhooks), to be sanitised into a fixture.
 */
final class ManualTestCommand extends Command
{
    protected $signature = 'paystack:manual-test
        {store : The ID of a store on this machine whose plan includes Paystack}
        {order : The number of an unpaid test order in that store}
        {--save-webhooks : Keep each webhook Paystack sends meanwhile, once its signature checks out, in a git-ignored folder}
        {--return-url=https://example.com/paystack-manual-test : Where Paystack sends the buyer back after paying}';

    protected $description = 'Run one real payment and partial refund in Paystack test mode, by hand, on this machine only';

    public function handle(ReceivedWebhooks $receivedWebhooks): int
    {
        if (! $receivedWebhooks->isAllowed()) {
            $this->components->error('This runs only in the local environment of a developer\'s own machine, never in CI.');

            return self::FAILURE;
        }

        $secretKey = config('paystack.manual_test.secret_key');

        // Checked by shape alone, and never echoed.
        if (! is_string($secretKey) || preg_match('/^sk_test_[A-Za-z0-9]+$/', $secretKey) !== 1) {
            $this->components->error('PAYSTACK_MANUAL_TEST_SECRET_KEY must hold a Paystack test secret key (starting sk_test_); any other key is refused.');

            return self::FAILURE;
        }

        $orderNumber = $this->argument('order');
        $store = Tenant::query()->find($this->argument('store'));

        if (! $store instanceof Tenant || ! ctype_digit($orderNumber)) {
            $this->components->error('No store with that ID on this machine, or the order number isn\'t a number.');

            return self::FAILURE;
        }

        if ($this->option('save-webhooks') === true) {
            $receivedWebhooks->startSaving(CarbonImmutable::now()->addHours(2));
        }

        try {
            /** @var int */
            return $store->run(fn (): int => $this->runInStore($secretKey, (int) $orderNumber, $receivedWebhooks));
        } finally {
            $receivedWebhooks->stopSaving();
        }
    }

    private function runInStore(string $secretKey, int $orderNumber, ReceivedWebhooks $receivedWebhooks): int
    {
        $owner = StaffMember::role(StaffRole::Owner->value)->first();
        $order = Order::query()->where('number', $orderNumber)->first();

        if (! $owner instanceof StaffMember || ! $order instanceof Order || ! $order->is_test || ! $order->awaitsPayment()) {
            $this->components->error('The store needs an owner, and the order must be an unpaid test order of that store.');

            return self::FAILURE;
        }

        $connection = app(ConnectPaymentGateway::class)->handle($owner, PaystackGateway::KEY, ['secret_key' => $secretKey]);
        $this->components->info("Connected Paystack in test mode, with the key ending {$connection->credentials_hint}.");

        if ($this->option('save-webhooks') === true && $connection->test_endpoint_id !== null) {
            $this->components->twoColumnDetail('Paystack test webhook URL', app(WebhookEndpoints::class)->paymentWebhookUrl($connection->test_endpoint_id));
            $this->components->twoColumnDetail('Webhooks kept in', $receivedWebhooks->folder());
        }

        $returnUrl = $this->option('return-url');
        $payment = app(StartPayment::class)->handle($order, PaystackGateway::KEY, is_string($returnUrl) ? $returnUrl : 'https://example.com/paystack-manual-test');
        $this->components->twoColumnDetail('Pay with Paystack\'s test card at', (string) $payment->redirect_url);

        while ($payment->status === PaymentStatus::Pending && $this->confirm('Paid? Verify with Paystack now (no to stop)?', true)) {
            $payment = app(VerifyPayment::class)->handle($payment->refresh())->refresh();
            $this->components->twoColumnDetail("Payment {$payment->reference}", $payment->status->value);
        }

        if ($payment->status !== PaymentStatus::Succeeded) {
            $this->components->warn('The payment did not go through, so nothing was refunded.');

            return self::FAILURE;
        }

        $amount = Money::ofMinor(max(1, intdiv($payment->amount->minorAmount(), 2)), $payment->amount->currency());
        $refund = app(RefundOrder::class)->handle($owner, $order->refresh(), new RefundRequest($amount, paymentId: $payment->public_id, reason: 'Paystack manual test-mode refund'));
        $part = $refund->paymentRefunds->sole();
        $this->components->twoColumnDetail("Refund {$part->reference} of {$amount->minorAmount()} {$amount->currency()}", $part->status->value.' (Paystack ID '.($part->gateway_reference ?? 'none yet').')');

        for ($check = 1; $part->status === RefundStatus::Pending && $check <= config()->integer('paystack.manual_test.refund_checks'); $check++) {
            Sleep::for(config()->integer('paystack.manual_test.refund_check_every_seconds'))->seconds();
            $part = app(VerifyRefund::class)->handle($part->refresh())->refresh();
            $this->components->twoColumnDetail("Refund check {$check}", $part->status->value);
        }

        return self::SUCCESS;
    }
}
