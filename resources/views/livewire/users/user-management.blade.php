<div>
    <!-- Header with Gradient -->
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Gestão de Utilizadores</h2>
                    <p class="text-purple-100 text-sm">Criar e gerenciar utilizadores com acesso multi-empresa</p>
                </div>
            </div>
            @php
                $tenant = \App\Models\Tenant::find(activeTenantId());
                $currentUsers = $tenant->users()->count();
                $maxUsers = $tenant->getMaxUsers();
                $canAdd = $tenant->canAddUser();
            @endphp
            
            <button wire:click="create" 
                    class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl {{ !$canAdd ? 'opacity-50 cursor-not-allowed' : '' }}"
                    {{ !$canAdd ? 'disabled' : '' }}>
                <i class="fas fa-user-plus mr-2"></i>Novo Utilizador
                <span class="ml-2 px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-lg">
                    {{ $currentUsers }}/{{ $maxUsers }}
                </span>
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Total Users -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-purple-500 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-600 mb-1">Total de Utilizadores</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $totalUsers }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        Nas suas empresas
                    </p>
                </div>
                <div class="w-16 h-16 bg-gradient-to-br from-purple-100 to-purple-200 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-users text-purple-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Active Users -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-green-500 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-600 mb-1">Utilizadores Ativos</p>
                    <p class="text-3xl font-bold text-green-600">{{ $activeUsers }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-check-circle mr-1"></i>
                        {{ $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0 }}% do total
                    </p>
                </div>
                <div class="w-16 h-16 bg-gradient-to-br from-green-100 to-green-200 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-user-check text-green-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Inactive Users -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-red-500 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-600 mb-1">Utilizadores Inativos</p>
                    <p class="text-3xl font-bold text-red-600">{{ $inactiveUsers }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-user-slash mr-1"></i>
                        {{ $totalUsers > 0 ? round(($inactiveUsers / $totalUsers) * 100) : 0 }}% do total
                    </p>
                </div>
                <div class="w-16 h-16 bg-gradient-to-br from-red-100 to-red-200 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-user-times text-red-600 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Alert -->
    @if(!auth()->user()->is_super_admin)
        <div class="bg-blue-50 border-l-4 border-blue-500 rounded-xl p-4 mb-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-500 text-xl"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-semibold text-blue-900">Visualização Filtrada</h3>
                    <p class="text-sm text-blue-700 mt-1">
                        Você está visualizando apenas os utilizadores que pertencem às suas empresas. 
                        Novos utilizadores criados terão acesso apenas às empresas que você gerencia.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Filters -->
    <div class="bg-white rounded-2xl shadow-lg p-4 mb-6">
        <div class="flex items-center space-x-4">
            <div class="flex-1">
                <input wire:model.live="search" type="text" placeholder="🔍 Pesquisar por nome ou email..." 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-purple-50 to-pink-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Utilizador</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Empresas</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-bold text-gray-900">{{ $user->name }}</div>
                                        @if($user->is_super_admin)
                                            <div class="text-xs text-red-600 font-semibold">
                                                <i class="fas fa-crown mr-1"></i>Super Admin
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $user->email }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($user->tenants as $tenant)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <i class="fas fa-building mr-1"></i>
                                            {{ $tenant->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-400">Sem empresas</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button wire:click="toggleStatus({{ $user->id }})" 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    <i class="fas fa-circle text-[8px] mr-1"></i>
                                    {{ $user->is_active ? 'Ativo' : 'Inativo' }}
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button wire:click="edit({{ $user->id }})" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @if(!$user->is_super_admin && $user->id != auth()->id())
                                    <button wire:click="confirmDelete({{ $user->id }})" 
                                            class="text-red-600 hover:text-red-900 transition">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <i class="fas fa-users text-gray-400 text-5xl mb-4"></i>
                                <p class="text-gray-500">Nenhum utilizador encontrado</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $users->links() }}
        </div>
    </div>

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 py-6">
                <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity backdrop-blur-sm" wire:click="closeModal"></div>
                
                <div class="relative bg-white rounded-2xl max-w-4xl w-full shadow-2xl transform transition-all" @click.stop>
                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-purple-600 to-pink-600 rounded-t-2xl px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center text-white">
                            <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-user-plus text-xl"></i>
                            </div>
                            <h3 class="text-xl font-bold">
                                {{ $editingUserId ? 'Editar Utilizador' : 'Novo Utilizador' }}
                            </h3>
                        </div>
                        <button wire:click="closeModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <!-- Modal Body -->
                    <form wire:submit.prevent="save" class="p-6 max-h-[70vh] overflow-y-auto">
                        {{-- Indicador de Limite de Usuários --}}
                        @if(!$editingUserId)
                            @php
                                $tenant = \App\Models\Tenant::find(activeTenantId());
                                $currentUsers = $tenant->users()->count();
                                $maxUsers = $tenant->getMaxUsers();
                                $remainingUsers = $maxUsers - $currentUsers;
                                $percentage = ($currentUsers / $maxUsers) * 100;
                            @endphp
                            
                            <div class="mb-6 p-4 rounded-xl {{ $remainingUsers > 0 ? 'bg-blue-50 border-2 border-blue-200' : 'bg-red-50 border-2 border-red-200' }}">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex items-center">
                                        <i class="fas fa-users {{ $remainingUsers > 0 ? 'text-blue-600' : 'text-red-600' }} mr-2"></i>
                                        <span class="font-semibold {{ $remainingUsers > 0 ? 'text-blue-900' : 'text-red-900' }}">
                                            Limite do Plano: {{ $currentUsers }} / {{ $maxUsers }} utilizadores
                                        </span>
                                    </div>
                                    @if($remainingUsers > 0)
                                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-lg">
                                            {{ $remainingUsers }} disponível{{ $remainingUsers != 1 ? 'is' : '' }}
                                        </span>
                                    @else
                                        <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-lg">
                                            Limite atingido
                                        </span>
                                    @endif
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all {{ $percentage >= 100 ? 'bg-red-600' : ($percentage >= 80 ? 'bg-yellow-500' : 'bg-blue-600') }}" 
                                         style="width: {{ min($percentage, 100) }}%"></div>
                                </div>
                                @if($remainingUsers <= 0)
                                    <p class="text-xs text-red-700 mt-2">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        Faça upgrade do seu plano para adicionar mais utilizadores.
                                    </p>
                                @endif
                            </div>
                        @endif
                        
                        <!-- User Info -->
                        <div class="mb-6">
                            <h4 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-user text-purple-500 mr-2"></i>
                                Informações Pessoais
                            </h4>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-user-circle text-purple-500 mr-2"></i>Nome Completo *
                                    </label>
                                    <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                    @error('name') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-envelope text-purple-500 mr-2"></i>Email *
                                    </label>
                                    <input wire:model="email" type="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                    @error('email') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-lock text-purple-500 mr-2"></i>Senha {{ $editingUserId ? '(deixe em branco para não alterar)' : '*' }}
                                    </label>
                                    <input wire:model="password" type="password" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                    @error('password') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-lock text-purple-500 mr-2"></i>Confirmar Senha
                                    </label>
                                    <input wire:model="password_confirmation" type="password" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <label class="flex items-center cursor-pointer">
                                    <input wire:model="is_active" type="checkbox" class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                    <span class="ml-2 text-sm font-semibold text-gray-700">
                                        <i class="fas fa-check-circle text-green-500 mr-1"></i>Conta Ativa
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Companies & Permissions -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-lg font-bold text-gray-900">
                                    <i class="fas fa-building text-purple-500 mr-2"></i>
                                    Empresas e Permissões
                                </h4>
                                <button type="button" wire:click="selectAllTenants" 
                                        class="text-sm font-semibold {{ $assignToAllTenants ? 'text-purple-600' : 'text-gray-600' }} hover:text-purple-700">
                                    <i class="fas {{ $assignToAllTenants ? 'fa-check-square' : 'fa-square' }} mr-1"></i>
                                    Atribuir a todas as empresas
                                </button>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-3 max-h-60 overflow-y-auto border border-gray-200 rounded-xl p-4">
                                @foreach($myTenants as $tenant)
                                    <div class="border-2 {{ in_array($tenant->id, $selectedTenants) ? 'border-purple-500 bg-purple-50' : 'border-gray-200' }} rounded-xl p-4 transition-all">
                                        <div class="flex items-center justify-between mb-3">
                                            <label class="flex items-center cursor-pointer flex-1">
                                                <input type="checkbox" 
                                                       wire:click="toggleTenant({{ $tenant->id }})"
                                                       {{ in_array($tenant->id, $selectedTenants) ? 'checked' : '' }}
                                                       class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                                <div class="ml-3">
                                                    <span class="text-sm font-bold text-gray-900">{{ $tenant->name }}</span>
                                                    <p class="text-xs text-gray-500">{{ $tenant->company_name }}</p>
                                                </div>
                                            </label>
                                        </div>
                                        
                                        @if(in_array($tenant->id, $selectedTenants))
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                                    <i class="fas fa-user-tag mr-1"></i>Perfil/Permissão
                                                </label>
                                                <select wire:model="selectedRoles.{{ $tenant->id }}" 
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                                    <option value="">Selecione...</option>
                                                    @foreach($roles as $role)
                                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            
                            <p class="text-xs text-gray-500 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                O utilizador terá acesso apenas às empresas selecionadas com as permissões definidas
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                            <button type="button" wire:click="closeModal" 
                                    class="px-6 py-2.5 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button type="submit" 
                                    class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl font-semibold hover:shadow-lg transition">
                                <i class="fas fa-save mr-2"></i>{{ $editingUserId ? 'Atualizar' : 'Criar' }} Utilizador
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
    
    {{-- Modal de Exclusão --}}
    @include('livewire.users.partials.delete-modal')
</div>
