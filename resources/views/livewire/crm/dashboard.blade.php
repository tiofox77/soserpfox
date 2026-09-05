<div>
    <!-- Header — o desenho da página de Produtos, na cor do CRM -->
    <div class="mb-6 bg-gradient-to-r from-teal-600 to-cyan-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-user-check text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">CRM</h2>
                    <p class="text-teal-100 text-sm">O funil, os ganhos e o que está atrasado — num sítio só</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('crm.leads') }}" class="bg-white text-teal-600 hover:bg-teal-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-user-plus mr-2"></i>Leads
                </a>
                <a href="{{ route('crm.funil-vendas') }}" class="bg-white/15 hover:bg-white/25 px-5 py-3 rounded-xl font-semibold transition-all">
                    <i class="fas fa-filter mr-2"></i>Funil
                </a>
            </div>
        </div>
    </div>

    <!-- As quatro perguntas de segunda-feira -->
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-gray-500">Funil aberto</p>
                <div class="w-10 h-10 bg-teal-100 rounded-xl flex items-center justify-center"><i class="fas fa-filter text-teal-600"></i></div>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ valorProtegido($resumo['funil_valor'], 'crm.opportunities.view') }} Kz</p>
            <p class="mt-1 text-xs text-gray-500">{{ $resumo['funil_contagem'] }} negócio(s) · ponderado <b>{{ valorProtegido($resumo['funil_ponderado'], 'crm.opportunities.view') }} Kz</b></p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-gray-500">Ganho este mês</p>
                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center"><i class="fas fa-trophy text-green-600"></i></div>
            </div>
            <p class="text-2xl font-bold text-green-700">{{ valorProtegido($resumo['ganho_mes'], 'crm.opportunities.view') }} Kz</p>
            <p class="mt-1 text-xs text-gray-500">
                {{ $resumo['ganhas_mes'] }} negócio(s) fechado(s)
                {{-- Ganho não é cobrado: o que já virou documento, e o que espera. --}}
                @if($resumo['ganho_mes'] > 0)
                    · {{ __('facturado') }} <b>{{ valorProtegido($resumo['facturado_mes'], 'crm.opportunities.view') }}</b>
                    @if($resumo['por_facturar_mes'] > 0)
                        <span class="text-amber-600">· {{ $resumo['por_facturar_mes'] }} {{ __('por facturar') }}</span>
                    @endif
                @endif
            </p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-gray-500">Taxa de conversão (mês)</p>
                <div class="w-10 h-10 bg-violet-100 rounded-xl flex items-center justify-center"><i class="fas fa-percent text-violet-600"></i></div>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ $resumo['taxa'] !== null ? $resumo['taxa'].'%' : '—' }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $resumo['taxa'] !== null ? 'ganhas sobre fechadas' : 'ainda sem fechos este mês' }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-gray-500">Leads</p>
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center"><i class="fas fa-user-plus text-blue-600"></i></div>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ $resumo['leads_abertos'] }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $resumo['leads_novos_mes'] }} novo(s) este mês</p>
        </div>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-grafico class="lg:col-span-2"
                   titulo="O funil, em valor"
                   subtitulo="Oportunidades abertas por etapa"
                   id="grCrmFunil"
                   :altura="240"
                   :vazio="!array_sum($graficoFunil['valores'])" />

        <x-grafico titulo="Ganhos por mês"
                   subtitulo="Últimos 6 meses"
                   id="grCrmGanhos"
                   :vazio="!array_sum($graficoGanhos['valores'])" />

        <x-grafico titulo="De onde vêm os leads"
                   subtitulo="Últimos 90 dias — onde vale a pena investir"
                   id="grCrmOrigens"
                   :vazio="empty($graficoOrigens['valores'])" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <!-- As tarefas: atrasadas primeiro — são a razão de abrir isto -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-clock mr-2 text-teal-600"></i>Tarefas com prazo
                </h3>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($tarefas as $t)
                    <div class="flex items-center justify-between gap-3 px-6 py-4 hover:bg-teal-50 transition">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-gray-900">{{ $t->subject }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $t->type_label }}
                                @if($t->lead) · {{ $t->lead->name }} @endif
                                @if($t->opportunity) · {{ $t->opportunity->title }} @endif
                            </p>
                        </div>
                        <span class="shrink-0 inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold {{ $t->atrasada() ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600' }}">
                            {{ $t->due_at->format('d/m H:i') }}
                        </span>
                    </div>
                @empty
                    <div class="p-12 text-center">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-clock text-gray-400 text-3xl"></i>
                        </div>
                        <p class="text-gray-500">Sem tarefas com prazo. As que criar nos leads e oportunidades aparecem aqui.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Últimas oportunidades -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-handshake mr-2 text-teal-600"></i>Últimas oportunidades
                </h3>
                <a href="{{ route('crm.oportunidades') }}" class="text-sm font-semibold text-teal-600 hover:text-teal-700">Ver todas</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($ultimasOportunidades as $o)
                    <div class="flex items-center justify-between gap-3 px-6 py-4 hover:bg-teal-50 transition">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-gray-900">{{ $o->title }}</p>
                            <p class="text-xs text-gray-500">{{ $o->client?->name ?? 'Sem cliente' }} · {{ $o->stage?->name }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-bold tabular-nums text-gray-900">{{ valorProtegido((float) $o->amount, 'crm.opportunities.view') }} Kz</p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold
                                {{ ['open' => 'bg-blue-100 text-blue-700', 'won' => 'bg-green-100 text-green-700', 'lost' => 'bg-red-100 text-red-600'][$o->status] }}">
                                {{ ['open' => 'ABERTA', 'won' => 'GANHA', 'lost' => 'PERDIDA'][$o->status] }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-handshake text-gray-400 text-3xl"></i>
                        </div>
                        <p class="text-gray-500 mb-4">Ainda sem oportunidades. Comece pelos leads.</p>
                        <a href="{{ route('crm.leads') }}" class="inline-block bg-gradient-to-r from-teal-600 to-cyan-600 text-white px-6 py-2.5 rounded-xl font-semibold shadow-lg">
                            <i class="fas fa-user-plus mr-2"></i>Ir aos leads
                        </a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @include('partials.graficos')
    <script>
        // Um sítio só para o quando: desenha já e outra vez depois de o
        // Livewire trocar o canvas. Dois ouvintes registados aqui eram
        // empilhados a cada navegação, porque o script volta a correr.
        sosDesenhar(iniciarGraficosCrm);

        function iniciarGraficosCrm() {
            const funil = @json($graficoFunil);
            const ganhos = @json($graficoGanhos);
            const origens = @json($graficoOrigens);

            if (funil.valores.some(v => v > 0)) sosBarras('grCrmFunil', funil.etiquetas, funil.valores, { cor: SOS_CORES[0] });
            if (ganhos.valores.some(v => v > 0)) sosLinha('grCrmGanhos', ganhos.etiquetas, ganhos.valores, { cor: SOS_CORES[2] });
            if (origens.valores.length) sosRosca('grCrmOrigens', origens.etiquetas, origens.valores);
        }
    </script>
</div>
