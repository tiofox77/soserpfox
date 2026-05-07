<div class="p-4 sm:p-6">
    {{-- Submenu --}}
    <div class="mb-6 bg-white rounded-xl shadow-md p-2 flex flex-wrap gap-2">
        <a href="{{ route('events.equipment.dashboard') }}" class="flex items-center px-4 py-2 rounded-lg font-semibold transition text-gray-700 hover:bg-gray-100">
            <i class="fas fa-chart-line mr-2"></i><span class="hidden sm:inline">Dashboard</span>
        </a>
        <a href="{{ route('events.equipment.index') }}" class="flex items-center px-4 py-2 rounded-lg font-semibold transition text-gray-700 hover:bg-gray-100">
            <i class="fas fa-boxes mr-2"></i><span class="hidden sm:inline">Equipamentos</span>
        </a>
        <a href="{{ route('events.equipment.sets') }}" class="flex items-center px-4 py-2 rounded-lg font-semibold transition text-gray-700 hover:bg-gray-100">
            <i class="fas fa-layer-group mr-2"></i><span class="hidden sm:inline">SETS</span>
        </a>
        <a href="{{ route('events.equipment.categories') }}" class="flex items-center px-4 py-2 rounded-lg font-semibold transition bg-purple-600 text-white">
            <i class="fas fa-tags mr-2"></i><span class="hidden sm:inline">Categorias</span>
        </a>
        <a href="{{ route('events.calendar') }}" class="flex items-center px-4 py-2 rounded-lg font-semibold transition text-gray-700 hover:bg-gray-100">
            <i class="fas fa-calendar-alt mr-2"></i><span class="hidden sm:inline">Calendário</span>
        </a>
    </div>

    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-tags text-purple-600 mr-3"></i>
                Categorias de Equipamentos
            </h2>
            <p class="text-sm sm:text-base text-gray-600">Gerencie as categorias dos equipamentos</p>
        </div>
        <button wire:click="openModal"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-70 scale-95"
                class="group bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-5 py-2.5 rounded-lg font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="openModal">
                <i class="fas fa-plus-circle mr-2 group-hover:rotate-90 transition-transform duration-300"></i>Nova Categoria
            </span>
            <span wire:loading wire:target="openModal">
                <i class="fas fa-spinner fa-spin mr-2"></i>Abrindo...
            </span>
        </button>
    </div>

    {{-- Lista de Categorias --}}
    <div class="bg-white rounded-xl shadow-md">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white">
                    <tr>
                        <th class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs sm:text-sm">Ícone</th>
                        <th class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs sm:text-sm">Nome</th>
                        <th class="px-6 py-4 text-left hidden md:table-cell">Cor</th>
                        <th class="px-6 py-4 text-left hidden lg:table-cell">Ordem</th>
                        <th class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs sm:text-sm">Equip.</th>
                        <th class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs sm:text-sm">Status</th>
                        <th class="px-3 sm:px-6 py-3 sm:py-4 text-right text-xs sm:text-sm">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($categories as $cat)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 sm:px-6 py-3 sm:py-4 text-xl sm:text-2xl">{{ $cat->icon ?: '📦' }}</td>
                        <td class="px-3 sm:px-6 py-3 sm:py-4 font-semibold text-gray-900 text-sm sm:text-base">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full flex-shrink-0 md:hidden" style="background-color: {{ $cat->color }}"></div>
                                {{ $cat->name }}
                            </div>
                        </td>
                        <td class="px-6 py-4 hidden md:table-cell">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded" style="background-color: {{ $cat->color }}"></div>
                                <span class="text-sm text-gray-600">{{ $cat->color }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-600 hidden lg:table-cell">{{ $cat->sort_order }}</td>
                        <td class="px-3 sm:px-6 py-3 sm:py-4">
                            <span class="px-2 sm:px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                                {{ $cat->equipments_count }}
                            </span>
                        </td>
                        <td class="px-3 sm:px-6 py-3 sm:py-4">
                            <button wire:click="toggleActive({{ $cat->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="toggleActive({{ $cat->id }})"
                                    class="px-2 sm:px-3 py-1 rounded-full text-xs font-bold transition-all duration-300 disabled:opacity-50 {{ $cat->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                <span wire:loading.remove wire:target="toggleActive({{ $cat->id }})">{{ $cat->is_active ? 'Ativa' : 'Inativa' }}</span>
                                <span wire:loading wire:target="toggleActive({{ $cat->id }})"><i class="fas fa-spinner fa-spin"></i></span>
                            </button>
                        </td>
                        <td class="px-3 sm:px-6 py-3 sm:py-4 text-right">
                            <div class="flex items-center justify-end gap-1 sm:gap-2">
                                <button wire:click="edit({{ $cat->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="edit({{ $cat->id }})"
                                        class="text-blue-600 hover:text-blue-800 p-1.5 rounded-lg hover:bg-blue-50 transition-all duration-300 disabled:opacity-50">
                                    <i class="fas fa-edit" wire:loading.remove wire:target="edit({{ $cat->id }})"></i>
                                    <i class="fas fa-spinner fa-spin" wire:loading wire:target="edit({{ $cat->id }})"></i>
                                </button>
                                <button wire:click="delete({{ $cat->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="delete({{ $cat->id }})"
                                        wire:confirm="Excluir esta categoria?"
                                        class="text-red-600 hover:text-red-800 p-1.5 rounded-lg hover:bg-red-50 transition-all duration-300 disabled:opacity-50">
                                    <i class="fas fa-trash" wire:loading.remove wire:target="delete({{ $cat->id }})"></i>
                                    <i class="fas fa-spinner fa-spin" wire:loading wire:target="delete({{ $cat->id }})"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            Nenhuma categoria criada
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal --}}
    @if($showModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4" style="backdrop-filter: blur(4px);">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas {{ $editMode ? 'fa-edit' : 'fa-plus-circle' }} mr-2"></i>
                    {{ $editMode ? 'Editar' : 'Nova' }} Categoria
                </h3>
                <button wire:click="closeModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nome *</label>
                    <input type="text" wire:model="name" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="Ex: Som e Áudio">
                    @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Ícone (Emoji)</label>
                        <input type="text" wire:model="icon" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="🔊">
                        <p class="text-xs text-gray-500 mt-1">Use um emoji</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Ordem</label>
                        <input type="number" wire:model="sort_order" min="0" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Cor *</label>
                    <div class="flex gap-2">
                        <input type="color" wire:model.live="color" class="h-10 w-20 border-2 border-gray-300 rounded-lg">
                        <input type="text" wire:model="color" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="#6366f1">
                    </div>
                    @error('color') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="flex space-x-3 pt-4 border-t">
                    <button wire:click="save"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 scale-95"
                            class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg hover:scale-105 transition-all duration-300 disabled:cursor-not-allowed">
                        <span wire:loading.remove><i class="fas fa-save mr-2"></i>Salvar</span>
                        <span wire:loading><i class="fas fa-spinner fa-spin mr-2"></i>Salvando...</span>
                    </button>
                    <button wire:click="closeModal"
                            class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 hover:scale-105 transition-all duration-300">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
