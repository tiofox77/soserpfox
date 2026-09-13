{{--
    O BALANÇO EM PAPEL.

    Esta vista nunca tinha existido: o botão PDF do ecrã dos relatórios dava 500.
    Desenha o que o BalanceSheetService devolve — as três massas (activo,
    passivo, capital próprio) com as contas que as compõem, o total de cada uma,
    e no fim o resumo que diz se o balanço fecha.

    O serviço embrulha cada massa em chave, depois label e items, e só lá dentro
    vem o bloco com total e details. Aceita-se também o bloco directo, para a
    vista não partir no dia em que o serviço aplanar a estrutura.
--}}
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ __('Balanço') }} — {{ $company['name'] ?? '' }}</title>
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
        td.num, th.num { text-align: right; white-space: nowrap; }
        td.code { width: 70px; font-size: 10px; color: #4b5563; }
        .type-row td { background: #faf5ff; font-weight: bold; color: #7c3aed; }
        .subtotal td { font-weight: bold; border-top: 1px solid #c4b5fd; }
        .total td { font-weight: bold; font-size: 13px; background: #ede9fe; border-top: 2px solid #7c3aed; }
        .muted { color: #9ca3af; }
        .neg { color: #b91c1c !important; }
        .indent { padding-left: 22px; color: #4b5563; }
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

        $massas = [
            'activo' => ['rotulo' => __('Activo'), 'total' => 'total_activo', 'fecho' => __('Total do activo')],
            'passivo' => ['rotulo' => __('Passivo'), 'total' => 'total_passivo', 'fecho' => __('Total do passivo')],
            'capital_proprio' => ['rotulo' => __('Capital próprio'), 'total' => 'total_capital_proprio', 'fecho' => __('Total do capital próprio')],
        ];

        // Os blocos (total + details) de uma massa, venham embrulhados ou directos.
        $blocosDe = function (string $chave) use ($data): array {
            $raiz = $data[$chave] ?? [];

            if (! is_array($raiz)) {
                return [];
            }

            if (array_key_exists('total', $raiz) || array_key_exists('details', $raiz)) {
                return [$chave => $raiz];
            }

            $blocos = [];

            foreach ($raiz as $embrulho) {
                foreach ((is_array($embrulho) ? ($embrulho['items'] ?? []) : []) as $k => $bloco) {
                    if (is_array($bloco)) {
                        $blocos[$k] = $bloco;
                    }
                }
            }

            return $blocos;
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
        <h1>{{ __('Balanço') }}</h1>
        <div class="meta">{{ __('À data de :data', ['data' => \Carbon\Carbon::parse($date)->format('d/m/Y')]) }}</div>
        <div class="meta">{{ __('Valores em Kwanzas (Kz)') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('Código') }}</th>
                <th>{{ __('Rubrica / Conta') }}</th>
                <th class="num">{{ __('Valor (Kz)') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($massas as $chave => $massa)
                @php
                    $blocos = $blocosDe($chave);
                    $totalMassa = $data[$massa['total']] ?? array_sum(array_map(fn ($b) => (float) ($b['total'] ?? 0), $blocos));
                @endphp
                <tr class="type-row"><td colspan="3">{{ mb_strtoupper($massa['rotulo']) }}</td></tr>

                @php $linhas = 0; @endphp
                @foreach($blocos as $k => $bloco)
                    @if(count($blocos) > 1)
                        <tr><td></td><td colspan="2" style="font-weight:bold">{{ ucfirst(str_replace('_', ' ', (string) $k)) }}</td></tr>
                    @endif
                    @foreach(($bloco['details'] ?? $bloco['detalhes'] ?? []) as $d)
                        @php
                            $valor = (float) ($d['balance'] ?? $d['saldo'] ?? 0);
                            $linhas++;
                        @endphp
                        <tr>
                            <td class="code">{{ $d['code'] ?? $d['codigo'] ?? '' }}</td>
                            <td>{{ $d['name'] ?? $d['nome'] ?? '' }}</td>
                            <td class="num {{ $valor < 0 ? 'neg' : '' }}">{{ $kz($valor) }}</td>
                        </tr>
                    @endforeach
                    @if(count($blocos) > 1)
                        <tr class="subtotal"><td></td><td>{{ __('Subtotal') }}</td><td class="num">{{ $kz($bloco['total'] ?? 0) }}</td></tr>
                    @endif
                @endforeach

                @if($linhas === 0)
                    <tr><td></td><td colspan="2" class="muted">{{ __('Sem saldos à data.') }}</td></tr>
                @endif

                <tr class="subtotal">
                    <td></td>
                    <td>{{ $massa['fecho'] }}</td>
                    <td class="num {{ $totalMassa < 0 ? 'neg' : '' }}">{{ $kz($totalMassa) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('Resumo') }}</h2>
    <table>
        <tbody>
            <tr>
                <td>{{ __('Total do activo') }}</td>
                <td class="num">{{ $kz($data['total_activo'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>{{ __('Total do passivo') }}</td>
                <td class="num">{{ $kz($data['total_passivo'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>{{ __('Total do capital próprio') }}</td>
                <td class="num">{{ $kz($data['total_capital_proprio'] ?? 0) }}</td>
            </tr>
            <tr>
                <td class="indent">{{ __('dos quais: resultado líquido do período') }}</td>
                <td class="num {{ (float) ($data['resultado_liquido'] ?? 0) < 0 ? 'neg' : '' }}">{{ $kz($data['resultado_liquido'] ?? 0) }}</td>
            </tr>
            <tr class="total">
                <td>{{ __('Total do passivo e capital próprio') }}</td>
                <td class="num">{{ $kz($data['total_passivo_capital'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>{{ __('Diferença (activo − passivo e capital próprio)') }}</td>
                <td class="num {{ abs((float) ($data['difference'] ?? 0)) >= 0.01 ? 'neg' : '' }}">{{ $kz($data['difference'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($data['balanced']))
        <div class="status ok">{{ __('O balanço fecha: o activo iguala o passivo mais o capital próprio.') }}</div>
    @else
        <div class="status ko">{{ __('O balanço não fecha: diferença de :valor Kz.', ['valor' => $kz($data['difference'] ?? 0)]) }}</div>
    @endif
</body>
</html>
