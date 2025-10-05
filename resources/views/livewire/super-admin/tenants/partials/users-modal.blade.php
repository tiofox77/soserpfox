<!-- Modal de Gestão de Usuários -->
@if($showUsersModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showUsersModal') }" x-show="show" x-cloak>
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="show = false"></div>
        
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                <!-- Header -->
                <div class="bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-users mr-3"></i>Gerenciar Usuários
                        </h3>
                        <button wire:click="closeUsersModal" class="text-white hover:text-gray-200 transition">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <!-- Botão Adicionar Usuário -->
                    <div class="mb-6 flex justify-between items-center">
                        <div>
                            <h4 class="text-lg font-bold text-gray-900">Usuários deste Tenant</h4>
                            <p class="text-sm text-gray-500">{{ count($tenantUsers) }} usuário(s) cadastrado(s)</p>
                        </div>
                        <button wire:click="openAddUserModal" class="px-4 py-2 bg-orange-600 text-white rounded-xl hover:bg-orange-700 font-semibold transition">
                            <i class="fas fa-user-plus mr-2"></i>Adicionar Usuário
                        </button>
                    </div>
                    
                    <!-- Lista de Usuários -->
                    <div class="space-y-3">
                        @forelse($tenantUsers as $user)
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition">
                                <div class="flex items-center space-x-4 flex-1">
                                    <!-- Avatar -->
                                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center text-white font-bold">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                    
                                    <!-- Info -->
                                    <div class="flex-1">
                                        <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                    </div>
                                    
                                    <!-- Role -->
                                    <div class="min-w-[200px]">
                                        <select wire:change="updateUserRole({{ $user->id }}, $event.target.value)" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 text-sm">
                                            <option value="">Sem Role</option>
                                            @foreach($roles as $role)
                                                <option value="{{ $role->id }}" {{ $user->current_role && $user->current_role->id == $role->id ? 'selected' : '' }}>
                                                    {{ $role->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <!-- Data de Entrada -->
                                    <div class="text-sm text-gray-500 min-w-[120px]">
                                        <i class="fas fa-calendar mr-1"></i>
                                        {{ $user->pivot->joined_at ? \Carbon\Carbon::parse($user->pivot->joined_at)->format('d/m/Y') : 'N/A' }}
                                    </div>
                                </div>
                                
                                <!-- Ações -->
                                <button wire:click="removeUserFromTenant({{ $user->id }})" 
                                        wire:confirm="Tem certeza que deseja remover este usuário?"
                                        class="ml-4 px-3 py-2 bg-red-50 text-red-700 rounded-lg hover:bg-red-100 transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        @empty
                            <div class="p-12 text-center">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-users text-gray-400 text-2xl"></i>
                                </div>
                                <p class="text-gray-500 font-medium">Nenhum usuário cadastrado</p>
                                <p class="text-gray-400 text-sm mt-1">Adicione usuários para este tenant</p>
                            </div>
                        @endforelse
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                    <button wire:click="closeUsersModal" class="px-6 py-2 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-100 transition">
                        <i class="fas fa-times mr-2"></i>Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

<!-- Modal de Adicionar Usuário -->
@if($showAddUserModal)
    <div class="fixed inset-0 z-[60] overflow-y-auto" x-data="{ show: @entangle('showAddUserModal') }" x-show="show" x-cloak>
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="show = false"></div>
        
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-white flex items-center">
                            <i class="fas fa-user-plus mr-3"></i>Adicionar Usuário ao Tenant
                        </h3>
                        <button wire:click="closeAddUserModal" class="text-white hover:text-gray-200 transition">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <form wire:submit.prevent="addUserToTenant" class="p-6">
                    <!-- Toggle: Existente vs Novo -->
                    <div class="mb-6 flex gap-4">
                        <label class="relative flex-1 cursor-pointer">
                            <input type="radio" wire:model.live="createNewUser" value="0" class="sr-only peer">
                            <div class="p-4 border-2 border-gray-300 rounded-xl peer-checked:border-blue-500 peer-checked:bg-blue-50 transition">
                                <div class="font-bold text-gray-900 mb-1">
                                    <i class="fas fa-user-check mr-2 text-blue-500"></i>Usuário Existente
                                </div>
                                <p class="text-xs text-gray-500">Adicionar usuário já cadastrado</p>
                            </div>
                        </label>
                        
                        <label class="relative flex-1 cursor-pointer">
                            <input type="radio" wire:model.live="createNewUser" value="1" class="sr-only peer">
                            <div class="p-4 border-2 border-gray-300 rounded-xl peer-checked:border-green-500 peer-checked:bg-green-50 transition">
                                <div class="font-bold text-gray-900 mb-1">
                                    <i class="fas fa-user-plus mr-2 text-green-500"></i>Novo Usuário
                                </div>
                                <p class="text-xs text-gray-500">Criar e adicionar novo usuário</p>
                            </div>
                        </label>
                    </div>
                    
                    @if($createNewUser == 0 || $createNewUser === false || !$createNewUser)
                        <!-- Selecionar Usuário Existente -->
                        <div class="mb-4" key="existing-user-fields">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user text-blue-500 mr-2"></i>Selecionar Usuário *
                            </label>
                            <select wire:model="selectedUserId" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                                <option value="">Selecione um usuário...</option>
                                @foreach($availableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                @endforeach
                            </select>
                            @error('selectedUserId') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    @endif
                    
                    @if($createNewUser == 1 || $createNewUser === true)
                        <!-- Criar Novo Usuário -->
                        <div class="space-y-4 mb-4" key="new-user-fields">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-signature text-green-500 mr-2"></i>Nome *
                                </label>
                                <input wire:model="newUserName" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
                                @error('newUserName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-envelope text-green-500 mr-2"></i>Email *
                                </label>
                                <input wire:model="newUserEmail" type="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
                                @error('newUserEmail') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-lock text-green-500 mr-2"></i>Senha *
                                </label>
                                <input wire:model="newUserPassword" type="password" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
                                @error('newUserPassword') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @endif
                    
                    <!-- Role -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user-tag text-orange-500 mr-2"></i>Role *
                        </label>
                        <select wire:model="selectedRoleId" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500">
                            <option value="">Selecione uma role...</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                        @error('selectedRoleId') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Footer -->
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" wire:click="closeAddUserModal" class="px-6 py-2 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-600 to-cyan-600 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-cyan-700 shadow-lg transition">
                            <i class="fas fa-check mr-2"></i>Adicionar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
