<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-violet-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-diagram-project text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">{{ __('Projetos') }}</h2>
                    <p class="text-violet-100 text-sm">{{ __('O que está a fugir ao orçamento e ao prazo') }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('projetos.lista') }}"
                   class="bg-white/20 hover:bg-white/30 backdrop-blur-sm px-5 py-2.5 rounded-xl font-semibold transition">
                    <i class="fas fa-folder-open mr-2"></i>{{ __('Projetos') }}
                </a>
                <a href="{{ route('projetos.timesheet') }}"
                   class="bg-white/20 hover:bg-white/30 backdrop-blur-sm px-5 py-2.5 rounded-xl font-semibold transition">
                    <i class="fas fa-clock mr-2"></i>{{ __('Lançar horas') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Cartões -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <a href="{{ route('projetos.lista') }}" class="bg-white rounded-2xl shadow-lg p-5 hover:shadow-xl transition">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Activos') }}</p>
            <p class="text-3xl font-bold text-green-600">{{ $resumo['activos'] }}</p>
        </a>
        <a href="{{ route('projetos.timesheet') }}" class="bg-white rounded-2xl shadow-lg p-5 hover:shadow-xl transition">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Horas do mês') }}</p>
            <p class="text-3xl font-bold text-violet-600">{{ number_format($resumo['horas_mes'], 1, ',', '.') }}</p>
        </a>
        <a href="{{ route('projetos.tarefas') }}" class="bg-white rounded-2xl shadow-lg p-5 hover:shadow-xl transition">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Tarefas abertas') }}</p>
            <p class="text-3xl font-bold text-blue-600">{{ $resumo['tarefas_abertas'] }}</p>
        </a>
        <a href="{{ route('projetos.tarefas') }}" class="bg-white rounded-2xl shadow-lg p-5 hover:shadow-xl transition">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Atrasadas') }}</p>
            <p class="text-3xl font-bold {{ $resumo['tarefas_atrasadas'] > 0 ? 'text-red-600' : 'text-gray-300' }}">
                {{ $resumo['tarefas_atrasadas'] }}
            </p>
        </a>
        <a href="{{ route('projetos.lista') }}" class="bg-white rounded-2xl shadow-lg p-5 hover:shadow-xl transition">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Por facturar') }}</p>
            <p class="text-2xl font-bold {{ $resumo['valor_por_facturar'] > 0 ? 'text-amber-600' : 'text-gray-300' }}">
                {{ valorProtegido($resumo['valor_por_facturar'], 'projetos.facturar', 'projetos.gerir') }}
            </p>
            <p class="text-xs text-gray-400 mt-1">
                {{ number_format($resumo['horas_por_facturar'], 1, ',', '.') }}h {{ __('trabalhadas') }}
            </p>
        </a>
    </div>

    @if($estouros->isNotEmpty())
        <div class="mb-6 bg-red-50 border border-red-100 rounded-2xl p-5">
            <h3 class="font-bold text-red-900 mb-3">
                <i class="fas fa-triangle-exclamation mr-2"></i>{{ __('Acima do orçamento') }}
            </h3>
            <div class="space-y-2">
                @foreach($estouros as $e)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-red-900 font-medium">{{ $e['projeto']->nome }}</span>
                        <span class="font-bold text-red-700">
                            {{ number_format($e['percentagem'], 1, ',', '.') }}%
                            <span class="font-normal text-red-500">
                                ({{ valorProtegido($e['gasto'], 'projetos.facturar', 'projetos.gerir') }}
                                {{ __('de') }} {{ valorProtegido((float) $e['projeto']->orcamento, 'projetos.facturar', 'projetos.gerir') }})
                            </span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Projetos em curso -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-violet-50 to-purple-50 border-b border-gray-100">
                <h3 class="font-bold text-gray-900">
                    <i class="fas fa-folder-open text-violet-600 mr-2"></i>{{ __('Em curso') }}
                </h3>
            </div>
            @forelse($activos as $a)
                <div class="px-6 py-3 border-b border-gray-50 last:border-0">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900 truncate">{{ $a['projeto']->nome }}</p>
                            <p class="text-xs text-gray-400">
                                {{ $a['projeto']->cliente?->name ?? __('interno') }}
                                · {{ number_format($a['horas'], 1, ',', '.') }}h
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            @if($a['percentagem'] !== null)
                                <p class="text-sm font-bold {{ $a['percentagem'] > 100 ? 'text-red-600' : 'text-gray-900' }}">
                                    {{ number_format($a['percentagem'], 0, ',', '.') }}%
                                </p>
                            @else
                                <p class="text-xs text-gray-300">{{ __('sem orçamento') }}</p>
                            @endif
                        </div>
                    </div>
                    @if($a['percentagem'] !== null)
                        <div class="mt-2 w-full bg-gray-100 rounded-full h-1.5">
                            <div class="h-1.5 rounded-full {{ $a['percentagem'] > 100 ? 'bg-red-500' : 'bg-violet-500' }}"
                                 style="width: {{ min(100, $a['percentagem']) }}%"></div>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-400 py-10 text-center">{{ __('Nenhum projeto activo.') }}</p>
            @endforelse
        </div>

        <!-- Tarefas atrasadas -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-red-50 to-rose-50 border-b border-gray-100">
                <h3 class="font-bold text-gray-900">
                    <i class="fas fa-clock text-red-600 mr-2"></i>{{ __('Tarefas com o prazo passado') }}
                </h3>
            </div>
            @forelse($atrasadas as $t)
                <a href="{{ route('projetos.tarefas') }}"
                   class="flex items-center justify-between px-6 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-gray-900 truncate">{{ $t->titulo }}</p>
                        <p class="text-xs text-gray-400">
                            <span class="font-mono">{{ $t->projeto?->codigo }}</span>
                            @if($t->responsavel) · {{ $t->responsavel->name }} @endif
                        </p>
                    </div>
                    <span class="text-xs font-semibold text-red-600 shrink-0 ml-3">
                        {{ $t->prazo->format('d/m/Y') }}
                    </span>
                </a>
            @empty
                <p class="text-sm text-gray-400 py-10 text-center">
                    <i class="fas fa-check-circle text-2xl text-green-200 block mb-2"></i>
                    {{ __('Nenhuma tarefa em atraso.') }}
                </p>
            @endforelse
        </div>
    </div>
</div>
