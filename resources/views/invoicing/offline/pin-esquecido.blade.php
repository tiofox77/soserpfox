{{--
    Esqueci o PIN — sem rede.

    O PIN de turno é um bcrypt: não se "acha", só se repõe. Com rede repõe-se
    em Utilizadores ou em "PIN de turno". Sem rede, até aqui, não havia nada:
    quem o esquecesse ficava fora da caixa até a internet voltar.

    Agora um GESTOR PRESENTE autoriza um PIN novo, ali mesmo. Põe o seu email e
    o seu PIN (conferido contra o verificador que o aparelho já tem), o
    funcionário escolhe o PIN novo, e o aparelho calcula o bcrypt e guarda-o.
    Entra-se de imediato. A reposição vai na fila e o servidor decide quando
    houver rede: se recusar, a sincronização seguinte repõe o PIN antigo.

    Tudo o que esta página faz é local. É pública (sem `auth`) e fica guardada
    na instalação, como a entrada — senão não existia quando faz falta.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Esqueci o PIN') }} — SOS ERP</title>
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <meta name="theme-color" content="{{ pwa_theme_color() }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ url("/pwa/icon-maskable-192.png") }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SOS ERP">
    {{-- LOCAIS, NUNCA DE CDN: sem rede é a única altura em que isto serve. --}}
    <script src="/vendor/js/tailwind.js"></script>
    <script defer src="/vendor/js/alpine.min.js"></script>
    <script src="/vendor/js/dexie.min.js"></script>
    <link rel="stylesheet" href="/vendor/css/fontawesome.min.css">
    {{-- O MESMO motor da entrada e do POS: a mesma gaveta, os mesmos
         verificadores, o mesmo bcrypt. Caminhos literais, iguais ao precache. --}}
    <script src="/js/vendor/bcrypt.min.js?v=1"></script>
    <script src="/js/pwa-invoicing.js?v={{ pwa_versao() }}"></script>
</head>
<body class="bg-gradient-to-br from-blue-900 to-blue-700 min-h-screen flex items-center justify-center p-4">

<div x-data="pinEsquecido()" x-init="arranque()" class="w-full max-w-sm">

    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/15 mb-3">
            <img src="{{ asset('pwa/icon-192x192.png') }}" alt="SOS ERP" class="w-11 h-11 object-contain" draggable="false">
        </div>
        <h1 class="text-white text-xl font-bold">{{ __('Esqueci o PIN') }}</h1>
        <p class="text-blue-200 text-sm">{{ __('Um gestor presente autoriza um PIN novo') }}</p>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl p-6">

        {{-- Este aparelho nunca sincronizou: não há verificadores para conferir
             ninguém, nem gestor nem funcionário. --}}
        <template x-if="pronto && !temAcessoOffline">
            <div class="text-center py-4">
                <i class="fas fa-wifi text-3xl text-amber-500 mb-2"></i>
                <p class="text-sm font-semibold text-gray-800">{{ __('Este aparelho ainda não sincronizou a empresa.') }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('Ligue à internet e sincronize uma vez. Depois, qualquer funcionário com PIN entra sem rede.') }}</p>
                <p x-show="janelaExpirada" x-cloak class="text-xs text-amber-700 mt-2 font-semibold">
                    {{ __('O acesso offline deste aparelho caducou. É preciso sincronizar de novo com internet.') }}
                </p>
            </div>
        </template>

        {{-- Feito: o PIN novo já serve neste aparelho. --}}
        <template x-if="feito">
            <div class="text-center py-2">
                <div class="w-14 h-14 mx-auto rounded-full bg-emerald-100 flex items-center justify-center mb-3">
                    <i class="fas fa-check text-emerald-600 text-2xl"></i>
                </div>
                <p class="text-sm font-semibold text-gray-800">
                    {{ __('PIN reposto para') }} <span x-text="nome"></span>.
                </p>
                <p class="text-xs text-gray-500 mt-2 leading-snug">
                    {{ __('Já serve neste aparelho. Chega ao servidor e aos outros aparelhos na próxima sincronização com internet.') }}
                </p>
                <button type="button" @click="entrar"
                        class="w-full mt-4 bg-blue-700 hover:bg-blue-800 text-white font-bold py-2.5 rounded-lg text-sm">
                    {{ __('Entrar com o PIN novo') }}
                </button>
            </div>
        </template>

        <template x-if="pronto && temAcessoOffline && !feito">
            <form @submit.prevent="repor" class="space-y-4">

                <div>
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-2">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-700 text-white text-[10px] mr-1">1</span>
                        {{ __('Quem esqueceu o PIN') }}
                    </p>
                    <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('Email') }}</label>
                    <input type="email" x-model="email" required autocomplete="username"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm" placeholder="seu@email.com">
                </div>

                <div class="rounded-lg bg-amber-50 border border-amber-200 p-3">
                    <p class="text-[11px] font-bold text-amber-900 uppercase tracking-wide mb-2">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-500 text-white text-[10px] mr-1">2</span>
                        {{ __('O gestor autoriza') }}
                    </p>
                    <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('Email do gestor') }}</label>
                    <input type="email" x-model="gestorEmail" required autocomplete="off"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm mb-2" placeholder="gestor@email.com">
                    <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('PIN do gestor') }}</label>
                    <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" x-model="gestorPin" required autocomplete="off"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-lg text-center tracking-[0.4em]" placeholder="••••">
                </div>

                <div>
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-2">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-700 text-white text-[10px] mr-1">3</span>
                        {{ __('O PIN novo') }}
                    </p>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('PIN novo') }}</label>
                            <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" x-model="pinNovo" required autocomplete="new-password"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-lg text-center tracking-[0.3em]" placeholder="••••">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('Repetir') }}</label>
                            <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" x-model="pinNovo2" required autocomplete="new-password"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-lg text-center tracking-[0.3em]" placeholder="••••">
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">{{ __('4 a 6 dígitos. Nada de 1234, datas ou dígitos repetidos.') }}</p>
                </div>

                <p x-show="erro" x-cloak class="text-red-600 text-xs" x-text="erro"></p>

                <button type="submit" :disabled="ocupado"
                        class="w-full bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white font-bold py-2.5 rounded-lg text-sm transition">
                    <span x-show="!ocupado">{{ __('Repor o PIN') }}</span>
                    <span x-show="ocupado" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i>{{ __('A calcular…') }}</span>
                </button>

                <p x-show="estado === 'online'" x-cloak class="text-[11px] text-gray-400 leading-snug">
                    {{ __('Há rede: também pode definir o PIN em Utilizadores, ou em "PIN de turno" com a sua palavra-passe.') }}
                </p>
            </form>
        </template>

        <a :href="voltar" class="block mt-4 text-center text-xs text-blue-700 font-semibold underline">
            <i class="fas fa-arrow-left mr-1"></i>{{ __('Voltar') }}
        </a>
    </div>
