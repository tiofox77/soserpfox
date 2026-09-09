{{--
    UM MAPA DA OFICINA EM PAPEL — os cinco pelo mesmo molde.

    As colunas vêm do mapa (`MapasDaOficina`), que é a mesma declaração que o
    ecrã usa: o papel sai exactamente com o que se estava a ver. Escrever aqui
    uma segunda lista de colunas era garantir que divergiam à primeira coluna
    nova.

    HTML com botão de imprimir, e não um PDF gerado à força: o mapa é largo e
    sai melhor em paisagem pelo diálogo do browser. É o caminho do mapa de
    stock, do mapa de IRT e dos recibos do RH.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }} — {{ $empresa->name ?? '' }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111827; margin: 0; padding: 18px; background: #f3f4f6; }
        .folha { background: #fff; padding: 22px; max-width: 1180px; margin: 0 auto; }
        h1 { font-size: 17px; margin: 0 0 3px; }
        .sub { color: #6b7280; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { background: #0e7490; color: #fff; padding: 7px 6px; font-size: 10px; text-align: left; }
        th.n, td.n { text-align: right; }
        td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f9fafb; }
        tfoot td { background: #ecfeff !important; font-weight: bold; border-top: 2px solid #9ca3af; }
        .cab { border-bottom: 2px solid #0e7490; padding-bottom: 10px; }
        .cab td { border: 0; padding: 0; background: transparent !important; }
        .vazio { text-align: center; padding: 40px; color: #6b7280; }
        .assinatura { margin-top: 44px; }
        .assinatura td { border: 0; padding-top: 34px; text-align: center; font-size: 10px; }
        .linha { border-top: 1px solid #9ca3af; padding-top: 6px; width: 70%; margin: 0 auto; }
        .barra { text-align: center; margin-bottom: 14px; }
        .barra button { background: #0e7490; color: #fff; border: 0; padding: 9px 22px; border-radius: 7px; font-size: 13px; cursor: pointer; }
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
                <h1>{{ $titulo }}</h1>
                <div class="sub">{{ $empresa->name ?? '' }}@if($empresa?->nif) · {{ __('NIF') }} {{ $empresa->nif }}@endif</div>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <div class="sub">{{ $descricao }}</div>
                <div class="sub">{{ $quando->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    @if(count($linhas) === 0)
        <p class="vazio">{{ $nada ?: __('Não há nada para mostrar com estes filtros.') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach($colunas as $c)
                        <th @class(['n' => in_array($c['formato'], ['numero', 'dinheiro'], true)])>{{ __($c['rotulo']) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($linhas as $l)
                    <tr>
                        @foreach($colunas as $c)
                            <td @class(['n' => in_array($c['formato'], ['numero', 'dinheiro'], true)])>
                                {{ $celula($l, $c) }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if($totais)
                <tfoot>
                    <tr>
                        @foreach($colunas as $c)
                            <td @class(['n' => in_array($c['formato'], ['numero', 'dinheiro'], true)])>
                                {{ $celula($totais, $c) }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>

        <table class="assinatura">
            <tr>
                <td><div class="linha">{{ __('Responsável da oficina') }}</div></td>
                <td><div class="linha">{{ __('Data') }}</div></td>
            </tr>
        </table>
    @endif

</div>

</body>
</html>
