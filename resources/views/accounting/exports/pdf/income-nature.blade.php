{{--
    A DEMONSTRAÇÃO DE RESULTADOS POR NATUREZA EM PAPEL.

    Esta vista nunca tinha existido: o botão PDF do ecrã dos relatórios dava 500.
    Desenha o que o IncomeStatementNatureService devolve — cada rubrica com o seu
    total e as contas que a compõem, os resultados em cascata e as margens.

    O valor de cada rubrica sai tal como o serviço o calcula (os gastos vêm
    positivos); o sinal com que entra no resultado vai à esquerda do rótulo.
    Uma chave que o serviço passe a devolver e que esta lista não conheça não se
    perde: sai no fim, em «Outras rubricas».
--}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ __('Demonstração de Resultados por Natureza') }} — {{ $company['name'] ?? '' }}</title>
    <style>
        @page { margin: 28px 32px 52px 32px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1f2937; margin: 0; }
        .header { text-align: center; margin-bottom: 16px; }
        .header h1 { font-size: 18px; margin: 6px 0 4px; color: #7c3aed; }
        .header .company { font-size: 13px; font-weight: bold; }
        .header .meta { font-size: 10px; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        th { background: #f3e8ff; color: #6b21a8; font-size: 10px; text-transform: uppercase; }
        td.num, th.num { text-align: right; white-space: nowrap; width: 110px; }
        td.sign { width: 14px; text-align: center; color: #6b7280; font-weight: bold; }
        .type-row td { background: #faf5ff; font-weight: bold; color: #7c3aed; }
        .rubrica td { font-weight: bold; }
        .detalhe td { font-size: 10px; color: #4b5563; border-bottom: 1px dotted #e5e7eb; padding-top: 2px; padding-bottom: 2px; }
        .detalhe td.conta { padding-left: 22px; }
        .subtotal td { font-weight: bold; background: #faf5ff; border-top: 1px solid #c4b5fd; color: #6b21a8; }
        .total td { font-weight: bold; font-size: 13px; background: #ede9fe; border-top: 2px solid #7c3aed; }
        .muted { color: #9ca3af; }
        .neg { color: #b91c1c !important; }
        h2 { font-size: 12px; color: #6b21a8; margin: 18px 0 0; text-transform: uppercase; }
        .footer { position: fixed; bottom: -36px; left: 0; right: 0; font-size: 9px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; }
    </style>
</head>
<body>
    @php
        $kz = fn ($v) => number_format((float) $v, 2, ',', '.');
        $pct = fn ($v) => number_format((float) $v, 2, ',', '.').' %';

        // A ordem da demonstração. `r` é uma rubrica (bloco com total e details);
        // `s` é um resultado intermédio (número), `t` o resultado final.
        $ordem = [
            ['r', ['vendas_servicos'], __('Vendas e serviços prestados'), '+'],
            ['r', ['subsidios_exploracao'], __('Subsídios à exploração'), '+'],
            ['r', ['variacoes_producao'], __('Variação nos inventários da produção'), '±'],
            ['r', ['trabalhos_propria_empresa'], __('Trabalhos para a própria entidade'), '+'],
            ['r', ['cmvmc'], __('Custo das mercadorias vendidas e das matérias consumidas'), '−'],
            ['s', ['resultado_bruto'], __('Resultado bruto (vendas − CMVMC)'), ''],
            ['r', ['fst'], __('Fornecimentos e serviços de terceiros'), '−'],
            ['r', ['gastos_pessoal'], __('Gastos com o pessoal'), '−'],
            ['r', ['ajustamentos_inventarios'], __('Ajustamentos de inventários (perdas / reversões)'), '−'],
            ['r', ['imparidades'], __('Imparidades (perdas / reversões)'), '−'],
            ['r', ['provisoes'], __('Provisões (aumentos / reduções)'), '−'],
            ['r', ['outros_rendimentos'], __('Outros rendimentos'), '+'],
            ['r', ['outros_gastos'], __('Outros gastos'), '−'],
            ['r', ['depreciações', 'depreciacoes'], __('Gastos de depreciação e amortização'), '−'],
            ['s', ['resultado_operacional'], __('Resultado operacional'), ''],
            ['r', ['juros_rendimentos_similares'], __('Juros e rendimentos similares obtidos'), '+'],
            ['r', ['juros_gastos_similares'], __('Juros e gastos similares suportados'), '−'],
            ['s', ['resultado_antes_impostos'], __('Resultado antes de impostos'), ''],
            ['r', ['imposto_rendimento'], __('Imposto sobre o rendimento do período'), '−'],
            ['t', ['resultado_liquido'], __('Resultado líquido do período'), ''],
        ];

        $margens = [
            'margem_bruta_percent' => __('Margem bruta'),
            'margem_operacional_percent' => __('Margem operacional'),
            'margem_liquida_percent' => __('Margem líquida'),
        ];

        // O que o serviço devolveu e esta vista não conhece.
        $conhecidas = array_keys($margens);
        foreach ($ordem as $o) {
            $conhecidas = array_merge($conhecidas, $o[1]);
        }
        $outras = array_filter(
            $data,
            fn ($v, $k) => ! in_array($k, $conhecidas, true) && (is_numeric($v) || (is_array($v) && array_key_exists('total', $v))),
            ARRAY_FILTER_USE_BOTH
        );

        $valorDe = function (array $chaves) use ($data) {
            foreach ($chaves as $c) {
                if (array_key_exists($c, $data)) {
                    return $data[$c];
                }
            }

            return null;
        };

        $morada = trim(implode(' · ', array_filter([
            $company['address'] ?? '', $company['city'] ?? '', $company['country'] ?? '',
        ])));
    @endphp

    <div class="footer">
        {{ $company['name'] ?? '' }} · {{ __('Valores em Kwanzas (Kz)') }} · {{ __('Emitido em :data', ['data' => now()->format('d/m/Y H:i')]) }}
    </div>

    <div class="header">
        <div class="company">{{ $company['name'] ?? __('Empresa') }}</div>
        @if(!empty($company['nif']))<div class="meta">{{ __('NIF') }}: {{ $company['nif'] }}</div>@endif
        @if($morada !== '')<div class="meta">{{ $morada }}</div>@endif
        <h1>{{ __('Demonstração de Resultados por Natureza') }}</h1>
        <div class="meta">{{ __('Período') }}: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} {{ __('a') }} {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
        <div class="meta">{{ __('Valores em Kwanzas (Kz)') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th></th>
                <th>{{ __('Rubrica / Conta') }}</th>
                <th class="num">{{ __('Contas (Kz)') }}</th>
                <th class="num">{{ __('Rubrica (Kz)') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ordem as [$tipo, $chaves, $rotulo, $sinal])
                @php $valor = $valorDe($chaves); @endphp

                @if($tipo === 'r')
                    @php
                        $bloco = is_array($valor) ? $valor : ['total' => (float) $valor, 'details' => []];
                        $total = (float) ($bloco['total'] ?? 0);
                    @endphp
                    <tr class="rubrica">
                        <td class="sign">{{ $sinal }}</td>
                        <td>{{ $rotulo }}</td>
                        <td></td>
                        <td class="num {{ $total < 0 ? 'neg' : '' }}">{{ $kz($total) }}</td>
                    </tr>
                    @foreach(($bloco['details'] ?? $bloco['detalhes'] ?? []) as $d)
                        @php $saldo = (float) ($d['balance'] ?? $d['saldo'] ?? $d['total'] ?? $d['amount'] ?? 0); @endphp
                        <tr class="detalhe">
                            <td></td>
                            <td class="conta">{{ $d['code'] ?? $d['codigo'] ?? '' }} {{ $d['name'] ?? $d['nome'] ?? '' }}</td>
                            <td class="num {{ $saldo < 0 ? 'neg' : '' }}">{{ $kz($saldo) }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                @else
                    @php $total = (float) $valor; @endphp
                    <tr class="{{ $tipo === 't' ? 'total' : 'subtotal' }}">
                        <td class="sign">=</td>
                        <td colspan="2">{{ $tipo === 't' ? mb_strtoupper($rotulo) : $rotulo }}</td>
                        <td class="num {{ $total < 0 ? 'neg' : '' }}">{{ $kz($total) }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    @if(count($outras) > 0)
        <h2>{{ __('Outras rubricas') }}</h2>
        <table>
            <tbody>
                @foreach($outras as $chave => $valor)
                    @php $total = (float) (is_array($valor) ? ($valor['total'] ?? 0) : $valor); @endphp
                    <tr class="rubrica">
                        <td>{{ ucfirst(str_replace('_', ' ', (string) $chave)) }}</td>
                        <td class="num {{ $total < 0 ? 'neg' : '' }}">{{ $kz($total) }}</td>
                    </tr>
                    @if(is_array($valor))
                        @foreach(($valor['details'] ?? $valor['detalhes'] ?? []) as $d)
                            <tr class="detalhe">
                                <td class="conta">{{ $d['code'] ?? $d['codigo'] ?? '' }} {{ $d['name'] ?? $d['nome'] ?? '' }}</td>
                                <td class="num">{{ $kz($d['balance'] ?? $d['saldo'] ?? $d['total'] ?? $d['amount'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>{{ __('Indicadores') }}</h2>
    <table>
        <tbody>
            @foreach($margens as $chave => $rotulo)
                @php $m = (float) ($data[$chave] ?? 0); @endphp
                <tr>
                    <td>{{ $rotulo }}</td>
                    <td class="num {{ $m < 0 ? 'neg' : '' }}">{{ $pct($m) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
