<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4">
        <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-cyan-600 flex justify-between items-center rounded-t-xl">
            <h2 class="text-xl font-bold text-white"><i class="fas fa-exchange-alt mr-2"></i>Nova Taxa Câmbio</h2>
            <button wire:click="$set('showRateModal', false)" class="text-white"><i class="fas fa-times text-2xl"></i></button>
        </div>
        <form wire:submit.prevent="saveRate" class="p-6">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">De *</label>
                        <select wire:model="currencyFromId" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">Selecione...</option>
                            @foreach($currencies as $curr)
                            <option value="{{ $curr->id }}">{{ $curr->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Para *</label>
                        <select wire:model="currencyToId" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">Selecione...</option>
                            @foreach($currencies as $curr)
                            <option value="{{ $curr->id }}">{{ $curr->code }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Data *</label>
                    <input type="date" wire:model="rateDate" class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Taxa *</label>
                    <input type="number" wire:model="rate" step="0.0001" class="w-full px-4 py-2 border rounded-lg" placeholder="1.2500">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                <button type="button" wire:click="$set('showRateModal', false)" class="px-6 py-2 bg-gray-100 rounded-lg">Cancelar</button>
                <button type="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50" class="px-6 py-2 bg-blue-600 text-white rounded-lg">
                    <span wire:loading.remove wire:target="saveRate"><i class="fas fa-save mr-2"></i>Salvar</span>
                    <span wire:loading wire:target="saveRate"><i class="fas fa-spinner fa-spin mr-2"></i>Salvando...</span>
                </button>
            </div>
        </form>
    </div>
</div>
