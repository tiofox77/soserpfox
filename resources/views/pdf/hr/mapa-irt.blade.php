@php
    $t = $mapa['totais'];
    $nomeDoMes = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
                  7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'][$mes] ?? $mes;
@endphp
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Mapa de IRT {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}/{{ $ano }} — {{ $empresa->name ?? '' }}</title>
    <style>
        /* Paisagem: o mapa tem oito colunas de números e em retrato não cabe
           sem encolher a letra até ao ilegível. */
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111827; margin: 0; padding: 18px; background: #f3f4f6; }
        .folha { background: #fff; padding: 22px; max-width: 1180px; margin: 0 auto; }
        h1 { font-size: 17px; margin: 0 0 3px; }
        .sub { color: #6b7280; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { background: #be123c; color: #fff; padding: 7px 6px; font-size: 10px; text-align: left; }
        th.n, td.n { text-align: right; }
        td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f9fafb; }
        tfoot td { background: #f3f4f6 !important; font-weight: bold; border-top: 2px solid #9ca3af; }
        .cab { border-bottom: 2px solid #be123c; padding-bottom: 10px; }
        .cab td { border: 0; padding: 0; background: transparent !important; }
        .resumo { margin-top: 16px; width: 320px; }
        .resumo td { padding: 5px 8px; border: 1px solid #e5e7eb; }
        .destaque { background: #be123c !important; color: #fff; font-size: 13px; }
        .assinatura { margin-top: 44px; }
        .assinatura td { border: 0; padding-top: 34px; text-align: center; font-size: 10px; }
        .linha { border-top: 1px solid #9ca3af; padding-top: 6px; width: 70%; margin: 0 auto; }
        .aviso { margin-top: 12px; padding: 8px 10px; background: #fef2f2; border-left: 3px solid #dc2626; color: #991b1b; font-size: 10px; }
        .barra { text-align: center; margin-bottom: 14px; }
        .barra button { background: #be123c; color: #fff; border: 0; padding: 9px 22px; border-radius: 7px; font-size: 13px; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .barra { display: none; } .folha { max-width: none; padding: 0; } }
    </style>
</head>
<body>

<div class="barra">
    <button onclick="window.print()">Imprimir / Guardar em PDF</button>
</div>

<div class="folha">
    <table class="cab">
        <tr>
            <td style="width:60%">
                <h1>{{ $empresa->name ?? '' }}</h1>
                <div class="sub">
                    NIF: {{ $empresa->nif ?? '—' }}
                    @if($empresa->address) · {{ $empresa->address }} @endif
                </div>
            </td>
            <td style="width:40%; text-align:right">
                <h1>MAPA DE IRT</h1>
                <div class="sub">
                    {{ $nomeDoMes }} de {{ $ano }}<br>
                    Imposto sobre o Rendimento do Trabalho — Grupo A<br>
                    Emitido em {{ now()->format('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width:52px">Nº</th>
                <th>Trabalhador</th>
                <th style="width:92px">NIF</th>
                <th style="width:104px">Seg. Social</th>
                <th class="n" style="width:104px">Remuneração</th>
                <th class="n" style="width:92px">INSS 3%</th>
                <th class="n" style="width:112px">Matéria colectável</th>
                <th class="n" style="width:62px">Taxa</th>
                <th class="n" style="width:104px">IRT retido</th>
            </tr>
        </thead>
        <tbody>
            @forelse($mapa['linhas'] as $l)
                <tr>
                    <td>{{ $l['numero'] ?: '—' }}</td>
                    <td>{{ $l['nome'] }}</td>
                    <td>{{ $l['nif'] ?: '—' }}</td>
                    <td>{{ $l['seguranca'] ?: '—' }}</td>
                    <td class="n">{{ number_format($l['bruto'], 2, ',', '.') }}</td>
                    <td class="n">{{ number_format($l['inss'], 2, ',', '.') }}</td>
                    <td class="n">{{ number_format($l['base'], 2, ',', '.') }}</td>
                    <td class="n">{{ $l['isento'] ? 'Isento' : number_format($l['taxa'], 2, ',', '.') . '%' }}</td>
                    <td class="n">{{ number_format($l['irt'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center; padding:26px; color:#6b7280">
                    Sem retenções aprovadas ou pagas neste período.
                </td></tr>
            @endforelse
        </tbody>
        @if($mapa['linhas']->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="4">TOTAL — {{ $t['trabalhadores'] }} trabalhador(es), {{ $t['tributados'] }} tributado(s)</td>
                    <td class="n">{{ number_format($t['bruto'], 2, ',', '.') }}</td>
                    <td class="n">{{ number_format($t['inss'], 2, ',', '.') }}</td>
                    <td class="n">{{ number_format($t['base'], 2, ',', '.') }}</td>
                    <td></td>
                    <td class="n">{{ number_format($t['irt'], 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <table class="resumo">
        <tr>
            <td>Trabalhadores tributados</td>
            <td class="n">{{ $t['tributados'] }}</td>
        </tr>
        <tr>
            <td>Trabalhadores isentos</td>
            <td class="n">{{ $t['isentos'] }}</td>
        </tr>
        <tr>
            <td class="destaque">IRT A ENTREGAR</td>
            <td class="destaque n">{{ number_format($t['irt'], 2, ',', '.') }} Kz</td>
        </tr>
    </table>

    @php $semNif = $mapa['linhas']->where('nif', '')->count(); @endphp
    @if($semNif > 0)
        <div class="aviso">
            <strong>{{ $semNif }} trabalhador(es) sem NIF.</strong>
            A declaração é feita por NIF — preencha-o na ficha antes de entregar.
        </div>
    @endif

    @if($mapa['folhas']->isNotEmpty())
        <div class="sub" style="margin-top:10px">
            Folhas incluídas: {{ $mapa['folhas']->pluck('payroll_number')->implode(', ') }}.
            Só entram folhas aprovadas ou pagas.
        </div>
    @endif

    <table class="assinatura">
        <tr>
            <td style="width:45%"><div class="linha">O Responsável</div></td>
            <td style="width:10%"></td>
            <td style="width:45%"><div class="linha">Data</div></td>
        </tr>
    </table>
</div>

</body>
</html>
