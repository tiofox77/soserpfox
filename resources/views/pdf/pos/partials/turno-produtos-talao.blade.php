{{-- O FECHO COM PRODUTOS no talão de 80 mm (ver App\Services\POS\ProdutosDoTurno).
     Um artigo ocupa duas linhas: o nome inteiro em cima, e «quantidade ×
     preço médio» com o total em baixo — o nome cortado não se lê no papel. --}}
@php
    $tp = $produtos['totais'];
    $qtd = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
@endphp
<hr class="solid">
<h4>VENDAS POR PRODUTO ({{ $tp['artigos'] }})</h4>
@if(empty($produtos['produtos']))
    <div class="center">Sem artigos vendidos neste turno.</div>
@else
    <table>
        @foreach($produtos['produtos'] as $p)
            <tr><td colspan="2" class="b" style="padding-top:3px">{{ $p['nome'] }}@if($p['codigo']) <span style="font-weight:400">({{ $p['codigo'] }})</span>@endif</td></tr>
            <tr>
                <td>{{ $qtd($p['quantidade']) }} × {{ number_format($p['preco_medio'], 2) }}</td>
                <td class="right">{{ number_format($p['total'], 2) }}</td>
            </tr>
            @if($p['devolvida'] > 0)
            <tr>
                <td>Devolvido: -{{ $qtd($p['devolvida']) }}</td>
                <td class="right">-{{ number_format($p['devolvido'], 2) }}</td>
            </tr>
            @endif
        @endforeach
    </table>
@endif

<hr class="dashed">
<table>
    <tr><td>Artigos diferentes:</td><td class="right">{{ $tp['artigos'] }}</td></tr>
    <tr><td>Quantidade vendida:</td><td class="right">{{ $qtd($tp['quantidade']) }}</td></tr>
    <tr><td>Total dos artigos:</td><td class="right">{{ number_format($tp['bruto'], 2) }} Kz</td></tr>
    @if($tp['descontos'] > 0)
    <tr><td>Descontos nos documentos:</td><td class="right">-{{ number_format($tp['descontos'], 2) }} Kz</td></tr>
    @endif
    @if($tp['devolvido'] > 0)
    <tr><td>Devoluções:</td><td class="right">-{{ number_format($tp['devolvido'], 2) }} Kz</td></tr>
    @endif
    <tr><td class="b">TOTAL LÍQUIDO:</td><td class="right b">{{ number_format($tp['liquido'], 2) }} Kz</td></tr>
    <tr><td>IVA incluído:</td><td class="right">{{ number_format($tp['imposto'], 2) }} Kz</td></tr>
    <tr><td>Ticket médio:</td><td class="right">{{ number_format($tp['ticket_medio'], 2) }} Kz</td></tr>
</table>

@if(!empty($produtos['documentos']))
<hr class="solid">
<h4>DOCUMENTOS ({{ count($produtos['documentos']) }})</h4>
<table>
    @foreach($produtos['documentos'] as $d)
        <tr>
            <td style="white-space:nowrap">{{ $d['hora'] }}</td>
            <td>{{ $d['numero'] }}@if($d['anulada']) <span class="b">(ANULADA)</span>@endif</td>
            <td class="right" style="white-space:nowrap">{{ number_format($d['total'], 2) }}</td>
        </tr>
        <tr><td></td><td colspan="2" style="font-size:9px">@if(!empty($d['numero_agt'])){{ $d['numero_agt'] }} · @endif{{ $d['meio'] }}@if($d['cliente']) · {{ $d['cliente'] }}@endif · {{ $d['artigos'] }} art.</td></tr>
    @endforeach
</table>
@endif
