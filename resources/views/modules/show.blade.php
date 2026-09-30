<x-modules-layout
    :title="$module['name']"
    :description="$seo['descricao']"
    :tituloSeo="$seo['titulo']"
    :canonical="$seo['url']"
    :dadosEstruturados="$dadosEstruturados"
    :ctaText="'Experimenta o ' . $module['name'] . ' grátis durante ' . ($plan?->trial_days ?? 14) . ' dias.'"
    :whatsapp="$module['whatsapp']"
    :gradientFrom="$module['gradient_from']"
    :gradientTo="$module['gradient_to']">

<style>
    :root {
        --from: {{ $module['gradient_from'] }};
        --to: {{ $module['gradient_to'] }};
    }
</style>

{{-- HERO --}}
<section class="relative overflow-hidden" style="background: linear-gradient(135deg, {{ $module['gradient_from'] }}, {{ $module['gradient_to'] }});">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-white rounded-full"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-white rounded-full"></div>
    </div>
    <div class="max-w-7xl mx-auto px-4 py-16 md:py-24 relative">
        <div class="grid md:grid-cols-2 gap-10 items-center">
            <div class="text-white">
                {{-- As migalhas à vista, as mesmas do BreadcrumbList. --}}
                <nav aria-label="Migalhas" class="mb-4 text-sm text-white/80">
                    <ol class="flex flex-wrap items-center gap-1.5">
                        @foreach($migalhas as [$nomeDaMigalha, $urlDaMigalha])
                            <li class="flex items-center gap-1.5">
                                @if($loop->last)
                                    <span aria-current="page" class="font-semibold text-white">{{ $nomeDaMigalha }}</span>
                                @else
                                    <a href="{{ $loop->first ? '/' : '/modulos' }}" class="hover:text-white hover:underline">{{ $nomeDaMigalha }}</a>
                                    <i class="fas fa-chevron-right text-[10px] opacity-60" aria-hidden="true"></i>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
                <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur px-3 py-1.5 rounded-full text-xs font-bold mb-4">
                    <i class="fas {{ $module['icon'] }}"></i> MÓDULO
                </div>
                <h1 class="text-4xl md:text-5xl font-extrabold mb-4 leading-tight">{{ $module['name'] }}</h1>
                <p class="text-xl opacity-95 mb-6">{{ $module['tagline'] }}</p>
                <ul class="space-y-2 mb-8">
                    @foreach($module['hero_features'] as $feat)
                        <li class="flex items-center gap-2"><i class="fas fa-check-circle text-white/90"></i><span>{{ $feat }}</span></li>
                    @endforeach
                </ul>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('register') }}" class="bg-white text-gray-900 px-6 py-3 rounded-xl font-bold shadow-lg hover:shadow-2xl transition text-center">
                        <i class="fas fa-rocket mr-2"></i>Experimentar Grátis
                    </a>
                    <a href="#pricing" class="bg-white/20 backdrop-blur border-2 border-white text-white px-6 py-3 rounded-xl font-bold text-center hover:bg-white/30">
                        <i class="fas fa-tag mr-2"></i>Ver Preços
                    </a>
                </div>
            </div>

            {{-- Hero illustration (mockup realista). Também no telemóvel
                 (26/09/2026): era aí que entravam os anúncios, e o produto
                 não aparecia — fica por baixo dos botões. --}}
            <div class="mt-2 md:mt-0">
                @include('modules.partials.mockup', ['slug' => $module['slug']])
            </div>
        </div>
    </div>
</section>

