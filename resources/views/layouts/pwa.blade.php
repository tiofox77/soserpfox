<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'SOS ERP — PWA Faturação' }}</title>

    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    @include('partials.favicon')

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    {{-- Dexie (IndexedDB wrapper) --}}
    <script src="https://unpkg.com/dexie@4.0.10/dist/dexie.min.js"></script>

    {{-- Alpine.js --}}
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .pwa-shell { padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom); }
        /* O x-cloak esconde o conteúdo até o Alpine arrancar. Se ele NÃO
           arrancar, esconde-o para sempre — e o ecrã fica cinzento, sem uma
           palavra. O `html.alpine-falhou` desfaz isso: ver o script no fim do
           corpo, que é quem a põe. */
        [x-cloak] { display: none !important; }
        html.alpine-falhou [x-cloak] { display: revert !important; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen pwa-shell">

{{-- Ecrã de carregamento.

     Tudo o que interessa nas páginas do PWA está atrás de um x-cloak, que é
     `display: none` até o Alpine arrancar. Enquanto ele não chega — e vem da
     internet — o ecrã fica CINZENTO e vazio: a aplicação parece encravada, e
     quem está ao balcão não sabe se há-de esperar, tocar outra vez, ou fechar.

     Este ecrã aparece de imediato e sai quando o Alpine arranca. Escrito com
     estilos e JavaScript à mão, sem Tailwind nem Alpine, de propósito: é para
     funcionar exactamente quando eles não funcionam. --}}
<div id="pwa-a-carregar" role="status" aria-live="polite"
     style="position:fixed;inset:0;z-index:9998;background:linear-gradient(160deg,#1e3a8a,#312e81);
            display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px;
            font-family:-apple-system,'Segoe UI',Roboto,sans-serif;color:#fff;text-align:center;padding:24px">
    <div style="width:64px;height:64px;border-radius:20px;background:rgba(255,255,255,.15);
                display:flex;align-items:center;justify-content:center;font-size:30px">⚡</div>

    <div style="width:34px;height:34px;border:3px solid rgba(255,255,255,.25);border-top-color:#fff;
                border-radius:50%;animation:pwa-roda .8s linear infinite"></div>

    <div>
        <p style="margin:0;font-weight:700;font-size:16px">{{ __('A preparar o ponto de venda') }}</p>
        <p id="pwa-a-carregar-nota" style="margin:6px 0 0;font-size:13px;opacity:.75">
            {{ __('Um momento…') }}
        </p>
    </div>
</div>

<style>
    @keyframes pwa-roda { to { transform: rotate(360deg); } }
</style>

<script>
(function () {
    var ecra = document.getElementById('pwa-a-carregar');
    var nota = document.getElementById('pwa-a-carregar-nota');
    if (!ecra) return;

    function sair() {
        ecra.style.transition = 'opacity .25s';
        ecra.style.opacity = '0';
        setTimeout(function () { ecra.remove(); }, 260);
    }

    // O Alpine avisa quando termina de arrancar. É o sinal certo: a partir daí
    // o x-cloak sai e há mesmo alguma coisa por baixo para se ver.
    document.addEventListener('alpine:initialized', sair, { once: true });

    // Já cá estava quando este script correu (página vinda da cache, rápida).
    if (typeof window.Alpine !== 'undefined') sair();

    // Ao fim de quatro segundos, dizer que ainda se está a tentar. Uma roda a
    // girar sem explicação, passado algum tempo, lê-se como bloqueio.
    setTimeout(function () {
        if (nota && document.body.contains(ecra)) {
            nota.textContent = @json(__('A carregar pela primeira vez. Com internet é mais rápido.'));
        }
    }, 4000);

    // Rede de último recurso: se o Alpine nunca arrancar, o aviso que está no
    // fim do corpo assume — e este ecrã tem de sair da frente para ele se ver.
    setTimeout(function () {
        if (typeof window.Alpine === 'undefined') sair();
    }, 8200);
})();
</script>


    {{-- Banner de instalação PWA --}}
    <div id="pwa-install-banner" class="hidden fixed bottom-20 inset-x-3 z-50 bg-gradient-to-r from-indigo-600 to-blue-700 text-white rounded-2xl shadow-2xl p-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-download text-xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-bold text-sm">Instalar aplicação</p>
                <p class="text-xs opacity-90">Acesso rápido + funciona offline</p>
            </div>
            <button id="pwa-install-btn" class="bg-white text-blue-700 px-3 py-2 rounded-lg text-xs font-bold whitespace-nowrap">Instalar</button>
            <button id="pwa-install-dismiss" class="text-white/70 hover:text-white text-lg px-1" title="Mais tarde">&times;</button>
        </div>
    </div>

    {{-- Indicador online/offline + sync queue --}}
    <div id="pwa-status-bar" class="fixed top-0 inset-x-0 z-50 hidden">
        <div id="pwa-status-offline" class="hidden bg-amber-500 text-white text-center py-1.5 text-xs font-semibold shadow-lg">
            <i class="fas fa-wifi-slash mr-1"></i>Sem conexão — A trabalhar offline. <span id="pwa-pending-count" class="ml-2"></span>
        </div>
        <div id="pwa-status-syncing" class="hidden bg-blue-600 text-white text-center py-1.5 text-xs font-semibold shadow-lg">
            <i class="fas fa-sync fa-spin mr-1"></i>A sincronizar com o servidor…
        </div>
        <div id="pwa-status-synced" class="hidden bg-emerald-600 text-white text-center py-1.5 text-xs font-semibold shadow-lg">
            <i class="fas fa-check-circle mr-1"></i>Sincronizado
        </div>
    </div>

    <header class="bg-gradient-to-r from-blue-700 to-blue-800 text-white shadow-lg sticky top-0 z-40">
        <div class="px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('invoicing.offline.index') }}" class="flex items-center gap-2">
                    <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div>
                        <p class="font-bold text-sm leading-tight">PWA Faturação</p>
                        <p class="text-xs opacity-75 leading-tight">
                            {{-- Duas versões, e as duas fazem falta.
                                 A do changelog é a RELEASE — o que mudou e está escrito
                                 em /changelog. A build é o que este aparelho está mesmo a
                                 correr, e muda a cada deploy que toque no PWA. Só a
                                 primeira não chegava: dizia 16.08 num aparelho a correr
                                 código do dia 17, e não havia como distinguir aparelhos. --}}
                            Modo Offline ·
                            <span class="font-mono">v{{ config('changelog.current', '1.0') }}</span>
                            <span class="font-mono opacity-60" title="{{ __('Versão instalada neste aparelho') }}">
                                ·{{ app(\App\Http\Controllers\PwaController::class)->buildVersion() }}
                            </span>
                            <span id="pwa-last-sync-badge" class="hidden ml-1 font-normal"></span>
                        </p>
                    </div>
                </a>
            </div>
            <div class="flex items-center gap-2">
                <button id="pwa-install-header" class="hidden px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-amber-950 rounded-lg text-xs font-bold transition" title="Instalar aplicação">
                    <i class="fas fa-download mr-1"></i>Instalar
                </button>
                <button onclick="window.SosPwa.sync(true)" id="pwa-sync-btn" class="px-3 py-1.5 bg-white/15 hover:bg-white/25 rounded-lg text-xs font-semibold transition" title="Sincronizar agora">
                    <i class="fas fa-rotate"></i>
                </button>
                <a href="{{ route('invoicing.offline.exit') }}" class="px-3 py-1.5 bg-white/15 hover:bg-white/25 rounded-lg text-xs font-semibold transition">
                    <i class="fas fa-arrow-right-from-bracket mr-1"></i>Sair do PWA
                </a>
            </div>
        </div>
    </header>

    <main class="px-4 py-4 pb-20">
        @yield('content')
    </main>

    {{-- Bottom navigation --}}
    <nav class="fixed bottom-0 inset-x-0 bg-white border-t border-gray-200 shadow-2xl z-40">
        <div class="grid grid-cols-5 text-center">
            <a href="{{ route('invoicing.offline.index') }}" class="py-3 hover:bg-blue-50 {{ request()->routeIs('invoicing.offline.index') ? 'text-blue-700 bg-blue-50' : 'text-gray-600' }}">
                <i class="fas fa-home block text-lg"></i>
                <span class="text-[10px] font-semibold">Início</span>
            </a>
            <a href="{{ route('invoicing.offline.catalog') }}" class="py-3 hover:bg-blue-50 {{ request()->routeIs('invoicing.offline.catalog') ? 'text-blue-700 bg-blue-50' : 'text-gray-600' }}">
                <i class="fas fa-box block text-lg"></i>
                <span class="text-[10px] font-semibold">Catálogo</span>
            </a>
            <a href="{{ route('invoicing.offline.pos') }}" class="py-2 -mt-4">
                <div class="w-12 h-12 mx-auto bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center shadow-lg text-white">
                    <i class="fas fa-cash-register text-lg"></i>
                </div>
                <span class="text-[10px] font-semibold text-orange-700 block mt-0.5">POS</span>
            </a>
            <a href="{{ route('invoicing.offline.clients') }}" class="py-3 hover:bg-blue-50 {{ request()->routeIs('invoicing.offline.clients') ? 'text-blue-700 bg-blue-50' : 'text-gray-600' }}">
                <i class="fas fa-users block text-lg"></i>
                <span class="text-[10px] font-semibold">Clientes</span>
            </a>
            <a href="{{ route('invoicing.offline.drafts') }}" class="py-3 hover:bg-blue-50 {{ request()->routeIs('invoicing.offline.drafts') ? 'text-blue-700 bg-blue-50' : 'text-gray-600' }}">
                <i class="fas fa-file-invoice block text-lg"></i>
                <span class="text-[10px] font-semibold">Rascunhos</span>
            </a>
        </div>
    </nav>

    {{-- Registo do Service Worker + auto-update (sem isto o PWA nunca atualiza) --}}
    @include('partials.pwa-register')

    {{-- O dicionário ANTES dos ficheiros de /js: eles chamam window.__ e
         precisam de o encontrar já definido. --}}
    @include('partials.js-traducoes')

    {{-- Quem está a usar isto, e por conta de que empresa.
         A cópia de segurança carimba estes valores, e é por eles que a
         importação recusa um ficheiro de outra empresa: sem o carimbo, as
         vendas entravam na contabilidade errada e uma factura emitida não
         se apaga. --}}
    <script>
        window.SOS_TENANT_ID = @json(activeTenantId());
        window.SOS_USER_ID   = @json(auth()->id());
        window.SOS_USER_NAME = @json(auth()->user()?->name);
    </script>

    <script src="/js/pwa-invoicing.js?v=17"></script>
    <script src="/js/pos-offline-ticket.js?v=3"></script>

    {{-- PWA OFFLINE WARMUP — pré-cacheia todas as páginas + assets críticos do PWA. --}}
    {{-- Garante que o app abre offline mesmo na primeira tentativa após sair de uma página. --}}
    <script>
    (function() {
        if (!('serviceWorker' in navigator)) return;
        if (!navigator.onLine) return;

        // Versão atrelada ao changelog — quando muda, faz warmup outra vez.
        const WARMUP_KEY = 'soserp-pwa-warmed-{{ config('changelog.current', '1.0') }}';
        try { if (localStorage.getItem(WARMUP_KEY) === '1') return; } catch (e) {}

        const URLS = [
            '{{ route('invoicing.offline.index') }}',
            '{{ route('invoicing.offline.catalog') }}',
            '{{ route('invoicing.offline.pos') }}',
            '{{ route('invoicing.offline.clients') }}',
            '{{ route('invoicing.offline.client-new') }}',
            '{{ route('invoicing.offline.drafts') }}',
            '{{ route('invoicing.offline.draft-new') }}',
            '/js/pwa-invoicing.js?v=17',
            '/js/pos-offline-ticket.js?v=3',
            '/manifest.webmanifest',
        ];

        // Esperar o SW estar pronto e fazer requests silenciosos para popular o cache.
        navigator.serviceWorker.ready.then(() => {
            // Pequeno delay para não competir com o load inicial.
            setTimeout(() => {
                Promise.allSettled(
                    URLS.map((u) => fetch(u, { credentials: 'same-origin', cache: 'no-store' }))
                ).then(() => {
                    try { localStorage.setItem(WARMUP_KEY, '1'); } catch (e) {}
                    console.log('[PWA] Warmup concluído — app pronta para offline.');
                });
            }, 1500);
        });
    })();
    </script>

    {{-- ============================================================
         OFFLINE LOGIN — gate e setup
         ============================================================
         Fluxos:
         1. SETUP (online, primeira vez): após sync, se não há auth_cache
            mostra prompt para o utilizador definir password local.
         2. GATE (offline / sessão expirada / tab novo): se há auth_cache
            válido e o tab não está unlocked, mostra overlay de login.
    --}}

    {{-- Overlay GATE de login offline --}}
    <div id="pwa-offline-login" class="hidden fixed inset-0 z-[100] bg-gradient-to-br from-slate-900 to-blue-900 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm p-6">
            <div class="text-center mb-5">
                <div class="w-16 h-16 mx-auto bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl flex items-center justify-center mb-3">
                    <i class="fas fa-lock text-white text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">Login Offline</h2>
                <p class="text-xs text-gray-500 mt-1" id="pwa-offline-login-subtitle">Insira as credenciais para desbloquear o PWA.</p>
            </div>
            <form id="pwa-offline-login-form" class="space-y-3">
                <div>
                    <label class="block text-[11px] font-bold text-gray-600 uppercase mb-1">Email</label>
                    <input id="pwa-offline-login-email" type="email" required autocomplete="username"
                           class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-600 uppercase mb-1">Password</label>
                    <input id="pwa-offline-login-password" type="password" required autocomplete="current-password"
                           class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-blue-500 focus:outline-none">
                </div>
                <p id="pwa-offline-login-error" class="hidden text-xs text-red-600 font-bold text-center"></p>
                <button type="submit" id="pwa-offline-login-submit"
                        class="w-full bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-bold py-3 rounded-xl shadow-lg disabled:opacity-50">
                    <i class="fas fa-unlock mr-1"></i>Desbloquear
                </button>
            </form>
            <p class="mt-4 text-[10px] text-center text-gray-400">
                Se voltar à internet, faça login normal em <a href="/login" class="text-blue-600 underline">/login</a>.
            </p>
        </div>
    </div>

    {{-- Banner SETUP (configurar login offline) --}}
    <div id="pwa-offline-login-setup" class="hidden fixed bottom-20 inset-x-3 z-[90] bg-gradient-to-r from-emerald-600 to-teal-700 text-white rounded-2xl shadow-2xl p-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-shield-halved text-xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-bold text-sm">Ativar login offline</p>
                <p class="text-xs opacity-90">Permite usar o PWA mesmo quando perde a internet.</p>
            </div>
            <button id="pwa-offline-login-setup-btn" class="bg-white text-teal-700 px-3 py-2 rounded-lg text-xs font-bold whitespace-nowrap">Ativar</button>
            <button id="pwa-offline-login-setup-dismiss" class="text-white/70 hover:text-white text-lg px-1" title="Mais tarde">&times;</button>
        </div>
    </div>

    {{-- Modal SETUP: pedir password --}}
    <div id="pwa-offline-login-setup-modal" class="hidden fixed inset-0 z-[110] bg-black/60 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-shield-halved text-emerald-600 mr-2"></i>Ativar login offline</h3>
                <button id="pwa-offline-setup-close" class="text-gray-400 text-2xl">&times;</button>
            </div>
            <p class="text-xs text-gray-500 mb-3">Digite a sua password atual para ativar o acesso offline. Será guardada em formato encriptado neste dispositivo (válido 90 dias).</p>
            <form id="pwa-offline-setup-form" class="space-y-3">
                <div>
                    <label class="block text-[11px] font-bold text-gray-600 uppercase mb-1">Password</label>
                    <input id="pwa-offline-setup-password" type="password" required minlength="4" autocomplete="current-password"
                           class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-emerald-500 focus:outline-none">
                </div>
                <p id="pwa-offline-setup-error" class="hidden text-xs text-red-600 font-bold"></p>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl shadow-lg">
                    <i class="fas fa-check mr-1"></i>Ativar login offline
                </button>
            </form>
        </div>
    </div>

    <script>
    (function() {
        // ============================================================
        // OFFLINE LOGIN GATE
        // ============================================================
        const overlay   = document.getElementById('pwa-offline-login');
        const form      = document.getElementById('pwa-offline-login-form');
        const errEl     = document.getElementById('pwa-offline-login-error');
        const subtitle  = document.getElementById('pwa-offline-login-subtitle');
        const btnSubmit = document.getElementById('pwa-offline-login-submit');

        // Setup
        const setupBanner   = document.getElementById('pwa-offline-login-setup');
        const setupBtn      = document.getElementById('pwa-offline-login-setup-btn');
        const setupDismiss  = document.getElementById('pwa-offline-login-setup-dismiss');
        const setupModal    = document.getElementById('pwa-offline-login-setup-modal');
        const setupClose    = document.getElementById('pwa-offline-setup-close');
        const setupForm     = document.getElementById('pwa-offline-setup-form');
        const setupPassword = document.getElementById('pwa-offline-setup-password');
        const setupError    = document.getElementById('pwa-offline-setup-error');

        function showOverlay(info) {
            overlay.classList.remove('hidden');
            const emailInput = document.getElementById('pwa-offline-login-email');
            if (info?.email) {
                emailInput.value = info.email;
                emailInput.readOnly = true;
                subtitle.textContent = 'Insira a password de ' + info.email;
            }
            document.getElementById('pwa-offline-login-password').focus();
        }

        function hideOverlay() {
            overlay.classList.add('hidden');
            errEl.classList.add('hidden');
            const pwd = document.getElementById('pwa-offline-login-password');
            if (pwd) pwd.value = '';
        }

        async function evaluateGate() {
            // Se não existe SosPwa ainda, tentar de novo em 200ms
            if (!window.SosPwa?.isOfflineAuthEnabled) {
                setTimeout(evaluateGate, 200);
                return;
            }
            // Se já desbloqueado neste tab, não fazer nada
            if (window.SosPwa.isPwaUnlocked()) return;

            // Se há auth_cache válido, mostrar overlay
            const enabled = await window.SosPwa.isOfflineAuthEnabled();
            if (enabled) {
                const info = await window.SosPwa.getOfflineAuthInfo();
                showOverlay(info);
            }
            // Se não há cache: assume sessão Laravel válida (página foi servida).
            // O sync online vai marcar pwa_unlocked.
        }

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            errEl.classList.add('hidden');
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>A verificar…';
            try {
                const email = document.getElementById('pwa-offline-login-email').value;
                const pwd   = document.getElementById('pwa-offline-login-password').value;
                const res   = await window.SosPwa.verifyOfflineAuth(email, pwd);
                if (res.ok) {
                    hideOverlay();
                    window.dispatchEvent(new CustomEvent('pwa:offline-login-success', { detail: res }));
                } else {
                    let msg = 'Credenciais inválidas';
                    if (res.reason === 'EXPIRED')        msg = 'Cache expirou — ligue-se à internet e faça login.';
                    else if (res.reason === 'NO_CACHE')  msg = 'Login offline não está configurado.';
                    else if (res.reason === 'EMAIL_MISMATCH') msg = 'Este email não está registado offline.';
                    else if (res.reason === 'BAD_PASSWORD')   msg = 'Password incorreta.';
                    errEl.textContent = msg;
                    errEl.classList.remove('hidden');
                }
            } catch (err) {
                errEl.textContent = err.message || 'Erro inesperado';
                errEl.classList.remove('hidden');
            } finally {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fas fa-unlock mr-1"></i>Desbloquear';
            }
        });

        // ============================================================
        // SETUP (banner + modal)
        // ============================================================
        async function maybeShowSetup() {
            if (!window.SosPwa?.isOfflineAuthEnabled) {
                setTimeout(maybeShowSetup, 500);
                return;
            }
            // Não mostrar se já foi dispensado nesta sessão
            try { if (sessionStorage.getItem('pwa_setup_dismissed') === '1') return; } catch (_) {}
            // Não mostrar se overlay de gate aberto
            if (!overlay.classList.contains('hidden')) return;
            // Não mostrar se já está ativado
            const enabled = await window.SosPwa.isOfflineAuthEnabled();
            if (enabled) return;
            // Tem que estar online + ter user (sessão Laravel ativa)
            if (!navigator.onLine) return;
            const userMeta = await window.SosPwa.db.meta.get('user');
            if (!userMeta?.value?.email) return;
            setupBanner.classList.remove('hidden');
        }

        setupBtn?.addEventListener('click', () => {
            setupBanner.classList.add('hidden');
            setupModal.classList.remove('hidden');
            setupPassword.focus();
        });

        setupDismiss?.addEventListener('click', () => {
            setupBanner.classList.add('hidden');
            try { sessionStorage.setItem('pwa_setup_dismissed', '1'); } catch (_) {}
        });

        setupClose?.addEventListener('click', () => {
            setupModal.classList.add('hidden');
            setupError.classList.add('hidden');
            setupPassword.value = '';
        });

        setupForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            setupError.classList.add('hidden');
            try {
                await window.SosPwa.enableOfflineAuth(setupPassword.value);
                setupModal.classList.add('hidden');
                setupPassword.value = '';
                // Toast simples
                alert('✅ Login offline ativado. Pode agora usar o PWA mesmo sem internet (90 dias).');
            } catch (err) {
                setupError.textContent = err.message || 'Erro ao ativar';
                setupError.classList.remove('hidden');
            }
        });

        // Triggers
        document.addEventListener('DOMContentLoaded', () => {
            evaluateGate();
            // Mostrar setup só após sync bem-sucedida (utilizador autenticado)
            window.addEventListener('pwa:synced', () => setTimeout(maybeShowSetup, 1500));
        });

        // Sessão expirou → reavaliar gate (mostra overlay se há cache offline)
        window.addEventListener('pwa:session-expired', () => setTimeout(evaluateGate, 200));
    })();
    </script>

    @stack('scripts')
{{-- Rede de segurança do ecrã cinzento.

     O start_url do PWA é a página do POS, e essa página tem vinte e dois
     x-cloak. O x-cloak é `display: none !important` até o Alpine arrancar — e
     o Alpine vem do unpkg. Numa instalação acabada de fazer, com a rede a
     falhar, ele não chega: fica tudo escondido, o utilizador vê um ecrã
     cinzento e nada lhe diz porquê nem o que fazer. Foi o que se viu ao
     reinstalar a aplicação.

     Isto não faz o Alpine funcionar — nada aqui pode. O que faz é trocar um
     ecrã mudo por um ecrã que se explica e oferece uma saída. --}}
