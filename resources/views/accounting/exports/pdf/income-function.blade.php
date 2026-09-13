{{--
    A DEMONSTRAÇÃO DE RESULTADOS POR FUNÇÕES EM PAPEL.

    Esta vista nunca tinha existido: o botão PDF do ecrã dos relatórios dava 500.
    Desenha o que o IncomeStatementFunctionService devolve — números simples, sem
    contas por baixo: as vendas, os gastos repartidos por função, os resultados
    em cascata e as margens.

    O valor de cada rubrica sai tal como o serviço o calcula (os gastos vêm
    positivos); o sinal com que entra no resultado vai à esquerda do rótulo.
    Uma chave que o serviço passe a devolver e que esta lista não conheça sai no
    fim, em «Outras rubricas».
--}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ __('Demonstração de Resultados por Funções') }} — {{ $company['name'] ?? '' }}</title>
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
        td.num, th.num { text-align: right; white-space: nowrap; width: 130px; }
        td.sign { width: 14px; text-align: center; color: #6b7280; font-weight: bold; }
        .subtotal td { font-weight: bold; background: #faf5ff; border-top: 1px solid #c4b5fd; color: #6b21a8; }
        .total td { font-weight: bold; font-size: 13px; background: #ede9fe; border-top: 2px solid #7c3aed; }
        .neg { color: #b91c1c !important; }
        h2 { font-size: 12px; color: #6b21a8; margin: 18px 0 0; text-transform: uppercase; }
        .nota { margin-top: 14px; font-size: 9px; color: #6b7280; }
        .footer { position: fixed; bottom: -36px; left: 0; right: 0; font-size: 9px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; }
    </style>
</head>
<body>
    @php
        $kz = fn ($v) => number_format((float) $v, 2, ',', '.');
        $pct = fn ($v) => number_format((float) $v, 2, ',', '.').' %';

        // `r` é uma rubrica, `s` um resultado intermédio, `t` o resultado final.
        $ordem = [
            ['r', 'sales', __('Vendas e serviços prestados'), '+'],
            ['r', 'cost_of_sales', __('Custo das vendas e dos serviços prestados'), '−'],
            ['s', 'gross_margin', __('Resultado bruto'), ''],
            ['r', 'distribution_costs', __('Gastos de distribuição'), '−'],
            ['r', 'administrative_costs', __('Gastos administrativos'), '−'],
            ['r', 'rd_costs', __('Gastos de investigação e desenvolvimento'), '−'],
            ['r', 'other_income', __('Outros rendimentos'), '+'],
            ['r', 'other_expenses', __('Outros gastos'), '−'],
            ['s', 'operating_result', __('Resultado operacional'), ''],
            ['r', 'financial_income', __('Rendimentos financeiros'), '+'],
            ['r', 'financial_expenses', __('Gastos financeiros'), '−'],
            ['s', 'result_before_tax', __('Resultado antes de impostos'), ''],
            ['r', 'income_tax', __('Imposto sobre o rendimento do período'), '−'],
            ['t', 'net_income', __('Resultado líquido do período'), ''],
        ];

        $margens = [
            'gross_margin_percent' => __('Margem bruta'),
            'operating_margin_percent' => __('Margem operacional'),
            'net_margin_percent' => __('Margem líquida'),
        ];

        $conhecidas = array_merge(array_keys($margens), array_column($ordem, 1));
        $outras = array_filter(
            $data,
            fn ($v, $k) => ! in_array($k, $conhecidas, true) && (is_numeric($v) || (is_array($v) && array_key_exists('total', $v))),
            ARRAY_FILTER_USE_BOTH
        );

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
        <h1>{{ __('Demonstração de Resultados por Funções') }}</h1>
        <div class="meta">{{ __('Período') }}: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} {{ __('a') }} {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
        <div class="meta">{{ __('Valores em Kwanzas (Kz)') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th></th>
                <th>{{ __('Rubrica') }}</th>
                <th class="num">{{ __('Valor (Kz)') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ordem as [$tipo, $chave, $rotulo, $sinal])
                @php
                    $bruto = $data[$chave] ?? 0;
                    $valor = (float) (is_array($bruto) ? ($bruto['total'] ?? 0) : $bruto);
                @endphp
                @if($tipo === 'r')
                    <tr>
                        <td class="sign">{{ $sinal }}</td>
                        <td>{{ $rotulo }}</td>
                        <td class="num {{ $valor < 0 ? 'neg' : '' }}">{{ $kz($valor) }}</td>
                    </tr>
                @else
                    <tr class="{{ $tipo === 't' ? 'total' : 'subtotal' }}">
                        <td class="sign">=</td>
                        <td>{{ $tipo === 't' ? mb_strtoupper($rotulo) : $rotulo }}</td>
                        <td class="num {{ $valor < 0 ? 'neg' : '' }}">{{ $kz($valor) }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    @if(count($outras) > 0)
        <h2>{{ __('Outras rubricas') }}</h2>
        <table>
            <tbody>
                @foreach($outras as $chave => $bruto)
                    @php $valor = (float) (is_array($bruto) ? ($bruto['total'] ?? 0) : $bruto); @endphp
                    <tr>
                        <td>{{ ucfirst(str_replace('_', ' ', (string) $chave)) }}</td>
                        <td class="num {{ $valor < 0 ? 'neg' : '' }}">{{ $kz($valor) }}</td>
                    </tr>
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
                    <td>{{ $rotulo }} {{ __('(sobre as vendas)') }}</td>
                    <td class="num {{ $m < 0 ? 'neg' : '' }}">{{ $pct($m) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="nota">
        {{ __('Os gastos são repartidos por função segundo a matriz de alocação da empresa; as contas sem alocação configurada seguem as percentagens padrão.') }}
    </div>
</body>
</html>
