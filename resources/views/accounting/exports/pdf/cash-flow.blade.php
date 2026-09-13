{{--
    A DEMONSTRAÇÃO DE FLUXOS DE CAIXA EM PAPEL (método indirecto).

    Esta vista nunca tinha existido: o botão PDF do ecrã dos relatórios dava 500.
    Desenha o que o CashFlowService devolve — as actividades operacionais (o
    resultado, os ajustamentos e a variação do fundo de maneio), as de
    investimento e as de financiamento, cada uma com o seu total; depois a
    variação líquida de caixa e a reconciliação com o saldo das contas.

    Os valores já vêm com o sinal do efeito na caixa. Uma linha que o serviço
    passe a devolver e que os rótulos abaixo não conheçam sai com o nome da chave.
--}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ __('Demonstração de Fluxos de Caixa') }} — {{ $company['name'] ?? '' }}</title>
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
        .type-row td { background: #faf5ff; font-weight: bold; color: #7c3aed; }
        .grupo td { font-weight: bold; color: #374151; padding-left: 16px; }
        .linha td.rotulo { padding-left: 30px; }
        .linha-directa td.rotulo { padding-left: 16px; }
        .subtotal td { font-weight: bold; border-top: 1px solid #c4b5fd; }
        .total td { font-weight: bold; font-size: 13px; background: #ede9fe; border-top: 2px solid #7c3aed; }
        .neg { color: #b91c1c !important; }
        h2 { font-size: 12px; color: #6b21a8; margin: 18px 0 0; text-transform: uppercase; }
        .status { margin-top: 12px; padding: 8px 10px; font-weight: bold; }
        .status.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .status.ko { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .footer { position: fixed; bottom: -36px; left: 0; right: 0; font-size: 9px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; }
    </style>
</head>
<body>
    @php
        $kz = fn ($v) => number_format((float) $v, 2, ',', '.');

        $rotulos = [
            'net_income' => __('Resultado líquido do período'),
            'adjustments' => __('Ajustamentos (itens sem efeito na caixa)'),
            'depreciation' => __('Depreciações e amortizações'),
            'impairments' => __('Imparidades'),
            'provisions' => __('Provisões'),
            'working_capital' => __('Variação do fundo de maneio'),
            'clients' => __('Clientes'),
            'inventory' => __('Inventários'),
            'suppliers' => __('Fornecedores'),
            'state' => __('Estado e outros entes públicos'),
            'fixed_assets' => __('Activos fixos tangíveis'),
            'intangibles' => __('Activos intangíveis'),
            'investments' => __('Investimentos financeiros'),
            'loans' => __('Empréstimos obtidos (líquidos)'),
            'capital' => __('Realizações de capital'),
            'dividends' => __('Dividendos pagos'),
        ];

        $rotulo = fn ($k) => $rotulos[$k] ?? ucfirst(str_replace('_', ' ', (string) $k));

        $actividades = [
            'operating' => ['A', __('Fluxos de caixa das actividades operacionais')],
            'investment' => ['B', __('Fluxos de caixa das actividades de investimento')],
            'financing' => ['C', __('Fluxos de caixa das actividades de financiamento')],
        ];

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
        <h1>{{ __('Demonstração de Fluxos de Caixa') }}</h1>
        <div class="meta">{{ __('Método indirecto') }} · {{ __('Período') }}: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} {{ __('a') }} {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
        <div class="meta">{{ __('Valores em Kwanzas (Kz)') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('Rubrica') }}</th>
                <th class="num">{{ __('Valor (Kz)') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($actividades as $chave => [$letra, $titulo])
                @php
                    $bloco = is_array($data[$chave] ?? null) ? $data[$chave] : [];
                    $totalBloco = (float) ($bloco['total'] ?? 0);
                @endphp
                <tr class="type-row"><td colspan="2">{{ $letra }}. {{ mb_strtoupper($titulo) }}</td></tr>

                @foreach($bloco as $k => $v)
                    @continue($k === 'total')

                    @if(is_array($v) && $k === 'items')
                        {{-- As linhas do investimento e do financiamento vêm directas em items. --}}
                        @forelse($v as $ik => $iv)
                            <tr class="linha-directa">
                                <td class="rotulo">{{ $rotulo($ik) }}</td>
                                <td class="num {{ (float) $iv < 0 ? 'neg' : '' }}">{{ $kz($iv) }}</td>
                            </tr>
                        @empty
                            <tr class="linha-directa"><td class="rotulo" colspan="2" style="color:#9ca3af">{{ __('Sem movimentos.') }}</td></tr>
                        @endforelse
                    @elseif(is_array($v))
                        {{-- Um grupo com linhas próprias: os ajustamentos e o fundo de maneio. --}}
                        @php $somaGrupo = array_sum(array_map(fn ($x) => is_numeric($x) ? (float) $x : 0, $v)); @endphp
                        <tr class="grupo">
                            <td>{{ $rotulo($k) }}</td>
                            <td class="num {{ $somaGrupo < 0 ? 'neg' : '' }}">{{ $kz($somaGrupo) }}</td>
                        </tr>
                        @foreach($v as $gk => $gv)
                            @if(is_numeric($gv))
                                <tr class="linha">
                                    <td class="rotulo">{{ $rotulo($gk) }}</td>
                                    <td class="num {{ (float) $gv < 0 ? 'neg' : '' }}">{{ $kz($gv) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    @elseif(is_numeric($v))
                        <tr class="linha-directa">
                            <td class="rotulo">{{ $rotulo($k) }}</td>
                            <td class="num {{ (float) $v < 0 ? 'neg' : '' }}">{{ $kz($v) }}</td>
                        </tr>
                    @endif
                @endforeach

                <tr class="subtotal">
                    <td>{{ __('Total') }} ({{ $letra }})</td>
                    <td class="num {{ $totalBloco < 0 ? 'neg' : '' }}">{{ $kz($totalBloco) }}</td>
                </tr>
            @endforeach

            @php $variacao = (float) ($data['net_cash_change'] ?? 0); @endphp
            <tr class="total">
                <td>{{ __('Variação líquida de caixa e equivalentes (A + B + C)') }}</td>
                <td class="num {{ $variacao < 0 ? 'neg' : '' }}">{{ $kz($variacao) }}</td>
            </tr>
        </tbody>
    </table>

    <h2>{{ __('Reconciliação com o saldo de caixa') }}</h2>
    <table>
        <tbody>
            <tr>
                <td>{{ __('Caixa e equivalentes no início do período') }}</td>
                <td class="num {{ (float) ($data['cash_start'] ?? 0) < 0 ? 'neg' : '' }}">{{ $kz($data['cash_start'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>{{ __('Caixa e equivalentes no fim do período') }}</td>
                <td class="num {{ (float) ($data['cash_end'] ?? 0) < 0 ? 'neg' : '' }}">{{ $kz($data['cash_end'] ?? 0) }}</td>
            </tr>
            <tr class="subtotal">
                <td>{{ __('Variação de caixa segundo as contas (fim − início)') }}</td>
                <td class="num {{ (float) ($data['cash_change'] ?? 0) < 0 ? 'neg' : '' }}">{{ $kz($data['cash_change'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>{{ __('Variação líquida segundo a demonstração') }}</td>
                <td class="num {{ $variacao < 0 ? 'neg' : '' }}">{{ $kz($variacao) }}</td>
            </tr>
            <tr>
                <td>{{ __('Diferença') }}</td>
                <td class="num {{ abs((float) ($data['difference'] ?? 0)) >= 0.01 ? 'neg' : '' }}">{{ $kz($data['difference'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($data['reconciled']))
        <div class="status ok">{{ __('A demonstração reconcilia com a variação do saldo de caixa.') }}</div>
    @else
        <div class="status ko">{{ __('A demonstração não reconcilia com a variação do saldo de caixa: diferença de :valor Kz.', ['valor' => $kz($data['difference'] ?? 0)]) }}</div>
    @endif
</body>
</html>
