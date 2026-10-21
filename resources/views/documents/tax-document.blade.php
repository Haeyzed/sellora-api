{{--
    An invoice or credit note, rendered to PDF by mPDF (TaxDocumentPdf).
    Every value is escaped: store and buyer text can never become markup. Styles are inline here; the
    renderer loads nothing from outside the document.

    mPDF does not understand logical text-align values (start/end), so the alignment is resolved here
    into explicit left/right from the document direction.
--}}
@php
    $start = $direction === 'rtl' ? 'right' : 'left';
    $end = $direction === 'rtl' ? 'left' : 'right';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
<meta charset="utf-8">
<title>{{ $title }} {{ $number }}</title>
<style>
    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #1f2933; }
    h1 { font-size: 20pt; margin: 0 0 4mm 0; }
    .test { color: #b42318; font-weight: bold; margin-bottom: 3mm; }
    table { width: 100%; border-collapse: collapse; }
    tr { page-break-inside: avoid; }

    .details { margin-bottom: 6mm; }
    .details td { padding: 0.7mm 0; }
    .details td.label { color: #616e7c; width: 38mm; }

    .parties { margin-bottom: 4mm; }
    .parties td { vertical-align: top; width: 33%; padding: 0 4mm 4mm 0; line-height: 1.4; }
    .heading { font-size: 7.5pt; text-transform: uppercase; color: #616e7c; margin-bottom: 1.5mm; }
    .name { font-weight: bold; }

    .lines { margin-top: 4mm; }
    .lines th { text-align: {{ $start }}; font-size: 7.5pt; text-transform: uppercase; color: #616e7c; background-color: #f5f7fa; border-bottom: 0.3mm solid #9aa5b1; padding: 2mm 1.5mm; }
    .lines td { border-bottom: 0.2mm solid #e4e7eb; padding: 2mm 1.5mm; }
    .lines th.amount, .lines td.amount, .totals td.amount { text-align: {{ $end }}; white-space: nowrap; }
    .lines th.first { width: 45%; }

    .totals { margin-top: 5mm; width: 75mm; page-break-inside: avoid; }
    .totals td { padding: 1.2mm 1.5mm; }
    .totals tr.grand td { font-size: 11pt; font-weight: bold; border-top: 0.4mm solid #1f2933; padding-top: 2.5mm; }

    .note, .footer { margin-top: 6mm; color: #616e7c; font-size: 8pt; line-height: 1.4; }
    .pagenum { text-align: center; font-size: 7.5pt; color: #9aa5b1; }
</style>
</head>
<body>
    <htmlpagefooter name="documentfooter">
        <div class="pagenum">{{ __('documents.page', [], $locale) }} {PAGENO} / {nbpg}</div>
    </htmlpagefooter>
    <sethtmlpagefooter name="documentfooter" value="on" />

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
                @foreach ($seller as $line)<div @class(['name' => $loop->first])>{{ $line }}</div>@endforeach
            </td>
            <td>
                @if ($buyer !== [])
                    <div class="heading">{{ __('documents.buyer', [], $locale) }}</div>
                    @foreach ($buyer as $line)<div @class(['name' => $loop->first])>{{ $line }}</div>@endforeach
                @endif
            </td>
            <td>
                @if ($shipping !== [])
                    <div class="heading">{{ __('documents.shipping_address', [], $locale) }}</div>
                    @foreach ($shipping as $line)<div @class(['name' => $loop->first])>{{ $line }}</div>@endforeach
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th @class(['first' => $loop->first, 'amount' => ! $loop->first])>{{ $column }}</th>
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
                    <th class="first">{{ __('documents.tax_summary', [], $locale) }}</th>
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

    <table class="totals" align="{{ $end }}">
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
