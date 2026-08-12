<!-- Transfer Modal -->
@if($showTransferModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-6xl w-[calc(100%-1rem)] sm:w-full max-h-[94vh] overflow-y-auto transform transition-all animate-scale-in">
        <!-- Header -->
        <div class="sticky top-0 bg-gradient-to-r from-purple-600 to-indigo-600 px-4 sm:px-8 py-4 sm:py-6 rounded-t-2xl flex items-center justify-between z-10">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-exchange-alt text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-white">Nova Transferência</h3>
                    <p class="text-purple-100 text-sm">Transferir produto entre armazéns</p>
                </div>
            </div>
            <button wire:click="$set('showTransferModal', false)" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-4 sm:p-8">
            <div class="space-y-8">
                <!-- Step 1: Warehouses -->
                <div class="bg-gradient-to-r from-purple-50 to-indigo-50 border-2 border-purple-200 rounded-2xl p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">
                            1
                        </div>
                        <div>
                            <h4 class="font-bold text-purple-900 text-lg">Selecione os Armazéns</h4>
                            <p class="text-sm text-purple-700">Escolha de onde e para onde transferir</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 items-end">
                        <!-- From -->
                        <div class="bg-white rounded-xl p-4 border-2 border-red-200">
                            <label class="block text-sm font-bold text-gray-700 mb-3">
                                <i class="fas fa-warehouse mr-1 text-red-600"></i>
                                <span class="text-red-600">ORIGEM</span> (De)
                            </label>
                            <select wire:model.live="transferFromWarehouse"
                                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition text-base font-semibold">
                                <option value="">📦 Selecionar armazém de origem...</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Arrow -->
                        <div class="hidden md:flex items-center justify-center px-2 pb-2">
                            <i class="fas fa-arrow-right text-purple-600 text-3xl"></i>
                        </div>

                        <!-- To -->
                        <div class="bg-white rounded-xl p-4 border-2 border-green-200">
                            <label class="block text-sm font-bold text-gray-700 mb-3">
                                <i class="fas fa-warehouse mr-1 text-green-600"></i>
                                <span class="text-green-600">DESTINO</span> (Para)
                            </label>
                            <select wire:model="transferToWarehouse"
                                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition text-base font-semibold">
                                <option value="">📦 Selecionar armazém de destino...</option>
                                @foreach($warehouses->where('id', '!=', $transferFromWarehouse) as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Add Products -->
                <div class="bg-gradient-to-r from-blue-50 to-cyan-50 border-2 border-blue-200 rounded-2xl p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">
                            2
                        </div>
                        <div>
                            <h4 class="font-bold text-blue-900 text-lg">Adicione os Produtos</h4>
                            <p class="text-sm text-blue-700">Procure e selecione os produtos para transferir</p>
                        </div>
                    </div>
                    
                    <!-- Search Products -->
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-search mr-2 text-blue-600 text-lg"></i>
                            <span>Procurar Produto</span>
                        </label>
                        <input type="text" wire:model.live.debounce.300ms="productSearch"
                               class="w-full px-5 py-4 rounded-xl border-2 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition text-base"
                               placeholder="🔍 Digite o nome ou código do produto para pesquisar...">
                    </div>

                    <!-- Products Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-96 overflow-y-auto mb-4 p-2">
                        {{-- A pesquisa e o limite são feitos em SQL, no
                             componente. Aqui filtrava-se em PHP a colecção
                             inteira — que era o catálogo todo. --}}
                        @forelse($products as $prod)
                            <div wire:click="selectProductForTransfer({{ $prod->id }})"
                                 class="p-4 border-2 rounded-xl cursor-pointer transition border-gray-200 hover:border-blue-400 hover:shadow-lg bg-white hover:scale-105">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex-1">
                                        <p class="font-bold text-sm text-gray-900">{{ $prod->name }}</p>
                                        <p class="text-xs text-gray-600">{{ $prod->code }}</p>
                                    </div>
                                </div>
                                @if($transferFromWarehouse)
                                    {{-- O stock vem na mesma consulta dos artigos.
                                         Aqui estava um Stock::where()->first() por
                                         cartão: com 5.729 artigos eram 5.729
                                         consultas por render. --}}
                                    @php $qty = (float) ($prod->stock_no_armazem ?? 0); @endphp
                                    <div class="mt-2 pt-2 border-t border-gray-200">
                                        <p class="text-xs text-gray-600">Stock disponível:</p>
                                        <p class="text-sm font-bold {{ $qty > 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ number_format($qty, 2) }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="col-span-3 text-center py-8 text-gray-500">
                                <i class="fas fa-search text-3xl mb-2"></i>
                                @if(!$transferFromWarehouse)
                                    <p>Escolha primeiro o armazém de origem</p>
                                @elseif($productSearch)
                                    <p>Nenhum produto encontrado para "{{ $productSearch }}"</p>
                                @else
                                    <p>Este armazém não tem nenhum artigo com stock</p>
                                @endif
                            </div>
                        @endforelse
                    </div>

                    {{-- Dizer que a lista está cortada. Sem isto, quem não
                         encontrasse o artigo concluía que ele não existe, em vez
                         de o procurar. --}}
                    @if($products->count() >= 50)
                        <p class="text-xs text-gray-500 text-center -mt-2 mb-4">
                            <i class="fas fa-circle-info mr-1"></i>
                            A mostrar os primeiros 50 artigos — escreva acima para encontrar outro.
                        </p>
                    @elseif(!$productSearch && $transferFromWarehouse)
                        <p class="text-xs text-gray-500 text-center -mt-2 mb-4">
                            <i class="fas fa-circle-info mr-1"></i>
                            A mostrar os artigos com stock neste armazém. Escreva acima para procurar qualquer outro.
                        </p>
                    @endif

                </div>

                <!-- Step 3: Review Cart -->
                @if(count($transferItems) > 0)
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-2xl p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-green-600 text-white rounded-full flex items-center justify-center font-bold text-lg mr-3">
                            3
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-green-900 text-lg">Revise os Produtos</h4>
                            <p class="text-sm text-green-700">{{ count($transferItems) }} produto(s) pronto(s) para transferir</p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        {{-- `wire:key` pelo produto: sem ele o Livewire reutiliza
                             as linhas por POSIÇÃO, e ao remover uma do meio o
                             conteúdo escrito podia aparecer noutra. --}}
                        @foreach($transferItems as $index => $item)
                            <div wire:key="linha-{{ $item['product_id'] ?? 'x' . $index }}"
                                 class="flex items-center justify-between p-4 bg-white rounded-xl border-2 border-green-200 shadow-sm hover:shadow-md transition">
                                <div class="flex items-center flex-1">
                                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center mr-4">
                                        <i class="fas fa-box text-white text-lg"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-bold text-gray-900">{{ $item['product_name'] }}</p>
                                        <p class="text-sm text-gray-600">📦 {{ $item['product_code'] }}</p>
                                    </div>
                                </div>
                                {{-- Editável no próprio carrinho. Enganar-se na
                                     quantidade é o erro mais banal deste ecrã, e
                                     a única saída era apagar a linha e voltar a
                                     procurar o artigo no meio de cinco mil.
                                     `.blur` e não `.live`: com validação a cada
                                     tecla, escrever "10" dava erro no "1". --}}
                                <div class="text-right mr-4">
                                    <label class="block text-xs text-gray-600 mb-1" for="qtd-t-{{ $index }}">Quantidade</label>
                                    <input id="qtd-t-{{ $index }}" type="number" min="0.01" step="0.01" inputmode="decimal"
                                           wire:model.blur="transferItems.{{ $index }}.quantity"
                                           class="w-28 px-3 py-2 text-right text-2xl font-bold text-green-600 rounded-xl border-2 border-green-200 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition">
                                </div>
                                <button type="button" wire:click="removeProductFromTransfer({{ $index }})"
                                        class="text-red-600 hover:text-white hover:bg-red-600 p-3 rounded-xl transition">
                                    <i class="fas fa-trash-alt text-lg"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-sticky-note mr-1 text-purple-600"></i>Observações
                    </label>
                    <textarea wire:model="transferNotes" rows="3"
                              class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition"
                              placeholder="Motivo da transferência, condições especiais, etc."></textarea>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 bg-gray-50 px-8 py-6 rounded-b-2xl flex justify-end space-x-4">
            <button type="button" wire:click="$set('showTransferModal', false)"
                    class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button type="button" wire:click="saveTransfer"
                    class="bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white px-8 py-3 rounded-xl font-semibold shadow-lg transition">
                <i class="fas fa-exchange-alt mr-2"></i>Executar Transferência
            </button>
        </div>
    </div>
</div>
@endif