{{-- MÓDULO EM FOCO: PERFIL + FLUXO + RESULTADOS --}}
<section class="py-16 bg-slate-50 border-y border-slate-200">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid lg:grid-cols-3 gap-8">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white" style="background: linear-gradient(135deg, {{ $module['gradient_from'] }}, {{ $module['gradient_to'] }});">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="font-bold text-lg text-gray-900">Para quem é este módulo?</h3>
                </div>
                <div class="space-y-3">
                    @foreach($module['targets'] ?? [] as $target)
                        <div class="flex items-center gap-2 text-sm text-gray-700">
                            <i class="fas {{ $target['icon'] ?? 'fa-bullseye' }} text-slate-500"></i>
                            <span>{{ $target['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm lg:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white" style="background: linear-gradient(135deg, {{ $module['gradient_from'] }}, {{ $module['gradient_to'] }});">
                        <i class="fas fa-route"></i>
                    </div>
                    <h3 class="font-bold text-lg text-gray-900">Como funciona na prática (em 4 passos)</h3>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach($module['workflow'] ?? [] as $idx => $item)
                        <div class="relative pl-10">
                            <span class="absolute left-2 top-2 flex items-center justify-center h-6 w-6 rounded-full text-white text-xs font-bold" style="background: {{ $module['gradient_from'] }};">{{ $idx + 1 }}</span>
                            <div class="pl-2">
                                <p class="font-semibold text-sm text-gray-900 flex items-center gap-2">
                                    <i class="fas {{ $item['icone'] ?? 'fa-circle-check' }}" style="color: {{ $module['gradient_from'] }};"></i>
                                    {{ $item['passo'] }}
                                </p>
                                <p class="text-sm text-gray-600 mt-1">{{ $item['detalhe'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- INDICADORES E INTEGRAÇÕES --}}
<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid md:grid-cols-2 gap-8">
            <div>
                <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-5">Indicadores que importam</h3>
                <p class="text-sm text-gray-600 mb-5">O impacto esperado para este módulo:</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach($module['metrics'] ?? [] as $metric)
                        <div class="border border-slate-200 rounded-xl p-4 bg-slate-50">
                            <p class="text-sm font-bold text-gray-900">{{ $metric['valor'] }}</p>
                            <p class="text-xs text-gray-600 mt-2">{{ $metric['texto'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-5">Integra com</h3>
                <p class="text-sm text-gray-600 mb-4">Módulos e áreas que puxam mais valor quando ligados a este módulo.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($module['integrations'] ?? [] as $integration)
                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-slate-200 bg-slate-50 text-sm text-gray-700">
                            <i class="fas fa-plug text-gray-400"></i>{{ $integration }}
                        </span>
                    @endforeach
                </div>
                <div class="mt-6 rounded-2xl border border-slate-200 bg-gradient-to-r from-slate-50 to-slate-100 p-5">
                    <p class="font-semibold text-gray-900 mb-2">
                        <i class="fas fa-bullseye mr-2" style="color: {{ $module['gradient_from'] }};"></i>Objetivo da implementação
                    </p>
                    <p class="text-sm text-gray-600">Padronizar operação, reduzir retrabalho e deixar toda a informação pronta para controlo financeiro, fiscal e operacional.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- FEATURES GRID --}}
<section class="py-16 md:py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-12">
            <p class="text-sm font-bold uppercase mb-2" style="color: {{ $module['gradient_from'] }};">Funcionalidades</p>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900">Tudo o que precisas, num só lugar</h2>
            <p class="text-gray-600 mt-3 max-w-2xl mx-auto">{{ $module['description'] }}</p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($module['features'] as $feature)
                <div class="feature-card bg-slate-50 rounded-2xl p-6 border border-slate-100">
                    <div class="w-14 h-14 rounded-xl flex items-center justify-center mb-4" style="background: linear-gradient(135deg, {{ $module['gradient_from'] }}, {{ $module['gradient_to'] }});">
                        <i class="fas {{ $feature['icon'] }} text-white text-xl"></i>
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">{{ $feature['title'] }}</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $feature['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- SCREENSHOTS / MOCKUPS --}}
<section class="py-16 md:py-24" style="background: linear-gradient(135deg, #f8fafc, #f1f5f9);">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-12">
            <p class="text-sm font-bold uppercase mb-2" style="color: {{ $module['gradient_from'] }};">Vê em ação</p>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900">Interface moderna e intuitiva</h2>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            @foreach($module['screenshots'] as $sc)
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition group">
                    {{-- Mockup ilustrativo --}}
                    <div class="aspect-video flex items-center justify-center relative" style="background: linear-gradient(135deg, {{ $module['gradient_from'] }}15, {{ $module['gradient_to'] }}25);">
                        <i class="fas {{ $sc['icon'] }} text-6xl group-hover:scale-110 transition" style="color: {{ $module['gradient_from'] }};"></i>
                        <div class="absolute top-3 left-3 right-3 flex gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-full bg-white/60"></div>
                            <div class="w-2.5 h-2.5 rounded-full bg-white/60"></div>
                            <div class="w-2.5 h-2.5 rounded-full bg-white/60"></div>
                        </div>
                    </div>
                    <div class="p-5">
                        <h4 class="font-bold text-gray-900 mb-1">{{ $sc['title'] }}</h4>
                        <p class="text-sm text-gray-600">{{ $sc['desc'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- PRICING --}}
@if($plan)
<section id="pricing" class="py-16 md:py-24 bg-white">
    <div class="max-w-4xl mx-auto px-4">
        <div class="text-center mb-10">
            <p class="text-sm font-bold uppercase mb-2" style="color: {{ $module['gradient_from'] }};">Preço Simples</p>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900">Sem surpresas, sem letras pequenas</h2>
        </div>

        <div class="bg-white rounded-3xl shadow-2xl border-2 overflow-hidden" style="border-color: {{ $module['gradient_from'] }};">
            <div class="p-8 md:p-10 text-white" style="background: linear-gradient(135deg, {{ $module['gradient_from'] }}, {{ $module['gradient_to'] }});">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h3 class="text-2xl font-bold mb-1">{{ $plan->name }}</h3>
                        <p class="opacity-90 text-sm">{{ $plan->description }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-4xl md:text-5xl font-extrabold">{{ number_format($plan->price_monthly, 0, ',', '.') }} <span class="text-lg opacity-80">Kz/mês</span></div>
                        @if($plan->price_yearly > 0)
                            <p class="text-sm opacity-90 mt-1">ou {{ number_format($plan->price_yearly, 0, ',', '.') }} Kz/ano <span class="bg-white/20 px-2 py-0.5 rounded-full text-xs font-bold ml-1">-17%</span></p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="p-8 md:p-10">
                <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-check-double" style="color: {{ $module['gradient_from'] }};"></i>O que está incluído
                </h4>
                <ul class="grid md:grid-cols-2 gap-3 mb-8">
                    @foreach(($plan->features ?? []) as $feat)
                        <li class="flex items-start gap-2 text-sm text-gray-700">
                            <i class="fas fa-check-circle mt-0.5" style="color: {{ $module['gradient_from'] }};"></i>
                            <span>{{ $feat }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('register') }}" class="flex-1 text-center text-white px-6 py-3 rounded-xl font-bold shadow-lg" style="background: linear-gradient(135deg, {{ $module['gradient_from'] }}, {{ $module['gradient_to'] }});">
                        <i class="fas fa-rocket mr-2"></i>Começar {{ $plan->trial_days }} dias grátis
                    </a>
                    @if($module['whatsapp'])
                    <a href="https://wa.me/{{ $module['whatsapp'] }}" target="_blank" class="flex-1 text-center border-2 border-gray-200 text-gray-700 px-6 py-3 rounded-xl font-bold hover:bg-gray-50">
                        <i class="fab fa-whatsapp mr-2 text-green-600"></i>Falar com Comercial
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <p class="text-center text-sm text-gray-500 mt-6">
            <i class="fas fa-shield-halved mr-1"></i>Sem fidelização · Cancele quando quiser · Suporte em Português
        </p>
    </div>
</section>
@endif

{{-- PERGUNTAS FREQUENTES — as mesmas do FAQPage desta página. --}}
@if(count($perguntas))
<section id="perguntas" class="py-16 md:py-20 bg-slate-50">
    <div class="max-w-3xl mx-auto px-4">
        <div class="text-center mb-10">
            <p class="text-sm font-bold uppercase mb-2" style="color: {{ $module['gradient_from'] }};">Perguntas frequentes</p>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900">O que nos perguntam sobre {{ \App\Support\DadosEstruturados::semEmoji($module['name']) }}</h2>
        </div>
        <div class="space-y-3">
            @foreach($perguntas as [$pergunta, $resposta])
                <details class="group rounded-2xl border border-gray-200 bg-white open:shadow-lg transition-all duration-300" @if($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-4 font-semibold text-gray-900">
                        <span>{{ $pergunta }}</span>
                        <i class="fas fa-chevron-down transition-transform duration-300 group-open:rotate-180" style="color: {{ $module['gradient_from'] }};" aria-hidden="true"></i>
                    </summary>
                    <p class="px-6 pb-5 text-gray-600 leading-relaxed">{{ $resposta }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- WHY US --}}
<section class="py-16 bg-gray-900 text-white">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold mb-2">Porquê escolher SOSERP?</h2>
            <p class="text-gray-400">Construído em Angola, para Angola</p>
        </div>
        <div class="grid md:grid-cols-4 gap-6 text-center">
            <div>
                <div class="w-14 h-14 mx-auto bg-blue-600/20 rounded-xl flex items-center justify-center mb-3">
                    <i class="fas fa-flag text-2xl text-blue-400"></i>
                </div>
                <h4 class="font-bold mb-1">100% Angolano</h4>
                <p class="text-sm text-gray-400">Servidores e suporte locais. Conformidade AGT total.</p>
            </div>
            <div>
                <div class="w-14 h-14 mx-auto bg-emerald-600/20 rounded-xl flex items-center justify-center mb-3">
                    <i class="fas fa-wifi-slash text-2xl text-emerald-400"></i>
                </div>
                <h4 class="font-bold mb-1">Funciona Offline</h4>
                <p class="text-sm text-gray-400">PWA com sincronização automática quando volta a internet.</p>
            </div>
            <div>
                <div class="w-14 h-14 mx-auto bg-purple-600/20 rounded-xl flex items-center justify-center mb-3">
                    <i class="fas fa-headset text-2xl text-purple-400"></i>
                </div>
                <h4 class="font-bold mb-1">Suporte Real</h4>
                <p class="text-sm text-gray-400">WhatsApp, telefone e email em horário comercial.</p>
            </div>
            <div>
                <div class="w-14 h-14 mx-auto bg-orange-600/20 rounded-xl flex items-center justify-center mb-3">
                    <i class="fas fa-lock text-2xl text-orange-400"></i>
                </div>
                <h4 class="font-bold mb-1">Dados Seguros</h4>
                <p class="text-sm text-gray-400">Backup diário, encriptação SSL e arquitetura multi-tenant.</p>
            </div>
        </div>
    </div>
</section>

</x-modules-layout>
