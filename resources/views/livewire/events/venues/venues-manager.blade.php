<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">📍 Locais de Eventos</h1>
            <p class="text-gray-600 mt-1">Gestão de locais e salas</p>
        </div>
        <button wire:click="create" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium shadow-lg">
            <i class="fas fa-plus mr-2"></i>Novo Local
        </button>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <input wire:model.live="search" type="text" placeholder="Buscar por nome ou cidade..." 
               class="w-full border border-gray-300 rounded-lg px-4 py-2">
    </div>

    <!-- Venues Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($venues as $venue)
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                    <h3 class="text-xl font-bold text-gray-900">{{ $venue->name }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $venue->city }}</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $venue->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                    {{ $venue->is_active ? 'Ativo' : 'Inativo' }}
                </span>
            </div>

            <div class="space-y-2 mb-4 text-sm text-gray-600">
                @if($venue->address)
                <div class="flex items-start">
                    <i class="fas fa-map-marker-alt w-5 mt-1 text-red-500"></i>
                    <span>{{ $venue->address }}</span>
                </div>
                @endif
                @if($venue->phone)
                <div class="flex items-center">
                    <i class="fas fa-phone w-5 text-blue-500"></i>
                    <span>{{ $venue->phone }}</span>
                </div>
                @endif
                @if($venue->capacity)
                <div class="flex items-center">
                    <i class="fas fa-users w-5 text-green-500"></i>
                    <span>Capacidade: {{ $venue->capacity }} pessoas</span>
                </div>
                @endif
            </div>

            <div class="flex gap-2 mt-4">
                <button wire:click="edit({{ $venue->id }})" class="flex-1 bg-blue-100 text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-200">
                    <i class="fas fa-edit mr-1"></i> Editar
                </button>
                <button wire:click="delete({{ $venue->id }})" onclick="return confirm('Excluir?')" 
                        class="bg-red-100 text-red-700 px-4 py-2 rounded-lg hover:bg-red-200">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $venues->links() }}
    </div>

    <!-- Modal (omitido para brevidade) -->
</div>
