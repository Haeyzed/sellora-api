<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Documents;

use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Mpdf\Container\SimpleContainer;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;

/**
 * Renders an invoice or credit note to PDF with mPDF: the only place mPDF is used (section 3.2).
 *
 * The document is built from its own copied values (TaxDocumentContent) into
 * HTML whose every value is escaped. mPDF then loads nothing from outside it:
 * its asset fetcher and HTTP client refuse everything and no stream wrapper
 * is allowed, so no image, stylesheet or font URL is ever requested and no
 * server file is read. Only mPDF's bundled fonts are used; it picks one for
 * each script, and lays out right-to-left languages right to left. Test
 * orders' documents are marked as such across the page.
 */
final readonly class TaxDocumentPdf
{
    public function __construct(
        private TaxDocumentContent $content,
        private ViewFactory $views,
    ) {}

    /**
     * @throws MpdfException When mPDF can't lay it out.
     */
    public function render(Invoice|CreditNote $document): string
    {
        $content = $this->content->for($document);

        return $this->fromHtml($this->views->make('documents.tax-document', $content)->render(), $content['direction'], $content['isTest'], $content['title'].' '.$content['number']);
    }

    /**
     * The HTML a document's PDF is laid out from.
     */
    public function html(Invoice|CreditNote $document): string
    {
        return $this->views->make('documents.tax-document', $this->content->for($document))->render();
    }

    /**
     * Lays HTML out as a PDF, loading nothing from outside it.
     *
     * @throws MpdfException When mPDF can't lay it out.
     */
    public function fromHtml(string $html, string $direction = 'ltr', bool $isTest = false, string $title = ''): string
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => self::workingDirectory(),
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'whitelistStreamWrappers' => [],
            'showImageErrors' => false,
            'debug' => false,
        ], new SimpleContainer([
            'assetFetcher' => new OfflineAssetFetcher,
            'httpClient' => new RefusingHttpClient,
        ]));

        $mpdf->SetDirectionality($direction);
        $mpdf->SetTitle($title);

        if ($isTest) {
            $mpdf->SetWatermarkText('TEST');
            $mpdf->showWatermarkText = true;
        }

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * This process's own folder for mPDF, under the configured one, removed when the process ends.
     *
     * After every render mPDF deletes the files in its folder older than an
     * hour; two processes doing so in one folder at once both try to delete
     * the same file, and the second fails. Its font cache lives in the same
     * folder, so fonts are prepared once per process.
     */
    private static function workingDirectory(): string
    {
        static $directory = null;

        if ($directory === null) {
            $directory = config()->string('orders.pdf_working_dir').DIRECTORY_SEPARATOR.'process-'.getmypid();
            // Not through the facade: by the time the process ends, the application may be gone.
            register_shutdown_function(static fn (): bool => (new Filesystem)->deleteDirectory($directory));
        }

        File::ensureDirectoryExists($directory);

        return $directory;
    }
}
