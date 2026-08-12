<div>
{{-- Header --}}
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">📊 Analytics & Leads</h1>
        <p class="text-sm text-gray-500">Rastreamento avançado da landing page e fluxo de conversão</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @foreach([['today','Hoje'],['7d','7 dias'],['30d','30 dias'],['90d','90 dias'],['all','Tudo'],['custom','Escolher']] as $r)
            <button wire:click="setRange('{{ $r[0] }}')"
                    class="px-3 py-2 rounded-lg text-xs font-bold transition {{ $range === $r[0] ? 'bg-blue-600 text-white shadow' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                {{ $r[1] }}
            </button>
        @endforeach
    </div>
</div>

{{-- Período à medida --}}
@if($range === 'custom')
    <div class="bg-white rounded-2xl shadow p-4 mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">De</label>
            <input type="date" wire:model.live="dataDe" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Até</label>
            <input type="date" wire:model.live="dataAte" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
    </div>
@endif

{{-- Quem está no site AGORA.

     Fora do período e fora dos filtros de propósito: "agora" é agora, e uma
     pessoa a olhar para isto quer saber quem está lá neste momento, não quem
     esteve na janela que escolheu no filtro. --}}
<div class="bg-gradient-to-r from-slate-900 to-slate-800 rounded-2xl shadow-lg p-4 mb-6 text-white"
     wire:poll.15s>
    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex items-center gap-3 shrink-0">
            <span class="relative flex h-3 w-3">
                @if($online > 0)
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                @endif
                <span class="relative inline-flex rounded-full h-3 w-3 {{ $online > 0 ? 'bg-green-500' : 'bg-gray-500' }}"></span>
            </span>
            <div>
                <p class="text-3xl font-extrabold leading-none">{{ $online }}</p>
                <p class="text-xs text-slate-300 uppercase font-bold mt-0.5">
                    {{ $online === 1 ? 'pessoa agora' : 'pessoas agora' }}
                </p>
            </div>
        </div>

        <div class="flex-1 min-w-0">
            @if($onlinePaginas->isNotEmpty())
                <p class="text-[11px] text-slate-400 uppercase font-bold mb-1">A ver neste momento</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($onlinePaginas as $p)
                        <span class="px-2.5 py-1 rounded-lg bg-white/10 text-xs font-medium">
                            {{ $p->path ?: '/' }}
                            <span class="text-slate-400 ml-1">{{ $p->visitantes }}</span>
                        </span>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-400">Ninguém no site nos últimos 5 minutos.</p>
            @endif
        </div>
    </div>
</div>

{{-- Filtros.

     Os que aqui estavam eram dois — aparelho e utm_source — e o segundo nunca
     filtrou nada, porque `utm_source` está vazio em todos os registos: nunca
     chegou cá uma visita por campanha marcada. Substituído pelo CANAL, que se
     deriva do referrer e existe sempre. --}}
