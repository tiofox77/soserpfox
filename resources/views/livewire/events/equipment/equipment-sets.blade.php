<div class="p-4 sm:p-6">
    {{-- Submenu --}}
    <div class="mb-6 bg-white rounded-xl shadow-md p-2 flex flex-wrap gap-2">
        <a href="{{ route('events.equipment.dashboard') }}" class="flex items-center px-4 py-2 rounded-lg font-semibold transition text-gray-700 hover:bg-gray-100">
            <i class="fas fa-chart-line mr-2"></i><span class="hidden sm:inline">Dashboard</span>
        </a>
        <a href="{{ route('events.equipment.index') }}" class="flex items-center px-4 py-2 rounded-lg font-semibold transition text-gray-700 hover:bg-gray-100">
            <i class="fas fa-boxes mr-2"></i><span class="hidden sm:inline">Equipamentos</span>
        </a>
        <a href="{{ route('events.equipment.sets') }}" class="flex items-center px-4 py-2 rounded-lg font-semibold transition bg-purple-600 text-white">
            <i class="fas fa-layer-group mr-2"></i><span class="hidden sm:inline">SETS</span>
        </a>
        <a href="{{ route('events.equipment.categories') }}" class="flex items-center px-4 py-2 rounded-lg font-semibold transition text-gray-700 hover:bg-gray-100">
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
                <i class="fas fa-layer-group text-purple-600 mr-3"></i>
                Conjuntos de Equipamentos (SETS)
            </h2>
            <p class="text-sm sm:text-base text-gray-600">Crie kits de equipamentos para eventos</p>
        </div>
        <button wire:click="openModal" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-5 py-2.5 rounded-lg font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center">
            <i class="fas fa-plus-circle mr-2"></i>Novo SET
        </button>
    </div>

    {{-- Busca --}}
    <div class="mb-6">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="🔍 Pesquisar SET..." class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
    </div>

    {{-- Grid de SETS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($sets as $set)
        <div class="bg-white rounded-xl shadow-md hover:shadow-2xl transition-all p-6 border-2 border-gray-100 hover:border-purple-400">
            <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-2xl">{{ $set->category?->icon ?? '📦' }}</span>
                        <h3 class="text-lg font-bold text-gray-900">{{ $set->name }}</h3>
                    </div>
                    @if($set->description)
                    <p class="text-sm text-gray-600 mb-3">{{ $set->description }}</p>
                    @endif
                </div>
            </div>

            <div class="mb-4 p-3 bg-purple-50 rounded-lg">
                <p class="text-sm font-semibold text-purple-800">
                    <i class="fas fa-boxes mr-2"></i>
                    {{ $set->equipments_count }} equipamento(s)
                </p>
                @if($set->equipments->count() > 0)
                <div class="mt-2 space-y-1">
                    @foreach($set->equipments->take(3) as $eq)
                    <p class="text-xs text-gray-700">• {{ $eq->name }} ({{ $eq->pivot->quantity }}x)</p>
                    @endforeach
                    @if($set->equipments->count() > 3)
                    <p class="text-xs text-purple-600 font-semibold">+ {{ $set->equipments->count() - 3 }} mais...</p>
                    @endif
                </div>
                @endif
            </div>

            <div class="flex gap-2">
                <button wire:click="openItemsModal({{ $set->id }})" class="flex-1 bg-purple-500 hover:bg-purple-600 text-white px-3 py-2 rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-cog"></i> Gerenciar
                </button>
                <button wire:click="edit({{ $set->id }})" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg text-sm transition">
                    <i class="fas fa-edit"></i>
                </button>
                <button wire:click="delete({{ $set->id }})" onclick="return confirm('Excluir este SET?')" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg text-sm transition">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        @empty
        <div class="col-span-full text-center py-12">
            <i class="fas fa-layer-group text-gray-300 text-6xl mb-4"></i>
            <p class="text-gray-500 text-lg">Nenhum SET criado ainda</p>
            <button wire:click="openModal" class="mt-4 text-purple-600 font-semibold hover:underline">Criar primeiro SET</button>
        </div>
        @endforelse
    </div>

    {{-- Paginação --}}
    <div class="mt-6">{{ $sets->links() }}</div>

    {{-- Modal Criar/Editar SET --}}
    @if($showModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4" style="backdrop-filter: blur(4px);">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas {{ $editMode ? 'fa-edit' : 'fa-plus-circle' }} mr-2"></i>
                    {{ $editMode ? 'Editar' : 'Novo' }} SET
                </h3>
                <button wire:click="closeModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nome do SET *</label>
                    <input type="text" wire:model="name" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Categoria *</label>
                    <select wire:model="category" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">Selecione</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->display_name }}</option>
                        @endforeach
                    </select>
                    @error('category') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Descrição</label>
                    <textarea wire:model="description" rows="3" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="Descreva este conjunto de equipamentos..."></textarea>
                </div>

                <div class="flex space-x-3 pt-4 border-t">
                    <button wire:click="save" wire:loading.attr="disabled" class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition">
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

    {{-- Modal Gerenciar Itens --}}
    @if($showItemsModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4" style="backdrop-filter: blur(4px);">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 sticky top-0 z-10">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas fa-cog mr-2"></i>
                    Gerenciar Equipamentos do SET
                </h3>
            </div>
            
            <div class="p-6">
                {{-- Adicionar Equipamento --}}
                <div class="mb-6 p-4 bg-purple-50 rounded-lg">
                    <h4 class="font-bold text-purple-900 mb-3">Adicionar Equipamento</h4>
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <select wire:model="selectedEquipment" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg">
                                <option value="">Selecione um equipamento</option>
                                @foreach($availableEquipments as $eq)
                                    <option value="{{ $eq->id }}">{{ $eq->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-24">
                            <input type="number" wire:model="quantity" min="1" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg" placeholder="Qtd">
                        </div>
                        <button wire:click="addEquipment" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg font-semibold">
                            <i class="fas fa-plus"></i> Adicionar
                        </button>
                    </div>
                </div>

                {{-- Lista de Equipamentos --}}
                @php
                    $currentSet = \App\Models\EquipmentSet::with('equipments')->find($currentSetId);
                @endphp
                @if($currentSet && $currentSet->equipments->count() > 0)
                <div class="space-y-2">
                    <h4 class="font-bold text-gray-900 mb-3">Equipamentos no SET ({{ $currentSet->equipments->count() }})</h4>
                    @foreach($currentSet->equipments as $eq)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100">
                        <div class="flex-1">
                            <p class="font-semibold text-gray-900">{{ $eq->name }}</p>
                            <p class="text-sm text-gray-600">Quantidade: {{ $eq->pivot->quantity }}x</p>
                        </div>
                        <button wire:click="removeEquipment({{ $currentSet->id }}, {{ $eq->id }})" class="text-red-600 hover:text-red-800 px-3 py-1">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-center text-gray-500 py-8">Nenhum equipamento adicionado ainda</p>
                @endif

                <div class="mt-6 pt-4 border-t">
                    <button wire:click="closeModal" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-semibold transition">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
