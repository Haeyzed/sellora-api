<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\CreditNoteLine;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Payments\Actions\FindRefundedUnits;
use App\Tenant\Payments\Data\GatewayRefundResult;
use App\Tenant\Payments\Enums\GatewayRefundState;
use App\Tenant\Payments\Exceptions\RefundInvalidException;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Payments\Models\RefundLine;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18.2: what Payments gives Returns. Part of a refund of lines can be
 * kept back, always with a reason, when a return breaks the condition a
 * discount depended on: it comes off the lines themselves, spread by what
 * each is worth with its tax, so their credit note credits only what went
 * back. And Returns can ask how many units of each line refunds already gave
 * back, counted as refunds count them.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    $this->script = useFakeGateway();
    connectFakeGateway('live');
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
    [$shirt, $socks] = $this->store->run(static function (): array {
        addTaxRate('Standard', 'NG', null, '7.5');

        return [cartVariant(1000, stock: 5), cartVariant(500, stock: 5, name: 'Socks')];
    });
    [$this->order] = gatewayPaidOrder([[$shirt, 2], [$socks, 1]]);
    [$this->shirt, $this->socks] = $this->store->run(fn (): array => $this->order->lines()->orderBy('position')->get()->all());
});

afterEach(function (): void {
    deleteAllStores();
});

it('keeps part of a refund of lines back with its reason, spread across the lines with their tax, and credits only what went back', function (): void {
    $refund = refundOrder($this->order, lines: [$this->shirt->public_id => 2, $this->socks->public_id => 1], withheld: 600, withheldReason: 'Kept the free pair of socks');

    $this->store->run(function () use ($refund): void {
        $lines = $refund->lines->keyBy('order_line_id');
        // ₦6 kept back of ₦25, by what each line is worth: ₦4.80 of the shirts' ₦20, ₦1.20 of the socks' ₦5.
        $taxKept = static fn (OrderLine $line, int $kept): int => Money::ofMinor($kept, 'NGN')->allocate([$line->tax->minorAmount(), $line->total->minorAmount() - $line->tax->minorAmount()])[0]->minorAmount();
        $note = CreditNote::query()->with('lines')->sole();

        expect([$refund->amount->minorAmount(), $refund->withheld->minorAmount(), $refund->withheld_reason])->toBe([1900, 600, 'Kept the free pair of socks'])
            ->and($lines->map(static fn (RefundLine $line): array => [$line->quantity, $line->amount->minorAmount(), $line->tax->minorAmount()])->all())->toBe([
                $this->shirt->id => [2, 1520, $this->shirt->tax->minorAmount() - $taxKept($this->shirt, 480)],
                $this->socks->id => [1, 380, $this->socks->tax->minorAmount() - $taxKept($this->socks, 120)],
            ])
            // The credit note credits the lines at what went back, with their lowered tax, and nothing as a price reduction.
            ->and($note->lines->map(static fn (CreditNoteLine $line): array => [$line->kind->value, $line->total->minorAmount(), $line->tax->minorAmount()])->all())->toBe([
                ['item', 1520, $lines[$this->shirt->id]->tax->minorAmount()],
                ['item', 380, $lines[$this->socks->id]->tax->minorAmount()],
            ])
            ->and($note->total->minorAmount())->toBe(1900)
            ->and($this->order->refresh()->payment_status->value)->toBe('partially_refunded');
    });

    // The units are given back all the same: none of them can be refunded again.
    expect(fn () => refundOrder($this->order, lines: [$this->socks->public_id => 1]))->toThrow(RefundInvalidException::class);
});

it('refuses keeping back anything that isn\'t part of lines, said why, and less than they are worth', function (Closure $refund): void {
    expect(fn () => $refund($this->order, $this->shirt->public_id))->toThrow(RefundInvalidException::class)
        ->and($this->store->run(static fn (): int => Refund::query()->count()))->toBe(0);
})->with([
    'without a reason' => [static fn ($order, $line) => refundOrder($order, lines: [$line => 1], withheld: 100)],
    'with a blank reason' => [static fn ($order, $line) => refundOrder($order, lines: [$line => 1], withheld: 100, withheldReason: '   ')],
    'a reason without an amount' => [static fn ($order, $line) => refundOrder($order, lines: [$line => 1], withheldReason: 'Kept the free one')],
    'nothing' => [static fn ($order, $line) => refundOrder($order, lines: [$line => 1], withheld: 0, withheldReason: 'Kept the free one')],
    'from an amount' => [static fn ($order) => refundOrder($order, 1000, withheld: 100, withheldReason: 'Kept the free one')],
    'from shipping alone' => [static fn ($order) => refundOrder($order, shipping: true, withheld: 100, withheldReason: 'Kept the free one')],
    'all the lines are worth' => [static fn ($order, $line) => refundOrder($order, lines: [$line => 1], withheld: 1000, withheldReason: 'Kept the free one')],
    'more than the lines are worth' => [static fn ($order, $line) => refundOrder($order, lines: [$line => 1], withheld: 1001, withheldReason: 'Kept the free one')],
]);

it('counts the units refunds gave back per line while they hold money, leaving out the refunds asked', function (): void {
    $given = refundOrder($this->order, lines: [$this->shirt->public_id => 1]);
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending);
    refundOrder($this->order, lines: [$this->shirt->public_id => 1]);
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Failed);
    refundOrder($this->order, lines: [$this->socks->public_id => 1]);
    // An amount gives back no units.
    $this->script->refundAnswer = null;
    refundOrder($this->order, 100);
    [$other] = gatewayPaidOrder([[$this->store->run(fn (): ProductVariant => ProductVariant::query()->findOrFail($this->shirt->product_variant_id)), 1]]);
    refundOrder($other, lines: [$this->store->run(static fn (): string => $other->lines()->sole()->public_id) => 1]);

    $this->store->run(function () use ($given): void {
        $find = app(FindRefundedUnits::class);

        // Pending and succeeded count; the socks' refund failed entirely, so they are free again; another order's refunds never count.
        expect($find->handle($this->order))->toBe([$this->shirt->public_id => 2])
            ->and($find->handle($this->order, [$given->public_id]))->toBe([$this->shirt->public_id => 1]);
    });
});
