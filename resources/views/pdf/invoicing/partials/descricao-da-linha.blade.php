{{-- A descrição da linha: formatada pelo editor das propostas, ou o texto de
     sempre. O HTML chega aqui já limpo; o paraImprimir volta a limpá-lo. --}}
@if(\App\Services\Invoicing\DescricaoRica::eRica($descricao))
    <div class="descricao-rica" style="font-size: {{ $tamanho ?? '8px' }}">{!! \App\Services\Invoicing\DescricaoRica::paraImprimir($descricao) !!}</div>
@else
    <span class="item-descricao">{{ $descricao }}</span>
@endif
