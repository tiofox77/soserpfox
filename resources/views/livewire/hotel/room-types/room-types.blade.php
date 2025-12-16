<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-purple-700 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-bed text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Tipos de Quarto</h2>
                    <p class="text-indigo-100 text-sm">Gestão de categorias e preços</p>
                </div>
            </div>
            <button wire:click="openModal" wire:loading.attr="disabled"
                    class="bg-white text-indigo-600 hover:bg-indigo-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl disabled:opacity-50">
                <span wire:loading.remove wire:target="openModal">
                    <i class="fas fa-plus mr-2"></i>Novo Tipo
                </span>
                <span wire:loading wire:target="openModal">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Aguarde...
                </span>
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-indigo-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/30">
                    <i class="fas fa-layer-group text-white"></i>
                </div>
                <p class="text-xs text-indigo-600 font-semibold uppercase">Total Tipos</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $roomTypes->total() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-5 border border-green-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/30">
                    <i class="fas fa-check-circle text-white"></i>
                </div>
                <p class="text-xs text-green-600 font-semibold uppercase">Ativos</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $roomTypes->where('is_active', true)->count() }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-5 border border-blue-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/30">
                    <i class="fas fa-door-open text-white"></i>
                </div>
                <p class="text-xs text-blue-600 font-semibold uppercase">Total Quartos</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $roomTypes->sum('rooms_count') }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-5 border border-amber-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-orange-600 rounded-xl flex items-center justify-center shadow-lg shadow-amber-500/30">
                    <i class="fas fa-coins text-white"></i>
                </div>
                <p class="text-xs text-amber-600 font-semibold uppercase">Preço Médio</p>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($roomTypes->avg('base_price') ?? 0, 0, ',', '.') }} <span class="text-sm text-gray-500">Kz</span></p>
        </div>
    </div>

    {{-- Filtros e Toggle View --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between gap-4">
            {{-- Pesquisa --}}
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Pesquisar por nome ou código..." 
                       class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm">
            </div>

            {{-- Toggle Grid/List --}}
            <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-xl">
                <button wire:click="setViewMode('grid')" 
                        class="px-4 py-2 rounded-lg font-semibold text-sm transition {{ $viewMode === 'grid' ? 'bg-white text-indigo-600 shadow' : 'text-gray-500 hover:text-gray-700' }}">
                    <i class="fas fa-th-large mr-1"></i>Grid
                </button>
                <button wire:click="setViewMode('list')" 
                        class="px-4 py-2 rounded-lg font-semibold text-sm transition {{ $viewMode === 'list' ? 'bg-white text-indigo-600 shadow' : 'text-gray-500 hover:text-gray-700' }}">
                    <i class="fas fa-list mr-1"></i>Lista
                </button>
            </div>

            @if($search)
                <button wire:click="$set('search', '')" class="text-sm text-indigo-600 hover:text-indigo-700 font-semibold">
                    <i class="fas fa-times mr-1"></i>Limpar
                </button>
            @endif
        </div>
    </div>

    {{-- Grid View --}}
    @if($viewMode === 'grid')
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($roomTypes as $type)
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-all duration-300 group {{ !$type->is_active ? 'opacity-60' : '' }}">
                {{-- Header com imagem ou gradiente --}}
                <div class="h-32 relative overflow-hidden cursor-pointer" wire:click="view({{ $type->id }})">
                    @if($type->featured_image)
                        <img src="{{ asset('storage/' . $type->featured_image) }}" alt="{{ $type->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                    @else
                        <div class="absolute inset-0" style="background: linear-gradient(135deg, {{ $type->color ?? '#6366f1' }} 0%, {{ $type->color ?? '#8b5cf6' }}dd 100%);"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-bed text-white/20 text-7xl"></i>
                        </div>
                    @endif
                    
                    {{-- Badge galeria --}}
                    @if($type->gallery && count($type->gallery) > 0)
                        <div class="absolute top-3 left-3 flex items-center gap-1">
                            <span class="px-2 py-1 bg-black/50 backdrop-blur-sm text-white text-xs font-bold rounded-lg">
                                <i class="fas fa-images mr-1"></i>{{ count($type->gallery) }}
                            </span>
                        </div>
                    @endif
                    
                    <div class="absolute top-3 right-3">
                        @if(!$type->is_active)
                            <span class="px-2 py-1 bg-red-500 text-white text-xs font-bold rounded-lg">Inativo</span>
                        @else
                            <span class="px-2 py-1 bg-green-500/80 text-white text-xs font-bold rounded-lg">Ativo</span>
                        @endif
                    </div>
                    <div class="absolute bottom-3 left-3">
                        <span class="px-3 py-1 bg-white/20 backdrop-blur-sm text-white text-xs font-bold rounded-lg">
                            {{ $type->code ?: 'N/A' }}
                        </span>
                    </div>
                    <div class="absolute bottom-3 right-3">
                        <span class="px-3 py-1 bg-white text-gray-900 text-lg font-bold rounded-lg shadow-lg">
                            {{ number_format($type->base_price, 0, ',', '.') }} Kz
                        </span>
                    </div>
                </div>

                {{-- Conteúdo --}}
                <div class="p-5">
                    <h3 class="font-bold text-lg text-gray-900 mb-2">{{ $type->name }}</h3>
                    <p class="text-sm text-gray-600 mb-4 line-clamp-2">{{ $type->description ?: 'Sem descrição' }}</p>

                    {{-- Tags de informação --}}
                    <div class="flex flex-wrap gap-2 mb-4">
                        <span class="px-3 py-1.5 bg-blue-100 text-blue-700 text-xs font-bold rounded-lg flex items-center">
                            <i class="fas fa-users mr-1.5"></i>{{ $type->capacity }} pessoas
                        </span>
                        <span class="px-3 py-1.5 bg-green-100 text-green-700 text-xs font-bold rounded-lg flex items-center">
                            <i class="fas fa-door-open mr-1.5"></i>{{ $type->rooms_count }} quartos
                        </span>
                        @if($type->extra_bed_capacity > 0)
                            <span class="px-3 py-1.5 bg-purple-100 text-purple-700 text-xs font-bold rounded-lg flex items-center">
                                <i class="fas fa-plus mr-1.5"></i>{{ $type->extra_bed_capacity }} camas
                            </span>
                        @endif
                    </div>

                    {{-- Comodidades --}}
                    @if($type->amenities && count($type->amenities) > 0)
                        <div class="flex flex-wrap gap-1 mb-4">
                            @foreach(array_slice($type->amenities, 0, 4) as $amenity)
                                <span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-lg">
                                    {{ $this->availableAmenities[$amenity] ?? $amenity }}
                                </span>
                            @endforeach
                            @if(count($type->amenities) > 4)
                                <span class="px-2 py-1 bg-indigo-100 text-indigo-600 text-xs font-bold rounded-lg">
                                    +{{ count($type->amenities) - 4 }}
                                </span>
                            @endif
                        </div>
                    @endif

                    {{-- Ações --}}
                    <div class="flex items-center gap-2 pt-4 border-t">
                        <button wire:click="view({{ $type->id }})" class="flex-1 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-semibold transition">
                            <i class="fas fa-eye mr-1"></i>Ver
                        </button>
                        <button wire:click="edit({{ $type->id }})" class="flex-1 px-3 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-xl text-sm font-semibold transition">
                            <i class="fas fa-edit mr-1"></i>Editar
                        </button>
                        <button wire:click="toggleStatus({{ $type->id }})" 
                                class="px-3 py-2 {{ $type->is_active ? 'bg-yellow-100 hover:bg-yellow-200 text-yellow-700' : 'bg-green-100 hover:bg-green-200 text-green-700' }} rounded-xl text-sm font-semibold transition">
                            <i class="fas {{ $type->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                        </button>
                        <button wire:click="confirmDelete({{ $type->id }})" class="px-3 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-xl text-sm font-semibold transition">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-16 bg-white rounded-2xl shadow-lg">
                <div class="w-20 h-20 mx-auto mb-4 bg-indigo-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-bed text-4xl text-indigo-400"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum tipo de quarto</h3>
                <p class="text-gray-500 mb-4">Comece criando o primeiro tipo de quarto</p>
                <button wire:click="openModal" class="px-6 py-2 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition font-semibold">
                    <i class="fas fa-plus mr-2"></i>Criar Tipo de Quarto
                </button>
            </div>
        @endforelse
    </div>
    @endif

    {{-- List View --}}
    @if($viewMode === 'list')
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase">Tipo</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase">Código</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase">Capacidade</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase">Quartos</th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-600 uppercase">Preço/Noite</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase">Status</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($roomTypes as $type)
                    <tr class="hover:bg-gray-50 transition {{ !$type->is_active ? 'opacity-60' : '' }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl overflow-hidden flex-shrink-0">
                                    @if($type->featured_image)
                                        <img src="{{ asset('storage/' . $type->featured_image) }}" alt="{{ $type->name }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                                            <i class="fas fa-bed text-white text-sm"></i>
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900">{{ $type->name }}</p>
                                    <p class="text-xs text-gray-500 line-clamp-1">{{ $type->description ?: 'Sem descrição' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 bg-gray-100 text-gray-700 text-xs font-mono font-bold rounded">{{ $type->code ?: 'N/A' }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-sm font-semibold text-gray-700">{{ $type->capacity }} <span class="text-gray-400">pessoas</span></span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded-lg">{{ $type->rooms_count }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-lg font-bold text-gray-900">{{ number_format($type->base_price, 0, ',', '.') }}</span>
                            <span class="text-xs text-gray-500">Kz</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 text-xs font-bold rounded-full {{ $type->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $type->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center gap-1">
                                <button wire:click="view({{ $type->id }})" class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button wire:click="edit({{ $type->id }})" class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="toggleStatus({{ $type->id }})" class="p-2 text-gray-500 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition" title="{{ $type->is_active ? 'Desativar' : 'Ativar' }}">
                                    <i class="fas {{ $type->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                                </button>
                                <button wire:click="confirmDelete({{ $type->id }})" class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center">
                            <div class="w-16 h-16 mx-auto mb-4 bg-indigo-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-bed text-3xl text-indigo-400"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum tipo de quarto</h3>
                            <p class="text-gray-500 mb-4">Comece criando o primeiro tipo de quarto</p>
                            <button wire:click="openModal" class="px-6 py-2 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition font-semibold">
                                <i class="fas fa-plus mr-2"></i>Criar Tipo de Quarto
                            </button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    {{-- Paginação --}}
    <div class="mt-6">
        {{ $roomTypes->links() }}
    </div>

    {{-- Modais --}}
    @include('livewire.hotel.room-types.partials.form-modal')
    @include('livewire.hotel.room-types.partials.view-modal')
    @include('livewire.hotel.room-types.partials.delete-modal')
    
    {{-- Toast --}}
    @include('livewire.hotel.room-types.partials.toast')
</div>
