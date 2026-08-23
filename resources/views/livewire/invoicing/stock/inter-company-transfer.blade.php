<div>
    {{-- Toast Notifications --}}
    <div x-data="{ show: false, message: '', type: 'success' }"
         x-on:notify.window="show = true; message = $event.detail[0]?.message || $event.detail.message; type = $event.detail[0]?.type || $event.detail.type; setTimeout(() => show = false, 4000)"
         x-show="show" x-transition
         x-cloak
         class="fixed top-4 right-4 z-[100] max-w-sm">
        <div :class="type === 'success' ? 'bg-green-500' : 'bg-red-500'" class="text-white px-6 py-4 rounded-xl shadow-2xl flex items-center">
            <i :class="type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'" class="mr-3 text-lg"></i>
            <span x-text="message" class="font-semibold text-sm"></span>
        </div>
    </div>

    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-building text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">{{ __('Transferência Entre Empresas') }}</h2>
                    <p class="text-purple-100 text-sm">{{ __('Transfira stock entre as suas empresas') }}</p>
                </div>
            </div>
            <button wire:click="openModal" class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-exchange-alt mr-2"></i>{{ __('Nova Transferência') }}
            </button>
        </div>
    </div>

    {{-- Info Alert --}}
    <div class="mb-6 bg-blue-50 border-2 border-blue-200 rounded-2xl p-4">
        <div class="flex items-center">
            <i class="fas fa-info-circle text-blue-500 text-lg mr-3"></i>
            <p class="text-sm text-blue-700">
                <strong>{{ __('Transferência Inter-Empresas:') }}</strong> {{ __('O stock será removido da empresa origem e adicionado à empresa destino. Apenas empresas que você gerencia são listadas.') }}
            </p>
        </div>
    </div>

    {{-- Transfer History --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-pink-50 border-b-2 border-purple-100">
            <h3 class="text-lg font-bold text-purple-900 flex items-center">
                <i class="fas fa-history mr-2"></i>{{ __('Histórico de Transferências') }}
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Data') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Tipo') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Produto') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Origem') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Destino') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Quantidade') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Saldo') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Observações') }}</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Usuário') }}</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Documento') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($transfers as $transfer)
                    @php
                        $isOutgoing = $transfer->type === 'transfer';
                    @endphp
                    <tr class="hover:bg-purple-50/50 transition">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            {{ $transfer->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if($isOutgoing)
                                <span class="px-3 py-1 text-xs font-bold bg-red-100 text-red-800 rounded-full">
                                    <i class="fas fa-arrow-up mr-1"></i>{{ __('Enviado') }}
                                </span>
                            @else
                                <span class="px-3 py-1 text-xs font-bold bg-green-100 text-green-800 rounded-full">
                                    <i class="fas fa-arrow-down mr-1"></i>{{ __('Recebido') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-gray-900">{{ $transfer->product->name ?? 'N/A' }}</div>
                            <div class="text-xs text-gray-500">{{ $transfer->product->code ?? '' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($isOutgoing)
                                <span class="px-3 py-1 text-xs font-bold bg-red-100 text-red-800 rounded-full">
                                    <i class="fas fa-warehouse mr-1"></i>{{ $transfer->warehouse->name ?? 'N/A' }}
                                </span>
                            @else
                                @if($transfer->fromWarehouse)
                                    <span class="px-3 py-1 text-xs font-bold bg-orange-100 text-orange-800 rounded-full">
                                        <i class="fas fa-warehouse mr-1"></i>{{ $transfer->fromWarehouse->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-sm italic">{{ __('Empresa externa') }}</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($isOutgoing)
                                @if($transfer->toWarehouse)
                                    <span class="px-3 py-1 text-xs font-bold bg-green-100 text-green-800 rounded-full">
                                        <i class="fas fa-warehouse mr-1"></i>{{ $transfer->toWarehouse->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-sm">-</span>
                                @endif
                            @else
                                <span class="px-3 py-1 text-xs font-bold bg-green-100 text-green-800 rounded-full">
                                    <i class="fas fa-warehouse mr-1"></i>{{ $transfer->warehouse->name ?? 'N/A' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-lg font-bold {{ $isOutgoing ? 'text-red-600' : 'text-green-600' }}">
                                {{ $isOutgoing ? '-' : '+' }}{{ number_format(abs($transfer->quantity), 2) }}
                            </span>
                        </td>
                        {{-- Saldo antes e depois. Sem isto sabia-se que saíram 3,
                             não de quanto para quanto. --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap text-sm">
                            @if($transfer->balance_after !== null)
                                <span class="text-gray-500">{{ rtrim(rtrim(number_format((float) $transfer->balance_before, 2, ',', '.'), '0'), ',') }}</span>
                                <span class="text-gray-400 mx-1">&rarr;</span>
                                <span class="font-bold {{ (float) $transfer->balance_after <= 0 ? 'text-red-600' : 'text-gray-900' }}">
                                    {{ rtrim(rtrim(number_format((float) $transfer->balance_after, 2, ',', '.'), '0'), ',') }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate">
                            {{ $transfer->notes }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            {{ $transfer->user->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if($transfer->batch_reference)
                                <p class="font-mono text-xs font-bold text-indigo-700 mb-1">{{ $transfer->batch_reference }}</p>
                                <a href="{{ route('invoicing.stock.batch-preview', ['reference' => $transfer->batch_reference]) }}"
                                   target="_blank" rel="noopener" title="{{ __('Ver documento') }}"
                                   class="inline-flex items-center px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold transition">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('invoicing.stock.batch-pdf', ['reference' => $transfer->batch_reference]) }}"
                                   target="_blank" rel="noopener" title="{{ __('Descarregar PDF') }}"
                                   class="inline-flex items-center px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg text-xs font-semibold transition ml-1">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-building text-gray-400 text-3xl"></i>
                                </div>
                                <p class="text-gray-500 text-lg font-semibold mb-2">{{ __('Nenhuma transferência realizada') }}</p>
                                <p class="text-gray-400 text-sm">{{ __('Use o botão acima para transferir stock entre empresas') }}</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $transfers->links() }}
        </div>
    </div>

    {{-- Modal Transfer --}}
    @if($showTransferModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 overflow-y-auto h-full w-full z-50 flex items-start justify-center p-4 pt-10">
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-y-auto transform transition-all">
            {{-- Header --}}
            <div class="sticky top-0 bg-gradient-to-r from-purple-600 to-pink-600 px-8 py-6 rounded-t-2xl flex items-center justify-between z-10">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-building text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-white">{{ __('Nova Transferência Inter-Empresas') }}</h3>
                        <p class="text-purple-100 text-sm">{{ __('Selecione armazéns, adicione produtos e confirme') }}</p>
                    </div>
                </div>
                <button wire:click="$set('showTransferModal', false)" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <div class="p-8 space-y-6">
                {{-- Step 1: Armazéns --}}
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 border-2 border-purple-200 rounded-2xl p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">1</div>
                        <div>
                            <h4 class="font-bold text-purple-900 text-lg">{{ __('Origem e Destino') }}</h4>
                            <p class="text-sm text-purple-700">{{ __('Selecione os armazéns e a empresa destino') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {{-- Armazém Origem --}}
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-warehouse mr-1 text-red-600"></i>{{ __('Armazém Origem *') }}
                            </label>
                            <select wire:model.live="warehouseFromId" class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition font-semibold">
                                <option value="">{{ __('Selecione...') }}</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Empresa Destino --}}
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-building mr-1 text-green-600"></i>{{ __('Empresa Destino *') }}
                            </label>
                            <select wire:model.live="tenantToId" class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition font-semibold">
                                <option value="">{{ __('Selecione...') }}</option>
                                @foreach($myTenants as $tenant)
                                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Armazém Destino --}}
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-warehouse mr-1 text-green-600"></i>{{ __('Armazém Destino *') }}
                            </label>
                            <select wire:model="warehouseToId" class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition font-semibold">
                                <option value="">{{ __('Selecione...') }}</option>
                                @if($tenantToId)
                                    @foreach($this->getWarehousesForTenant() as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Step 2: Produtos --}}
                <div class="bg-gradient-to-r from-blue-50 to-cyan-50 border-2 border-blue-200 rounded-2xl p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">2</div>
                        <div>
                            <h4 class="font-bold text-blue-900 text-lg">{{ __('Selecione os Produtos') }}</h4>
                            <p class="text-sm text-blue-700">{{ __('Clique nos produtos para adicionar à transferência') }}</p>
                        </div>
                    </div>

                    {{-- Search --}}
                    <div class="mb-4">
                        <input type="text" wire:model.live.debounce.300ms="productSearch"
                               class="w-full px-5 py-3 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                               placeholder="{{ __('🔍 Procurar produto por nome ou código...') }}">
                    </div>

                    {{-- Products Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-64 overflow-y-auto p-1">
                        {{-- A pesquisa e o limite são feitos em SQL, no
                             componente. Aqui filtrava-se em PHP a colecção
                             inteira — que era o catálogo todo, com todas as
                             linhas de stock de cada artigo carregadas. --}}
                        @forelse($products as $prod)
                            <div wire:click="selectProduct({{ $prod->id }})"
                                 class="p-3 border-2 rounded-xl cursor-pointer transition border-gray-200 hover:border-purple-400 hover:shadow-lg bg-white hover:scale-[1.02]">
                                <p class="font-bold text-sm text-gray-900 truncate">{{ $prod->name }}</p>
                                <p class="text-xs text-gray-500">{{ $prod->code }}</p>
                                @if($warehouseFromId)
                                    {{-- O disponível vem na mesma consulta dos artigos. --}}
                                    @php $qty = (float) ($prod->disponivel_na_origem ?? 0); @endphp
                                    <div class="mt-2 pt-2 border-t border-gray-100 flex items-center justify-between">
                                        <span class="text-xs text-gray-500">{{ __('Stock:') }}</span>
                                        <span class="text-sm font-bold {{ $qty > 0 ? 'text-green-600' : 'text-red-500' }}">{{ number_format($qty, 2) }}</span>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="col-span-3 text-center py-8 text-gray-400">
                                <i class="fas fa-search text-3xl mb-2"></i>
                                @if(!$warehouseFromId)
                                    <p>{{ __('Escolha primeiro o armazém de origem') }}</p>
                                @elseif($productSearch)
                                    <p>Nenhum produto encontrado para "{{ $productSearch }}"</p>
                                @else
                                    <p>{{ __('Este armazém não tem nenhum artigo com stock') }}</p>
                                @endif
                            </div>
                        @endforelse
                    </div>

                    {{-- Dizer que a lista está cortada. Sem isto, quem não
                         encontrasse o artigo concluía que ele não existe. --}}
                    @if($products->count() >= 50)
                        <p class="text-xs text-gray-500 text-center mt-2">
                            <i class="fas fa-circle-info mr-1"></i>
                            {{ __('A mostrar os primeiros 50 artigos — escreva acima para encontrar outro.') }}
                        </p>
                    @elseif(!$productSearch && $warehouseFromId)
                        <p class="text-xs text-gray-500 text-center mt-2">
                            <i class="fas fa-circle-info mr-1"></i>
                            {{ __('A mostrar os artigos com stock neste armazém. Escreva acima para procurar qualquer outro.') }}
                        </p>
                    @endif
                </div>

                {{-- Step 3: Cart --}}
                @if(count($transferItems) > 0)
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-2xl p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-green-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">3</div>
                        <div class="flex-1">
                            <h4 class="font-bold text-green-900 text-lg">{{ __('Produtos a Transferir') }}</h4>
                            <p class="text-sm text-green-700">{{ count($transferItems) }} produto(s) no carrinho</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        {{-- `wire:key` pelo produto: sem ele o Livewire reutiliza
                             as linhas por POSIÇÃO. --}}
                        @foreach($transferItems as $index => $item)
                            <div wire:key="linha-{{ $item['product_id'] ?? 'x' . $index }}"
                                 class="flex items-center justify-between p-4 bg-white rounded-xl border-2 border-green-200 shadow-sm">
                                <div class="flex items-center flex-1">
                                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center mr-4">
                                        <i class="fas fa-box text-white text-lg"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-bold text-gray-900">{{ $item['product_name'] }}</p>
                                        <p class="text-sm text-gray-500">{{ $item['product_code'] }}</p>
                                    </div>
                                </div>
                                {{-- Editável no próprio carrinho: a única saída
                                     para uma quantidade errada era apagar a
                                     linha e voltar a procurar o artigo.
                                     `.blur` e não `.live`: com validação a cada
                                     tecla, escrever "10" dava erro no "1". --}}
                                <div class="text-right mr-4">
                                    <label class="block text-xs text-gray-500 mb-1" for="qtd-ic-{{ $index }}">{{ __('Quantidade') }}</label>
                                    <input id="qtd-ic-{{ $index }}" type="number" min="0.01" step="0.01" inputmode="decimal"
                                           wire:model.blur="transferItems.{{ $index }}.quantity"
                                           class="w-28 px-3 py-2 text-right text-2xl font-bold text-purple-600 rounded-xl border-2 border-purple-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition">
                                </div>
                                <div class="text-right mr-4">
                                    <p class="text-xs text-gray-500">{{ __('Custo Unit.') }}</p>
                                    <p class="text-sm font-semibold text-gray-700">{{ number_format($item['unit_cost'], 2) }} Kz</p>
                                </div>
                                <button type="button" wire:click="removeProduct({{ $index }})"
                                        class="text-red-500 hover:text-white hover:bg-red-500 p-3 rounded-xl transition">
                                    <i class="fas fa-trash-alt text-lg"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Step 4: Notes --}}
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-2 border-yellow-200 rounded-2xl p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-yellow-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">
                            {{ count($transferItems) > 0 ? '4' : '3' }}
                        </div>
                        <div>
                            <h4 class="font-bold text-yellow-900 text-lg">{{ __('Motivo da Transferência') }}</h4>
                            <p class="text-sm text-yellow-700">{{ __('Descreva o motivo desta transferência') }}</p>
                        </div>
                    </div>
                    <textarea wire:model="notes" rows="3"
                        placeholder="{{ __('Ex: Reposição de stock, encomenda de cliente, etc...') }}"
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition"></textarea>
                </div>

                {{-- Warning --}}
                <div class="bg-yellow-50 border-2 border-yellow-200 rounded-2xl p-4">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-lg mr-3"></i>
                        <p class="text-sm text-yellow-700">
                            <strong>{{ __('Atenção:') }}</strong> {{ __('Esta acção é irreversível. O stock será removido da empresa origem e adicionado à empresa destino.') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="sticky bottom-0 bg-gray-50 px-8 py-6 rounded-b-2xl flex justify-end space-x-4">
                <button type="button" wire:click="$set('showTransferModal', false)"
                    class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                    <i class="fas fa-times mr-2"></i>{{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="saveTransfer" wire:loading.attr="disabled" wire:target="saveTransfer"
                    class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white px-8 py-3 rounded-xl font-semibold shadow-lg transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="saveTransfer"><i class="fas fa-check mr-2"></i>Confirmar Transferência ({{ count($transferItems) }})</span>
                    <span wire:loading wire:target="saveTransfer"><i class="fas fa-spinner fa-spin mr-2"></i>{{ __('A processar...') }}</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Quantity Sub-Modal --}}
    @if($showQuantityModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-[60] flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all">
            <div class="bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-4 rounded-t-2xl">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-hashtag mr-2"></i>{{ __('Definir Quantidade') }}
                </h3>
            </div>

            <div class="p-6">
                <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mb-6">
                    <div class="flex items-center mb-2">
                        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-box text-white"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-bold text-gray-900">{{ $selectedProductName }}</p>
                            <p class="text-sm text-gray-600">{{ $selectedProductCode }}</p>
                        </div>
                    </div>
                    @if($availableStock > 0)
                        <div class="mt-3 pt-3 border-t border-blue-200 flex items-center justify-between">
                            <span class="text-sm text-gray-700">{{ __('Stock Disponível:') }}</span>
                            <span class="text-2xl font-bold text-green-600">{{ number_format($availableStock, 2) }}</span>
                        </div>
                    @else
                        <div class="mt-3 pt-3 border-t border-blue-200">
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                <p class="text-sm text-red-700 font-semibold">{{ __('Sem stock neste armazém') }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-3">{{ __('Quantidade a Transferir *') }}</label>
                    <input type="number" wire:model.blur="productQuantity" step="0.01" min="0.01" max="{{ $availableStock }}"
                           class="w-full px-6 py-4 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-200 transition text-3xl font-bold text-center"
                           placeholder="0.00" autofocus>

                    @if($availableStock > 0)
                    <div class="grid grid-cols-4 gap-2 mt-4">
                        @if($availableStock >= 1)
                            <button type="button" wire:click="$set('productQuantity', 1)" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 border-2 border-gray-300 hover:border-blue-400 rounded-lg font-semibold text-sm transition">1</button>
                        @endif
                        @if($availableStock >= 5)
                            <button type="button" wire:click="$set('productQuantity', 5)" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 border-2 border-gray-300 hover:border-blue-400 rounded-lg font-semibold text-sm transition">5</button>
                        @endif
                        @if($availableStock >= 10)
                            <button type="button" wire:click="$set('productQuantity', 10)" class="px-3 py-2 bg-gray-100 hover:bg-blue-100 border-2 border-gray-300 hover:border-blue-400 rounded-lg font-semibold text-sm transition">10</button>
                        @endif
                        <button type="button" wire:click="$set('productQuantity', {{ $availableStock }})" class="px-3 py-2 bg-blue-100 hover:bg-blue-200 border-2 border-blue-400 rounded-lg font-semibold text-sm transition">{{ __('Tudo') }}</button>
                    </div>
                    @endif
                </div>
            </div>

            <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end space-x-3">
                <button type="button" wire:click="$set('showQuantityModal', false)"
                        class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                    <i class="fas fa-times mr-2"></i>{{ __('Cancelar') }}
                </button>
                <button type="button" wire:click="addProductToTransfer"
                        class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-8 py-3 rounded-xl font-bold transition shadow-lg hover:shadow-xl">
                    <i class="fas fa-cart-plus mr-2"></i>{{ __('Adicionar') }}
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Comprovativo da transferência acabada de registar.

         São DUAS referências porque são dois documentos: MOV/AAAA/NNNNNN é
         sequencial POR EMPRESA, e cada uma fica com o seu na sua sequência. O
         do destino é dado a conhecer mas não abre daqui — pertence à outra
         empresa e só de lá se imprime. --}}
    @if($batchReference)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-[70] flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[92vh] overflow-y-auto">

            @php
                $qtd = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
            @endphp

            <div class="sticky top-0 bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-4 rounded-t-2xl flex items-center justify-between z-10">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-circle-check mr-2"></i>
                    {{ __('Transferência registada') }}
                </h3>
                <button wire:click="fecharPainelLote" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <div class="p-6 space-y-5">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="text-center p-4 rounded-xl bg-red-50 border-2 border-red-200">
                        <p class="text-xs text-gray-600 uppercase">{{ __('Esta empresa (saída)') }}</p>
                        <p class="mt-1 font-mono text-lg font-extrabold text-red-800">{{ $batchReference }}</p>
                    </div>
                    <div class="text-center p-4 rounded-xl bg-green-50 border-2 border-green-200">
                        <p class="text-xs text-gray-600 uppercase">{{ $batchDestinoNome }} (entrada)</p>
                        <p class="mt-1 font-mono text-lg font-extrabold text-green-800">{{ $batchReferenceDestino }}</p>
                    </div>
                </div>

                <p class="text-xs text-gray-500 text-center">
                    {{ __('Registado por') }} <strong>{{ auth()->user()->name }}</strong> em {{ now()->format('d/m/Y H:i') }}
                </p>

                <div class="border-2 border-gray-200 rounded-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 uppercase align-bottom">{{ __('Produto') }}</th>
                                    <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 uppercase align-bottom">{{ __('Transf.') }}</th>
                                    <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-red-700 uppercase border-l">{{ __('Esta empresa') }}</th>
                                    <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-green-700 uppercase border-l">{{ $batchDestinoNome }}</th>
                                </tr>
                                <tr class="text-[11px] text-gray-500 uppercase">
                                    <th class="px-3 py-1 text-right font-semibold border-l">{{ __('Antes') }}</th>
                                    <th class="px-3 py-1 text-right font-semibold">{{ __('Ficou') }}</th>
                                    <th class="px-3 py-1 text-right font-semibold border-l">{{ __('Antes') }}</th>
                                    <th class="px-3 py-1 text-right font-semibold">{{ __('Ficou') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($batchResumo as $linha)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2">
                                            <p class="font-semibold text-gray-900">{{ $linha['produto'] }}</p>
                                            @if(!empty($linha['codigo']))
                                                <p class="text-xs text-gray-500">{{ $linha['codigo'] }}</p>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right font-bold text-purple-600">{{ $qtd($linha['quantidade']) }}</td>
                                        <td class="px-3 py-2 text-right text-gray-500 border-l">{{ $qtd($linha['origem_antes']) }}</td>
                                        <td class="px-3 py-2 text-right font-bold {{ $linha['origem_depois'] <= 0 ? 'text-red-600' : 'text-gray-900' }}">
                                            {{ $qtd($linha['origem_depois']) }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-500 border-l">{{ $qtd($linha['destino_antes']) }}</td>
                                        <td class="px-3 py-2 text-right font-bold text-green-700">{{ $qtd($linha['destino_depois']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <a href="{{ route('invoicing.stock.batch-preview', ['reference' => $batchReference]) }}"
                       target="_blank" rel="noopener"
                       class="flex items-center justify-center gap-2 px-5 py-3 bg-white border-2 border-gray-300 hover:bg-gray-50 text-gray-700 rounded-xl font-bold transition">
                        <i class="fas fa-eye"></i> {{ __('Pré-visualizar') }}
                    </a>
                    <a href="{{ route('invoicing.stock.batch-pdf', ['reference' => $batchReference]) }}"
                       target="_blank" rel="noopener"
                       class="flex items-center justify-center gap-2 px-5 py-3 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white rounded-xl font-bold shadow transition">
                        <i class="fas fa-file-pdf text-lg"></i> {{ __('Abrir documento em PDF') }}
                    </a>
                </div>

                <p class="text-[11px] text-gray-400 text-center">
                    O documento acima é o desta empresa. O de {{ $batchDestinoNome }}
                    ({{ $batchReferenceDestino }}) imprime-se de lá.<br>
                    {{ __('Documento interno de conferência — não é documento fiscal e não substitui factura nem guia de transporte.') }}
                </p>
            </div>

            <div class="sticky bottom-0 bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end border-t">
                <button type="button" wire:click="fecharPainelLote"
                        class="px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-xl font-bold shadow transition">
                    {{ __('Concluir') }}
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
