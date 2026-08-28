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
    {{-- LOCAIS, NUNCA DE CDN — e esta é a página onde isso mais importa.

         A entrada era servida de três CDN: Tailwind, Alpine e Dexie. Sem rede
         nenhum deles chega, e sem o Dexie o motor nem arranca (o
         pwa-invoicing.js começa com `if (typeof Dexie === 'undefined') return`).
         O ecrã aparecia — vinha do cache — mas o formulário do PIN não
         respondia, porque não havia motor nenhum por trás dele. A página cuja
         única razão de existir é funcionar sem rede era a única que dependia
         de três servidores estranhos.

         O resto do PWA já tinha sido passado para `/vendor/`, e estas cópias
         estão na lista de pré-guardados do service worker. Esta ficou para
         trás. --}}
    <script src="/vendor/js/tailwind.js"></script>
    <script defer src="/vendor/js/alpine.min.js"></script>
    <script src="/vendor/js/dexie.min.js"></script>
    <link rel="stylesheet" href="/vendor/css/fontawesome.min.css">
    {{-- O MESMO motor do resto do PWA. Uma segunda base aqui dentro era um
         segundo sistema de login offline, cego ao que o outro guardou. --}}
    {{-- Caminho literal, igual ao do precache/warmup do SW: um asset() com
         host de APP_URL diferente da origem servida seria outra chave de
         cache e offline ficava sem motor. --}}
    <script src="/js/vendor/bcrypt.min.js?v=1"></script>
    <script src="/js/pwa-invoicing.js?v=22"></script>
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

    {{-- Estado da rede: quem está ao balcão tem de saber em que modo vai entrar.

         TRÊS ESTADOS, e não dois. Uma sessão expirada aparecia aqui como "Sem
         ligação — entrada local" com o telemóvel cheio de sinal: o ping devolve
         401 e isso contava como falta de rede. O operador punha o PIN, que
         confere (é um desbloqueio local), e a aplicação saltava para o POS — que
         vai pela rede que existe, apanha o desvio do `auth` e aterra na entrada
         normal. Parecia que o PIN não fazia nada.

         Com rede, quem resolve uma sessão morta é a palavra-passe. --}}
    <div class="mb-3 text-center">
        <span x-show="estado === 'online'" x-cloak
              class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-100 text-xs font-semibold">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>{{ __('Com ligação') }}
        </span>
        <span x-show="estado === 'sessao_expirada'" x-cloak
              class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-sky-500/20 text-sky-100 text-xs font-semibold">
            <span class="w-2 h-2 rounded-full bg-sky-300"></span>{{ __('Sessão expirada — entre outra vez') }}
        </span>
        <span x-show="estado === 'offline'" x-cloak
              class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-amber-500/20 text-amber-100 text-xs font-semibold">
            <span class="w-2 h-2 rounded-full bg-amber-400"></span>{{ __('Sem ligação — entrada local') }}
        </span>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl p-6">

        {{-- Com rede: formulário normal, para o servidor. É ele que cria sessão. --}}
        <form x-show="temRede && !usarOffline" x-cloak method="POST" action="{{ route('login') }}">
            @csrf
            <input type="hidden" name="pwa" value="1">

            {{-- A sessão caducou, mas há rede: a palavra-passe resolve isto num
                 toque. Antes, este caso aparecia como "sem ligação" e mandava o
                 operador por um PIN que nunca podia chegar ao POS. --}}
            <div x-show="estado === 'sessao_expirada'" x-cloak
                 class="mb-4 rounded-lg bg-sky-50 border border-sky-200 p-3 text-xs text-sky-900">
                <i class="fas fa-circle-info mr-1"></i>{{ __('A sua sessão expirou. Entre com a palavra-passe para continuar — o que ficou por enviar continua guardado neste aparelho.') }}
            </div>

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

            {{-- Recurso explícito para Wi-Fi sem internet e browsers que
                 mantêm navigator.onLine=true. Se o aparelho já foi preparado,
                 o operador nunca fica preso ao formulário do servidor. --}}
            <button x-show="temAcessoOffline" x-cloak type="button" @click="usarOffline = true"
                    class="w-full mt-3 border border-amber-300 bg-amber-50 text-amber-800 font-bold py-2.5 rounded-lg text-sm">
                <i class="fas fa-plane mr-1"></i>{{ __('Entrar com PIN offline') }}
            </button>
        </form>

        {{-- Sem rede: confere-se contra o acesso offline guardado no aparelho
             pela barra "Ativar login offline", ja dentro da aplicacao. --}}
        <div x-show="!temRede || usarOffline" x-cloak>
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
                    {{-- COM REDE, O PIN NÃO CHEGA AO POS.

                         O PIN é um desbloqueio local: não cria sessão no
                         servidor. Havendo rede, o pedido seguinte vai lá ter,
                         apanha o desvio do `auth` e o operador aterra na
                         entrada normal sem perceber porquê. Quem chega aqui
                         por engano — com rede — tem de saber que o caminho é
                         a palavra-passe. --}}
                    <div x-show="estado === 'sessao_expirada'" x-cloak
                         class="mb-4 rounded-lg bg-sky-50 border border-sky-200 p-3 text-xs text-sky-900">
                        <i class="fas fa-circle-info mr-1"></i>{{ __('Há rede: o PIN não chega para entrar. Volte atrás e use a palavra-passe.') }}
                        <button type="button" @click="usarOffline = false"
                                class="block mt-2 font-bold underline">{{ __('Entrar com palavra-passe') }}</button>
                    </div>

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
                    <button x-show="temRede" type="button" @click="usarOffline = false"
                            class="w-full mt-3 text-xs text-blue-700 font-semibold underline">
                        {{ __('Voltar ao login com internet') }}
                    </button>
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

