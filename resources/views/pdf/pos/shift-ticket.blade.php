<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Ticket Turno {{ $shift->shift_number }}</title>
    <style>
        @page { margin: 4mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; }
        .center { text-align: center; }
        .right { text-align: right; }
        .b { font-weight: bold; }
        h3 { font-size: 11px; font-weight: normal; text-transform: uppercase; margin-bottom: 2px; }
        h4 { font-size: 10px; font-weight: bold; margin: 4px 0; }
        hr.solid { border: 0; border-top: 1px solid #000; margin: 4px 0; }
        hr.dashed { border: 0; border-top: 1px dashed #666; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; font-size: 9px; }
        .row { display: table; width: 100%; }
        .col { display: table-cell; }
    </style>
</head>
<body>
    @php
        $logoUri = app_logo_data_uri();
        $agtCert = \App\Helpers\AGTHelper::softwareValidationNumber();
    @endphp

    @if($logoUri)
    <div class="center" style="margin-bottom: 4px;">
        <img src="{{ $logoUri }}" alt="logo" style="height: 28px; width: auto;">
    </div>
    @endif

    <div class="center">
        <h3>{{ $tenant->company_name ?? $tenant->name }}</h3>
        <div>NIF: {{ $tenant->nif ?? '—' }}</div>
        <div>{{ $tenant->address ?? '' }}</div>
        <div>Tel: {{ $tenant->phone ?? '' }}</div>
    </div>

    <hr class="solid">
    <h4 class="center">RESUMO DE TURNO</h4>
    <hr class="solid">

    <table>
        <tr><td class="b">Turno:</td><td class="right">{{ $shift->shift_number }}</td></tr>
        <tr><td class="b">Operador:</td><td class="right">{{ optional($shift->user)->name }}</td></tr>
        <tr><td class="b">Abertura:</td><td class="right">{{ optional($shift->opened_at)->format('d/m/Y H:i') }}</td></tr>
        @if($shift->closed_at)
        <tr><td class="b">Fecho:</td><td class="right">{{ optional($shift->closed_at)->format('d/m/Y H:i') }}</td></tr>
        <tr><td class="b">Duração:</td><td class="right">{{ number_format($shift->duration, 2) }}h</td></tr>
        @endif
        <tr><td class="b">Estado:</td><td class="right">{{ strtoupper($shift->status_label) }}</td></tr>
    </table>

    <hr class="dashed">
    <h4>VENDAS POR PAGAMENTO</h4>
    <table>
        <tr><td>Dinheiro</td><td class="right">{{ number_format($shift->cash_sales, 2) }} Kz</td></tr>
        <tr><td>Cartão/TPA</td><td class="right">{{ number_format($shift->card_sales, 2) }} Kz</td></tr>
        <tr><td>Transferência</td><td class="right">{{ number_format($shift->bank_transfer_sales, 2) }} Kz</td></tr>
        <tr><td>Outros</td><td class="right">{{ number_format($shift->other_sales, 2) }} Kz</td></tr>
    </table>
    <hr class="dashed">
    <table>
        <tr>
            <td class="b">TOTAL VENDAS:</td>
            <td class="right b">{{ number_format($shift->total_sales, 2) }} Kz</td>
        </tr>
        <tr><td>Nº Faturas:</td><td class="right">{{ $shift->total_invoices }}</td></tr>
        <tr><td>Nº Recibos:</td><td class="right">{{ $shift->total_receipts }}</td></tr>
    </table>

    @if($shift->isClosed())
    <hr class="solid">
    <h4>CONFERÊNCIA DE CAIXA</h4>
    <table>
        <tr><td>Saldo Inicial:</td><td class="right">{{ number_format($shift->opening_balance, 2) }}</td></tr>
        <tr><td>+ Vendas Dinheiro:</td><td class="right">{{ number_format($shift->cash_sales, 2) }}</td></tr>
        <tr><td class="b">= Esperado:</td><td class="right b">{{ number_format($shift->expected_cash, 2) }}</td></tr>
        <tr><td>Contado:</td><td class="right">{{ number_format($shift->actual_cash, 2) }}</td></tr>
        <tr>
            <td class="b">Diferença:</td>
            <td class="right b">
                {{ ($shift->cash_difference >= 0 ? '+' : '') . number_format($shift->cash_difference, 2) }} Kz
            </td>
        </tr>
    </table>
    @if($shift->difference_reason)
    <hr class="dashed">
    <div><span class="b">Justif.:</span> {{ $shift->difference_reason }}</div>
    @endif
    @endif

    @if($shift->closing_notes)
    <hr class="dashed">
    <div><span class="b">Notas fecho:</span> {{ $shift->closing_notes }}</div>
    @endif

    <hr class="solid">
    <div class="center" style="font-size:8px;">
        Emitido {{ now()->format('d/m/Y H:i') }}<br>
        por {{ auth()->user()->name }}
    </div>
    <hr class="dashed">
    <div class="center" style="font-size:8px;">
        <strong>Processado por programa validado</strong><br>
        Certificado AGT N.º {{ $agtCert }}<br>
        Software: SOS ERP - SOLUÇÕES EMPRESARIAIS
    </div>
</body>
</html>
