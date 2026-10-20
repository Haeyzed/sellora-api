<?php

declare(strict_types=1);

use App\Tenant\Orders\Data\DocumentNumber;
use App\Tenant\Orders\Enums\TaxDocumentKind;
use App\Tenant\Orders\Models\DocumentSequence;
use App\Tenant\Orders\Services\TaxDocumentNumbers;
use App\Tenant\Settings\Models\StoreSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 15b.1: invoices and credit notes are numbered gap-free and in order,
 * in separate series per store. A number is taken under its series' row lock
 * in the transaction that saves the document, so a rollback gives it back
 * and two documents issued at once never share one. Yearly numbering starts
 * again each year in the store's timezone; continuous numbering never does.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    // Nigeria's timezone is UTC+1, all year.
    $this->store = createStore('numbers-store', ['country_code' => 'NG']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('numbers invoices and credit notes in separate gap-free series, starting again each year in the store\'s timezone', function (): void {
    $numbers = $this->store->run(static fn (): array => array_map(static fn (DocumentNumber $number): string => $number->number, [
        takeDocumentNumber(TaxDocumentKind::Invoice, CarbonImmutable::parse('2026-03-01 10:00', 'UTC')),
        takeDocumentNumber(TaxDocumentKind::Invoice, CarbonImmutable::parse('2026-03-02 10:00', 'UTC')),
        takeDocumentNumber(TaxDocumentKind::CreditNote, CarbonImmutable::parse('2026-03-03 10:00', 'UTC')),
        // Already 2027 in Lagos.
        takeDocumentNumber(TaxDocumentKind::Invoice, CarbonImmutable::parse('2026-12-31 23:30', 'UTC')),
        // Still 2026 there.
        takeDocumentNumber(TaxDocumentKind::Invoice, CarbonImmutable::parse('2026-12-31 22:30', 'UTC')),
    ]));

    expect($numbers)->toBe(['INV-2026-000001', 'INV-2026-000002', 'CN-2026-000001', 'INV-2027-000001', 'INV-2026-000003']);
});

it('numbers continuously when the store says so, with the prefix in force when each is taken', function (): void {
    $numbers = $this->store->run(static function (): array {
        StoreSettings::query()->sole()->update(['invoice_numbering' => 'continuous', 'invoice_prefix' => 'ADA-']);
        $first = takeDocumentNumber(TaxDocumentKind::Invoice, CarbonImmutable::parse('2026-12-31 10:00', 'UTC'));
        StoreSettings::query()->sole()->update(['invoice_prefix' => 'SHOP/']);
        $second = takeDocumentNumber(TaxDocumentKind::Invoice, CarbonImmutable::parse('2027-01-02 10:00', 'UTC'));

        return [$first->number, $second->number, $second->series, $second->sequenceNumber];
    });

    expect($numbers)->toBe(['ADA-000001', 'SHOP/000002', 'all', 2]);
});

it('gives a number back when the document\'s transaction rolls back, so none is ever skipped', function (): void {
    $numbers = $this->store->run(static function (): array {
        $first = takeDocumentNumber()->number;

        try {
            DB::transaction(static function (): void {
                app(TaxDocumentNumbers::class)->next(TaxDocumentKind::Invoice, CarbonImmutable::now());

                throw new RuntimeException('The document could not be saved.');
            });
        } catch (RuntimeException) {
            // The document failed; its number goes back.
        }

        return [$first, takeDocumentNumber()->number];
    });

    expect($numbers[1])->toBe(str_replace('000001', '000002', $numbers[0]));
});

it('takes a number only inside the transaction that saves the document', function (): void {
    expect(fn () => $this->store->run(static fn () => app(TaxDocumentNumbers::class)->next(TaxDocumentKind::Invoice, CarbonImmutable::now())))
        ->toThrow(LogicException::class);
});

it('waits for a number another request is taking, so two documents never share one', function (): void {
    $this->store->run(static function (): void {
        takeDocumentNumber();
        $sequence = DocumentSequence::query()->sole();
        $other = SecondConnection::open()->holdRowLock($sequence);

        expect($other->blockedStatement(static fn () => takeDocumentNumber()))->toContain('"document_sequences"')->toContain('for update');

        // The other request takes number 2 and commits.
        $other->run(static fn (Connection $db): int => $db->table('document_sequences')->where('id', $sequence->id)->update(['last_number' => 2]));
        $other->commit();

        expect(takeDocumentNumber()->sequenceNumber)->toBe(3);
    });
});

it('keeps every series gap-free in the database too: it only moves on by one, and is never deleted', function (): void {
    $this->store->run(static function (): void {
        takeDocumentNumber();
        $series = DocumentSequence::query()->where('kind', 'invoice');

        expect(static fn () => DB::transaction(static fn () => (clone $series)->update(['last_number' => 3])))->toThrow(QueryException::class, 'only moves on by one')
            ->and(static fn () => DB::transaction(static fn () => (clone $series)->update(['last_number' => 0])))->toThrow(QueryException::class, 'only moves on by one')
            ->and(static fn () => DB::transaction(static fn () => (clone $series)->update(['series' => '2030'])))->toThrow(QueryException::class, 'only moves on by one')
            ->and(static fn () => DB::transaction(static fn () => (clone $series)->delete()))->toThrow(QueryException::class, 'never given back')
            ->and(static fn () => DB::transaction(static fn () => DB::statement('truncate document_sequences')))->toThrow(QueryException::class, 'never given back')
            ->and(static fn () => DB::transaction(static fn () => DB::table('document_sequences')->insert(['kind' => 'receipt', 'series' => 'all'])))->toThrow(QueryException::class, 'document_sequences_kind_check')
            ->and(static fn () => DB::transaction(static fn () => DB::table('document_sequences')->insert(['kind' => 'credit_note', 'series' => '26'])))->toThrow(QueryException::class, 'document_sequences_series_check');
    });
});

it('never hands out the same number twice, nor skips one, to processes taking numbers at the same moment', function (): void {
    expect(takeInvoiceNumbersInProcesses(processes: 3, each: 4))->toBe(range(1, 12))
        ->and($this->store->run(static fn (): int => DocumentSequence::query()->sole()->last_number))->toBe(12);
});
