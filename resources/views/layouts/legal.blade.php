<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') — {{ function_exists('app_name') ? app_name() : 'SOS ERP' }}</title>
    <meta name="description" content="@yield('descricao')">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="{{ \App\Support\DadosEstruturados::raiz() . '/' . ltrim(request()->path(), '/') }}">
    <link rel="icon" href="/brand/favicon-32x32.png">
    <link rel="stylesheet" href="/vendor/css/fontawesome.min.css">
    <style>
        /* Tipografia de documento: linha larga e respiração entre secções —
           estes textos são para ler, não para varrer. A identidade é a do
           site: azuis profundos da aplicação, laranja da raposa. */
        .doc h2 {
            font-size: 1.2rem; font-weight: 800; color: #0f172a;
            margin: 2.5rem 0 .85rem; padding-left: .9rem;
            border-left: 4px solid #ea580c; scroll-margin-top: 6rem;
        }
        .doc h2:first-child { margin-top: 0; }
        .doc h3 { font-size: 1rem; font-weight: 700; color: #1e293b; margin: 1.5rem 0 .5rem; scroll-margin-top: 6rem; }
        .doc p, .doc li { color: #334155; line-height: 1.8; }
        .doc p { margin-bottom: .95rem; }
        .doc ul { list-style: disc; padding-left: 1.4rem; margin-bottom: .95rem; }
        .doc ul li { margin-bottom: .45rem; }
        .doc strong { color: #0f172a; }
        .doc a { color: #1d4ed8; text-decoration: underline; text-underline-offset: 2px; }
        .doc a:hover { color: #ea580c; }

        /* O inventário dos dados e as tabelas de cookies: cartões e tabelas em
           CSS próprio (não dependem das classes compiladas do site público). */
        .doc .fichas { display: grid; gap: 1rem; margin: 1.25rem 0 1.5rem; }
        @media (min-width: 900px) { .doc .fichas { grid-template-columns: 1fr 1fr; } }
        .doc .ficha { border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.1rem 1.2rem; background: #fff;
                      transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
        .doc .ficha:hover { transform: translateY(-2px); box-shadow: 0 14px 30px -18px rgba(15,23,42,.45); border-color: #fdba74; }
        .doc .ficha h3 { margin: 0 0 .5rem; display: flex; align-items: center; gap: .6rem; }
        .doc .ficha h3 i { display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: .6rem;
                           background: #fff7ed; color: #ea580c; font-size: .85rem; flex: none; }
        .doc .ficha ul { margin-bottom: .6rem; }
        .doc .ficha li, .doc .ficha p { font-size: .9rem; line-height: 1.6; }
        .doc .ficha dl { display: grid; grid-template-columns: auto 1fr; gap: .25rem .75rem; margin: .5rem 0 0; font-size: .82rem; }
        .doc .ficha dt { font-weight: 700; color: #64748b; }
        .doc .ficha dd { margin: 0; color: #334155; }
        .doc .tabela { overflow-x: auto; margin: 1rem 0 1.5rem; border: 1px solid #e2e8f0; border-radius: .9rem; }
        .doc table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        .doc th { background: #f8fafc; text-align: left; font-weight: 700; color: #475569; padding: .6rem .8rem; border-bottom: 1px solid #e2e8f0; }
        .doc td { padding: .6rem .8rem; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: top; }
        .doc tr:last-child td { border-bottom: 0; }
        .doc .destaque { border-radius: 1rem; background: #eff6ff; border: 1px solid #bfdbfe; padding: 1rem 1.2rem; margin: 1rem 0 1.5rem; }
        .doc .destaque p:last-child { margin-bottom: 0; }
        .doc .botao-doc { display: inline-flex; align-items: center; gap: .5rem; border-radius: .8rem; padding: .6rem 1rem;
                          background: #1e3a8a; color: #fff !important; text-decoration: none !important; font-weight: 700; font-size: .9rem;
                          transition: background .2s, transform .15s; }
        .doc .botao-doc:hover { background: #ea580c; transform: translateY(-1px); }
        @media (prefers-reduced-motion: reduce) { .doc .ficha, .doc .botao-doc { transition: none; } }

        .indice a { display: block; padding: .45rem .75rem; border-radius: .6rem;
                    font-size: .82rem; font-weight: 600; color: #475569; line-height: 1.35; }
        .indice a:hover { background: #fff7ed; color: #c2410c; }
        .indice a.activo { background: #ffedd5; color: #9a3412; }
    </style>
    {{-- No fim do <head>: era aqui que o Tailwind em runtime injectava o CSS, e a cascata depende disso. --}}
    @include('partials.css-publico')
</head>
<body class="bg-slate-100">

    {{-- Cabeçalho de marca — a mesma identidade da aplicação. --}}
    <header class="bg-gradient-to-r from-blue-950 via-blue-900 to-slate-900 text-white sticky top-0 z-40 shadow-lg">
        <div class="max-w-6xl mx-auto px-5 py-3.5 flex items-center justify-between gap-4">
            <a href="/" class="flex items-center gap-3 min-w-0">
                <span class="h-11 w-11 shrink-0 rounded-xl bg-white p-1 shadow-md grid place-items-center">
                    <img src="{{ function_exists('app_logo') && app_logo() ? app_logo() : '/brand/soserp-icone-192.png' }}"
                         alt="{{ function_exists('app_name') ? app_name() : 'SOS ERP' }}"
                         class="h-9 w-9 object-contain"
                         onerror="this.closest('span').style.display='none'">
                </span>
                <span class="min-w-0">
                    <span class="block text-lg font-black leading-tight truncate">{{ function_exists('app_name') ? app_name() : 'SOS ERP' }}</span>
                    <span class="block text-[11px] font-semibold uppercase tracking-widest text-orange-300">Software de gestão 100% angolano</span>
                </span>
            </a>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('login') }}" class="hidden sm:inline-block rounded-xl border border-white/25 px-4 py-2 text-sm font-bold text-blue-100 hover:bg-white/10 transition">Entrar</a>
                <a href="/" class="rounded-xl bg-orange-600 hover:bg-orange-500 px-4 py-2 text-sm font-bold shadow-lg transition">
                    <i class="fas fa-arrow-left mr-1.5"></i>Voltar ao site
                </a>
            </div>
        </div>
    </header>

    {{-- Faixa do título --}}
    <div class="bg-gradient-to-r from-blue-900 to-blue-950 text-white border-t border-white/10">
        <div class="max-w-6xl mx-auto px-5 py-10">
            <p class="text-xs font-black uppercase tracking-widest text-orange-300">
                <i class="fas fa-scale-balanced mr-1.5"></i>Documento legal
            </p>
            <h1 class="mt-2 text-3xl md:text-4xl font-black">@yield('titulo')</h1>
            <p class="mt-2 text-sm text-blue-200">
                Última actualização: @yield('atualizado', $atualizado ?? '24 de Agosto de 2026')
            </p>
        </div>
    </div>

    <main class="max-w-6xl mx-auto px-5 py-8 md:py-10">
        <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)] items-start">

            {{-- O índice constrói-se sozinho a partir dos títulos do texto:
                 o documento legal não se toca para o desenho mudar. --}}
            <aside class="hidden lg:block sticky top-24 rounded-2xl bg-white shadow-lg p-4">
                <p class="px-2 pb-2 text-[11px] font-black uppercase tracking-widest text-slate-400">Nesta página</p>
                <nav id="indice" class="indice space-y-0.5 max-h-[70vh] overflow-y-auto"></nav>
                <div class="mt-4 border-t border-slate-100 pt-3 px-2">
                    <a href="{{ route('legal.termos') }}" class="block text-xs font-bold text-slate-500 hover:text-orange-700 py-1">Termos de Utilização</a>
                    <a href="{{ route('legal.privacidade') }}" class="block text-xs font-bold text-slate-500 hover:text-orange-700 py-1">Política de Privacidade</a>
                    <a href="{{ route('legal.cookies') }}" class="block text-xs font-bold text-slate-500 hover:text-orange-700 py-1">Política de Cookies</a>
                    <a href="#" data-abrir-consentimento class="block text-xs font-bold text-slate-500 hover:text-orange-700 py-1"><i class="fas fa-sliders mr-1"></i>Preferências de cookies</a>
                </div>
            </aside>

            <article class="bg-white rounded-2xl shadow-lg p-6 md:p-12">
                <div class="doc" id="documento">
                    @yield('conteudo')
                </div>

                <div class="mt-12 rounded-2xl bg-slate-50 border border-slate-200 p-5 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-600">
                        <i class="fas fa-circle-question text-orange-500 mr-1.5"></i>
                        Dúvidas sobre este documento? Fale connosco.
                    </p>
                    <a href="mailto:{{ config('privacidade.responsavel.email') }}" class="rounded-xl bg-blue-900 hover:bg-blue-800 px-4 py-2 text-sm font-bold text-white transition">
                        <i class="fas fa-envelope mr-1.5"></i>{{ config('privacidade.responsavel.email') }}
                    </a>
                </div>
            </article>
        </div>
    </main>

    <footer class="bg-blue-950 text-blue-200 mt-10">
        <div class="max-w-6xl mx-auto px-5 py-10 grid gap-8 md:grid-cols-3">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="h-9 w-9 rounded-lg bg-white p-0.5 grid place-items-center">
                        <img src="/brand/soserp-icone-192.png" alt="" class="h-8 w-8 object-contain" onerror="this.closest('span').style.display='none'">
                    </span>
                    <span class="text-lg font-black text-white">{{ function_exists('app_name') ? app_name() : 'SOS ERP' }}</span>
                </div>
                <p class="mt-3 text-sm leading-relaxed text-blue-300">
                    Facturação certificada AGT, POS offline, RH, restaurante, hotel, salão e oficina — feito para Angola.
                </p>
            </div>
            <div class="text-sm">
                <p class="font-black text-white mb-2 uppercase text-xs tracking-widest">Legal</p>
                <a href="{{ route('legal.termos') }}" class="block py-1 hover:text-orange-300">Termos de Utilização</a>
                <a href="{{ route('legal.privacidade') }}" class="block py-1 hover:text-orange-300">Política de Privacidade</a>
                <a href="{{ route('legal.cookies') }}" class="block py-1 hover:text-orange-300">Política de Cookies</a>
                <a href="#" data-abrir-consentimento class="block py-1 hover:text-orange-300"><i class="fas fa-sliders mr-1"></i>Preferências de cookies</a>
            </div>
            <div class="text-sm">
                <p class="font-black text-white mb-2 uppercase text-xs tracking-widest">SOS ERP</p>
                <a href="/" class="block py-1 hover:text-orange-300">Início</a>
                <a href="{{ route('login') }}" class="block py-1 hover:text-orange-300">Entrar</a>
            </div>
        </div>
        <div class="border-t border-white/10">
            <p class="max-w-6xl mx-auto px-5 py-4 text-xs text-blue-400">
                © {{ date('Y') }} {{ function_exists('app_name') ? app_name() : 'SOS ERP' }} — Todos os direitos reservados.
            </p>
        </div>
    </footer>

    <script>
        // O índice lateral nasce dos h2 do documento — e realça a secção
        // visível enquanto se lê.
        (function () {
            const doc = document.getElementById('documento');
            const nav = document.getElementById('indice');
            if (!doc || !nav) return;

            const titulos = [...doc.querySelectorAll('h2')];

            titulos.forEach((h, i) => {
                if (!h.id) h.id = 'sec-' + (i + 1);
                const a = document.createElement('a');
                a.href = '#' + h.id;
                a.textContent = h.textContent;
                nav.appendChild(a);
            });

            if (!titulos.length) { nav.closest('aside')?.remove(); return; }

            const ligacoes = [...nav.querySelectorAll('a')];
            const observador = new IntersectionObserver((entradas) => {
                entradas.forEach((e) => {
                    if (!e.isIntersecting) return;
                    ligacoes.forEach((l) => l.classList.toggle('activo', l.getAttribute('href') === '#' + e.target.id));
                });
            }, { rootMargin: '-20% 0px -70% 0px' });

            titulos.forEach((h) => observador.observe(h));
        })();
    </script>
    @include('partials.consentimento')
</body>
</html>
