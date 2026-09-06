{{--
    O MENU LATERAL, desenhado a partir do MenuDaCasca.

    Não há aqui uma ligação escrita à mão: o que aparece, com que permissão e
    em que ordem, vem de App\Support\MenuDaCasca — o mesmo sítio de onde o
    ecrã em React desenha o dele. O que este ficheiro sabe é a FORMA: as
    classes de sempre, o Alpine que abre e fecha, o `sidebarOpen` que esconde
    os rótulos quando a barra encolhe.

    Recebe `$menu` (MenuDaCasca::montar).
--}}

@php
    // Uma ligação, nos três tamanhos: de topo, de submenu, de sub-submenu.
    $ligacao = function (array $e, string $nivel) {
        $activa = $e['activo'] ? 'bg-blue-700 border-l-4 border-' . $e['barra'] : $e['hover'];
        $prefixo = isset($e['prefixo']) ? $e['prefixo'] . ' ' : '';

        [$caixa, $icone, $rotulo] = match ($nivel) {
            'topo' => ['px-4 py-3', 'w-6', 'ml-3'],
            'sub' => ['pl-8 pr-4 py-2.5', 'w-5 text-sm', 'ml-3 text-sm' . (! empty($e['forte']) ? ' font-semibold' : '')],
            'subsub' => ['pl-4 pr-4 py-2.5', 'w-5 text-sm', 'ml-3 text-xs'],
            'relatorio' => ['pl-6 pr-4 py-2', 'w-5 text-xs', 'ml-3 text-xs'],
        };

        $marca = ! empty($e['marca']) ? 'fab' : 'fas';

        return '<a href="' . e($e['url']) . '" class="flex items-center ' . $caixa . ' ' . $activa . ' transition">'
            . '<i class="' . $marca . ' ' . e($e['icone']) . ' ' . $icone . ' text-' . e($e['cor']) . '"></i>'
            . '<span x-show="sidebarOpen" class="' . $rotulo . '">' . e($prefixo) . e($e['rotulo']) . '</span>'
            . '</a>';
    };
@endphp

<div class="px-3 mb-2">
    <p x-show="sidebarOpen" class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-2">{{ __('Menu Principal') }}</p>
</div>

@foreach($menu['principal'] as $e)
    {!! $ligacao($e, 'topo') !!}
@endforeach

@foreach($menu['grupos'] as $g)
    @if($g['simples'])
        {{-- Uma ligação de topo com as suas dependentes, sem abrir e fechar. --}}
        <div class="mt-6">
            <a href="{{ $g['url'] }}" class="flex items-center px-4 py-3 {{ $g['activo'] ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700/50' }} transition">
                <i class="fas {{ $g['icone'] }} w-5 text-xl text-{{ $g['cor'] }}"></i>
                <span x-show="sidebarOpen" class="ml-3 font-semibold text-white">{{ $g['rotulo'] }}</span>
            </a>
            @foreach($g['entradas'] as $e)
                {!! $ligacao($e, 'sub') !!}
            @endforeach
        </div>
        @continue
    @endif

    <div class="mt-6" x-data="{ aberto: {{ $g['aberto'] ? 'true' : 'false' }} }">
        <button @click="aberto = !aberto" class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-700/50 transition group">
            <div class="flex items-center">
                <i class="fas {{ $g['icone'] }} w-6 text-{{ $g['cor'] }}"></i>
                <span x-show="sidebarOpen" class="ml-3 {{ empty($g['leve']) ? 'font-semibold text-white' : '' }}">{{ $g['rotulo'] }}</span>
            </div>
            <i x-show="sidebarOpen" :class="aberto ? 'fa-chevron-down' : 'fa-chevron-right'" class="fas text-blue-300 text-xs transition-transform duration-200"></i>
        </button>

        <div x-show="aberto" x-collapse class="bg-blue-900/30">
            @foreach($g['entradas'] as $e)
                @if(isset($e['separador']))
                    <div class="my-2 border-t border-blue-700/50"></div>
                @elseif(isset($e['sub']))
                    @php $s = $e['sub']; @endphp
                    <div x-data="{ aberto: {{ $s['aberto'] ? 'true' : 'false' }} }" class="border-l-2 border-blue-700/30 ml-8">
                        <button @click="aberto = !aberto" class="w-full flex items-center justify-between pr-4 py-2.5 hover:bg-blue-700/30 transition group">
                            <div class="flex items-center">
                                <i class="fas {{ $s['icone'] }} w-5 text-{{ $s['cor'] }} text-sm"></i>
                                <span x-show="sidebarOpen" class="ml-3 text-sm font-semibold">{{ isset($s['prefixo']) ? $s['prefixo'] . ' ' : '' }}{{ $s['rotulo'] }}</span>
                            </div>
                            <i x-show="sidebarOpen" :class="aberto ? 'fa-chevron-down' : 'fa-chevron-right'" class="fas text-blue-300 text-xs transition-transform duration-200"></i>
                        </button>

                        <div x-show="aberto" x-collapse class="bg-blue-900/20">
                            @foreach($s['entradas'] as $se)
                                @if(isset($se['titulo']))
                                    <div x-show="sidebarOpen" class="px-4 pt-3 pb-1 text-[10px] uppercase tracking-wider text-blue-300/70 font-bold">{{ $se['titulo'] }}</div>
                                @elseif(isset($se['separador']))
                                    <div class="my-2 border-t border-blue-700/50"></div>
                                @else
                                    {!! $ligacao($se, $se['relatorio'] ? 'relatorio' : 'subsub') !!}
                                @endif
                            @endforeach
                        </div>
                    </div>
                @else
                    {!! $ligacao($e, 'sub') !!}
                @endif
            @endforeach
        </div>
    </div>
@endforeach

@foreach($menu['superadmin'] as $seccao)
    <div class="px-3 mt-6 mb-2">
        <p x-show="sidebarOpen" class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-2">{{ $seccao['titulo'] }}</p>
    </div>
    @foreach($seccao['entradas'] as $e)
        {!! $ligacao($e, 'topo') !!}
    @endforeach
@endforeach
