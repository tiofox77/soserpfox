<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Portal do Revendedor')) - {{ app_name() ?? config('app.name', 'SOS ERP') }}</title>
    <meta name="robots" content="noindex, nofollow">

    @include('partials.favicon')

    <script src="/vendor/js/tailwind.js"></script>
    <link rel="stylesheet" href="/vendor/css/fontawesome.min.css">

    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-6px); }
        }
        .icon-float { animation: float 3s ease-in-out infinite; }
        .card-hover { transition: transform .3s cubic-bezier(.4, 0, .2, 1), box-shadow .3s cubic-bezier(.4, 0, .2, 1); }
        .card-hover:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(0, 0, 0, .1); }
        @media (prefers-reduced-motion: reduce) {
            .icon-float { animation: none; }
            .card-hover:hover { transform: none; }
        }
    </style>

    @include('partials.react-animacoes')
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-violet-50/40 to-emerald-50/30">
    {{-- O PORTAL DO REVENDEDOR: a barra lateral e o cabeçalho (16/09/2026), em
         React (revenda/moldura). Os números que pedem acção vêm daqui. --}}
    @php
        $revendedorDoPortal = auth('revendedor')->user();
        $contadoresDoPortal = $revendedorDoPortal ? \App\Services\Revenda\EmpresasDoRevendedor::contadores($revendedorDoPortal) : ['empresas' => 0, 'pagamentos' => 0];
        $comissoesDoPortal = $revendedorDoPortal ? \App\Services\Revenda\ComissoesDoRevendedor::totais($revendedorDoPortal->id) : ['por_pagar_n' => 0];
        $ligacaoDoPortal = fn (string $rota, string $icone, string $rotulo, array $activa, ?int $contador = null, bool $alerta = false) => [
            'url' => route($rota),
            'icone' => $icone,
            'rotulo' => $rotulo,
            'activo' => request()->routeIs(...$activa),
            'contador' => $contador,
            'alerta' => $alerta,
        ];
        $seccoesDoPortal = [
            ['titulo' => __('Principal'), 'ligacoes' => [
                $ligacaoDoPortal('revendedor.painel', 'fa-gauge-high', __('Painel'), ['revendedor.painel']),
                $ligacaoDoPortal('revendedor.empresas', 'fa-building', __('Empresas'), ['revendedor.empresas', 'revendedor.empresas.ver'], $contadoresDoPortal['empresas'] ?: null),
                $ligacaoDoPortal('revendedor.empresas.nova', 'fa-building-circle-arrow-right', __('Nova empresa'), ['revendedor.empresas.nova']),
            ]],
            ['titulo' => __('Financeiro'), 'ligacoes' => [
                $ligacaoDoPortal('revendedor.pagamentos', 'fa-file-invoice-dollar', __('Pagamentos'), ['revendedor.pagamentos'], $contadoresDoPortal['pagamentos'] ?: null, $contadoresDoPortal['pagamentos'] > 0),
                $ligacaoDoPortal('revendedor.comissoes', 'fa-sack-dollar', __('Comissões'), ['revendedor.comissoes'], $comissoesDoPortal['por_pagar_n'] ?: null),
            ]],
            ['titulo' => __('Conta'), 'ligacoes' => [
                $ligacaoDoPortal('revendedor.perfil', 'fa-user-gear', __('O meu perfil'), ['revendedor.perfil']),
            ]],
        ];
    @endphp

    <x-ecra-react nome="revenda/moldura" :esqueleto="false" :props="[
        'logo' => app_logo(),
        'nomeApp' => app_name(),
        'titulo' => $titulo ?? __('Portal do Revendedor'),
        'revendedor' => [
            'nome' => $revendedorDoPortal?->name ?? '',
            'empresa' => $revendedorDoPortal?->company_name,
            'codigo' => $revendedorDoPortal?->code,
            'link' => $revendedorDoPortal?->link(),
        ],
        'seccoes' => $seccoesDoPortal,
        'pagamentos' => (int) $contadoresDoPortal['pagamentos'],
        'urls' => [
            'painel' => route('revendedor.painel'),
            'nova' => route('revendedor.empresas.nova'),
            'pagamentos' => route('revendedor.pagamentos'),
            'perfil' => route('revendedor.perfil'),
            'sair' => route('revendedor.sair'),
        ],
        'csrf' => csrf_token(),
    ]" />

    <div class="flex min-h-screen flex-col pt-16 lg:pl-72">
        <main class="flex-1">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                @yield('content')
            </div>
        </main>

        <footer class="border-t border-slate-200 bg-white/70">
            <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                <p class="text-center text-sm text-slate-500">&copy; {{ date('Y') }} {{ config('app.name') }} · {{ __('Programa de Revendedores') }}</p>
            </div>
        </footer>
    </div>

    <x-ecra-react nome="casca/sistema" :esqueleto="false" :props="[
        'login' => route('revendedor.login'),
        'manterViva' => null,
        'recados' => \App\Support\RecadosDaSessao::lista(),
    ]" />

    @include('partials.react-pacote')
</body>
</html>
