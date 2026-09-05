{{--
    O PONTO DE MONTAGEM DE UM ECRÃ EM REACT.

    Uso:  <x-ecra-react nome="facturacao/lista-de-facturas" :props="['id' => 7]" />

    Fica DENTRO do layout de sempre: o menu, o cabeçalho e as permissões
    continuam a ser desenhados pelo Laravel. É isso que permite migrar um ecrã
    de cada vez em vez de trocar tudo num dia.

    O `data-props` é por onde o servidor passa o que já sabe — evita que o React
    vá buscar outra vez o que o Blade tinha à mão. Vai como JSON escapado; o
    Blade trata das aspas.
--}}
@props(['nome', 'props' => []])

<div
    data-ecra="{{ $nome }}"
    @if(!empty($props)) data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE) }}" @endif
    {{ $attributes->merge(['class' => 'ecra-react']) }}
>
    {{-- O que se vê antes de o JavaScript chegar. Sem isto a página pisca a
         branco no primeiro carregamento, que num telemóvel de balcão com rede
         fraca é meio segundo a olhar para o nada. --}}
    <div class="animate-pulse space-y-3">
        <div class="h-9 w-1/3 rounded-xl bg-slate-200"></div>
        <div class="h-12 rounded-xl bg-slate-100"></div>
        <div class="h-12 rounded-xl bg-slate-50"></div>
        <div class="h-12 rounded-xl bg-slate-100"></div>
    </div>
</div>
