{{-- Relatório financeiro da Tesouraria, em PDF.

     Os números vêm todos do App\Services\Treasury\RelatoriosDeTesouraria — os
     mesmos que o ecrã mostra. Nada é recalculado aqui: um relatório que dá
     contas diferentes no papel e no ecrã é pior do que não existir. --}}
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 20mm 14mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    h2 { font-size: 12px; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #d1d5db; }
    .cab { text-align: center; margin-bottom: 14px; }
    .cab .empresa { font-size: 13px; font-weight: bold; }
    .cab .periodo { color: #6b7280; font-size: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th { background: #e5e7eb; text-align: left; padding: 5px 6px; font-size: 9px; text-transform: uppercase; }
    td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; }
    .n { text-align: right; }
    .total td { font-weight: bold; border-top: 1px solid #9ca3af; background: #f9fafb; }
    .grande td { font-size: 12px; font-weight: bold; background: #f3f4f6; }
    .vencida { color: #b91c1c; font-weight: bold; }
    .nota { color: #6b7280; font-style: italic; font-size: 9px; margin-top: 10px; }
    .rodape { position: fixed; bottom: -12mm; left: 0; right: 0; text-align: center;
              font-size: 8px; color: #9ca3af; }
</style>
</head>
<body>

@php
    $kz = fn ($v) => number_format((float) $v, 2, ',', '.');
    $cat = fn ($c) => \App\Support\CategoriasDeTesouraria::nome($c);
@endphp

<div class="cab">
    <div class="empresa">{{ $empresa->name }}</div>
    @if($empresa->nif)<div style="font-size:9px;color:#6b7280">NIF {{ $empresa->nif }}</div>@endif
    <h1>{{ $titulo }}</h1>
    <div class="periodo">
        {{ \Carbon\Carbon::parse($de)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($ate)->format('d/m/Y') }}
    </div>
</div>

@if($tipo === 'cash_flow')
    <table>
        <tr class="total"><td>Saldo inicial</td><td class="n">{{ $kz($dados['initialBalance']) }} Kz</td></tr>
    </table>

    <h2>Entradas</h2>
    <table>
        <thead><tr><th>Categoria</th><th class="n">Valor</th></tr></thead>
        <tbody>
        @forelse($dados['incomeByCategory'] as $c)
            <tr><td>{{ $cat($c->category) }}</td><td class="n">{{ $kz($c->total) }} Kz</td></tr>
        @empty
            <tr><td colspan="2">Sem entradas no período.</td></tr>
        @endforelse
        <tr class="total"><td>Total de entradas</td><td class="n">{{ $kz($dados['totalIncome']) }} Kz</td></tr>
        </tbody>
    </table>

    <h2>Saídas</h2>
    <table>
        <thead><tr><th>Categoria</th><th class="n">Valor</th></tr></thead>
        <tbody>
        @forelse($dados['expenseByCategory'] as $c)
            <tr><td>{{ $cat($c->category) }}</td><td class="n">{{ $kz($c->total) }} Kz</td></tr>
        @empty
            <tr><td colspan="2">Sem saídas no período.</td></tr>
        @endforelse
        <tr class="total"><td>Total de saídas</td><td class="n">{{ $kz($dados['totalExpense']) }} Kz</td></tr>
        </tbody>
    </table>

    <table>
        <tr class="grande">
            <td>Saldo final</td>
            <td class="n">{{ $kz($dados['finalBalance']) }} Kz</td>
        </tr>
    </table>

@elseif($tipo === 'dre')
    <table>
        <tbody>
        <tr><td>Receita bruta (facturas, com IVA)</td><td class="n">{{ $kz($dados['grossRevenue']) }} Kz</td></tr>
        <tr><td>Devoluções (notas de crédito)</td><td class="n">({{ $kz($dados['deductions']) }}) Kz</td></tr>
        <tr class="total"><td>Receita líquida</td><td class="n">{{ $kz($dados['netRevenue']) }} Kz</td></tr>
        <tr><td>Compras (facturas de compra)</td><td class="n">({{ $kz($dados['operationalCosts']) }}) Kz</td></tr>
        <tr class="total"><td>Lucro bruto</td><td class="n">{{ $kz($dados['grossProfit']) }} Kz</td></tr>
        </tbody>
    </table>

    <h2>Despesas por categoria</h2>
    <table>
        <thead><tr><th>Categoria</th><th class="n">Valor</th></tr></thead>
        <tbody>
        @forelse($dados['expensesByCategory'] as $c)
            <tr><td>{{ $cat($c->category) }}</td><td class="n">{{ $kz($c->total) }} Kz</td></tr>
        @empty
            <tr><td colspan="2">Sem despesas registadas no período.</td></tr>
        @endforelse
        <tr class="total"><td>Total de despesas</td><td class="n">{{ $kz($dados['totalExpenses']) }} Kz</td></tr>
        </tbody>
    </table>

    <table>
        <tbody>
        <tr class="total"><td>Lucro operacional</td><td class="n">{{ $kz($dados['operationalProfit']) }} Kz</td></tr>
        <tr class="grande"><td>Resultado líquido</td><td class="n">{{ $kz($dados['netProfit']) }} Kz</td></tr>
        </tbody>
    </table>

    <p class="nota">
        Valores com IVA. As despesas não repetem o que já está noutra rubrica: pagamentos de compras,
        devoluções a clientes e movimentos ligados a facturas. As compras de mercadoria contam aqui
        pela factura; o resultado económico, com o custo do que foi vendido, está no DRE Integrado.
    </p>

@elseif($tipo === 'dre_integrado')
    <table>
        <tbody>
        @foreach($dados['linhas'] as $l)
            <tr class="{{ $l['final'] ? 'grande' : ($l['total'] ? 'total' : '') }}">
                <td style="padding-left: {{ 6 + 12 * $l['nivel'] }}px">{{ $l['rotulo'] }}</td>
                <td class="n">{{ $l['valor'] < 0 ? '(' . $kz(abs($l['valor'])) . ')' : $kz($l['valor']) }} Kz</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if(count($dados['despesas']['por_categoria']))
        <h2>Despesas pagas pela tesouraria, por categoria</h2>
        <table>
            <thead><tr><th>Categoria</th><th class="n">Valor</th></tr></thead>
            <tbody>
            @foreach($dados['despesas']['por_categoria'] as $c)
                <tr><td>{{ $c['rotulo'] }}</td><td class="n">{{ $kz($c['valor']) }} Kz</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if(count($dados['fora_do_resultado']))
        <h2>Movimentos que não entram no resultado</h2>
        <table>
            <thead><tr><th>Natureza</th><th class="n">Saídas</th><th class="n">Entradas</th></tr></thead>
            <tbody>
            @foreach($dados['fora_do_resultado'] as $f)
                <tr><td>{{ $f['rotulo'] }}</td><td class="n">{{ $kz($f['saidas']) }} Kz</td><td class="n">{{ $kz($f['entradas']) }} Kz</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2>Últimos seis meses</h2>
    <table>
        <thead><tr><th>Mês</th><th class="n">Receita líquida</th><th class="n">CMV</th><th class="n">Despesas</th><th class="n">Resultado</th></tr></thead>
        <tbody>
        @foreach($dados['mensal'] as $m)
            <tr><td>{{ $m['mes'] }}</td><td class="n">{{ $kz($m['receita_liquida']) }}</td><td class="n">{{ $kz($m['cmv']) }}</td>
                <td class="n">{{ $kz($m['despesas']) }}</td><td class="n">{{ $kz($m['resultado']) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <p class="nota">
        Sem IVA. Receita pelas facturas e notas emitidas; CMV ao custo de compra à data da venda;
        despesas pela natureza das categorias da tesouraria e pela parte de serviços das facturas de compra.
        Compras de stock, pagamentos de facturas, transferências e recebimentos não entram no resultado.
    </p>

@elseif($tipo === 'receivables' || $tipo === 'payables')
    @php
        $ehReceber = $tipo === 'receivables';
        $linhas = $ehReceber ? $dados['receivables'] : $dados['payables'];
        $totalGeral = $ehReceber ? $dados['totalReceivables'] : $dados['totalPayables'];
        $quem = $ehReceber ? 'client' : 'supplier';
        $rotulo = $ehReceber ? 'Cliente' : 'Fornecedor';
    @endphp

    <table>
        <thead>
            <tr>
                <th>Documento</th>
                <th>{{ $rotulo }}</th>
                <th>Data</th>
                <th>Vencimento</th>
                <th class="n">Total</th>
                <th class="n">Pago</th>
                <th class="n">Em dívida</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
        @forelse($linhas as $l)
            <tr>
                <td>{{ $l['invoice_number'] }}</td>
                <td>{{ $l[$quem] ?? '—' }}</td>
                <td>{{ optional($l['invoice_date'])->format('d/m/Y') }}</td>
                <td>{{ optional($l['due_date'])->format('d/m/Y') }}</td>
                <td class="n">{{ $kz($l['total']) }}</td>
                <td class="n">{{ $kz($l['paid']) }}</td>
                <td class="n">{{ $kz($l['balance']) }}</td>
                <td class="{{ $l['overdue'] ? 'vencida' : '' }}">{{ $l['overdue'] ? 'VENCIDA' : 'Em prazo' }}</td>
            </tr>
        @empty
            <tr><td colspan="8">Sem documentos em aberto neste período.</td></tr>
        @endforelse
        <tr class="total">
            <td colspan="6">Total em dívida</td>
            <td class="n">{{ $kz($totalGeral) }} Kz</td>
            <td></td>
        </tr>
        @if($dados['totalOverdue'] > 0)
        <tr class="total">
            <td colspan="6" class="vencida">Do qual já vencido</td>
            <td class="n vencida">{{ $kz($dados['totalOverdue']) }} Kz</td>
            <td></td>
        </tr>
        @endif
        </tbody>
    </table>
@endif

<div class="rodape">
    {{ $empresa->name }} — {{ $titulo }} — emitido em {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()?->name }}
</div>

</body>
</html>
