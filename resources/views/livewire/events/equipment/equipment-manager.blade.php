<div class="p-4 sm:p-6">
    {{-- Submenu de Navegação --}}
    <div class="mb-6 bg-white rounded-xl shadow-md p-2 flex flex-wrap gap-2">
        <a href="{{ route('events.equipment.dashboard') }}" 
           class="flex items-center px-4 py-2 rounded-lg font-semibold transition {{ request()->routeIs('events.equipment.dashboard') ? 'bg-purple-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
            <i class="fas fa-chart-line mr-2"></i>
            <span class="hidden sm:inline">Dashboard</span>
        </a>
        <a href="{{ route('events.equipment.index') }}" 
           class="flex items-center px-4 py-2 rounded-lg font-semibold transition {{ request()->routeIs('events.equipment.index') ? 'bg-purple-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
            <i class="fas fa-boxes mr-2"></i>
            <span class="hidden sm:inline">Equipamentos</span>
        </a>
        <a href="{{ route('events.equipment.sets') }}" 
           class="flex items-center px-4 py-2 rounded-lg font-semibold transition {{ request()->routeIs('events.equipment.sets') ? 'bg-purple-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
            <i class="fas fa-layer-group mr-2"></i>
            <span class="hidden sm:inline">SETS</span>
        </a>
        <a href="{{ route('events.equipment.categories') }}" 
           class="flex items-center px-4 py-2 rounded-lg font-semibold transition {{ request()->routeIs('events.equipment.categories') ? 'bg-purple-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
            <i class="fas fa-tags mr-2"></i>
            <span class="hidden sm:inline">Categorias</span>
        </a>
        <a href="{{ route('events.calendar') }}" 
           class="flex items-center px-4 py-2 rounded-lg font-semibold transition text-gray-700 hover:bg-gray-100">
            <i class="fas fa-calendar-alt mr-2"></i>
            <span class="hidden sm:inline">Calendário</span>
        </a>
    </div>

    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-toolbox text-purple-600 mr-3"></i>
                Gestão de Equipamentos
            </h2>
            <p class="text-sm sm:text-base text-gray-600">Inventário em tempo real e controle completo</p>
        </div>
        <div class="flex items-center space-x-2 sm:space-x-3">
            {{-- Abas de Visualização --}}
            <div class="flex bg-gray-100 rounded-lg p-1">
                <button wire:click="switchView('grid')" 
                        class="px-4 py-2 rounded-md font-semibold transition-all duration-300 flex items-center {{ $viewMode === 'grid' ? 'bg-white text-purple-600 shadow-md' : 'text-gray-600 hover:text-gray-900' }}">
                    <i class="fas fa-th mr-2"></i>
                    Grade
                </button>
                <button wire:click="switchView('list')" 
                        class="px-4 py-2 rounded-md font-semibold transition-all duration-300 flex items-center {{ $viewMode === 'list' ? 'bg-white text-purple-600 shadow-md' : 'text-gray-600 hover:text-gray-900' }}">
                    <i class="fas fa-list mr-2"></i>
                    Lista
                </button>
            </div>
            
            <button wire:click="openModal" 
                    class="group bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-5 py-2.5 rounded-lg font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center">
                <i class="fas fa-plus-circle mr-2 group-hover:rotate-90 transition-transform duration-300"></i>
                Novo Equipamento
            </button>
        </div>
    </div>

    {{-- Alertas --}}
    @if(count($alerts) > 0)
    <div class="mb-6 space-y-2">
        @foreach($alerts as $alert)
        <div class="flex items-center p-4 rounded-lg {{ $alert['type'] === 'error' ? 'bg-red-50 border-l-4 border-red-500' : 'bg-yellow-50 border-l-4 border-yellow-500' }}">
            <span class="text-2xl mr-3">{{ $alert['icon'] }}</span>
            <span class="flex-1 {{ $alert['type'] === 'error' ? 'text-red-800' : 'text-yellow-800' }} font-semibold">
                {{ $alert['message'] }}
            </span>
            <button class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-6 gap-6 mb-6">
        <div class="bg-gradient-to-br from-gray-500 to-gray-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-200 text-sm font-medium">Total</p>
                    <p class="text-3xl font-bold mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-boxes text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-sm font-medium">Disponível</p>
                    <p class="text-3xl font-bold mt-1">{{ $stats['disponivel'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-200 text-sm font-medium">Em Uso</p>
                    <p class="text-3xl font-bold mt-1">{{ $stats['em_uso'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-play-circle text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-200 text-sm font-medium">Emprestado</p>
                    <p class="text-3xl font-bold mt-1">{{ $stats['emprestado'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-hand-holding text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-200 text-sm font-medium">Manutenção</p>
                    <p class="text-3xl font-bold mt-1">{{ $stats['manutencao'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-wrench text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 transition duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-200 text-sm font-medium">Avariado</p>
                    <p class="text-3xl font-bold mt-1">{{ $stats['avariado'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-exclamation-triangle text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-lg shadow-md p-4 mb-6 border-l-4 border-purple-600">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <input type="text" wire:model.live.debounce.300ms="search" 
                       placeholder="🔍 Pesquisar equipamento, serial..." 
                       class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>

            <div>
                <select wire:model.live="categoryFilter" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value="">📦 Todas Categorias</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->display_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <select wire:model.live="locationFilter" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value="">📍 Todas Localizações</option>
                    @foreach($locations as $location)
                        <option value="{{ $location }}">{{ $location }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center space-x-2">
                @foreach(['disponivel' => '✅', 'em_uso' => '▶️', 'emprestado' => '🤝', 'manutencao' => '🔧'] as $status => $icon)
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="statusFilter" value="{{ $status }}" class="rounded text-purple-600">
                    <span class="ml-1 text-sm">{{ $icon }}</span>
                </label>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Grid/Lista --}}
    @if($viewMode === 'grid')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @forelse($equipment as $item)
                <div class="group bg-white rounded-xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden border-2 border-gray-100 hover:border-purple-400">
                    {{-- Imagem --}}
                    <div class="relative h-48 bg-gradient-to-br from-purple-100 to-indigo-100 flex items-center justify-center overflow-hidden">
                        @if($item->image_path)
                            <img src="{{ asset('storage/' . $item->image_path) }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" alt="{{ $item->name }}">
                        @else
                            <i class="fas fa-box-open text-6xl text-purple-300"></i>
                        @endif
                        
                        <div class="absolute top-2 right-2">
                            <span class="px-3 py-1 rounded-full text-xs font-bold text-white shadow-lg" style="background-color: {{ $item->status_color }}">
                                {{ $item->status_label }}
                            </span>
                        </div>

                        @if($item->is_overdue)
                        <div class="absolute top-2 left-2">
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-600 text-white shadow-lg animate-pulse">
                                ⏰ {{ $item->days_overdue }}d atrasado
                            </span>
                        </div>
                        @endif
                    </div>

                    {{-- Conteúdo --}}
                    <div class="p-4">
                        <h3 class="font-bold text-lg text-gray-900 mb-1 group-hover:text-purple-700 transition">{{ $item->name }}</h3>
                        <p class="text-sm text-gray-500 mb-3">{{ $item->category?->display_name ?? '-' }}</p>
                        
                        <div class="space-y-2 text-sm mb-4">
                            @if($item->serial_number)
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-barcode w-5"></i>
                                <span>{{ $item->serial_number }}</span>
                            </div>
                            @endif
                            
                            @if($item->location)
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-map-marker-alt w-5"></i>
                                <span>{{ $item->location }}</span>
                            </div>
                            @endif

                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-history w-5"></i>
                                <span>{{ $item->total_uses }} usos</span>
                            </div>
                        </div>

                        {{-- Ações --}}
                        <div class="space-y-2">
                            <div class="flex gap-2">
                                <button wire:click="edit({{ $item->id }})" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg text-sm font-semibold transition">
                                    <i class="fas fa-edit"></i>
                                    <span class="hidden sm:inline ml-1">Editar</span>
                                </button>
                                
                                @if($item->status === 'disponivel')
                                <button wire:click="openBorrowModal({{ $item->id }})" class="flex-1 bg-purple-500 hover:bg-purple-600 text-white px-3 py-2 rounded-lg text-sm font-semibold transition">
                                    <i class="fas fa-hand-holding"></i>
                                    <span class="hidden sm:inline ml-1">Emprestar</span>
                                </button>
                                @elseif($item->status === 'emprestado')
                                <button wire:click="returnEquipment({{ $item->id }})" class="flex-1 bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded-lg text-sm font-semibold transition">
                                    <i class="fas fa-undo"></i>
                                    <span class="hidden sm:inline ml-1">Devolver</span>
                                </button>
                                @endif
                            </div>
                            
                            <a href="{{ route('events.equipment.qrcode.print', $item->id) }}" target="_blank" 
                               class="block w-full bg-gray-700 hover:bg-gray-800 text-white px-3 py-2 rounded-lg text-sm font-semibold transition text-center">
                                <i class="fas fa-qrcode"></i>
                                <span class="ml-1">QR Code</span>
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
                    <p class="text-gray-500 text-lg">Nenhum equipamento encontrado</p>
                </div>
            @endforelse
        </div>
    @else
        {{-- Visualização em Lista --}}
        <div class="bg-white rounded-xl shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white">
                        <tr>
                            <th class="px-6 py-4 text-left">Equipamento</th>
                            <th class="px-6 py-4 text-left">Categoria</th>
                            <th class="px-6 py-4 text-left">Serial</th>
                            <th class="px-6 py-4 text-left">Localização</th>
                            <th class="px-6 py-4 text-left">Status</th>
                            <th class="px-6 py-4 text-left">Usos</th>
                            <th class="px-6 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($equipment as $item)
                        <tr class="hover:bg-purple-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    @if($item->image_path)
                                        <img src="{{ asset('storage/' . $item->image_path) }}" class="w-12 h-12 rounded-lg object-cover mr-3" alt="{{ $item->name }}">
                                    @else
                                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-box text-purple-600"></i>
                                        </div>
                                    @endif
                                    <span class="font-semibold text-gray-900">{{ $item->name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ $item->category?->display_name ?? '-' }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $item->serial_number ?? '-' }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $item->location ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-bold text-white" style="background-color: {{ $item->status_color }}">
                                    {{ $item->status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ $item->total_uses }}</td>
                            <td class="px-6 py-4 text-right">
                                <button wire:click="edit({{ $item->id }})" class="text-blue-600 hover:text-blue-800 mx-1">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="delete({{ $item->id }})" class="text-red-600 hover:text-red-800 mx-1">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                Nenhum equipamento encontrado
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Paginação --}}
    <div class="mt-6">
        {{ $equipment->links() }}
    </div>

    {{-- Modal Criar/Editar --}}
    @if($showModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4" style="backdrop-filter: blur(4px);">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas {{ $editMode ? 'fa-edit' : 'fa-plus-circle' }} mr-2"></i>
                    {{ $editMode ? 'Editar' : 'Novo' }} Equipamento
                </h3>
                <button wire:click="closeModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Nome *</label>
                        <input type="text" wire:model="name" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 @error('name') border-red-500 @enderror">
                        @error('name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center justify-between">
                            <span>Categoria *</span>
                            <button type="button"
                                    wire:click="openCategoryModal"
                                    class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded-md hover:bg-purple-200 transition">
                                <i class="fas fa-plus mr-1"></i>Nova
                            </button>
                        </label>
                        <select wire:model="category" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 @error('category') border-red-500 @enderror">
                            <option value="">Selecione</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->display_name }}</option>
                            @endforeach
                        </select>
                        @error('category') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>
                        <select wire:model="status" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="disponivel">Disponível</option>
                            <option value="reservado">Reservado</option>
                            <option value="em_uso">Em Uso</option>
                            <option value="avariado">Avariado</option>
                            <option value="manutencao">Manutenção</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Número de Série</label>
                        <input type="text" wire:model="serial_number" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Localização</label>
                        <input type="text" wire:model="location" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>

                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Descrição</label>
                        <textarea wire:model="description" rows="3" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Data Aquisição</label>
                        <input type="date" wire:model="acquisition_date" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 @error('acquisition_date') border-red-500 @enderror">
                        @error('acquisition_date') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Preço Compra (€)</label>
                        <input type="number" wire:model="purchase_price" step="0.01" min="0" placeholder="0.00" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 @error('purchase_price') border-red-500 @enderror">
                        @error('purchase_price') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Valor Atual (€)</label>
                        <input type="number" wire:model="current_value" step="0.01" min="0" placeholder="0.00" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 @error('current_value') border-red-500 @enderror">
                        @error('current_value') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                            <i class="fas fa-image text-purple-600 mr-2"></i>
                            Imagem do Equipamento
                        </label>
                        
                        <div class="flex items-start gap-4">
                            {{-- Preview --}}
                            <div class="flex-shrink-0">
                                @if ($image)
                                    <div class="relative w-32 h-32 rounded-lg overflow-hidden border-2 border-purple-300 shadow-lg">
                                        <img src="{{ $image->temporaryUrl() }}" class="w-full h-full object-cover">
                                        <div class="absolute top-1 right-1">
                                            <button type="button" wire:click="$set('image', null)" 
                                                    class="bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 transition">
                                                <i class="fas fa-times text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <div class="w-32 h-32 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center bg-gray-50">
                                        <i class="fas fa-image text-4xl text-gray-300"></i>
                                    </div>
                                @endif
                            </div>
                            
                            {{-- Upload button --}}
                            <div class="flex-1">
                                <label for="image-upload" class="cursor-pointer">
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-purple-500 hover:bg-purple-50 transition text-center @error('image') border-red-500 @enderror">
                                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                        <p class="text-sm font-semibold text-gray-700">Clique para escolher</p>
                                        <p class="text-xs text-gray-500 mt-1">PNG, JPG até 2MB</p>
                                    </div>
                                </label>
                                <input id="image-upload" type="file" wire:model="image" accept="image/*" class="hidden">
                                @error('image') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                
                                {{-- Loading indicator --}}
                                <div wire:loading wire:target="image" class="mt-2">
                                    <div class="flex items-center gap-2 text-purple-600">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        <span class="text-sm">Carregando imagem...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <div class="flex space-x-3 pt-4 border-t">
                    <button wire:click="save" 
                            wire:loading.attr="disabled"
                            class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="save">
                            <i class="fas fa-save mr-2"></i>
                            Salvar
                        </span>
                        <span wire:loading wire:target="save">
                            <i class="fas fa-spinner fa-spin mr-2"></i>
                            Salvando...
                        </span>
                    </button>
                    <button wire:click="closeModal" class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Empréstimo --}}
    @if($showBorrowModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4" style="backdrop-filter: blur(4px);">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas fa-hand-holding mr-2"></i>
                    Emprestar Equipamento
                </h3>
            </div>
            
            <div class="p-6 space-y-4">
                {{-- Tipo de Empréstimo --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Emprestar para:</label>
                    <div class="flex gap-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" wire:model.live="borrow_type" value="client" class="w-4 h-4 text-purple-600 focus:ring-purple-500">
                            <span class="ml-2 text-gray-700">👤 Cliente</span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" wire:model.live="borrow_type" value="technician" class="w-4 h-4 text-purple-600 focus:ring-purple-500">
                            <span class="ml-2 text-gray-700">🔧 Técnico</span>
                        </label>
                    </div>
                </div>

                {{-- Campo Cliente (condicional) --}}
                @if($borrow_type === 'client')
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Cliente *</label>
                    <select wire:model="borrowed_to_client_id" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 @error('borrowed_to_client_id') border-red-500 @enderror">
                        <option value="">Selecione o cliente</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                    @error('borrowed_to_client_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                @endif

                {{-- Campo Técnico (condicional) --}}
                @if($borrow_type === 'technician')
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Técnico *</label>
                    <select wire:model="borrowed_to_technician_id" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 @error('borrowed_to_technician_id') border-red-500 @enderror">
                        <option value="">Selecione o técnico</option>
                        @foreach($technicians as $technician)
                            <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                        @endforeach
                    </select>
                    @error('borrowed_to_technician_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Data Empréstimo *</label>
                        <input type="date" wire:model="borrow_date" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 @error('borrow_date') border-red-500 @enderror">
                        @error('borrow_date') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Data Devolução *</label>
                        <input type="date" wire:model="return_due_date" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 @error('return_due_date') border-red-500 @enderror">
                        @error('return_due_date') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Preço/Dia (só para clientes) --}}
                @if($borrow_type === 'client')
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Preço/Dia € (opcional)</label>
                    <input type="number" wire:model="rental_price_per_day" step="0.01" min="0" placeholder="0.00" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 @error('rental_price_per_day') border-red-500 @enderror">
                    @error('rental_price_per_day') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                @else
                <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-3">
                    <p class="text-sm text-blue-800 flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Empréstimo para técnico não tem custo</span>
                    </p>
                </div>
                @endif

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Observações (opcional)</label>
                    <textarea wire:model="borrow_notes" rows="3" placeholder="Adicione detalhes sobre o empréstimo..." class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"></textarea>
                </div>

                <div class="flex space-x-3 pt-4 border-t">
                    <button wire:click="saveBorrow" 
                            wire:loading.attr="disabled"
                            class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="saveBorrow">
                            <i class="fas fa-check mr-2"></i>
                            Confirmar Empréstimo
                        </span>
                        <span wire:loading wire:target="saveBorrow">
                            <i class="fas fa-spinner fa-spin mr-2"></i>
                            Processando...
                        </span>
                    </button>
                    <button wire:click="closeModal" class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
    
    {{-- Mini Modal: Criar Categoria --}}
    @if($showCategoryModal)
    <div class="fixed inset-0 bg-black bg-opacity-60 z-[60] flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" @click.stop>
            <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-5 py-3 flex items-center justify-between rounded-t-xl">
                <h4 class="text-lg font-bold text-white flex items-center">
                    <i class="fas fa-tag mr-2"></i>
                    Nova Categoria
                </h4>
                <button wire:click="closeCategoryModal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="p-5 space-y-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nome da Categoria *</label>
                    <input type="text" wire:model="newCategoryName" 
                           class="w-full px-3 py-2 border-2 @error('newCategoryName') border-red-500 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-purple-500"
                           placeholder="Ex: Câmeras">
                    @error('newCategoryName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Ícone *</label>
                        <select wire:model="newCategoryIcon" 
                                class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="📦">📦 Geral</option>
                            <option value="🎥">🎥 Câmera</option>
                            <option value="🎤">🎤 Áudio</option>
                            <option value="💡">💡 Iluminação</option>
                            <option value="📹">📹 Vídeo</option>
                            <option value="🎬">🎬 Filmagem</option>
                            <option value="🎸">🎸 Musical</option>
                            <option value="🖥️">🖥️ Computador</option>
                            <option value="📡">📡 Streaming</option>
                            <option value="🔌">🔌 Elétrico</option>
                            <option value="🎭">🎭 Cenário</option>
                            <option value="📺">📺 TV/Monitor</option>
                            <option value="🎮">🎮 Gaming</option>
                            <option value="🔧">🔧 Ferramentas</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Cor *</label>
                        <input type="color" wire:model="newCategoryColor" 
                               class="w-full h-[42px] border-2 border-gray-300 rounded-lg cursor-pointer">
                    </div>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-3 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center text-2xl" 
                         style="background-color: {{ $newCategoryColor }}20; border: 2px solid {{ $newCategoryColor }}">
                        {{ $newCategoryIcon }}
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 font-semibold">Preview:</p>
                        <p class="font-bold" style="color: {{ $newCategoryColor }}">
                            {{ $newCategoryIcon }} {{ $newCategoryName ?: 'Nome da categoria' }}
                        </p>
                    </div>
                </div>
                
                <div class="flex gap-2 pt-3">
                    <button wire:click="saveCategory" 
                            wire:loading.attr="disabled"
                            class="flex-1 bg-purple-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-purple-700 transition">
                        <span wire:loading.remove wire:target="saveCategory">
                            <i class="fas fa-save mr-1"></i>Salvar
                        </span>
                        <span wire:loading wire:target="saveCategory">
                            <i class="fas fa-spinner fa-spin mr-1"></i>Salvando...
                        </span>
                    </button>
                    <button wire:click="closeCategoryModal" 
                            class="px-4 py-2 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
