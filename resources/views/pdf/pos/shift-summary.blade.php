<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Resumo do Turno {{ $shift->shift_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #000; margin: 0; padding: 15mm; }
        h1, h2, h3 { margin: 0 0 6px 0; }
        h1 { font-size: 18px; }
        h2 { font-size: 13px; color: #16a34a; border-bottom: 1px solid #16a34a; padding-bottom: 3px; margin-top: 10px; }
        .header { display: table; width: 100%; margin-bottom: 10px; border-bottom: 2px solid #000; padding-bottom: 8px; }
        .header .left, .header .right { display: table-cell; vertical-align: top; }
        .header .right { text-align: right; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { padding: 5px 6px; border: 1px solid #d4d4d8; }
        th { background: #16a34a; color: #fff; text-align: left; font-size: 11px; }
        .row { display: table; width: 100%; }
        .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 6px; }
        .kpi { background: #f0fdf4; border: 1px solid #16a34a; padding: 8px; margin-bottom: 5px; border-radius: 4px; }
        .kpi .label { font-size: 10px; color: #15803d; text-transform: uppercase; }
        .kpi .value { font-size: 16px; font-weight: bold; color: #14532d; }
        .totals-row td { background: #f0fdf4; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .small { font-size: 9px; color: #555; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; }
        .badge-open { background: #dcfce7; color: #15803d; }
        .badge-closed { background: #e5e7eb; color: #374151; }
        .diff-pos { color: #15803d; }
        .diff-neg { color: #b91c1c; }
    </style>
</head>
<body>
    <div class="header">
        <div class="left">
            <h1>{{ $tenant->nomeParaDocumentos() }}</h1>
            <div class="small">
                NIF: {{ $tenant->nif ?? '—' }}<br>
                {{ $tenant->address ?? '—' }}<br>
                Tel: {{ $tenant->phone ?? '—' }}
            </div>
        </div>
        <div class="right">
            <h2 style="border:0;color:#000;">{{ !empty($produtos) ? 'FECHO DE TURNO COM PRODUTOS' : 'RESUMO DE TURNO POS' }}</h2>
            <div><strong>{{ $shift->shift_number }}</strong></div>
            <span class="badge {{ $shift->status === 'open' ? 'badge-open' : 'badge-closed' }}">
                {{ $shift->status_label }}
            </span>
        </div>
    </div>

    <h2>Informações do Turno</h2>
    <div class="row">
        <div class="col">
            <table>
                <tr><th>Operador</th><td>{{ optional($shift->user)->name ?? '—' }}</td></tr>
                <tr><th>Aberto em</th><td>{{ optional($shift->opened_at)->format('d/m/Y H:i') }}</td></tr>
                <tr><th>Fechado em</th><td>{{ optional($shift->closed_at)->format('d/m/Y H:i') ?? '—' }}</td></tr>
                <tr><th>Fechado por</th><td>{{ optional($shift->closedBy)->name ?? '—' }}</td></tr>
                <tr><th>Duração</th><td>{{ $shift->duration ? number_format($shift->duration, 2) . 'h' : '—' }}</td></tr>
            </table>
        </div>
        <div class="col">
            <div class="kpi"><div class="label">Total Vendas</div><div class="value">{{ number_format($shift->net_sales, 2) }} Kz</div></div>
            <div class="kpi"><div class="label">Nº Faturas</div><div class="value">{{ $shift->total_invoices }}</div></div>
            <div class="kpi"><div class="label">Nº Recibos</div><div class="value">{{ $shift->total_receipts }}</div></div>
        </div>
    </div>

    <h2>Vendas por Método de Pagamento</h2>
    <table>
        <thead>
            <tr>
                <th>Método</th>
                <th class="text-right">Valor (Kz)</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Dinheiro</td><td class="text-right">{{ number_format($shift->cash_sales, 2) }}</td></tr>
            <tr><td>Cartão (TPA/Multicaixa)</td><td class="text-right">{{ number_format($shift->card_sales, 2) }}</td></tr>
            <tr><td>Transferência Bancária</td><td class="text-right">{{ number_format($shift->bank_transfer_sales, 2) }}</td></tr>
            <tr><td>Outros</td><td class="text-right">{{ number_format($shift->other_sales, 2) }}</td></tr>
            {{-- O QUE SAIU DA GAVETA. Os baldes acima já vêm líquidos — o
                 movimento da devolução é negativo — e por isso o TOTAL tem de
                 ser o líquido, senão não bate com a soma deles. --}}
            @if($shift->credit_notes_amount > 0)
            <tr><td>Devolvido ({{ $shift->total_credit_notes }} NC)</td><td class="text-right">-{{ number_format($shift->credit_notes_amount, 2) }}</td></tr>
            @endif
            <tr class="totals-row">
                <td>TOTAL</td>
                <td class="text-right">{{ number_format($shift->net_sales, 2) }}</td>
            </tr>
            {{-- OS DOCUMENTOS A PRAZO emitidos pelos Documentos (FT, ND, NC sem
                 devolução): saem no fecho, mas não passaram pela gaveta e não
                 entram no TOTAL acima. Ver App\Services\POS\DocumentosNoTurno. --}}
            @php
                $aPrazo = $shift->documentosAPrazo();
                $compras = $shift->comprasDoTurno();
            @endphp
            @if($aPrazo['quantos'] > 0)
            <tr><td>A prazo pelos Documentos ({{ $aPrazo['quantos'] }} doc.) — fora da gaveta</td><td class="text-right">{{ number_format($aPrazo['valor'], 2) }}</td></tr>
            @endif
            {{-- As facturas de compra: não são vendas. O pagamento delas, quando
                 sai da gaveta, já conta nas saídas da gaveta. --}}
            @if($compras['quantos'] > 0)
            <tr><td>Faturas de compra ({{ $compras['quantos'] }} doc.) — fora das vendas</td><td class="text-right">{{ number_format($compras['valor'], 2) }}</td></tr>
            @endif
        </tbody>
    </table>

    @if($shift->isClosed())
    <h2>Conferência de Caixa</h2>
    <table>
        <tr><th>Saldo Inicial (abertura)</th><td class="text-right">{{ number_format($shift->opening_balance, 2) }} Kz</td></tr>
        <tr><th>+ Vendas em Dinheiro</th><td class="text-right">{{ number_format($shift->cash_sales, 2) }} Kz</td></tr>
        @php
            $gaveta = $shift->movimentosDaGaveta();
        @endphp
        @if($gaveta['entradas'] > 0)
        <tr><th>+ Entradas na Gaveta</th><td class="text-right">{{ number_format($gaveta['entradas'], 2) }} Kz</td></tr>
        @endif
        @if($gaveta['saidas'] > 0)
        <tr><th>- Saídas da Gaveta (recolhas, despesas)</th><td class="text-right">{{ number_format($gaveta['saidas'], 2) }} Kz</td></tr>
        @endif
        <tr><th>= Dinheiro Esperado</th><td class="text-right">{{ number_format($shift->expected_cash, 2) }} Kz</td></tr>
        <tr><th>Dinheiro Contado (real)</th><td class="text-right">{{ number_format($shift->actual_cash, 2) }} Kz</td></tr>
        <tr class="totals-row">
            <th>Diferença</th>
            <td class="text-right {{ $shift->cash_difference < 0 ? 'diff-neg' : 'diff-pos' }}">
                {{ ($shift->cash_difference >= 0 ? '+' : '') . number_format($shift->cash_difference, 2) }} Kz
            </td>
        </tr>
    </table>

    @if($shift->difference_reason)
    <p class="small" style="margin-top:8px;"><strong>Justificação da diferença:</strong> {{ $shift->difference_reason }}</p>
    @endif
    @endif

    @if($shift->opening_notes || $shift->closing_notes)
    <h2>Observações</h2>
    @if($shift->opening_notes)
    <p class="small"><strong>Abertura:</strong> {{ $shift->opening_notes }}</p>
    @endif
    @if($shift->closing_notes)
    <p class="small"><strong>Fecho:</strong> {{ $shift->closing_notes }}</p>
    @endif
    @endif

    @if(!empty($produtos))
    @php
        $tp = $produtos['totais'];
        $qtd = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
    @endphp
    {{-- O FECHO COM PRODUTOS (ver App\Services\POS\ProdutosDoTurno). --}}
    <h2>Vendas por Produto ({{ $tp['artigos'] }})</h2>
    <table>
        <thead>
            <tr>
                <th>Artigo</th>
                <th class="text-right">Qtd.</th>
                <th class="text-right">Preço médio</th>
                <th class="text-right">Vendido (Kz)</th>
                <th class="text-right">Devolvido (Kz)</th>
                <th class="text-right">Líquido (Kz)</th>
                <th class="text-right">%</th>
            </tr>
        </thead>
        <tbody>
            @forelse($produtos['produtos'] as $p)
            <tr>
                <td>{{ $p['nome'] }}@if($p['codigo'])<br><span class="small">{{ $p['codigo'] }}</span>@endif</td>
                <td class="text-right">{{ $qtd($p['liquida']) }}@if($p['devolvida'] > 0)<br><span class="small">{{ $qtd($p['quantidade']) }} − {{ $qtd($p['devolvida']) }}</span>@endif</td>
                <td class="text-right">{{ number_format($p['preco_medio'], 2) }}</td>
                <td class="text-right">{{ number_format($p['total'], 2) }}</td>
                <td class="text-right">{{ $p['devolvido'] > 0 ? '-' . number_format($p['devolvido'], 2) : '—' }}</td>
                <td class="text-right"><strong>{{ number_format($p['liquido'], 2) }}</strong></td>
                <td class="text-right">{{ number_format($p['peso'], 1) }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center">Sem artigos vendidos neste turno.</td></tr>
            @endforelse
            <tr class="totals-row">
                <td>TOTAL DOS ARTIGOS</td>
                <td class="text-right">{{ $qtd($tp['quantidade']) }}</td>
                <td></td>
                <td class="text-right">{{ number_format($tp['bruto'], 2) }}</td>
                <td class="text-right">{{ $tp['devolvido'] > 0 ? '-' . number_format($tp['devolvido'], 2) : '—' }}</td>
                <td class="text-right">{{ number_format($tp['bruto'] - $tp['devolvido'], 2) }}</td>
                <td class="text-right">100</td>
            </tr>
        </tbody>
    </table>

    <table>
        <tr><th>Facturas válidas</th><td class="text-right">{{ $tp['facturas'] }}</td><th>Anuladas</th><td class="text-right">{{ $tp['anuladas'] }}</td></tr>
        <tr><th>Notas de crédito</th><td class="text-right">{{ $tp['notas'] }}</td><th>Ticket médio</th><td class="text-right">{{ number_format($tp['ticket_medio'], 2) }} Kz</td></tr>
        <tr><th>Descontos nos documentos</th><td class="text-right">{{ $tp['descontos'] > 0 ? '-' . number_format($tp['descontos'], 2) : '0.00' }} Kz</td><th>IVA incluído</th><td class="text-right">{{ number_format($tp['imposto'], 2) }} Kz</td></tr>
        <tr class="totals-row"><td colspan="3">TOTAL LÍQUIDO DO TURNO</td><td class="text-right">{{ number_format($tp['liquido'], 2) }} Kz</td></tr>
    </table>

    @if(!empty($produtos['documentos']))
    <h2>Documentos do Turno ({{ count($produtos['documentos']) }})</h2>
    <table>
        <thead>
            <tr>
                <th>Hora</th>
                <th>Documento</th>
                <th>Cliente</th>
                <th>Pagamento</th>
                <th class="text-right">Artigos</th>
                <th class="text-right">Total (Kz)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($produtos['documentos'] as $d)
            <tr @if($d['anulada']) style="color:#9ca3af;text-decoration:line-through" @endif>
                <td>{{ $d['hora'] }}</td>
                {{-- Os DOIS números, cada um com o seu nome: sem rótulo, a
                     série interna e a da AGT confundiam-se no papel. --}}
                <td><span style="font-size:8px;color:#666;">Interna:</span> {{ $d['numero'] }}@if($d['anulada']) (anulada)@endif
                    <div style="font-size:8px;color:#666;">AGT: {{ $d['numero_agt'] ?: '—' }}</div>
                </td>
                <td>{{ $d['cliente'] ?? '—' }}</td>
                <td>{{ $d['meio'] }}</td>
                <td class="text-right">{{ $d['artigos'] }}</td>
                <td class="text-right {{ $d['total'] < 0 ? 'diff-neg' : '' }}">{{ number_format($d['total'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    @endif

    @if($shift->transactions->isNotEmpty())
    <h2>Transações ({{ $shift->transactions->count() }})</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Hora</th>
                <th>Tipo</th>
                <th>Referência</th>
                <th>Método</th>
                <th class="text-right">Valor (Kz)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($shift->transactions as $idx => $tx)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ optional($tx->created_at)->format('H:i') }}</td>
                <td>{{ strtoupper($tx->type) }}</td>
                <td>{{ $tx->reference_number ?? '—' }}</td>
                <td>{{ posPaymentMethodLabel($tx->payment_method) }}</td>
                <td class="text-right">{{ number_format($tx->amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @php
        $agtCert = \App\Helpers\AGTHelper::softwareValidationNumber();
    @endphp

    <div class="small text-center" style="margin-top:15px;border-top:1px dashed #999;padding-top:6px;line-height:1.4;">
        <strong>Processado por programa validado</strong><br>
        Certificado AGT N.º {{ $agtCert }} — Software: <strong>SOS ERP — SOLUÇÕES EMPRESARIAIS</strong><br>
        Documento gerado em {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()->name }}
    </div>
</body>
</html>
