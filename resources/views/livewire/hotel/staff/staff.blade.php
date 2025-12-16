<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-teal-600 to-cyan-700 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Funcionarios</h2>
                    <p class="text-teal-100 text-sm">Gestao de equipa do hotel</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button wire:click="openImportModal" class="bg-white/20 hover:bg-white/30 px-4 py-3 rounded-xl font-semibold transition">
                    <i class="fas fa-file-import mr-2"></i>Importar do RH
                </button>
                <button wire:click="openModal" class="bg-white text-teal-600 hover:bg-teal-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg">
                    <i class="fas fa-plus mr-2"></i>Novo Funcionario
                </button>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-teal-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-xl flex items-center justify-center shadow-lg shadow-teal-500/30"><i class="fas fa-users text-white"></i></div>
                <p class="text-xs text-teal-600 font-semibold uppercase">Total</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-green-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/30"><i class="fas fa-check-circle text-white"></i></div>
                <p class="text-xs text-green-600 font-semibold uppercase">Ativos</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['active'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-blue-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/30"><i class="fas fa-broom text-white"></i></div>
                <p class="text-xs text-blue-600 font-semibold uppercase">Housekeeping</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['housekeeping'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-purple-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-violet-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/30"><i class="fas fa-concierge-bell text-white"></i></div>
                <p class="text-xs text-purple-600 font-semibold uppercase">Recepcao</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['front_desk'] }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-4 flex flex-wrap items-center gap-4">
        <div class="flex-1 min-w-[200px] relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400"></i></div>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Pesquisar por nome..." class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
        </div>
        <select wire:model.live="departmentFilter" class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
            <option value="">Todos departamentos</option>
            @foreach($departments as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select>
        <select wire:model.live="positionFilter" class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
            <option value="">Todos cargos</option>
            @foreach($positions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select>
        <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-xl">
            <button wire:click="setViewMode('grid')" class="px-4 py-2 rounded-lg font-semibold text-sm transition {{ $viewMode === 'grid' ? 'bg-white text-teal-600 shadow' : 'text-gray-500' }}"><i class="fas fa-th-large"></i></button>
            <button wire:click="setViewMode('list')" class="px-4 py-2 rounded-lg font-semibold text-sm transition {{ $viewMode === 'list' ? 'bg-white text-teal-600 shadow' : 'text-gray-500' }}"><i class="fas fa-list"></i></button>
        </div>
    </div>

    {{-- Grid View --}}
    @if($viewMode === 'grid')
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @forelse($staffList as $member)
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-all group {{ !$member->is_active ? 'opacity-60' : '' }}">
            <div class="h-32 bg-gradient-to-br from-teal-400 to-cyan-600 relative flex items-center justify-center">
                @if($member->photo)
                    <img src="{{ asset('storage/' . $member->photo) }}" class="w-full h-full object-cover">
                @else
                    <span class="text-4xl font-bold text-white/80">{{ $member->initials }}</span>
                @endif
                <div class="absolute top-2 right-2">
                    <span class="px-2 py-1 text-xs font-bold rounded-lg {{ $member->is_active ? 'bg-green-500 text-white' : 'bg-red-500 text-white' }}">{{ $member->is_active ? 'Ativo' : 'Inativo' }}</span>
                </div>
            </div>
            <div class="p-4">
                <h3 class="font-bold text-gray-900 truncate">{{ $member->name }}</h3>
                <p class="text-sm text-teal-600 font-medium">{{ $member->position_label }}</p>
                <p class="text-xs text-gray-500 mb-3">{{ $member->department_label }}</p>
                @if($member->phone)<p class="text-xs text-gray-500 truncate"><i class="fas fa-phone mr-1"></i>{{ $member->phone }}</p>@endif
                <div class="flex items-center gap-2 mt-3 opacity-0 group-hover:opacity-100 transition">
                    <button wire:click="view({{ $member->id }})" class="flex-1 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold"><i class="fas fa-eye"></i></button>
                    <button wire:click="openModal({{ $member->id }})" class="flex-1 px-3 py-2 bg-teal-500 hover:bg-teal-600 text-white rounded-lg text-xs font-semibold"><i class="fas fa-edit"></i></button>
                    <button wire:click="toggleStatus({{ $member->id }})" class="px-3 py-2 {{ $member->is_active ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' }} rounded-lg text-xs font-semibold"><i class="fas {{ $member->is_active ? 'fa-pause' : 'fa-play' }}"></i></button>
                    <button wire:click="confirmDelete({{ $member->id }})" class="px-3 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg text-xs font-semibold"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full text-center py-16 bg-white rounded-2xl shadow-lg">
            <div class="w-20 h-20 mx-auto mb-4 bg-teal-100 rounded-full flex items-center justify-center"><i class="fas fa-users text-4xl text-teal-400"></i></div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum funcionario</h3>
            <p class="text-gray-500 mb-4">Adicione o primeiro membro da equipa</p>
            <button wire:click="openModal" class="px-6 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 transition font-semibold"><i class="fas fa-plus mr-2"></i>Novo Funcionario</button>
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
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase">Funcionario</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase">Cargo</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase">Departamento</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase">Contacto</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase">Status</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase">Acoes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($staffList as $member)
                <tr class="hover:bg-gray-50 transition {{ !$member->is_active ? 'opacity-60' : '' }}">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-teal-400 to-cyan-600 flex items-center justify-center text-white font-bold">
                                @if($member->photo)<img src="{{ asset('storage/' . $member->photo) }}" class="w-full h-full object-cover">@else{{ $member->initials }}@endif
                            </div>
                            <div><p class="font-bold text-gray-900">{{ $member->name }}</p></div>
                        </div>
                    </td>
                    <td class="px-6 py-4"><span class="text-sm font-medium text-gray-700">{{ $member->position_label }}</span></td>
                    <td class="px-6 py-4"><span class="px-2 py-1 bg-gray-100 text-gray-700 text-xs font-bold rounded">{{ $member->department_label }}</span></td>
                    <td class="px-6 py-4"><span class="text-sm text-gray-600">{{ $member->phone ?? $member->email ?? '-' }}</span></td>
                    <td class="px-6 py-4 text-center"><span class="px-3 py-1 text-xs font-bold rounded-full {{ $member->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $member->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-center gap-1">
                            <button wire:click="view({{ $member->id }})" class="p-2 text-gray-500 hover:text-teal-600 hover:bg-teal-50 rounded-lg transition"><i class="fas fa-eye"></i></button>
                            <button wire:click="openModal({{ $member->id }})" class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"><i class="fas fa-edit"></i></button>
                            <button wire:click="toggleStatus({{ $member->id }})" class="p-2 text-gray-500 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition"><i class="fas {{ $member->is_active ? 'fa-pause' : 'fa-play' }}"></i></button>
                            <button wire:click="confirmDelete({{ $member->id }})" class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-16 text-center"><div class="w-16 h-16 mx-auto mb-4 bg-teal-100 rounded-full flex items-center justify-center"><i class="fas fa-users text-3xl text-teal-400"></i></div><h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum funcionario</h3><button wire:click="openModal" class="px-6 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 transition font-semibold"><i class="fas fa-plus mr-2"></i>Novo Funcionario</button></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    {{-- Paginacao --}}
    <div class="mt-6">{{ $staffList->links() }}</div>

    {{-- Modais --}}
    @include('livewire.hotel.staff.partials.form-modal')
    @include('livewire.hotel.staff.partials.view-modal')
    @include('livewire.hotel.staff.partials.delete-modal')
    @include('livewire.hotel.staff.partials.import-modal')
    
    {{-- Toast --}}
    @include('livewire.hotel.staff.partials.toast')
</div>
