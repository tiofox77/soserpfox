<div>
{{-- Header --}}
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">📊 Analytics & Leads</h1>
        <p class="text-sm text-gray-500">Rastreamento avançado da landing page e fluxo de conversão</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @foreach([['today','Hoje'],['7d','7 dias'],['30d','30 dias'],['90d','90 dias'],['all','Tudo']] as $r)
            <button wire:click="setRange('{{ $r[0] }}')"
                    class="px-3 py-2 rounded-lg text-xs font-bold transition {{ $range === $r[0] ? 'bg-blue-600 text-white shadow' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                {{ $r[1] }}
            </button>
        @endforeach
    </div>
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

{{-- Tabs --}}
<div class="bg-white rounded-2xl shadow-lg mb-6">
    <div class="border-b border-gray-200 flex flex-wrap gap-1 px-3 pt-3 overflow-x-auto">
        @foreach([
            ['overview','Visão Geral','fa-chart-pie'],
            ['leads','Leads ('.count($leads).')','fa-fire'],
            ['traffic','Tráfego','fa-route'],
            ['funnel','Funil','fa-filter'],
            ['live','Live Feed','fa-bolt'],
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
                <h3 class="text-sm font-bold text-gray-700 mb-3">🌐 Origens de tráfego</h3>
                <div class="space-y-2">
                    @forelse($topSources as $s)
                        @php
                            $maxV = $topSources->first()->visitors ?: 1; $pct = $s->visitors / $maxV * 100;
                            $icon = ['google'=>'fab fa-google text-blue-500','facebook'=>'fab fa-facebook text-blue-700','instagram'=>'fab fa-instagram text-pink-500','linkedin'=>'fab fa-linkedin text-blue-600','whatsapp'=>'fab fa-whatsapp text-green-500','direct'=>'fas fa-arrow-right text-gray-400','other'=>'fas fa-globe text-gray-500'][$s->source] ?? 'fas fa-link text-gray-400';
                        @endphp
                        <div class="bg-white border border-gray-100 rounded-lg p-2">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-semibold text-gray-700 capitalize"><i class="{{ $icon }} mr-1"></i>{{ $s->source }}</span>
                                <span class="text-xs font-bold text-emerald-600">{{ $s->visitors }}</span>
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
                <h3 class="text-sm font-bold text-gray-700 mb-3">🌍 Países (top 10)</h3>
                @foreach($countries as $c)
                    <div class="flex items-center justify-between mb-2 text-xs">
                        <span class="font-mono">{{ strtoupper($c->country) }}</span>
                        <span class="font-bold text-amber-700">{{ $c->count }}</span>
                    </div>
                @endforeach
                @if($countries->isEmpty())<p class="text-xs text-gray-400 italic">Sem dados de país (configura header CF-IPCountry no Cloudflare)</p>@endif
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
                            <td class="px-3 py-2 font-mono text-xs text-gray-500">{{ substr($l->visitor_id, 0, 8) }}…</td>
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
                    <thead><tr class="bg-gray-50"><th class="text-left px-2 py-1 text-xs">Source</th><th class="text-right px-2 py-1 text-xs">Visitantes</th></tr></thead>
                    <tbody>
                        @foreach($topSources as $s)
                            <tr class="border-b border-gray-100"><td class="px-2 py-2 capitalize">{{ $s->source }}</td><td class="text-right px-2 py-2 font-bold">{{ $s->visitors }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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
</div>
