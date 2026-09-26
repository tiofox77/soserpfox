{{-- Layout partilhado para páginas de módulos/pacotes --}}
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Módulo' }} — SOSERP</title>
    <meta name="description" content="{{ $description ?? '' }}">
    @php
        $pageUrl = url()->current();
        $pageTitle = ($title ?? 'Módulo') . ' — SOSERP';
        $pageDescription = $description ?? 'SOSERP — software de gestão empresarial para Angola.';
        $shareImage = asset('brand/soserp-og-1200x630.png');
    @endphp
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="{{ $pageUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $pageUrl }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:image" content="{{ $shareImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $shareImage }}">
    @include('partials.favicon')
    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-text { background: linear-gradient(135deg, var(--from), var(--to)); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .feature-card { transition: all 0.3s; }
        .feature-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -10px rgba(0,0,0,0.15); }
        .screenshot { background: linear-gradient(135deg, var(--from), var(--to)); border-radius: 1rem; padding: 0.5rem; box-shadow: 0 30px 60px -15px rgba(0,0,0,0.3); }
        .screenshot-inner { background: white; border-radius: 0.75rem; padding: 1.5rem; aspect-ratio: 16/10; display: flex; flex-direction: column; }
    </style>
    {{-- No fim do <head>: era aqui que o Tailwind em runtime injectava o CSS, e a cascata depende disso. --}}
    @include('partials.css-publico')
</head>
<body class="bg-slate-50">

{{-- Navbar --}}
<nav class="bg-white shadow-sm sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <a href="/" class="flex items-center gap-2 font-bold text-xl text-gray-900">
            <i class="fas fa-cubes text-blue-600"></i> SOSERP
        </a>
        <div class="hidden md:flex items-center gap-6 text-sm font-medium text-gray-600">
            <a href="/modulos/vendas" class="hover:text-blue-600">Vendas</a>
            <a href="/modulos/rh" class="hover:text-blue-600">RH</a>
            <a href="/modulos/hotel" class="hover:text-blue-600">Hotel</a>
            <a href="/modulos/salao" class="hover:text-blue-600">Salão</a>
            <a href="/modulos/oficina" class="hover:text-blue-600">Oficina</a>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-700 px-3 py-2">Entrar</a>
            <a href="{{ route('register') }}" class="text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">Experimentar Grátis</a>
        </div>
    </div>
</nav>

{{ $slot }}

{{-- CTA Final --}}
<section class="py-16 bg-gradient-to-br" style="background-image: linear-gradient(135deg, var(--from, #2563eb), var(--to, #7c3aed));">
    <div class="max-w-4xl mx-auto px-4 text-center text-white">
        <h2 class="text-3xl md:text-4xl font-bold mb-4">Pronto para começar?</h2>
        <p class="text-lg opacity-90 mb-8">{{ $ctaText ?? ('Experimenta grátis: ' . \App\Support\DiasDeTeste::frase() . '. Sem cartão de crédito.') }}</p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('register') }}" class="bg-white text-gray-900 px-8 py-3 rounded-xl font-bold hover:bg-gray-100 shadow-lg">
                <i class="fas fa-rocket mr-2"></i>Começar Grátis
            </a>
            @if(!empty($whatsapp))
            <a href="https://wa.me/{{ $whatsapp }}" target="_blank" class="bg-white/20 border-2 border-white text-white px-8 py-3 rounded-xl font-bold hover:bg-white/30">
                <i class="fab fa-whatsapp mr-2"></i>Falar com Comercial
            </a>
            @endif
        </div>
    </div>
</section>

{{-- Footer --}}
<footer class="bg-gray-900 text-gray-400 py-10">
    <div class="max-w-7xl mx-auto px-4 grid md:grid-cols-4 gap-8">
        <div>
            <h3 class="text-white font-bold mb-3"><i class="fas fa-cubes text-blue-500 mr-2"></i>SOSERP</h3>
            <p class="text-sm">Sistema de gestão empresarial 100% angolano, com integração AGT e suporte local.</p>
        </div>
        <div>
            <h4 class="text-white font-bold mb-3 text-sm">Módulos</h4>
            <ul class="space-y-1 text-sm">
                <li><a href="/modulos/vendas" class="hover:text-white">📊 Vendas / Faturação</a></li>
                <li><a href="/modulos/rh" class="hover:text-white">👥 Recursos Humanos</a></li>
                <li><a href="/modulos/hotel" class="hover:text-white">🏨 Gestão de Hotel</a></li>
                <li><a href="/modulos/salao" class="hover:text-white">💇 Salão de Beleza</a></li>
                <li><a href="/modulos/oficina" class="hover:text-white">🔧 Oficina Auto</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-white font-bold mb-3 text-sm">Empresa</h4>
            <ul class="space-y-1 text-sm">
                <li><a href="/" class="hover:text-white">Sobre</a></li>
                <li><a href="/#contact" class="hover:text-white">Contacto</a></li>
                <li><a href="{{ route('login') }}" class="hover:text-white">Entrar</a></li>
                <li><a href="{{ route('revendedores.pagina') }}" class="hover:text-white"><i class="fas fa-handshake mr-1 text-emerald-400"></i>Seja revendedor</a></li>
                <li><a href="{{ route('revendedor.login') }}" class="hover:text-white">Portal do revendedor</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-white font-bold mb-3 text-sm">Conformidade</h4>
            <ul class="space-y-1 text-sm">
                <li>✓ AGT — SAFT, hash, séries fiscais</li>
                <li>✓ Faturação certificada</li>
                <li>✓ Servidores em Angola</li>
            </ul>
        </div>
    </div>
    <div class="text-center text-xs text-gray-500 mt-8 pt-6 border-t border-gray-800">
        © {{ date('Y') }} SOSERP. Todos os direitos reservados.
    </div>
</footer>

</body>
</html>
