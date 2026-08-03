<x-modules-layout title="Todos os Módulos" description="Conhece todos os módulos do SOSERP — soluções dedicadas para cada setor.">

{{-- Hero --}}
<section class="bg-gradient-to-br from-blue-600 to-purple-700 py-16 md:py-24 text-white">
    <div class="max-w-5xl mx-auto px-4 text-center">
        <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur px-3 py-1.5 rounded-full text-xs font-bold mb-4">
            <i class="fas fa-cubes"></i> MÓDULOS DISPONÍVEIS
        </div>
        <h1 class="text-4xl md:text-6xl font-extrabold mb-4">Uma solução para cada negócio</h1>
        <p class="text-xl opacity-90 mb-2">Pacotes especializados por setor — paga só pelo que usas</p>
        <p class="text-sm opacity-75">Faturação certificada AGT incluída em todos os pacotes</p>
    </div>
</section>

{{-- Grid de módulos --}}
<section class="py-16 md:py-20">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($modules as $m)
                <a href="/modulos/{{ $m['slug'] }}" class="block feature-card bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
                    {{-- Header com gradiente --}}
                    <div class="p-6 text-white relative overflow-hidden" style="background: linear-gradient(135deg, {{ $m['gradient_from'] }}, {{ $m['gradient_to'] }});">
                        <div class="absolute -top-8 -right-8 w-32 h-32 bg-white/10 rounded-full"></div>
                        <i class="fas {{ $m['icon'] }} text-4xl mb-3 relative"></i>
                        <h3 class="text-xl font-bold relative">{{ $m['name'] }}</h3>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-gray-600 mb-4 min-h-[60px]">{{ $m['tagline'] }}</p>
                        @if($m['plan'])
                            <div class="flex items-end justify-between mb-4">
                                <div>
                                    <p class="text-xs text-gray-500 font-bold uppercase">Desde</p>
                                    <p class="text-3xl font-extrabold" style="color: {{ $m['gradient_from'] }};">{{ number_format($m['plan']->price_monthly, 0, ',', '.') }}<span class="text-sm text-gray-500 font-normal"> Kz/mês</span></p>
                                </div>
                                <span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-1 rounded-full font-bold">{{ $m['plan']->trial_days }}d grátis</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between text-sm font-bold pt-3 border-t border-gray-100" style="color: {{ $m['gradient_from'] }};">
                            <span>Ver detalhes</span>
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Comparativo --}}
<section class="py-16 bg-white">
    <div class="max-w-5xl mx-auto px-4">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Comparativo rápido</h2>
            <p class="text-gray-600">Escolhe o pacote certo para o teu negócio</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border border-gray-200 rounded-2xl overflow-hidden text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-bold text-gray-700">Pacote</th>
                        <th class="px-4 py-3 text-center font-bold text-gray-700">Ideal para</th>
                        <th class="px-4 py-3 text-center font-bold text-gray-700">Utilizadores</th>
                        <th class="px-4 py-3 text-right font-bold text-gray-700">Preço/mês</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($modules as $m)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="/modulos/{{ $m['slug'] }}" class="font-bold hover:underline" style="color: {{ $m['gradient_from'] }};">
                                    <i class="fas {{ $m['icon'] }} mr-1"></i>{{ $m['name'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600 text-xs">
                                @if($m['slug'] === 'vendas') Lojas, restaurantes, comércio
                                @elseif($m['slug'] === 'rh') Empresas com colaboradores
                                @elseif($m['slug'] === 'hotel') Hotéis, pousadas, residenciais
                                @elseif($m['slug'] === 'salao') Salões, barbearias, spas
                                @elseif($m['slug'] === 'oficina') Oficinas auto e mecânicas
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $m['plan']?->max_users ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-bold">{{ $m['plan'] ? number_format($m['plan']->price_monthly, 0, ',', '.') . ' Kz' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-center text-sm text-gray-500 mt-4">
            <i class="fas fa-circle-info mr-1"></i>Todos os preços incluem o módulo de Faturação certificada AGT
        </p>
    </div>
</section>

</x-modules-layout>
