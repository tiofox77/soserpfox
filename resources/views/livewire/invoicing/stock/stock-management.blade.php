<div class="p-6 relative">
    {{-- Overlay de loading global (feedback imediato em acções Livewire) --}}
    <div wire:loading.delay.long.flex
         wire:target="saveEntry,saveAdjustment,saveTransfer,search,warehouseFilter,lowStockFilter,filterConservacao,gotoPage,previousPage,nextPage"
         class="hidden absolute inset-0 z-40 bg-white/60 backdrop-blur-sm items-center justify-center rounded-lg">
        <div class="bg-white shadow-lg rounded-xl px-5 py-3 flex items-center gap-3 border border-gray-200">
            <i class="fas fa-spinner fa-spin text-purple-600 text-lg"></i>
            <span class="text-sm font-semibold text-gray-700">{{ __('A actualizar…') }}</span>
        </div>
    </div>

    {{-- Header --}}
    <div class="mb-4 sm:mb-6 flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-800">{{ __('Gestão de Stock') }}</h2>
            <p class="text-xs sm:text-base text-gray-600">{{ __('Controle de inventário por armazém') }}</p>
        </div>
        @can('invoicing.stock.edit')
        <button wire:click="openEntryModal"
                wire:loading.attr="disabled" wire:target="openEntryModal"
                class="btn-press inline-flex items-center px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-white rounded-xl font-bold shadow transition disabled:opacity-60">
            <span wire:loading.remove wire:target="openEntryModal">
                <i class="fas fa-plus-circle mr-2"></i> {{ __('Adicionar Stock') }}
            </span>
            <span wire:loading wire:target="openEntryModal" class="inline-flex items-center">
                <i class="fas fa-spinner fa-spin mr-2"></i> {{ __('A abrir…') }}
            </span>
        </button>
        @endcan
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 border-l-4 border-green-500 text-green-700">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 p-4 bg-red-100 border-l-4 border-red-500 text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6 mb-4 sm:mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-3 sm:p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-200 text-[11px] sm:text-sm font-medium">{{ __('Total de Produtos') }}</p>
                    <p class="text-xl sm:text-3xl font-bold mt-1">{{ $stats['total_products'] }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full shrink-0">
                    <i class="fas fa-box text-base sm:text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-3 sm:p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-[11px] sm:text-sm font-medium">{{ __('Quantidade Total') }}</p>
                    <p class="text-xl sm:text-3xl font-bold mt-1">{{ number_format($stats['total_quantity'], 0, ',', '.') }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full shrink-0">
                    <i class="fas fa-cubes text-base sm:text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-3 sm:p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-200 text-[11px] sm:text-sm font-medium">{{ __('Valor Total') }}</p>
                    <p class="text-lg sm:text-3xl font-bold mt-1 break-words">{{ number_format($stats['total_value'], 2, ',', '.') }} Kz</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full shrink-0">
                    <i class="fas fa-money-bill-wave text-base sm:text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-lg shadow-lg p-3 sm:p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-200 text-[11px] sm:text-sm font-medium">{{ __('Stock Baixo') }}</p>
                    <p class="text-xl sm:text-3xl font-bold mt-1">{{ $stats['low_stock'] }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full shrink-0">
                    <i class="fas fa-exclamation-triangle text-base sm:text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow-md p-3 sm:p-4 mb-4 sm:mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
            <div class="sm:col-span-2 md:col-span-2">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Pesquisar produto...') }}" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>
            <div>
                <select wire:model.live="warehouseFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value="">{{ __('Todos os Armazéns') }}</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="flex items-center px-4 py-2 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" wire:model.live="lowStockFilter" class="rounded text-red-600 mr-2">
                    <span class="text-sm">{{ __('Apenas Stock Baixo') }}</span>
                </label>
            </div>
        </div>

        @if($mostraConservacao)
            {{-- Aparece a quem trabalha com mercearia OU a quem já tem artigos
                 com conservação gravada. A segunda metade não é decorativa:
                 sem ela, desligar o perfil deixava a mercadoria refrigerada
                 gravada e sem forma de a separar do resto.

                 A lista é fechada (as três opções), por isso o select nunca
                 fica vazio a parecer avariado. --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mt-3 sm:mt-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1.5 uppercase">
                        <i class="fas fa-temperature-half mr-1 text-sky-600"></i>{{ __('Conservação') }}
                    </label>
                    <select wire:model.live="filterConservacao"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">{{ __('Todas') }}</option>
                        @foreach($rotulosConservacao as $chave => $rotulo)
                            <option value="{{ $chave }}">{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-4 sm:px-6 py-3 sm:py-4 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-white font-bold text-base sm:text-lg flex items-center">
                <i class="fas fa-boxes mr-2"></i>
                {{ __('Lista de Stock por Produto') }}
            </h3>
            <span class="md:hidden text-[11px] text-white/80 flex items-center gap-1">
                <i class="fas fa-arrows-left-right"></i> {{ __('Arraste para ver mais') }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider sticky left-0 bg-gray-50 z-10">
                            <i class="fas fa-box mr-1 text-purple-600"></i>{{ __('Produto') }}
                        </th>
                        @if($mostraConservacao)
                        {{-- Logo a seguir ao produto e não no fim da tabela: quem
                             descarrega mercadoria tem de ver o que vai ao frio
                             sem arrastar a lista para o lado. --}}
                        <th class="px-3 sm:px-6 py-3 sm:py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">
                            <i class="fas fa-temperature-half mr-1 text-sky-600"></i>{{ __('Conservação') }}
                        </th>
                        @endif
                        <th class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">
                            <i class="fas fa-warehouse mr-1 text-blue-600"></i>{{ __('Armazém') }}
                        </th>
                        <th class="px-3 sm:px-6 py-3 sm:py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">
                            <i class="fas fa-cubes mr-1 text-gray-600"></i>{{ __('Quantidade') }}
                        </th>
                        <th class="px-3 sm:px-6 py-3 sm:py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">
                            <i class="fas fa-check-circle mr-1 text-green-600"></i>{{ __('Disponível') }}
                        </th>
                        <th class="px-3 sm:px-6 py-3 sm:py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">
                            <i class="fas fa-lock mr-1 text-orange-600"></i>{{ __('Reservado') }}
                        </th>
                        <th class="px-3 sm:px-6 py-3 sm:py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">
                            <i class="fas fa-dollar-sign mr-1 text-purple-600"></i>{{ __('Custo Médio') }}
                        </th>
                        <th class="px-3 sm:px-6 py-3 sm:py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">
                            <i class="fas fa-cog mr-1 text-gray-600"></i>{{ __('Ações') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($stocks as $stock)
                    <tr class="hover:bg-purple-50 transition-all duration-200 ease-in-out transform hover:scale-[1.01] hover:shadow-md">
                        <!-- Product -->
                        <td class="px-3 sm:px-6 py-3 sm:py-4 sticky left-0 bg-white z-10">
                            <div class="flex items-center">
                                @if($stock->product->image_url)
                                    <img src="{{ $stock->product->image_url }}" 
                                         class="h-12 w-12 rounded-xl object-cover shadow-md ring-2 ring-purple-200"
                                         loading="lazy"
                                         onerror="this.src='{{ asset('images/placeholder-product.png') }}'">
                                @else
                                    <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center shadow-md">
                                        <i class="fas fa-box text-white text-lg"></i>
                                    </div>
                                @endif
                                <div class="ml-4">
                                    {{-- Conteúdo líquido na própria linha do nome: quem
                                         confere stock vê duas linhas de "Leite" e só as
                                         separa pelo 1L e pelo 200ml. Aparece sempre que
                                         o artigo o tenha, com perfil ligado ou
                                         desligado. --}}
                                    <div class="text-sm font-bold text-gray-900">
                                        {{ $stock->product->name }}
                                        @if(filled($stock->product->net_content))
                                            <span class="text-gray-500 font-semibold">· {{ $stock->product->net_content }}</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 flex items-center">
                                        <i class="fas fa-barcode mr-1"></i>
                                        {{ $stock->product->code }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        @if($mostraConservacao)
                        <!-- Conservação -->
                        <td class="px-3 sm:px-6 py-3 sm:py-4 text-center">
                            @php
                                $conservacao = $stock->product->storage_conditions;
                                // O frio distingue-se pela cor: numa descarga o que
                                // conta é ver de relance o que não pode ficar à espera.
                                $corConservacao = match ($conservacao) {
                                    'refrigerado' => 'bg-sky-100 text-sky-700',
                                    'congelado'   => 'bg-indigo-100 text-indigo-700',
                                    default       => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            @if(filled($conservacao))
                                {{-- Um valor fora da lista mostra-se como está gravado:
                                     mais vale cru do que escondido. --}}
                                <span class="inline-flex items-center px-3 py-1.5 text-xs font-bold rounded-full whitespace-nowrap {{ $corConservacao }}">
                                    <i class="fas fa-temperature-half mr-1.5"></i>
                                    {{ $rotulosConservacao[$conservacao] ?? $conservacao }}
                                </span>
                            @else
                                <span class="text-gray-400 text-sm">
                                    <i class="fas fa-minus-circle"></i>
                                </span>
                            @endif
                        </td>
                        @endif

                        <!-- Warehouse -->
                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-3 py-1.5 text-xs font-bold bg-blue-100 text-blue-800 rounded-full">
                                <i class="fas fa-warehouse mr-1.5"></i>
                                {{ $stock->warehouse?->name ?? 'Armazém indisponível' }}
                            </span>
                        </td>
                        
                        <!-- Quantity -->
                        <td class="px-3 sm:px-6 py-3 sm:py-4 text-center">
                            @php
                                $percentage = $stock->product->stock_min > 0 
                                    ? ($stock->quantity / $stock->product->stock_min) * 100 
                                    : 100;
                                $isLowStock = $stock->quantity <= $stock->product->stock_min;
                            @endphp
                            
                            <div class="flex flex-col items-center">
                                <span class="text-2xl font-bold {{ $isLowStock ? 'text-red-600 animate-pulse' : 'text-gray-900' }}">
                                    {{ number_format($stock->quantity, 0) }}
                                </span>
                                <div class="text-xs text-gray-500 flex items-center mt-1">
                                    <i class="fas fa-balance-scale mr-1"></i>
                                    {{ $stock->product->unit }}
                                </div>
                                @if($isLowStock)
                                    <span class="mt-1 px-2 py-0.5 bg-red-100 text-red-800 text-xs font-bold rounded-full animate-bounce">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>{{ __('Baixo') }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        
                        <!-- Available -->
                        <td class="px-3 sm:px-6 py-3 sm:py-4 text-center">
                            <div class="flex flex-col items-center">
                                <span class="text-lg font-bold text-green-600">
                                    {{ number_format($stock->available_quantity, 0) }}
                                </span>
                                <div class="w-full bg-gray-200 rounded-full h-2 mt-2 max-w-[80px]">
                                    @php
                                        $availablePercent = $stock->quantity > 0 
                                            ? ($stock->available_quantity / $stock->quantity) * 100 
                                            : 0;
                                    @endphp
                                    <div class="bg-green-500 h-2 rounded-full transition-all duration-300" 
                                         style="width: {{ min($availablePercent, 100) }}%"></div>
                                </div>
                            </div>
                        </td>
                        
                        <!-- Reserved -->
                        <td class="px-3 sm:px-6 py-3 sm:py-4 text-center">
                            @if($stock->reserved_quantity > 0)
                                <span class="inline-flex items-center px-3 py-1.5 bg-orange-100 text-orange-800 text-sm font-bold rounded-full">
                                    <i class="fas fa-lock mr-1.5"></i>
                                    {{ number_format($stock->reserved_quantity, 0) }}
                                </span>
                            @else
                                <span class="text-gray-400 text-sm">
                                    <i class="fas fa-minus-circle"></i>
                                </span>
                            @endif
                        </td>
                        
                        <!-- Cost -->
                        <td class="px-3 sm:px-6 py-3 sm:py-4 text-center">
                            @if($stock->unit_cost)
                                <div class="flex flex-col items-center">
                                    <span class="text-sm font-bold text-purple-600">{{ number_format($stock->unit_cost, 2) }} Kz</span>
                                    <span class="text-xs text-gray-500 mt-0.5">
                                        Total: {{ number_format($stock->quantity * $stock->unit_cost, 2) }} Kz
                                    </span>
                                </div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        
                        <!-- Actions -->
                        <td class="px-3 sm:px-6 py-3 sm:py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                @can('invoicing.stock.edit')
                                <button 
                                    wire:click="openAdjustModal({{ $stock->id }})"
                                    wire:loading.attr="disabled" wire:target="openAdjustModal({{ $stock->id }})"
                                    class="btn-press group relative p-2 bg-blue-100 hover:bg-blue-600 rounded-lg transition-all duration-200 transform hover:scale-110 disabled:opacity-50"
                                    title="{{ __('Ajustar Stock') }}">
                                    <i class="fas fa-edit text-blue-600 group-hover:text-white transition-colors" wire:loading.remove wire:target="openAdjustModal({{ $stock->id }})"></i>
                                    <i class="fas fa-spinner fa-spin text-blue-600" wire:loading wire:target="openAdjustModal({{ $stock->id }})"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap">
                                        {{ __('Ajustar') }}
                                    </span>
                                </button>
                                @endcan
                                
                                @can('invoicing.warehouse-transfer.create')
                                <button 
                                    wire:click="openTransferModal({{ $stock->id }})"
                                    wire:loading.attr="disabled" wire:target="openTransferModal({{ $stock->id }})"
                                    class="btn-press group relative p-2 bg-purple-100 hover:bg-purple-600 rounded-lg transition-all duration-200 transform hover:scale-110 disabled:opacity-50"
                                    title="{{ __('Transferir') }}">
                                    <i class="fas fa-exchange-alt text-purple-600 group-hover:text-white transition-colors" wire:loading.remove wire:target="openTransferModal({{ $stock->id }})"></i>
                                    <i class="fas fa-spinner fa-spin text-purple-600" wire:loading wire:target="openTransferModal({{ $stock->id }})"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap">
                                        {{ __('Transferir') }}
                                    </span>
                                </button>
                                @endcan
                                
                                <button 
                                    wire:click="showMovements({{ $stock->product_id }}, '{{ addslashes($stock->product->name) }}')"
                                    wire:loading.attr="disabled" wire:target="showMovements({{ $stock->product_id }})"
                                    class="btn-press group relative p-2 bg-green-100 hover:bg-green-600 rounded-lg transition-all duration-200 transform hover:scale-110 disabled:opacity-50"
                                    title="{{ __('Ver Movimentos') }}">
                                    <i class="fas fa-history text-green-600 group-hover:text-white transition-colors" wire:loading.remove wire:target="showMovements({{ $stock->product_id }})"></i>
                                    <i class="fas fa-spinner fa-spin text-green-600" wire:loading wire:target="showMovements({{ $stock->product_id }})"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap">
                                        {{ __('Histórico') }}
                                    </span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        {{-- Acompanha a coluna de conservação: um colspan a menos
                             deixava a célula vazia a desalinhar a tabela toda. --}}
                        <td colspan="{{ $mostraConservacao ? 8 : 7 }}" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-boxes text-gray-300 text-4xl"></i>
                                </div>
                                <p class="text-gray-500 text-lg font-semibold">{{ __('Nenhum stock encontrado') }}</p>
                                <p class="text-gray-400 text-sm mt-2 mb-4">
                                    @if($search)
                                        Não existe stock para "<span class="font-bold">{{ $search }}</span>". Pode adicionar agora.
                                    @else
                                        Adicione uma entrada de stock para um produto novo.
                                    @endif
                                </p>
                                @can('invoicing.stock.edit')
                                <button wire:click="openEntryModal"
                                        wire:loading.attr="disabled" wire:target="openEntryModal"
                                        class="btn-press inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-white rounded-xl font-bold shadow transition disabled:opacity-60">
                                    <span wire:loading.remove wire:target="openEntryModal">
                                        <i class="fas fa-plus-circle mr-2"></i> {{ __('Adicionar Stock') }}
                                    </span>
                                    <span wire:loading wire:target="openEntryModal" class="inline-flex items-center">
                                        <i class="fas fa-spinner fa-spin mr-2"></i> {{ __('A abrir…') }}
                                    </span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $stocks->links() }}
        </div>
    </div>

    {{-- Modals --}}
    @include('livewire.invoicing.stock.partials.entry-modal')
    @include('livewire.invoicing.stock.partials.adjust-modal')
    @include('livewire.invoicing.stock.partials.transfer-modal')
    @include('livewire.invoicing.stock.partials.movements-modal')
</div>
