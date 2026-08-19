{{--
    Entrada do PWA — funciona com rede e sem ela.

    COM REDE entra-se de verdade: o formulário vai ao servidor, nasce sessão, e
    o que se guarda no aparelho é um VERIFICADOR (PBKDF2 sobre a palavra-passe,
    com sal), nunca a palavra-passe.

    SEM REDE a mesma palavra-passe é conferida contra esse verificador e o
    aparelho destranca-se. Isto NÃO é uma sessão do servidor e não dá acesso a
    nada remoto — serve para quem está ao balcão continuar a vender com o que
    já está no aparelho. As vendas ficam na fila e sobem quando houver rede.

    É preciso dizer com todas as letras o que isto vale: os dados do POS já
    estão no aparelho, legíveis por quem lhe deitar a mão. Este ecrã impede
    que outra pessoa se sente à caixa e venda em nome de quem lá estava. Não
    protege a base local de quem leve o aparelho.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Entrar') }} — SOS ERP</title>
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <meta name="theme-color" content="#1e3a8a">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/dexie@4.0.10/dist/dexie.min.js"></script>
    {{-- O MESMO motor do resto do PWA. Uma segunda base aqui dentro era um
         segundo sistema de login offline, cego ao que o outro guardou. --}}
    <script src="/js/vendor/bcrypt.min.js?v=1"></script>
    <script src="{{ asset('js/pwa-invoicing.js') }}?v=18"></script>
</head>
<body class="bg-gradient-to-br from-blue-900 to-blue-700 min-h-screen flex items-center justify-center p-4">

<div x-data="pwaLogin()" x-init="arranque()" class="w-full max-w-sm">

    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/15 mb-3">
            <i class="fas fa-cash-register text-3xl text-white"></i>
        </div>
        <h1 class="text-white text-xl font-bold">SOS ERP</h1>
        <p class="text-blue-200 text-sm">{{ __('Ponto de venda') }}</p>
    </div>

    {{-- Estado da rede: quem está ao balcão tem de saber em que modo vai entrar --}}
    <div class="mb-3 text-center">
        <span x-show="online" x-cloak
              class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-100 text-xs font-semibold">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>{{ __('Com ligação') }}
        </span>
        <span x-show="!online" x-cloak
              class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-amber-500/20 text-amber-100 text-xs font-semibold">
            <span class="w-2 h-2 rounded-full bg-amber-400"></span>{{ __('Sem ligação — entrada local') }}
        </span>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl p-6">

        {{-- Com rede: formulário normal, para o servidor. É ele que cria sessão. --}}
        <form x-show="online" x-cloak method="POST" action="{{ route('login') }}">
            @csrf
            <input type="hidden" name="pwa" value="1">

            <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('Email') }}</label>
            <input type="email" name="email" x-model="email" required autocomplete="username"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm mb-3"
                   placeholder="seu@email.com">

            <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('Palavra-passe') }}</label>
            <input type="password" name="password" x-model="password" required autocomplete="current-password"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm mb-4"
                   placeholder="••••••••">

            @if ($errors->any())
                <p class="text-red-600 text-xs mb-3">{{ $errors->first() }}</p>
            @endif

            <button type="submit"
                    class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-2.5 rounded-lg text-sm transition">
                {{ __('Entrar') }}
            </button>
        </form>

        {{-- Sem rede: confere-se contra o acesso offline guardado no aparelho
             pela barra "Ativar login offline", ja dentro da aplicacao. --}}
        <div x-show="!online" x-cloak>
            <template x-if="!temAcessoOffline">
                <div class="text-center py-4">
                    <i class="fas fa-wifi text-3xl text-amber-500 mb-2"></i>
                    <p class="text-sm font-semibold text-gray-800">{{ __('Este aparelho ainda não sincronizou a empresa.') }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ __('Ligue à internet e sincronize uma vez. Depois, qualquer funcionário com PIN entra sem rede.') }}
                    </p>
                    <p x-show="janelaExpirada" x-cloak class="text-xs text-amber-700 mt-2 font-semibold">
                        {{ __('O acesso offline deste aparelho caducou. É preciso sincronizar de novo com internet.') }}
                    </p>
                </div>
            </template>

            {{-- Qualquer funcionário activo da empresa entra com o seu email e
                 o PIN de turno — mesmo que nunca tenha usado este aparelho. --}}
            <template x-if="temAcessoOffline">
                <form @submit.prevent="entrarLocal">
                    <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('Email') }}</label>
                    <input type="email" x-model="email" required autocomplete="username"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm mb-3"
                           placeholder="seu@email.com">

                    <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('PIN de turno') }}</label>
                    <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                           x-model="pin" required autocomplete="off"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-lg text-center tracking-[0.4em] mb-4"
                           placeholder="••••">

                    <p x-show="erro" x-cloak class="text-red-600 text-xs mb-3" x-text="erro"></p>

                    <button type="submit" :disabled="ocupado"
                            class="w-full bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white font-bold py-2.5 rounded-lg text-sm transition">
                        <span x-show="!ocupado">{{ __('Entrar sem rede') }}</span>
                        <span x-show="ocupado" x-cloak>{{ __('A verificar…') }}</span>
                    </button>

                    <p class="text-[11px] text-gray-400 mt-3 leading-snug">
                        {{ __('O PIN define-se com internet, em "PIN de turno". As vendas ficam em fila e sobem ao servidor assim que houver rede.') }}
                    </p>
                </form>
            </template>
        </div>
    </div>

    <p class="text-center text-blue-200 text-[11px] mt-4">
        <span x-show="pendentes > 0" x-cloak>
            <i class="fas fa-clock mr-1"></i><span x-text="pendentes"></span> {{ __('venda(s) por enviar neste aparelho') }}
        </span>
    </p>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<script>
