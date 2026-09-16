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
<body class="flex min-h-screen flex-col bg-gradient-to-br from-slate-50 via-violet-50/40 to-emerald-50/30">
    {{-- O PORTAL DO REVENDEDOR (programa de revendedores, 16/09/2026): a
         mesma barra do portal do cliente, com as ligações dele. --}}
    @php
        $revendedorDoPortal = auth('revendedor')->user();
        $ligacoesDoRevendedor = collect([
            ['route' => 'revendedor.painel', 'icon' => 'fa-gauge-high', 'label' => __('Painel'), 'activa' => ['revendedor.painel']],
            ['route' => 'revendedor.empresas', 'icon' => 'fa-building', 'label' => __('Empresas'), 'activa' => ['revendedor.empresas', 'revendedor.empresas.*']],
            ['route' => 'revendedor.comissoes', 'icon' => 'fa-sack-dollar', 'label' => __('Comissões'), 'activa' => ['revendedor.comissoes']],
        ])->map(fn ($l) => [
            'url' => route($l['route']),
            'icone' => $l['icon'],
            'rotulo' => $l['label'],
            'activo' => request()->routeIs(...$l['activa']),
        ])->all();
    @endphp

    <x-ecra-react nome="cliente/topo" :esqueleto="false" class="relative z-10 block min-h-[4rem] bg-white shadow-md" :props="[
        'inicio' => route('revendedor.painel'),
        'logo' => app_logo(),
        'nome' => app_name(),
        'titulo' => __('Portal do Revendedor'),
        'quem' => $revendedorDoPortal?->nomeVisivel(),
        'ligacoes' => $ligacoesDoRevendedor,
        'perfil' => route('revendedor.perfil'),
        'sair' => route('revendedor.sair'),
        'csrf' => csrf_token(),
    ]" />

    <main class="flex-1 py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @yield('content')
        </div>
    </main>

    <footer class="mt-auto border-t border-gray-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <p class="text-center text-sm text-gray-500">&copy; {{ date('Y') }} {{ config('app.name') }} · {{ __('Programa de Revendedores') }}</p>
        </div>
    </footer>

    <x-ecra-react nome="casca/sistema" :esqueleto="false" :props="[
        'login' => route('revendedor.login'),
        'manterViva' => null,
        'recados' => \App\Support\RecadosDaSessao::lista(),
    ]" />

    @include('partials.react-pacote')
</body>
</html>
