<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Ticket Turno {{ $shift->shift_number }}</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; color: #000; }
        html, body { width: 80mm; }
        body {
            font-family: 'Ubuntu', sans-serif;
            font-size: 11px;
            line-height: 1.3;
            background: #f4f4f5;
            padding: 4mm 2mm;
        }
        .ticket {
            width: 76mm;
            margin: 0 auto;
            background: #fff;
            padding: 3mm 2mm;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .b { font-weight: 700; }
        h3 { font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 2px; }
        h4 { font-size: 11px; font-weight: 700; margin: 4px 0; }
        hr.solid { border: 0; border-top: 1px solid #000; margin: 4px 0; }
        hr.dashed { border: 0; border-top: 1px dashed #888; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; font-size: 11px; }
        .logo { max-height: 32px; width: auto; }
        .actions { max-width: 320px; margin: 6mm auto 0; display: flex; gap: 8px; }
        .actions button {
            flex: 1; padding: 10px 14px; font-family: inherit; font-size: 13px;
            border: 0; border-radius: 6px; cursor: pointer; font-weight: 700;
        }
        .btn-print { background: #16a34a; color: #fff; }
        .btn-close { background: #e5e7eb; color: #111827; }
        @media print {
            body { background: #fff; padding: 1mm; }
            .ticket { box-shadow: none; width: 80mm; padding: 1mm; }
            .actions, .no-print { display: none !important; }
        }
    </style>
</head>
<body>
@php
    $logoUri = app_logo_data_uri();
    $agtCert = \App\Helpers\AGTHelper::softwareValidationNumber();
@endphp
<div class="ticket" id="ticket-content">
    @if($logoUri)
        <div class="center" style="margin-bottom: 4px;">
            <img src="{{ $logoUri }}" alt="logo" class="logo">
        </div>
    @endif
    <div class="center">
        <h3>{{ $tenant->nomeParaDocumentos() }}</h3>
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
    <div class="center" style="font-size:10px;">
        Emitido {{ now()->format('d/m/Y H:i') }}<br>
        por {{ auth()->user()->name }}
    </div>
    <hr class="dashed">
    <div class="center" style="font-size:10px; line-height:1.4;">
        <strong>Processado por programa validado</strong><br>
        Certificado AGT N.º {{ $agtCert }}<br>
        Software: <strong>SOS ERP - SOLUÇÕES EMPRESARIAIS</strong>
    </div>
</div>

<div class="actions no-print">
    <button class="btn-close" onclick="window.close()">✕ Fechar</button>
    <button class="btn-print" onclick="window.print()">🖨 Imprimir</button>
</div>

<script>
    // Auto-print + auto-close (apenas se autoPrint=1)
    @if($autoPrint)
    window.addEventListener('load', function () {
        // pequena espera para garantir fonts/logo carregados
        setTimeout(function () {
            window.print();
        }, 350);
    });
    // Após terminar (ou cancelar) a impressão, fechar a aba
    window.addEventListener('afterprint', function () {
        setTimeout(function () { window.close(); }, 200);
    });
    @endif
</script>
</body>
</html>
