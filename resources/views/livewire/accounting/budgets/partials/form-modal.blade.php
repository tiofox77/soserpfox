<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-6xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 bg-gradient-to-r from-indigo-600 to-blue-600 flex justify-between items-center rounded-t-xl sticky top-0">
            <h2 class="text-xl font-bold text-white"><i class="fas fa-calculator mr-2"></i>{{ $budgetId ? 'Editar' : 'Novo' }} Orçamento</h2>
            <button wire:click="$set('showModal', false)" class="text-white"><i class="fas fa-times text-2xl"></i></button>
        </div>
        <form wire:submit.prevent="save" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nome *</label>
                    <input type="text" wire:model="name" class="w-full px-4 py-2 border rounded-lg" placeholder="Orçamento 2025">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ano *</label>
                    <input type="number" wire:model="year" class="w-full px-4 py-2 border rounded-lg" placeholder="2025">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Conta *</label>
                    <select wire:model="accountId" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Selecione...</option>
                        @foreach($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Centro Custo</label>
                    <select wire:model="costCenterId" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Nenhum</option>
                        @foreach($costCenters as $center)
                        <option value="{{ $center->id }}">{{ $center->code }} - {{ $center->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Grid 12 Meses --}}
            <div class="mb-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-calendar-alt mr-2 text-indigo-600"></i>
                    Valores Mensais (Kz)
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div><label class="block text-sm font-medium mb-1">Janeiro</label><input type="number" wire:model="january" step="0.01" class="w-full px-3 py-2 border rounded-lg"></div>
                    <div><label class="block text-sm font-medium mb-1">Fevereiro</label><input type="number" wire:model="february" step="0.01" class="w-full px-3 py-2 border rounded-lg"></div>
                    <div><label class="block text-sm font-medium mb-1">Março</label><input type="number" wire:model="march" step="0.01" class="w-full px-3 py-2 border rounded-lg"></div>
                    <div><label class="block text-sm font-medium mb-1">Abril</label><input type="number" wire:model="april" step="0.01" class="w-full px-3 py-2 border rounded-lg"></div>
                    <div><label class="block text-sm font-medium mb-1">Maio</label><input type="number" wire:model="may" step="0.01" class="w-full px-3 py-2 border rounded-lg"></div>
                    <div><label class="block text-sm font-medium mb-1">Junho</label><input type="number" wire:model="june" step="0.01" class="w-full px-3 py-2 border rounded-lg"></div>
                    <div><label class="block text-sm font-medium mb-1">Julho</label><input type="number" wire:model="july" step="0.01" class="w-full px-3 py-2 border rounded-lg"></div>
                    <div><label class="block text-sm font-medium mb-1">Agosto</label><input type="number" wire:model="august" step="0.01" class="w-full px-3 py-2 border rounded-lg"></div>
                    <div><label class="block text-sm font-medium mb-1">Setembro</label><input type="number" wire:model="september" step="0.01" class="w-full px-3 py-2 border rounded-lg"></div>
                    <div><label class="block text-sm font-medium mb-1">Outubro</label><input type="number" wire:model="october" step="0.01" class="w-full px-3 py-2 border rounded-lg"></div>
                    <div><label class="block text-sm font-medium mb-1">Novembro</label><input type="number" wire:model="november" step="0.01" class="w-full px-3 py-2 border rounded-lg"></div>
                    <div><label class="block text-sm font-medium mb-1">Dezembro</label><input type="number" wire:model="december" step="0.01" class="w-full px-3 py-2 border rounded-lg"></div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-indigo-50 to-blue-50 rounded-lg p-4 mb-6">
                <div class="flex justify-between items-center">
                    <span class="text-lg font-bold text-gray-900">TOTAL ANUAL:</span>
                    <span class="text-2xl font-bold text-indigo-600">{{ number_format($total ?? 0, 2, ',', '.') }} Kz</span>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" wire:click="$set('showModal', false)" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancelar</button>
                <button type="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    <span wire:loading.remove wire:target="save"><i class="fas fa-save mr-2"></i>Salvar</span>
                    <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin mr-2"></i>Salvando...</span>
                </button>
            </div>
        </form>
    </div>
</div>
