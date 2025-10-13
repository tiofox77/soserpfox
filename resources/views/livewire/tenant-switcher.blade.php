<div class="relative" x-data="{ open: false }" @tenant-switched-reload.window="setTimeout(() => window.location.reload(), 500)">
    <!-- Botão de Seletor -->
    <button @click="open = !open" 
            type="button"
            class="flex items-center space-x-3 px-4 py-2.5 bg-white border-2 {{ $hasExceededLimit ? 'border-red-400 ring-2 ring-red-200' : 'border-gray-200' }} rounded-xl hover:border-blue-400 hover:shadow-md transition-all duration-200 relative">
        
        @if($hasExceededLimit)
            <div class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center shadow-lg animate-pulse">
                <i class="fas fa-exclamation text-white text-xs"></i>
            </div>
        @endif
        
        <div class="flex items-center justify-center w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg">
            <i class="fas fa-building text-white text-lg"></i>
        </div>
        <div class="text-left">
            <div class="text-xs {{ $hasExceededLimit ? 'text-red-600' : 'text-gray-500' }} font-medium">
                @if($hasExceededLimit)
                    ⚠️ Limite Excedido
                @else
                    Empresa Ativa
                @endif
            </div>
            <div class="text-sm font-bold text-gray-900">{{ $activeTenantName }}</div>
        </div>
        <i class="fas fa-chevron-down text-gray-400 transition-transform" :class="{ 'rotate-180': open }"></i>
    </button>
    
    <!-- Dropdown -->
    <div x-show="open" 
         @click.away="open = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95"
         class="absolute right-0 mt-2 w-80 bg-white border-2 border-gray-200 rounded-2xl shadow-2xl z-50"
         x-cloak>
        
        <!-- Aviso de Limite Excedido -->
        @if($hasExceededLimit)
            <div class="px-4 py-3 bg-gradient-to-r from-red-500 to-orange-500 border-b-2 border-red-600">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-white text-2xl animate-pulse"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-white font-bold text-sm mb-1">⚠️ Limite Excedido</h4>
                        <p class="text-white text-xs leading-relaxed">
                            Você está gerenciando <strong>{{ $currentCount }} empresas</strong>, mas seu plano permite apenas <strong>{{ $maxAllowed }}</strong>.
                        </p>
                        <p class="text-yellow-100 text-xs mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Faça upgrade do plano para continuar usando todas as empresas.
                        </p>
                    </div>
                </div>
            </div>
        @endif
        
        <!-- Header -->
        <div class="px-4 py-3 bg-gradient-to-r from-blue-500 to-purple-600 {{ $hasExceededLimit ? '' : 'rounded-t-xl' }}">
            <div class="text-white">
                <div class="text-xs font-medium opacity-90">
                    {{ $tenants->count() > 1 ? 'Suas Empresas' : 'Empresa Ativa' }}
                </div>
                <div class="text-sm font-bold">
                    {{ $tenants->count() }} empresa(s) {{ $tenants->count() > 1 ? 'disponível(is)' : 'cadastrada' }}
                </div>
            </div>
        </div>
        
        <!-- Lista de Tenants -->
        <div class="max-h-80 overflow-y-auto">
            @foreach($tenants as $index => $tenant)
                @php
                    $isBlocked = !auth()->user()->is_super_admin && $hasExceededLimit && $index >= $maxAllowed;
                    $isActive = $tenant->id == $activeTenantId;
                @endphp
                
                <button wire:click="switchTenant({{ $tenant->id }})" 
                        type="button"
                        class="w-full text-left px-4 py-4 flex items-center justify-between transition-colors border-b border-gray-100 last:border-b-0
                               {{ $isActive ? 'bg-blue-50' : ($isBlocked ? 'bg-red-50 opacity-60' : 'hover:bg-gray-50') }}
                               {{ $isBlocked ? 'cursor-not-allowed' : '' }}"
                        @click="open = false"
                        {{ $isBlocked ? 'disabled' : '' }}>
                    <div class="flex items-center space-x-3 flex-1">
                        <!-- Icon -->
                        <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br {{ $isActive ? 'from-blue-500 to-purple-600' : ($isBlocked ? 'from-red-400 to-red-500' : 'from-gray-400 to-gray-500') }} flex items-center justify-center">
                            <i class="fas {{ $isBlocked ? 'fa-lock' : 'fa-building' }} text-white"></i>
                        </div>
                        
                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="font-bold {{ $isBlocked ? 'text-gray-500' : 'text-gray-900' }} truncate flex items-center">
                                {{ $tenant->name }}
                                @if($isBlocked)
                                    <span class="ml-2 text-xs bg-red-500 text-white px-2 py-0.5 rounded-full">
                                        <i class="fas fa-lock text-[10px] mr-1"></i>BLOQUEADA
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500">NIF: {{ $tenant->nif ?? 'N/A' }}</div>
                            @if($tenant->pivot->role_id)
                                <div class="text-xs text-blue-600 mt-1">
                                    <i class="fas fa-user-tag mr-1"></i>
                                    {{ \Spatie\Permission\Models\Role::find($tenant->pivot->role_id)?->name ?? 'Usuário' }}
                                </div>
                            @endif
                        </div>
                        
                        <!-- Check -->
                        @if($tenant->id == $activeTenantId)
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-blue-500 text-2xl"></i>
                            </div>
                        @else
                            <div class="flex-shrink-0">
                                <i class="fas fa-arrow-right text-gray-300 text-lg"></i>
                            </div>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>
        
        <!-- Footer -->
        <div class="px-4 py-3 bg-gray-50 rounded-b-xl border-t border-gray-200">
            @if($tenants->count() > 1)
                <p class="text-xs text-gray-600 text-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    Clique em uma empresa para alternar
                </p>
            @else
                <a href="{{ route('my-account') }}?tab=companies" 
                   class="block text-xs text-blue-600 hover:text-blue-700 text-center font-medium">
                    <i class="fas fa-plus-circle mr-1"></i>
                    Adicionar mais empresas
                </a>
            @endif
        </div>
    </div>
</div>
