<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-indigo-700 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Hospedes / Clientes</h2>
                    <p class="text-purple-100 text-sm">Gestao de hospedes do hotel</p>
                </div>
            </div>
            <button wire:click="openModal" class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg">
                <i class="fas fa-plus mr-2"></i>Novo Hospede
            </button>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-purple-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/30">
                    <i class="fas fa-users text-white"></i>
                </div>
                <p class="text-xs text-purple-600 font-semibold uppercase">Total</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-yellow-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-yellow-500/30">
                    <i class="fas fa-crown text-white"></i>
                </div>
                <p class="text-xs text-yellow-600 font-semibold uppercase">VIP</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['vip'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-red-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-red-400 to-red-600 rounded-xl flex items-center justify-center shadow-lg shadow-red-500/30">
                    <i class="fas fa-ban text-white"></i>
                </div>
                <p class="text-xs text-red-600 font-semibold uppercase">Bloqueados</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['blacklisted'] }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-2xl shadow-lg p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-64 relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Pesquisar por nome, email, telefone..." 
                       class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
            </div>
            <select wire:model.live="vipFilter" class="border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition min-w-40">
                <option value="">Todos os Status</option>
                <option value="vip">VIP</option>
                <option value="blacklisted">Lista Negra</option>
                <option value="regular">Regular</option>
            </select>
            <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1">
                <button wire:click="setViewMode('grid')" class="p-2.5 rounded-lg transition {{ $viewMode === 'grid' ? 'bg-white shadow text-purple-600' : 'text-gray-500 hover:text-gray-700' }}">
                    <i class="fas fa-th-large"></i>
                </button>
                <button wire:click="setViewMode('list')" class="p-2.5 rounded-lg transition {{ $viewMode === 'list' ? 'bg-white shadow text-purple-600' : 'text-gray-500 hover:text-gray-700' }}">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- Grid View --}}
    @if($viewMode === 'grid')
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        @forelse($guests as $guest)
            <div class="bg-white rounded-2xl shadow-lg p-5 border hover:shadow-xl transition-all cursor-pointer {{ $guest->hotel_blacklisted ? 'border-red-200 bg-red-50/50' : 'border-gray-100' }}" wire:click="view({{ $guest->id }})">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold text-lg {{ $guest->hotel_vip ? 'bg-gradient-to-br from-yellow-400 to-orange-500' : 'bg-gradient-to-br from-purple-500 to-indigo-600' }}">
                            {{ strtoupper(substr($guest->name, 0, 2)) }}
                        </div>
                        <div>
                            <p class="font-bold text-gray-900">{{ $guest->name }}</p>
                            <p class="text-xs text-gray-500">{{ $guest->email ?? 'Sem email' }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        @if($guest->hotel_vip)
                            <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded-full font-bold"><i class="fas fa-crown mr-1"></i>VIP</span>
                        @endif
                        @if($guest->hotel_blacklisted)
                            <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded-full font-bold"><i class="fas fa-ban mr-1"></i>Bloqueado</span>
                        @endif
                    </div>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-2 text-gray-600">
                        <i class="fas fa-phone w-4 text-purple-400"></i>
                        <span>{{ $guest->phone ?? '-' }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-gray-600">
                        <i class="fas fa-id-card w-4 text-purple-400"></i>
                        <span>{{ $guest->document_number ?? '-' }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-gray-600">
                        <i class="fas fa-globe w-4 text-purple-400"></i>
                        <span>{{ $guest->nationality ?? $guest->country ?? '-' }}</span>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t flex items-center justify-between">
                    <span class="text-xs text-gray-500"><i class="fas fa-globe mr-1"></i>{{ $guest->country ?? 'Angola' }}</span>
                    <div class="flex items-center gap-1" wire:click.stop>
                        <button wire:click="edit({{ $guest->id }})" class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"><i class="fas fa-edit"></i></button>
                        <button wire:click="toggleVip({{ $guest->id }})" class="p-2 hover:bg-yellow-50 rounded-lg transition {{ $guest->hotel_vip ? 'text-yellow-500' : 'text-gray-400 hover:text-yellow-600' }}"><i class="fas fa-crown"></i></button>
                        <button wire:click="toggleBlacklist({{ $guest->id }})" class="p-2 hover:bg-red-50 rounded-lg transition {{ $guest->hotel_blacklisted ? 'text-red-500' : 'text-gray-400 hover:text-red-600' }}"><i class="fas fa-ban"></i></button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-2xl shadow-lg p-12 text-center">
                <div class="w-20 h-20 mx-auto mb-4 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-users text-4xl text-purple-400"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Nenhum hospede encontrado</h3>
                <p class="text-gray-500 mb-4">Adicione o primeiro hospede ao sistema</p>
                <button wire:click="openModal" class="px-6 py-3 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition font-semibold">
                    <i class="fas fa-plus mr-2"></i>Novo Hospede
                </button>
            </div>
        @endforelse
    </div>
    @endif

    {{-- List View --}}
    @if($viewMode === 'list')
    <div class="bg-white rounded-2xl shadow-lg overflow-visible">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase">Hospede</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase">Contacto</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase">Documento</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase">Status</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase">Acoes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($guests as $guest)
                    <tr class="hover:bg-gray-50 transition {{ $guest->hotel_blacklisted ? 'bg-red-50' : '' }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold {{ $guest->hotel_vip ? 'bg-gradient-to-br from-yellow-400 to-orange-500' : 'bg-gradient-to-br from-purple-500 to-indigo-600' }}">
                                    {{ strtoupper(substr($guest->name, 0, 2)) }}
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900">{{ $guest->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $guest->nationality ?? '-' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-sm text-gray-900">{{ $guest->email ?? '-' }}</p>
                            <p class="text-xs text-gray-500">{{ $guest->phone ?? '-' }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-sm font-mono text-gray-900">{{ $guest->document_number ?? '-' }}</p>
                            <p class="text-xs text-gray-500">{{ $guest->nif ?? '-' }}</p>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($guest->hotel_blacklisted)
                                <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold">Bloqueado</span>
                            @elseif($guest->hotel_vip)
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-bold">VIP</span>
                            @else
                                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-bold">Regular</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center gap-1">
                                <button wire:click="view({{ $guest->id }})" class="p-2 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition"><i class="fas fa-eye"></i></button>
                                <button wire:click="edit({{ $guest->id }})" class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"><i class="fas fa-edit"></i></button>
                                <button wire:click="toggleVip({{ $guest->id }})" class="p-2 hover:bg-yellow-50 rounded-lg transition {{ $guest->hotel_vip ? 'text-yellow-500' : 'text-gray-400 hover:text-yellow-600' }}"><i class="fas fa-crown"></i></button>
                                <button wire:click="toggleBlacklist({{ $guest->id }})" class="p-2 hover:bg-red-50 rounded-lg transition {{ $guest->hotel_blacklisted ? 'text-red-500' : 'text-gray-400 hover:text-red-600' }}"><i class="fas fa-ban"></i></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-16 text-center"><div class="w-16 h-16 mx-auto mb-4 bg-purple-100 rounded-full flex items-center justify-center"><i class="fas fa-users text-3xl text-purple-400"></i></div><h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum hospede</h3><button wire:click="openModal" class="px-6 py-2 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition font-semibold"><i class="fas fa-plus mr-2"></i>Novo Hospede</button></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    {{-- Paginacao --}}
    <div class="mt-6">{{ $guests->links() }}</div>

    {{-- Modais --}}
    @include('livewire.hotel.guests.partials.form-modal')
    @include('livewire.hotel.guests.partials.view-modal')
    
    {{-- Toast --}}
    @include('livewire.hotel.guests.partials.toast')
</div>
