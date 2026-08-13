<div class="p-3 sm:p-6">
    {{-- Cabeçalho --}}
    <div class="mb-4 sm:mb-6 bg-gradient-to-r from-slate-700 to-slate-900 rounded-2xl shadow-lg p-4 sm:p-6 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3 sm:mr-4">
                    <i class="fas fa-clipboard-list text-xl sm:text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-lg sm:text-2xl font-bold">{{ __('Auditoria') }}</h2>
                    <p class="text-slate-200 text-xs sm:text-sm">{{ __('Quem fez o quê, quando e por que caminho') }}</p>
                </div>
            </div>

            <button wire:click="verificarIntegridade"
                    wire:loading.attr="disabled" wire:target="verificarIntegridade"
                    class="bg-white/15 hover:bg-white/25 px-4 py-2 rounded-xl font-semibold text-sm transition disabled:opacity-60">
                <span wire:loading.remove wire:target="verificarIntegridade">
                    <i class="fas fa-shield-halved mr-2"></i>{{ __('Verificar integridade') }}
                </span>
                <span wire:loading wire:target="verificarIntegridade">
                    <i class="fas fa-spinner fa-spin mr-2"></i>{{ __('A verificar...') }}
                </span>
            </button>
        </div>
    </div>

    {{-- Resultado da verificação da cadeia --}}
    @if($integridade)
        <div class="mb-4 rounded-2xl border-2 p-4 {{ $integridade['ok'] ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50' }}">
            @if($integridade['ok'])
                <p class="font-bold text-green-800">
                    <i class="fas fa-circle-check mr-2"></i>{{ __('Cadeia íntegra') }}
                </p>
                <p class="text-sm text-green-700 mt-1">
                    Nenhum registo foi alterado ou removido. Verificado em {{ $integridade['em'] }}.
                </p>
            @else
                <p class="font-bold text-red-800">
                    <i class="fas fa-triangle-exclamation mr-2"></i>
                    {{ $integridade['total'] }} problema(s) de integridade
                </p>
                <p class="text-sm text-red-700 mt-1">
                    {{ __('A trilha foi alterada fora da aplicação. Cada linha abaixo indica onde a cadeia parte.') }}
                </p>
                <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                    @foreach($integridade['problemas'] as $p)
                        <li>Sequência {{ $p['sequencia'] }} — {{ $p['motivo'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- Filtros --}}
    <div class="mb-4 bg-white rounded-2xl shadow p-4">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <div class="col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Pesquisar') }}</label>
                <input wire:model.live.debounce.300ms="pesquisa" type="text"
                       placeholder="{{ __('Documento, artigo ou pessoa...') }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-slate-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Acto') }}</label>
                <select wire:model.live="filtroEvento" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach($eventos as $e)<option value="{{ $e }}">{{ $e }}</option>@endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Canal') }}</label>
                <select wire:model.live="filtroCanal" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach($canais as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('De') }}</label>
                <input wire:model.live="dataDe" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">{{ __('Até') }}</label>
                <input wire:model.live="dataAte" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
            </div>
        </div>

        <div class="flex items-center justify-between mt-3">
            <select wire:model.live="filtroActor" class="px-3 py-2 border border-gray-300 rounded-xl text-sm bg-white">
                <option value="">{{ __('Todas as pessoas') }}</option>
                @foreach($actores as $a)
                    <option value="{{ $a->user_id }}">{{ $a->actor_name }}</option>
                @endforeach
            </select>

            <button wire:click="limparFiltros" class="text-sm text-slate-600 hover:text-slate-800 font-semibold">
                <i class="fas fa-redo mr-1"></i>{{ __('Limpar filtros') }}
            </button>
        </div>
    </div>

    {{-- Registos --}}
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="px-4 sm:px-6 py-3 border-b bg-gray-50 flex items-center justify-between">
            <h3 class="font-bold text-gray-800"><i class="fas fa-list mr-2"></i>{{ __('Registos') }}</h3>
            <span class="text-sm text-gray-500">{{ $registos->total() }} no total</span>
        </div>

        <div class="divide-y">
            @forelse($registos as $r)
                <div wire:key="audit-{{ $r->id }}" class="px-4 sm:px-6 py-3 hover:bg-slate-50 transition">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                        <span class="text-xs text-gray-500 font-mono w-32 shrink-0">
                            {{ $r->created_at->format('d/m/Y H:i:s') }}
                        </span>

                        @php
                            $cor = match($r->event) {
                                'created'  => 'bg-green-100 text-green-700',
                                'updated'  => 'bg-blue-100 text-blue-700',
                                'deleted', 'force_deleted' => 'bg-red-100 text-red-700',
                                'restored' => 'bg-amber-100 text-amber-700',
                                default    => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <span class="px-2 py-0.5 rounded-lg text-xs font-bold shrink-0 {{ $cor }}">
                            {{ $r->event }}
                        </span>

                        <span class="text-sm text-gray-900 flex-1 min-w-0">
                            {{-- O que aconteceu, em palavras. Antes dizia
                                 "StockMovement" e obrigava a abrir o detalhe
                                 para descobrir que artigo tinha saído de onde. --}}
                            <span class="block truncate font-medium">
                                {{ $leitura->frase($r) }}
                            </span>

                            <span class="block text-xs text-gray-400 truncate">
                                {{ $leitura->nomeDoModelo(class_basename($r->auditable_type)) ?: 'acto' }}
                                @if($r->auditable_id) #{{ $r->auditable_id }} @endif
                                @if($r->auditable_label) · {{ $r->auditable_label }} @endif
                            </span>

                            {{-- Contexto legível: sem isto a linha obrigava a
                                 abrir o detalhe só para saber de que valor e de
                                 que cliente se falava. --}}
                            @if($r->metadata)
                                <span class="block text-xs text-gray-500 truncate">
                                    @foreach(array_slice($r->metadata, 0, 4) as $chave => $valor)
                                        @continue(is_array($valor) || $valor === null || $valor === '')
                                        <span class="mr-2">{{ $leitura->rotuloDoCampo($chave) }}: <span class="text-gray-700">{{ \Illuminate\Support\Str::limit((string) $leitura->valor($chave, $valor), 30) }}</span></span>
                                    @endforeach
                                </span>
                            @endif

                            {{-- Que campos mudaram, sem abrir nada. --}}
                            @if($r->event === 'updated' && $r->new_values)
                                <span class="block text-xs text-blue-600 truncate">
                                    alterou: {{ implode(', ', array_map(fn ($c) => $leitura->rotuloDoCampo($c), array_slice(array_keys($r->new_values), 0, 6))) }}@if(count($r->new_values) > 6) …@endif
                                </span>
                            @endif
                        </span>

                        <span class="text-sm text-gray-600 shrink-0">
                            {{ $r->actor_name ?: 'sistema' }}
                            <span class="text-xs text-gray-400">({{ $r->channel }})</span>
                        </span>

                        @if($r->is_impersonated ?? $r->impersonator_id)
                            <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-xs font-bold shrink-0"
                                  title="{{ __('Acto praticado em personificação') }}">
                                {{ __('personificado') }}
                            </span>
                        @endif

                        <button wire:click="abrir({{ $r->id }})"
                                class="text-slate-500 hover:text-slate-800 shrink-0" title="{{ __('Ver detalhe') }}">
                            <i class="fas fa-magnifying-glass"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="px-6 py-16 text-center">
                    <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-3"></i>
                    <h3 class="font-bold text-gray-900 mb-1">{{ __('Sem registos') }}</h3>
                    <p class="text-gray-500 text-sm">
                        {{-- O comando é literal: escreve-se igual em qualquer
                             língua e traduzi-lo tornava-o inexecutável. --}}
                        {!! __('Ou não há actividade no período escolhido, ou a auditoria não está a registar — confirme com <code class="bg-gray-100 px-1 rounded">php artisan audit:health</code>.') !!}
                    </p>
                </div>
            @endforelse
        </div>

        @if($registos->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t">{{ $registos->links() }}</div>
        @endif
    </div>

    {{-- Detalhe --}}
    @if($this->linhaAberta)
        @php($l = $this->linhaAberta)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
             wire:click.self="fechar">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b sticky top-0 bg-white">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="font-bold text-gray-900">{{ $leitura->frase($l) }}</h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $l->event }} ·
                                {{ $leitura->nomeDoModelo(class_basename($l->auditable_type)) ?: 'acto' }}
                                @if($l->auditable_id) #{{ $l->auditable_id }} @endif
                                @if($l->auditable_label) · {{ $l->auditable_label }} @endif
                            </p>
                        </div>
                        <button wire:click="fechar" class="text-gray-400 hover:text-gray-700 shrink-0"><i class="fas fa-times text-xl"></i></button>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                        <div><span class="text-gray-500 block text-xs">{{ __('Quando') }}</span>{{ $l->created_at->format('d/m/Y H:i:s') }}</div>
                        <div><span class="text-gray-500 block text-xs">{{ __('Quem') }}</span>{{ $l->actor_name ?: 'sistema' }} ({{ $l->actor_type }})</div>
                        <div><span class="text-gray-500 block text-xs">{{ __('Canal') }}</span>{{ $l->channel }}</div>
                        <div><span class="text-gray-500 block text-xs">{{ __('Endereço') }}</span>{{ $l->ip_address ?: '—' }}</div>
                        <div><span class="text-gray-500 block text-xs">{{ __('Origem') }}</span>{{ $l->route ?: '—' }}</div>
                        <div><span class="text-gray-500 block text-xs">{{ __('Sequência') }}</span>#{{ $l->sequence }}</div>
                    </div>

                    @if($l->tenant_id !== $l->context_tenant_id && $l->context_tenant_id)
                        <div class="rounded-xl border-2 border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
                            <strong><i class="fas fa-triangle-exclamation mr-1"></i>{{ __('Empresas diferentes.') }}</strong>
                            O registo pertence a {{ $leitura->rotuloDaReferencia('tenant_id', (int) $l->tenant_id) }}
                            mas o acto foi praticado com {{ $leitura->rotuloDaReferencia('tenant_id', (int) $l->context_tenant_id) }} activa.
                        </div>
                    @endif

                    @if($l->old_values || $l->new_values)
                        <div>
                            <h4 class="font-bold text-sm text-gray-700 mb-2">
                                {{ $l->event === 'created' ? 'O que foi registado' : 'O que mudou' }}
                            </h4>
                            <div class="rounded-xl border overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 text-xs text-gray-600">
                                        <tr>
                                            <th class="text-left px-3 py-2">{{ __('Campo') }}</th>
                                            @if($l->event !== 'created')
                                                <th class="text-left px-3 py-2">{{ __('Antes') }}</th>
                                            @endif
                                            <th class="text-left px-3 py-2">{{ $l->event === 'created' ? 'Valor' : 'Depois' }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        {{-- Nome do campo em português e referências
                                             resolvidas: "Armazém · SALA DE VENDAS (#12)"
                                             em vez de "warehouse_id · 12". O número vai
                                             junto porque o nome é o de hoje e o registo
                                             é do passado. --}}
                                        @foreach($leitura->campos($l) as $linha)
                                            <tr class="{{ $linha['referencia'] ? 'bg-slate-50/60' : '' }}">
                                                <td class="px-3 py-2 font-medium text-gray-700">
                                                    {{ $linha['rotulo'] }}
                                                    <span class="block font-mono text-[10px] text-gray-400">{{ $linha['campo'] }}</span>
                                                </td>
                                                @if($l->event !== 'created')
                                                    <td class="px-3 py-2 text-red-700">{{ $linha['antes'] === null ? '—' : \Illuminate\Support\Str::limit($linha['antes'], 60) }}</td>
                                                @endif
                                                <td class="px-3 py-2 {{ $l->event === 'created' ? 'text-gray-900' : 'text-green-700' }}">
                                                    {{ $linha['depois'] === null ? '—' : \Illuminate\Support\Str::limit($linha['depois'], 60) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if($l->metadata)
                        <div>
                            <h4 class="font-bold text-sm text-gray-700 mb-2">{{ __('Contexto') }}</h4>
                            <div class="rounded-xl border overflow-hidden">
                                <table class="w-full text-sm">
                                    <tbody class="divide-y">
                                        @foreach($l->metadata as $chave => $valor)
                                            <tr>
                                                <td class="px-3 py-2 font-medium text-gray-700 w-1/3">{{ $leitura->rotuloDoCampo($chave) }}</td>
                                                <td class="px-3 py-2 text-gray-900">{{ $leitura->valor($chave, $valor) ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <div class="text-xs text-gray-400 font-mono break-all border-t pt-3">
                        hash {{ $l->hash }}
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
