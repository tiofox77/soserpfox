<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-shield-alt mr-3 text-purple-600"></i>
                    Roles e Permissões
                </h2>
                <p class="text-gray-600 mt-1">Gestão de permissões e controlo de acesso</p>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="bg-white rounded-xl shadow-lg mb-6">
        <div class="flex border-b border-gray-200">
            <button wire:click="$set('activeTab', 'roles')" 
                    class="px-6 py-4 font-medium {{ $activeTab === 'roles' ? 'text-purple-600 border-b-2 border-purple-600' : 'text-gray-600 hover:text-gray-800' }}">
                <i class="fas fa-user-tag mr-2"></i>Roles
            </button>
            <button wire:click="$set('activeTab', 'permissions')" 
                    class="px-6 py-4 font-medium {{ $activeTab === 'permissions' ? 'text-purple-600 border-b-2 border-purple-600' : 'text-gray-600 hover:text-gray-800' }}">
                <i class="fas fa-key mr-2"></i>Permissões
            </button>
            <button wire:click="$set('activeTab', 'assign')" 
                    class="px-6 py-4 font-medium {{ $activeTab === 'assign' ? 'text-purple-600 border-b-2 border-purple-600' : 'text-gray-600 hover:text-gray-800' }}">
                <i class="fas fa-users mr-2"></i>Atribuir Roles
            </button>
        </div>

        {{-- Tab Content --}}
        <div class="p-6">
            @if($activeTab === 'roles')
                {{-- ROLES TAB --}}
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-800">Gestão de Roles</h3>
                        <button wire:click="openRoleModal" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                            <i class="fas fa-plus mr-2"></i>Novo Role
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($roles as $role)
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-6 border-2 border-purple-200 hover:shadow-lg transition">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-purple-600 rounded-full flex items-center justify-center text-white mr-3">
                                        <i class="fas fa-user-shield text-xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-800">{{ $role->name }}</h4>
                                        <p class="text-xs text-gray-600">{{ $role->description ?? 'Sem descrição' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-sm text-gray-600 mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-key mr-2 text-purple-600"></i>
                                    <span>{{ $role->permissions_count }} permissões</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-users mr-2 text-purple-600"></i>
                                    <span>{{ $role->users_count }} utilizadores</span>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button wire:click="openRoleModal({{ $role->id }})" class="flex-1 px-3 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition text-sm">
                                    <i class="fas fa-edit mr-1"></i>Editar
                                </button>
                                <button wire:click="confirmDeleteRole({{ $role->id }})" class="flex-1 px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition text-sm">
                                    <i class="fas fa-trash mr-1"></i>Eliminar
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

            @elseif($activeTab === 'permissions')
                {{-- PERMISSIONS TAB --}}
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-800">Gestão de Permissões</h3>
                        <button wire:click="openPermissionModal" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                            <i class="fas fa-plus mr-2"></i>Nova Permissão
                        </button>
                    </div>

                    @foreach($permissions as $module => $perms)
                    <div class="mb-6 bg-white rounded-lg border-2 border-gray-200 overflow-hidden">
                        <div class="bg-gradient-to-r from-purple-600 to-purple-700 text-white px-4 py-3 font-bold">
                            <i class="fas fa-cube mr-2"></i>{{ strtoupper($module) }}
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($perms as $permission)
                                <div class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                    <i class="fas fa-key text-purple-600 mr-3"></i>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-800">{{ $permission->name }}</p>
                                        <p class="text-xs text-gray-600">{{ $permission->description ?? '' }}</p>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

            @else
                {{-- ASSIGN TAB --}}
                <div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Atribuir Roles a Utilizadores</h3>

                    <div class="grid grid-cols-1 gap-4">
                        @foreach($users as $user)
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold mr-4">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800">{{ $user->name }}</p>
                                    <p class="text-sm text-gray-600">{{ $user->email }}</p>
                                    <div class="flex gap-2 mt-1">
                                        @forelse($user->roles as $role)
                                            <span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-full">{{ $role->name }}</span>
                                        @empty
                                            <span class="px-2 py-1 bg-gray-200 text-gray-600 text-xs rounded-full">Sem roles</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                            <button wire:click="openAssignModal({{ $user->id }})" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                                <i class="fas fa-user-cog mr-2"></i>Gerir Roles
                            </button>
                        </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Create/Edit Role --}}
    @if($showRoleModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click.self="$set('showRoleModal', false)">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="bg-gradient-to-r from-purple-600 to-purple-700 text-white px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold">
                    <i class="fas fa-user-tag mr-2"></i>{{ $editingRole ? 'Editar Role' : 'Novo Role' }}
                </h3>
                <button wire:click="$set('showRoleModal', false)" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Nome do Role *</label>
                    <input type="text" wire:model="roleName" class="w-full rounded-lg border-gray-300" placeholder="Ex: Vendedor">
                    @error('roleName') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Descrição</label>
                    <textarea wire:model="roleDescription" class="w-full rounded-lg border-gray-300" rows="2" placeholder="Descrição do role"></textarea>
                </div>

                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-bold text-gray-700">Permissões</label>
                        <div class="flex gap-2">
                            <button type="button" 
                                    wire:click="selectAllPermissions"
                                    class="text-xs px-3 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200">
                                <i class="fas fa-check-double mr-1"></i>Selecionar Todas
                            </button>
                            <button type="button" 
                                    wire:click="deselectAllPermissions"
                                    class="text-xs px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200">
                                <i class="fas fa-times mr-1"></i>Desmarcar Todas
                            </button>
                        </div>
                    </div>
                    <div class="max-h-96 overflow-y-auto border-2 border-gray-200 rounded-lg p-4 bg-gray-50">
                        @foreach($allPermissions->groupBy(function($permission) { return explode('.', $permission->name)[0]; }) as $module => $perms)
                        <div class="mb-4 bg-white rounded-lg p-3 shadow-sm" x-data="{ moduleExpanded: true }">
                            <div class="flex items-center justify-between mb-2 cursor-pointer" @click="moduleExpanded = !moduleExpanded">
                                <div class="flex items-center gap-2">
                                    <i class="fas" :class="moduleExpanded ? 'fa-chevron-down' : 'fa-chevron-right'" class="text-purple-600"></i>
                                    <div class="bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800 px-4 py-2 rounded-lg font-bold flex-1">
                                        <i class="fas fa-cube mr-2"></i>{{ strtoupper($module) }} ({{ count($perms) }})
                                    </div>
                                </div>
                                <button type="button"
                                        wire:click="toggleModulePermissions('{{ $module }}')"
                                        @click.stop
                                        class="text-xs px-2 py-1 bg-purple-100 text-purple-700 rounded hover:bg-purple-200">
                                    <i class="fas fa-check mr-1"></i>Todos
                                </button>
                            </div>
                            <div x-show="moduleExpanded" x-collapse class="grid grid-cols-1 gap-1 mt-2">
                                @foreach($perms as $permission)
                                <label class="flex items-center p-2 hover:bg-purple-50 rounded cursor-pointer border border-transparent hover:border-purple-200 transition">
                                    <input type="checkbox" 
                                           wire:model="selectedPermissions" 
                                           value="{{ $permission->id }}" 
                                           data-module="{{ $module }}"
                                           class="w-5 h-5 rounded text-purple-600 border-gray-300 focus:ring-purple-500">
                                    <div class="ml-3 flex-1">
                                        <span class="text-sm font-medium text-gray-800">{{ $permission->name }}</span>
                                        @if($permission->description)
                                        <p class="text-xs text-gray-500">{{ $permission->description }}</p>
                                        @endif
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-2">
                    <button wire:click="saveRole" class="flex-1 px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-bold">
                        <i class="fas fa-save mr-2"></i>Guardar
                    </button>
                    <button wire:click="$set('showRoleModal', false)" class="px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Assign Roles --}}
    @if($showAssignModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click.self="$set('showAssignModal', false)">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div class="bg-gradient-to-r from-purple-600 to-purple-700 text-white px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold">
                    <i class="fas fa-user-cog mr-2"></i>Atribuir Roles
                </h3>
                <button wire:click="$set('showAssignModal', false)" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                <div class="space-y-2 mb-6">
                    @foreach($roles as $role)
                    <label class="flex items-center p-3 bg-gray-50 hover:bg-purple-50 rounded-lg cursor-pointer transition">
                        <input type="checkbox" wire:model="selectedRoles" value="{{ $role->id }}" class="rounded text-purple-600">
                        <div class="ml-3 flex-1">
                            <p class="font-bold text-gray-800">{{ $role->name }}</p>
                            <p class="text-xs text-gray-600">{{ $role->description }}</p>
                        </div>
                    </label>
                    @endforeach
                </div>

                <div class="flex gap-2">
                    <button wire:click="assignRoles" class="flex-1 px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-bold">
                        <i class="fas fa-check mr-2"></i>Atribuir
                    </button>
                    <button wire:click="$set('showAssignModal', false)" class="px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Delete Confirmation --}}
    @if($showDeleteModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click.self="$set('showDeleteModal', false)">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
            <div class="text-center mb-4">
                <i class="fas fa-exclamation-triangle text-6xl text-red-600 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Confirmar Eliminação</h3>
                <p class="text-gray-600">Tem certeza que deseja eliminar este {{ $deletingType === 'role' ? 'role' : 'permissão' }}?</p>
            </div>

            <div class="flex gap-2">
                {{-- BUG-U12 FIX: Chamar método correcto baseado no tipo --}}
                <button wire:click="deleteRole" class="flex-1 px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-bold">
                    <i class="fas fa-trash mr-2"></i>Sim, Eliminar
                </button>
                <button wire:click="$set('showDeleteModal', false)" class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
