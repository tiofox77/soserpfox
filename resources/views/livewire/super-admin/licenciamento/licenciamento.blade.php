<div class="p-6 max-w-6xl mx-auto space-y-6">
    <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-indigo-600/20 flex items-center justify-center">
            <i class="fas fa-key text-indigo-400 text-xl"></i>
        </div>
        <div>
            <h1 class="text-xl font-bold text-white">Licenciamento Offline</h1>
            <p class="text-sm text-gray-400">Emitir licenças, publicar versões e rollout por-tenant.</p>
        </div>
    </div>

    @if (session('ok'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-300 px-4 py-3 rounded-xl text-sm">
            <i class="fas fa-check-circle mr-1"></i>{{ session('ok') }}
        </div>
    @endif

    @if (!$chaveLicOk || !$chaveUpdOk)
        <div class="bg-amber-500/10 border border-amber-500/30 text-amber-300 px-4 py-3 rounded-xl text-sm">
            <i class="fas fa-triangle-exclamation mr-1"></i>
            Chave(s) privada(s) por configurar:
            @if(!$chaveLicOk) <code>LICENSE_SIGNING_KEY</code> @endif
            @if(!$chaveUpdOk) <code>LICENSE_UPDATE_SIGNING_KEY</code> @endif
            — gere um par com <code>php artisan licenca:chaves</code> e ponha a privada no .env do servidor.
        </div>
    @endif

    {{-- ── Emitir licença ─────────────────────────────────────────── --}}
    <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-5">
        <h2 class="text-white font-semibold mb-4"><i class="fas fa-id-card mr-2 text-indigo-400"></i>Emitir licença</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-400 mb-1">Empresa</label>
                <select wire:model="licTenantId" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                    <option value="">Escolher empresa...</option>
                    @foreach($tenants as $t)
                        <option value="{{ $t->id }}">{{ $t->name }} (#{{ $t->id }})</option>
                    @endforeach
                </select>
                @error('licTenantId') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Validade (dias)</label>
                <input type="number" wire:model="licDias" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                @error('licDias') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Graça offline (dias)</label>
                <input type="number" wire:model="licGraca" placeholder="padrão" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs text-gray-400 mb-1">Prender à máquina (fingerprint) — opcional</label>
                <input type="text" wire:model="licBindFp" placeholder="deixe vazio para licença flutuante" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono">
            </div>
            <div class="flex items-end">
                <button wire:click="emitirLicenca" wire:loading.attr="disabled"
                        class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-semibold disabled:opacity-50">
                    <i class="fas fa-signature mr-1"></i>Emitir
                </button>
            </div>
        </div>
        @error('licToken') <p class="text-red-400 text-xs mt-2">{{ $message }}</p> @enderror

        @if($licToken)
            <div class="mt-4">
                <label class="block text-xs text-gray-400 mb-1">Token da licença (copie e envie ao cliente)</label>
                <textarea readonly rows="3" class="w-full bg-black/40 border border-gray-700 rounded-lg px-3 py-2 text-xs text-green-300 font-mono">{{ $licToken }}</textarea>
            </div>
        @endif
    </div>

    {{-- ── Publicar versão ────────────────────────────────────────── --}}
    <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-5">
        <h2 class="text-white font-semibold mb-4"><i class="fas fa-cloud-arrow-up mr-2 text-cyan-400"></i>Publicar versão</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs text-gray-400 mb-1">Versão *</label>
                <input type="text" wire:model="verVersao" placeholder="1.2.0" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                @error('verVersao') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Versão mínima</label>
                <input type="text" wire:model="verMin" placeholder="1.0.0" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-400 mb-1">URL do pacote (zip) *</label>
                <input type="url" wire:model="verUrl" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                @error('verUrl') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-400 mb-1">SHA-256 do pacote *</label>
                <input type="text" wire:model="verSha" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono">
                @error('verSha') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Rollout</label>
                <select wire:model="verRollout" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                    <option value="none">none (só alvos)</option>
                    <option value="all">all (toda a gente)</option>
                </select>
            </div>
            <div class="flex items-center gap-2 pt-6">
                <input type="checkbox" wire:model="verObrigatorio" id="obrig" class="rounded bg-gray-900 border-gray-600">
                <label for="obrig" class="text-sm text-gray-300">Obrigatória</label>
            </div>
            <div class="md:col-span-4">
                <label class="block text-xs text-gray-400 mb-1">Notas</label>
                <textarea wire:model="verNotas" rows="2" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white"></textarea>
            </div>
            <div>
                <button wire:click="publicarVersao" wire:loading.attr="disabled"
                        class="bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg px-4 py-2 text-sm font-semibold disabled:opacity-50">
                    <i class="fas fa-upload mr-1"></i>Publicar
                </button>
            </div>
        </div>
    </div>

    {{-- ── Versões & rollout por-tenant ───────────────────────────── --}}
    <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-5">
        <h2 class="text-white font-semibold mb-4"><i class="fas fa-list-check mr-2 text-emerald-400"></i>Versões & rollout por-tenant</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5 items-end">
            <div>
                <label class="block text-xs text-gray-400 mb-1">Abrir versão a um tenant (canary)</label>
                <select wire:model="alvoVersao" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                    <option value="">Versão...</option>
                    @foreach($versoes as $v)<option value="{{ $v->versao }}">{{ $v->versao }}</option>@endforeach
                </select>
                @error('alvoVersao') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Tenant</label>
                <select wire:model="alvoTenantId" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                    <option value="">Empresa...</option>
                    @foreach($tenants as $t)<option value="{{ $t->id }}">{{ $t->name }} (#{{ $t->id }})</option>@endforeach
                </select>
                @error('alvoTenantId') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <button wire:click="adicionarAlvo" class="bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg px-4 py-2 text-sm font-semibold">
                    <i class="fas fa-plus mr-1"></i>Adicionar ao rollout
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-gray-400 border-b border-gray-700">
                    <tr>
                        <th class="text-left py-2 pr-4">Versão</th>
                        <th class="text-left py-2 pr-4">Rollout</th>
                        <th class="text-left py-2 pr-4">Tenants (canary)</th>
                        <th class="text-left py-2 pr-4">Obrig.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($versoes as $v)
                        <tr class="text-gray-200">
                            <td class="py-3 pr-4 font-mono">{{ $v->versao }}<div class="text-xs text-gray-500">min {{ $v->min_versao ?? '—' }}</div></td>
                            <td class="py-3 pr-4">
                                <div class="inline-flex rounded-lg overflow-hidden border border-gray-700">
                                    <button wire:click="definirRollout({{ $v->id }}, 'none')"
                                        class="px-3 py-1 text-xs {{ $v->rollout === 'none' ? 'bg-gray-600 text-white' : 'bg-gray-900 text-gray-400' }}">none</button>
                                    <button wire:click="definirRollout({{ $v->id }}, 'all')"
                                        class="px-3 py-1 text-xs {{ $v->rollout === 'all' ? 'bg-emerald-600 text-white' : 'bg-gray-900 text-gray-400' }}">all</button>
                                </div>
                            </td>
                            <td class="py-3 pr-4">
                                @forelse($v->targets as $tg)
                                    <span class="inline-flex items-center gap-1 bg-gray-900 border border-gray-700 rounded-full px-2 py-0.5 text-xs mr-1 mb-1">
                                        {{ $tg->tenant->name ?? ('#'.$tg->tenant_id) }}
                                        <button wire:click="removerAlvo({{ $tg->id }})" class="text-red-400 hover:text-red-300"><i class="fas fa-times"></i></button>
                                    </span>
                                @empty
                                    <span class="text-gray-600 text-xs">{{ $v->rollout === 'all' ? 'todos' : '—' }}</span>
                                @endforelse
                            </td>
                            <td class="py-3 pr-4">{!! $v->obrigatorio ? '<span class="text-amber-400">sim</span>' : '<span class="text-gray-500">não</span>' !!}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-gray-500">Nenhuma versão publicada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
