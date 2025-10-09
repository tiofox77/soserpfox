<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mr-4">
                    <i class="fas fa-tags text-3xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-bold">Tipos de Eventos</h2>
                    <p class="text-blue-100 text-sm mt-1">Gerencie os tipos personalizados de eventos</p>
                </div>
            </div>
            <button wire:click="create" 
                    class="bg-white text-blue-600 px-6 py-3 rounded-xl font-bold hover:bg-blue-50 transition shadow-lg">
                <i class="fas fa-plus mr-2"></i>Novo Tipo
            </button>
        </div>
    </div>

    {{-- Lista de Tipos --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($types as $type)
        <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition p-5 border-2" 
             style="border-color: {{ $type->color }}20">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center text-2xl"
                         style="background-color: {{ $type->color }}20; border: 2px solid {{ $type->color }}">
                        {{ $type->icon }}
                    </div>
                    <div>
                        <h3 class="font-bold text-lg" style="color: {{ $type->color }}">
                            {{ $type->name }}
                        </h3>
                        <p class="text-xs text-gray-500">Ordem: {{ $type->order }}</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    {{-- Toggle Status --}}
                    <button wire:click="toggleStatus({{ $type->id }})"
                            class="p-2 rounded-lg hover:bg-gray-100 transition"
                            title="{{ $type->is_active ? 'Desativar' : 'Ativar' }}">
                        @if($type->is_active)
                            <i class="fas fa-toggle-on text-green-500 text-xl"></i>
                        @else
                            <i class="fas fa-toggle-off text-gray-400 text-xl"></i>
                        @endif
                    </button>
                    
                    {{-- Editar --}}
                    <button wire:click="edit({{ $type->id }})"
                            class="p-2 rounded-lg hover:bg-blue-100 text-blue-600 transition">
                        <i class="fas fa-edit"></i>
                    </button>
                    
                    {{-- Deletar --}}
                    <button wire:click="delete({{ $type->id }})"
                            onclick="return confirm('Tem certeza que deseja excluir este tipo?')"
                            class="p-2 rounded-lg hover:bg-red-100 text-red-600 transition">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            
            @if($type->description)
            <p class="text-sm text-gray-600 mt-2">{{ $type->description }}</p>
            @endif
            
            <div class="mt-3 pt-3 border-t flex items-center justify-between text-xs text-gray-500">
                <span>
                    <i class="fas fa-calendar-check mr-1"></i>
                    {{ $type->events_count ?? 0 }} evento(s)
                </span>
                <span class="px-2 py-1 rounded-full {{ $type->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $type->is_active ? 'Ativo' : 'Inativo' }}
                </span>
            </div>
        </div>
        @empty
        <div class="col-span-3 bg-gray-50 rounded-xl p-12 text-center">
            <i class="fas fa-tags text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-500 text-lg">Nenhum tipo de evento cadastrado</p>
            <button wire:click="create" 
                    class="mt-4 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i>Criar Primeiro Tipo
            </button>
        </div>
        @endforelse
    </div>

    {{-- Modal Criar/Editar --}}
    @if($showModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" @click.stop>
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-{{ $editingId ? 'edit' : 'plus' }} mr-2"></i>
                    {{ $editingId ? 'Editar' : 'Novo' }} Tipo de Evento
                </h3>
                <button wire:click="closeModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                {{-- Nome --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nome do Tipo *</label>
                    <input type="text" wire:model="name" 
                           class="w-full px-4 py-2 border-2 @error('name') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Ex: Festa Corporativa">
                    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                
                {{-- Ícone e Cor --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Ícone *</label>
                        <select wire:model="icon" 
                                class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="🏢">🏢 Corporativo</option>
                            <option value="💍">💍 Casamento</option>
                            <option value="🎤">🎤 Conferência</option>
                            <option value="🎸">🎸 Show</option>
                            <option value="📹">📹 Streaming</option>
                            <option value="🎉">🎉 Festa</option>
                            <option value="🎓">🎓 Formatura</option>
                            <option value="🎂">🎂 Aniversário</option>
                            <option value="🎭">🎭 Teatro</option>
                            <option value="🏆">🏆 Premiação</option>
                            <option value="📚">📚 Workshop</option>
                            <option value="🎨">🎨 Cultural</option>
                            <option value="⚽">⚽ Esportivo</option>
                            <option value="🎪">🎪 Festival</option>
                            <option value="📌">📌 Outros</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Cor *</label>
                        <input type="color" wire:model="color" 
                               class="w-full h-[42px] border-2 border-gray-300 rounded-lg cursor-pointer">
                    </div>
                </div>
                
                {{-- Ordem --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Ordem de Exibição</label>
                    <input type="number" wire:model="order" min="0"
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Menor número aparece primeiro</p>
                </div>
                
                {{-- Descrição --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Descrição</label>
                    <textarea wire:model="description" rows="3"
                              class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Descrição opcional do tipo de evento"></textarea>
                </div>
                
                {{-- Status --}}
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                    <input type="checkbox" wire:model="is_active" id="is_active" 
                           class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                    <label for="is_active" class="font-semibold text-gray-700">Tipo ativo</label>
                </div>
                
                {{-- Preview --}}
                <div class="bg-gray-50 rounded-lg p-4 flex items-center gap-3">
                    <div class="w-14 h-14 rounded-lg flex items-center justify-center text-2xl" 
                         style="background-color: {{ $color }}20; border: 2px solid {{ $color }}">
                        {{ $icon }}
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 font-semibold">Preview:</p>
                        <p class="font-bold text-lg" style="color: {{ $color }}">
                            {{ $icon }} {{ $name ?: 'Nome do tipo' }}
                        </p>
                    </div>
                </div>
                
                {{-- Botões --}}
                <div class="flex gap-3 pt-4">
                    <button wire:click="save" 
                            wire:loading.attr="disabled"
                            class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition">
                        <span wire:loading.remove wire:target="save">
                            <i class="fas fa-save mr-2"></i>{{ $editingId ? 'Atualizar' : 'Salvar' }}
                        </span>
                        <span wire:loading wire:target="save">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                        </span>
                    </button>
                    <button wire:click="closeModal" 
                            class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
