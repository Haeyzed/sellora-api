<?php

declare(strict_types=1);

use App\Shared\Privacy\PersonalDataErasure;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\StaffPermissionCatalogue;
use App\Tenant\Orders\Enums\OrderPermission;
use App\Tenant\Orders\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 14a: orders are financial records (section 3.3). Each store numbers
 * its own from 1001. The database refuses every change to what was sold and
 * every delete: lines, discounts, taxes, the shipping line and the history
 * never change; on the order only its statuses, their timestamps, the refund
 * flag and the guest-to-customer link (once) do. Contact details, the note
 * and addresses change only inside an erasure.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('numbers each store\'s orders from 1001, separately', function (): void {
    $other = createStore('other-store', ['country_code' => 'NG']);

    $numbers = $this->store->run(static fn (): array => [Order::query()->findOrFail(rawOrder())->number, Order::query()->findOrFail(rawOrder())->number]);
    $theirs = $other->run(static fn (): int => Order::query()->findOrFail(rawOrder())->number);

    expect($numbers)->toBe([1001, 1002])
        ->and($theirs)->toBe(1001)
        ->and($this->store->run(static fn (): string => Order::query()->where('number', 1001)->firstOrFail()->displayNumber()))->toBe('#1001');
});

it('refuses any change to what was sold, and any delete', function (Closure $tamper, string $message): void {
    expect(fn () => $this->store->run(static function () use ($tamper): void {
        $tamper(rawOrder());
    }))->toThrow(QueryException::class, $message);
})->with([
    'the number' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['number' => 9999]), 'never change'],
    'the total' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['total_amount' => 1]), 'never change'],
    'the base total' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['base_total_amount' => 1]), 'never change'],
    'the tax' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['tax_amount' => 5]), 'never change'],
    'the discount' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['discount_amount' => 0]), 'never change'],
    'the shipping' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['shipping_amount' => 0]), 'never change'],
    'the currency' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['currency' => 'USD']), 'never change'],
    'the rate' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['exchange_rate' => '2']), 'never change'],
    'the tax mode' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['tax_mode' => 'exclusive']), 'never change'],
    'the tax destination' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['tax_state_code' => 'LA']), 'never change'],
    'when it was placed' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['placed_at' => now()->subDay()]), 'never change'],
    'the payment deadline' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['payment_due_at' => now()->addYear()]), 'never change'],
    'the checkout' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['checkout_id' => 'another']), 'never change'],
    'the locale' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['locale' => 'fr']), 'never change'],
    'the contact email' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['contact_email' => 'eve@example.com']), 'only through erasure'],
    'the contact name' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['contact_name' => 'Eve']), 'only through erasure'],
    'the contact phone' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['contact_phone' => null]), 'only through erasure'],
    'the customer\'s note' => [fn (int $id) => DB::table('orders')->where('id', $id)->update(['customer_note' => null]), 'only through erasure'],
    'deleting the order' => [fn (int $id) => DB::table('orders')->where('id', $id)->delete(), 'DELETE is refused'],
    'emptying orders' => [fn (int $id) => DB::statement('truncate orders cascade'), 'Orders are financial records; TRUNCATE is refused'],
    'a line\'s price' => [fn (int $id) => DB::table('order_lines')->where('order_id', $id)->update(['unit_price_amount' => 1, 'subtotal_amount' => 2]), 'order_lines rows are financial records'],
    'a line\'s name' => [fn (int $id) => DB::table('order_lines')->where('order_id', $id)->update(['product_name' => 'Silk shirt']), 'order_lines rows are financial records'],
    'deleting a line' => [fn (int $id) => DB::table('order_lines')->where('order_id', $id)->delete(), 'order_lines rows are financial records'],
    'emptying lines' => [fn (int $id) => DB::statement('truncate order_lines cascade'), 'order_lines rows are financial records'],
    'a discount' => [fn (int $id) => DB::table('order_discounts')->where('order_id', $id)->update(['discount_amount' => 5]), 'order_discounts rows are financial records'],
    'deleting a discount' => [fn (int $id) => DB::table('order_discounts')->where('order_id', $id)->delete(), 'order_discounts rows are financial records'],
    'emptying discounts' => [fn (int $id) => DB::statement('truncate order_discounts'), 'order_discounts rows are financial records'],
    'a tax' => [fn (int $id) => DB::table('order_taxes')->where('order_id', $id)->update(['rate_percent' => '7.5']), 'order_taxes rows are financial records'],
    'deleting a tax' => [fn (int $id) => DB::table('order_taxes')->where('order_id', $id)->delete(), 'order_taxes rows are financial records'],
    'emptying taxes' => [fn (int $id) => DB::statement('truncate order_taxes'), 'order_taxes rows are financial records'],
    'the shipping line' => [fn (int $id) => DB::table('order_shipping')->where('order_id', $id)->update(['method_name' => 'Express']), 'order_shipping rows are financial records'],
    'deleting the shipping line' => [fn (int $id) => DB::table('order_shipping')->where('order_id', $id)->delete(), 'order_shipping rows are financial records'],
    'emptying shipping lines' => [fn (int $id) => DB::statement('truncate order_shipping'), 'order_shipping rows are financial records'],
    'the history' => [fn (int $id) => DB::table('order_events')->where('order_id', $id)->update(['type' => 'paid']), 'order_events rows are financial records'],
    'deleting history' => [fn (int $id) => DB::table('order_events')->where('order_id', $id)->delete(), 'order_events rows are financial records'],
    'emptying history' => [fn (int $id) => DB::statement('truncate order_events'), 'order_events rows are financial records'],
    'an address' => [fn (int $id) => DB::table('order_addresses')->where('order_id', $id)->update(['line1' => '1 Other Street']), 'order_addresses rows change only through erasure'],
    'deleting an address' => [fn (int $id) => DB::table('order_addresses')->where('order_id', $id)->delete(), 'order_addresses rows change only through erasure'],
    'emptying addresses' => [fn (int $id) => DB::statement('truncate order_addresses'), 'order_addresses rows are financial records'],
    'contact details after an erasure ended' => [function (int $id): void {
        app(PersonalDataErasure::class)->run(static fn (): null => null);
        DB::table('orders')->where('id', $id)->update(['contact_email' => 'eve@example.com']);
    }, 'only through erasure'],
    'what was sold, even inside an erasure' => [fn (int $id) => app(PersonalDataErasure::class)->run(static fn (): int => DB::table('orders')->where('id', $id)->update(['total_amount' => 1])), 'never change'],
    'a line, even inside an erasure' => [fn (int $id) => app(PersonalDataErasure::class)->run(static fn (): int => DB::table('order_lines')->where('order_id', $id)->delete()), 'order_lines rows are financial records'],
    'deleting the order, even inside an erasure' => [fn (int $id) => app(PersonalDataErasure::class)->run(static fn (): int => DB::table('orders')->where('id', $id)->delete()), 'DELETE is refused'],
    'another customer, once linked' => [function (int $id): void {
        $customers = Customer::factory()->count(2)->create();
        DB::table('orders')->where('id', $id)->update(['customer_id' => $customers[0]->id]);
        DB::table('orders')->where('id', $id)->update(['customer_id' => $customers[1]->id]);
    }, 'customer never changes once set'],
    'unlinking the customer' => [function (int $id): void {
        DB::table('orders')->where('id', $id)->update(['customer_id' => Customer::factory()->create()->id]);
        DB::table('orders')->where('id', $id)->update(['customer_id' => null]);
    }, 'customer never changes once set'],
]);

