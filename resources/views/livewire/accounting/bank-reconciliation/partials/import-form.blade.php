{{-- Import Form --}}
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
        <i class="fas fa-file-upload mr-2 text-blue-600"></i>
        Importar Extrato Bancário
    </h2>
    
    <form wire:submit.prevent="importFile" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Conta Bancária</label>
            <select wire:model="accountId" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">Selecione...</option>
                @foreach($bankAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                @endforeach
            </select>
            @error('accountId') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Arquivo</label>
            <select wire:model="fileType" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="csv">CSV</option>
                <option value="mt940">MT940</option>
                <option value="ofx">OFX</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Arquivo</label>
            <input type="file" wire:model="file" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            @error('file') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-end">
            <button type="submit" 
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50"
                    class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                <span wire:loading.remove wire:target="importFile">
                    <i class="fas fa-upload mr-2"></i>Importar
                </span>
                <span wire:loading wire:target="importFile">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Importando...
                </span>
            </button>
        </div>
    </form>
</div>
