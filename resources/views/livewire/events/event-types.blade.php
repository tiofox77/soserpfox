<div class="p-4 sm:p-6">
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-tags text-blue-600 mr-3"></i>
                Tipos de Eventos
            </h2>
            <p class="text-sm sm:text-base text-gray-600">Gerencie categorias personalizadas</p>
        </div>
        <div class="flex items-center space-x-2 sm:space-x-3">
            <button wire:click="create"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-70 scale-95"
                    class="group bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-5 py-2.5 rounded-lg font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="create">
                    <i class="fas fa-plus-circle mr-2 group-hover:rotate-90 transition-transform duration-300"></i>
                    Novo Tipo
                </span>
                <span wire:loading wire:target="create">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Abrindo...
                </span>
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-4 sm:p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-xs sm:text-sm font-medium">Total</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $types->count() }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full">
                    <i class="fas fa-tags text-xl sm:text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-4 sm:p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-xs sm:text-sm font-medium">Ativos</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $types->where('is_active', true)->count() }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full">
                    <i class="fas fa-check-circle text-xl sm:text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-4 sm:p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-xs sm:text-sm font-medium">Em Uso</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $types->sum('events_count') }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full">
                    <i class="fas fa-calendar-alt text-xl sm:text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-lg p-4 sm:p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 text-xs sm:text-sm font-medium">Personalizados</p>
                    <p class="text-2xl sm:text-3xl font-bold mt-1">{{ $types->count() }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full">
                    <i class="fas fa-palette text-xl sm:text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Grid de Tipos --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($types as $type)
        <div class="group bg-white rounded-xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden border-2 border-gray-100 hover:border-{{ substr($type->color, 1) }}">
            
            {{-- Área do Ícone --}}
            <div class="relative h-32 flex items-center justify-center overflow-hidden"
                 style="background: linear-gradient(135deg, {{ $type->color }}20 0%, {{ $type->color }}40 100%);">
                <div class="w-20 h-20 rounded-full flex items-center justify-center text-5xl transform group-hover:scale-110 transition-transform duration-300"
                     style="background-color: {{ $type->color }}30; border: 3px solid {{ $type->color }}">
                    {{ $type->icon }}
                </div>
                
                {{-- Badge Status --}}
                <div class="absolute top-2 right-2">
                    <button wire:click="toggleStatus({{ $type->id }})"
                            wire:loading.attr="disabled"
                            wire:target="toggleStatus({{ $type->id }})"
                            class="px-3 py-1 rounded-full text-xs font-bold text-white shadow-lg transition-all duration-300 disabled:opacity-50 {{ $type->is_active ? 'bg-green-500 hover:bg-green-600' : 'bg-gray-500 hover:bg-gray-600' }}">
                        <span wire:loading.remove wire:target="toggleStatus({{ $type->id }})">{{ $type->is_active ? '✓ Ativo' : 'Inativo' }}</span>
                        <span wire:loading wire:target="toggleStatus({{ $type->id }})"><i class="fas fa-spinner fa-spin"></i></span>
                    </button>
                </div>
                
                {{-- Badge Ordem --}}
                <div class="absolute bottom-2 left-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-white/90 text-gray-700 shadow">
                        #{{ $type->order }}
                    </span>
                </div>
            </div>

            {{-- Conteúdo --}}
            <div class="p-4">
                <h3 class="font-bold text-lg text-gray-900 mb-2 group-hover:text-blue-700 transition"
                    style="color: {{ $type->color }}">
                    {{ $type->name }}
                </h3>
                
                @if($type->description)
                <p class="text-sm text-gray-600 mb-3 line-clamp-2">{{ $type->description }}</p>
                @endif
                
                {{-- Contador de Eventos --}}
                <div class="flex items-center text-sm text-gray-600 mb-4">
                    <i class="fas fa-calendar-check w-5 text-blue-600"></i>
                    <span class="font-semibold">{{ $type->events_count ?? 0 }}</span>
                    <span class="ml-1">evento(s) criado(s)</span>
                </div>

                {{-- Ações --}}
                <div class="space-y-2">
                    <div class="flex gap-2">
                        <button wire:click="edit({{ $type->id }})"
                                wire:loading.attr="disabled"
                                wire:target="edit({{ $type->id }})"
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-semibold transition-all duration-300 hover:scale-105 flex items-center justify-center disabled:opacity-50">
                            <span wire:loading.remove wire:target="edit({{ $type->id }})"><i class="fas fa-edit mr-2"></i>Editar</span>
                            <span wire:loading wire:target="edit({{ $type->id }})"><i class="fas fa-spinner fa-spin mr-2"></i>...</span>
                        </button>
                        <button wire:click="delete({{ $type->id }})"
                                wire:loading.attr="disabled"
                                wire:target="delete({{ $type->id }})"
                                wire:confirm="Tem certeza que deseja excluir este tipo?"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg font-semibold transition-all duration-300 hover:scale-105 flex items-center justify-center disabled:opacity-50">
                            <span wire:loading.remove wire:target="delete({{ $type->id }})"><i class="fas fa-trash mr-2"></i>Excluir</span>
                            <span wire:loading wire:target="delete({{ $type->id }})"><i class="fas fa-spinner fa-spin mr-2"></i>...</span>
                        </button>
                    </div>
                </div>
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
                            wire:loading.class="opacity-70 scale-95"
                            class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 hover:scale-105 transition-all duration-300 disabled:cursor-not-allowed">
                        <span wire:loading.remove>
                            <i class="fas fa-save mr-2"></i>{{ $editingId ? 'Atualizar' : 'Salvar' }}
                        </span>
                        <span wire:loading>
                            <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                        </span>
                    </button>
                    <button wire:click="closeModal"
                            class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 hover:scale-105 transition-all duration-300">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
