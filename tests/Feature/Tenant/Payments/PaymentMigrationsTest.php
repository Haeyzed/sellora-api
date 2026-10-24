<?php

declare(strict_types=1);

use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TruncatesCentralDatabase;

/*
 * The store migrations that change payment records already there carry them
 * over: rolled back and run again on a store holding them, nothing is lost.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    $this->script = useFakeGateway();
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('takes every gateway refund already made as sent once, so none is sent again unasked, and none paid back by hand', function (): void {
    connectFakeGateway('live');
    [$gatewayOrder] = gatewayPaidOrder();
    $throughGateway = refundOrder($gatewayOrder, 4000)->paymentRefunds->sole();
    [$manualOrder, $manualPayment] = manuallyPaidOrder(PaymentMethod::BankTransfer);
    recordReceived($manualPayment, $manualPayment->amount->minorAmount());
    $byHand = refundOrder($manualOrder, 4000)->paymentRefunds->sole();
    /** @var Migration $migration */
    $migration = require database_path('migrations/tenant/2026_10_24_090000_add_sends_and_review_to_payment_refunds.php');

    $attempts = $this->store->run(static function () use ($migration, $throughGateway, $byHand): array {
        $migration->down();
        $migration->up();

        return [DB::table('payment_refunds')->where('id', $throughGateway->id)->value('send_attempts'), DB::table('payment_refunds')->where('id', $byHand->id)->value('send_attempts')];
    });

    expect($attempts)->toBe([1, 0]);
});

it('gives each connection made before webhook endpoints per mode its endpoint in the mode it is connected in', function (): void {
    $connection = connectFakeGateway('live');
    /** @var Migration $migration */
    $migration = require database_path('migrations/tenant/2026_10_23_110000_add_mode_endpoints_and_retired_credentials_to_payment_gateway_connections.php');

    $endpoints = $this->store->run(static function () use ($migration): array {
        $migration->down();
        $before = DB::table('payment_gateway_connections')->value('endpoint_id');
        $migration->up();

        return [$before, PaymentGatewayConnection::query()->sole()->only(['test_endpoint_id', 'live_endpoint_id'])];
    });

    expect($endpoints)->toBe([$connection->live_endpoint_id, ['test_endpoint_id' => null, 'live_endpoint_id' => $connection->live_endpoint_id]]);
});
