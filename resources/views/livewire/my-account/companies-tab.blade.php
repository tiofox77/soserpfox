<!-- Minhas Empresas Tab -->
<div class="bg-white rounded-2xl shadow-lg p-6">
    
    <!-- Status do Limite -->
    <div class="mb-6 p-4 rounded-xl {{ $hasExceededLimit ? 'bg-red-50 border-2 border-red-200' : 'bg-blue-50 border-2 border-blue-200' }}">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-full {{ $hasExceededLimit ? 'bg-red-500' : 'bg-blue-500' }} flex items-center justify-center">
                    <i class="fas {{ $hasExceededLimit ? 'fa-exclamation-triangle' : 'fa-info-circle' }} text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold {{ $hasExceededLimit ? 'text-red-900' : 'text-blue-900' }}">
                        @if($hasExceededLimit)
                            ⚠️ Limite de Empresas Excedido
                        @else
                            Limite de Empresas
                        @endif
                    </h3>
                    <p class="{{ $hasExceededLimit ? 'text-red-700' : 'text-blue-700' }} text-sm">
                        Você está gerenciando <strong>{{ $currentCount }}</strong> de <strong>{{ $maxAllowed >= 999 ? '∞' : $maxAllowed }}</strong> empresa(s) permitida(s)
                    </p>
                </div>
            </div>
            
            @if($hasExceededLimit)
                <a href="#" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-arrow-up mr-2"></i>Fazer Upgrade
                </a>
            @endif
        </div>
        
        <!-- Progress Bar -->
        <div class="mt-4">
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="{{ $hasExceededLimit ? 'bg-red-500' : 'bg-blue-500' }} h-2 rounded-full transition-all" 
                     style="width: {{ min(($currentCount / max($maxAllowed, 1)) * 100, 100) }}%"></div>
            </div>
        </div>
    </div>

    <!-- Lista de Empresas -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-gray-900">
                <i class="fas fa-building text-blue-500 mr-2"></i>
                Empresas ({{ $myTenants->count() }})
            </h2>
            
            @if(!auth()->user()->is_super_admin)
                @if($currentCount < $maxAllowed)
                    <button wire:click="openCreateCompanyModal" 
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-semibold transition shadow-lg">
                        <i class="fas fa-plus-circle mr-2"></i>Criar Nova Empresa
                    </button>
                @else
                    <button disabled 
                            class="px-4 py-2 bg-gray-400 text-white rounded-lg text-sm font-semibold cursor-not-allowed opacity-60"
                            title="Limite de empresas atingido">
                        <i class="fas fa-lock mr-2"></i>Limite Atingido
                    </button>
                @endif
            @endif
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($myTenants as $index => $tenant)
                @php
                    $isBlocked = !auth()->user()->is_super_admin && $hasExceededLimit && $index >= $maxAllowed;
                    $isActive = $tenant->id == activeTenantId();
                    $subscription = $tenant->activeSubscription;
                    $usersCount = $tenant->users()->count();
                    $modulesCount = $tenant->modules()->wherePivot('is_active', true)->count();
                @endphp
                
                <div class="border-2 {{ $isActive ? 'border-blue-500 bg-gradient-to-br from-blue-50 to-blue-100' : ($isBlocked ? 'border-red-300 bg-red-50 opacity-75' : 'border-gray-200 bg-white') }} rounded-2xl p-5 hover:shadow-2xl transition-all relative overflow-hidden">
                    
                    @if($isBlocked)
                        <div class="absolute top-3 right-3 bg-red-500 text-white text-xs px-3 py-1 rounded-full flex items-center font-semibold shadow-lg">
                            <i class="fas fa-lock mr-1"></i>BLOQUEADA
                        </div>
                    @endif
                    
                    @if($isActive)
                        <div class="absolute top-3 right-3 bg-green-500 text-white text-xs px-3 py-1 rounded-full flex items-center font-semibold shadow-lg animate-pulse">
                            <i class="fas fa-check-circle mr-1"></i>ATIVA
                        </div>
                    @endif
                    
                    <!-- Header do Card -->
                    <div class="flex items-start space-x-4 mb-4">
                        <div class="w-16 h-16 rounded-xl bg-gradient-to-br {{ $isBlocked ? 'from-red-400 to-red-600' : ($isActive ? 'from-green-400 to-blue-600' : 'from-blue-500 to-purple-600') }} flex items-center justify-center shadow-lg flex-shrink-0">
                            <i class="fas {{ $isBlocked ? 'fa-lock' : 'fa-building' }} text-white text-2xl"></i>
                        </div>
                        <div class="flex-1 {{ $isActive ? 'pr-20' : ($isBlocked ? 'pr-24' : '') }}">
                            <h3 class="font-bold text-lg {{ $isBlocked ? 'text-gray-500' : 'text-gray-900' }} mb-1">
                                {{ $tenant->name }}
                            </h3>
                            <p class="text-sm text-gray-600">{{ $tenant->company_name }}</p>
                        </div>
                    </div>
                    
                    <!-- Informações Detalhadas -->
                    <div class="space-y-3 mb-4">
                        <!-- NIF -->
                        <div class="flex items-center text-sm">
                            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center mr-3">
                                <i class="fas fa-id-card text-gray-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">NIF</p>
                                <p class="font-semibold text-gray-900">{{ $tenant->nif ?? 'N/A' }}</p>
                            </div>
                        </div>
                        
                        <!-- Utilizadores -->
                        <div class="flex items-center text-sm">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center mr-3">
                                <i class="fas fa-users text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Utilizadores</p>
                                <p class="font-semibold text-gray-900">{{ $usersCount }} {{ $usersCount == 1 ? 'utilizador' : 'utilizadores' }}</p>
                            </div>
                        </div>
                        
                        <!-- Módulos -->
                        <div class="flex items-center text-sm">
                            <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center mr-3">
                                <i class="fas fa-puzzle-piece text-purple-600"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Módulos Ativos</p>
                                <p class="font-semibold text-gray-900">{{ $modulesCount }} {{ $modulesCount == 1 ? 'módulo' : 'módulos' }}</p>
                            </div>
                        </div>
                        
                        <!-- Plano -->
                        @if($subscription)
                            <div class="flex items-center text-sm">
                                <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-crown text-yellow-600"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Plano</p>
                                    <p class="font-semibold text-gray-900">{{ $subscription->plan->name }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Footer com Ações -->
                    <div class="pt-4 border-t border-gray-200">
                        <div class="flex items-center justify-between mb-3">
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-calendar mr-1"></i>
                                Desde {{ $tenant->pivot->joined_at ? \Carbon\Carbon::parse($tenant->pivot->joined_at)->format('d/m/Y') : 'N/A' }}
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full {{ $tenant->pivot->role_id == 2 ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700' }} font-semibold">
                                <i class="fas fa-user-shield mr-1"></i>
                                {{ \Spatie\Permission\Models\Role::find($tenant->pivot->role_id)?->name ?? 'Utilizador' }}
                            </span>
                        </div>
                        
                        <!-- Botões de Ação -->
                        <div class="flex space-x-2">
                            @if(!$isActive && !$isBlocked)
                                <button wire:click="switchToTenant({{ $tenant->id }})" 
                                        class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition shadow-lg">
                                    <i class="fas fa-exchange-alt mr-2"></i>Ativar Empresa
                                </button>
                            @elseif($isBlocked)
                                <button disabled 
                                        class="flex-1 px-4 py-2 bg-gray-400 text-white rounded-lg text-sm font-semibold cursor-not-allowed opacity-60">
                                    <i class="fas fa-lock mr-2"></i>Bloqueada
                                </button>
                            @else
                                <div class="flex-1 px-4 py-2 bg-green-100 border-2 border-green-500 text-green-700 rounded-lg text-sm font-semibold text-center">
                                    <i class="fas fa-check-circle mr-2"></i>Empresa Ativa
                                </div>
                            @endif
                            
                            @if($tenant->pivot->role_id == 2)
                                <button wire:click="openEditCompanyModal({{ $tenant->id }})" 
                                        class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-sm font-semibold transition shadow-lg hover:scale-105"
                                        title="Editar empresa">
                                    <i class="fas fa-edit"></i>
                                </button>
                            @endif
                            
                            @if($tenant->pivot->role_id == 2 && $myTenants->count() > 1 && !$isActive)
                                <button wire:click="confirmDeleteCompany({{ $tenant->id }})" 
                                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold transition shadow-lg hover:scale-105"
                                        title="Eliminar empresa">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