// O acesso offline é o do próprio motor (SosPwa): a mesma gaveta, o mesmo
// hash e a mesma validade. Isto aqui só o consulta.
async function pwa() {
    for (let i = 0; i < 60 && !window.SosPwa; i++) {
        await new Promise(r => setTimeout(r, 100));
    }

    return window.SosPwa || null;
}

const URL_POS         = @json(route('invoicing.offline.pos'));
const MSG_SEM_MOTOR   = @json(__('O motor offline ainda não carregou. Tente outra vez.'));
const MSG_EXPIRADO    = @json(__('O acesso offline deste aparelho expirou. É preciso entrar uma vez com ligação.'));
const MSG_NAO_CONFERE = @json(__('Email ou PIN que não conferem.'));
const MSG_FALHOU      = @json(__('Não foi possível verificar neste aparelho.'));
const MSG_TRANCADO    = @json(__('Demasiadas tentativas. Espere :seg segundos.'));

function pwaLogin() {
    return {
        online: navigator.onLine,
        email: '',
        pin: '',
        erro: '',
        ocupado: false,
        temAcessoOffline: false,
        janelaExpirada: false,
        pendentes: 0,

        async arranque() {
            window.addEventListener('online', () => { this.online = true; });
            window.addEventListener('offline', () => { this.online = false; });

            const p = await pwa();
            if (!p) return;

            try {
                // Há acesso offline se a empresa já foi sincronizada (há
                // funcionários com PIN e a janela não caducou) ou se ainda
                // existe o verificador legado de um operador.
                const info = await p.getOfflineAuthInfo();

                const temFuncionarios = info && info.employees > 0 && !info.window_expired;
                const temLegado = info && info.legacy && new Date(info.legacy.expires_at) >= new Date();

                this.temAcessoOffline = !!(temFuncionarios || temLegado);
                this.janelaExpirada = !!(info && info.employees > 0 && info.window_expired);

                // Num aparelho legado (um só operador), pré-preenche o email.
                if (!temFuncionarios && temLegado) {
                    this.email = info.legacy.email;
                }

                this.pendentes = await p.db.sync_queue.where('status').equals('pending').count();
            } catch (_) {}
        },

        async entrarLocal() {
            if (this.ocupado) return;
            this.ocupado = true;
            this.erro = '';

            try {
                const p = await pwa();

                if (!p) {
                    this.erro = MSG_SEM_MOTOR;
                    return;
                }

                const r = await p.verifyOfflineAuth(this.email, this.pin);

                if (!r.ok) {
                    if (r.reason === 'LOCKED') {
                        const seg = Math.max(1, Math.ceil((r.until - Date.now()) / 1000));
                        this.erro = MSG_TRANCADO.replace(':seg', seg);
                    } else if (r.reason === 'EXPIRED_WINDOW' || r.reason === 'EXPIRED') {
                        this.erro = MSG_EXPIRADO;
                        this.temAcessoOffline = false;
                        this.janelaExpirada = true;
                    } else {
                        // Uma mensagem só para email e PIN: distingui-los diria
                        // a quem tenta se o email existe.
                        this.erro = MSG_NAO_CONFERE;
                    }
                    return;
                }

                window.location.href = URL_POS;
            } catch (e) {
                this.erro = MSG_FALHOU;
            } finally {
                this.ocupado = false;
                this.pin = '';
            }
        },
    };
}

</script>
</body>
</html>
