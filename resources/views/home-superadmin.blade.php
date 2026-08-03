@extends('layouts.superadmin')

@section('title', 'Início — Super Admin')
@section('subtitle', 'Visão geral do sistema e analytics em tempo real')

@section('content')
<div class="space-y-6">

    {{-- Hero / Saudação --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 text-white p-6 md:p-8 shadow-2xl">
        <div class="absolute -top-12 -right-12 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-12 -left-12 w-64 h-64 bg-yellow-400/10 rounded-full blur-3xl"></div>
        <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1 bg-white/20 backdrop-blur px-3 py-1 rounded-full text-xs font-bold">
                    <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span> Online
                </span>
                <h1 class="text-3xl md:text-4xl font-extrabold mt-2">Olá, {{ $user->name }}! 👋</h1>
                <p class="text-white/80 mt-1">{{ ucfirst(\Carbon\Carbon::now()->locale('pt')->isoFormat('dddd, D [de] MMMM [de] YYYY [às] HH:mm')) }}</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('superadmin.tenants') }}" class="bg-white/20 hover:bg-white/30 backdrop-blur px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2">
                    <i class="fas fa-building"></i>Tenants
                </a>
                <a href="{{ route('superadmin.analytics') }}" class="bg-white text-indigo-600 hover:bg-yellow-300 px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2 shadow-lg">
                    <i class="fas fa-fire text-orange-500"></i>Ver Leads
                </a>
            </div>
        </div>
    </div>

    {{-- KPIs principais --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <a href="{{ route('superadmin.tenants') }}" class="group bg-white rounded-2xl shadow hover:shadow-2xl border border-gray-100 p-4 transition hover:-translate-y-1">
            <div class="flex items-center justify-between mb-2">
                <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center"><i class="fas fa-building"></i></div>
                @if($tenantGrowth !== 0)
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $tenantGrowth > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $tenantGrowth > 0 ? '↑' : '↓' }} {{ abs($tenantGrowth) }}%</span>
                @endif
            </div>
            <p class="text-xs text-gray-500 uppercase font-bold">Tenants</p>
            <p class="text-3xl font-extrabold text-gray-900">{{ $totalTenants }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $activeTenants }} ativos · {{ $trialTenants }} em trial</p>
        </a>

        <a href="#" class="group bg-white rounded-2xl shadow hover:shadow-2xl border border-gray-100 p-4 transition hover:-translate-y-1">
            <div class="flex items-center justify-between mb-2">
                <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center"><i class="fas fa-users"></i></div>
                @if($newUsersToday > 0)<span class="text-xs font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-700">+{{ $newUsersToday }} hoje</span>@endif
            </div>
            <p class="text-xs text-gray-500 uppercase font-bold">Utilizadores</p>
            <p class="text-3xl font-extrabold text-gray-900">{{ number_format($totalUsers) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $activeUsersToday }} ativos hoje</p>
        </a>

        <a href="{{ route('superadmin.billing') }}" class="group bg-white rounded-2xl shadow hover:shadow-2xl border border-gray-100 p-4 transition hover:-translate-y-1">
            <div class="flex items-center justify-between mb-2">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center"><i class="fas fa-coins"></i></div>
                <span class="text-xs font-bold text-emerald-700">MRR</span>
            </div>
            <p class="text-xs text-gray-500 uppercase font-bold">Receita Mensal</p>
            <p class="text-2xl font-extrabold text-gray-900">{{ number_format($mrr, 0, ',', '.') }} <span class="text-sm">Kz</span></p>
            <p class="text-xs text-gray-500 mt-1">Pago no mês: {{ number_format($monthlyRevenue, 0, ',', '.') }} Kz</p>
        </a>

        <a href="{{ route('superadmin.analytics') }}" class="group bg-gradient-to-br from-orange-500 to-red-600 text-white rounded-2xl shadow hover:shadow-2xl p-4 transition hover:-translate-y-1">
            <div class="flex items-center justify-between mb-2">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center"><i class="fas fa-fire"></i></div>
                <span class="text-xs font-bold bg-white/20 px-2 py-0.5 rounded-full">30d</span>
            </div>
            <p class="text-xs text-white/80 uppercase font-bold">Visitantes Landing</p>
            <p class="text-3xl font-extrabold">{{ number_format($visitors30d) }}</p>
            <p class="text-xs text-white/80 mt-1">{{ $pageviews30d }} pageviews · {{ $registerClicks30d }} click-register</p>
        </a>
    </div>

    {{-- Grid principal: gráfico + leads --}}
    <div class="grid lg:grid-cols-3 gap-4">

        {{-- Gráfico --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-bold text-gray-900">📈 Crescimento — últimos 14 dias</h3>
                    <p class="text-xs text-gray-500">Novos tenants e utilizadores por dia</p>
                </div>
                <div class="flex gap-3 text-xs">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-blue-500 rounded"></span>Tenants</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-purple-500 rounded"></span>Utilizadores</span>
                </div>
            </div>

            @php
                $maxT = max(array_column($series, 'tenants')) ?: 1;
                $maxU = max(array_column($series, 'users')) ?: 1;
                $maxAll = max($maxT, $maxU, 1);
                $w = 700; $h = 200; $pad = 30;
                $count = count($series);
                $stepX = $count > 1 ? ($w - $pad * 2) / ($count - 1) : 0;
            @endphp
            <svg viewBox="0 0 {{ $w }} {{ $h + 30 }}" class="w-full" preserveAspectRatio="none" style="max-height:220px;">
                @for($i = 0; $i <= 4; $i++)
                    <line x1="{{ $pad }}" y1="{{ $pad + $i * ($h - $pad) / 4 }}" x2="{{ $w - $pad }}" y2="{{ $pad + $i * ($h - $pad) / 4 }}" stroke="#e2e8f0" stroke-width="0.5" stroke-dasharray="2 2"/>
                @endfor

                @php
                    $pointsT = ''; $pointsU = '';
                    foreach ($series as $i => $d) {
                        $x = $pad + $i * $stepX;
                        $yT = $h - ($d['tenants'] / $maxAll * ($h - $pad - 10));
                        $yU = $h - ($d['users'] / $maxAll * ($h - $pad - 10));
                        $pointsT .= "{$x},{$yT} ";
                        $pointsU .= "{$x},{$yU} ";
                    }
                @endphp
                <polyline points="{{ trim($pointsU) }}" fill="none" stroke="#a855f7" stroke-width="2"/>
                <polyline points="{{ trim($pointsT) }}" fill="none" stroke="#3b82f6" stroke-width="2.5"/>

                @foreach($series as $i => $d)
                    @php
                        $x = $pad + $i * $stepX;
                        $yT = $h - ($d['tenants'] / $maxAll * ($h - $pad - 10));
                        $yU = $h - ($d['users'] / $maxAll * ($h - $pad - 10));
                    @endphp
                    <circle cx="{{ $x }}" cy="{{ $yU }}" r="2.5" fill="#a855f7"/>
                    <circle cx="{{ $x }}" cy="{{ $yT }}" r="3" fill="#3b82f6"/>
                    <text x="{{ $x }}" y="{{ $h + 18 }}" text-anchor="middle" font-size="9" fill="#94a3b8">{{ $d['label'] }}</text>
                @endforeach
            </svg>

            <div class="grid grid-cols-3 gap-3 mt-4 pt-4 border-t border-gray-100">
                <div class="text-center">
                    <p class="text-xs text-gray-500">Hoje</p>
                    <p class="text-lg font-bold text-blue-600">{{ $newTenantsToday }} <span class="text-xs text-gray-400">tenants</span></p>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-500">7 dias</p>
                    <p class="text-lg font-bold text-blue-600">{{ $newTenants7d }}</p>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-500">30 dias</p>
                    <p class="text-lg font-bold text-blue-600">{{ $newTenants30d }}</p>
                </div>
            </div>
        </div>

        {{-- Hot Leads --}}
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-bold text-gray-900">🔥 Leads quentes</h3>
                <a href="{{ route('superadmin.analytics') }}" class="text-xs text-blue-600 font-bold hover:underline">Ver todos →</a>
            </div>
            @if($hotLeads->isEmpty())
                <p class="text-sm text-gray-400 italic text-center py-8">Sem leads ainda. Aguarda visitantes na landing.</p>
            @else
                <div class="space-y-2">
                    @foreach($hotLeads as $l)
                        @php
                            $hot = $l->score >= 50 ? '🔥🔥' : ($l->score >= 20 ? '🔥' : '⭐');
                            $col = $l->score >= 50 ? 'red' : ($l->score >= 20 ? 'orange' : 'amber');
                            $colClass = "bg-{$col}-100 text-{$col}-700";
                        @endphp
                        <div class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50">
                            <span class="text-lg">{{ $hot }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-mono text-gray-600 truncate">{{ substr($l->visitor_id, 0, 12) }}…</p>
                                <p class="text-[10px] text-gray-400">
                                    {{ $l->device ?? '—' }} · {{ $l->country ?? 'AO' }}
                                    @if($l->utm_source) · {{ $l->utm_source }}@endif
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold {{ $colClass }}">{{ $l->score }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Top páginas + Origens --}}
    <div class="grid md:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-5">
            <h3 class="font-bold text-gray-900 mb-3">🏆 Páginas mais visitadas (30d)</h3>
            @if($topPages->isEmpty())
                <p class="text-sm text-gray-400 italic">Ainda sem dados.</p>
            @else
                @php $maxV = $topPages->first()->views ?: 1; @endphp
                @foreach($topPages as $p)
                    <div class="mb-2 last:mb-0">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="font-mono text-gray-700 truncate">{{ $p->path ?: '/' }}</span>
                            <span class="font-bold text-blue-600">{{ $p->views }}</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-blue-500 to-blue-600" style="width:{{ $p->views / $maxV * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-5">
            <h3 class="font-bold text-gray-900 mb-3">🌐 Origens de tráfego (30d)</h3>
            @if($topSources->isEmpty())
                <p class="text-sm text-gray-400 italic">Ainda sem dados.</p>
            @else
                @php $maxS = $topSources->first()->v ?: 1; @endphp
                @foreach($topSources as $s)
                    @php $icon = ['google'=>'fab fa-google text-blue-500','facebook'=>'fab fa-facebook text-blue-700','whatsapp'=>'fab fa-whatsapp text-green-500','direct'=>'fas fa-arrow-right text-gray-500','other'=>'fas fa-globe text-gray-500'][$s->source] ?? 'fas fa-link text-gray-500'; @endphp
                    <div class="mb-2 last:mb-0">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="capitalize text-gray-700"><i class="{{ $icon }} mr-1"></i>{{ $s->source }}</span>
                            <span class="font-bold text-emerald-600">{{ $s->v }}</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-600" style="width:{{ $s->v / $maxS * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Tenants recentes + Atalhos rápidos --}}
    <div class="grid lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-bold text-gray-900">🏢 Tenants recentes</h3>
                <a href="{{ route('superadmin.tenants') }}" class="text-xs text-blue-600 font-bold hover:underline">Ver todos →</a>
            </div>
            @if($recentTenants->isEmpty())
                <p class="text-sm text-gray-400 italic text-center py-8">Nenhum tenant ainda.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left py-2 text-xs font-bold text-gray-500 uppercase">Empresa</th>
                                <th class="text-left py-2 text-xs font-bold text-gray-500 uppercase">Slug</th>
                                <th class="text-center py-2 text-xs font-bold text-gray-500 uppercase">Status</th>
                                <th class="text-right py-2 text-xs font-bold text-gray-500 uppercase">Criado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentTenants as $t)
                                <tr class="border-b border-gray-50 hover:bg-blue-50/30">
                                    <td class="py-2 font-semibold text-gray-800">{{ $t->name }}</td>
                                    <td class="py-2 font-mono text-xs text-gray-500">{{ $t->slug }}</td>
                                    <td class="py-2 text-center">
                                        @if($t->is_active)
                                            <span class="inline-block w-2 h-2 rounded-full bg-green-500" title="Ativo"></span>
                                        @else
                                            <span class="inline-block w-2 h-2 rounded-full bg-red-500" title="Inativo"></span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-right text-xs text-gray-500">{{ $t->created_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Atalhos + Sistema --}}
        <div class="space-y-4">
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-5">
                <h3 class="font-bold text-gray-900 mb-3">⚡ Atalhos rápidos</h3>
                <div class="grid grid-cols-2 gap-2">
                    @foreach([
                        ['superadmin.dashboard','fa-chart-line','blue','Dashboard'],
                        ['superadmin.analytics','fa-fire','orange','Leads'],
                        ['superadmin.plans','fa-tags','pink','Planos'],
                        ['superadmin.modules','fa-puzzle-piece','purple','Módulos'],
                        ['superadmin.system-commands','fa-terminal','green','Comandos'],
                        ['superadmin.system-settings','fa-cog','gray','Definições'],
                    ] as $sc)
                        <a href="{{ route($sc[0]) }}" class="flex flex-col items-center gap-1 p-3 rounded-xl bg-gray-50 hover:bg-{{ $sc[2] }}-50 transition border border-transparent hover:border-{{ $sc[2] }}-200">
                            <i class="fas {{ $sc[1] }} text-{{ $sc[2] }}-600"></i>
                            <span class="text-[10px] font-bold text-gray-700">{{ $sc[3] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl shadow-lg p-5">
                <h3 class="font-bold mb-3"><i class="fas fa-server mr-2"></i>Sistema</h3>
                <dl class="grid grid-cols-2 gap-2 text-xs">
                    <div><dt class="text-slate-400">PHP</dt><dd class="font-mono font-bold">{{ $systemInfo['php'] }}</dd></div>
                    <div><dt class="text-slate-400">Laravel</dt><dd class="font-mono font-bold">{{ $systemInfo['laravel'] }}</dd></div>
                    <div><dt class="text-slate-400">Ambiente</dt><dd class="font-mono font-bold">{{ $systemInfo['env'] }}</dd></div>
                    <div><dt class="text-slate-400">Debug</dt><dd class="font-mono font-bold {{ $systemInfo['debug'] === 'ON' ? 'text-red-400' : 'text-green-400' }}">{{ $systemInfo['debug'] }}</dd></div>
                    <div><dt class="text-slate-400">DB</dt><dd class="font-mono font-bold">{{ $systemInfo['db'] }}</dd></div>
                    <div><dt class="text-slate-400">Cache</dt><dd class="font-mono font-bold">{{ $systemInfo['cache'] }}</dd></div>
                    <div><dt class="text-slate-400">Queue</dt><dd class="font-mono font-bold">{{ $systemInfo['queue'] }}</dd></div>
                    <div><dt class="text-slate-400">TZ</dt><dd class="font-mono font-bold">{{ $systemInfo['tz'] }}</dd></div>
                </dl>
            </div>
        </div>
    </div>

    {{-- Alerta de pedidos pendentes --}}
    @if($pendingOrders > 0)
    <div class="bg-gradient-to-r from-amber-500 to-orange-600 text-white rounded-2xl p-5 shadow-lg flex items-center justify-between">
        <div>
            <h3 class="font-bold text-lg"><i class="fas fa-bell mr-2"></i>{{ $pendingOrders }} pedido(s) pendente(s)</h3>
            <p class="text-sm text-white/80">Aguardam aprovação ou ação no sistema</p>
        </div>
        <a href="{{ route('superadmin.billing') }}" class="bg-white text-orange-600 px-5 py-2 rounded-xl font-bold hover:bg-yellow-100">Ver →</a>
    </div>
    @endif

</div>
@endsection
