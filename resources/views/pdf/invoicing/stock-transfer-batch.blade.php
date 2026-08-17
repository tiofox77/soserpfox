{{--
    Guia de Transferência de Stock (lote MOV/AAAA/NNNNNN).

    Documento INTERNO de conferência entre armazéns: não é fiscal, não leva
    assinatura AGT nem série de faturação, e — isto é importante — NÃO substitui
    a guia de transporte. Mercadoria a circular na via pública precisa do
    documento de transporte próprio; este serve para conferir o que saiu de um
    armazém e entrou no outro.

    Cada artigo tem duas pernas gravadas, uma por armazém. O documento junta-as
    numa linha só, para se ler de uma vez quanto havia de cada lado, quanto se
    moveu e com quanto cada lado ficou.

    Layout em tabelas e fonte DejaVu Sans porque é o que o DomPDF renderiza de
    forma previsível — flexbox e grid saem desalinhados.

    Espera: $reference, $movimentos (Collection<StockMovement>), $tenant
--}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Transferência de Stock {{ $reference }}</title>
    <style>
        /* Medidas afinadas contra o DomPDF, não contra o browser: o DejaVu Sans
           embutido é mais largo do que a fonte que um browser escolhe, por isso
           um layout que cabe numa folha em pré-visualização passava para a
           segunda ao gerar o PDF. */
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
        .doc-ref { font-size: 14px; font-weight: bold; color: #4338ca; margin-top: 2px; }

        .small { font-size: 8px; color: #4b5563; }

        h2.section { font-size: 10px; color: #4338ca; border-bottom: 1px solid #4338ca;
                     padding-bottom: 2px; margin: 9px 0 4px 0; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2.5px 4px; border: 1px solid #d4d4d8; }
        th { background: #4338ca; color: #fff; text-align: left; font-size: 8px; text-transform: uppercase; }
        /* Num lote longo o documento passa a folhas seguintes e o cabeçalho da
           tabela repete-se em cada uma. */
        thead { display: table-header-group; }

        table.meta td { border: 1px solid #e5e7eb; }
        table.meta td.k { background: #f9fafb; font-weight: bold; width: 22%; }

        /* Rota: origem → destino, em destaque no topo. */
        .rota { display: table; width: 100%; margin: 8px 0; }
        .rota .lado { display: table-cell; width: 45%; vertical-align: middle;
                      border: 1px solid #d4d4d8; padding: 6px 8px; }
        .rota .seta { display: table-cell; width: 10%; text-align: center; vertical-align: middle;
                      font-size: 16px; font-weight: bold; color: #4338ca; }
        .rota .origem  { background: #fef2f2; border-color: #fecaca; }
        .rota .destino { background: #f0fdf4; border-color: #bbf7d0; }
        .rota .rotulo { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .rota .nome { font-size: 11px; font-weight: bold; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .nowrap { white-space: nowrap; }

        .qtd-mov { font-weight: bold; color: #4338ca; }
        .antes { color: #6b7280; }
        .depois { font-weight: bold; }
        .zerado { color: #b91c1c; font-weight: bold; }

        /* Sub-cabeçalho que separa as colunas da origem das do destino. */
        th.grupo-origem  { background: #b91c1c; }
        th.grupo-destino { background: #047857; }

        tfoot td { background: #eef2ff; font-weight: bold; }

        .sign { display: table; width: 100%; margin-top: 26px; }
        .sign .cell { display: table-cell; width: 33.33%; padding-right: 14px; vertical-align: bottom; }
        .sign .rule { border-top: 1px solid #111; margin-top: 34px; padding-top: 3px; font-size: 9px; text-align: center; }

        .footer-note { margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 6px;
                       font-size: 8.5px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>

@php
    // As duas pernas de cada artigo: a negativa é a origem, a positiva o destino.
    // Agrupa-se por produto para as juntar numa linha só.
    $porProduto = $movimentos->groupBy('product_id');

    $primeiro = $movimentos->first();

    $saidas  = $movimentos->filter(fn ($m) => (float) $m->quantity < 0);
    $entradas = $movimentos->filter(fn ($m) => (float) $m->quantity > 0);

    // O armazém lê-se do movimento e não do ecrã: numa reimpressão o filtro do
    // ecrã já mudou. `from_warehouse_id` só existe nas transferências novas —
    // nas antigas deriva-se do sinal da quantidade.
    $armazemOrigem  = optional($saidas->first())->warehouse;
    $armazemDestino = optional($entradas->first())->warehouse;

    $totalQtd   = $entradas->sum(fn ($m) => (float) $m->quantity);
    $totalValor = $entradas->sum(fn ($m) => (float) $m->quantity * (float) ($m->unit_cost ?? 0));

    // Quantidades de stock são inteiras na esmagadora maioria dos casos: mostrar
    // "12" em vez de "12,0000" poupa uma coluna inteira de ruído.
    $qtd = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
@endphp

<div class="header">
    <div class="left">
        @include('pdf.invoicing.partials.logo')
        <h1>{{ $tenant->nomeParaDocumentos() }}</h1>
        <div class="small">
            NIF: {{ $tenant->nif ?: '—' }}<br>
            {{ $tenant->address ?: '—' }}{{ $tenant->city ? ', ' . $tenant->city : '' }}<br>
            @if($tenant->phone) Tel: {{ $tenant->phone }} @endif
            @if($tenant->email) · {{ $tenant->email }} @endif
        </div>
    </div>
    <div class="right">
        <div class="doc-title">Transferência de Stock</div>
        <div class="doc-ref">{{ $reference }}</div>
        <div class="small" style="margin-top:4px;">
            Emitido em {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</div>

<div class="rota">
    <div class="lado origem">
        <div class="rotulo">Armazém de origem</div>
        <div class="nome">{{ $armazemOrigem->name ?? '—' }}</div>
        @if(!empty($armazemOrigem->code))
            <div class="small">{{ $armazemOrigem->code }}</div>
        @endif
    </div>
    <div class="seta">&#8594;</div>
    <div class="lado destino">
        <div class="rotulo">Armazém de destino</div>
        <div class="nome">{{ $armazemDestino->name ?? '—' }}</div>
        @if(!empty($armazemDestino->code))
            <div class="small">{{ $armazemDestino->code }}</div>
        @endif
    </div>
</div>

<table class="meta">
    <tr>
        <td class="k">Data da transferência</td>
        <td>{{ optional($primeiro->created_at)->format('d/m/Y H:i') ?: '—' }}</td>
        <td class="k">Registado por</td>
        <td>{{ optional($primeiro->user)->name ?: '—' }}</td>
    </tr>
    <tr>
        <td class="k">Artigos</td>
        <td>{{ $porProduto->count() }}</td>
        <td class="k">Quantidade total</td>
        <td>{{ $qtd($totalQtd) }}</td>
    </tr>
    <tr>
        <td class="k">Nota</td>
        <td colspan="3">{{ $primeiro->notes ?: '—' }}</td>
    </tr>
</table>

<h2 class="section">Artigos transferidos</h2>

<table>
    <thead>
        <tr>
            <th class="text-center" style="width:24px;" rowspan="2">#</th>
            <th style="width:80px;" rowspan="2">Código</th>
            <th rowspan="2">Produto</th>
            <th class="text-right" style="width:58px;" rowspan="2">Transf.</th>
            <th class="text-center grupo-origem" colspan="2">Origem</th>
            <th class="text-center grupo-destino" colspan="2">Destino</th>
            <th class="text-right" style="width:74px;" rowspan="2">Total (Kz)</th>
        </tr>
        <tr>
            <th class="text-right grupo-origem" style="width:52px;">Antes</th>
            <th class="text-right grupo-origem" style="width:52px;">Ficou</th>
            <th class="text-right grupo-destino" style="width:52px;">Antes</th>
            <th class="text-right grupo-destino" style="width:52px;">Ficou</th>
        </tr>
    </thead>
    <tbody>
        @foreach($porProduto as $i => $pernas)
            @php
                $saida   = $pernas->first(fn ($m) => (float) $m->quantity < 0);
                $entrada = $pernas->first(fn ($m) => (float) $m->quantity > 0);

                // A quantidade movida é a mesma nas duas pernas; lê-se da que
                // existir, porque um lote antigo pode ter só uma gravada.
                $movida  = abs((float) optional($entrada ?: $saida)->quantity);
                $produto = optional($entrada ?: $saida)->product;
                $custo   = (float) (optional($entrada ?: $saida)->unit_cost ?? 0);
            @endphp
            <tr>
                <td class="text-center">{{ $loop->iteration }}</td>
                <td class="small nowrap">{{ optional($produto)->code ?: (optional($produto)->barcode ?: '—') }}</td>
                <td>
                    {{ optional($produto)->name ?: '(produto removido)' }}
                    @if(optional($produto)->unit)
                        <span class="small">· {{ $produto->unit }}</span>
                    @endif
                </td>
                <td class="text-right qtd-mov">{{ $qtd($movida) }}</td>

                {{-- Saldos gravados no momento do movimento. Numa reimpressão o
                     stock de hoje já não é o de então — mostrar o actual seria
                     escrever no documento uma coisa que nunca aconteceu. Os
                     lotes anteriores a estas colunas mostram "—". --}}
                <td class="text-right antes">{{ $qtd(optional($saida)->balance_before) }}</td>
                <td class="text-right {{ optional($saida)->balance_after !== null && (float) $saida->balance_after <= 0 ? 'zerado' : 'depois' }}">
                    {{ $qtd(optional($saida)->balance_after) }}
                </td>

                <td class="text-right antes">{{ $qtd(optional($entrada)->balance_before) }}</td>
                <td class="text-right depois">{{ $qtd(optional($entrada)->balance_after) }}</td>

                <td class="text-right">{{ $custo > 0 ? number_format($movida * $custo, 2, ',', '.') : '—' }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="text-right">Total ({{ $porProduto->count() }} artigo(s))</td>
            <td class="text-right">{{ $qtd($totalQtd) }}</td>
            <td colspan="4"></td>
            <td class="text-right">{{ number_format($totalValor, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

<div class="sign">
    <div class="cell"><div class="rule">Entregue por (origem)</div></div>
    <div class="cell"><div class="rule">Recebido por (destino)</div></div>
    <div class="cell"><div class="rule">Conferido por</div></div>
</div>

<div class="footer-note">
    Documento interno de conferência entre armazéns — não é documento fiscal e não substitui
    factura nem guia de transporte.<br>
    {{ $tenant->nomeParaDocumentos() }} · {{ $reference }} · gerado pelo SOS ERP
</div>

</body>
</html>
