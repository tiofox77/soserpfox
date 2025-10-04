<!-- Modal Criar/Editar Conta Bancária -->
@if($showModal)
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeModal"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
            <!-- Header -->
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-wallet mr-3"></i>
                        {{ $editMode ? 'Editar Conta Bancária' : 'Nova Conta Bancária' }}
                    </h3>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <form wire:submit.prevent="save">
                <div class="px-6 py-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-6">
                        <!-- Banco -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-university mr-1 text-purple-600"></i>Banco*
                            </label>
                            <select wire:model="form.bank_id" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                <option value="">Selecione um banco</option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                @endforeach
                            </select>
                            @error('form.bank_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Nome da Conta -->
                        <div class="md:col-span-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag mr-1 text-purple-600"></i>Nome da Conta*
                            </label>
                            <input type="text" wire:model="form.account_name" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="Ex: Conta Corrente Empresa">
                            @error('form.account_name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Número da Conta -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-hashtag mr-1 text-purple-600"></i>Número da Conta*
                            </label>
                            <input type="text" wire:model="form.account_number" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="000000000">
                            @error('form.account_number') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- IBAN -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-barcode mr-1 text-purple-600"></i>IBAN
                            </label>
                            <input type="text" wire:model="form.iban" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="AO06000000000000000000000">
                            @error('form.iban') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Moeda -->
                        <div class="md:col-span-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-dollar-sign mr-1 text-purple-600"></i>Moeda*
                            </label>
                            <select wire:model="form.currency" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                <option value="AOA">AOA</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                            @error('form.currency') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Tipo de Conta -->
                        <div class="md:col-span-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-list mr-1 text-purple-600"></i>Tipo*
                            </label>
                            <select wire:model="form.account_type" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200">
                                <option value="checking">Corrente</option>
                                <option value="savings">Poupança</option>
                                <option value="investment">Investimento</option>
                            </select>
                            @error('form.account_type') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Saldo Inicial -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-money-bill mr-1 text-purple-600"></i>Saldo Inicial
                            </label>
                            <input type="number" step="0.01" wire:model="form.initial_balance" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="0.00">
                            @error('form.initial_balance') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Gestor da Conta -->
                        <div class="md:col-span-6">
                            <h4 class="text-md font-bold text-gray-700 mb-3 flex items-center">
                                <i class="fas fa-user-tie mr-2 text-purple-600"></i>Informações do Gestor
                            </h4>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Nome do Gestor</label>
                            <input type="text" wire:model="form.manager_name" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="Nome completo">
                            @error('form.manager_name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Telefone</label>
                            <input type="text" wire:model="form.manager_phone" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="+244 900 000 000">
                            @error('form.manager_phone') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                            <input type="email" wire:model="form.manager_email" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                   placeholder="gestor@empresa.ao">
                            @error('form.manager_email') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Notas -->
                        <div class="md:col-span-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-sticky-note mr-1 text-purple-600"></i>Notas
                            </label>
                            <textarea wire:model="form.notes" rows="2"
                                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                      placeholder="Observações sobre a conta"></textarea>
                            @error('form.notes') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Checkboxes -->
                        <div class="md:col-span-6 space-y-3">
                            <div class="flex items-center space-x-6">
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="form.is_active" class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                                    <span class="ml-2 text-sm text-gray-700">Conta Ativa</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="form.is_default" class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                                    <span class="ml-2 text-sm text-gray-700">Conta Padrão</span>
                                </label>
                            </div>
                            
                            <!-- Exibir na Fatura -->
                            <div class="p-4 bg-blue-50 border-2 border-blue-200 rounded-xl">
                                <label class="flex items-start">
                                    <input type="checkbox" wire:model="form.show_on_invoice" class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500 mt-0.5">
                                    <div class="ml-3 flex-1">
                                        <span class="text-sm font-semibold text-blue-900">Exibir na Fatura/Proforma</span>
                                        <p class="text-xs text-blue-700 mt-1">Os dados desta conta aparecerão nas faturas/proformas geradas (máximo 4 contas)</p>
                                    </div>
                                </label>
                                
                                @if($form['show_on_invoice'])
                                    <div class="mt-3">
                                        <label class="block text-sm font-semibold text-blue-900 mb-2">
                                            Ordem de Exibição (1-4)
                                        </label>
                                        <input type="number" wire:model="form.invoice_display_order" min="1" max="4"
                                               class="w-24 px-3 py-2 border-2 border-blue-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                               placeholder="1">
                                        <span class="text-xs text-blue-600 ml-2">Deixe vazio para usar ordem automática</span>
                                    </div>
                                @endif
                                
                                @error('form.show_on_invoice') 
                                    <div class="mt-2 p-2 bg-red-100 border border-red-300 rounded text-red-700 text-xs">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
                    <button type="button" wire:click="closeModal"
                            class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-100 transition">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-indigo-700 transition shadow-lg">
                        <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar' : 'Criar' }} Conta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
