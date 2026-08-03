<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Vendas POS</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; margin: 0; padding: 10mm; }
        h1 { font-size: 16px; margin: 0 0 4px 0; }
        .header { border-bottom: 2px solid #16a34a; padding-bottom: 6px; margin-bottom: 8px; display: table; width: 100%; }
        .header .left, .header .right { display: table-cell; vertical-align: top; }
        .header .right { text-align: right; }
        .small { font-size: 9px; color: #555; }
        .filters { background: #f0fdf4; border: 1px solid #16a34a; padding: 6px 8px; margin-bottom: 8px; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 5px; border: 1px solid #d4d4d8; font-size: 9px; }
        th { background: #16a34a; color: #fff; text-align: left; }
        .text-right { text-align: right; }
        .totals-row td { background: #f0fdf4; font-weight: bold; }
        .kpi-row { display: table; width: 100%; margin-bottom: 8px; }
        .kpi { display: table-cell; padding: 6px 8px; border: 1px solid #16a34a; background: #f0fdf4; text-align: center; width: 25%; }
        .kpi .label { font-size: 8px; color: #15803d; text-transform: uppercase; }
        .kpi .value { font-size: 12px; font-weight: bold; color: #14532d; }
    </style>
</head>
<body>
    @php
        $logoUri = app_logo_data_uri();
        $agtCert = \App\Helpers\AGTHelper::softwareValidationNumber();
    @endphp

    <div class="header">
        <div class="left">
            @if($logoUri)
                <img src="{{ $logoUri }}" alt="logo" style="height: 36px; width:auto; margin-bottom: 4px;">
            @endif
            <h1>RELATÓRIO DE VENDAS POS</h1>
            <div class="small">
                <strong>{{ $tenant->company_name ?? $tenant->name }}</strong> — NIF: {{ $tenant->nif ?? '—' }}<br>
                {{ $tenant->address ?? '' }} @if($tenant->phone) | Tel: {{ $tenant->phone }} @endif
            </div>
        </div>
        <div class="right small">
            Emitido: {{ now()->format('d/m/Y H:i') }}<br>
            Por: {{ auth()->user()->name }}
        </div>
    </div>

    <div class="filters">
        <strong>Período:</strong> {{ \Carbon\Carbon::parse($filters['startDate'])->format('d/m/Y') }}
        a {{ \Carbon\Carbon::parse($filters['endDate'])->format('d/m/Y') }}
        @if($filters['search'])| <strong>Pesquisa:</strong> {{ $filters['search'] }} @endif
        @if($filters['status'])| <strong>Status:</strong> {{ $filters['status'] }} @endif
        @if($filters['paymentMethod'])| <strong>Pagamento:</strong> {{ $filters['paymentMethod'] }} @endif
    </div>

    <div class="kpi-row">
        <div class="kpi"><div class="label">Total Vendas</div><div class="value">{{ $totals['count'] }}</div></div>
        <div class="kpi"><div class="label">Receita</div><div class="value">{{ number_format($totals['total'], 2) }} Kz</div></div>
        <div class="kpi"><div class="label">Total IVA</div><div class="value">{{ number_format($totals['tax'], 2) }} Kz</div></div>
        <div class="kpi"><div class="label">Total Desc.</div><div class="value">{{ number_format($totals['discount'], 2) }} Kz</div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fatura</th>
                <th>Data</th>
                <th>Cliente</th>
                <th>NIF</th>
                <th>Pagamento</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">IVA</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoices as $inv)
            <tr>
                <td>{{ $inv->invoice_number }}</td>
                <td>{{ optional($inv->system_entry_date ?? $inv->invoice_date)->format('d/m/Y H:i') }}</td>
                <td>{{ optional($inv->client)->name ?? '—' }}</td>
                <td>{{ optional($inv->client)->nif ?? '—' }}</td>
                <td>{{ posPaymentMethodLabel($inv->payment_method) }}</td>
                <td class="text-right">{{ number_format($inv->subtotal, 2) }}</td>
                <td class="text-right">{{ number_format($inv->tax_amount, 2) }}</td>
                <td class="text-right">{{ number_format($inv->total, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center">Sem vendas no período</td></tr>
            @endforelse
            <tr class="totals-row">
                <td colspan="5" class="text-right">TOTAIS:</td>
                <td class="text-right">{{ number_format($totals['subtotal'], 2) }}</td>
                <td class="text-right">{{ number_format($totals['tax'], 2) }}</td>
                <td class="text-right">{{ number_format($totals['total'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="small" style="margin-top:10px;text-align:center;border-top:1px dashed #999;padding-top:6px;line-height:1.4;">
        <strong>Processado por programa validado</strong> — Certificado AGT N.º {{ $agtCert }}<br>
        Software: <strong>SOS ERP — SOLUÇÕES EMPRESARIAIS</strong>
    </div>
</body>
</html>
