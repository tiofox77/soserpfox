<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-hidden"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-file-invoice text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">
                        Novo Lançamento Contabilístico
                    </h3>
                    <p class="text-green-100 text-sm">Partidas dobradas - Débito = Crédito</p>
                </div>
            </div>
            <button wire:click="closeModal" 
                    class="text-white hover:text-green-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <form wire:submit.prevent="save" class="overflow-y-auto max-h-[calc(90vh-180px)]">
            
            {{-- Header Info --}}
            <div class="p-6 bg-gray-50 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-book mr-1 text-green-600"></i>Diário *
                        </label>
                        <select wire:model="journal_id" 
                                class="w-full px-4 py-2.5 border @error('journal_id') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-green-500"
                                required>
                            <option value="">Selecione...</option>
                            @foreach($journals as $journal)
                                <option value="{{ $journal->id }}">{{ $journal->name }}</option>
                            @endforeach
                        </select>
                        @error('journal_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-1 text-green-600"></i>Período *
                        </label>
                        <select wire:model="period_id" 
                                class="w-full px-4 py-2.5 border @error('period_id') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-green-500"
                                required>
                            <option value="">Selecione...</option>
                            @foreach($periods as $period)
                                <option value="{{ $period->id }}">{{ $period->name }}</option>
                            @endforeach
                        </select>
                        @error('period_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar-day mr-1 text-green-600"></i>Data *
                        </label>
                        <input type="date" wire:model="date" 
                               class="w-full px-4 py-2.5 border @error('date') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-green-500"
                               required>
                        @error('date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-hashtag mr-1 text-green-600"></i>Referência *
                        </label>
                        <input type="text" wire:model="ref" 
                               class="w-full px-4 py-2.5 border @error('ref') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-green-500"
                               placeholder="Ex: LC-001"
                               required>
                        @error('ref') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-align-left mr-1 text-green-600"></i>Descrição
                    </label>
                    <textarea wire:model="narration" 
                              rows="2"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500"
                              placeholder="Descrição do lançamento..."></textarea>
                </div>
            </div>

            {{-- Lines --}}
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-bold text-gray-900 flex items-center">
                        <i class="fas fa-list mr-2 text-green-600"></i>
                        Linhas do Lançamento
                    </h4>
                    <button type="button" wire:click="addLine" 
                            class="px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition text-sm font-semibold">
                        <i class="fas fa-plus mr-1"></i>Adicionar Linha
                    </button>
                </div>

                <div class="space-y-3">
                    @foreach($lines as $index => $line)
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <div class="grid grid-cols-12 gap-3 items-start">
                            <div class="col-span-5">
                                <label class="block text-xs font-bold text-gray-700 mb-1">Conta *</label>
                                <select wire:model="lines.{{ $index }}.account_id" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 text-sm">
                                    <option value="">Selecione...</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-span-3">
                                <label class="block text-xs font-bold text-green-700 mb-1">Débito (Kz)</label>
                                <input type="number" wire:model="lines.{{ $index }}.debit" 
                                       step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 text-sm text-right">
                            </div>

                            <div class="col-span-3">
                                <label class="block text-xs font-bold text-red-700 mb-1">Crédito (Kz)</label>
                                <input type="number" wire:model="lines.{{ $index }}.credit" 
                                       step="0.01" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 text-sm text-right">
                            </div>

                            <div class="col-span-1 flex items-end">
                                @if(count($lines) > 2)
                                <button type="button" wire:click="removeLine({{ $index }})" 
                                        class="w-full py-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @endif
                            </div>

                            <div class="col-span-11">
                                <label class="block text-xs font-bold text-gray-700 mb-1">Observação</label>
                                <input type="text" wire:model="lines.{{ $index }}.narration" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 text-sm"
                                       placeholder="Observação desta linha...">
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Totals --}}
                <div class="mt-4 bg-blue-50 rounded-xl p-4 border-2 border-blue-200">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Total Débito</p>
                            <p class="text-2xl font-bold text-green-600">
                                {{ number_format(collect($lines)->sum('debit'), 2, ',', '.') }} Kz
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Total Crédito</p>
                            <p class="text-2xl font-bold text-red-600">
                                {{ number_format(collect($lines)->sum('credit'), 2, ',', '.') }} Kz
                            </p>
                        </div>
                    </div>
                    
                    @php
                        $diff = collect($lines)->sum('debit') - collect($lines)->sum('credit');
                    @endphp
                    
                    <div class="mt-3 pt-3 border-t border-blue-300 flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">Diferença:</span>
                        <span class="text-xl font-bold {{ abs($diff) < 0.01 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($diff, 2, ',', '.') }} Kz
                        </span>
                    </div>
                    
                    @if(abs($diff) < 0.01)
                        <div class="mt-2 flex items-center justify-center text-green-600">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span class="text-sm font-semibold">Lançamento balanceado!</span>
                        </div>
                    @else
                        <div class="mt-2 flex items-center justify-center text-red-600">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span class="text-sm font-semibold">Atenção: Débito e Crédito devem ser iguais!</span>
                        </div>
                    @endif
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
                    class="px-6 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:shadow-lg transition font-semibold">
                <span wire:loading.remove wire:target="save">
                    <i class="fas fa-save mr-2"></i>Guardar Lançamento
                </span>
                <span wire:loading wire:target="save">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                </span>
            </button>
        </div>
    </div>
</div>
