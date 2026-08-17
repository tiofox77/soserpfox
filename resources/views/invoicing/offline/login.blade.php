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
        <form x-show="online" x-cloak method="POST" action="{{ route('login') }}" @submit="guardarVerificador">
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

        {{-- Sem rede: confere-se contra o verificador guardado neste aparelho. --}}
        <div x-show="!online" x-cloak>
            <template x-if="!temVerificador">
                <div class="text-center py-4">
                    <i class="fas fa-wifi text-3xl text-amber-500 mb-2"></i>
                    <p class="text-sm font-semibold text-gray-800">{{ __('Este aparelho ainda não entrou uma vez.') }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ __('A primeira entrada tem de ser com ligação. Depois disso passa a funcionar sem rede.') }}
                    </p>
                </div>
            </template>

            <template x-if="temVerificador">
                <form @submit.prevent="entrarLocal">
                    <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('Email') }}</label>
                    <input type="email" x-model="email" required autocomplete="username"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm mb-3">

                    <label class="block text-xs font-bold text-gray-600 mb-1">{{ __('Palavra-passe') }}</label>
                    <input type="password" x-model="password" required autocomplete="current-password"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm mb-4">

                    <p x-show="erro" x-cloak class="text-red-600 text-xs mb-3" x-text="erro"></p>

                    <button type="submit" :disabled="ocupado"
                            class="w-full bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white font-bold py-2.5 rounded-lg text-sm transition">
                        <span x-show="!ocupado">{{ __('Entrar sem rede') }}</span>
                        <span x-show="ocupado" x-cloak>{{ __('A verificar…') }}</span>
                    </button>

                    <p class="text-[11px] text-gray-400 mt-3 leading-snug">
                        {{ __('Entra no que já está guardado neste aparelho. As vendas ficam em fila e sobem ao servidor assim que houver rede.') }}
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
// A base é a mesma do motor offline; aqui só se lê o `meta` e a fila.
const dbLogin = new Dexie('SosErpInvoicing');
dbLogin.version(3).stores({
    products: 'id, name, sku, barcode, type, category',
    clients: 'id, local_uuid, name, nif, _synced',
    series: 'id, document_type',
    tax_rates: '++id, rate',
    draft_invoices: 'local_uuid, client_id, client_local_uuid, created_at, _synced, _status',
    sync_queue: '++id, op, created_at, retries, status',
    meta: 'key',
    draft_documents: 'local_uuid, doc_type, client_id, client_local_uuid, created_at, _synced, _server_id',
    pos_sales: 'local_uuid, created_at, _synced, _server_id, _server_number',
});

/** PBKDF2-SHA256. Devagar de propósito: um aparelho roubado dá tentativas sem conta. */
async function derivar(password, saltB64, iteracoes = 210000) {
    const enc = new TextEncoder();
    const salt = Uint8Array.from(atob(saltB64), c => c.charCodeAt(0));
    const chave = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveBits']);
    const bits = await crypto.subtle.deriveBits(
        { name: 'PBKDF2', salt, iterations: iteracoes, hash: 'SHA-256' },
        chave, 256
    );
    return btoa(String.fromCharCode(...new Uint8Array(bits)));
}

/** Comparação de tempo constante: não deixar o tempo de resposta dizer nada. */
function igual(a, b) {
    if (a.length !== b.length) return false;
    let d = 0;
    for (let i = 0; i < a.length; i++) d |= a.charCodeAt(i) ^ b.charCodeAt(i);
    return d === 0;
}

function pwaLogin() {
    return {
        online: navigator.onLine,
        email: '',
        password: '',
        erro: '',
        ocupado: false,
        temVerificador: false,
        pendentes: 0,

        async arranque() {
            window.addEventListener('online', () => { this.online = true; });
            window.addEventListener('offline', () => { this.online = false; });

            try {
                const v = await dbLogin.meta.get('acesso_local');
                this.temVerificador = !!(v && v.value && v.value.hash);
                if (v?.value?.email) this.email = v.value.email;

                this.pendentes = await dbLogin.sync_queue.where('status').equals('pending').count();
            } catch (_) {}
        },

        /**
         * Entrada COM rede: antes de o formulário partir, guarda-se o
         * verificador para as próximas vezes sem rede.
         *
         * Guarda-se aqui e não no servidor porque é aqui que a palavra-passe
         * existe — e sai daqui derivada, nunca em claro.
         */
        async guardarVerificador() {
            try {
                const salt = btoa(String.fromCharCode(...crypto.getRandomValues(new Uint8Array(16))));
                const hash = await derivar(this.password, salt);

                await dbLogin.meta.put({
                    key: 'acesso_local',
                    value: { email: (this.email || '').trim().toLowerCase(), salt, hash, em: new Date().toISOString() },
                });
            } catch (_) {
                // Falhar a guardar não pode impedir a entrada normal.
            }
        },

        async entrarLocal() {
            if (this.ocupado) return;
            this.ocupado = true;
            this.erro = '';

            try {
                const v = (await dbLogin.meta.get('acesso_local'))?.value;

                if (!v) {
                    this.erro = '{{ __('Este aparelho ainda não entrou uma vez com ligação.') }}';
                    return;
                }

                const mesmoEmail = (this.email || '').trim().toLowerCase() === v.email;
                const hash = await derivar(this.password, v.salt);

                // As duas condições avaliam-se sempre, e a mensagem é uma só:
                // dizer qual delas falhou é dizer se o email existe.
                if (!mesmoEmail || !igual(hash, v.hash)) {
                    this.erro = '{{ __('Email ou palavra-passe que não conferem.') }}';
                    return;
                }

                await dbLogin.meta.put({
                    key: 'sessao_local',
                    value: { email: v.email, desde: new Date().toISOString() },
                });

                window.location.href = '{{ route('invoicing.offline.pos') }}';
            } catch (e) {
                this.erro = '{{ __('Não foi possível verificar neste aparelho.') }}';
            } finally {
                this.ocupado = false;
                this.password = '';
            }
        },
    };
}
</script>
</body>
</html>