{{-- O FontAwesome já vem do `/vendor/` no cabeçalho. Estava aqui duas vezes,
     de um CDN, e uma delas como <script> a apontar para um ficheiro CSS. --}}

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
        // 'online' | 'sessao_expirada' | 'offline' — ver estadoDaLigacao()
        // no motor. Um booleano não chegava: uma sessão morta não é falta de
        // rede, e tratá-la como tal mandava o operador por um PIN que, com
        // rede, nunca chega ao POS.
        estado: navigator.onLine ? 'online' : 'offline',
        usarOffline: false,
        email: '',
        pin: '',
        erro: '',
        ocupado: false,
        temAcessoOffline: false,
        janelaExpirada: false,
        pendentes: 0,

        // Há rede? (com sessão ou sem ela). É o que decide qual dos dois
        // formulários aparece: com rede resolve-se com a palavra-passe, sem
        // rede resolve-se com o PIN.
        get temRede() {
            return this.estado !== 'offline';
        },

        async arranque() {
            window.addEventListener('online', () => { this.actualizarLigacao(); });
            window.addEventListener('offline', () => { this.estado = 'offline'; });

            const p = await pwa();
            if (!p) return;

            // navigator.onLine só diz que há uma interface de rede. Num
            // telemóvel ligado a um Wi-Fi sem internet continua a devolver
            // true e mostrava o formulário normal, que nunca poderia entrar.
            // O ping real decide qual dos formulários deve aparecer — e
            // distingue "sem rede" de "sessão morta", que são coisas
            // diferentes e pedem respostas diferentes.
            this.estado = await p.estadoDaLigacao();

            // Sem rede vai-se direito ao PIN. Com rede — mesmo com a sessão
            // morta — o caminho é a palavra-passe.
            if (this.estado === 'offline') { this.usarOffline = true; }

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

        async actualizarLigacao() {
            const p = await pwa();
            this.estado = p ? await p.estadoDaLigacao() : (navigator.onLine ? 'online' : 'offline');

            // Sem rede, salta-se direito ao PIN. COM rede não: com rede o
            // caminho é a palavra-passe, e empurrar para o PIN era o que
            // fechava o operador no beco.
            if (this.estado === 'offline') { this.usarOffline = true; }
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
                    if (r.reason === 'NO_ENGINE') {
                        this.erro = MSG_SEM_MOTOR;
                    } else if (r.reason === 'LOCKED') {
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
