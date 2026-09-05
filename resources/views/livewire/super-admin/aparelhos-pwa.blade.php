<div class="p-4 sm:p-6">
    <div class="mx-auto max-w-7xl space-y-6">

        {{-- ============ CABEÇALHO ============ --}}
        <header class="rounded-3xl bg-gradient-to-r from-slate-900 to-blue-900 p-6 text-white shadow-xl">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-blue-200">{{ __('Plataforma') }}</p>
                    <h1 class="mt-2 text-3xl font-black">{{ __('Aparelhos com PWA') }}</h1>
                    <p class="mt-2 max-w-2xl text-sm text-blue-100">
                        {{ __('Que empresas usam o ponto de venda offline, em que aparelhos, e em que versão. A coluna que interessa é a versão.') }}
                    </p>
                </div>

                <div class="shrink-0 rounded-2xl bg-white/10 px-4 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-blue-200">{{ __('Versão servida agora') }}</p>
                    <p class="mt-0.5 font-mono text-lg font-black">{{ $versaoActual }}</p>
                </div>
            </div>
        </header>

        {{-- ============ O RESUMO ============
             Conta SEMPRE tudo, e não o que o filtro deixou ver: um resumo que
             encolhe com o filtro faz o problema parecer menor do que é. --}}
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            @php
                $__cartoes = [
                    ['rotulo' => __('Empresas'),    'valor' => $resumo['empresas'],    'cor' => 'slate',   'nota' => __('com pelo menos um aparelho')],
                    ['rotulo' => __('Aparelhos'),   'valor' => $resumo['aparelhos'],   'cor' => 'blue',    'nota' => __('já vistos')],
                    ['rotulo' => __('Instalados'),  'valor' => $resumo['instalados'],  'cor' => 'emerald', 'nota' => __('no ecrã principal')],
                    ['rotulo' => __('Atrasados'),   'valor' => $resumo['atrasados'],   'cor' => 'red',     'nota' => __('não têm a última versão')],
                    ['rotulo' => __('Adormecidos'), 'valor' => $resumo['adormecidos'], 'cor' => 'amber',   'nota' => __(':n dias sem falar', ['n' => $diasAteAdormecer])],
                ];
            @endphp

            @foreach($__cartoes as $c)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold text-slate-500">{{ $c['rotulo'] }}</p>
                    <p class="mt-1.5 text-3xl font-black text-{{ $c['cor'] }}-700">{{ $c['valor'] }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $c['nota'] }}</p>
                </article>
            @endforeach
        </section>

        {{-- Um aviso, e não só um número: um aparelho atrasado é uma correcção
             que não chegou a quem vende. --}}
        @if($resumo['atrasados'] > 0)
            <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
                <i class="fas fa-triangle-exclamation mt-0.5 text-red-500"></i>
                <div class="text-sm">
                    <p class="font-bold text-red-800">
                        {{ trans_choice(':n aparelho não tem a última versão|:n aparelhos não têm a última versão', $resumo['atrasados'], ['n' => $resumo['atrasados']]) }}
                    </p>
                    <p class="mt-0.5 text-red-700">
                        {{ __('Cada um desses está a correr código antigo — uma correcção deployada pode não ter chegado a quem vende. Os instalados no ecrã principal são os que mais tempo ficam presos.') }}
                    </p>
                </div>
            </div>
        @endif

        {{-- ============ FILTROS ============ --}}
        <section class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex flex-1 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 h-11 shadow-sm">
                <i class="fas fa-magnifying-glass text-slate-400"></i>
                <input wire:model.live.debounce.300ms="pesquisa" type="search"
                       placeholder="{{ __('Empresa, aparelho ou versão…') }}"
                       class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:ring-0">
            </div>

            <div class="flex gap-2 overflow-x-auto">
                @foreach([
                    'tudo' => __('Todos'),
                    'atrasados' => __('Atrasados'),
                    'instalados' => __('Instalados'),
                    'adormecidos' => __('Adormecidos'),
                ] as $chave => $rotulo)
                    <button wire:click="$set('filtro', '{{ $chave }}')"
                            class="shrink-0 rounded-xl px-4 h-11 text-sm font-bold transition
                                   {{ $filtro === $chave ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border border-slate-200' }}">
                        {{ $rotulo }}
                    </button>
                @endforeach
            </div>
        </section>

        {{-- ============ A LISTA ============ --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-bold">{{ __('Empresa') }}</th>
                            <th class="px-4 py-3 font-bold">{{ __('Versão') }}</th>
                            <th class="px-4 py-3 font-bold">{{ __('Como') }}</th>
                            <th class="px-4 py-3 font-bold">{{ __('Último operador') }}</th>
                            <th class="px-4 py-3 font-bold">{{ __('Visto') }}</th>
                            <th class="px-4 py-3 text-right font-bold">{{ __('Sincs') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse($aparelhos as $a)
                            @php
                                $atrasado = $a->app_version !== $versaoActual;
                                $adormecido = !$a->last_seen_at || $a->last_seen_at->lt(now()->subDays($diasAteAdormecer));
                            @endphp
                            <tr class="{{ $atrasado ? 'bg-red-50/40' : '' }}">
                                <td class="px-4 py-3">
                                    <p class="font-bold text-slate-800">{{ $a->tenant?->name ?? __('(empresa apagada)') }}</p>
                                    <p class="font-mono text-[11px] text-slate-400">{{ Str::limit($a->device_uuid, 14, '…') }}</p>
                                </td>

                                <td class="px-4 py-3">
                                    @if($a->app_version)
                                        <span class="rounded-lg px-2 py-1 font-mono text-xs font-bold
                                                     {{ $atrasado ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                            {{ $a->app_version }}
                                        </span>
                                    @else
                                        {{-- Sem versão declarada é o mesmo problema com outro nome:
                                             não se sabe o que este aparelho corre. --}}
                                        <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold text-slate-500">
                                            {{ __('não diz') }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    @if($a->standalone)
                                        <span class="text-xs font-bold text-emerald-700">
                                            <i class="fas fa-mobile-screen-button mr-1"></i>{{ __('Instalado') }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-500">
                                            <i class="fas fa-globe mr-1"></i>{{ __('Browser') }}
                                        </span>
                                    @endif
                                    @if($a->platform)
                                        <p class="text-[11px] text-slate-400">{{ $a->platform }}</p>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    <p class="text-slate-700">{{ $a->user?->name ?? '—' }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $a->user?->email }}</p>
                                </td>

                                <td class="px-4 py-3">
                                    @if($a->last_seen_at)
                                        <span class="{{ $adormecido ? 'font-bold text-amber-700' : 'text-slate-600' }}">
                                            {{ $a->last_seen_at->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right font-bold text-slate-700">{{ $a->syncs }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-16 text-center">
                                    <i class="fas fa-mobile-screen mb-3 block text-4xl text-slate-200"></i>
                                    <p class="text-sm text-slate-400">
                                        {{ $pesquisa || $filtro !== 'tudo'
                                            ? __('Nada com este filtro.')
                                            : __('Ainda nenhum aparelho se identificou. Aparecem aqui à primeira sincronização.') }}
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($aparelhos->hasPages())
                <div class="border-t border-slate-100 px-4 py-3">{{ $aparelhos->links() }}</div>
            @endif
        </section>

        {{-- ============ QUEM TEM E NÃO USA ============
             A pergunta que o inventário sozinho não responde: um cliente que
             paga pela facturação e nunca abriu o PWA não aparece em lista
             nenhuma de aparelhos — e é esse que vale a pena ligar. --}}
        @if($comModuloSemAparelho->count())
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-800">
                    {{ __('Têm Facturação mas nunca abriram o PWA') }}
                    <span class="ml-1 text-sm font-bold text-slate-400">({{ $comModuloSemAparelho->count() }})</span>
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ __('Pagam pelo módulo e não estão a usar o ponto de venda offline. Vale uma chamada.') }}
                </p>

                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($comModuloSemAparelho as $t)
                        <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">{{ $t->name }}</span>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
