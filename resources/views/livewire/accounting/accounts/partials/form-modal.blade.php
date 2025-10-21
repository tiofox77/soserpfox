<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-data="{ activeTab: 'general' }"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-emerald-600 to-green-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-sitemap text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">
                        {{ $editMode ? 'Editar Conta' : 'Nova Conta' }}
                    </h3>
                    <p class="text-green-100 text-sm">Preencha os dados da conta contabilística</p>
                </div>
            </div>
            <button wire:click="closeModal" 
                    class="text-white hover:text-green-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Tabs Navigation --}}
        <div class="bg-gray-50 border-b border-gray-200 px-6">
            <div class="flex space-x-1 -mb-px">
                <button @click="activeTab = 'general'" 
                        :class="activeTab === 'general' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-info-circle mr-2"></i>Dados Gerais
                </button>
                <button @click="activeTab = 'advanced'" 
                        :class="activeTab === 'advanced' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-sliders-h mr-2"></i>Avançado
                </button>
                <button @click="activeTab = 'settings'" 
                        :class="activeTab === 'settings' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-gray-600 hover:text-gray-800'"
                        class="py-3 px-4 border-b-2 font-semibold text-sm transition-all">
                    <i class="fas fa-cog mr-2"></i>Configurações
                </button>
            </div>
        </div>

        <form wire:submit.prevent="save" class="overflow-y-auto max-h-[calc(90vh-180px)]">
            
            {{-- Tab: Dados Gerais --}}
            <div x-show="activeTab === 'general'" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-hashtag mr-1 text-emerald-600"></i>Código *
                        </label>
                        <input type="text" wire:model="code" 
                               class="w-full px-4 py-2.5 border @error('code') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                               placeholder="Ex: 11"
                               required>
                        @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-tag mr-1 text-emerald-600"></i>Nome *
                        </label>
                        <input type="text" wire:model="name" 
                               class="w-full px-4 py-2.5 border @error('name') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                               placeholder="Nome da conta"
                               required>
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-layer-group mr-1 text-emerald-600"></i>Tipo de Conta *
                        </label>
                        <select wire:model="type" 
                                class="w-full px-4 py-2.5 border @error('type') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                                required>
                            <option value="">Selecione...</option>
                            <option value="asset">Ativo</option>
                            <option value="liability">Passivo</option>
                            <option value="equity">Capital Próprio</option>
                            <option value="revenue">Receitas</option>
                            <option value="expense">Gastos</option>
                        </select>
                        @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-balance-scale mr-1 text-emerald-600"></i>Natureza *
                        </label>
                        <select wire:model="nature" 
                                class="w-full px-4 py-2.5 border @error('nature') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                                required>
                            <option value="debit">Débito</option>
                            <option value="credit">Crédito</option>
                        </select>
                        @error('nature') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-sitemap mr-1 text-emerald-600"></i>Conta Pai
                        </label>
                        <select wire:model="parent_id" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all">
                            <option value="">Sem conta pai (raiz)</option>
                            @foreach($parentAccounts as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->code }} - {{ $parent->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-layer-group mr-1 text-emerald-600"></i>Nível
                        </label>
                        <input type="number" wire:model="level" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                               min="1" max="10">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-align-left mr-1 text-emerald-600"></i>Descrição
                        </label>
                        <textarea wire:model="description" 
                                  rows="3"
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                                  placeholder="Descrição opcional da conta"></textarea>
                    </div>
                </div>
            </div>

            {{-- Tab: Avançado --}}
            <div x-show="activeTab === 'advanced'" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    {{-- Imposto Padrão (IVA) --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-percent mr-1 text-purple-600"></i>Imposto Padrão (IVA)
                        </label>
                        <select wire:model="default_tax_id" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all">
                            <option value="">Sem imposto padrão</option>
                            @foreach($availableTaxes ?? [] as $tax)
                                <option value="{{ $tax->id }}">{{ $tax->name }} ({{ $tax->rate }}%)</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Imposto aplicado automaticamente nos lançamentos</p>
                    </div>

                    {{-- Centro de Custo Padrão --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-building mr-1 text-indigo-600"></i>Centro de Custo Padrão
                        </label>
                        <select wire:model="default_cost_center_id" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all">
                            <option value="">Sem centro de custo padrão</option>
                            @foreach($availableCostCenters ?? [] as $cc)
                                <option value="{{ $cc->id }}">{{ $cc->code }} - {{ $cc->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Centro de custo associado automaticamente</p>
                    </div>

                    {{-- Reflexão em Débito --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-arrow-right mr-1 text-green-600"></i>Conta Reflexo (Débito)
                        </label>
                        <select wire:model="debit_reflection_account_id" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all">
                            <option value="">Sem reflexo automático</option>
                            @foreach($parentAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Conta de contrapartida em débito</p>
                    </div>

                    {{-- Reflexão em Crédito --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-arrow-left mr-1 text-red-600"></i>Conta Reflexo (Crédito)
                        </label>
                        <select wire:model="credit_reflection_account_id" 
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all">
                            <option value="">Sem reflexo automático</option>
                            @foreach($parentAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Conta de contrapartida em crédito</p>
                    </div>

                    {{-- Chave da Conta --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-key mr-1 text-yellow-600"></i>Chave da Conta
                        </label>
                        <input type="text" wire:model="account_key" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                               placeholder="Ex: KEY001">
                        <p class="text-xs text-gray-500 mt-1">Código/chave adicional para identificação</p>
                    </div>

                    {{-- Subtipo da Conta --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-tags mr-1 text-pink-600"></i>Subtipo/Classificação
                        </label>
                        <input type="text" wire:model="account_subtype" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                               placeholder="Ex: Operacional, Financeiro">
                        <p class="text-xs text-gray-500 mt-1">Classificação adicional da conta</p>
                    </div>

                    {{-- Custo Fixo --}}
                    <div class="md:col-span-2">
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="is_fixed_cost" 
                                       class="w-5 h-5 text-emerald-600 rounded focus:ring-emerald-500">
                                <div class="ml-3">
                                    <span class="text-sm font-bold text-gray-900">Custo Fixo</span>
                                    <p class="text-xs text-gray-600">Marcar se esta conta representa um custo fixo</p>
                                </div>
                            </label>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Tab: Configurações --}}
            <div x-show="activeTab === 'settings'" class="p-6">
                <div class="space-y-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="is_view" 
                                   class="w-5 h-5 text-emerald-600 rounded focus:ring-emerald-500">
                            <div class="ml-3">
                                <span class="text-sm font-bold text-gray-900">Conta de Visualização</span>
                                <p class="text-xs text-gray-600">Esta conta é apenas para agrupamento e não aceita lançamentos diretos</p>
                            </div>
                        </label>
                    </div>

                    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="blocked" 
                                   class="w-5 h-5 text-red-600 rounded focus:ring-red-500">
                            <div class="ml-3">
                                <span class="text-sm font-bold text-gray-900">Conta Bloqueada</span>
                                <p class="text-xs text-gray-600">Impede novos lançamentos nesta conta</p>
                            </div>
                        </label>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                        <h4 class="font-bold text-gray-900 mb-2 flex items-center">
                            <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                            Informações Adicionais
                        </h4>
                        <div class="space-y-2 text-sm text-gray-600">
                            <p><strong>Tipo:</strong> Define a classificação contabilística da conta</p>
                            <p><strong>Natureza:</strong> Define se o saldo aumenta no débito ou crédito</p>
                            <p><strong>Nível:</strong> Indica a profundidade na hierarquia (1 = raiz)</p>
                        </div>
                    </div>
                </div>
            </div>

        </form>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-end space-x-3 border-t border-gray-200">
            <button wire:click="closeModal" 
                    type="button"
                    class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-100 transition font-semibold">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button wire:click="save" 
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-not-allowed"
                    type="submit"
                    class="px-6 py-2.5 bg-gradient-to-r from-emerald-600 to-green-600 text-white rounded-xl hover:shadow-lg transition font-semibold">
                <span wire:loading.remove wire:target="save">
                    <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar' : 'Guardar' }}
                </span>
                <span wire:loading wire:target="save">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                </span>
            </button>
        </div>
    </div>
</div>
