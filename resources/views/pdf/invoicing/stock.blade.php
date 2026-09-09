{{--
    O MAPA DE STOCK EM PAPEL.

    Sai exactamente o que estava no ecrã — os filtros viajam no URL e a consulta
    é a mesma da lista. O cabeçalho diz por extenso o que se pediu, porque um
    mapa impresso sem isso é um mapa que ninguém consegue conferir daqui a uma
    semana.

    HTML com botão de imprimir, e não um PDF gerado à força: o mapa é largo e
    sai melhor em paisagem pelo diálogo do browser. É o mesmo caminho do mapa
    de IRT e dos recibos do RH.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Gestão de Stock') }} — {{ $empresa->name ?? '' }}</title>
    <style>
        /* Paisagem: são dez colunas, e em retrato não cabem sem encolher a
           letra até ao ilegível. */
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111827; margin: 0; padding: 18px; background: #f3f4f6; }
        .folha { background: #fff; padding: 22px; max-width: 1180px; margin: 0 auto; }
        h1 { font-size: 17px; margin: 0 0 3px; }
        .sub { color: #6b7280; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { background: #4338ca; color: #fff; padding: 7px 6px; font-size: 10px; text-align: left; }
        th.n, td.n { text-align: right; }
        td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f9fafb; }
        tfoot td { background: #eef2ff !important; font-weight: bold; border-top: 2px solid #9ca3af; }
        .cab { border-bottom: 2px solid #4338ca; padding-bottom: 10px; }
        .cab td { border: 0; padding: 0; background: transparent !important; }
        .codigo { font-family: "Courier New", monospace; font-size: 10px; color: #4b5563; }
        /* O QUE ESTÁ EM FALTA LÊ-SE DE LONGE: é o que faz alguém pegar no mapa. */
        .baixo td { background: #fef2f2 !important; }
        .baixo .q { color: #b91c1c; font-weight: bold; }
        .vazio { text-align: center; padding: 40px; color: #6b7280; }
        .resumo { margin-top: 16px; width: 360px; }
        .resumo td { padding: 5px 8px; border: 1px solid #e5e7eb; }
        .destaque { background: #4338ca !important; color: #fff; font-size: 13px; }
        .assinatura { margin-top: 44px; }
        .assinatura td { border: 0; padding-top: 34px; text-align: center; font-size: 10px; }
        .linha { border-top: 1px solid #9ca3af; padding-top: 6px; width: 70%; margin: 0 auto; }
        .barra { text-align: center; margin-bottom: 14px; }
        .barra button { background: #4338ca; color: #fff; border: 0; padding: 9px 22px; border-radius: 7px; font-size: 13px; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .barra { display: none; } .folha { max-width: none; padding: 0; } }
    </style>
</head>
<body>

<div class="barra">
    <button type="button" onclick="window.print()">{{ __('Imprimir') }}</button>
</div>

<div class="folha">

    <table class="cab">
        <tr>
            <td>
                <h1>{{ __('Gestão de Stock') }}</h1>
                <div class="sub">{{ $empresa->name ?? '' }}@if($empresa?->nif) · {{ __('NIF') }} {{ $empresa->nif }}@endif</div>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <div class="sub">{{ $cabecalho['descricao'] }}</div>
                <div class="sub">{{ $cabecalho['quando']->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    @if(count($linhas) === 0)
        <p class="vazio">{{ __('Sem stock com estes filtros.') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('Código') }}</th>
                    <th>{{ __('Artigo') }}</th>
                    <th>{{ __('Armazém') }}</th>
                    <th class="n">{{ __('Quantidade') }}</th>
                    <th class="n">{{ __('Reservado') }}</th>
                    <th class="n">{{ __('Disponível') }}</th>
                    <th class="n">{{ __('Mínimo') }}</th>
                    <th class="n">{{ __('Custo unitário') }}</th>
                    <th class="n">{{ __('Valor') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($linhas as $l)
                    <tr @class(['baixo' => $l['baixo']])>
                        <td class="codigo">{{ $l['codigo'] ?: '—' }}</td>
                        <td>{{ $l['artigo'] }}</td>
                        <td>{{ $l['armazem'] ?: '—' }}</td>
                        <td class="n q">{{ number_format($l['quantidade'], 3, ',', '.') }} {{ $l['unidade'] }}</td>
                        <td class="n">{{ $l['reservado'] > 0 ? number_format($l['reservado'], 3, ',', '.') : '—' }}</td>
                        <td class="n">{{ number_format($l['disponivel'], 3, ',', '.') }}</td>
                        <td class="n">{{ $l['minimo'] > 0 ? number_format($l['minimo'], 3, ',', '.') : '—' }}</td>
                        <td class="n">{{ number_format($l['custo'], 2, ',', '.') }}</td>
                        <td class="n">{{ number_format($l['valor'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">{{ __(':quantos artigo(s)', ['quantos' => number_format($resumo['artigos'], 0, ',', '.')]) }}</td>
                    <td class="n">{{ number_format($resumo['quantidade'], 3, ',', '.') }}</td>
                    <td colspan="4"></td>
                    <td class="n">{{ number_format($resumo['valor'], 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>

        <table class="resumo">
            <tr>
                <td>{{ __('Artigos') }}</td>
                <td class="n">{{ number_format($resumo['artigos'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>{{ __('Abaixo do mínimo') }}</td>
                <td class="n">{{ number_format($resumo['baixo'], 0, ',', '.') }}</td>
            </tr>
            <tr class="destaque">
                <td>{{ __('Valor ao custo') }}</td>
                <td class="n">{{ number_format($resumo['valor'], 2, ',', '.') }} Kz</td>
            </tr>
        </table>

        <table class="assinatura">
            <tr>
                <td><div class="linha">{{ __('Conferido por') }}</div></td>
                <td><div class="linha">{{ __('Responsável de armazém') }}</div></td>
            </tr>
        </table>
    @endif

</div>

</body>
</html>
