{{-- Modal de Criação Rápida de Cliente (POS) --}}
@if($showQuickClientModal)
<div class="fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-user-plus"></i> Novo Cliente
            </h3>
            <button wire:click="closeQuickClientModal" type="button"
                    class="text-white hover:text-gray-200">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <form wire:submit.prevent="quickCreateClient" class="p-6 space-y-4">
            {{-- Nome --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Nome <span class="text-red-500">*</span>
                </label>
                <input type="text" wire:model.defer="quickClientName" autofocus
                       placeholder="Ex: João Manuel Cliente"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                @error('quickClientName')
                    <p class="text-xs text-red-600 mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                @enderror
            </div>

            {{-- NIF --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">NIF</label>
                <input type="text" wire:model.defer="quickClientNif"
                       placeholder="Ex: 005123456 (deixe vazio para 'Consumidor Final')"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                @error('quickClientNif')
                    <p class="text-xs text-red-600 mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Telefone --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Telefone</label>
                    <input type="text" wire:model.defer="quickClientPhone"
                           placeholder="+244 9..."
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                    @error('quickClientPhone')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                    <input type="email" wire:model.defer="quickClientEmail"
                           placeholder="email@exemplo.com"
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                    @error('quickClientEmail')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-800">
                <i class="fas fa-info-circle mr-1"></i>
                Apenas o <strong>nome</strong> é obrigatório. Pode completar o perfil mais tarde em Faturação → Clientes.
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" wire:click="closeQuickClientModal"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl font-semibold transition">
                    Cancelar
                </button>
                <button type="submit"
                        wire:loading.attr="disabled" wire:target="quickCreateClient"
                        class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold transition disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="quickCreateClient">
                        <i class="fas fa-check mr-1"></i> Criar e Seleccionar
                    </span>
                    <span wire:loading wire:target="quickCreateClient">
                        <i class="fas fa-spinner fa-spin mr-1"></i> A criar...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif
