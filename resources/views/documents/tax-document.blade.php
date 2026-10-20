{{--
    An invoice or credit note, rendered to PDF by mPDF (TaxDocumentPdf).
    Every value is escaped: store and buyer text can never become markup. Styles are inline here; the
    renderer loads nothing from outside the document.
--}}
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
<meta charset="utf-8">
<title>{{ $title }} {{ $number }}</title>
<style>
    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #1f2933; }
    h1 { font-size: 18pt; margin: 0 0 2mm 0; }
    .test { color: #b42318; font-weight: bold; margin-bottom: 3mm; }
    table { width: 100%; border-collapse: collapse; }
    .parties td { vertical-align: top; width: 33%; padding: 0 3mm 4mm 0; }
    .heading { font-size: 7.5pt; text-transform: uppercase; color: #616e7c; margin-bottom: 1mm; }
    .details td { padding: 0.6mm 0; }
    .details td.label { color: #616e7c; width: 35mm; }
    .lines { margin-top: 4mm; }
    .lines th { text-align: start; font-size: 7.5pt; color: #616e7c; border-bottom: 0.3mm solid #9aa5b1; padding: 1.5mm 1mm; }
    .lines td { border-bottom: 0.2mm solid #e4e7eb; padding: 1.5mm 1mm; }
    .lines .amount, .totals .amount { text-align: end; white-space: nowrap; }
    .totals { margin-top: 4mm; width: 70mm; }
    .totals td { padding: 1mm; }
    .totals tr.grand td { font-weight: bold; border-top: 0.3mm solid #1f2933; }
    .note, .footer { margin-top: 5mm; color: #616e7c; font-size: 8pt; }
</style>
</head>
<body>
    @if ($isTest)
        <div class="test">{{ __('documents.test_document', [], $locale) }}</div>
    @endif

    <h1>{{ $title }}</h1>

    <table class="details">
        @foreach ($details as [$label, $value])
            <tr><td class="label">{{ $label }}</td><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="heading">{{ __('documents.seller', [], $locale) }}</div>
                @foreach ($seller as $line)<div>{{ $line }}</div>@endforeach
            </td>
            <td>
                @if ($buyer !== [])
                    <div class="heading">{{ __('documents.buyer', [], $locale) }}</div>
                    @foreach ($buyer as $line)<div>{{ $line }}</div>@endforeach
                @endif
            </td>
            <td>
                @if ($shipping !== [])
                    <div class="heading">{{ __('documents.shipping_address', [], $locale) }}</div>
                    @foreach ($shipping as $line)<div>{{ $line }}</div>@endforeach
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th @class(['amount' => ! $loop->first])>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td @class(['amount' => ! $loop->first])>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($taxes !== [])
        <table class="lines">
            <thead>
                <tr>
                    <th>{{ __('documents.tax_summary', [], $locale) }}</th>
                    <th class="amount">{{ __('documents.taxable', [], $locale) }}</th>
                    <th class="amount">{{ __('documents.tax', [], $locale) }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($taxes as [$rate, $taxable, $tax])
                    <tr><td>{{ $rate }}</td><td class="amount">{{ $taxable }}</td><td class="amount">{{ $tax }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="totals" align="{{ $direction === 'rtl' ? 'left' : 'right' }}">
        @foreach ($totals as [$label, $value])
            <tr @class(['grand' => $loop->last])><td>{{ $label }}</td><td class="amount">{{ $value }}</td></tr>
        @endforeach
    </table>

    <div class="note">{{ $taxNote }}</div>

    @if ($footer !== null)
        <div class="footer">{{ $footer }}</div>
    @endif
</body>
</html>
