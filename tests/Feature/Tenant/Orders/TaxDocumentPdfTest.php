<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Shared\Tenancy\TenantScopedKey;
use App\Tenant\Orders\Actions\IssueCreditNote;
use App\Tenant\Orders\Actions\StoreTaxDocumentPdf;
use App\Tenant\Orders\Data\CreditRequest;
use App\Tenant\Orders\Documents\OfflineAssetFetcher;
use App\Tenant\Orders\Documents\RefusingHttpClient;
use App\Tenant\Orders\Documents\TaxDocumentContent;
use App\Tenant\Orders\Documents\TaxDocumentPdf;
use App\Tenant\Orders\Enums\TaxDocumentKind;
use App\Tenant\Orders\Jobs\RenderTaxDocumentPdf;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Settings\Models\StoreSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mpdf\Exception\AssetFetchingException;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 15b.4: each invoice and credit note is rendered to PDF once, with
 * mPDF, on the default queue (limited per store), and kept on a private disk
 * apart from other stores. The PDF is laid out from the document's own copied
 * values, in its language and direction; store and buyer text is escaped; and
 * mPDF loads nothing from outside the document, so a URL in it never makes a
 * network request.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->travelTo(CarbonImmutable::parse('2026-06-01 10:00', 'UTC'));
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    useFakeGateway();
    connectFakeGateway('live');
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('renders each invoice and credit note to a PDF once, kept on the private disk apart from other stores', function (): void {
    $this->store->run(static fn () => addTaxRate('Standard', 'NG', null, '7.5'));
    [$order] = gatewayPaidOrder();
    refundOrder($order, 1000);

    $this->store->run(function (): void {
        $invoice = Invoice::query()->sole();
        $creditNote = CreditNote::query()->sole();
        $disk = Storage::disk('local');

        expect([$invoice->pdf_path, $creditNote->pdf_path])->toBe(['invoices/'.$invoice->public_id.'.pdf', 'credit-notes/'.$creditNote->public_id.'.pdf'])
            ->and($invoice->pdf_rendered_at)->not->toBeNull()
            ->and(substr((string) $disk->get((string) $invoice->pdf_path), 0, 5))->toBe('%PDF-')
            ->and(substr((string) $disk->get((string) $creditNote->pdf_path), 0, 5))->toBe('%PDF-')
            // Tenancy keeps each store's files apart.
            ->and($disk->path((string) $invoice->pdf_path))->toContain('tenant_'.$this->store->getTenantKey());

        // Rendered once: asking again keeps the first.
        $before = $disk->get((string) $invoice->pdf_path);
        app(StoreTaxDocumentPdf::class)->handle($invoice);

        expect($disk->get((string) $invoice->pdf_path))->toBe($before)
            ->and(Invoice::query()->sole()->pdf_rendered_at?->toIso8601String())->toBe($invoice->pdf_rendered_at?->toIso8601String());
    });
});

it('renders on the default queue, limited per store, and the sweep queues any still missing', function (): void {
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class, RenderTaxDocumentPdf::class]);
    $this->store->run(static fn () => markPaid(unpaidOrder()));

    Queue::assertPushed(RenderTaxDocumentPdf::class, static fn (RenderTaxDocumentPdf $job): bool => $job->kind === TaxDocumentKind::Invoice && $job->queue === null && $job->middleware()[0] instanceof Illuminate\Queue\Middleware\RateLimited);

    // One PDF a minute per store: the store's second waits; another store's goes ahead.
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
    config(['api.rate_limits.tax_document_pdfs' => 1]);
    $other = createStore('other-store', ['country_code' => 'NG']);
    [$first, $second] = $this->store->run(static fn (): array => [markPaid(unpaidOrder())->invoice, markPaid(unpaidOrder())->invoice]);
    $elsewhere = $other->run(static fn (): ?Invoice => markPaid(unpaidOrder())->invoice);

    expect([$first?->pdf_path, $second?->pdf_path])->toBe(['invoices/'.$first?->public_id.'.pdf', null])
        ->and($elsewhere?->pdf_path)->toBe('invoices/'.$elsewhere?->public_id.'.pdf')
        // The limit is counted per store.
        ->and($this->store->run(static fn (): string => TenantScopedKey::make('tax-document-pdfs')))->toContain('tenant:'.$this->store->getTenantKey());

    // Later, the hourly sweep queues the ones still missing again (the first, whose job was faked, and the one that waited), and they render.
    config(['api.rate_limits.tax_document_pdfs' => 30]);
    $this->travel(11)->minutes();
    Artisan::call('orders:issue-missing-tax-documents');

    expect($this->store->run(static fn (): array => Invoice::query()->whereNull('pdf_path')->pluck('id')->all()))->toBe([]);
});

