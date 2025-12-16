{{-- Modal de Formulário --}}
@if($showModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="$set('showModal', false)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 m-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-door-open text-indigo-600"></i>
                {{ $editingId ? 'Editar Quarto' : 'Novo Quarto' }}
            </h3>
            <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        <i class="fas fa-hashtag mr-1 text-indigo-500"></i>Número *
                    </label>
                    <input wire:model="number" type="text" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm" 
                           placeholder="Ex: 101">
                    @error('number') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        <i class="fas fa-layer-group mr-1 text-purple-500"></i>Andar
                    </label>
                    <input wire:model="floor" type="text" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm" 
                           placeholder="Ex: 1">
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">
                    <i class="fas fa-bed mr-1 text-blue-500"></i>Tipo de Quarto *
                </label>
                <select wire:model="room_type_id" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                    <option value="">Selecione...</option>
                    @foreach($roomTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }} - {{ number_format($type->base_price, 0, ',', '.') }} Kz</option>
                    @endforeach
                </select>
                @error('room_type_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">
                    <i class="fas fa-flag mr-1 text-green-500"></i>Status
                </label>
                <select wire:model="status" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                    @foreach(\App\Models\Hotel\Room::STATUSES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">
                    <i class="fas fa-sticky-note mr-1 text-yellow-500"></i>Notas
                </label>
                <textarea wire:model="notes" rows="2" 
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm" 
                          placeholder="Observações sobre o quarto..."></textarea>
            </div>

            <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-xl">
                <input wire:model="is_active" type="checkbox" 
                       class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label class="text-sm font-medium text-gray-700">Quarto ativo e disponível para reservas</label>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" wire:click="$set('showModal', false)" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium">
                    Cancelar
                </button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="px-6 py-2 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition font-semibold disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">
                        <i class="fas fa-save mr-2"></i>{{ $editingId ? 'Atualizar' : 'Criar' }}
                    </span>
                    <span wire:loading wire:target="save">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif
