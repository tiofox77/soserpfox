<div>
    <!-- Header -->
    <div class="mb-4 sm:mb-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-4 sm:p-6 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3 sm:mr-4">
                    <i class="fas fa-box text-xl sm:text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-lg sm:text-2xl font-bold">{{ __('Produtos/Serviços') }}</h2>
                    <p class="text-purple-100 text-xs sm:text-sm">{{ __('Gerir catálogo de produtos') }}</p>
                </div>
            </div>
            @can('invoicing.products.create')
            <button wire:click="create"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-70 scale-95"
                    class="group bg-white text-purple-600 hover:bg-purple-50 px-4 sm:px-6 py-2 sm:py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105 text-sm sm:text-base disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="create">
                    <i class="fas fa-plus mr-2 group-hover:rotate-90 transition-transform duration-300"></i>{{ __('Novo Produto') }}
                </span>
                <span wire:loading wire:target="create">
                    <i class="fas fa-spinner fa-spin mr-2"></i>{{ __('Abrindo...') }}
                </span>
            </button>
            @endcan
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-6 mb-4 sm:mb-6 stagger-animation">
        <!-- Total Produtos -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float">
                    <i class="fas fa-box text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">{{ __('Total Produtos') }}</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $estatisticas['produtos'] }}</p>
            <p class="text-xs text-gray-500">{{ __('No catálogo') }}</p>
        </div>

        <!-- Valor Médio -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">{{ __('Valor Médio') }}</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ number_format($estatisticas['preco_medio'], 2) }} Kz</p>
            <p class="text-xs text-gray-500">{{ __('Preço médio') }}</p>
        </div>

        <!-- Serviços -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-cogs text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">{{ __('Serviços') }}</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $estatisticas['servicos'] }}</p>
            <p class="text-xs text-gray-500">{{ __('Tipo serviço') }}</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-purple-600"></i>
                {{ __('Filtros Avançados') }}
            </h3>
            <div class="flex items-center gap-3">
                {{-- Lixeira: a eliminação é recuperável, mas até aqui não havia
                     forma de ver nem restaurar um produto eliminado. --}}
                @can('invoicing.products.delete')
                <button wire:click="alternarEliminados"
                        wire:target="alternarEliminados"
                        wire:loading.attr="disabled"
                        class="text-sm font-semibold flex items-center px-3 py-1.5 rounded-lg transition
                               {{ $mostrarEliminados ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-100' }}">
                    <i class="fas fa-trash-can-arrow-up mr-1.5"></i>
                    {{ $mostrarEliminados ? 'A ver eliminados' : 'Ver eliminados' }}
                </button>
                @endcan

                <button wire:click="clearFilters" class="text-sm text-purple-600 hover:text-purple-700 font-semibold flex items-center">
                    <i class="fas fa-redo mr-1"></i>{{ __('Limpar Filtros') }}
                </button>
            </div>
        </div>

        @if($mostrarEliminados)
        <div class="mb-4 bg-amber-50 border-2 border-amber-200 rounded-xl p-3">
            <p class="text-sm text-amber-800">
                <i class="fas fa-circle-info mr-1"></i>
                {{-- A frase inteira numa chave só. Partida em tres pedacos
                     ("A mostrar produtos" + "eliminados" + ". Continuam...")
                     nao havia forma de a traduzir: a ordem das palavras muda
                     de lingua para lingua e o tradutor so via os bocados. --}}
                {!! __('A mostrar produtos <strong>eliminados</strong>. Continuam guardados na base de dados e podem ser restaurados com o histórico e as imagens intactos.') !!}
            </p>
        </div>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 sm:gap-4">
            <!-- Search -->
            <div class="col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>{{ __('Pesquisar') }}
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Nome, código, descrição...') }}" 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            <!-- Type Filter -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-tag mr-1"></i>{{ __('Tipo') }}
                </label>
                <select wire:model.live="typeFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="produto">{{ __('Produto') }}</option>
                    <option value="servico">{{ __('Serviço') }}</option>
                </select>
            </div>

            <!-- Stock Filter -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-boxes mr-1"></i>{{ __('Stock') }}
                </label>
                <select wire:model.live="stockFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="gerenciado">{{ __('Gere stock') }}</option>
                    <option value="nao_gerenciado">{{ __('Não gere stock') }}</option>
                    <option value="com_stock">{{ __('Com Stock') }}</option>
                    <option value="sem_stock">{{ __('Sem Stock') }}</option>
                    <option value="stock_baixo">{{ __('Stock abaixo do mínimo') }}</option>
                </select>
            </div>

            <!-- Categoria -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-folder mr-1"></i>{{ __('Categoria') }}
                </label>
                <select wire:model.live="categoryFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="">{{ __('Todas') }}</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Estado -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-toggle-on mr-1"></i>{{ __('Estado') }}
                </label>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="activo">{{ __('Activos') }}</option>
                    <option value="inactivo">{{ __('Inactivos') }}</option>
                </select>
            </div>

            <!-- Por preencher (qualidade do catálogo) -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-triangle-exclamation mr-1"></i>{{ __('Por preencher') }}
                </label>
                <select wire:model.live="qualidadeFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="sem_preco">{{ __('Sem preço') }}</option>
                    <option value="sem_codigo_barras">{{ __('Sem código de barras') }}</option>
                    <option value="sem_categoria">{{ __('Sem categoria') }}</option>
                </select>
            </div>

            <!-- Per Page -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-list mr-1"></i>{{ __('Por Página') }}
                </label>
                <select wire:model.live="perPage" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        {{-- Catálogo especializado. Aparecem a quem diz trabalhar com isto nas
             Definições de Faturação OU a quem já tem artigos assim marcados —
             numa oficina ou num restaurante seriam selects permanentemente
             vazios. A segunda metade da condição não é decorativa: sem ela,
             desligar o perfil deixava dados gravados sem forma de os filtrar. --}}
        @php
            $perfilFarmacia  = $perfis['farmacia'] ?? false;
            $perfilVestuario = $perfis['vestuario'] ?? false;
            $perfilMercearia = $perfis['mercearia'] ?? false;

            $temTamanhos = !empty($variantes['tamanhos']);
            $temCores = !empty($variantes['cores']);

            // Os valores gravados são chaves internas — o que se mostra (aqui e
            // no crachá da lista) é o rótulo, traduzível.
            $rotulosConservacao = [
                'ambiente'    => __('Ambiente'),
                'refrigerado' => __('Refrigerado'),
                'congelado'   => __('Congelado'),
            ];

            $mostrarFiltroReceita = $perfilFarmacia || ($variantes['ha_receituario'] ?? false);
            $mostrarFiltroTamanho = $perfilVestuario || $temTamanhos;
            $mostrarFiltroCor     = $perfilVestuario || $temCores;
            // Não depende de haver artigos de cada tipo: a lista é fechada, por
            // isso o select nunca fica vazio a parecer avariado.
            $mostrarFiltroConservacao = $perfilMercearia || ($variantes['ha_conservacao'] ?? false);

            $mostrarFiltrosCatalogo = $mostrarFiltroReceita || $mostrarFiltroTamanho
                || $mostrarFiltroCor || $mostrarFiltroConservacao;
        @endphp
        @if($mostrarFiltrosCatalogo)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mt-4">
            @if($mostrarFiltroReceita)
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-file-prescription mr-1"></i>{{ __('Receita') }}
                </label>
                <select wire:model.live="filterPrescricao" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="sim">{{ __('Exige receita') }}</option>
                    <option value="nao">{{ __('Venda livre') }}</option>
                </select>
            </div>
            @endif

            @if($mostrarFiltroTamanho)
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-ruler mr-1"></i>{{ __('Tamanho') }}
                </label>
                {{-- Select e não caixa de texto: ninguém se lembra de como
                     escreveu o tamanho da última vez. Com o perfil acabado de
                     ligar ainda não há nada para escolher — em vez de um select
                     vazio que parece avariado, diz-se porquê. --}}
                <select wire:model.live="filterTamanho" @disabled(!$temTamanhos) class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm disabled:bg-gray-100 disabled:text-gray-400">
                    <option value="">{{ $temTamanhos ? __('Todos') : __('Ainda sem tamanhos registados') }}</option>
                    @foreach($variantes['tamanhos'] as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if($mostrarFiltroCor)
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-palette mr-1"></i>{{ __('Cor') }}
                </label>
                <select wire:model.live="filterCor" @disabled(!$temCores) class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm disabled:bg-gray-100 disabled:text-gray-400">
                    <option value="">{{ $temCores ? __('Todas') : __('Ainda sem cores registadas') }}</option>
                    @foreach($variantes['cores'] as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if($mostrarFiltroConservacao)
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-temperature-half mr-1"></i>{{ __('Conservação') }}
                </label>
                {{-- A pergunta do armazém, não a da ficha: "o que é que vai para
                     o frigorífico?" é o que se pergunta antes de arrumar uma
                     entrada de mercadoria. --}}
                <select wire:model.live="filterConservacao" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="">{{ __('Todas') }}</option>
                    @foreach($rotulosConservacao as $chave => $rotulo)
                        <option value="{{ $chave }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>
        @endif

        <!-- Date Range -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar-alt mr-1"></i>{{ __('Data de Cadastro (De)') }}
                </label>
                <input wire:model.live="dateFrom" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar-alt mr-1"></i>{{ __('Data de Cadastro (Até)') }}
                </label>
                <input wire:model.live="dateTo" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all text-sm">
            </div>
        </div>

        <!-- Active Filters Display -->
        @if($search || $typeFilter || $stockFilter || $categoryFilter || $statusFilter || $qualidadeFilter || $dateFrom || $dateTo || $filterPrescricao || $filterTamanho || $filterCor || $filterConservacao)
            <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="flex flex-wrap gap-2">
                    <span class="text-xs font-semibold text-gray-600">{{ __('Filtros ativos:') }}</span>
                    @if($search)
                        <span class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-search mr-1"></i>{{ $search }}
                            <button wire:click="$set('search', '')" class="ml-2 hover:text-purple-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($typeFilter)
                        <span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-tag mr-1"></i>{{ $typeFilter === 'produto' ? 'Produto' : 'Serviço' }}
                            <button wire:click="$set('typeFilter', '')" class="ml-2 hover:text-blue-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($stockFilter)
                        <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-boxes mr-1"></i>
                            @if($stockFilter === 'com_stock') {{ __('Com Stock') }}
                            @elseif($stockFilter === 'sem_stock') {{ __('Sem Stock') }}
                            @elseif($stockFilter === 'gerenciado') {{ __('Gere stock') }}
                            @elseif($stockFilter === 'stock_baixo') {{ __('Stock abaixo do mínimo') }}
                            @else {{ __('Não gere stock') }}
                            @endif
                            <button wire:click="$set('stockFilter', '')" class="ml-2 hover:text-green-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($categoryFilter)
                        <span class="inline-flex items-center px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-folder mr-1"></i>{{ $categorias->firstWhere('id', $categoryFilter)?->name ?? __('Categoria') }}
                            <button wire:click="$set('categoryFilter', '')" class="ml-2 hover:text-amber-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($statusFilter)
                        <span class="inline-flex items-center px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-toggle-on mr-1"></i>{{ $statusFilter === 'activo' ? __('Activos') : __('Inactivos') }}
                            <button wire:click="$set('statusFilter', '')" class="ml-2 hover:text-slate-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($qualidadeFilter)
                        <span class="inline-flex items-center px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-triangle-exclamation mr-1"></i>
                            @if($qualidadeFilter === 'sem_preco') {{ __('Sem preço') }}
                            @elseif($qualidadeFilter === 'sem_codigo_barras') {{ __('Sem código de barras') }}
                            @else {{ __('Sem categoria') }}
                            @endif
                            <button wire:click="$set('qualidadeFilter', '')" class="ml-2 hover:text-orange-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($filterPrescricao)
                        <span class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-file-prescription mr-1"></i>{{ $filterPrescricao === 'sim' ? __('Exige receita') : __('Venda livre') }}
                            <button wire:click="$set('filterPrescricao', '')" class="ml-2 hover:text-red-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($filterTamanho)
                        <span class="inline-flex items-center px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-ruler mr-1"></i>{{ $filterTamanho }}
                            <button wire:click="$set('filterTamanho', '')" class="ml-2 hover:text-emerald-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($filterCor)
                        <span class="inline-flex items-center px-3 py-1 bg-teal-100 text-teal-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-palette mr-1"></i>{{ $filterCor }}
                            <button wire:click="$set('filterCor', '')" class="ml-2 hover:text-teal-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($filterConservacao)
                        <span class="inline-flex items-center px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-temperature-half mr-1"></i>{{ $rotulosConservacao[$filterConservacao] ?? $filterConservacao }}
                            <button wire:click="$set('filterConservacao', '')" class="ml-2 hover:text-amber-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($dateFrom)
                        <span class="inline-flex items-center px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-calendar mr-1"></i>De: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}
                            <button wire:click="$set('dateFrom', '')" class="ml-2 hover:text-orange-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($dateTo)
                        <span class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-calendar mr-1"></i>Até: {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
                            <button wire:click="$set('dateTo', '')" class="ml-2 hover:text-red-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- List -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2 text-purple-600"></i>
                    {{ __('Lista de Produtos') }}
                </h3>
                <span class="text-sm text-gray-600 font-semibold">
                    <i class="fas fa-box mr-1"></i>{{ $products->total() }} Total Produtos
                </span>
            </div>
        </div>
        
        <!-- Table Header -->
        <div class="overflow-x-auto">
        <div class="grid grid-cols-12 gap-4 px-4 sm:px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-600 uppercase min-w-[600px]">
            <div class="col-span-4 sm:col-span-3 flex items-center">
                <i class="fas fa-box mr-2 text-purple-500"></i>{{ __('Produto') }}
            </div>
            <div class="col-span-2 sm:col-span-1 hidden sm:flex items-center">
                <i class="fas fa-tag mr-2 text-pink-500"></i>{{ __('Tipo') }}
            </div>
            <div class="col-span-2 hidden md:flex items-center">
                <i class="fas fa-barcode mr-2 text-blue-500"></i>{{ __('Código') }}
            </div>
            <div class="col-span-3 sm:col-span-2 flex items-center">
                <i class="fas fa-money-bill-wave mr-2 text-green-500"></i>{{ __('Preço') }}
            </div>
            <div class="col-span-1 hidden lg:flex items-center">
                <i class="fas fa-percent mr-2 text-orange-500"></i>IVA
            </div>
            <div class="col-span-1 hidden lg:flex items-center">
                <i class="fas fa-warehouse mr-2 text-emerald-500"></i>{{ __('Stock') }}
            </div>
            <div class="col-span-1 hidden lg:flex items-center">
                <i class="fas fa-cube mr-2 text-cyan-500"></i>{{ __('Unidade') }}
            </div>
            <div class="col-span-3 sm:col-span-2 lg:col-span-1 flex items-center justify-end">
                <i class="fas fa-cog mr-2 text-gray-500"></i>{{ __('Ações') }}
            </div>
        </div>
        
        <!-- Table Body -->
        <div class="divide-y divide-gray-100">
            @forelse($products as $product)
                <div class="group grid grid-cols-12 gap-4 px-4 sm:px-6 py-3 sm:py-4 hover:bg-purple-50 transition-all duration-300 items-center min-w-[600px]">
                    <!-- Produto -->
                    <div class="col-span-4 sm:col-span-3 flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white font-bold shadow-lg flex-shrink-0">
                            {{ strtoupper(substr($product->name, 0, 2)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            {{-- O conteúdo líquido vai na própria linha do nome e não
                                 num crachá: "Champô X" e "Champô X" são a mesma
                                 linha até se ler o 200ml e o 750ml. --}}
                            <p class="font-bold text-gray-900 truncate">
                                {{ $product->name }}
                                @if(filled($product->net_content))
                                    <span class="text-gray-500 font-semibold">· {{ $product->net_content }}</span>
                                @endif
                            </p>

                            {{-- Receita/controlado, tamanho/cor e conservação/alergénios
                                 junto ao nome: são o que distingue duas linhas com a
                                 mesma designação (a mesma t-shirt em M e em L) e o que
                                 o balcão e o armazém têm de ver antes de dispensar ou
                                 de arrumar.

                                 O perfil da empresa NÃO entra nesta condição de
                                 propósito: o crachá só existe porque o artigo tem
                                 o dado, e um crachá "Receita" nunca deve
                                 desaparecer por se ter desligado uma definição de
                                 visualização. Ligar o perfil também não os
                                 inventa — não há crachá sem valor por trás. --}}
                            @if($product->requires_prescription || $product->is_controlled || filled($product->size) || filled($product->color) || filled($product->storage_conditions) || filled($product->allergens))
                                <div class="flex flex-wrap items-center gap-1 mt-1">
                                    @if($product->requires_prescription)
                                        <span class="inline-flex items-center px-1.5 py-0.5 bg-red-100 text-red-700 rounded text-[10px] font-bold" title="{{ __('Exige receita médica') }}">
                                            <i class="fas fa-file-prescription mr-1"></i>{{ __('Receita') }}
                                        </span>
                                    @endif
                                    @if($product->is_controlled)
                                        <span class="inline-flex items-center px-1.5 py-0.5 bg-purple-100 text-purple-700 rounded text-[10px] font-bold" title="{{ __('Psicotrópico / estupefaciente') }}">
                                            <i class="fas fa-triangle-exclamation mr-1"></i>{{ __('Controlado') }}
                                        </span>
                                    @endif
                                    @if(filled($product->size))
                                        <span class="inline-flex items-center px-1.5 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[10px] font-bold" title="{{ __('Tamanho') }}">
                                            {{ $product->size }}
                                        </span>
                                    @endif
                                    @if(filled($product->color))
                                        <span class="inline-flex items-center px-1.5 py-0.5 bg-teal-100 text-teal-700 rounded text-[10px] font-bold" title="{{ __('Cor') }}">
                                            {{ $product->color }}
                                        </span>
                                    @endif
                                    @if(filled($product->storage_conditions))
                                        {{-- O frio distingue-se do resto pela cor: numa
                                             entrada de mercadoria o que importa é ver
                                             de relance o que não pode ficar à espera
                                             na prateleira. --}}
                                        @php
                                            $corConservacao = match ($product->storage_conditions) {
                                                'refrigerado' => 'bg-sky-100 text-sky-700',
                                                'congelado'   => 'bg-indigo-100 text-indigo-700',
                                                default       => 'bg-gray-100 text-gray-600',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-1.5 py-0.5 {{ $corConservacao }} rounded text-[10px] font-bold" title="{{ __('Conservação') }}">
                                            <i class="fas fa-temperature-half mr-1"></i>{{ $rotulosConservacao[$product->storage_conditions] ?? $product->storage_conditions }}
                                        </span>
                                    @endif
                                    @if(filled($product->allergens))
                                        <span class="inline-flex items-center px-1.5 py-0.5 bg-orange-100 text-orange-700 rounded text-[10px] font-bold" title="{{ __('Alergénios') }}: {{ $product->allergens }}">
                                            <i class="fas fa-wheat-awn-circle-exclamation mr-1"></i>{{ __('Alergénios') }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            @if($product->description)
                                <p class="text-xs text-gray-500 truncate">{{ $product->description }}</p>
                            @else
                                <p class="text-xs text-gray-400 italic">{{ __('Sem descrição') }}</p>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Tipo -->
                    <div class="col-span-2 sm:col-span-1 hidden sm:block">
                        @if($product->type === 'produto')
                            <span class="inline-flex items-center px-2 py-1 bg-purple-100 text-purple-700 rounded-lg text-xs font-semibold">
                                <i class="fas fa-box mr-1"></i>{{ __('Produto') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 bg-pink-100 text-pink-700 rounded-lg text-xs font-semibold">
                                <i class="fas fa-concierge-bell mr-1"></i>{{ __('Serviço') }}
                            </span>
                        @endif
                    </div>
                    
                    <!-- Código -->
                    <div class="col-span-2 hidden md:block">
                        <span class="inline-flex items-center px-2.5 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold">
                            <i class="fas fa-barcode mr-1"></i>{{ $product->code }}
                        </span>
                    </div>
                    
                    <!-- Preço -->
                    <div class="col-span-3 sm:col-span-2">
                        <p class="text-sm font-bold text-green-600">{{ number_format($product->price, 2) }} Kz</p>
                        @if($product->cost > 0)
                            <p class="text-xs text-gray-500">Custo: {{ number_format($product->cost, 2) }} Kz</p>
                        @endif
                    </div>
                    
                    <!-- IVA -->
                    <div class="col-span-1 hidden lg:block">
                        {{-- `tax_rate` não existe (nem coluna, nem acessor): a
                             coluna do IVA saía sempre vazia, um "%" solto. A taxa
                             real vem da relação `taxRate`, e um produto isento
                             mostra-se como isento, não como 0%. --}}
                        @if(($product->tax_type ?? 'iva') === 'isento')
                            <span class="inline-flex items-center px-2.5 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs font-bold">
                                {{ __('Isento') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 bg-orange-100 text-orange-700 rounded-lg text-xs font-bold">
                                <i class="fas fa-percent mr-1"></i>{{ rtrim(rtrim(number_format((float) ($product->taxRate->rate ?? 0), 2, ',', ''), '0'), ',') }}%
                            </span>
                        @endif
                    </div>

                    <!-- Stock -->
                    <div class="col-span-1 hidden lg:block">
                        @if($product->manage_stock)
                            @php $qty = (float) ($product->stocks_total_quantity ?? 0); @endphp
                            @if($qty <= 0)
                                <span class="inline-flex items-center px-2 py-1 bg-red-100 text-red-700 rounded-lg text-xs font-bold" title="{{ __('Sem stock') }}">
                                    <i class="fas fa-times-circle mr-1"></i>{{ rtrim(rtrim(number_format($qty, 2), '0'), '.') }}
                                </span>
                            @elseif($product->stock_min > 0 && $qty <= $product->stock_min)
                                <span class="inline-flex items-center px-2 py-1 bg-amber-100 text-amber-700 rounded-lg text-xs font-bold" title="Stock baixo (mín: {{ $product->stock_min }})">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>{{ rtrim(rtrim(number_format($qty, 2), '0'), '.') }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold" title="{{ __('Em stock') }}">
                                    <i class="fas fa-check-circle mr-1"></i>{{ rtrim(rtrim(number_format($qty, 2), '0'), '.') }}
                                </span>
                            @endif
                        @else
                            <span class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-500 rounded-lg text-xs font-semibold" title="{{ __('Stock não gerenciado') }}">
                                <i class="fas fa-minus"></i>
                            </span>
                        @endif
                    </div>

                    <!-- Unidade -->
                    <div class="col-span-1 hidden lg:block">
                        <span class="inline-flex items-center px-2 py-1 bg-cyan-100 text-cyan-700 rounded-lg text-xs font-semibold">
                            {{ $product->unit }}
                        </span>
                    </div>
                    
                    <!-- Ações -->
                    <div class="col-span-3 sm:col-span-2 lg:col-span-1 flex items-center justify-end space-x-1">
                        @can('invoicing.products.view')
                        <button wire:click="view({{ $product->id }})"
                                wire:loading.attr="disabled"
                                class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition-all duration-300 shadow-md hover:shadow-lg hover:scale-110 disabled:opacity-50" title="{{ __('Visualizar') }}">
                            <i class="fas fa-eye text-xs" wire:loading.remove></i>
                            <i class="fas fa-spinner fa-spin text-xs" wire:loading></i>
                        </button>

                        {{-- Rastreio: para onde foi este artigo. Junta as vendas
                             com os movimentos de stock, porque é a divergência
                             entre os dois que denuncia problemas. --}}
                        <button wire:click="verRastreio({{ $product->id }})"
                                wire:loading.attr="disabled"
                                wire:target="verRastreio({{ $product->id }})"
                                class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center bg-teal-500 hover:bg-teal-600 text-white rounded-lg transition-all duration-300 shadow-md hover:shadow-lg hover:scale-110 disabled:opacity-50"
                                title="{{ __('Histórico de vendas e movimentos') }}">
                            <i class="fas fa-timeline text-xs" wire:loading.remove wire:target="verRastreio({{ $product->id }})"></i>
                            <i class="fas fa-spinner fa-spin text-xs" wire:loading wire:target="verRastreio({{ $product->id }})"></i>
                        </button>
                        @endcan

                        @can('invoicing.products.edit')
                        <button wire:click="edit({{ $product->id }})"
                                wire:loading.attr="disabled"
                                class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-all duration-300 shadow-md hover:shadow-lg hover:scale-110 disabled:opacity-50" title="{{ __('Editar') }}">
                            <i class="fas fa-edit text-xs" wire:loading.remove></i>
                            <i class="fas fa-spinner fa-spin text-xs" wire:loading></i>
                        </button>
                        @endcan
                        
                        @can('invoicing.products.delete')
                        @if($mostrarEliminados)
                        {{-- Na lixeira só faz sentido restaurar --}}
                        <button wire:click="restore({{ $product->id }})"
                                wire:target="restore({{ $product->id }})"
                                wire:loading.attr="disabled"
                                wire:confirm="Restaurar &quot;{{ $product->name }}&quot;?"
                                class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition-all duration-300 shadow-md hover:shadow-lg hover:scale-110 disabled:opacity-50" title="{{ __('Restaurar') }}">
                            <i class="fas fa-trash-can-arrow-up text-xs" wire:loading.remove wire:target="restore({{ $product->id }})"></i>
                            <i class="fas fa-spinner fa-spin text-xs" wire:loading wire:target="restore({{ $product->id }})"></i>
                        </button>
                        @else
                        <button wire:click="confirmDelete({{ $product->id }})"
                                wire:target="confirmDelete({{ $product->id }})"
                                wire:loading.attr="disabled"
                                class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition-all duration-300 shadow-md hover:shadow-lg hover:scale-110 disabled:opacity-50" title="{{ __('Excluir') }}">
                            <i class="fas fa-trash text-xs" wire:loading.remove wire:target="confirmDelete({{ $product->id }})"></i>
                            <i class="fas fa-spinner fa-spin text-xs" wire:loading wire:target="confirmDelete({{ $product->id }})"></i>
                        </button>
                        @endif
                        @endcan
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-box text-gray-400 text-3xl"></i>
                    </div>
                    {{-- Estado vazio honesto: dizer "crie um novo produto" quando
                         o catálogo TEM artigos, apenas escondidos por um filtro,
                         leva o utilizador a criar duplicados. --}}
                    @php
                        $haCatalogo = ($estatisticas['produtos'] + $estatisticas['servicos']) > 0;
                        $haFiltro = $search || $typeFilter || $stockFilter || $categoryFilter
                            || $statusFilter || $qualidadeFilter || $dateFrom || $dateTo
                            || $filterPrescricao || $filterTamanho || $filterCor || $filterConservacao;
                    @endphp

                    @if($haCatalogo && $haFiltro)
                        <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('Nada corresponde aos filtros') }}</h3>
                        <p class="text-gray-500 mb-4">
                            {{-- Dois números na mesma frase: o trans_choice
                                 só sabe concordar com um, portanto os plurais
                                 ficam na forma que esta mensagem quase sempre
                                 usa — quem tem zero de ambos vê o outro ramo
                                 do @if, o de catálogo vazio. --}}
                            {!! __('O catálogo tem <strong>:produtos</strong> produtos e <strong>:servicos</strong> serviços, mas nenhum passa nos filtros activos.', [
                                'produtos' => (int) $estatisticas['produtos'],
                                'servicos' => (int) $estatisticas['servicos'],
                            ]) !!}
                        </p>
                        <button wire:click="clearFilters"
                                class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-xl font-semibold hover:bg-purple-700 transition">
                            <i class="fas fa-redo mr-2"></i>{{ __('Limpar filtros') }}
                        </button>
                    @else
                        <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('Nenhum produto encontrado') }}</h3>
                        <p class="text-gray-500 mb-4">{{ __('Crie um novo produto para começar') }}</p>
                    @endif
                </div>
            @endforelse
        </div>

        @if($products->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
                {{ $products->links() }}
            </div>
        @endif
        </div>
    </div>

    <!-- Modals -->
    @include('livewire.workshop.artigos.partials.form-modal')
    @include('livewire.workshop.artigos.partials.view-modal')
    <x-delete-confirmation-modal 
        :itemName="$deletingProductName" 
        entityType="o produto"
        icon="fa-box-open"
    />

    {{-- ══════════ Rastreio do artigo ══════════ --}}
    @if($rastreio)
        {{-- Sempre em bloco: a forma de uma linha parte a compilação.
             E o próprio texto de um comentário não pode conter a directiva
             escrita por extenso — o Blade apanha-a mesmo dentro do comentário e
             engole tudo até ao fecho seguinte. Foi o que aconteceu aqui. --}}
        @php
            $r = $rastreio;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-2 sm:p-4"
             wire:click.self="fecharRastreio">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[92vh] overflow-y-auto">

                <div class="px-5 py-4 border-b sticky top-0 bg-white z-10 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="font-bold text-gray-900 truncate">
                            <i class="fas fa-timeline text-teal-600 mr-2"></i>{{ $r['produto']->name }}
                        </h3>
                        <p class="text-xs text-gray-500">{{ $r['produto']->code }} · rastreio de vendas e stock</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <select wire:model.live="rastreioDias" class="text-sm border border-gray-300 rounded-lg px-2 py-1.5">
                            <option value="30">{{ __('30 dias') }}</option>
                            <option value="90">{{ __('90 dias') }}</option>
                            <option value="365">{{ __('1 ano') }}</option>
                            <option value="0">{{ __('Tudo') }}</option>
                        </select>
                        <button wire:click="fecharRastreio" class="text-gray-400 hover:text-gray-700">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>

                <div class="p-5 space-y-5">
                    {{-- Resumo --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="bg-teal-50 rounded-xl p-3">
                            <p class="text-xs text-teal-700 font-semibold uppercase">{{ __('Vendido') }}</p>
                            <p class="text-xl font-bold text-teal-900">{{ rtrim(rtrim(number_format($r['resumo']['qtd_vendida'], 3, ',', '.'), '0'), ',') }}</p>
                            <p class="text-xs text-teal-600">{{ $r['resumo']['documentos'] }} documento(s)</p>
                        </div>
                        <div class="bg-green-50 rounded-xl p-3">
                            <p class="text-xs text-green-700 font-semibold uppercase">{{ __('Faturado') }}</p>
                            <p class="text-xl font-bold text-green-900">{{ number_format($r['resumo']['valor_vendido'], 2, ',', '.') }}</p>
                            <p class="text-xs text-green-600">Kz</p>
                        </div>
                        <div class="bg-blue-50 rounded-xl p-3">
                            <p class="text-xs text-blue-700 font-semibold uppercase">{{ __('Stock actual') }}</p>
                            <p class="text-xl font-bold text-blue-900">{{ rtrim(rtrim(number_format($r['resumo']['stock_total'], 3, ',', '.'), '0'), ',') }}</p>
                            <p class="text-xs text-blue-600">{{ $r['porArmazem']->count() }} armazém(ns)</p>
                        </div>
                        <div class="{{ abs($r['resumo']['divergencia']) > 0.001 ? 'bg-red-50' : 'bg-gray-50' }} rounded-xl p-3">
                            <p class="text-xs {{ abs($r['resumo']['divergencia']) > 0.001 ? 'text-red-700' : 'text-gray-600' }} font-semibold uppercase">{{ __('Vendido − saídas') }}</p>
                            <p class="text-xl font-bold {{ abs($r['resumo']['divergencia']) > 0.001 ? 'text-red-900' : 'text-gray-800' }}">
                                {{ rtrim(rtrim(number_format($r['resumo']['divergencia'], 3, ',', '.'), '0'), ',') }}
                            </p>
                            <p class="text-xs {{ abs($r['resumo']['divergencia']) > 0.001 ? 'text-red-600' : 'text-gray-500' }}">
                                {{ abs($r['resumo']['divergencia']) > 0.001 ? 'stock não acompanhou a venda' : 'coerente' }}
                            </p>
                        </div>
                    </div>

                    @if(abs($r['resumo']['divergencia']) > 0.001)
                        <div class="rounded-xl border-2 border-red-300 bg-red-50 p-3 text-sm text-red-800">
                            <strong><i class="fas fa-triangle-exclamation mr-1"></i>{{ __('Vendas e stock não batem certo.') }}</strong>
                            Foram vendidas {{ rtrim(rtrim(number_format($r['resumo']['qtd_vendida'], 3, ',', '.'), '0'), ',') }}
                            unidades mas só saíram {{ rtrim(rtrim(number_format($r['resumo']['saidas'], 3, ',', '.'), '0'), ',') }}
                            do stock. É o sintoma do artigo que aparece disponível mas cuja baixa falha.
                        </div>
                    @endif

                    {{-- Stock por armazém --}}
                    @if($r['porArmazem']->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach($r['porArmazem'] as $s)
                                <span class="px-3 py-1.5 bg-gray-100 rounded-lg text-sm">
                                    <strong>{{ $s->warehouse->name ?? 'Armazém #' . $s->warehouse_id }}:</strong>
                                    {{ rtrim(rtrim(number_format((float) $s->quantity, 3, ',', '.'), '0'), ',') }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Vendas --}}
                    <div>
                        <h4 class="font-bold text-sm text-gray-700 mb-2">
                            <i class="fas fa-file-invoice mr-1 text-gray-400"></i>Vendas ({{ $r['vendas']->count() }})
                        </h4>
                        <div class="rounded-xl border overflow-hidden overflow-x-auto">
                            <table class="w-full text-sm min-w-[640px]">
                                <thead class="bg-gray-50 text-xs text-gray-600">
                                    <tr>
                                        <th class="text-left px-3 py-2">{{ __('Data') }}</th>
                                        <th class="text-left px-3 py-2">{{ __('Documento') }}</th>
                                        <th class="text-left px-3 py-2">{{ __('Cliente') }}</th>
                                        <th class="text-right px-3 py-2">{{ __('Qtd') }}</th>
                                        <th class="text-right px-3 py-2">{{ __('Preço') }}</th>
                                        <th class="text-right px-3 py-2">{{ __('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @forelse($r['vendas'] as $v)
                                        <tr wire:key="venda-{{ $v->id }}" class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-gray-500 whitespace-nowrap">
                                                {{ optional($v->invoice?->invoice_date)->format('d/m/Y') ?? '—' }}
                                            </td>
                                            <td class="px-3 py-2 font-semibold">
                                                @if($v->invoice)
                                                    <a href="{{ route('invoicing.sales.invoices.preview', $v->invoice->id) }}"
                                                       target="_blank" class="text-indigo-600 hover:underline">
                                                        {{ $v->invoice->invoice_number }}
                                                    </a>
                                                @else — @endif
                                            </td>
                                            <td class="px-3 py-2">{{ $v->invoice?->client?->name ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right font-semibold">{{ rtrim(rtrim(number_format((float) $v->quantity, 3, ',', '.'), '0'), ',') }}</td>
                                            <td class="px-3 py-2 text-right">{{ number_format((float) $v->unit_price, 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $v->total, 2, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">{{ __('Sem vendas no período.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Movimentos de stock --}}
                    <div>
                        <h4 class="font-bold text-sm text-gray-700 mb-2">
                            <i class="fas fa-arrow-right-arrow-left mr-1 text-gray-400"></i>Movimentos de stock ({{ $r['movimentos']->count() }})
                        </h4>
                        <div class="rounded-xl border overflow-hidden overflow-x-auto">
                            <table class="w-full text-sm min-w-[640px]">
                                <thead class="bg-gray-50 text-xs text-gray-600">
                                    <tr>
                                        <th class="text-left px-3 py-2">{{ __('Data') }}</th>
                                        <th class="text-left px-3 py-2">{{ __('Tipo') }}</th>
                                        <th class="text-left px-3 py-2">{{ __('Armazém') }}</th>
                                        <th class="text-right px-3 py-2">{{ __('Qtd') }}</th>
                                        <th class="text-left px-3 py-2">{{ __('Origem') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @forelse($r['movimentos'] as $m)
                                        @php
                                            $cor = match($m->type) {
                                                'in'  => 'bg-green-100 text-green-700',
                                                'out' => 'bg-red-100 text-red-700',
                                                default => 'bg-blue-100 text-blue-700',
                                            };
                                        @endphp
                                        <tr wire:key="mov-{{ $m->id }}" class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-gray-500 whitespace-nowrap">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold {{ $cor }}">{{ $m->type }}</span></td>
                                            <td class="px-3 py-2">{{ $m->warehouse->name ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right font-semibold">{{ rtrim(rtrim(number_format((float) $m->quantity, 3, ',', '.'), '0'), ',') }}</td>
                                            <td class="px-3 py-2 text-gray-600 text-xs">
                                                {{ class_basename($m->reference_type) ?: '—' }}
                                                @if($m->notes) · {{ \Illuminate\Support\Str::limit($m->notes, 45) }} @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">{{ __('Sem movimentos no período.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
