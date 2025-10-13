<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4">
        <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-emerald-600 flex justify-between items-center rounded-t-xl">
            <h2 class="text-xl font-bold text-white"><i class="fas fa-coins mr-2"></i>{{ $currencyId ? 'Editar' : 'Nova' }} Moeda</h2>
            <button wire:click="$set('showCurrencyModal', false)" class="text-white"><i class="fas fa-times text-2xl"></i></button>
        </div>
        <form wire:submit.prevent="saveCurrency" class="p-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Código (ISO) *</label>
                    <input type="text" wire:model="code" class="w-full px-4 py-2 border rounded-lg" placeholder="USD" maxlength="3">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nome *</label>
                    <input type="text" wire:model="name" class="w-full px-4 py-2 border rounded-lg" placeholder="Dólar Americano">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Símbolo *</label>
                    <input type="text" wire:model="symbol" class="w-full px-4 py-2 border rounded-lg" placeholder="$">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                <button type="button" wire:click="$set('showCurrencyModal', false)" class="px-6 py-2 bg-gray-100 rounded-lg">Cancelar</button>
                <button type="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50" class="px-6 py-2 bg-green-600 text-white rounded-lg">
                    <span wire:loading.remove wire:target="saveCurrency"><i class="fas fa-save mr-2"></i>Salvar</span>
                    <span wire:loading wire:target="saveCurrency"><i class="fas fa-spinner fa-spin mr-2"></i>Salvando...</span>
                </button>
            </div>
        </form>
    </div>
</div>
