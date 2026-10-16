<?php

declare(strict_types=1);

use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Actions\LinkGuestOrders;
use App\Tenant\Orders\Data\OrderBuyer;
use App\Tenant\Orders\Models\Order;
use Illuminate\Support\Facades\Notification;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 14f: a customer's order history. A signed-in customer sees and
 * cancels (before paying) only their own orders; anyone else's is not found.
 * Guest orders join the account once its email is verified, or its change
 * to that email confirmed, never on an unverified email and never through a
 * corrected email.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    seedWorld();
    $this->store = createStore('first-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
});

afterEach(function (): void {
    deleteAllStores();
});

it('shows a customer their own orders, newest first, and nobody else\'s', function (): void {
    $customer = firstStoreCustomer(['email_verified_at' => now()]);
    $someoneElse = firstStoreCustomer(['email' => 'eve@example.com']);
    [$mine, $theirs, $guest] = $this->store->run(static function () use ($customer, $someoneElse): array {
        $method = orderShippingMethod();

        return [
            [placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::customer($customer)))->public_id, placeOrder(orderDraft([[cartVariant(5000), 2]], $method, buyer: OrderBuyer::customer($customer)))->public_id],
            placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::customer($someoneElse)))->public_id,
            placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::guest('stranger@example.com')))->public_id,
        ];
    });

    customerRequest('GET', 'orders')->assertUnauthorized();

    $list = customerRequest('GET', 'orders', as: $customer)->assertOk();
    expect($list->json('data.*.id'))->toBe([$mine[1], $mine[0]])
        ->and($list->json('data.0'))->toMatchArray(['number' => 1002, 'status' => 'open', 'payment_status' => 'unpaid', 'fulfilment_status' => 'unfulfilled'])
        ->and($list->json('data.0.lines.0'))->toMatchArray(['product_name' => 'Linen shirt', 'quantity' => 2])
        ->and($list->json('data.0'))->not->toHaveKeys(['tags', 'refund_due', 'contact', 'customer_note']);

    customerRequest('GET', "orders/{$mine[0]}", as: $customer)->assertOk()->assertJsonPath('data.shipping_address.line1', '5 Allen Avenue');
    customerRequest('GET', "orders/{$theirs}", as: $customer)->assertNotFound();
    customerRequest('GET', "orders/{$guest}", as: $customer)->assertNotFound();
});

it('lets a customer cancel their own order before paying, never a paid one or anyone else\'s', function (): void {
    $customer = firstStoreCustomer();
    $someoneElse = firstStoreCustomer(['email' => 'eve@example.com']);
    [$unpaid, $paid, $theirs] = $this->store->run(static function () use ($customer, $someoneElse): array {
        $method = orderShippingMethod();

        return [
            placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], $method, buyer: OrderBuyer::customer($customer)))->public_id,
            markPaid(placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::customer($customer))))->public_id,
            placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::customer($someoneElse)))->public_id,
        ];
    });

    customerRequest('POST', "orders/{$unpaid}/cancel", as: $customer)->assertOk()->assertJsonPath('data.status', 'cancelled');
    customerRequest('POST', "orders/{$paid}/cancel", as: $customer)->assertConflict()->assertJsonPath('code', 'order_already_paid');
    customerRequest('POST', "orders/{$theirs}/cancel", as: $customer)->assertNotFound();

    expect($this->store->run(static fn (): array => Order::query()->where('public_id', $unpaid)->sole()->only(['cancelled_by_type', 'cancelled_by_id'])))->toBe(['cancelled_by_type' => 'customer', 'cancelled_by_id' => $customer->public_id])
        ->and($this->store->run(static fn (): string => Order::query()->where('public_id', $theirs)->sole()->status->value))->toBe('open');
});

it('links guest orders to an account once its email is verified, and only then', function (): void {
    [$guestOrder, $otherOrder] = $this->store->run(static function (): array {
        $method = orderShippingMethod();

        return [
            placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::guest('ada@example.com')))->public_id,
            placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::guest('someone@example.com')))->public_id,
        ];
    });
    $customer = firstStoreCustomer(['email_verified_at' => null]);

    // Signed up, but not verified: not theirs yet, even if linking is asked for.
    expect($this->store->run(static fn (): int => app(LinkGuestOrders::class)->handle($customer)))->toBe(0)
        ->and(customerRequest('GET', 'orders', as: $customer)->json('data'))->toBe([]);

    customerRequest('POST', 'account/email-verifications', as: $customer)->assertAccepted();
    customerRequest('POST', 'auth/email-verifications', ['token' => emailedCustomerToken(CustomerEmailTokenPurpose::Verification, 'ada@example.com')])->assertNoContent();

    expect(customerRequest('GET', 'orders', as: $customer)->json('data.*.id'))->toBe([$guestOrder])
        ->and(staffOrderRequest('GET', "/orders/{$guestOrder}/events", subdomain: 'first-store')->json('data.1'))->toMatchArray(['type' => 'linked_to_customer', 'data' => ['customer' => $customer->public_id, 'when' => 'email_verified']])
        ->and(staffOrderRequest('GET', "/orders/{$otherOrder}", subdomain: 'first-store')->json('data.customer'))->toBeNull();
});

it('links guest orders placed with a new email once the change to it is confirmed, never through a corrected email', function (): void {
    $customer = firstStoreCustomer(['email_verified_at' => now()]);
    [$placedWithNew, $correctedToNew] = $this->store->run(static function (): array {
        $method = orderShippingMethod();

        return [
            placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::guest('ada.new@example.com')))->public_id,
            placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::guest('typo@example.com')))->public_id,
        ];
    });
    staffOrderRequest('PUT', "/orders/{$correctedToNew}/contact", ['email' => 'ada.new@example.com'], subdomain: 'first-store')->assertOk();

    customerRequest('POST', 'account/email-changes', ['email' => 'ada.new@example.com', 'current_password' => 'a-long-shopper-password'], as: $customer)->assertAccepted();
    customerRequest('POST', 'account/email-changes/confirmations', ['token' => emailedCustomerToken(CustomerEmailTokenPurpose::EmailChange, 'ada.new@example.com')], as: $customer)->assertOk();

    expect(customerRequest('GET', 'orders', as: $customer)->json('data.*.id'))->toBe([$placedWithNew]);
});

it('keeps each store\'s customers to their own store\'s orders', function (): void {
    $other = createStore('other-store', ['country_code' => 'NG']);
    $customer = firstStoreCustomer();
    $theirs = $other->run(static fn (): string => placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod()))->public_id);

    customerRequest('GET', "orders/{$theirs}", as: $customer)->assertNotFound();
    expect(customerRequest('GET', 'orders', as: $customer)->json('data'))->toBe([]);
});
