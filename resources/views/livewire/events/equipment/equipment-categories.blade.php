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
        <button wire:click="openModal" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-5 py-2.5 rounded-lg font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center">
            <i class="fas fa-plus-circle mr-2"></i>Nova Categoria
        </button>
    </div>

    {{-- Lista de Categorias --}}
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <table class="w-full">
            <thead class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white">
                <tr>
                    <th class="px-6 py-4 text-left">Ícone</th>
                    <th class="px-6 py-4 text-left">Nome</th>
                    <th class="px-6 py-4 text-left">Cor</th>
                    <th class="px-6 py-4 text-left">Ordem</th>
                    <th class="px-6 py-4 text-left">Equipamentos</th>
                    <th class="px-6 py-4 text-left">Status</th>
                    <th class="px-6 py-4 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($categories as $cat)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-2xl">{{ $cat->icon ?: '📦' }}</td>
                    <td class="px-6 py-4 font-semibold text-gray-900">{{ $cat->name }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded" style="background-color: {{ $cat->color }}"></div>
                            <span class="text-sm text-gray-600">{{ $cat->color }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-600">{{ $cat->sort_order }}</td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                            {{ $cat->equipments_count }} equip.
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <button wire:click="toggleActive({{ $cat->id }})" class="px-3 py-1 rounded-full text-xs font-bold {{ $cat->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $cat->is_active ? 'Ativa' : 'Inativa' }}
                        </button>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <button wire:click="edit({{ $cat->id }})" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button wire:click="delete({{ $cat->id }})" onclick="return confirm('Excluir esta categoria?')" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </button>
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
                    <button wire:click="save" class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition">
                        <i class="fas fa-save mr-2"></i>Salvar
                    </button>
                    <button wire:click="closeModal" class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
