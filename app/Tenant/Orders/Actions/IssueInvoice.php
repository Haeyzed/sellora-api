<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Tenant\Orders\Enums\InvoiceLineKind;
use App\Tenant\Orders\Enums\OrderAddressKind;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\TaxDocumentKind;
use App\Tenant\Orders\Events\InvoiceIssued;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\InvoiceLine;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Orders\Models\OrderShipping;
use App\Tenant\Orders\Models\OrderTax;
use App\Tenant\Orders\Services\OrderEventLog;
use App\Tenant\Orders\Services\OrderProgress;
use App\Tenant\Orders\Services\TaxDocumentNumbers;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Settings\Models\StoreSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Issues the invoice for a sale once its order is paid in full (cash on delivery: once the cash is recorded). Safe to run twice: an order has at most one.
 *
 * Runs under the order's lock, and takes the next invoice number in the same
 * transaction, so a failure gives the number back. An order that was never
 * sold (unpaid, paid only in part, or paid after it was cancelled) gets none.
 * A test order's invoice is numbered "TEST-" and its order number, and takes
 * no number from the store's series. Everything is copied, so changing the
 * store's settings or the order's corrections later never changes it.
 */
final readonly class IssueInvoice
{
    public function __construct(
        private OrderProgress $orderProgress,
        private TaxDocumentNumbers $taxDocumentNumbers,
        private FindStoreSettings $findStoreSettings,
        private OrderEventLog $orderEventLog,
    ) {}

    /**
     * @return Invoice|null The order's invoice; null when it hasn't been sold.
     */
    public function handle(Order $order): ?Invoice
    {
        return DB::transaction(function () use ($order): ?Invoice {
            $locked = $this->orderProgress->lock($order);
            $existing = Invoice::query()->where('order_id', $locked->id)->first();

            if ($existing !== null || ! $locked->hasBeenSold()) {
                return $existing;
            }

            $locked->load(['lines.taxes', 'taxes', 'shippingLine', 'addresses', 'contactCorrections']);
            $invoice = $this->copyOf($locked, CarbonImmutable::now(), $this->findStoreSettings->handle());
            $invoice->save();
            $this->copyLines($locked, $invoice);
            $this->orderEventLog->record($locked, OrderEventType::InvoiceIssued, ['invoice' => $invoice->public_id, 'number' => $invoice->number]);
            InvoiceIssued::dispatch($invoice);

            return $invoice;
        });
    }

    private function copyOf(Order $order, CarbonImmutable $issuedAt, StoreSettings $settings): Invoice
    {
        $number = $order->is_test ? null : $this->taxDocumentNumbers->next(TaxDocumentKind::Invoice, $issuedAt);
        $contact = $order->currentContact();
        $billing = $order->currentAddress(OrderAddressKind::Billing);
        $shipping = $order->currentAddress(OrderAddressKind::Shipping);
        $footer = $settings->getTranslation('invoice_footer', $order->locale);

        return (new Invoice)->forceFill([
            'order_id' => $order->id,
            'number' => $number === null ? Invoice::TEST_PREFIX.$order->number : $number->number,
            'series' => $number?->series,
            'sequence_number' => $number?->sequenceNumber,
            'is_test' => $order->is_test,
            'issued_at' => $issuedAt,
            'paid_at' => $order->paid_at ?? $issuedAt,
            // The dates printed, in the store's timezone as it is now.
            'issued_on' => $issuedAt->setTimezone($settings->timezone)->toDateString(),
            'paid_on' => ($order->paid_at ?? $issuedAt)->setTimezone($settings->timezone)->toDateString(),
            'locale' => $order->locale,
            'currency' => $order->currency,
            'base_currency' => $order->base_currency,
            'exchange_rate' => $order->exchange_rate,
            'tax_mode' => $order->tax_mode,
            'tax_country_code' => $order->tax_country_code,
            'tax_state_code' => $order->tax_state_code,
            'seller_name' => $settings->legal_name ?? $settings->name,
            'seller_trading_name' => $settings->name,
            'seller_tax_registration_label' => $settings->tax_registration_number === null ? null : $settings->taxRegistrationLabel(),
            'seller_tax_registration_number' => $settings->tax_registration_number,
            'seller_address' => $settings->address_country_code === null ? null : [
                'line1' => $settings->address_line1,
                'line2' => $settings->address_line2,
                'city' => $settings->address_city,
                'state_code' => $settings->address_state_code,
                'postal_code' => $settings->address_postal_code,
                'country_code' => $settings->address_country_code,
            ],
            'seller_email' => $settings->contact_email,
            'seller_phone' => $settings->contact_phone,
            'buyer_name' => $billing->recipient_name ?? $contact->name,
            'buyer_email' => $contact->email,
            'buyer_phone' => $contact->phone,
            'buyer_tax_id' => $order->buyer_tax_id,
            'buyer_tax_id_country' => $order->buyer_tax_id_country,
            'billing_address' => $billing?->details(),
            'shipping_address' => $shipping?->details(),
            'footer' => $footer === '' ? null : $footer,
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'shipping' => $order->shipping,
            'shipping_discount' => $order->shipping_discount,
            'tax' => $order->tax,
            'total' => $order->total,
            'base_total' => $order->base_total,
        ]);
    }

    /**
     * The order's lines in order, then the shipping when anything was charged for it, each with its taxes.
     */
    private function copyLines(Order $order, Invoice $invoice): void
    {
        $position = 0;

        foreach ($order->lines as $line) {
            /** @var OrderLine $line */
            $copy = $this->saveLine($invoice, $position++, InvoiceLineKind::Item, $line->id, $line->product_name, $line->variant_name, $line->sku, $line->quantity, [
                'unit_price' => $line->unit_price, 'subtotal' => $line->subtotal, 'discount' => $line->discount, 'net' => $line->net, 'tax' => $line->tax, 'total' => $line->total,
            ]);
            $this->copyTaxes($invoice, $copy, $line->taxes->all());
        }

        $shipping = $order->shippingLine;

        if ($shipping instanceof OrderShipping && $shipping->price->isPositive()) {
            $copy = $this->saveLine($invoice, $position, InvoiceLineKind::Shipping, null, $shipping->method_name, null, null, 1, [
                'unit_price' => $shipping->price, 'subtotal' => $shipping->price, 'discount' => $shipping->discount, 'net' => $shipping->net, 'tax' => $shipping->tax, 'total' => $shipping->total,
            ]);
            $this->copyTaxes($invoice, $copy, $order->taxes->whereNull('order_line_id')->all());
        }
    }

    /**
     * @param  array<string, mixed>  $amounts
     */
    private function saveLine(Invoice $invoice, int $position, InvoiceLineKind $kind, ?int $orderLineId, string $name, ?string $variantName, ?string $sku, int $quantity, array $amounts): InvoiceLine
    {
        $line = (new InvoiceLine)->forceFill([
            'invoice_id' => $invoice->id,
            'position' => $position,
            'kind' => $kind,
            'order_line_id' => $orderLineId,
            'name' => $name,
            'variant_name' => $variantName,
            'sku' => $sku,
            'quantity' => $quantity,
            ...$amounts,
        ]);
        $line->save();

        return $line;
    }

    /**
     * @param  array<array-key, OrderTax>  $taxes
     */
    private function copyTaxes(Invoice $invoice, InvoiceLine $line, array $taxes): void
    {
        foreach ($taxes as $tax) {
            $line->taxes()->forceCreate([
                'invoice_id' => $invoice->id,
                'name' => $tax->name,
                'country_code' => $tax->country_code,
                'state_code' => $tax->state_code,
                'rate_percent' => $tax->rate_percent,
                'tax' => $tax->tax,
            ]);
        }
    }
}
