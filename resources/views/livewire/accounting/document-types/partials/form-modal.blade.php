{{-- Modal Formulário --}}
@if($showFormModal)
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showFormModal') }" x-show="show" x-cloak>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        {{-- Overlay --}}
        <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="$wire.closeFormModal()"></div>

        {{-- Modal Content --}}
        <div x-show="show" x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="inline-block w-full max-w-4xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl">
            
            {{-- Header --}}
            <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white">
                        {{ $documentTypeId ? 'Editar Tipo de Documento' : 'Novo Tipo de Documento' }}
                    </h3>
                    <button wire:click="closeFormModal" class="text-white hover:text-gray-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="px-6 py-6 space-y-6 max-h-[70vh] overflow-y-auto">
                {{-- Informações Principais --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Código *</label>
                        <input wire:model="code" type="text" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 @error('code') border-red-500 @enderror">
                        @error('code') <span class="text-xs text-red-600 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Diário</label>
                        <select wire:model="journal_id" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                            <option value="">Selecione um diário...</option>
                            @foreach($journals as $journal)
                                <option value="{{ $journal->id }}">{{ $journal->name }}</option>
                            @endforeach
                        </select>
                        @error('journal_id') <span class="text-xs text-red-600 mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descrição *</label>
                    <input wire:model="description" type="text" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror">
                    @error('description') <span class="text-xs text-red-600 mt-1">{{ $message }}</span> @enderror
                </div>

                {{-- Flags Booleanas --}}
                <div class="border-t pt-6">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Configurações</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <label class="flex items-center space-x-3 p-3 border border-gray-200 rounded-xl hover:bg-gray-50 cursor-pointer">
                            <input wire:model="recapitulativos" type="checkbox" class="rounded text-blue-600 focus:ring-blue-500">
                            <span class="text-sm font-medium text-gray-700">Recapitulativos</span>
                        </label>

                        <label class="flex items-center space-x-3 p-3 border border-gray-200 rounded-xl hover:bg-gray-50 cursor-pointer">
                            <input wire:model="retencao_fonte" type="checkbox" class="rounded text-blue-600 focus:ring-blue-500">
                            <span class="text-sm font-medium text-gray-700">Retenção Fonte</span>
                        </label>

                        <label class="flex items-center space-x-3 p-3 border border-gray-200 rounded-xl hover:bg-gray-50 cursor-pointer">
                            <input wire:model="bal_financeira" type="checkbox" class="rounded text-blue-600 focus:ring-blue-500">
                            <span class="text-sm font-medium text-gray-700">Bal. Financeira</span>
                        </label>

                        <label class="flex items-center space-x-3 p-3 border border-gray-200 rounded-xl hover:bg-gray-50 cursor-pointer">
                            <input wire:model="bal_analitica" type="checkbox" class="rounded text-blue-600 focus:ring-blue-500">
                            <span class="text-sm font-medium text-gray-700">Bal. Analítica</span>
                        </label>
                    </div>
                </div>

                {{-- Campos Numéricos --}}
                <div class="border-t pt-6">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Campos Numéricos</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Rec. Informação</label>
                            <input wire:model="rec_informacao" type="number" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo Doc. Imo.</label>
                            <input wire:model="tipo_doc_imo" type="number" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cálculo Fluxo Caixa</label>
                            <input wire:model="calculo_fluxo_caixa" type="number" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                {{-- Status e Ordem --}}
                <div class="border-t pt-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <label class="flex items-center space-x-3">
                            <input wire:model="is_active" type="checkbox" class="rounded text-green-600 focus:ring-green-500">
                            <span class="text-sm font-medium text-gray-700">Ativo</span>
                        </label>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Ordem de Exibição</label>
                            <input wire:model="display_order" type="number" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
                <button wire:click="closeFormModal" 
                    class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-xl transition-colors">
                    Cancelar
                </button>
                <button wire:click="save" wire:loading.attr="disabled"
                    class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">Salvar</span>
                    <span wire:loading wire:target="save">Salvando...</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif
