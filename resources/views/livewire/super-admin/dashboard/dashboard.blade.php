<div>
    <!-- Stats Cards - Modern 2025 Design with Colored Icons -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6 stagger-animation">
        <!-- Total Tenants - Purple -->
        <div class="group relative bg-white rounded-2xl shadow-lg p-6 border border-purple-100 overflow-hidden card-hover card-3d">
            <div class="absolute top-0 right-0 w-32 h-32 bg-purple-50 rounded-full -mr-16 -mt-16 opacity-50 group-hover:opacity-70 transition-opacity"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float gradient-shift">
                        <i class="fas fa-layer-group text-white text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-purple-600 font-semibold mb-2">Total Tenants</p>
                <p class="text-4xl font-bold text-gray-900 group-hover:scale-110 transition-transform inline-block">{{ $stats['total_tenants'] }}</p>
                <p class="text-xs text-gray-500 mt-2">
                    <span class="inline-flex items-center px-2 py-1 bg-purple-100 text-purple-700 rounded-full group-hover:bg-purple-200 transition-colors">
                        <i class="fas fa-check-circle mr-1"></i>
                        Ativos: {{ $stats['active_tenants'] }}
                    </span>
                </p>
            </div>
        </div>

        <!-- Active Tenants - Green -->
        <div class="group relative bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-zoom">
            <div class="absolute top-0 right-0 w-32 h-32 bg-green-50 rounded-full -mr-16 -mt-16 opacity-50 group-hover:opacity-70 transition-opacity"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float gradient-shift">
                        <i class="fas fa-users text-white text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-green-600 font-semibold mb-2">Total Utilizadores</p>
                <p class="text-4xl font-bold text-gray-900 group-hover:scale-110 transition-transform inline-block">{{ $stats['total_users'] }}</p>
                <p class="text-xs text-gray-500 mt-2">
                    <i class="fas fa-arrow-up text-green-500 mr-1 animate-bounce"></i>
                    Registados no sistema
                </p>
            </div>
        </div>

        <!-- Total Revenue - Yellow/Orange -->
        <div class="group relative bg-white rounded-2xl shadow-lg p-6 border border-yellow-100 overflow-hidden card-hover card-glow">
            <div class="absolute top-0 right-0 w-32 h-32 bg-yellow-50 rounded-full -mr-16 -mt-16 opacity-50 group-hover:opacity-70 transition-opacity"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-2xl flex items-center justify-center shadow-lg shadow-yellow-500/50 icon-float gradient-shift">
                        <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-orange-600 font-semibold mb-2">Receita Total</p>
                <p class="text-4xl font-bold text-gray-900 group-hover:scale-110 transition-transform inline-block">{{ number_format($stats['total_revenue'], 2) }} Kz</p>
                <p class="text-xs text-gray-500 mt-2">
                    <span class="inline-flex items-center px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full group-hover:bg-yellow-200 transition-colors">
                        <i class="fas fa-check mr-1"></i>
                        Faturas Pagas
                    </span>
                </p>
            </div>
        </div>

        <!-- Modules - Indigo -->
        <div class="group relative bg-white rounded-2xl shadow-lg p-6 border border-indigo-100 overflow-hidden card-hover card-rotate">
            <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-50 rounded-full -mr-16 -mt-16 opacity-50 group-hover:opacity-70 transition-opacity"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/50 icon-float gradient-shift">
                        <i class="fas fa-puzzle-piece text-white text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-indigo-600 font-semibold mb-2">Modulos Activos</p>
                <p class="text-4xl font-bold text-gray-900 group-hover:scale-110 transition-transform inline-block">{{ $stats['total_modules'] }}</p>
                <p class="text-xs text-gray-500 mt-2">
                    <span class="inline-flex items-center px-2 py-1 bg-indigo-100 text-indigo-700 rounded-full">
                        <i class="fas fa-check-circle mr-1"></i>
                        Subscrições: {{ $stats['active_subscriptions'] }}
                    </span>
                </p>
            </div>
        </div>

        <!-- Quick Actions - Blue -->
        <div class="group relative bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-rotate">
            <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-full -mr-16 -mt-16 opacity-50 group-hover:opacity-70 transition-opacity"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float gradient-shift">
                        <i class="fas fa-bolt text-white text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-blue-600 font-semibold mb-2">Acoes Rapidas</p>
                <div class="space-y-2 mt-2">
                    <a href="{{ route('superadmin.tenants') }}" class="flex items-center text-sm text-gray-700 hover:text-blue-600 hover:translate-x-2 transition-all duration-300">
                        <i class="fas fa-plus-circle mr-2 text-blue-500"></i>Novo Tenant
                    </a>
                    <a href="{{ route('superadmin.system-commands') }}" class="flex items-center text-sm text-gray-700 hover:text-green-600 hover:translate-x-2 transition-all duration-300">
                        <i class="fas fa-terminal mr-2 text-green-500"></i>Comandos
                    </a>
                    <a href="{{ route('superadmin.plans') }}" class="flex items-center text-sm text-gray-700 hover:text-purple-600 hover:translate-x-2 transition-all duration-300">
                        <i class="fas fa-tag mr-2 text-purple-500"></i>Planos
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Tenants & Invoices -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Tenants -->
        <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-shadow duration-300">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-building text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Tenants Recentes</h3>
                </div>
                <a href="{{ route('superadmin.tenants') }}" class="text-sm text-blue-600 hover:text-blue-700">Ver todos →</a>
            </div>
            <div class="p-6">
                @forelse($recentTenants as $tenant)
                    <div class="group flex items-start justify-between py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50 rounded-lg px-3 -mx-3 transition-colors">
                        <div class="flex items-start space-x-4 flex-1">
                            <!-- Avatar -->
                            <div class="relative flex-shrink-0">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center shadow-sm group-hover:shadow-md transition-shadow">
                                    <span class="text-white font-bold text-sm">{{ strtoupper(substr($tenant->name, 0, 2)) }}</span>
                                </div>
                                <div class="absolute -bottom-1 -right-1 w-4 h-4 {{ $tenant->is_active ? 'bg-green-500' : 'bg-gray-400' }} rounded-full border-2 border-white"></div>
                            </div>
                            
                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 mb-2">{{ $tenant->name }}</p>
                                
                                <!-- Contact Info with Icons -->
                                <div class="flex flex-wrap gap-3 mb-2">
                                    <span class="inline-flex items-center text-xs text-gray-600">
                                        <span class="w-5 h-5 rounded bg-blue-100 flex items-center justify-center mr-1.5">
                                            <i class="fas fa-envelope text-blue-600 text-[10px]"></i>
                                        </span>
                                        {{ $tenant->email }}
                                    </span>
                                    
                                    @if($tenant->domain)
                                    <span class="inline-flex items-center text-xs text-gray-600">
                                        <span class="w-5 h-5 rounded bg-purple-100 flex items-center justify-center mr-1.5">
                                            <i class="fas fa-globe text-purple-600 text-[10px]"></i>
                                        </span>
                                        {{ $tenant->domain }}
                                    </span>
                                    @endif
                                </div>
                                
                                <!-- Date & Stats -->
                                <div class="flex flex-wrap gap-2">
                                    <span class="inline-flex items-center text-xs text-gray-500">
                                        <span class="w-5 h-5 rounded bg-green-100 flex items-center justify-center mr-1">
                                            <i class="fas fa-calendar text-green-600 text-[10px]"></i>
                                        </span>
                                        {{ $tenant->created_at->format('d/m/Y') }}
                                    </span>
                                    <span class="inline-flex items-center text-xs text-gray-500">
                                        <span class="w-5 h-5 rounded bg-indigo-100 flex items-center justify-center mr-1">
                                            <i class="fas fa-puzzle-piece text-indigo-600 text-[10px]"></i>
                                        </span>
                                        {{ $tenant->modules->count() }} mod.
                                    </span>
                                    <span class="inline-flex items-center text-xs text-gray-500">
                                        <span class="w-5 h-5 rounded bg-orange-100 flex items-center justify-center mr-1">
                                            <i class="fas fa-users text-orange-600 text-[10px]"></i>
                                        </span>
                                        {{ $tenant->users_count ?? 0 }} users
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Status & Actions -->
                            <div class="flex flex-col items-end space-y-2 flex-shrink-0">
                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full {{ $tenant->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $tenant->is_active ? 'bg-green-500' : 'bg-gray-500' }} mr-1.5"></span>
                                    {{ $tenant->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                                
                                <!-- Action Icons -->
                                <div class="flex space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button wire:click="viewTenant({{ $tenant->id }})" 
                                            class="w-7 h-7 rounded flex items-center justify-center hover:bg-blue-50 transition-colors"
                                            title="Ver Detalhes">
                                        <i class="fas fa-eye text-blue-600 text-xs"></i>
                                    </button>
                                    <button wire:click="editTenant({{ $tenant->id }})" 
                                            class="w-7 h-7 rounded flex items-center justify-center hover:bg-green-50 transition-colors"
                                            title="Editar">
                                        <i class="fas fa-edit text-green-600 text-xs"></i>
                                    </button>
                                    <button wire:click="manageTenant({{ $tenant->id }})" 
                                            class="w-7 h-7 rounded flex items-center justify-center hover:bg-purple-50 transition-colors"
                                            title="Gerir Tenant">
                                        <i class="fas fa-cog text-purple-600 text-xs"></i>
                                    </button>
                                    <button wire:click="deleteTenant({{ $tenant->id }})" 
                                            wire:confirm="Tem certeza que deseja eliminar este tenant? Esta ação não pode ser desfeita."
                                            class="w-7 h-7 rounded flex items-center justify-center hover:bg-red-50 transition-colors"
                                            title="Eliminar">
                                        <i class="fas fa-trash text-red-600 text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-inbox text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-500 font-medium">Nenhum tenant registado</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Invoices -->
        <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-shadow duration-300">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-file-invoice-dollar text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Faturas Recentes</h3>
                </div>
                <a href="{{ route('superadmin.billing') }}" class="text-sm text-purple-600 hover:text-purple-700">Ver todas →</a>
            </div>
            <div class="p-6">
                @forelse($recentInvoices as $invoice)
                    <div class="group flex items-start justify-between py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50 rounded-lg px-3 -mx-3 transition-colors">
                        <div class="flex items-start space-x-4 flex-1">
                            <!-- Icon -->
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center shadow-sm group-hover:shadow-md transition-shadow flex-shrink-0">
                                <i class="fas fa-file-invoice text-white text-lg"></i>
                            </div>
                            
                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 mb-2">{{ $invoice->invoice_number }}</p>
                                
                                <!-- Details with Icons -->
                                <div class="flex flex-wrap gap-3 mb-2">
                                    <span class="inline-flex items-center text-xs text-gray-600">
                                        <span class="w-5 h-5 rounded bg-blue-100 flex items-center justify-center mr-1.5">
                                            <i class="fas fa-building text-blue-600 text-[10px]"></i>
                                        </span>
                                        {{ $invoice->tenant->name ?? 'N/A' }}
                                    </span>
                                    
                                    <span class="inline-flex items-center text-xs text-gray-600">
                                        <span class="w-5 h-5 rounded bg-orange-100 flex items-center justify-center mr-1.5">
                                            <i class="fas fa-money-bill-wave text-orange-600 text-[10px]"></i>
                                        </span>
                                        <span class="font-semibold">{{ number_format($invoice->total, 2) }} Kz</span>
                                    </span>
                                </div>
                                
                                <!-- Date -->
                                <span class="inline-flex items-center text-xs text-gray-500">
                                    <span class="w-5 h-5 rounded bg-green-100 flex items-center justify-center mr-1.5">
                                        <i class="fas fa-calendar text-green-600 text-[10px]"></i>
                                    </span>
                                    {{ $invoice->created_at->format('d/m/Y') }}
                                </span>
                            </div>
                            
                            <!-- Status & Actions -->
                            <div class="flex flex-col items-end space-y-2 flex-shrink-0">
                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full 
                                    {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-700' : 
                                       ($invoice->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $invoice->status === 'paid' ? 'bg-green-500' : ($invoice->status === 'pending' ? 'bg-yellow-500' : 'bg-gray-500') }} mr-1.5"></span>
                                    {{ ucfirst($invoice->status) }}
                                </span>
                                
                                <!-- Action Icons -->
                                <div class="flex space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button class="w-7 h-7 rounded flex items-center justify-center hover:bg-blue-50 transition-colors">
                                        <i class="fas fa-eye text-blue-600 text-xs"></i>
                                    </button>
                                    <button class="w-7 h-7 rounded flex items-center justify-center hover:bg-green-50 transition-colors">
                                        <i class="fas fa-download text-green-600 text-xs"></i>
                                    </button>
                                    <button class="w-7 h-7 rounded flex items-center justify-center hover:bg-purple-50 transition-colors">
                                        <i class="fas fa-paper-plane text-purple-600 text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-file-invoice text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-500 font-medium">Nenhuma fatura registada</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