<div class="bg-white rounded-2xl shadow p-4 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Aparelho</label>
            <select wire:model.live="deviceFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="">Todos</option>
                @foreach(['desktop' => 'Computador', 'mobile' => 'Telemóvel', 'tablet' => 'Tablet', 'bot' => 'Robô'] as $v => $n)
                    <option value="{{ $v }}">{{ $n }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Veio de</label>
            <select wire:model.live="sourceFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="">Todos os canais</option>
                @foreach(['directo','orgânico','social','campanha','referência','interno'] as $c)
                    <option value="{{ $c }}">{{ ucfirst($c) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">País</label>
            <select wire:model.live="countryFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="">Todos</option>
                @foreach($paisesDisponiveis as $c)
                    <option value="{{ $c }}">{{ \App\Services\Analytics\Regiao::bandeira($c) }} {{ \App\Services\Analytics\Regiao::nomeDoPais($c) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Browser</label>
            <select wire:model.live="browserFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="">Todos</option>
                @foreach($browsersDisponiveis as $b)
                    <option value="{{ $b }}">{{ $b }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Página</label>
            <select wire:model.live="pathFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="">Todas</option>
                @foreach($paginasDisponiveis as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if($this->temFiltros)
        <div class="mt-3 flex items-center justify-between">
            <p class="text-xs text-gray-500">
                <i class="fas fa-filter mr-1"></i>
                A mostrar {{ number_format($totalVisitors) }} visitante(s) de {{ number_format($totalEvents) }} registo(s).
            </p>
            <button wire:click="limparFiltros" class="text-sm text-blue-600 hover:text-blue-800 font-semibold">
                <i class="fas fa-redo mr-1"></i>Limpar filtros
            </button>
        </div>
    @endif
</div>

{{-- KPIs principais --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-2xl shadow-lg p-4">
        <div class="flex items-center justify-between mb-2">
            <i class="fas fa-users text-2xl opacity-70"></i>
            @if($visitorsTrend !== 0)
                <span class="text-xs bg-white/20 px-2 py-1 rounded-full font-bold">
                    {{ $visitorsTrend > 0 ? '↑' : '↓' }} {{ abs($visitorsTrend) }}%
                </span>
            @endif
        </div>
        <p class="text-xs opacity-90 uppercase font-bold">Visitantes Únicos</p>
        <p class="text-3xl font-extrabold mt-1">{{ number_format($totalVisitors) }}</p>
    </div>

    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 text-white rounded-2xl shadow-lg p-4">
        <i class="fas fa-eye text-2xl opacity-70 mb-2"></i>
        <p class="text-xs opacity-90 uppercase font-bold">Pageviews</p>
        <p class="text-3xl font-extrabold mt-1">{{ number_format($totalPageviews) }}</p>
        <p class="text-xs opacity-80 mt-1">{{ $totalSessions }} sessões</p>
    </div>

    <div class="bg-gradient-to-br from-orange-500 to-red-600 text-white rounded-2xl shadow-lg p-4">
        <i class="fas fa-bullseye text-2xl opacity-70 mb-2"></i>
        <p class="text-xs opacity-90 uppercase font-bold">CTAs Clicados</p>
        <p class="text-3xl font-extrabold mt-1">{{ number_format($totalCtaClicks) }}</p>
        <p class="text-xs opacity-80 mt-1">📝 {{ $registerClicks }} · 💬 {{ $whatsappClicks }} · 📦 {{ $moduleClicks }}</p>
    </div>

    <div class="bg-gradient-to-br from-purple-500 to-pink-600 text-white rounded-2xl shadow-lg p-4">
        <i class="fas fa-percent text-2xl opacity-70 mb-2"></i>
        <p class="text-xs opacity-90 uppercase font-bold">Taxa Conversão</p>
        <p class="text-3xl font-extrabold mt-1">{{ $convRate }}%</p>
        <p class="text-xs opacity-80 mt-1">visitor → registo</p>
    </div>
</div>

{{-- Qualidade da visita. Um total de visitantes não distingue quem leu o site
     de quem fechou o separador — estas duas medidas distinguem. --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-2xl shadow p-4">
        <p class="text-xs text-gray-500 uppercase font-bold">Páginas por sessão</p>
        <p class="text-2xl font-extrabold text-gray-900 mt-1">{{ $paginasPorSessao }}</p>
    </div>
    <div class="bg-white rounded-2xl shadow p-4">
        <p class="text-xs text-gray-500 uppercase font-bold">Saem à primeira</p>
        <p class="text-2xl font-extrabold {{ $taxaRejeicao > 70 ? 'text-red-600' : 'text-gray-900' }} mt-1">{{ $taxaRejeicao }}%</p>
        <p class="text-[11px] text-gray-400">sessões de uma só página</p>
    </div>
    <div class="bg-white rounded-2xl shadow p-4">
        <p class="text-xs text-gray-500 uppercase font-bold">Pesquisas</p>
        <p class="text-2xl font-extrabold text-gray-900 mt-1">{{ number_format($totalSearches) }}</p>
    </div>
    <div class="bg-white rounded-2xl shadow p-4">
        <p class="text-xs text-gray-500 uppercase font-bold">Registos</p>
        <p class="text-2xl font-extrabold text-gray-900 mt-1">{{ number_format($totalEvents) }}</p>
    </div>
</div>

{{-- Tabs --}}
<div class="bg-white rounded-2xl shadow-lg mb-6">
    <div class="border-b border-gray-200 flex flex-wrap gap-1 px-3 pt-3 overflow-x-auto">
        @foreach([
            ['overview','Visão Geral','fa-chart-pie'],
            ['leads','Visitantes ('.count($leads).')','fa-fire'],
            ['traffic','De onde vêm','fa-route'],
            ['regiao','Região','fa-earth-africa'],
            ['pesquisas','Pesquisas','fa-magnifying-glass'],
            ['funnel','Funil','fa-filter'],
            ['live','Ao vivo','fa-bolt'],
        ] as $t)
            <button wire:click="setTab('{{ $t[0] }}')"
                    class="px-4 py-2.5 text-sm font-semibold transition rounded-t-lg whitespace-nowrap {{ $tab === $t[0] ? 'bg-blue-50 text-blue-700 border-b-2 border-blue-600' : 'text-gray-600 hover:bg-gray-50' }}">
                <i class="fas {{ $t[2] }} mr-1"></i>{{ $t[1] }}
            </button>
        @endforeach
    </div>

    <div class="p-4">

    {{-- ============= TAB: OVERVIEW ============= --}}
    @if($tab === 'overview')

        {{-- Gráfico de visitas (SVG inline) --}}
        <div class="mb-6">
            <h3 class="text-sm font-bold text-gray-700 mb-3">📈 Visitantes por dia</h3>
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-100">
                @php
                    $maxVal = max(array_column($days, 'value')) ?: 1;
                    $w = 1000; $h = 200; $pad = 30;
                    $count = count($days);
                    $stepX = $count > 1 ? ($w - $pad * 2) / ($count - 1) : 0;
                @endphp
                <svg viewBox="0 0 {{ $w }} {{ $h + 30 }}" class="w-full" preserveAspectRatio="none" style="max-height: 220px;">
                    {{-- Linhas guia --}}
                    @for($i = 0; $i <= 4; $i++)
                        <line x1="{{ $pad }}" y1="{{ $pad + $i * ($h - $pad) / 4 }}" x2="{{ $w - $pad }}" y2="{{ $pad + $i * ($h - $pad) / 4 }}" stroke="#cbd5e1" stroke-width="0.5" stroke-dasharray="2 2"/>
                    @endfor
                    {{-- Área --}}
                    @if($count > 0)
                        @php
                            $points = '';
                            foreach ($days as $idx => $d) {
                                $x = $pad + $idx * $stepX;
                                $y = $h - ($d['value'] / $maxVal * ($h - $pad - 10));
                                $points .= "{$x},{$y} ";
                            }
                            $area = "M {$pad},{$h} L " . $points . "L " . ($pad + ($count - 1) * $stepX) . ",{$h} Z";
                        @endphp
                        <path d="{{ $area }}" fill="url(#chartGr)" opacity="0.3"/>
                        <polyline points="{{ trim($points) }}" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linejoin="round"/>
                        @foreach($days as $idx => $d)
                            @php
                                $x = $pad + $idx * $stepX;
                                $y = $h - ($d['value'] / $maxVal * ($h - $pad - 10));
                            @endphp
                            <circle cx="{{ $x }}" cy="{{ $y }}" r="3.5" fill="white" stroke="#2563eb" stroke-width="2"/>
                            <text x="{{ $x }}" y="{{ $h + 18 }}" text-anchor="middle" font-size="10" fill="#64748b">{{ $d['label'] }}</text>
                        @endforeach
                    @endif
                    <defs>
                        <linearGradient id="chartGr" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0" stop-color="#2563eb"/>
                            <stop offset="1" stop-color="#2563eb" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
        </div>

        {{-- Top páginas + sources --}}
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <h3 class="text-sm font-bold text-gray-700 mb-3">🏆 Páginas mais visitadas</h3>
                <div class="space-y-2">
                    @forelse($topPages as $p)
                        @php $maxViews = $topPages->first()->views ?: 1; $pct = $p->views / $maxViews * 100; @endphp
                        <div class="bg-white border border-gray-100 rounded-lg p-2 hover:shadow transition">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-mono text-xs text-gray-700 truncate flex-1">{{ $p->path ?: '/' }}</span>
                                <span class="text-xs font-bold text-blue-600 ml-2">{{ $p->views }}</span>
                            </div>
                            <div class="bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-full" style="width: {{ $pct }}%"></div>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">{{ $p->visitors }} visitantes únicos</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 italic text-center py-4">Sem dados</p>
                    @endforelse
                </div>
            </div>

            <div>
                <h3 class="text-sm font-bold text-gray-700 mb-3">🌐 De onde vieram</h3>

                {{-- Canais primeiro: é a leitura de uma olhadela. O painel que
                     aqui esteve juntava tudo o que não fosse google/facebook
                     num "other", e contava o tráfego da própria aplicação como
                     se viesse de fora. --}}
                @if($canais->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mb-3">
                        @foreach($canais as $canal => $n)
                            <button wire:click="$set('sourceFilter', '{{ $canal }}')"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition hover:ring-2 hover:ring-offset-1 {{ \App\Services\Analytics\Origem::cor($canal) }}">
                                <i class="fas {{ \App\Services\Analytics\Origem::icone($canal) }} mr-1"></i>{{ ucfirst($canal) }}
                                <span class="ml-1 opacity-70">{{ $n }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="space-y-2">
                    @forelse($topSources as $s)
                        @php $maxV = $topSources->first()['visitantes'] ?: 1; $pct = $s['visitantes'] / $maxV * 100; @endphp
                        <div class="bg-white border border-gray-100 rounded-lg p-2">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-semibold text-gray-700 truncate">
                                    <i class="fas {{ \App\Services\Analytics\Origem::icone($s['canal']) }} mr-1 text-gray-400"></i>{{ $s['fonte'] }}
                                </span>
                                <span class="text-xs font-bold text-emerald-600 ml-2">{{ $s['visitantes'] }}</span>
                            </div>
                            <div class="bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-gradient-to-r from-emerald-500 to-teal-600 h-full" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 italic text-center py-4">Sem dados</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Devices + Browsers + Countries --}}
        <div class="grid md:grid-cols-3 gap-4 mt-6">
            <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-4 border border-purple-100">
                <h3 class="text-sm font-bold text-gray-700 mb-3">📱 Dispositivos</h3>
                @foreach($devices as $d)
                    @php
                        $total = $devices->sum('count') ?: 1;
                        $pct = round($d->count / $total * 100, 1);
                        $ic = ['mobile'=>'fa-mobile-screen','desktop'=>'fa-desktop','tablet'=>'fa-tablet','bot'=>'fa-robot'][$d->device_type] ?? 'fa-question';
                    @endphp
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs"><i class="fas {{ $ic }} mr-1 text-purple-600"></i>{{ ucfirst($d->device_type ?? 'unknown') }}</span>
                        <span class="text-sm font-bold">{{ $d->count }} <span class="text-xs text-gray-500">({{ $pct }}%)</span></span>
                    </div>
                @endforeach
            </div>

            <div class="bg-gradient-to-br from-cyan-50 to-blue-50 rounded-xl p-4 border border-cyan-100">
                <h3 class="text-sm font-bold text-gray-700 mb-3">🌐 Browsers</h3>
                @foreach($browsers as $b)
                    <div class="flex items-center justify-between mb-2 text-xs">
                        <span>{{ $b->browser }}</span>
                        <span class="font-bold text-cyan-700">{{ $b->count }}</span>
                    </div>
                @endforeach
                @if($browsers->isEmpty())<p class="text-xs text-gray-400 italic">Sem dados</p>@endif
            </div>

            <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-xl p-4 border border-amber-100">
                <h3 class="text-sm font-bold text-gray-700 mb-3">🌍 Países</h3>
                @foreach($countries as $c)
                    <div class="flex items-center justify-between mb-2 text-xs">
                        <span>
                            {{ \App\Services\Analytics\Regiao::bandeira($c->country) }}
                            {{ \App\Services\Analytics\Regiao::nomeDoPais($c->country) }}
                        </span>
                        <span class="font-bold text-amber-700">{{ $c->count }}</span>
                    </div>
                @endforeach
                @if($countries->isEmpty())
                    <p class="text-xs text-gray-400 italic">
                        Ainda sem região resolvida — vai sendo descoberta à medida que houver tráfego.
                    </p>
                @endif
                @if($regiaoPorResolver > 0)
                    <p class="text-[10px] text-gray-400 mt-2">{{ $regiaoPorResolver }} endereço(s) por resolver</p>
                @endif
            </div>
        </div>

        {{-- Cliques por evento --}}
        @if($eventBreakdown->count())
            <div class="mt-6">
                <h3 class="text-sm font-bold text-gray-700 mb-3">🎯 Eventos de CTA</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2">
                    @foreach($eventBreakdown as $e)
                        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-3 text-center">
                            <p class="text-xs text-gray-500 truncate">{{ $e->event_name }}</p>
                            <p class="text-lg font-bold text-orange-600">{{ $e->count }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    @endif

    {{-- ============= TAB: LEADS ============= --}}
    @if($tab === 'leads')
        <div class="mb-3 flex items-center gap-2">
            <i class="fas fa-fire text-orange-500"></i>
            <p class="text-sm text-gray-700">Visitantes ordenados por <strong>score</strong> de interesse — quanto mais alto, mais quente o lead.</p>
        </div>

        <div class="overflow-x-auto -mx-4 px-4">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left">
                        <th class="px-3 py-2 font-bold text-xs text-gray-600 uppercase">Score</th>
                        <th class="px-3 py-2 font-bold text-xs text-gray-600 uppercase">Visitor ID</th>
                        <th class="px-3 py-2 font-bold text-xs text-gray-600 uppercase text-center">Páginas</th>
                        <th class="px-3 py-2 font-bold text-xs text-gray-600 uppercase text-center">Cliques</th>
                        <th class="px-3 py-2 font-bold text-xs text-gray-600 uppercase">Origem</th>
                        <th class="px-3 py-2 font-bold text-xs text-gray-600 uppercase">Device</th>
                        <th class="px-3 py-2 font-bold text-xs text-gray-600 uppercase">Última visita</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($leads as $l)
                        @php
                            $hot = $l->score >= 50 ? 'red' : ($l->score >= 20 ? 'orange' : ($l->score >= 10 ? 'amber' : 'gray'));
                            $colors = ['red'=>'bg-red-100 text-red-700','orange'=>'bg-orange-100 text-orange-700','amber'=>'bg-amber-100 text-amber-700','gray'=>'bg-gray-100 text-gray-600'];
                            $emoji = $l->score >= 50 ? '🔥🔥' : ($l->score >= 20 ? '🔥' : ($l->score >= 10 ? '⭐' : '·'));
                        @endphp
                        <tr class="hover:bg-blue-50">
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold {{ $colors[$hot] }}">
                                    {{ $emoji }} {{ $l->score }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                {{-- Clicável: é aqui que se vê o percurso, página a
                                     página. Sem isto sabia-se que alguém viu cinco
                                     páginas e não QUAIS. --}}
                                <button wire:click="verVisitante('{{ $l->visitor_id }}')"
                                        class="font-mono text-xs text-blue-600 hover:text-blue-800 hover:underline">
                                    {{ substr($l->visitor_id, 0, 8) }}…
                                </button>
                                @if($l->country)
                                    <span class="ml-1" title="{{ \App\Services\Analytics\Regiao::nomeDoPais($l->country) }}">
                                        {{ \App\Services\Analytics\Regiao::bandeira($l->country) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">{{ $l->pageviews }} <span class="text-xs text-gray-400">({{ $l->pages_visited }} únicas)</span></td>
                            <td class="px-3 py-2 text-center font-bold text-orange-600">{{ $l->clicks }}</td>
                            <td class="px-3 py-2 text-xs text-gray-600">
                                @if($l->utm_source)<span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">{{ $l->utm_source }}</span>@endif
                                @if($l->referrer)<span class="text-gray-400 truncate inline-block max-w-[150px]">{{ str_replace(['https://','http://','www.'], '', $l->referrer) }}</span>@endif
                                @if(!$l->utm_source && !$l->referrer)<span class="text-gray-400">direto</span>@endif
                            </td>
                            <td class="px-3 py-2 text-xs">
                                <i class="fas fa-{{ $l->device === 'mobile' ? 'mobile-screen' : ($l->device === 'tablet' ? 'tablet' : 'desktop') }} mr-1 text-gray-500"></i>
                                {{ $l->browser ?? '—' }}
                                @if($l->country)<span class="ml-1 text-gray-400">{{ $l->country }}</span>@endif
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ \Carbon\Carbon::parse($l->last_seen)->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-12 text-gray-400 italic">Nenhum visitante no período selecionado</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- ============= TAB: TRAFFIC ============= --}}
    @if($tab === 'traffic')
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-sm font-bold text-gray-700 mb-3">Páginas (com bounce)</h3>
                <table class="w-full text-sm">
                    <thead><tr class="bg-gray-50"><th class="text-left px-2 py-1 text-xs">Path</th><th class="text-right px-2 py-1 text-xs">Views</th><th class="text-right px-2 py-1 text-xs">Únicos</th></tr></thead>
                    <tbody>
                        @foreach($topPages as $p)
                            <tr class="border-b border-gray-100"><td class="px-2 py-2 font-mono text-xs">{{ $p->path }}</td><td class="text-right px-2 py-2 font-bold">{{ $p->views }}</td><td class="text-right px-2 py-2 text-blue-600">{{ $p->visitors }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>
                <h3 class="text-sm font-bold text-gray-700 mb-3">Origens detalhadas</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="text-left px-2 py-1 text-xs">Fonte</th>
                            <th class="text-left px-2 py-1 text-xs">Canal</th>
                            <th class="text-right px-2 py-1 text-xs">Visitantes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topSources as $s)
                            <tr class="border-b border-gray-100">
                                <td class="px-2 py-2">{{ $s['fonte'] }}</td>
                                <td class="px-2 py-2">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ \App\Services\Analytics\Origem::cor($s['canal']) }}">
                                        {{ $s['canal'] }}
                                    </span>
                                </td>
                                <td class="text-right px-2 py-2 font-bold">{{ $s['visitantes'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-2 py-6 text-center text-gray-400 italic">Sem dados</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ============= TAB: REGIÃO ============= --}}
    @if($tab === 'regiao')
        @if($countries->isEmpty() && $regiaoPorResolver > 0)
            <div class="rounded-xl border-2 border-blue-200 bg-blue-50 p-4 mb-4">
                <p class="font-bold text-blue-800"><i class="fas fa-hourglass-half mr-2"></i>A descobrir de onde vêm</p>
                <p class="text-sm text-blue-700 mt-1">
                    {{ $regiaoPorResolver }} endereço(s) ainda por resolver. Vão sendo descobertos com o
                    tráfego do sistema, em lotes — não há tarefa agendada neste alojamento.
                </p>
            </div>
        @endif

        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-sm font-bold text-gray-700 mb-3">🌍 Países</h3>
                <div class="space-y-2">
                    @forelse($countries as $c)
                        @php $maxP = $countries->first()->count ?: 1; @endphp
                        <div class="bg-white border border-gray-100 rounded-lg p-2">
                            <div class="flex items-center justify-between mb-1">
                                <button wire:click="$set('countryFilter', '{{ $c->country }}')"
                                        class="text-sm font-semibold text-gray-700 hover:text-blue-600 transition">
                                    {{ \App\Services\Analytics\Regiao::bandeira($c->country) }}
                                    {{ \App\Services\Analytics\Regiao::nomeDoPais($c->country) }}
                                </button>
                                <span class="text-sm font-bold text-amber-700">{{ $c->count }}</span>
                            </div>
                            <div class="bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-gradient-to-r from-amber-500 to-orange-600 h-full" style="width: {{ $c->count / $maxP * 100 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 italic text-center py-6">Ainda sem países resolvidos.</p>
                    @endforelse
                </div>
            </div>

            <div>
                <h3 class="text-sm font-bold text-gray-700 mb-3">🏙️ Cidades</h3>
                <div class="space-y-2">
                    @forelse($cities as $c)
                        <div class="flex items-center justify-between bg-white border border-gray-100 rounded-lg p-2">
                            <span class="text-sm text-gray-700">
                                {{ \App\Services\Analytics\Regiao::bandeira($c->country) }} {{ $c->city }}
                            </span>
                            <span class="text-sm font-bold text-gray-900">{{ $c->count }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 italic text-center py-6">Ainda sem cidades resolvidas.</p>
                    @endforelse
                </div>

                <h3 class="text-sm font-bold text-gray-700 mt-6 mb-3">💻 Sistemas</h3>
                @foreach($sistemas as $s)
                    <div class="flex items-center justify-between mb-2 text-xs">
                        <span>{{ $s->os }}</span>
                        <span class="font-bold text-gray-700">{{ $s->count }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ============= TAB: PESQUISAS ============= --}}
    @if($tab === 'pesquisas')
        <div class="mb-4 rounded-xl border border-gray-200 bg-gray-50 p-3">
            <p class="text-xs text-gray-600">
                <i class="fas fa-circle-info mr-1"></i>
                O que as pessoas procuram nos ecrãs de venda. Registado no momento em que a pesquisa
                leva a escolher um artigo — e não a cada tecla premida, que encheria a lista de
                pedaços de palavras.
            </p>
        </div>

        <div class="space-y-2">
            @forelse($topSearches as $s)
                @php $maxS = $topSearches->first()->vezes ?: 1; @endphp
                <div class="bg-white border border-gray-100 rounded-lg p-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-semibold text-gray-900">{{ $s->search_term }}</span>
                        <span class="text-sm">
                            <span class="font-bold text-indigo-600">{{ $s->vezes }}</span>
                            <span class="text-xs text-gray-400 ml-1">{{ $s->pessoas }} pessoa(s)</span>
                        </span>
                    </div>
                    <div class="bg-gray-100 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 h-full" style="width: {{ $s->vezes / $maxS * 100 }}%"></div>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">
                        última vez {{ \Carbon\Carbon::parse($s->ultima)->diffForHumans() }}
                    </p>
                </div>
            @empty
                <div class="text-center py-12">
                    <i class="fas fa-magnifying-glass text-4xl text-gray-300 mb-3"></i>
                    <h3 class="font-bold text-gray-900 mb-1">Ainda sem pesquisas registadas</h3>
                    <p class="text-gray-500 text-sm max-w-lg mx-auto">
                        As pesquisas começam a aparecer aqui assim que alguém procurar um artigo no
                        ponto de venda e escolher um resultado. O site público não tem caixa de
                        pesquisa, por isso nada vem de lá.
                    </p>
                </div>
            @endforelse
        </div>
    @endif

    {{-- ============= TAB: FUNNEL ============= --}}
    @if($tab === 'funnel')
        <h3 class="text-sm font-bold text-gray-700 mb-4">🪜 Funil de conversão</h3>
        @php $maxF = max(array_column($funnel, 'count')) ?: 1; @endphp
        <div class="space-y-3 max-w-3xl">
            @foreach($funnel as $i => $f)
                @php
                    $w = $f['count'] / $maxF * 100;
                    $dropFromPrev = $i > 0 && $funnel[$i-1]['count'] > 0 ? round(($funnel[$i-1]['count'] - $f['count']) / $funnel[$i-1]['count'] * 100, 1) : 0;
                @endphp
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-bold text-gray-700"><i class="fas {{ $f['icon'] }} mr-2" style="color: {{ $f['color'] }};"></i>{{ $f['stage'] }}</span>
                        <span class="text-sm">
                            <strong>{{ $f['count'] }}</strong>
                            @if($i > 0 && $dropFromPrev > 0)
                                <span class="text-red-500 text-xs ml-2">↓ {{ $dropFromPrev }}% drop-off</span>
                            @endif
                        </span>
                    </div>
                    <div class="bg-gray-100 rounded-lg h-10 overflow-hidden">
                        <div class="h-full flex items-center px-3 text-white font-bold transition-all" style="width: {{ max($w, 5) }}%; background: linear-gradient(90deg, {{ $f['color'] }}, {{ $f['color'] }}dd);">
                            <span class="text-sm">{{ $f['count'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($funnel[0]['count'] > 0)
            <div class="mt-6 bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-200 rounded-xl p-4">
                <p class="text-sm font-bold text-emerald-800">
                    <i class="fas fa-trophy mr-1"></i>Conversão Landing → Registo:
                    <span class="text-2xl ml-2">{{ round($funnel[3]['count'] / $funnel[0]['count'] * 100, 2) }}%</span>
                </p>
            </div>
        @endif
    @endif

    {{-- ============= TAB: LIVE ============= --}}
    @if($tab === 'live')
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-gray-700"><i class="fas fa-bolt text-yellow-500 mr-1"></i>Eventos em tempo real (últimos 30)</h3>
            <button wire:click="$refresh" class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg font-bold"><i class="fas fa-sync mr-1"></i>Atualizar</button>
        </div>
        <div class="space-y-1 max-h-[600px] overflow-y-auto">
            @forelse($recentEvents as $e)
                @php
                    $iconMap = ['pageview'=>['fa-eye','blue'],'cta_click'=>['fa-bullseye','orange'],'click'=>['fa-mouse-pointer','purple'],'form_submit'=>['fa-paper-plane','emerald']];
                    [$ic, $col] = $iconMap[$e->type] ?? ['fa-circle','gray'];
                @endphp
                <div class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg border-l-4 border-{{ $col }}-500 bg-{{ $col }}-50/30">
                    <i class="fas {{ $ic }} text-{{ $col }}-500"></i>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm">
                            <span class="font-bold uppercase text-xs text-{{ $col }}-700 bg-{{ $col }}-100 px-1.5 py-0.5 rounded mr-1">{{ $e->type }}</span>
                            @if($e->event_name)<span class="font-mono text-xs">{{ $e->event_name }}</span>@endif
                        </div>
                        <p class="text-xs text-gray-500 truncate">{{ $e->path }}
                            @if($e->meta && isset($e->meta['module']))· <strong>{{ $e->meta['module'] }}</strong>@endif
                            @if($e->utm_source)· UTM: {{ $e->utm_source }}@endif
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] text-gray-400 font-mono">{{ substr($e->visitor_id, 0, 6) }}</p>
                        <p class="text-[10px] text-gray-500">{{ $e->created_at?->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <p class="text-center py-12 text-gray-400 italic">Sem eventos no período selecionado</p>
            @endforelse
        </div>
    @endif

    </div>
</div>

<p class="text-xs text-gray-400 text-center mt-4">
    Total de eventos no período: <strong>{{ number_format($totalEvents) }}</strong> · 100% próprio · Sem cookies de terceiros
</p>

{{-- Percurso de um visitante: por onde entrou, por onde andou, onde parou. --}}
@if($visitanteAberto)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
         wire:click.self="fecharVisitante">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b flex items-center justify-between sticky top-0 bg-white z-10">
                <div>
                    <h3 class="font-bold text-gray-900">Percurso do visitante</h3>
                    <p class="font-mono text-xs text-gray-500">{{ $visitanteAberto }}</p>
                </div>
                <button wire:click="fecharVisitante" class="text-gray-400 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="p-6">
                @php $primeiro = $percurso->first(); @endphp

                @if($primeiro)
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6 text-sm">
                        <div>
                            <span class="block text-xs text-gray-500">Chegou</span>
                            {{ $primeiro->created_at->format('d/m/Y H:i') }}
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500">Veio de</span>
                            @php $o = \App\Services\Analytics\Origem::classificar($primeiro->referrer, $primeiro->utm_source, parse_url(config('app.url'), PHP_URL_HOST)); @endphp
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ \App\Services\Analytics\Origem::cor($o['canal']) }}">{{ $o['fonte'] }}</span>
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500">Onde</span>
                            {{ \App\Services\Analytics\Regiao::bandeira($primeiro->country) }}
                            {{ $primeiro->city ?: \App\Services\Analytics\Regiao::nomeDoPais($primeiro->country) }}
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500">Aparelho</span>
                            {{ ucfirst($primeiro->device_type ?? '—') }} · {{ $primeiro->browser ?: '—' }}
                        </div>
                    </div>
                @endif

                <div class="relative border-l-2 border-gray-200 ml-3 space-y-4">
                    @foreach($percurso as $e)
                        @php
                            $cor = match($e->type) {
                                'pageview'  => 'bg-blue-500',
                                'cta_click' => 'bg-orange-500',
                                'search'    => 'bg-indigo-500',
                                default     => 'bg-gray-400',
                            };
                        @endphp
                        <div class="relative pl-6">
                            <span class="absolute -left-[7px] top-1.5 w-3 h-3 rounded-full {{ $cor }} ring-2 ring-white"></span>
                            <div class="flex items-baseline justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">
                                        @if($e->type === 'search')
                                            Pesquisou <strong>{{ $e->search_term }}</strong>
                                        @elseif($e->type === 'cta_click')
                                            Clicou <strong>{{ $e->event_name }}</strong>
                                        @else
                                            {{ $e->path ?: '/' }}
                                        @endif
                                    </p>
                                    @if($e->type === 'pageview' && $e->duration_seconds)
                                        <p class="text-[11px] text-gray-400">esteve {{ $e->duration_seconds }}s</p>
                                    @endif
                                </div>
                                <span class="text-xs text-gray-400 shrink-0">{{ $e->created_at->format('d/m H:i:s') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($percurso->isEmpty())
                    <p class="text-center text-gray-400 italic py-8">Sem registos para este visitante.</p>
                @endif
            </div>
        </div>
    </div>
@endif
</div>
