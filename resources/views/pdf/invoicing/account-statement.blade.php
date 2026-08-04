{{--
    Extracto de conta corrente, para enviar ao cliente ou ao fornecedor.

    Layout em tabelas e DejaVu Sans, como os restantes documentos: é o que o
    DomPDF renderiza de forma previsível.

    Espera: $entidade, $titular, $de, $ate, $movimentos, $resumo, $tenant
--}}
@php
    $ehCliente = $entidade === 'cliente';
    $qtd = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Extracto de conta — {{ $titular->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; margin: 0; padding: 12mm; }
        h1 { font-size: 14px; margin: 0 0 4px 0; }

        .header { display: table; width: 100%; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 8px; }
        .header .left, .header .right { display: table-cell; vertical-align: top; }
        .header .right { text-align: right; width: 42%; }
        .logo-image { max-height: 40px; max-width: 150px; margin-bottom: 4px; }
        .logo-fallback { width: 90px; height: 34px; border: 1px dashed #9ca3af; color: #9ca3af;
                         text-align: center; line-height: 34px; font-size: 9px; margin-bottom: 4px; }

        .doc-title { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
        .doc-sub { font-size: 13px; font-weight: bold; color: #0369a1; margin-top: 2px; }
        .small { font-size: 8px; color: #4b5563; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 3px 5px; border: 1px solid #d4d4d8; }
        th { background: #0369a1; color: #fff; text-align: left; font-size: 8px; text-transform: uppercase; }
        thead { display: table-header-group; }

        table.meta td { border: 1px solid #e5e7eb; }
        table.meta td.k { background: #f9fafb; font-weight: bold; width: 20%; }

        .text-right { text-align: right; }
        tr.transporte td { background: #f9fafb; font-style: italic; }
        tfoot td { background: #f0f9ff; font-weight: bold; }
        .deb { color: #b91c1c; }
        .cre { color: #047857; }

        .footer-note { margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 5px;
                       font-size: 7.5px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>

<div class="header">
    <div class="left">
        @include('pdf.invoicing.partials.logo')
        <h1>{{ $tenant->company_name ?: $tenant->name }}</h1>
        <div class="small">
            NIF: {{ $tenant->nif ?: '—' }}<br>
            {{ $tenant->address ?: '—' }}{{ $tenant->city ? ', ' . $tenant->city : '' }}<br>
            @if($tenant->phone) Tel: {{ $tenant->phone }} @endif
            @if($tenant->email) · {{ $tenant->email }} @endif
        </div>
    </div>
    <div class="right">
        <div class="doc-title">Extracto de Conta Corrente</div>
        <div class="doc-sub">{{ $ehCliente ? 'Cliente' : 'Fornecedor' }}</div>
        <div class="small" style="margin-top:4px;">
            Emitido em {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</div>

<table class="meta">
    <tr>
        <td class="k">{{ $ehCliente ? 'Cliente' : 'Fornecedor' }}</td>
        <td>{{ $titular->name }}</td>
        <td class="k">NIF</td>
        <td>{{ $titular->nif ?: '—' }}</td>
    </tr>
    <tr>
        <td class="k">Período</td>
        <td>{{ \Carbon\Carbon::parse($de)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($ate)->format('d/m/Y') }}</td>
        <td class="k">Movimentos</td>
        <td>{{ $resumo['movimentos'] }}</td>
    </tr>
</table>

<table style="margin-top:8px;">
    <thead>
        <tr>
            <th style="width:60px;">Data</th>
            <th style="width:40px;">Tipo</th>
            <th>Documento</th>
            <th class="text-right" style="width:80px;">Débito</th>
            <th class="text-right" style="width:80px;">Crédito</th>
            <th class="text-right" style="width:88px;">Saldo</th>
        </tr>
    </thead>
    <tbody>
        {{-- O saldo transportado é uma linha: sem ele, a coluna de saldo parece
             começar do nada e o extracto de um período parcial mente. --}}
        <tr class="transporte">
            <td>{{ \Carbon\Carbon::parse($de)->format('d/m/Y') }}</td>
            <td>—</td>
            <td>Saldo transportado</td>
            <td></td>
            <td></td>
            <td class="text-right">{{ $qtd($resumo['saldo_anterior']) }}</td>
        </tr>

        @forelse($movimentos as $m)
            <tr>
                <td>{{ \Carbon\Carbon::parse($m->data)->format('d/m/Y') }}</td>
                <td>{{ $m->tipo }}</td>
                <td>{{ $m->numero }}</td>
                <td class="text-right deb">{{ (float) $m->debito != 0 ? $qtd($m->debito) : '' }}</td>
                <td class="text-right cre">{{ (float) $m->credito != 0 ? $qtd($m->credito) : '' }}</td>
                <td class="text-right">{{ $qtd($m->saldo) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;">Sem movimentos no período</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="text-right">TOTAIS DO PERÍODO</td>
            <td class="text-right deb">{{ $qtd($resumo['debito']) }}</td>
            <td class="text-right cre">{{ $qtd($resumo['credito']) }}</td>
            <td class="text-right">{{ $qtd($resumo['saldo_final']) }}</td>
        </tr>
        <tr>
            <td colspan="5" class="text-right">
                {{ $resumo['saldo_final'] > 0
                    ? ($ehCliente ? 'SALDO EM DÍVIDA DO CLIENTE' : 'SALDO EM DÍVIDA AO FORNECEDOR')
                    : 'CONTA SALDADA' }}
            </td>
            <td class="text-right">{{ $qtd($resumo['saldo_final']) }}</td>
        </tr>
    </tfoot>
</table>

<div class="footer-note">
    Documento informativo de conferência de conta corrente — não é documento fiscal.<br>
    {{ $tenant->company_name ?: $tenant->name }} · {{ $titular->name }} · gerado pelo SOS ERP
</div>

</body>
</html>
