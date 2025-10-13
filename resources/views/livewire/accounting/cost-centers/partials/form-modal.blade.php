<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4">
        <div class="px-6 py-4 bg-gradient-to-r from-orange-600 to-red-600 flex justify-between items-center rounded-t-xl">
            <h2 class="text-xl font-bold text-white"><i class="fas fa-sitemap mr-2"></i>{{ $centerId ? 'Editar' : 'Novo' }} Centro de Custo</h2>
            <button wire:click="$set('showModal', false)" class="text-white"><i class="fas fa-times text-2xl"></i></button>
        </div>
        <form wire:submit.prevent="save" class="p-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Código *</label>
                    <input type="text" wire:model="code" class="w-full px-4 py-2 border rounded-lg" placeholder="CC-001">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo *</label>
                    <select wire:model="type" class="w-full px-4 py-2 border rounded-lg">
                        <option value="revenue">Revenue (Receita)</option>
                        <option value="cost">Cost (Custo)</option>
                        <option value="support">Support (Suporte)</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nome *</label>
                    <input type="text" wire:model="name" class="w-full px-4 py-2 border rounded-lg" placeholder="Ex: Departamento Vendas">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Centro Pai (Hierarquia)</label>
                    <select wire:model="parentId" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Nenhum (Nível Superior)</option>
                        @foreach($parentCenters as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->code }} - {{ $parent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                    <textarea wire:model="description" rows="3" class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                <button type="button" wire:click="$set('showModal', false)" class="px-6 py-2 bg-gray-100 rounded-lg">Cancelar</button>
                <button type="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50" class="px-6 py-2 bg-orange-600 text-white rounded-lg">
                    <span wire:loading.remove wire:target="save"><i class="fas fa-save mr-2"></i>Salvar</span>
                    <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin mr-2"></i>Salvando...</span>
                </button>
            </div>
        </form>
    </div>
</div>
