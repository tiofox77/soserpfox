{{-- Modal Atribuição --}}
@if($showAssignModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="$set('showAssignModal', false)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 m-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-user-plus text-purple-600"></i>
                Atribuir Tarefa
            </h3>
            <button wire:click="$set('showAssignModal', false)" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-bold text-gray-700 mb-2">Funcionário</label>
            <select wire:model="assignToUser" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 text-sm">
                <option value="">Selecione funcionário...</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        
        <div class="flex gap-2">
            <button wire:click="$set('showAssignModal', false)" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium">
                Cancelar
            </button>
            <button wire:click="assignTask" wire:loading.attr="disabled"
                    class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition font-semibold disabled:opacity-50">
                <span wire:loading.remove wire:target="assignTask">
                    <i class="fas fa-check mr-2"></i>Atribuir
                </span>
                <span wire:loading wire:target="assignTask">
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                </span>
            </button>
        </div>
    </div>
</div>
@endif
