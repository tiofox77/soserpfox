<div>
    <!-- Header with Gradient -->
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-building text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Tenants</h2>
                    <p class="text-blue-100 text-sm">Gerir e acompanhar todos os tenants</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Tenant
            </button>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center space-x-4">
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input wire:model.live="search" type="text" placeholder="Pesquisar tenants..." 
                       class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            </div>
        </div>
    </div>

    <!-- Tenants List -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2 text-blue-600"></i>
                    Lista de Tenants ({{ $tenants->total() }})
                </h3>
            </div>
        </div>
        
        <div class="divide-y divide-gray-100 stagger-animation">
            @forelse($tenants as $tenant)
                <div class="group p-6 hover:bg-gray-50 transition-all duration-300 card-hover cursor-pointer">
                    <div class="flex items-start space-x-4">
                        <!-- Avatar/Logo -->
                        <div class="relative flex-shrink-0">
                            @if($tenant->logo)
                                <img src="{{ Storage::url($tenant->logo) }}" 
                                     alt="{{ $tenant->name }}" 
                                     class="w-14 h-14 rounded-full object-cover shadow-lg group-hover:shadow-2xl transition-all duration-300 ring-2 ring-purple-200">
                            @else
                                <div class="w-14 h-14 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center shadow-lg group-hover:shadow-2xl transition-all duration-300 icon-float gradient-shift">
                                    <span class="text-white font-bold text-lg">{{ strtoupper(substr($tenant->name, 0, 2)) }}</span>
                                </div>
                            @endif
                            <div class="absolute -bottom-1 -right-1 w-5 h-5 {{ $tenant->is_active ? 'bg-green-500 animate-pulse' : 'bg-gray-400' }} rounded-full border-2 border-white shadow"></div>
                        </div>
                        
                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900 mb-1">{{ $tenant->name }}</h4>
                                    <p class="text-sm text-gray-500">{{ $tenant->slug }}</p>
                                </div>
                                
                                <button wire:click="toggleStatus({{ $tenant->id }})"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                        wire:target="activateTenant({{ $tenant->id }}), confirmDeactivation"
                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full cursor-pointer transition-all {{ $tenant->is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }} disabled:opacity-50">
                                    <span wire:loading.remove wire:target="activateTenant({{ $tenant->id }}), confirmDeactivation">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $tenant->is_active ? 'bg-green-500' : 'bg-gray-500' }} mr-1.5"></span>
                                        {{ $tenant->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                    <span wire:loading wire:target="activateTenant({{ $tenant->id }}), confirmDeactivation">
                                        <i class="fas fa-spinner fa-spin mr-1.5"></i>Processando...
                                    </span>
                                </button>
                            </div>
                            
                            <!-- Info Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                                <!-- Contact -->
                                <div class="flex items-start space-x-2">
                                    <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-envelope text-blue-600 text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-500 font-medium">Email</p>
                                        <p class="text-sm text-gray-900 truncate">{{ $tenant->email }}</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start space-x-2">
                                    <span class="w-7 h-7 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-phone text-green-600 text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-500 font-medium">Telefone</p>
                                        <p class="text-sm text-gray-900">{{ $tenant->phone ?? 'N/A' }}</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start space-x-2">
                                    <span class="w-7 h-7 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-calendar text-purple-600 text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-500 font-medium">Criado em</p>
                                        <p class="text-sm text-gray-900">{{ $tenant->created_at->format('d/m/Y') }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Plan & Limits -->
                            <div class="flex items-center flex-wrap gap-2 mb-4">
                                @php
                                    $activeSub = $tenant->activeSubscription;
                                @endphp
                                @if($activeSub && $activeSub->plan)
                                <span class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-purple-50 to-indigo-50 text-purple-700 rounded-lg text-xs font-bold border border-purple-200">
                                    <i class="fas fa-crown mr-1.5 text-yellow-500"></i>
                                    {{ $activeSub->plan->name }}
                                    <span class="ml-1.5 px-1.5 py-0.5 bg-purple-200 text-purple-800 rounded text-[10px]">
                                        {{ ucfirst($activeSub->billing_cycle) }}
                                    </span>
                                </span>
                                @else
                                <span class="inline-flex items-center px-3 py-1.5 bg-gray-50 text-gray-600 rounded-lg text-xs font-medium">
                                    <i class="fas fa-question-circle mr-1.5"></i>
                                    Sem plano
                                </span>
                                @endif
                                <span class="inline-flex items-center px-3 py-1.5 bg-orange-50 text-orange-700 rounded-lg text-xs font-medium">
                                    <i class="fas fa-users mr-1.5"></i>
                                    {{ $tenant->max_users }} utilizadores
                                </span>
                                <span class="inline-flex items-center px-3 py-1.5 bg-cyan-50 text-cyan-700 rounded-lg text-xs font-medium">
                                    <i class="fas fa-database mr-1.5"></i>
                                    {{ $tenant->max_storage_mb }}MB storage
                                </span>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex flex-wrap gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="manageUsers({{ $tenant->id }})" class="inline-flex items-center px-3 py-1.5 bg-orange-50 text-orange-700 rounded-lg text-xs font-medium hover:bg-orange-100 transition-colors">
                                    <i class="fas fa-users mr-1.5"></i>Usuários
                                </button>
                                <button wire:click="managePlan({{ $tenant->id }})" class="inline-flex items-center px-3 py-1.5 bg-purple-50 text-purple-700 rounded-lg text-xs font-medium hover:bg-purple-100 transition-colors">
                                    <i class="fas fa-crown mr-1.5"></i>Plano
                                </button>
                                <button wire:click="viewDetails({{ $tenant->id }})" class="inline-flex items-center px-3 py-1.5 bg-green-50 text-green-700 rounded-lg text-xs font-medium hover:bg-green-100 transition-colors">
                                    <i class="fas fa-eye mr-1.5"></i>Ver Detalhes
                                </button>
                                <button wire:click="edit({{ $tenant->id }})" class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium hover:bg-blue-100 transition-colors">
                                    <i class="fas fa-edit mr-1.5"></i>Editar
                                </button>
                                <button wire:click="toggleStatus({{ $tenant->id }})" 
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                        wire:target="activateTenant({{ $tenant->id }}), confirmDeactivation"
                                        class="inline-flex items-center px-3 py-1.5 bg-purple-50 text-purple-700 rounded-lg text-xs font-medium hover:bg-purple-100 transition-colors disabled:opacity-50">
                                    <span wire:loading.remove wire:target="activateTenant({{ $tenant->id }}), confirmDeactivation">
                                        <i class="fas fa-power-off mr-1.5"></i>{{ $tenant->is_active ? 'Desativar' : 'Ativar' }}
                                    </span>
                                    <span wire:loading wire:target="activateTenant({{ $tenant->id }}), confirmDeactivation">
                                        <i class="fas fa-spinner fa-spin mr-1.5"></i>Enviando emails...
                                    </span>
                                </button>
                                <button wire:click="openDeleteModal({{ $tenant->id }})" class="inline-flex items-center px-3 py-1.5 bg-red-50 text-red-700 rounded-lg text-xs font-medium hover:bg-red-100 transition-colors">
                                    <i class="fas fa-trash mr-1.5"></i>Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-inbox text-gray-400 text-3xl"></i>
                    </div>
                    <p class="text-gray-500 font-medium text-lg">Nenhum tenant encontrado</p>
                    <p class="text-gray-400 text-sm mt-1">Comece criando um novo tenant</p>
                </div>
            @endforelse
        </div>
        
        <!-- Pagination -->
        @if($tenants->hasPages())
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                {{ $tenants->links() }}
            </div>
        @endif
    </div>

    <!-- Modals -->
    @include('livewire.super-admin.tenants.partials.form-modal')
    @include('livewire.super-admin.tenants.partials.delete-modal')
    @include('livewire.super-admin.tenants.partials.view-modal')
    @include('livewire.super-admin.tenants.partials.users-modal')
    @include('livewire.super-admin.tenants.partials.plan-modal')
    @include('livewire.super-admin.tenants.partials.deactivation-modal')
</div>
