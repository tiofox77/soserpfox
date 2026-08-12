<!-- Adjust Modal -->
@if($showAdjustModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-6xl w-[calc(100%-1rem)] sm:w-full max-h-[94vh] overflow-y-auto transform transition-all animate-scale-in">
        <!-- Header -->
        <div class="sticky top-0 bg-gradient-to-r from-yellow-500 to-orange-500 px-4 sm:px-8 py-4 sm:py-6 rounded-t-2xl flex items-center justify-between z-10">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-sliders-h text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-white">Ajustar Stock</h3>
                    <p class="text-yellow-100 text-sm">Entrada ou saída manual de stock</p>
                </div>
            </div>
            <button wire:click="$set('showAdjustModal', false)" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-4 sm:p-8">
            <div class="space-y-8">
                <!-- Step 1: Settings -->
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-2 border-yellow-200 rounded-2xl p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-yellow-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">
                            1
                        </div>
                        <div>
                            <h4 class="font-bold text-yellow-900 text-lg">Configurações do Ajuste</h4>
                            <p class="text-sm text-yellow-700">Selecione o armazém e tipo de ajuste</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Warehouse -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-3">
                                <i class="fas fa-warehouse mr-1 text-yellow-600"></i>Armazém *
                            </label>
                            <select wire:model.live="adjustWarehouse"
                                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition text-base font-semibold">
                                <option value="">📦 Selecionar armazém...</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Type -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-3">
                                <i class="fas fa-arrow-up-arrow-down mr-1 text-yellow-600"></i>Tipo de Ajuste *
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" wire:model="adjustType" value="in" class="peer sr-only">
                                    <div class="p-3 border-2 border-gray-200 rounded-xl peer-checked:border-green-500 peer-checked:bg-green-50 transition text-center">
                                        <i class="fas fa-arrow-up text-xl text-green-600 mb-1"></i>
                                        <p class="font-bold text-sm text-gray-900">Entrada</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" wire:model="adjustType" value="out" class="peer sr-only">
                                    <div class="p-3 border-2 border-gray-200 rounded-xl peer-checked:border-red-500 peer-checked:bg-red-50 transition text-center">
                                        <i class="fas fa-arrow-down text-xl text-red-600 mb-1"></i>
                                        <p class="font-bold text-sm text-gray-900">Saída</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Products -->
                <div class="bg-gradient-to-r from-blue-50 to-cyan-50 border-2 border-blue-200 rounded-2xl p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">
                            2
                        </div>
                        <div>
                            <h4 class="font-bold text-blue-900 text-lg">Selecione os Produtos</h4>
                            <p class="text-sm text-blue-700">Procure e adicione produtos para ajustar</p>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-search mr-2 text-blue-600 text-lg"></i>
                            <span>Procurar Produto</span>
                        </label>
                        <input type="text" wire:model.live.debounce.300ms="adjustSearch"
                               class="w-full px-5 py-4 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition text-base"
                               placeholder="🔍 Digite o nome ou código do produto para pesquisar...">
                    </div>

                    <!-- Products Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-96 overflow-y-auto p-2">
                        {{-- A pesquisa e o limite são feitos em SQL, no
                             componente. Aqui filtrava-se em PHP a colecção
                             inteira — que era o catálogo todo. --}}
                        @forelse($products as $prod)
                            <div wire:click="selectProductForAdjust({{ $prod->id }})"
                                 class="p-4 border-2 rounded-xl cursor-pointer transition border-gray-200 hover:border-yellow-400 hover:shadow-lg bg-white hover:scale-105">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex-1">
                                        <p class="font-bold text-sm text-gray-900">{{ $prod->name }}</p>
                                        <p class="text-xs text-gray-600">{{ $prod->code }}</p>
                                    </div>
                                </div>
                                @if($adjustWarehouse)
                                    {{-- O stock vem na mesma consulta dos artigos.
                                         Aqui estava um Stock::where()->first() por
                                         cartão: com 5.729 artigos eram 5.729
                                         consultas por render. --}}
                                    @php $qty = (float) ($prod->stock_no_armazem ?? 0); @endphp
                                    <div class="mt-2 pt-2 border-t border-gray-200">
                                        <p class="text-xs text-gray-600">Stock atual:</p>
                                        <p class="text-sm font-bold text-blue-600">
                                            {{ number_format($qty, 2) }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="col-span-3 text-center py-8 text-gray-500">
                                <i class="fas fa-search text-3xl mb-2"></i>
                                @if(!$adjustWarehouse)
                                    <p>Escolha primeiro o armazém</p>
                                @elseif($adjustSearch)
                                    <p>Nenhum produto encontrado para "{{ $adjustSearch }}"</p>
                                @else
                                    <p>Nenhum produto no catálogo</p>
                                @endif
                            </div>
                        @endforelse
                    </div>

                    {{-- Dizer que a lista está cortada. Sem isto, quem não
                         encontrasse o artigo concluía que ele não existe. --}}
                    @if($products->count() >= 50)
                        <p class="text-xs text-gray-500 text-center mt-2">
                            <i class="fas fa-circle-info mr-1"></i>
                            A mostrar os primeiros 50 artigos — escreva acima para encontrar outro.
                        </p>
                    @endif
                </div>

                <!-- Step 3: Cart -->
                @if(count($adjustItems) > 0)
                <div class="bg-gradient-to-r from-orange-50 to-yellow-50 border-2 border-orange-200 rounded-2xl p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">
                            3
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-orange-900 text-lg">Produtos a Ajustar</h4>
                            <p class="text-sm text-orange-700">{{ count($adjustItems) }} produto(s) pronto(s)</p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        {{-- `wire:key` pelo produto: sem ele o Livewire reutiliza
                             as linhas por POSIÇÃO. --}}
                        @foreach($adjustItems as $index => $item)
                            <div wire:key="linha-{{ $item['product_id'] ?? 'x' . $index }}"
                                 class="flex items-center justify-between p-4 bg-white rounded-xl border-2 border-orange-200 shadow-sm">
                                <div class="flex items-center flex-1">
                                    <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-yellow-600 rounded-xl flex items-center justify-center mr-4">
                                        <i class="fas fa-box text-white text-lg"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-bold text-gray-900">{{ $item['product_name'] }}</p>
                                        <p class="text-sm text-gray-600">📦 {{ $item['product_code'] }}</p>
                                    </div>
                                </div>
                                {{-- Editável no próprio carrinho, como na
                                     transferência. `.blur` e não `.live`: com
                                     validação a cada tecla, escrever "10" dava
                                     erro no "1". --}}
                                <div class="text-right mr-4">
                                    <label class="block text-xs text-gray-600 mb-1" for="qtd-a-{{ $index }}">Quantidade</label>
                                    <input id="qtd-a-{{ $index }}" type="number" min="0.01" step="0.01" inputmode="decimal"
                                           wire:model.blur="adjustItems.{{ $index }}.quantity"
                                           class="w-28 px-3 py-2 text-right text-2xl font-bold text-orange-600 rounded-xl border-2 border-orange-200 focus:border-orange-500 focus:ring-2 focus:ring-orange-200 transition">
                                </div>
                                <button type="button" wire:click="removeProductFromAdjust({{ $index }})"
                                        class="text-red-600 hover:text-white hover:bg-red-600 p-3 rounded-xl transition">
                                    <i class="fas fa-trash-alt text-lg"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Reason -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-comment-alt mr-1 text-yellow-600"></i>Motivo do Ajuste *
                    </label>
                    <textarea wire:model="adjustReason" rows="3"
                              class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition"
                              placeholder="Ex: Inventário, devolução, perda, dano, etc..."></textarea>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 bg-gray-50 px-8 py-6 rounded-b-2xl flex justify-end space-x-4">
            <button type="button" wire:click="$set('showAdjustModal', false)"
                    class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button type="button" wire:click="saveAdjust"
                    class="bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-white px-8 py-3 rounded-xl font-semibold shadow-lg transition">
                <i class="fas fa-save mr-2"></i>Executar Ajuste
            </button>
        </div>
    </div>
</div>
@endif