it('lets the statuses, their times, the refund flag and a guest\'s first link to a customer change', function (): void {
    $order = $this->store->run(static function (): Order {
        $id = rawOrder();
        $customer = Customer::factory()->create();
        DB::table('orders')->where('id', $id)->update(['customer_id' => $customer->id]);
        DB::table('orders')->where('id', $id)->update(['payment_status' => 'paid', 'paid_at' => now()]);
        DB::table('orders')->where('id', $id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'staff', 'refund_due' => true]);

        return Order::query()->findOrFail($id);
    });

    expect([$order->status->value, $order->payment_status->value, $order->refund_due, $order->customer_id])->toMatchArray(['cancelled', 'paid', true])
        ->and($order->customer_id)->not->toBeNull();
});

it('lets an erasure blank contact details, the note and addresses, and nothing else', function (): void {
    $order = $this->store->run(static function (): Order {
        $id = rawOrder();
        app(PersonalDataErasure::class)->run(static function () use ($id): void {
            DB::table('orders')->where('id', $id)->update(['contact_email' => 'erased@invalid', 'contact_name' => null, 'contact_phone' => null, 'customer_note' => null]);
            DB::table('order_addresses')->where('order_id', $id)->update(['recipient_name' => null, 'line1' => null, 'city' => null, 'phone' => null]);
        });

        return Order::query()->with('addresses')->findOrFail($id);
    });

    expect([$order->contact_email, $order->contact_name, $order->customer_note])->toBe(['erased@invalid', null, null])
        ->and($order->addresses->sole()->only(['recipient_name', 'line1', 'country_code', 'state_code']))->toBe(['recipient_name' => null, 'line1' => null, 'country_code' => 'NG', 'state_code' => 'LA'])
        ->and($order->total->minorAmount())->toBe(10500);
});

it('refuses statuses that don\'t fit together, at the database', function (array $columns, string $constraint): void {
    expect(fn () => $this->store->run(static fn () => rawOrder($columns)))->toThrow(QueryException::class, $constraint);
})->with([
    'completed but unpaid' => [['status' => 'completed', 'completed_at' => now(), 'fulfilment_status' => 'fulfilled'], 'orders_completed_when_done'],
    'completed but not fulfilled' => [['status' => 'completed', 'completed_at' => now(), 'payment_status' => 'paid', 'paid_at' => now()], 'orders_completed_when_done'],
    'paid with no time' => [['payment_status' => 'paid'], 'orders_paid_when_paid'],
    'cancelled with no reason' => [['status' => 'cancelled', 'cancelled_at' => now()], 'orders_cancelled_when_cancelled'],
    'refund due while unpaid' => [['refund_due' => true, 'status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'staff'], 'orders_refund_due_only_when_money_and_cancelled'],
    'an unknown status' => [['status' => 'shipped'], 'orders_status_check'],
    'an email in capitals' => [['contact_email' => 'Ada@Example.com'], 'orders_email_lower_case'],
    'a discount over the subtotal' => [['discount_amount' => 20000], 'orders_discounts_within'],
]);

it('lets roles grant viewing, managing and fulfilling orders', function (): void {
    expect(app(StaffPermissionCatalogue::class)->all())->toContain(...array_map(static fn (OrderPermission $permission): string => $permission->value, OrderPermission::cases()));
});
