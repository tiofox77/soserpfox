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

    {{-- Os cartões de estado: contagem e filtro ao mesmo tempo.

         Contados ANTES do filtro de estado — clicar num cartão não pode zerar
         os outros, senão nunca se conseguia trocar de cartão. --}}
    <div class="mb-4 grid grid-cols-2 md:grid-cols-5 gap-3">
        @foreach([
            'activa'     => ['A facturar',  'green',  'fa-file-invoice-dollar'],
            'a_usar'     => ['A usar',      'blue',   'fa-computer'],
            'a_montar'   => ['A montar',    'amber',  'fa-screwdriver-wrench'],
            'adormecida' => ['Adormecidas', 'orange', 'fa-moon'],
            'vazia'      => ['Nunca usaram','red',    'fa-ghost'],
        ] as $chave => [$rotulo, $cor, $icone])
            <button wire:click="filtrarPorEstado('{{ $chave }}')"
                    class="text-left rounded-xl p-3 border-2 transition
                           {{ $filtroEstado === $chave
                              ? 'border-' . $cor . '-500 bg-' . $cor . '-50 shadow'
                              : 'border-transparent bg-white shadow-sm hover:shadow' }}">
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-extrabold text-{{ $cor }}-600">{{ $contagens[$chave] ?? 0 }}</span>
                    <i class="fas {{ $icone }} text-{{ $cor }}-400"></i>
                </div>
                <p class="text-xs font-bold text-gray-600 mt-1">{{ $rotulo }}</p>
                @if($filtroEstado === $chave)
                    <p class="text-[10px] text-{{ $cor }}-600 font-semibold">a filtrar — clique para largar</p>
                @endif
            </button>
        @endforeach
    </div>

    <!-- Search and Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[220px] relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Nome, email, NIF..."
                       class="w-full pl-11 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            </div>

            <select wire:model.live="filtroPlano" title="Filtrar pelo plano em vigor"
                    class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">Todos os planos</option>
                @foreach($planosParaFiltro as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="filtroActivo" title="Activas ou desactivadas"
                    class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">Activas e desactivadas</option>
                <option value="1">Só activas</option>
                <option value="0">Só desactivadas</option>
            </select>

            <select wire:model.live="ordenar" title="Ordenação"
                    class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                @foreach(\App\Livewire\SuperAdmin\Tenants::ORDENACOES as $chave => $rotulo)
                    <option value="{{ $chave }}">{{ $rotulo }}</option>
                @endforeach
            </select>

            <select wire:model.live="porPagina" title="Empresas por página"
                    class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                <option value="10">10 / página</option>
                <option value="25">25 / página</option>
                <option value="50">50 / página</option>
            </select>

            @if($this->temFiltros)
                <button wire:click="limparFiltros"
                        class="px-3 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">
                    <i class="fas fa-times mr-1"></i>Limpar
                </button>
            @endif
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
                            {{-- Quatro colunas e não três: entrou o NIF, que é por onde a AGT
                                 identifica a empresa e o primeiro número que se pede ao telefone
                                 quando um cliente liga. Estava só dentro da ficha de edição. --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 mb-3">
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
                                    <span class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-id-card text-amber-600 text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-gray-500 font-medium">{{ __('NIF') }}</p>
                                        @if($tenant->nif)
                                            {{-- font-mono: um NIF lê-se dígito a dígito, e é assim que se
                                                 confere ao telefone sem trocar um 5 por um 6. --}}
                                            <p class="text-sm text-gray-900 font-mono">{{ $tenant->nif }}</p>
                                            @unless(preg_match('/^5\d{8,9}$/', preg_replace('/\D/', '', $tenant->nif)))
                                                {{-- Dito aqui porque é aqui que se vê: um NIF que não começa
                                                     por 5 não é de empresa, e é ele que vai nos documentos
                                                     comunicados à AGT. As empresas antigas ficaram com o que
                                                     escreveram antes de haver validação no registo. --}}
                                                <p class="text-[11px] text-amber-700 font-semibold">
                                                    <i class="fas fa-triangle-exclamation mr-1"></i>{{ __('Não é NIF de empresa') }}
                                                </p>
                                            @endunless
                                        @else
                                            <p class="text-sm text-gray-400">{{ __('por preencher') }}</p>
                                        @endif
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
                                    {{ __(":n utilizadores no plano", ["n" => $tenant->max_users]) }}
                                </span>
                                <span class="inline-flex items-center px-3 py-1.5 bg-cyan-50 text-cyan-700 rounded-lg text-xs font-medium">
                                    <i class="fas fa-database mr-1.5"></i>
                                    {{ __(":n MB de espaço", ["n" => $tenant->max_storage_mb]) }}
                                </span>
                                @if($tenant->modules->count() > 0)
                                    <span class="inline-flex items-center px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-medium">
                                        <i class="fas fa-puzzle-piece mr-1.5"></i>
                                        {{ __(":n modulos", ["n" => $tenant->modules->where("pivot.is_active", true)->count()]) }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-xs font-medium">
                                    <i class="fas fa-users mr-1.5"></i>
                                    {{-- "no plano" no outro crachá e "criados" aqui: os dois números
                                         apareciam lado a lado como "5 utilizadores" e "1 users", e liam-se
                                         como uma contradição em vez de limite e realidade. --}}
                                    {{ __(':n criados', ['n' => $tenant->users_count ?? 0]) }}
                                </span>
                            </div>

                            {{-- Está viva ou é só uma linha na base?

                                 A lista dava nome, plano e número de utilizadores,
                                 e com isso um cliente que factura todos os dias e
                                 outro que se registou e nunca mais voltou são duas
                                 linhas iguais. --}}
                            @php $v = $sinais[$tenant->id] ?? null; @endphp
                            @if($v)
                                <div class="flex flex-wrap items-center gap-2 mt-2 pt-2 border-t border-gray-100">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-{{ $v->estado['cor'] }}-100 text-{{ $v->estado['cor'] }}-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-{{ $v->estado['cor'] }}-500 mr-1.5"></span>
                                        {{ $v->estado['texto'] }}
                                    </span>

                                    {{-- Em que pé está a subscrição.

                                         A lista dizia o plano e mais nada.
                                         "Pacote Vendas · Monthly" não responde
                                         ao que interessa a quem gere: está em
                                         teste? falta quanto? já passou do prazo
                                         e continua a usar? Sem isto, um teste
                                         que expira passa despercebido até
                                         alguém reparar por acaso. --}}
                                    @php $sub = \App\Support\EstadoDaSubscricao::para($tenant); @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-{{ $sub['cor'] }}-100 text-{{ $sub['cor'] }}-700"
                                          title="{{ $sub['detalhe'] }}">
                                        <i class="fas {{ $sub['icone'] }} mr-1.5"></i>{{ $sub['rotulo'] }}
                                        @if(!is_null($sub['dias']))
                                            <span class="ml-1 font-normal opacity-90">
                                                · {{ \App\Support\EstadoDaSubscricao::quantoFalta($sub['dias']) }}
                                                @if($sub['ate'])
                                                    ({{ $sub['ate'] }})
                                                @endif
                                            </span>
                                        @endif
                                    </span>

                                    <span class="text-xs text-gray-600" title="Facturas emitidas nos últimos 30 dias">
                                        <i class="fas fa-file-invoice text-gray-400 mr-1"></i>
                                        <strong>{{ $v->facturas_30d }}</strong> factura(s)/30d
                                    </span>

                                    <span class="text-xs text-gray-600" title="Artigos no catálogo">
                                        <i class="fas fa-box text-gray-400 mr-1"></i>
                                        <strong>{{ number_format($v->artigos, 0, ',', '.') }}</strong> artigo(s)
                                    </span>

                                    <span class="text-xs text-gray-600" title="Movimentos de stock nos últimos 30 dias">
                                        <i class="fas fa-right-left text-gray-400 mr-1"></i>
                                        <strong>{{ $v->movimentos_30d }}</strong> mov./30d
                                    </span>

                                    <span class="text-xs text-gray-600" title="Utilizadores que entraram nos últimos 30 dias">
                                        <i class="fas fa-user-clock text-gray-400 mr-1"></i>
                                        <strong>{{ $v->entraram_30d }}</strong>/{{ $v->utilizadores }} activo(s)
                                    </span>

                                    <span class="text-xs {{ $v->ultima_entrada && $v->ultima_entrada->gt(now()->subDays(30)) ? 'text-gray-600' : 'text-red-600 font-semibold' }}"
                                          title="Última vez que alguém desta empresa entrou">
                                        <i class="fas fa-right-to-bracket text-gray-400 mr-1"></i>
                                        {{ $v->ultima_entrada ? 'entrou ' . $v->ultima_entrada->diffForHumans(short: true) : 'nunca entrou' }}
                                    </span>
                                </div>
                            @endif
                            
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
                                    <i class="fas fa-box-archive mr-1.5"></i>{{ __('Suspender') }}
                                </button>
                                {{-- O botao acima chama o delete() do modelo, e como o Tenant usa
                                     SoftDeletes isso e uma SUSPENSAO: sai da lista e fica na base.
                                     Dizia "Excluir", pelo que ninguem sabia — e ninguem tinha como
                                     limpar as empresas de lixo. Este segundo apaga mesmo. --}}
                                <button wire:click="abrirApagarDefinitivo({{ $tenant->id }})"
                                        class="inline-flex items-center px-3 py-2 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg text-sm font-semibold transition border border-red-200"
                                        title="{{ __('Apagar da base de dados, sem retorno') }}">
                                    <i class="fas fa-trash mr-1.5"></i>{{ __('Apagar') }}
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

    {{-- Apagar em definitivo. Vermelho, com o que se perde à vista e o nome a
         escrever à mão — não é burocracia: é a diferença entre carregar num
         botão por engano e decidir. Isto não se desfaz. --}}
    @if($apagarDefinitivoId)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60" wire:click="fecharApagarDefinitivo"></div>

            <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
                <div class="bg-red-600 px-6 py-4">
                    <h3 class="text-white font-bold flex items-center gap-2">
                        <i class="fas fa-triangle-exclamation"></i>{{ __('Apagar em definitivo') }}
                    </h3>
                </div>

                <div class="p-6">
                    @if($apagarDefinitivoImpedido)
                        <p class="text-sm text-gray-800 leading-relaxed">{{ $apagarDefinitivoImpedido }}</p>
                    @else
                        <p class="text-sm text-gray-800 leading-relaxed">
                            {{ __('Vai apagar :nome e tudo o que é dela. Não há como voltar atrás.', ['nome' => $apagarDefinitivoNome]) }}
                        </p>

                        <ul class="mt-3 text-sm text-gray-600 space-y-0.5">
                            @foreach($apagarDefinitivoPerdas as $rotulo => $quantos)
                                <li>· {{ $quantos }} {{ __($rotulo) }}</li>
                            @endforeach
                        </ul>

                        <label class="block text-sm font-semibold text-gray-700 mt-5 mb-1">
                            {{ __('Escreva o nome da empresa para confirmar:') }}
                        </label>
                        <p class="text-xs text-gray-400 mb-2 font-mono">{{ $apagarDefinitivoNome }}</p>
                        <input type="text" wire:model="apagarDefinitivoConfirmacao"
                               class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:border-red-500 focus:outline-none">
                        @error('apagarDefinitivoConfirmacao')
                            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    @endif
                </div>

                <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
                    <button wire:click="fecharApagarDefinitivo"
                            class="px-4 py-2 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-100">
                        {{ __('Cancelar') }}
                    </button>
                    @unless($apagarDefinitivoImpedido)
                        <button wire:click="confirmarApagarDefinitivo" wire:loading.attr="disabled"
                                class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold disabled:opacity-50">
                            {{ __('Apagar para sempre') }}
                        </button>
                    @endunless
                </div>
            </div>
        </div>
    @endif
</div>
