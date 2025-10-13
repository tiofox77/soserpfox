<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4">
        <div class="px-6 py-4 bg-gradient-to-r from-purple-600 to-indigo-600 flex justify-between items-center rounded-t-xl">
            <h2 class="text-xl font-bold text-white"><i class="fas fa-tag mr-2"></i>Nova Tag</h2>
            <button wire:click="$set('showTagModal', false)" class="text-white"><i class="fas fa-times text-2xl"></i></button>
        </div>
        <form wire:submit.prevent="saveTag" class="p-6">
            <div class="space-y-4">
                <div><label class="block text-sm font-medium mb-2">Código *</label><input type="text" wire:model="tagCode" class="w-full px-4 py-2 border rounded-lg" placeholder="PROJ-001"></div>
                <div><label class="block text-sm font-medium mb-2">Nome *</label><input type="text" wire:model="tagName" class="w-full px-4 py-2 border rounded-lg" placeholder="Projeto Alpha"></div>
                <div><label class="block text-sm font-medium mb-2">Descrição</label><textarea wire:model="tagDescription" rows="3" class="w-full px-4 py-2 border rounded-lg"></textarea></div>
            </div>
            <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                <button type="button" wire:click="$set('showTagModal', false)" class="px-6 py-2 bg-gray-100 rounded-lg">Cancelar</button>
                <button type="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50" class="px-6 py-2 bg-purple-600 text-white rounded-lg">
                    <span wire:loading.remove wire:target="saveTag"><i class="fas fa-save mr-2"></i>Salvar</span>
                    <span wire:loading wire:target="saveTag"><i class="fas fa-spinner fa-spin mr-2"></i>Salvando...</span>
                </button>
            </div>
        </form>
    </div>
</div>
