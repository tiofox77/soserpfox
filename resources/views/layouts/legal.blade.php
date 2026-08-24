<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') — SOSERP</title>
    <meta name="description" content="@yield('descricao')">
    <link rel="icon" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Tipografia de documento: linha larga e respiração entre secções —
           estes textos são para ler, não para varrer. */
        .doc h2 { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 2.25rem 0 .75rem; }
        .doc h3 { font-size: 1rem; font-weight: 600; color: #1e293b; margin: 1.5rem 0 .5rem; }
        .doc p, .doc li { color: #334155; line-height: 1.75; }
        .doc p { margin-bottom: .9rem; }
        .doc ul { list-style: disc; padding-left: 1.4rem; margin-bottom: .9rem; }
        .doc ul li { margin-bottom: .4rem; }
        .doc strong { color: #0f172a; }
        .doc a { color: #2563eb; text-decoration: underline; }
    </style>
</head>
<body class="bg-gray-50">
    {{-- Cabeçalho --}}
    <header class="bg-gradient-to-r from-blue-700 to-indigo-700 text-white">
        <div class="max-w-4xl mx-auto px-6 py-5 flex items-center justify-between">
            <a href="/" class="flex items-center gap-3">
                <img src="/brand/soserp-logo.png" alt="SOSERP" class="h-9 w-auto"
                     onerror="this.style.display='none'">
                <span class="text-xl font-bold">SOSERP</span>
            </a>
            <a href="/" class="text-sm text-blue-100 hover:text-white">
                <i class="fas fa-arrow-left mr-1"></i>Voltar ao site
            </a>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-10">
        <div class="bg-white rounded-2xl shadow-lg p-8 md:p-12">
            <h1 class="text-3xl font-bold text-gray-900">@yield('titulo')</h1>
            <p class="text-sm text-gray-500 mt-2">
                Última actualização: {{ $atualizado ?? '24 de Agosto de 2026' }}
            </p>

            <div class="doc mt-8">
                @yield('conteudo')
            </div>
        </div>

        <div class="text-center text-sm text-gray-500 mt-8">
            <a href="{{ route('legal.termos') }}" class="hover:text-gray-700">Termos de Utilização</a>
            <span class="mx-2">·</span>
            <a href="{{ route('legal.privacidade') }}" class="hover:text-gray-700">Política de Privacidade</a>
            <span class="mx-2">·</span>
            <a href="/" class="hover:text-gray-700">Início</a>
        </div>
    </main>

    <footer class="bg-gray-900 text-gray-400 py-8 mt-10">
        <div class="max-w-4xl mx-auto px-6 text-center text-sm">
            <p>&copy; {{ date('Y') }} SOSERP. Todos os direitos reservados.</p>
            <p class="mt-2">
                Desenvolvido por
                <a href="https://softecangola.net" target="_blank" rel="noopener"
                   class="text-blue-400 hover:text-blue-300 font-semibold">Softec Angola</a>
            </p>
        </div>
    </footer>
</body>
</html>
