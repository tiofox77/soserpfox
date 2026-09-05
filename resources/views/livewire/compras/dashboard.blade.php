<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-lime-600 to-green-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-shopping-cart text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">{{ __('Compras') }}</h2>
                    <p class="text-lime-100 text-sm">{{ __('Do pedido interno à mercadoria no armazém') }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('compras.requisicoes') }}"
                   class="bg-white/20 hover:bg-white/30 backdrop-blur-sm px-5 py-2.5 rounded-xl font-semibold transition">
                    <i class="fas fa-file-alt mr-2"></i>{{ __('Requisições') }}
                </a>
                <a href="{{ route('compras.encomendas') }}"
                   class="bg-white/20 hover:bg-white/30 backdrop-blur-sm px-5 py-2.5 rounded-xl font-semibold transition">
                    <i class="fas fa-clipboard-list mr-2"></i>{{ __('Encomendas') }}
                </a>
            </div>
        </div>
    </div>

    <!-- O que está à minha espera -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <a href="{{ route('compras.requisicoes') }}" class="bg-white rounded-2xl shadow-lg p-5 hover:shadow-xl transition">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Por decidir') }}</p>
            <p class="text-3xl font-bold {{ $resumo['por_decidir'] > 0 ? 'text-amber-600' : 'text-gray-300' }}">{{ $resumo['por_decidir'] }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ __('requisições submetidas') }}</p>
        </a>
        <a href="{{ route('compras.encomendas') }}" class="bg-white rounded-2xl shadow-lg p-5 hover:shadow-xl transition">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Por encomendar') }}</p>
            <p class="text-3xl font-bold {{ $resumo['por_encomendar'] > 0 ? 'text-lime-600' : 'text-gray-300' }}">{{ $resumo['por_encomendar'] }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ __('aprovadas à espera') }}</p>
        </a>
        <a href="{{ route('compras.encomendas') }}" class="bg-white rounded-2xl shadow-lg p-5 hover:shadow-xl transition">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Em curso') }}</p>
            <p class="text-3xl font-bold text-blue-600">{{ $resumo['em_curso'] }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ __('encomendas por receber') }}</p>
        </a>
        <a href="{{ route('compras.encomendas') }}" class="bg-white rounded-2xl shadow-lg p-5 hover:shadow-xl transition">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Atrasadas') }}</p>
            <p class="text-3xl font-bold {{ $resumo['atrasadas'] > 0 ? 'text-red-600' : 'text-gray-300' }}">{{ $resumo['atrasadas'] }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ __('passou a data prometida') }}</p>
        </a>
        <a href="{{ route('compras.encomendas') }}" class="bg-white rounded-2xl shadow-lg p-5 hover:shadow-xl transition">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Por facturar') }}</p>
            <p class="text-3xl font-bold {{ $resumo['por_facturar'] > 0 ? 'text-purple-600' : 'text-gray-300' }}">{{ $resumo['por_facturar'] }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ __('recebidas sem factura') }}</p>
        </a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6 mb-6">
        <!-- Gasto encomendado -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg p-6">
            <div class="flex items-baseline justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">{{ __('Encomendado por mês') }}</h3>
                <p class="text-sm text-gray-500">
                    {{ __('Em curso agora') }}:
                    <span class="font-bold text-gray-900">{{ valorProtegido($resumo['valor_em_curso'], 'compras.encomendas.view', 'compras.view') }}</span>
                </p>
            </div>
            @php $maximo = max($grafico['valores'] ?: [0]) ?: 1; @endphp
            <div class="flex items-end justify-between gap-3 h-48">
                @foreach($grafico['valores'] as $i => $valor)
                    <div class="flex-1 flex flex-col items-center justify-end h-full">
                        <span class="text-xs text-gray-400 mb-1">
                            {{ $valor > 0 ? valorProtegido(number_format($valor / 1000, 0, ',', '.').'k', 'compras.encomendas.view', 'compras.view') : '' }}
                        </span>
                        <div class="w-full bg-gradient-to-t from-lime-600 to-green-500 rounded-t-lg transition-all"
                             style="height: {{ max(2, round($valor / $maximo * 100)) }}%"></div>
                        <span class="text-xs text-gray-500 mt-2">{{ $grafico['etiquetas'][$i] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Top fornecedores -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">{{ __('A quem compramos') }} <span class="text-sm font-normal text-gray-400">{{ now()->year }}</span></h3>
            @forelse($topFornecedores as $f)
                <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $f->name }}</p>
                        <p class="text-xs text-gray-400">{{ $f->quantas }} {{ __('encomenda(s)') }}</p>
                    </div>
                    <p class="text-sm font-bold text-gray-900 ml-3">{{ valorProtegido((float) $f->valor, 'compras.encomendas.view', 'compras.view') }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-8 text-center">{{ __('Ainda sem encomendas este ano.') }}</p>
            @endforelse
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <!-- A decidir -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-amber-50 to-yellow-50 border-b border-gray-100">
                <h3 class="font-bold text-gray-900">
                    <i class="fas fa-hourglass-half text-amber-600 mr-2"></i>{{ __('Requisições à espera de decisão') }}
                </h3>
            </div>
            @forelse($aDecidir as $req)
                <a href="{{ route('compras.requisicoes') }}"
                   class="flex items-center justify-between px-6 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $req->numero }}</p>
                        <p class="text-xs text-gray-400">
                            {{ $req->autor?->name }} · {{ $req->itens->count() }} {{ __('artigo(s)') }}
                        </p>
                    </div>
                    <span class="text-xs text-gray-400">{{ $req->created_at->diffForHumans() }}</span>
                </a>
            @empty
                <p class="text-sm text-gray-400 py-10 text-center">
                    <i class="fas fa-check-circle text-2xl text-green-200 block mb-2"></i>
                    {{ __('Nada à espera de decisão.') }}
                </p>
            @endforelse
        </div>

        <!-- Atrasadas -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-red-50 to-rose-50 border-b border-gray-100">
                <h3 class="font-bold text-gray-900">
                    <i class="fas fa-clock text-red-600 mr-2"></i>{{ __('Encomendas atrasadas') }}
                </h3>
            </div>
            @forelse($atrasadas as $enc)
                <a href="{{ route('compras.encomendas') }}"
                   class="flex items-center justify-between px-6 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $enc->numero }}</p>
                        <p class="text-xs text-gray-400">{{ $enc->fornecedor?->name }}</p>
                    </div>
                    <span class="text-xs font-semibold text-red-600">
                        {{ __('prometida') }} {{ $enc->entrega_prevista->format('d/m/Y') }}
                    </span>
                </a>
            @empty
                <p class="text-sm text-gray-400 py-10 text-center">
                    <i class="fas fa-check-circle text-2xl text-green-200 block mb-2"></i>
                    {{ __('Nenhuma encomenda em atraso.') }}
                </p>
            @endforelse
        </div>
    </div>
</div>
