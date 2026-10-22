<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Documents;

use App\Shared\Money\Money;
use App\Shared\Money\TaxMode;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\InvoiceLine;

/**
 * What a tax document's PDF shows, worked out from the document alone: every value is the copy made when it was issued, nothing is read live.
 *
 * Wording is in the document's language. Values are plain text; the view
 * escapes all of them, so a store's or buyer's text can never become markup.
 */
final readonly class TaxDocumentContent
{
    /** Languages written right to left. */
    private const array RIGHT_TO_LEFT = ['ar', 'fa', 'he', 'ur'];

    /**
     * @return array{
     *     locale: string, direction: string, title: string, number: string, isTest: bool,
     *     details: list<array{string, string}>, seller: list<string>, buyer: list<string>, shipping: list<string>,
     *     columns: list<string>, lines: list<list<string>>, taxes: list<list<string>>, totals: list<array{string, string}>, taxNote: string, footer: string|null
     * }
     */
    public function for(Invoice|CreditNote $document): array
    {
        $locale = $document->locale;
        $label = static fn (string $key, array $replace = []): string => (string) __('documents.'.$key, $replace, $locale);
        $money = static fn (Money $amount): string => $amount->format($locale);
        $document->loadMissing('lines.taxes');

        return [
            'locale' => $locale,
            'direction' => self::direction($locale),
            'title' => $label($document instanceof Invoice ? 'invoice' : 'credit_note'),
            'number' => $document->number,
            'isTest' => $document->is_test,
            'details' => $this->details($document, $label),
            'seller' => $this->seller($document),
            'buyer' => array_values(array_filter([
                $document->buyer_name,
                ...$this->address($document->billing_address),
                $document->buyer_tax_id === null ? null : $label('buyer_tax_id', ['country' => (string) $document->buyer_tax_id_country]).': '.$document->buyer_tax_id,
                $document->buyer_email,
                $document->buyer_phone,
            ])),
            'shipping' => $this->address($document->shipping_address, withRecipient: true),
            'columns' => $document instanceof Invoice
                ? [$label('description'), $label('quantity'), $label('unit_price'), $label('discount'), $label('net'), $label('tax'), $label('total')]
                : [$label('description'), $label('quantity'), $label('net'), $label('tax'), $label('total')],
            'lines' => $this->lines($document, $money),
            'taxes' => $this->taxes($document, $money),
            'totals' => $this->totals($document, $label, $money),
            'taxNote' => $label($document->tax_mode === TaxMode::Inclusive ? 'prices_include_tax' : 'prices_exclude_tax'),
            'footer' => $document->footer,
        ];
    }

    /**
     * Which way a language is written: "rtl" or "ltr".
     */
    public static function direction(string $locale): string
    {
        return in_array(explode('_', str_replace('-', '_', $locale))[0], self::RIGHT_TO_LEFT, true) ? 'rtl' : 'ltr';
    }

    /**
     * @param  callable(string, array<string, string>=): string  $label
     * @return list<array{string, string}>
     */
    private function details(Invoice|CreditNote $document, callable $label): array
    {
        $date = static fn ($day): string => $day->locale($document->locale)->isoFormat('LL');
        $details = [[$label('number'), $document->number], [$label('issued_on'), $date($document->issued_on)]];

        if ($document instanceof Invoice) {
            $details[] = [$label('paid_on'), $date($document->paid_on)];
        } else {
            $details[] = [$label('credits_invoice'), $document->invoice->number];
        }

        $details[] = [$label('currency'), $document->currency];

        if ($document->currency !== $document->base_currency) {
            $details[] = ['', $label('exchange_rate', ['base' => $document->base_currency, 'rate' => $document->exchange_rate->toString(), 'currency' => $document->currency])];
        }

        return $details;
    }

    /**
     * @return list<string>
     */
    private function seller(Invoice|CreditNote $document): array
    {
        return array_values(array_filter([
            $document->seller_name,
            $document->seller_trading_name === $document->seller_name ? null : $document->seller_trading_name,
            ...$this->address($document->seller_address),
            $document->seller_tax_registration_number === null ? null : $document->seller_tax_registration_label.': '.$document->seller_tax_registration_number,
            $document->seller_email,
            $document->seller_phone,
        ]));
    }

    /**
     * An address as printed lines; none when there is no address (or it was erased).
     *
     * @param  array<string, string|null>|null  $address
     * @return list<string>
     */
    private function address(?array $address, bool $withRecipient = false): array
    {
        if ($address === null) {
            return [];
        }

        return array_values(array_filter([
            $withRecipient ? ($address['recipient_name'] ?? null) : null,
            $address['company'] ?? null,
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            trim(implode(' ', array_filter([$address['city'] ?? null, $address['state_code'] ?? null, $address['postal_code'] ?? null]))),
            $address['country_code'] ?? null,
        ], static fn (?string $part): bool => $part !== null && $part !== ''));
    }

    /**
     * @param  callable(Money): string  $money
     * @return list<list<string>>
     */
    private function lines(Invoice|CreditNote $document, callable $money): array
    {
        $rows = [];

        foreach ($document->lines as $line) {
            $description = trim($line->name.($line->variant_name === null ? '' : ' — '.$line->variant_name).($line->sku === null ? '' : ' ('.$line->sku.')'));

            $rows[] = $line instanceof InvoiceLine
                ? [$description, (string) $line->quantity, $money($line->unit_price), $money($line->discount), $money($line->net), $money($line->tax), $money($line->total)]
                : [$description, $line->quantity === 0 ? '' : (string) $line->quantity, $money($line->net), $money($line->tax), $money($line->total)];
        }

        return $rows;
    }

    /**
     * The tax by rate: what was taxed at it, and the tax.
     *
     * @param  callable(Money): string  $money
     * @return list<list<string>>
     */
    private function taxes(Invoice|CreditNote $document, callable $money): array
    {
        $rates = [];

        foreach ($document->lines as $line) {
            foreach ($line->taxes as $tax) {
                $rate = rtrim(rtrim($tax->rate_percent->toString(), '0'), '.');
                $key = $tax->name.'|'.$rate.'|'.$tax->country_code.'|'.$tax->state_code;
                $rates[$key] ??= ['name' => $tax->name.' '.$rate.'%', 'taxable' => Money::zero($document->currency), 'tax' => Money::zero($document->currency)];
                $rates[$key]['taxable'] = $rates[$key]['taxable']->add($line->net);
                $rates[$key]['tax'] = $rates[$key]['tax']->add($tax->tax);
            }
        }

        return array_values(array_map(static fn (array $rate): array => [$rate['name'], $money($rate['taxable']), $money($rate['tax'])], $rates));
    }

    /**
     * @param  callable(string, array<string, string>=): string  $label
     * @param  callable(Money): string  $money
     * @return list<array{string, string}>
     */
    private function totals(Invoice|CreditNote $document, callable $label, callable $money): array
    {
        if ($document instanceof CreditNote) {
            return [[$label('net'), $money($document->net)], [$label('tax'), $money($document->tax)], [$label('total_credited'), $money($document->total)]];
        }

        return array_values(array_filter([
            [$label('subtotal'), $money($document->subtotal)],
            $document->discount->isZero() ? null : [$label('discount'), '−'.$money($document->discount)],
            $document->shipping->isZero() ? null : [$label('shipping'), $money($document->shipping->subtract($document->shipping_discount))],
            [$label('tax'), $money($document->tax)],
            [$label('total_due'), $money($document->total)],
        ]));
    }
}
