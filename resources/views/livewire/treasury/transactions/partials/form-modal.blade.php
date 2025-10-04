<!-- Modal Criar/Editar Transação -->
@if($showModal)
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeModal"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
            <!-- Header -->
            <div class="bg-gradient-to-r from-teal-600 to-cyan-600 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-exchange-alt mr-3"></i>
                        {{ $editMode ? 'Editar Transação' : 'Nova Transação' }}
                    </h3>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <form wire:submit.prevent="save">
                <div class="px-6 py-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Tipo -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-list mr-1 text-teal-600"></i>Tipo*
                            </label>
                            <select wire:model="form.type" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                <option value="">Selecione o tipo</option>
                                <option value="income">Entrada</option>
                                <option value="expense">Saída</option>
                                <option value="transfer">Transferência</option>
                            </select>
                            @error('form.type') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Categoria -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag mr-1 text-teal-600"></i>Categoria
                            </label>
                            <select wire:model="form.category" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                <option value="">Selecione a categoria</option>
                                <option value="sale">Venda</option>
                                <option value="purchase">Compra</option>
                                <option value="salary">Salário</option>
                                <option value="rent">Aluguel</option>
                                <option value="utilities">Utilidades</option>
                                <option value="other">Outro</option>
                            </select>
                            @error('form.category') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Valor -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-dollar-sign mr-1 text-teal-600"></i>Valor*
                            </label>
                            <input type="number" step="0.01" wire:model="form.amount" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200"
                                   placeholder="0.00">
                            @error('form.amount') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Moeda -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-coins mr-1 text-teal-600"></i>Moeda*
                            </label>
                            <select wire:model="form.currency" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                <option value="AOA">AOA</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                            @error('form.currency') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Data -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar mr-1 text-teal-600"></i>Data da Transação*
                            </label>
                            <input type="date" wire:model="form.transaction_date" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                            @error('form.transaction_date') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Método de Pagamento -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-credit-card mr-1 text-teal-600"></i>Método de Pagamento*
                            </label>
                            <select wire:model="form.payment_method_id" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                <option value="">Selecione</option>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->id }}">{{ $method->name }}</option>
                                @endforeach
                            </select>
                            @error('form.payment_method_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Conta/Caixa -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-wallet mr-1 text-teal-600"></i>Conta Bancária
                            </label>
                            <select wire:model="form.account_id" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                <option value="">Selecione</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->account_name }}</option>
                                @endforeach
                            </select>
                            @error('form.account_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-cash-register mr-1 text-teal-600"></i>Caixa
                            </label>
                            <select wire:model="form.cash_register_id" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                <option value="">Selecione</option>
                                @foreach($cashRegisters as $register)
                                    <option value="{{ $register->id }}">{{ $register->name }}</option>
                                @endforeach
                            </select>
                            @error('form.cash_register_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Referência -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-hashtag mr-1 text-teal-600"></i>Referência
                            </label>
                            <input type="text" wire:model="form.reference" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200"
                                   placeholder="REF-001">
                            @error('form.reference') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-info-circle mr-1 text-teal-600"></i>Status*
                            </label>
                            <select wire:model="form.status" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                                <option value="pending">Pendente</option>
                                <option value="completed">Concluído</option>
                                <option value="cancelled">Cancelado</option>
                            </select>
                            @error('form.status') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Descrição -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-align-left mr-1 text-teal-600"></i>Descrição*
                            </label>
                            <textarea wire:model="form.description" rows="2"
                                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200"
                                      placeholder="Descrição da transação"></textarea>
                            @error('form.description') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Notas -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-sticky-note mr-1 text-teal-600"></i>Notas
                            </label>
                            <textarea wire:model="form.notes" rows="2"
                                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200"
                                      placeholder="Observações adicionais"></textarea>
                            @error('form.notes') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
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
                            class="px-6 py-3 bg-gradient-to-r from-teal-600 to-cyan-600 text-white rounded-xl font-semibold hover:from-teal-700 hover:to-cyan-700 transition shadow-lg">
                        <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar' : 'Criar' }} Transação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