</div>

<script>
async function pwa() {
    for (let i = 0; i < 60 && !window.SosPwa; i++) {
        await new Promise(r => setTimeout(r, 100));
    }

    return window.SosPwa || null;
}

const MSG = {
    MISSING:            @json(__('Preencha todos os campos.')),
    NO_ENGINE:          @json(__('O motor offline ainda não carregou. Tente outra vez.')),
    EXPIRED_WINDOW:     @json(__('O acesso offline deste aparelho expirou. É preciso entrar uma vez com ligação.')),
    ALVO_DESCONHECIDO:  @json(__('Este email não está sincronizado neste aparelho. Só quem já tinha PIN pode repô-lo aqui.')),
    BAD_PIN:            @json(__('Email ou PIN do gestor que não conferem.')),
    GESTOR_SEM_DIREITO: @json(__('Esta conta não pode autorizar: só quem gere utilizadores.')),
    LOCKED:             @json(__('Demasiadas tentativas. Espere :seg segundos.')),
    TAMANHO:            @json(__('O PIN tem de ter 4 a 6 dígitos.')),
    OBVIO:              @json(__('Escolha um PIN menos óbvio.')),
    DIFERENTES:         @json(__('Os dois PIN não coincidem.')),
    FALHOU:             @json(__('Não foi possível repor neste aparelho.')),
};

const URL_ENTRADA = @json(route('invoicing.offline.login', [], false));

function pinEsquecido() {
    return {
        pronto: false,
        estado: navigator.onLine ? 'online' : 'offline',
        temAcessoOffline: false,
        janelaExpirada: false,
        email: '',
        gestorEmail: '',
        gestorPin: '',
        pinNovo: '',
        pinNovo2: '',
        erro: '',
        ocupado: false,
        feito: false,
        nome: '',
        voltar: URL_ENTRADA,

        async arranque() {
            // Só se volta para dentro do próprio site: um `voltar` para fora
            // seria uma porta aberta a quem quisesse desviar o operador.
            const pedido = new URLSearchParams(location.search).get('voltar') || '';
            if (pedido.startsWith('/') && !pedido.startsWith('//')) { this.voltar = pedido; }

            const p = await pwa();
            if (!p) { this.pronto = true; return; }

            this.estado = await p.estadoDaLigacao();

            try {
                const info = await p.getOfflineAuthInfo();
                this.temAcessoOffline = !!(info && info.employees > 0 && !info.window_expired);
                this.janelaExpirada = !!(info && info.employees > 0 && info.window_expired);
            } catch (_) {}

            this.pronto = true;
        },

        async repor() {
            if (this.ocupado) return;
            this.erro = '';

            if (this.pinNovo !== this.pinNovo2) { this.erro = MSG.DIFERENTES; return; }

            this.ocupado = true;
            try {
                const p = await pwa();
                if (!p) { this.erro = MSG.NO_ENGINE; return; }

                const r = await p.reporPinOffline({
                    email: this.email, gestorEmail: this.gestorEmail,
                    gestorPin: this.gestorPin, pinNovo: this.pinNovo,
                });

                if (!r.ok) {
                    if (r.reason === 'LOCKED') {
                        const seg = Math.max(1, Math.ceil((r.until - Date.now()) / 1000));
                        this.erro = MSG.LOCKED.replace(':seg', seg);
                    } else if (r.reason === 'EXPIRED_WINDOW') {
                        this.erro = MSG.EXPIRED_WINDOW;
                        this.temAcessoOffline = false;
                        this.janelaExpirada = true;
                    } else {
                        this.erro = MSG[r.reason] || MSG.FALHOU;
                    }
                    return;
                }

                this.nome = r.name || this.email;
                this.feito = true;
            } catch (e) {
                this.erro = MSG.FALHOU;
            } finally {
                this.ocupado = false;
                this.gestorPin = '';
                this.pinNovo = '';
                this.pinNovo2 = '';
            }
        },

        entrar() {
            window.location.href = this.voltar;
        },
    };
}
</script>

{{-- O service worker regista-se aqui também, como na entrada: quem chega a
     esta página pode ser a primeira visita do aparelho. --}}
@include('partials.pwa-register')
</body>
</html>