it('loads nothing from outside the document: an image, stylesheet or font at a URL makes no network request', function (): void {
    [$url, $requests] = recordingServer();

    $pdf = app(TaxDocumentPdf::class)->fromHtml(<<<HTML
        <html><head>
        <link rel="stylesheet" href="{$url}/style.css">
        <style>@import url("{$url}/import.css"); @font-face { font-family: remote; src: url("{$url}/font.ttf"); } body { background-image: url("{$url}/background.png"); font-family: remote; }</style>
        </head><body><img src="{$url}/logo.png"><svg width="10" height="10"><image href="{$url}/inside.png" width="10" height="10"/></svg><p>Invoice</p></body></html>
        HTML);

    expect(substr($pdf, 0, 5))->toBe('%PDF-')
        // Nothing was ever asked for.
        ->and($requests())->toBe([]);

    // Each guard holds on its own: the fetcher refuses web and file paths alike, and the HTTP client behind it refuses every request.
    expect(static fn () => (new OfflineAssetFetcher)->fetchDataFromPath('https://example.com/logo.png'))->toThrow(AssetFetchingException::class)
        ->and(static fn () => (new OfflineAssetFetcher)->fetchDataFromPath(base_path('composer.json')))->toThrow(AssetFetchingException::class)
        ->and([(new OfflineAssetFetcher)->fetchLocalContent(base_path('composer.json'), null), (new OfflineAssetFetcher)->fetchRemoteContent('https://example.com/a.css')])->toBe(['', ''])
        ->and(static fn () => (new RefusingHttpClient)->sendRequest(new Mpdf\PsrHttpMessageShim\Request('GET', 'https://example.com/a.png')))->toThrow(InvalidArgumentException::class);
});

it('escapes store and buyer text, so it can never become markup, and lays a document out from its own copied values', function (): void {
    [$url, $requests] = recordingServer();

    $this->store->run(static function () use ($url): void {
        $settings = StoreSettings::query()->sole();
        $settings->setTranslation('invoice_footer', 'en', "Thanks! <img src=\"{$url}/track.png\"><b>bold</b>");
        $settings->forceFill(['legal_name' => '<script>alert(1)</script> Ada Ltd', 'tax_registration_number' => '12345678-0001'])->save();
    });
    $order = $this->store->run(static fn () => markPaid(unpaidOrder()));

    [$html, $pdfStarts] = $this->store->run(static function (): array {
        // Changed after it was issued: the document keeps what it said.
        StoreSettings::query()->sole()->update(['legal_name' => 'Someone else', 'tax_registration_number' => '999']);
        $invoice = Invoice::query()->sole();

        return [app(TaxDocumentPdf::class)->html($invoice), substr((string) Storage::disk('local')->get((string) $invoice->pdf_path), 0, 5)];
    });

    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt; Ada Ltd')
        ->toContain('Thanks! &lt;img src=&quot;'.$url.'/track.png&quot;&gt;&lt;b&gt;bold&lt;/b&gt;')
        ->toContain('TIN: 12345678-0001')
        ->toContain('Invoice')->toContain('INV-2026-000001')->toContain('Date of sale')->not->toContain('<script>')->not->toContain('<img')->not->toContain('Someone else')
        ->and($pdfStarts)->toBe('%PDF-')
        ->and($requests())->toBe([]);
});

it('dates a document in the store\'s timezone when it was issued, and keeps that date', function (): void {
    // Already New Year's Day in Lagos.
    $this->travelTo(CarbonImmutable::parse('2026-12-31 23:30', 'UTC'));
    $this->store->run(static fn () => markPaid(unpaidOrder()));

    [$dates, $html, $filledIn] = $this->store->run(static function (): array {
        $invoice = Invoice::query()->sole();
        StoreSettings::query()->sole()->update(['timezone' => 'America/New_York']);
        // Documents issued before dates were stored are dated as the migration found the store.
        $migration = require database_path('migrations/tenant/2026_10_20_130000_add_local_dates_to_tax_documents.php');
        $migration->down();
        $migration->up();

        return [
            [$invoice->number, $invoice->issued_on->toDateString(), $invoice->paid_on->toDateString()],
            app(TaxDocumentPdf::class)->html($invoice),
            Invoice::query()->sole()->issued_on->toDateString(),
        ];
    });

    expect($dates)->toBe(['INV-2027-000001', '2027-01-01', '2027-01-01'])
        ->and($html)->toContain('January 1, 2027')
        ->and($filledIn)->toBe('2026-12-31');
});

it('writes a document in its own language\'s direction, and marks a test order\'s', function (): void {
    $rtl = app(TaxDocumentPdf::class)->fromHtml('<p>فاتورة</p>', 'rtl');
    [$test, $creditNote] = $this->store->run(static function (): array {
        $order = markPaid(unpaidOrder(isTest: true));
        app(IssueCreditNote::class)->handle(new CreditRequest($order->id, 'rfd_test', 'rf_test', Money::ofMinor(1000, 'NGN'), [], Money::zero('NGN'), Money::zero('NGN'), Money::ofMinor(1000, 'NGN')));

        return [app(TaxDocumentPdf::class)->html(Invoice::query()->sole()), app(TaxDocumentPdf::class)->html(CreditNote::query()->sole())];
    });

    expect(substr($rtl, 0, 5))->toBe('%PDF-')
        ->and(array_map(TaxDocumentContent::direction(...), ['ar', 'ar-EG', 'fa', 'en', 'fr', 'ha']))->toBe(['rtl', 'rtl', 'rtl', 'ltr', 'ltr', 'ltr'])
        ->and($test)->toContain('Test document: not a tax document')->toContain('dir="ltr"')
        // A credit note names the invoice it credits.
        ->and($creditNote)->toContain('Credit note')->toContain('Credits invoice')->toContain('TEST-');
});