<script>
(function () {
    var SEGUNDOS = 8;

    function arrancou() {
        return typeof window.Alpine !== 'undefined';
    }

    function desistir() {
        if (arrancou()) return;

        // Mostrar o que estava escondido: mesmo por hidratar, a página tem o
        // menu e os links, e com eles dá para sair dali.
        document.documentElement.classList.add('alpine-falhou');

        if (document.getElementById('pwa-aviso-arranque')) return;

        var aviso = document.createElement('div');
        aviso.id = 'pwa-aviso-arranque';
        aviso.setAttribute('role', 'alert');
        aviso.style.cssText = 'position:fixed;left:0;right:0;top:0;z-index:9999;'
            + 'background:#b45309;color:#fff;padding:12px 16px;font-size:14px;line-height:1.45;'
            + 'font-family:-apple-system,Segoe UI,Roboto,sans-serif;box-shadow:0 2px 8px rgba(0,0,0,.2)';
        aviso.innerHTML =
            '<strong>A aplicação não carregou por completo.</strong> '
            + 'Faltou uma parte que vem da internet. Ligue-se à rede e recarregue — '
            + 'depois de carregar uma vez, passa a abrir sem internet.'
            + '<button type="button" id="pwa-aviso-recarregar" '
            + 'style="margin-left:12px;background:#fff;color:#b45309;border:0;border-radius:8px;'
            + 'padding:6px 14px;font-weight:700;cursor:pointer">Recarregar</button>';

        document.body.appendChild(aviso);
        document.getElementById('pwa-aviso-recarregar').onclick = function () {
            window.location.reload();
        };

        console.warn('[PWA] O Alpine não arrancou em ' + SEGUNDOS + 's. Conteúdo revelado à força.');
    }

    // Depois do load e não a partir do início: com a rede lenta, o Alpine pode
    // demorar e não vale a pena assustar ninguém enquanto ele ainda vem a
    // caminho.
    if (document.readyState === 'complete') {
        setTimeout(desistir, SEGUNDOS * 1000);
    } else {
        window.addEventListener('load', function () {
            setTimeout(desistir, SEGUNDOS * 1000);
        });
    }
})();
</script>
</body>
</html>
