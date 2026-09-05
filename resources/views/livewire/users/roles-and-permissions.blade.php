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
                                    <div class="flex-1 min-w-0">
                                        {{-- Em português primeiro; o nome técnico fica em pequeno. --}}
                                        <p class="text-sm font-medium text-gray-800">{{ \App\Support\CatalogoDePermissoes::rotulo($permission) }}</p>
                                        <p class="text-[10px] text-gray-400 font-mono truncate">{{ $permission->name }}</p>
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
    {{-- O modal do papel: largo, por módulo, em português.

         À esquerda os grupos que ESTA empresa tem (núcleo + módulos activos),
         cada um com «marcadas / total». À direita as permissões do grupo aberto,
         agrupadas por entidade, com o rótulo em português e o nome técnico em
         pequeno. Pesquisa, «Todas do módulo», «Só consulta» e «Copiar de outro
         papel» tratam dos casos em que marcar uma a uma é castigo.

         O estado que interessa (grupo aberto, pesquisa, marcadas) vive no
         componente, não em Alpine: o modal entra e sai por morph e um x-data
         aqui dentro perdia-se. --}}
    @if($showRoleModal)
    @php
        $grupos = $this->grupos;
        $linhas = $this->linhasDoModulo;
        $slugAberto = $moduloActivo ?: array_key_first($grupos);
        $grupoAberto = $grupos[$slugAberto] ?? null;
        $totalVisivel = array_sum(array_column($grupos, 'total'));
        $totalMarcado = array_sum(array_column($grupos, 'marcadas'));
    @endphp
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-2 sm:p-4" wire:click.self="$set('showRoleModal', false)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[94vh] flex flex-col overflow-hidden">

            {{-- Cabeçalho: nome e descrição na mesma linha, com o contador ao lado --}}
            <div class="bg-gradient-to-r from-purple-600 to-purple-700 text-white px-6 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-bold flex items-center">
                            <i class="fas fa-user-tag mr-2"></i>{{ $editingRole ? __('Editar Papel') : __('Novo Papel') }}
                        </h3>
                        <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] uppercase tracking-wide text-purple-200 mb-1">{{ __('Nome do papel') }} *</label>
                                <input type="text" wire:model="roleName"
                                       class="w-full rounded-lg border-0 text-gray-900 text-sm px-3 py-2 focus:ring-2 focus:ring-white"
                                       placeholder="{{ __('Ex.: Vendedor de loja') }}">
                                @error('roleName') <span class="text-yellow-200 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[11px] uppercase tracking-wide text-purple-200 mb-1">{{ __('Para que serve') }}</label>
                                <input type="text" wire:model="roleDescription"
                                       class="w-full rounded-lg border-0 text-gray-900 text-sm px-3 py-2 focus:ring-2 focus:ring-white"
                                       placeholder="{{ __('Ex.: vende ao balcão e emite faturas, não vê as dos colegas') }}">
                            </div>
                        </div>
                    </div>
                    <button wire:click="$set('showRoleModal', false)" class="text-white/80 hover:text-white shrink-0" title="{{ __('Fechar') }}">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            {{-- Corpo: grupos à esquerda, permissões à direita --}}
            <div class="flex-1 min-h-0 grid grid-cols-1 md:grid-cols-4">

                {{-- Grupos --}}
                <aside class="md:col-span-1 border-r border-gray-200 bg-gray-50 overflow-y-auto">
                    <div class="px-4 pt-4 pb-2 flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-gray-500">{{ __('Módulos') }}</span>
                        <span class="text-[11px] text-gray-500">{{ $totalMarcado }} / {{ $totalVisivel }}</span>
                    </div>
                    <nav class="px-2 pb-3 space-y-1">
                        @foreach($grupos as $g)
                        <button type="button" wire:click="escolherModulo('{{ $g['slug'] }}')"
                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition
                                       {{ $g['slug'] === $slugAberto ? 'bg-white shadow border border-purple-200' : 'hover:bg-white/70' }}">
                            <i class="fas {{ $g['icone'] }} w-5 text-center {{ $g['slug'] === $slugAberto ? 'text-purple-600' : 'text-gray-400' }}"></i>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-semibold text-gray-800 truncate">{{ $g['nome'] }}</span>
                                <span class="block text-[11px] text-gray-500">{{ $g['marcadas'] }} de {{ $g['total'] }}</span>
                            </span>
                            @if($g['marcadas'] === $g['total'])
                                <i class="fas fa-check-circle text-green-500" title="{{ __('Tudo marcado') }}"></i>
                            @elseif($g['marcadas'] > 0)
                                <span class="w-2.5 h-2.5 rounded-full bg-purple-400" title="{{ __('Parcial') }}"></span>
                            @endif
                        </button>
                        @endforeach
                    </nav>

                    {{-- Atalhos que valem para o papel inteiro --}}
                    <div class="px-4 py-3 border-t border-gray-200 space-y-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">{{ __('Atalhos') }}</p>
                        <div class="flex gap-2">
                            <button type="button" wire:click="selectAllPermissions" class="flex-1 text-xs px-2 py-1.5 bg-green-100 text-green-700 rounded-lg hover:bg-green-200">
                                <i class="fas fa-check-double mr-1"></i>{{ __('Tudo') }}
                            </button>
                            <button type="button" wire:click="deselectAllPermissions" class="flex-1 text-xs px-2 py-1.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                                <i class="fas fa-eraser mr-1"></i>{{ __('Nada') }}
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <select wire:model="copiarDe" class="flex-1 text-xs rounded-lg border-gray-300 py-1.5">
                                <option value="">{{ __('Copiar de outro papel…') }}</option>
                                @foreach($roles as $outro)
                                    @if($outro->id !== $editingRole)
                                    <option value="{{ $outro->id }}">{{ $outro->name }} ({{ $outro->permissions_count }})</option>
                                    @endif
                                @endforeach
                            </select>
                            <button type="button" wire:click="copiarDePapel" class="text-xs px-2 py-1.5 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200" title="{{ __('Copiar') }}">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </aside>

                {{-- Permissões do grupo aberto --}}
                <section class="md:col-span-3 flex flex-col min-h-0">
                    @if($grupoAberto)
                    <div class="px-5 py-3 border-b border-gray-200 flex flex-wrap items-center gap-3">
                        <h4 class="font-bold text-gray-800 flex items-center">
                            <i class="fas {{ $grupoAberto['icone'] }} text-purple-600 mr-2"></i>{{ $grupoAberto['nome'] }}
                            <span class="ml-2 text-xs font-normal text-gray-500">{{ $grupoAberto['marcadas'] }} de {{ $grupoAberto['total'] }}</span>
                        </h4>
                        <div class="flex-1 min-w-[12rem]">
                            <input type="search" wire:model.live.debounce.300ms="pesquisa"
                                   class="w-full text-sm rounded-lg border-gray-300 py-1.5"
                                   placeholder="{{ __('Procurar neste módulo…') }}">
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="toggleModulo('{{ $grupoAberto['slug'] }}')"
                                    class="text-xs px-3 py-1.5 rounded-lg {{ $grupoAberto['marcadas'] === $grupoAberto['total'] ? 'bg-gray-200 text-gray-700' : 'bg-purple-600 text-white hover:bg-purple-700' }}">
                                <i class="fas {{ $grupoAberto['marcadas'] === $grupoAberto['total'] ? 'fa-square' : 'fa-check-square' }} mr-1"></i>
                                {{ $grupoAberto['marcadas'] === $grupoAberto['total'] ? __('Desmarcar módulo') : __('Todo o módulo') }}
                            </button>
                            <button type="button" wire:click="soLeituraDoModulo('{{ $grupoAberto['slug'] }}')"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100"
                                    title="{{ __('Só ver: sem criar, editar nem eliminar') }}">
                                <i class="fas fa-eye mr-1"></i>{{ __('Só consulta') }}
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto px-5 py-4">
                        @forelse($linhas as $entidade => $permissoes)
                        <div class="mb-4">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1.5">{{ $entidade }}</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-1.5">
                                @foreach($permissoes as $p)
                                <label class="flex items-start gap-2.5 p-2.5 rounded-lg border cursor-pointer transition
                                              {{ in_array($p['id'], array_map('intval', (array) $selectedPermissions), true) ? 'border-purple-300 bg-purple-50' : 'border-gray-200 hover:border-purple-200 hover:bg-purple-50/40' }}">
                                    <input type="checkbox" wire:model.live="selectedPermissions" value="{{ $p['id'] }}"
                                           class="mt-0.5 w-4 h-4 rounded text-purple-600 border-gray-300 focus:ring-purple-500">
                                    <span class="min-w-0">
                                        <span class="block text-sm text-gray-800 leading-tight">{{ $p['rotulo'] }}</span>
                                        <span class="block text-[10px] text-gray-400 font-mono truncate">{{ $p['nome'] }}</span>
                                    </span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @empty
                        <p class="text-sm text-gray-500 py-8 text-center">
                            <i class="fas fa-search mr-1"></i>{{ __('Nada com esse nome neste módulo.') }}
                        </p>
                        @endforelse
                    </div>
                    @else
                    <p class="text-sm text-gray-500 p-8 text-center">{{ __('Esta empresa não tem módulos activos.') }}</p>
                    @endif
                </section>
            </div>

            {{-- Rodapé fixo --}}
            <div class="px-6 py-3 border-t border-gray-200 bg-white flex items-center justify-between gap-3">
                <p class="text-sm text-gray-600">
                    <i class="fas fa-key text-purple-600 mr-1"></i>
                    <strong>{{ $totalMarcado }}</strong> {{ __('permissões marcadas') }}
                    @if($totalMarcado === 0)
                        <span class="text-yellow-700 ml-2"><i class="fas fa-exclamation-triangle mr-1"></i>{{ __('Um papel sem permissões não deixa fazer nada.') }}</span>
                    @endif
                </p>
                <div class="flex gap-2">
                    <button wire:click="$set('showRoleModal', false)" class="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        {{ __('Cancelar') }}
                    </button>
                    <button wire:click="saveRole" wire:loading.attr="disabled" class="px-6 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-bold disabled:opacity-50">
                        <i class="fas fa-save mr-2"></i>{{ __('Guardar') }}
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
