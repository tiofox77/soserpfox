<div>
    {{-- Header com gradiente (padrão superadmin) --}}
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                <i class="fas fa-key text-2xl"></i>
            </div>
            <div>
                <h2 class="text-2xl font-bold">Licenciamento Offline</h2>
                <p class="text-indigo-100 text-sm">Emitir licenças, publicar versões e rollout por-tenant</p>
            </div>
        </div>
    </div>

    @if (session('ok'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl flex items-center">
            <i class="fas fa-check-circle mr-2"></i>{{ session('ok') }}
        </div>
    @endif

    @if (!$criptoOk)
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
            <i class="fas fa-circle-exclamation mr-2"></i>
            <strong>Cripto indisponível neste servidor.</strong> Falta a extensão PHP <code class="font-mono">sodium</code>
            (ou o pacote <code class="font-mono">paragonie/sodium_compat</code>). Sem ela não é possível assinar licenças.
        </div>
    @endif

    @if (!$chaveLicOk || !$chaveUpdOk)
        <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-sm">
            <i class="fas fa-triangle-exclamation mr-2"></i>
            Chave(s) privada(s) por configurar no <code class="font-mono">.env</code>:
            @if(!$chaveLicOk) <code class="font-mono">LICENSE_SIGNING_KEY</code> @endif
            @if(!$chaveUpdOk) <code class="font-mono">LICENSE_UPDATE_SIGNING_KEY</code> @endif
        </div>
    @endif

    {{-- ── Clientes offline (instalações on-premise) ──────────────── --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center">
                <i class="fas fa-server text-indigo-600 text-lg mr-2"></i>
                <h3 class="text-lg font-bold text-gray-900">Clientes offline</h3>
            </div>
            <span class="text-xs text-gray-500">{{ $instalacoes->count() }} instalação(ões)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="text-left text-xs font-bold text-gray-500 uppercase">
                        <th class="py-3 pr-4">Empresa</th>
                        <th class="py-3 pr-4">Plano / Módulos</th>
                        <th class="py-3 pr-4">Util.</th>
                        <th class="py-3 pr-4">Versão</th>
                        <th class="py-3 pr-4">Último contacto</th>
                        <th class="py-3 pr-4">Licença</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($instalacoes as $i)
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 pr-4">
                                <div class="font-semibold text-gray-900">{{ $i->tenant->name ?? ('#'.$i->tenant_id) }}</div>
                                <div class="text-xs text-gray-400 font-mono">
                                    {{ $i->fingerprint ? Str::limit($i->fingerprint, 16) : 'licença flutuante' }}
                                </div>
                            </td>
                            <td class="py-3 pr-4 text-sm text-gray-700">
                                {{ $i->plano ?? '—' }}
                                <div class="text-xs text-gray-400">
                                    {{ in_array('*', (array) $i->modulos, true) ? 'todos os módulos' : (count((array) $i->modulos) . ' módulo(s)') }}
                                </div>
                            </td>
                            <td class="py-3 pr-4 text-sm text-gray-700">{{ $i->max_users ?: '∞' }}</td>
                            <td class="py-3 pr-4 text-sm text-gray-600">{{ $i->versao_instalada ?: '—' }}</td>
                            <td class="py-3 pr-4 text-sm">
                                @if($i->ultimo_checkin)
                                    <span class="text-gray-700">{{ $i->ultimo_checkin->diffForHumans() }}</span>
                                    <div class="text-xs text-gray-400">{{ $i->ultimo_checkin->format('d/m/Y H:i') }}</div>
                                @else
                                    <span class="text-gray-400">nunca ligou</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                @php $sit = $i->situacao(); @endphp
                                @if($sit === 'activa')
                                    <span class="bg-green-100 text-green-800 text-xs font-bold px-2.5 py-1 rounded-full">activa</span>
                                @elseif($sit === 'silenciosa')
                                    <span class="bg-amber-100 text-amber-800 text-xs font-bold px-2.5 py-1 rounded-full">silenciosa</span>
                                @elseif($sit === 'expirada')
                                    <span class="bg-red-100 text-red-800 text-xs font-bold px-2.5 py-1 rounded-full">expirada</span>
                                @else
                                    <span class="bg-gray-100 text-gray-600 text-xs font-bold px-2.5 py-1 rounded-full">nunca ligou</span>
                                @endif
                                @if($i->expira_em)
                                    <div class="text-xs text-gray-400 mt-1">expira {{ $i->expira_em->format('d/m/Y') }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-gray-400">
                            <i class="fas fa-server text-3xl mb-2 block"></i>
                            Nenhuma instalação offline ainda. Aparecem aqui assim que emitir uma licença.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-3">
            "Silenciosa" = sem contacto há 15 dias ou mais. O estado real da licença é calculado na própria
            máquina do cliente; aqui vê-se o que o servidor sabe.
        </p>
    </div>

    {{-- ── Pedidos de licença ─────────────────────────────────────── --}}
    @php $pendentes = $pedidos->where('estado', 'pendente'); @endphp
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center">
                <i class="fas fa-inbox text-amber-600 text-lg mr-2"></i>
                <h3 class="text-lg font-bold text-gray-900">Pedidos de licença</h3>
            </div>
            @if($pendentes->count())
                <span class="bg-amber-100 text-amber-800 text-xs font-bold px-3 py-1 rounded-full">
                    {{ $pendentes->count() }} por aprovar
                </span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="text-left text-xs font-bold text-gray-500 uppercase">
                        <th class="py-3 pr-4">Empresa</th>
                        <th class="py-3 pr-4">Contacto</th>
                        <th class="py-3 pr-4">Util.</th>
                        <th class="py-3 pr-4">Máquina</th>
                        <th class="py-3 pr-4">Estado</th>
                        <th class="py-3 pr-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pedidos as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 pr-4">
                                <div class="font-semibold text-gray-900">{{ $p->empresa }}</div>
                                <div class="text-xs text-gray-500">NIF {{ $p->nif ?: '—' }} · {{ $p->created_at?->format('d/m/Y H:i') }}</div>
                            </td>
                            <td class="py-3 pr-4 text-sm text-gray-600">
                                {{ $p->responsavel ?: '—' }}<div class="text-xs text-gray-400">{{ $p->email ?: $p->telefone ?: '' }}</div>
                            </td>
                            <td class="py-3 pr-4 text-sm text-gray-700">{{ $p->utilizadores }}</td>
                            <td class="py-3 pr-4"><span class="font-mono text-xs text-gray-400">{{ Str::limit($p->fingerprint, 12) }}</span></td>
                            <td class="py-3 pr-4">
                                @if($p->estado === 'pendente')
                                    <span class="bg-amber-100 text-amber-800 text-xs font-bold px-2.5 py-1 rounded-full">pendente</span>
                                @elseif($p->estado === 'aprovado')
                                    <span class="bg-green-100 text-green-800 text-xs font-bold px-2.5 py-1 rounded-full">aprovado</span>
                                    @if($p->entregue_em)<div class="text-xs text-gray-400 mt-1">entregue</div>@endif
                                @else
                                    <span class="bg-red-100 text-red-800 text-xs font-bold px-2.5 py-1 rounded-full">recusado</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-right">
                                @if($p->estado === 'pendente')
                                    <button wire:click="abrirPedido({{ $p->id }})"
                                        class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-xs font-semibold">
                                        Analisar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-gray-400"><i class="fas fa-inbox text-3xl mb-2 block"></i>Nenhum pedido de licença.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal de aprovação --}}
    @if($pedidoId)
        @php $pd = $pedidos->firstWhere('id', $pedidoId); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">Aprovar pedido — {{ $pd?->empresa }}</h3>
                    <button wire:click="fecharPedido" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="bg-gray-50 rounded-xl p-4 text-sm text-gray-600">
                        NIF {{ $pd?->nif ?: '—' }} · {{ $pd?->email ?: '—' }} · {{ $pd?->telefone ?: '—' }}<br>
                        Máquina: <span class="font-mono text-xs">{{ $pd?->fingerprint ?: '—' }}</span>
                        @if($pd?->observacoes)<div class="mt-2 italic">"{{ $pd->observacoes }}"</div>@endif
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Plano *</label>
                            <select wire:model="pedPlanoId" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                                @foreach($planos as $pl)<option value="{{ $pl->id }}">{{ $pl->name }}</option>@endforeach
                            </select>
                            @error('pedPlanoId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Validade (dias)</label>
                            <input type="number" wire:model="pedDias" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Máx. utilizadores</label>
                            <input type="number" wire:model="pedMaxUsers" placeholder="sem limite" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                            @error('pedMaxUsers') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 mb-2">
                            <input type="checkbox" wire:model.live="pedTodosModulos" class="w-4 h-4 rounded border-gray-300 text-indigo-600">
                            <span class="text-sm font-medium text-gray-700">Todos os módulos</span>
                        </label>
                        @if(!$pedTodosModulos)
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 bg-gray-50 rounded-xl p-4">
                                @foreach($modulos as $m)
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" wire:model="pedModulos" value="{{ $m->slug }}" class="w-4 h-4 rounded border-gray-300 text-indigo-600">
                                        {{ $m->name }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="pedPrenderMaquina" class="w-4 h-4 rounded border-gray-300 text-indigo-600">
                        <span class="text-sm text-gray-700">Prender a licença a esta máquina (recomendado)</span>
                    </label>

                    <div class="border-t pt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ou recusar, com motivo</label>
                        <input type="text" wire:model="pedMotivoRecusa" placeholder="Motivo da recusa..." class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-400 text-sm">
                        @error('pedMotivoRecusa') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    @error('pedidoId') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror
                </div>
                <div class="px-6 py-4 border-t flex justify-between">
                    <button wire:click="recusarPedido" class="px-5 py-2.5 bg-red-50 text-red-700 rounded-xl text-sm font-semibold hover:bg-red-100">Recusar</button>
                    <div class="flex gap-2">
                        <button wire:click="fecharPedido" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold">Cancelar</button>
                        <button wire:click="aprovarPedido" wire:loading.attr="disabled"
                            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold shadow-lg disabled:opacity-50">
                            <i class="fas fa-check mr-1"></i>Aprovar e emitir licença
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Emitir licença ─────────────────────────────────────────── --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center mb-5">
            <i class="fas fa-id-card text-indigo-600 text-lg mr-2"></i>
            <h3 class="text-lg font-bold text-gray-900">Emitir licença</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Empresa</label>
                <select wire:model="licTenantId" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm">
                    <option value="">Escolher empresa...</option>
                    @foreach($tenants as $t)
                        <option value="{{ $t->id }}">{{ $t->name }} (#{{ $t->id }})</option>
                    @endforeach
                </select>
                @error('licTenantId') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Validade (dias)</label>
                <input type="number" wire:model="licDias" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm">
                @error('licDias') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Graça offline (dias)</label>
                <input type="number" wire:model="licGraca" placeholder="padrão" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Máx. utilizadores</label>
                <input type="number" wire:model="licMaxUsers" placeholder="sem limite" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm">
            </div>
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Prender à máquina (fingerprint) — opcional</label>
                <input type="text" wire:model="licBindFp" placeholder="deixe vazio para licença flutuante" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm font-mono">
            </div>
            <div class="md:col-span-4">
                <label class="flex items-center gap-2 mb-2">
                    <input type="checkbox" wire:model.live="licTodosModulos" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-gray-700">Todos os módulos</span>
                </label>
                @if(!$licTodosModulos)
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 bg-gray-50 rounded-xl p-4">
                        @foreach($modulos as $m)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model="licModulos" value="{{ $m->slug }}" class="w-4 h-4 rounded border-gray-300 text-indigo-600">
                                {{ $m->name }}
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="flex items-end">
                <button wire:click="emitirLicenca" wire:loading.attr="disabled"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl disabled:opacity-50">
                    <i class="fas fa-signature mr-2"></i>Emitir
                </button>
            </div>
        </div>
        @error('licToken') <p class="text-red-500 text-sm mt-3">{{ $message }}</p> @enderror

        @if($licToken)
            <div class="mt-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">Token da licença — copie e envie ao cliente</label>
                <textarea readonly rows="3" onclick="this.select()"
                          class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-3 text-xs text-green-300 font-mono">{{ $licToken }}</textarea>
            </div>
        @endif
    </div>

    {{-- ── Publicar versão ────────────────────────────────────────── --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center mb-5">
            <i class="fas fa-cloud-arrow-up text-cyan-600 text-lg mr-2"></i>
            <h3 class="text-lg font-bold text-gray-900">Publicar versão</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Versão *</label>
                <input type="text" wire:model="verVersao" placeholder="1.2.0" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-sm">
                @error('verVersao') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Versão mínima</label>
                <input type="text" wire:model="verMin" placeholder="1.0.0" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">URL do pacote (zip) *</label>
                <input type="url" wire:model="verUrl" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-sm">
                @error('verUrl') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">SHA-256 do pacote *</label>
                <input type="text" wire:model="verSha" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-sm font-mono">
                @error('verSha') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rollout</label>
                <select wire:model="verRollout" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-sm">
                    <option value="none">none (só alvos)</option>
                    <option value="all">all (toda a gente)</option>
                </select>
            </div>
            <div class="flex items-center gap-2 pt-7">
                <input type="checkbox" wire:model="verObrigatorio" id="obrig" class="w-4 h-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                <label for="obrig" class="text-sm text-gray-700">Obrigatória</label>
            </div>
            <div class="md:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                <textarea wire:model="verNotas" rows="2" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-sm"></textarea>
            </div>
            <div>
                <button wire:click="publicarVersao" wire:loading.attr="disabled"
                        class="bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl disabled:opacity-50">
                    <i class="fas fa-upload mr-2"></i>Publicar
                </button>
            </div>
        </div>
    </div>

    {{-- ── Versões & rollout por-tenant ───────────────────────────── --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center mb-5">
            <i class="fas fa-list-check text-emerald-600 text-lg mr-2"></i>
            <h3 class="text-lg font-bold text-gray-900">Versões &amp; rollout por-tenant</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Abrir versão a um tenant (canary)</label>
                <select wire:model="alvoVersao" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-sm">
                    <option value="">Versão...</option>
                    @foreach($versoes as $v)<option value="{{ $v->versao }}">{{ $v->versao }}</option>@endforeach
                </select>
                @error('alvoVersao') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tenant</label>
                <select wire:model="alvoTenantId" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-sm">
                    <option value="">Empresa...</option>
                    @foreach($tenants as $t)<option value="{{ $t->id }}">{{ $t->name }} (#{{ $t->id }})</option>@endforeach
                </select>
                @error('alvoTenantId') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <button wire:click="adicionarAlvo" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-plus mr-2"></i>Adicionar ao rollout
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="text-left text-xs font-bold text-gray-500 uppercase">
                        <th class="py-3 pr-4">Versão</th>
                        <th class="py-3 pr-4">Rollout</th>
                        <th class="py-3 pr-4">Tenants (canary)</th>
                        <th class="py-3 pr-4">Obrig.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($versoes as $v)
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 pr-4 font-mono text-gray-900">{{ $v->versao }}<div class="text-xs text-gray-400">min {{ $v->min_versao ?? '—' }}</div></td>
                            <td class="py-3 pr-4">
                                <div class="inline-flex rounded-lg overflow-hidden border border-gray-200">
                                    <button wire:click="definirRollout({{ $v->id }}, 'none')"
                                        class="px-3 py-1.5 text-xs font-semibold {{ $v->rollout === 'none' ? 'bg-gray-700 text-white' : 'bg-white text-gray-500 hover:bg-gray-50' }}">none</button>
                                    <button wire:click="definirRollout({{ $v->id }}, 'all')"
                                        class="px-3 py-1.5 text-xs font-semibold {{ $v->rollout === 'all' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50' }}">all</button>
                                </div>
                            </td>
                            <td class="py-3 pr-4">
                                @forelse($v->targets as $tg)
                                    <span class="inline-flex items-center gap-1 bg-gray-100 border border-gray-200 rounded-full px-2.5 py-1 text-xs text-gray-700 mr-1 mb-1">
                                        {{ $tg->tenant->name ?? ('#'.$tg->tenant_id) }}
                                        <button wire:click="removerAlvo({{ $tg->id }})" class="text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
                                    </span>
                                @empty
                                    <span class="text-gray-400 text-xs">{{ $v->rollout === 'all' ? 'todos' : '—' }}</span>
                                @endforelse
                            </td>
                            <td class="py-3 pr-4">{!! $v->obrigatorio ? '<span class="text-amber-600 font-semibold">sim</span>' : '<span class="text-gray-400">não</span>' !!}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-10 text-center text-gray-400"><i class="fas fa-inbox text-3xl mb-2 block"></i>Nenhuma versão publicada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
