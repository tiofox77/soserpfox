{{--
    Documento de Movimentação de Stock (lote MOV/AAAA/NNNNNN).

    Documento INTERNO de conferência de armazém: não é fiscal, não leva
    assinatura AGT nem série de faturação. O rodapé diz isso de forma explícita
    para ninguém o entregar a um cliente a pensar que é uma factura.

    Layout em tabelas e fonte DejaVu Sans porque é o que o DomPDF renderiza de
    forma previsível — flexbox e grid saem desalinhados.

    Espera: $reference, $movimentos (Collection<StockMovement>), $tenant, $armazem
--}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Movimentação de Stock {{ $reference }}</title>
    <style>
        /* Medidas afinadas contra o DomPDF, não contra o browser: o DejaVu Sans
           embutido é bastante mais largo do que a fonte que um browser escolhe,
           por isso um layout que cabe numa folha em pré-visualização passava
           para a segunda ao gerar o PDF. Foi medido por contagem de páginas. */
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; margin: 0; padding: 12mm; }
        h1, h2 { margin: 0 0 4px 0; }
        h1 { font-size: 14px; }

        .header { display: table; width: 100%; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 8px; }
        .header .left, .header .right { display: table-cell; vertical-align: top; }
        .header .right { text-align: right; width: 40%; }
        .logo-image { max-height: 40px; max-width: 150px; margin-bottom: 4px; }
        .logo-fallback { width: 90px; height: 34px; border: 1px dashed #9ca3af; color: #9ca3af;
                         text-align: center; line-height: 34px; font-size: 9px; margin-bottom: 4px; }

        .doc-title { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
        .doc-ref { font-size: 14px; font-weight: bold; color: #047857; margin-top: 2px; }

        .small { font-size: 8px; color: #4b5563; }

        h2.section { font-size: 10px; color: #047857; border-bottom: 1px solid #047857;
                     padding-bottom: 2px; margin: 9px 0 4px 0; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2.5px 4px; border: 1px solid #d4d4d8; }
        th { background: #047857; color: #fff; text-align: left; font-size: 8px; text-transform: uppercase; }
        /* Num lote longo o documento passa a folhas seguintes e o cabeçalho da
           tabela repete-se em cada uma. */
        thead { display: table-header-group; }

        table.meta td { border: 1px solid #e5e7eb; }
        table.meta td.k { background: #f9fafb; font-weight: bold; width: 22%; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .nowrap { white-space: nowrap; }

        .op-in  { color: #047857; font-weight: bold; }
        .op-out { color: #b91c1c; font-weight: bold; }
        tr.line-out td { background: #fef2f2; }

        tfoot td { background: #f0fdf4; font-weight: bold; }

        .sign { display: table; width: 100%; margin-top: 26px; }
        .sign .cell { display: table-cell; width: 33.33%; padding-right: 14px; vertical-align: bottom; }
        .sign .rule { border-top: 1px solid #111; margin-top: 34px; padding-top: 3px; font-size: 9px; text-align: center; }

        .footer-note { margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 6px;
                       font-size: 8.5px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>

@php
    // Classificação pelo EFEITO no armazém, não pelo `type`. As entradas e
    // saídas do ecrã de movimentação gravam a quantidade sempre positiva e
    // distinguem-se por `type`; os ajustes do ecrã de transferências gravam-na
    // com sinal e ficam ambos em `adjustment`. Filtrar só por `type` deixava as
    // linhas de ajuste fora dos dois totais — apareciam na tabela e não somavam
    // em lado nenhum.
    $ehSaida = fn ($m) => $m->type === 'out' || (float) $m->quantity < 0;

    $saidas   = $movimentos->filter($ehSaida);
    $entradas = $movimentos->reject($ehSaida);

    // Valor absoluto: os ajustes de saída trazem a quantidade negativa e sem
    // isto o total de saídas vinha negativo, para ser mostrado a seguir a um
    // "−" escrito à mão — dois sinais para a mesma coisa.
    $qtdEntradas  = $entradas->sum(fn ($m) => abs((float) $m->quantity));
    $qtdSaidas    = $saidas->sum(fn ($m) => abs((float) $m->quantity));

    // Os dois totais de valor são somados. Somar só as entradas deixava na
    // coluna "Total" linhas de saída que ninguém conseguia reconciliar com o
    // rodapé — quem confere um documento soma a coluna.
    $valorEntradas = $entradas->sum(fn ($m) => abs((float) $m->quantity) * (float) ($m->unit_cost ?? 0));
    $valorSaidas   = $saidas->sum(fn ($m) => abs((float) $m->quantity) * (float) ($m->unit_cost ?? 0));

    $primeiro = $movimentos->first();

    // Quantidades de stock são inteiras na esmagadora maioria dos casos: mostrar
    // "12" em vez de "12,0000" poupa uma coluna inteira de ruído.
    $qtd = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
@endphp

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
        <div class="doc-title">Movimentação de Stock</div>
        <div class="doc-ref">{{ $reference }}</div>
        <div class="small" style="margin-top:4px;">
            Emitido em {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</div>

<table class="meta">
    <tr>
        <td class="k">Armazém</td>
        <td>{{ $armazem->name ?? '—' }}{{ !empty($armazem->code) ? ' (' . $armazem->code . ')' : '' }}</td>
        <td class="k">Data do registo</td>
        <td>{{ optional($primeiro->created_at)->format('d/m/Y H:i') ?: '—' }}</td>
    </tr>
    <tr>
        <td class="k">Registado por</td>
        <td>{{ optional($primeiro->user)->name ?: '—' }}</td>
        <td class="k">Linhas</td>
        <td>
            {{ $movimentos->count() }}
            ({{ $entradas->count() }} entrada(s), {{ $saidas->count() }} saída(s))
        </td>
    </tr>
    <tr>
        <td class="k">Nota</td>
        <td colspan="3">{{ $primeiro->notes ?: '—' }}</td>
    </tr>
</table>

<h2 class="section">Movimentos</h2>

<table>
    <thead>
        <tr>
            <th class="text-center" style="width:26px;">#</th>
            <th style="width:88px;">Código</th>
            <th>Produto</th>
            <th class="text-center" style="width:58px;">Op.</th>
            <th class="text-right" style="width:62px;">Qtd.</th>
            <th class="text-right" style="width:70px;">Custo un.</th>
            <th class="text-right" style="width:78px;">Total (Kz)</th>
            <th class="text-right" style="width:56px;">Antes</th>
            <th class="text-right" style="width:56px;">Ficou</th>
        </tr>
    </thead>
    <tbody>
        @foreach($movimentos as $i => $m)
            @php
                $saida = $ehSaida($m);
                $total = abs((float) $m->quantity) * (float) ($m->unit_cost ?? 0);
            @endphp
            <tr class="{{ $saida ? 'line-out' : '' }}">
                <td class="text-center">{{ $i + 1 }}</td>
                <td class="small nowrap">{{ optional($m->product)->code ?: (optional($m->product)->barcode ?: '—') }}</td>
                <td>
                    {{ optional($m->product)->name ?: '(produto removido)' }}
                    @if(optional($m->product)->unit)
                        <span class="small">· {{ $m->product->unit }}</span>
                    @endif
                </td>
                <td class="text-center {{ $saida ? 'op-out' : 'op-in' }}">{{ $saida ? '− Saída' : '+ Entrada' }}</td>
                {{-- Valor absoluto: o sinal já está na coluna da operação, e um
                     "-3" ao lado de "− Saída" lê-se como duas negações. --}}
                <td class="text-right">{{ $qtd(abs((float) $m->quantity)) }}</td>
                <td class="text-right">{{ $m->unit_cost !== null ? number_format((float) $m->unit_cost, 2, ',', '.') : '—' }}</td>
                <td class="text-right">{{ $total > 0 ? number_format($total, 2, ',', '.') : '—' }}</td>
                {{-- Saldos gravados no momento do movimento. Numa reimpressão, o
                     stock de hoje já não é o de então — mostrar o actual seria
                     escrever no documento uma coisa que nunca aconteceu. Os
                     movimentos anteriores a estas colunas mostram "—". --}}
                <td class="text-right" style="color:#6b7280;">{{ $m->balance_before !== null ? $qtd($m->balance_before) : '—' }}</td>
                <td class="text-right" style="font-weight:bold;">{{ $m->balance_after !== null ? $qtd($m->balance_after) : '—' }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        @if($entradas->isNotEmpty())
            <tr>
                <td colspan="4" class="text-right">Total entradas ({{ $entradas->count() }})</td>
                <td class="text-right op-in">+ {{ $qtd($qtdEntradas) }}</td>
                <td></td>
                <td class="text-right">{{ number_format($valorEntradas, 2, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        @endif
        @if($saidas->isNotEmpty())
            <tr>
                <td colspan="4" class="text-right">Total saídas ({{ $saidas->count() }})</td>
                <td class="text-right op-out">− {{ $qtd($qtdSaidas) }}</td>
                <td></td>
                <td class="text-right">{{ number_format($valorSaidas, 2, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        @endif
        @if($entradas->isNotEmpty() && $saidas->isNotEmpty())
            @php
                $variacaoQtd   = $qtdEntradas - $qtdSaidas;
                $variacaoValor = $valorEntradas - $valorSaidas;
            @endphp
            <tr>
                <td colspan="4" class="text-right">Variação líquida do armazém</td>
                {{-- Sinal escrito à mão e valor absoluto: o number_format de um
                     negativo traz o hífen ASCII, que ao lado dos "−" das linhas
                     acima parece um travessão diferente. --}}
                <td class="text-right">{{ ($variacaoQtd >= 0 ? '+ ' : '− ') . $qtd(abs($variacaoQtd)) }}</td>
                <td></td>
                <td class="text-right">{{ ($variacaoValor >= 0 ? '+ ' : '− ') . number_format(abs($variacaoValor), 2, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        @endif
    </tfoot>
</table>

<div class="sign">
    <div class="cell"><div class="rule">Registado por</div></div>
    <div class="cell"><div class="rule">Conferido por</div></div>
    <div class="cell"><div class="rule">Responsável do armazém</div></div>
</div>

<div class="footer-note">
    Documento interno de conferência de armazém — não é documento fiscal e não substitui factura,
    guia de transporte ou nota de crédito.<br>
    {{ $tenant->company_name ?: $tenant->name }} · {{ $reference }} · gerado pelo SOS ERP
</div>

</body>
</html>
