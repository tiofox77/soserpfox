<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">🎬 Equipamentos</h1>
            <p class="text-gray-600 mt-1">Gestão de equipamentos de eventos</p>
        </div>
        <button wire:click="create" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium shadow-lg">
            <i class="fas fa-plus mr-2"></i>Novo Equipamento
        </button>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <input wire:model.live="search" type="text" placeholder="Buscar equipamento..." 
                   class="border border-gray-300 rounded-lg px-4 py-2">
            
            <select wire:model.live="categoryFilter" class="border border-gray-300 rounded-lg px-4 py-2">
                <option value="all">Todas as Categorias</option>
                <option value="audio">Áudio</option>
                <option value="video">Vídeo</option>
                <option value="iluminacao">Iluminação</option>
                <option value="streaming">Streaming</option>
                <option value="led">LED</option>
                <option value="estrutura">Estrutura</option>
                <option value="outros">Outros</option>
            </select>

            <select wire:model.live="statusFilter" class="border border-gray-300 rounded-lg px-4 py-2">
                <option value="all">Todos os Status</option>
                <option value="disponivel">Disponível</option>
                <option value="em_uso">Em Uso</option>
                <option value="manutencao">Manutenção</option>
                <option value="danificado">Danificado</option>
            </select>
        </div>
    </div>

    <!-- Equipment Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @foreach($equipment as $item)
        <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-gray-900 mb-1">{{ $item->name }}</h3>
                        <p class="text-sm text-gray-500">{{ $item->code ?? '-' }}</p>
                    </div>
                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ $item->category_label }}
                    </span>
                </div>

                <div class="space-y-2 mb-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Preço Diária:</span>
                        <span class="font-bold text-green-600">{{ number_format($item->daily_price, 2, ',', '.') }} Kz</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Disponível:</span>
                        <span class="font-bold">{{ $item->quantity_available }} / {{ $item->quantity }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Status:</span>
                        <span class="px-2 py-1 rounded text-xs font-medium 
                            {{ $item->status == 'disponivel' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $item->status == 'em_uso' ? 'bg-yellow-100 text-yellow-800' : '' }}
                            {{ $item->status == 'manutencao' ? 'bg-orange-100 text-orange-800' : '' }}
                            {{ $item->status == 'danificado' ? 'bg-red-100 text-red-800' : '' }}">
                            {{ ucfirst(str_replace('_', ' ', $item->status)) }}
                        </span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button wire:click="edit({{ $item->id }})" class="flex-1 bg-blue-100 text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-200 transition">
                        <i class="fas fa-edit mr-1"></i> Editar
                    </button>
                    <button wire:click="delete({{ $item->id }})" onclick="return confirm('Excluir?')" 
                            class="bg-red-100 text-red-700 px-4 py-2 rounded-lg hover:bg-red-200 transition">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $equipment->links() }}
    </div>

    <!-- Modal (omitido para brevidade - similar ao de eventos) -->
</div>
