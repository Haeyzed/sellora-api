<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Tenant\Orders\Data\DocumentNumber;
use App\Tenant\Orders\Enums\TaxDocumentKind;
use App\Tenant\Orders\Models\DocumentSequence;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Settings\Enums\InvoiceNumbering;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Gives invoices and credit notes their numbers: gap-free and sequential per store and kind, such as INV-2026-000042.
 *
 * The number is taken under its series' row lock, inside the transaction that
 * saves the document, so a rollback gives it back and two documents issued at
 * once never share one. Yearly numbering starts again each year, by the issue
 * date in the store's timezone, with the year in the number; continuous
 * numbering never does. The prefix in force when the document is issued is
 * printed. Test orders' documents never take a number from here.
 */
final readonly class TaxDocumentNumbers
{
    private const int DIGITS = 6;

    public function __construct(private FindStoreSettings $findStoreSettings) {}

    /**
     * @throws LogicException When called outside a transaction, where the number couldn't be given back on failure.
     */
    public function next(TaxDocumentKind $kind, CarbonImmutable $issuedAt): DocumentNumber
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Document numbers are taken inside the transaction that saves the document.');
        }

        $settings = $this->findStoreSettings->handle();
        $isYearly = $settings->invoice_numbering === InvoiceNumbering::Yearly;
        $series = $isYearly ? $issuedAt->setTimezone($settings->timezone)->format('Y') : DocumentSequence::CONTINUOUS_SERIES;
        $sequenceNumber = $this->takeNext($kind, $series);
        $prefix = $kind === TaxDocumentKind::Invoice ? $settings->invoice_prefix : $settings->credit_note_prefix;

        return new DocumentNumber($prefix.($isYearly ? $series.'-' : '').mb_str_pad((string) $sequenceNumber, self::DIGITS, '0', STR_PAD_LEFT), $series, $sequenceNumber);
    }

    /**
     * Moves the series on by one under its row's lock, starting it on first use.
     */
    private function takeNext(TaxDocumentKind $kind, string $series): int
    {
        DocumentSequence::query()->insertOrIgnore(['kind' => $kind->value, 'series' => $series, 'last_number' => 0, 'created_at' => CarbonImmutable::now(), 'updated_at' => CarbonImmutable::now()]);
        $sequence = DocumentSequence::query()->where('kind', $kind->value)->where('series', $series)->lockForUpdate()->firstOrFail();
        $sequence->forceFill(['last_number' => $sequence->last_number + 1])->save();

        return $sequence->last_number;
    }
}
