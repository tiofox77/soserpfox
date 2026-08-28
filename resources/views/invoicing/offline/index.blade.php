@extends('layouts.pwa', ['title' => 'PWA Faturação — Início'])

@section('content')
<div x-data="pwaHome()" x-init="init()" x-cloak>
    {{-- QUEM SOU E ONDE ESTOU, em duas linhas.

         Era um bloco azul que ocupava um terço do ecrã para dizer o nome, o
         email, a empresa, o NIF, o armazém e a última sincronização — seis
         coisas que quase nunca mudam, todas em destaque, antes de qualquer
         coisa que se possa fazer. O que ali interessa mesmo é UMA: em que
         empresa é que estou, porque quem trabalha em duas precisa de saber
         isso ANTES de vender. O resto passou para as Ferramentas. --}}
    <div class="flex items-center gap-3 mb-4">
        <div class="w-11 h-11 shrink-0 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center font-bold text-lg"
             x-text="(userName || '?').trim().charAt(0).toUpperCase()">?</div>
        <div class="min-w-0 flex-1">
            <p class="text-base font-bold text-gray-800 leading-tight truncate" x-text="userName">…</p>
            <p class="text-xs text-gray-500 truncate">
                <span x-show="companyName" x-text="companyName"></span>
                <span x-show="warehouseName" class="text-gray-400" x-text="' · ' + warehouseName"></span>
            </p>
        </div>
    </div>

    {{-- O TURNO.
         Vem do cache local, por isso continua a dizer a verdade sem internet —
         que é quando faz falta: quem está na caixa precisa de saber se o turno
         está aberto, desde quando, e com quanto começou. --}}
    <div class="rounded-2xl shadow p-4 mb-4"
         :class="shift.open ? 'bg-emerald-50 border border-emerald-200' : 'bg-gray-50 border border-gray-200'">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 min-w-0">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                     :class="shift.open ? 'bg-emerald-100' : 'bg-gray-200'">
                    <i class="fas fa-cash-register"
                       :class="shift.open ? 'text-emerald-600' : 'text-gray-500'"></i>
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-sm"
                       :class="shift.open ? 'text-emerald-900' : 'text-gray-700'"
                       x-text="shift.open ? '{{ __('Turno aberto') }}' : '{{ __('Sem turno aberto') }}'"></p>
                    <p class="text-xs text-gray-500" x-show="shift.open && shift.number"
                       x-text="'{{ __('Turno') }} #' + shift.number + (shift.openedAt ? ' · ' + horaDoTurno() : '')"></p>
                    <p class="text-xs text-gray-500" x-show="!shift.open">
                        {{ __('Abra um turno no POS antes de começar a vender.') }}
                    </p>
                </div>
            </div>

            {{-- O turno abre-se no POS. Sem acesso ao POS não há botão: levava
                 a um 403 e o cartão do turno passava a parecer avariado. --}}
            @if(\App\Support\MenuDoPwa::podeVer('pos'))
                <a href="{{ route('invoicing.offline.pos') }}"
                   class="shrink-0 px-3 py-2 rounded-xl text-xs font-bold transition"
                   :class="shift.open ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-blue-600 hover:bg-blue-700 text-white'"
                   x-text="shift.open ? '{{ __('Ir para o POS') }}' : '{{ __('Abrir turno') }}'"></a>
            @endif
        </div>

        <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-emerald-200" x-show="shift.open">
            <div>
                <p class="text-[10px] uppercase font-bold text-gray-500">{{ __('Abertura') }}</p>
                <p class="text-sm font-bold text-gray-900" x-text="kz(shift.opening)"></p>
            </div>
            <div>
                <p class="text-[10px] uppercase font-bold text-gray-500">{{ __('Dinheiro') }}</p>
                <p class="text-sm font-bold text-gray-900" x-text="kz(shift.cash)"></p>
            </div>
            <div>
                <p class="text-[10px] uppercase font-bold text-gray-500">{{ __('Total vendido') }}</p>
                <p class="text-sm font-bold text-emerald-700" x-text="kz(shift.total)"></p>
            </div>
        </div>
    </div>

    {{-- Fila de envio --}}
    <div class="bg-white rounded-xl shadow p-4 mb-4" x-show="fila.length || filaErros" x-cloak>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-bold text-gray-800">
                <i class="fas fa-paper-plane text-blue-600 mr-1"></i>{{ __('Por enviar') }}
            </h2>
            <button @click="enviarAgora" :disabled="aEnviar"
                    class="px-3 py-1 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg text-xs font-bold">
                <span x-show="!aEnviar">{{ __('Enviar agora') }}</span>
                <span x-show="aEnviar" x-cloak>{{ __('A enviar…') }}</span>
            </button>
        </div>

        {{-- Contar não chega: quem está à caixa precisa de ver O QUÊ ficou
             preso. "3 por enviar" não se distingue de "3 perdidos". --}}
        <ul class="divide-y divide-gray-100">
            <template x-for="j in fila" :key="j.id">
                <li class="py-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold" x-text="j.tipo"></p>
                            <p class="text-[11px] text-gray-500 truncate" x-text="j.detalhe"></p>
                            <p class="text-[10px] text-gray-400" x-text="quandoTexto(j.quando)"></p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-[10px] px-2 py-0.5 rounded-full font-bold"
                                  :class="j.estado === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'"
                                  x-text="j.estado === 'failed' ? '{{ __('com erro') }}' : '{{ __('à espera') }}'"></span>
                            <p x-show="j.tentativas > 0" x-cloak class="text-[10px] text-gray-400 mt-0.5">
                                <span x-text="j.tentativas"></span> {{ __('tentativa(s)') }}
                            </p>
                        </div>
                    </div>

                    <div x-show="j.erro" x-cloak class="mt-1 flex items-center justify-between gap-2">
                        <p class="text-[10px] text-red-600 truncate" x-text="j.erro"></p>
                        <button @click="repetir(j.id)"
                                class="shrink-0 text-[10px] font-bold text-blue-700 hover:underline">
                            {{ __('Repetir') }}
                        </button>
                    </div>
                </li>
            </template>
        </ul>

        <p x-show="!fila.length" x-cloak class="text-xs text-gray-400 text-center py-2">
            {{ __('Está tudo enviado.') }}
        </p>
    </div>

    {{-- Ações.

         Seguem a MESMA regra do menu de baixo e das rotas — módulo, permissão
         e a escolha da empresa (App\Support\MenuDoPwa). Um atalho para um ecrã
         que responde 403 é pior do que não ter atalho nenhum: parece avaria. --}}
    @php $atalhos = \App\Support\MenuDoPwa::visiveis(); @endphp

    {{-- HIERARQUIA, e não um arco-íris.

         Eram quatro faixas de largura inteira em quatro cores a gritar — o
         vermelho, o laranja, o verde e o roxo todos com o mesmo peso. Sem
         hierarquia, o olho não sabe onde pousar, e "Cliente" pesava tanto como
         o POS, que é a razão de a aplicação existir.

         Agora: o que se usa a toda a hora fica grande e a cor; o resto fica
         numa grelha calma por baixo. --}}
    <div class="space-y-2.5">
        @if(isset($atalhos['pos']))
            <a href="{{ route('invoicing.offline.pos') }}"
               class="flex items-center gap-4 bg-gradient-to-br from-orange-500 to-red-600 text-white rounded-2xl shadow-lg shadow-orange-600/20 p-4 active:scale-[.99] transition">
                <span class="w-12 h-12 shrink-0 rounded-2xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-cash-register text-xl"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-lg font-bold leading-tight">{{ __('Vender') }}</span>
                    <span class="block text-xs opacity-85">{{ __('Balcão — Fatura-Recibo em segundos') }}</span>
                </span>
                <i class="fas fa-chevron-right opacity-60"></i>
            </a>
        @endif

        @if(isset($atalhos['restaurante']))
            <a href="{{ route('invoicing.offline.restaurant') }}"
               class="flex items-center gap-4 bg-gradient-to-br from-amber-500 to-orange-600 text-white rounded-2xl shadow-lg shadow-amber-600/20 p-4 active:scale-[.99] transition">
                <span class="w-12 h-12 shrink-0 rounded-2xl bg-white/20 flex items-center justify-center">
                    <i class="fas fa-utensils text-xl"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-lg font-bold leading-tight">{{ __('Mesas') }}</span>
                    <span class="block text-xs opacity-85">{{ __('Sala, comandas e conta') }}</span>
                </span>
                <i class="fas fa-chevron-right opacity-60"></i>
            </a>
        @endif

        {{-- O que se faz de vez em quando: sem cor de fundo, para não competir
             com o que se faz a toda a hora. --}}
        @if(isset($atalhos['documentos']) || isset($atalhos['clientes']))
            <div class="grid grid-cols-2 gap-2.5">
                @if(isset($atalhos['documentos']))
                    <a href="{{ route('invoicing.offline.draft-new') }}"
                       class="bg-white rounded-2xl shadow-sm p-4 active:scale-[.99] transition">
                        <span class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2">
                            <i class="fas fa-file-circle-plus"></i>
                        </span>
                        <span class="block text-sm font-bold text-gray-800">{{ __('Novo documento') }}</span>
                        <span class="block text-[11px] text-gray-400 leading-tight">{{ __('Fatura ou proforma') }}</span>
                    </a>
                @endif
                @if(isset($atalhos['clientes']))
                    <a href="{{ route('invoicing.offline.client-new') }}"
                       class="bg-white rounded-2xl shadow-sm p-4 active:scale-[.99] transition">
                        <span class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center mb-2">
                            <i class="fas fa-user-plus"></i>
                        </span>
                        <span class="block text-sm font-bold text-gray-800">{{ __('Novo cliente') }}</span>
                        <span class="block text-[11px] text-gray-400 leading-tight">{{ __('Sobe quando houver rede') }}</span>
                    </a>
                @endif
            </div>
        @endif
    </div>

    {{-- O QUE ESTE APARELHO TEM, numa linha.

         Eram quatro caixas grandes a dizer "PRODUTOS EM CACHE" e "CLIENTES EM
         CACHE" — palavras de quem escreve o programa, não de quem o usa: ao
         balcão ninguém sabe o que é uma cache, e a pergunta que se faz é
         "tenho aqui os artigos?". Além disso, "Por sincronizar" repetia o
         cartão de baixo, que já mostra O QUÊ está preso e não só quantos. --}}
    <div class="flex items-stretch bg-white rounded-2xl shadow-sm mb-4 divide-x divide-gray-100">
        <div class="flex-1 px-3 py-3 text-center">
            <p class="text-xl font-bold text-gray-800 leading-none" x-text="counts.products">—</p>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mt-1">{{ __('Artigos') }}</p>
        </div>
        <div class="flex-1 px-3 py-3 text-center">
            <p class="text-xl font-bold text-gray-800 leading-none" x-text="counts.clients">—</p>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mt-1">{{ __('Clientes') }}</p>
        </div>
        <div class="flex-1 px-3 py-3 text-center">
            <p class="text-xl font-bold text-gray-800 leading-none" x-text="counts.drafts">—</p>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mt-1">{{ __('Documentos') }}</p>
        </div>
    </div>

    {{-- Facturas vendidas hoje --}}
    <div class="bg-white rounded-xl shadow p-4 mb-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-bold text-gray-800">
                <i class="fas fa-receipt text-emerald-600 mr-1"></i>{{ __('Facturas de hoje') }}
            </h2>
            <span class="text-sm font-bold text-emerald-700" x-text="kz(vendas.valor)">—</span>
        </div>

        {{-- O DIA VAZIO OCUPA UMA LINHA, não meio ecrã.

             Estava aqui uma caixa verde grande com "EMITIDAS 0" e, logo por
             baixo, "Ainda não há vendas hoje" — duas maneiras de dizer o mesmo
             nada, e a ocupar mais espaço do que um dia cheio de vendas. De
             manhã, que é quando isto se abre, era o maior elemento do ecrã. --}}
        <template x-if="vendas.total > 0">
            <div class="flex gap-2 mb-3">
                <div class="flex-1 bg-emerald-50 rounded-lg px-3 py-2">
                    <p class="text-[10px] uppercase font-bold text-emerald-700">{{ __('Emitidas') }}</p>
                    <p class="text-lg font-bold text-emerald-800" x-text="vendas.total - vendas.porEmitir">—</p>
                </div>
                {{-- Só aparece quando há alguma por emitir: um zero permanente a
                     vermelho ensina o operador a ignorar o aviso. --}}
                <div x-show="vendas.porEmitir > 0" x-cloak class="flex-1 bg-amber-50 rounded-lg px-3 py-2">
                    <p class="text-[10px] uppercase font-bold text-amber-700">{{ __('Por emitir') }}</p>
                    <p class="text-lg font-bold text-amber-800" x-text="vendas.porEmitir">—</p>
                </div>
            </div>
        </template>

        <template x-if="!vendas.ultimas.length">
            <p class="text-xs text-gray-400">{{ __('Ainda não há vendas hoje.') }}</p>
        </template>

        <ul class="divide-y divide-gray-100">
            <template x-for="v in vendas.ultimas" :key="v.numero">
                <li class="py-2 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold truncate" x-text="v.numero"></p>
                        <p class="text-[11px] text-gray-500 truncate">
                            <span x-text="v.hora"></span> · <span x-text="v.cliente"></span>
                        </p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-xs font-bold" x-text="kz(v.total)"></p>
                        <p class="text-[10px]"
                           :class="v.emitida ? 'text-emerald-600' : 'text-amber-600'"
                           x-text="v.emitida ? '{{ __('emitida') }}' : '{{ __('por emitir') }}'"></p>
                    </div>
                </li>
            </template>
        </ul>
    </div>

    {{-- ============ FERRAMENTAS ============

         ESTAVA TUDO ABERTO NO ECRÃ INICIAL: sete botões técnicos, com o
         "Reset Total — apaga TUDO incl. pendentes" a um toque de distância no
         ecrã que o empregado vê primeiro de manhã. Não é só feio; é uma venda
         por sincronizar à distância de um dedo enganado.

         Estas ferramentas continuam todas cá — servem quando alguma coisa
         corre mal, e é para isso que existem. O que muda é a ordem das coisas:
         quem abre a aplicação para vender vê o que é de vender; quem vem
         resolver um problema abre esta gaveta. --}}
    <details class="mt-4 group bg-white rounded-2xl shadow-sm overflow-hidden">
        <summary class="flex items-center justify-between gap-2 p-4 cursor-pointer list-none select-none">
            <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">
                <i class="fas fa-screwdriver-wrench mr-1.5"></i>{{ __('Ferramentas') }}
            </span>
            <span class="flex items-center gap-2">
                <span x-show="busy" x-cloak class="text-[10px] text-blue-600 font-bold">
                    <i class="fas fa-spinner fa-spin mr-1"></i><span x-text="busyText"></span>
                </span>
                <i class="fas fa-chevron-down text-gray-400 text-xs transition group-open:rotate-180"></i>
            </span>
        </summary>

        <div class="px-4 pb-4 space-y-3 border-t border-gray-100 pt-4">
            {{-- Sincronizar. O cabeçalho já sincroniza no dia-a-dia; a que fica
                 aqui é a COMPLETA, que volta a descarregar o catálogo todo. --}}
            <button @click="forceSync()" :disabled="busy"
                    class="w-full flex items-center gap-3 bg-blue-50 hover:bg-blue-100 text-blue-800 p-3 rounded-xl text-left transition disabled:opacity-50">
                <i class="fas fa-arrows-rotate w-5 text-center"></i>
                <span class="min-w-0">
                    <span class="block text-sm font-bold">{{ __('Sincronização completa') }}</span>
                    <span class="block text-[11px] opacity-70">{{ __('Volta a descarregar o catálogo e os clientes todos') }}</span>
                </span>
            </button>

            {{-- A CÓPIA VEM ANTES DO QUE APAGA, e de propósito: quem chega aqui
                 com um problema deve ver primeiro a forma de salvar o que ainda
                 não subiu. --}}
            <button @click="exportarCopia()" :disabled="busy"
                    class="w-full flex items-center gap-3 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 p-3 rounded-xl text-left transition disabled:opacity-50">
                <i class="fas fa-download w-5 text-center"></i>
                <span class="min-w-0">
                    <span class="block text-sm font-bold">{{ __('Guardar cópia do que falta enviar') }}</span>
                    <span class="block text-[11px] opacity-70">{{ __('Ficheiro para importar no sistema se este aparelho se perder') }}</span>
                </span>
            </button>

            <div class="grid grid-cols-2 gap-2">
                <button @click="forceUpdateApp()" :disabled="busy"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2.5 rounded-xl text-xs font-bold disabled:opacity-50">
                    <i class="fas fa-cloud-arrow-down mr-1"></i>{{ __('Actualizar aplicação') }}
                </button>
                {{-- O diagnóstico do SERVIDOR não vê a fila, que vive no
                     aparelho. Sem ela, uma venda presa é invisível de fora. --}}
                <button type="button" @click="diagnosticar"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2.5 rounded-xl text-xs font-bold">
                    <i class="fas fa-stethoscope mr-1"></i>{{ __('Diagnosticar') }}
                </button>
            </div>

            <p x-show="statusMsg" x-cloak x-text="statusMsg" class="text-[11px] font-bold"
               :class="statusOk ? 'text-emerald-700' : 'text-red-600'"></p>

            {{-- O estado do aparelho, em números. Fica fechado: é para quando
                 alguém pergunta "quantos produtos é que este tablet tem?" --}}
            <button @click="detailsOpen = !detailsOpen"
                    class="w-full text-left text-[11px] font-bold text-gray-500 hover:text-gray-700 pt-1">
                <i class="fas fa-circle-info mr-1"></i><span x-text="detailsOpen ? '{{ __('Ocultar estado do aparelho') }}' : '{{ __('Estado do aparelho') }}'"></span>
            </button>

            <div x-show="detailsOpen" x-cloak class="bg-gray-50 rounded-xl p-3 text-[11px] text-gray-700 space-y-1">
                <p>{{ __('Última sincronização:') }} <strong x-text="lastSyncText"></strong></p>
                <p>{{ __('Artigos:') }} <strong x-text="counts.products"></strong> · {{ __('isentos') }}: <strong x-text="detail.exemptProducts"></strong> · {{ __('com IVA') }}: <strong x-text="detail.taxedProducts"></strong></p>
                <p>{{ __('Clientes:') }} <strong x-text="counts.clients"></strong> (<span x-text="detail.unsyncedClients"></span> {{ __('por enviar') }})</p>
                <p>{{ __('Vendas no aparelho:') }} <strong x-text="detail.posSales"></strong> (<span x-text="detail.unsyncedPosSales"></span> {{ __('por enviar') }})</p>
                <p>{{ __('Fila:') }} <strong x-text="detail.queuePending"></strong> {{ __('à espera') }} · <strong x-text="detail.queueFailed"></strong> {{ __('com erro') }}</p>
                <p>{{ __('Login offline:') }} <strong x-text="detail.offlineAuth"></strong></p>
                <p>{{ __('Turno:') }} <strong x-text="detail.shift"></strong></p>
            </div>

            {{-- ZONA PERIGOSA, e assinalada como tal. Apagar o catálogo ou o
                 aparelho inteiro não pode parecer mais um botão. --}}
            <div class="border-t border-gray-100 pt-3 space-y-2">
                <p class="text-[10px] font-bold text-red-500 uppercase tracking-wide">
                    <i class="fas fa-triangle-exclamation mr-1"></i>{{ __('Apaga dados deste aparelho') }}
                </p>
                <div class="grid grid-cols-2 gap-2">
                    <button @click="clearCatalog()" :disabled="busy"
                            class="border border-amber-200 text-amber-700 hover:bg-amber-50 px-3 py-2.5 rounded-xl text-xs font-bold disabled:opacity-50">
                        {{ __('Limpar catálogo') }}
                    </button>
                    <button @click="resetAll()" :disabled="busy"
                            class="border border-red-200 text-red-700 hover:bg-red-50 px-3 py-2.5 rounded-xl text-xs font-bold disabled:opacity-50">
                        {{ __('Apagar tudo') }}
                    </button>
                </div>
                <p class="text-[10px] text-gray-400">
                    {{ __('"Apagar tudo" leva também o que ainda não foi enviado. Guarde a cópia primeiro.') }}
                </p>
            </div>
        </div>
    </details>
</div>

@push('scripts')
<script>
function pwaHome() {
    return {
        userName: '…',
        userEmail: '',
        companyName: '',
        companyNif: '',
        warehouseName: '',
        // O turno vem do sync e fica no cache local: é preciso saber de quem é
        // a caixa e desde quando, mesmo — sobretudo — sem internet.
        shift: { open: false, number: null, openedAt: null, opening: 0, cash: 0, total: 0 },
        lastSyncText: 'a verificar…',
        counts: { products: 0, clients: 0, drafts: 0, pending: 0 },
        vendas: { total: 0, porEmitir: 0, valor: 0, ultimas: [] },
        fila: [],
        filaErros: 0,
        aEnviar: false,

        quandoTexto(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            return isNaN(d) ? '' : d.toLocaleString('pt-PT', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
        },

        async enviarAgora() {
            if (this.aEnviar) return;
            this.aEnviar = true;
            try {
                await window.SosPwa.sync(true);
            } catch (_) {
            } finally {
                this.aEnviar = false;
                await this.refresh();
            }
        },

        /**
         * Diagnostico dos DOIS lados.
         *
         * O do servidor dizia o catalogo e ficava-se sem saber o que estava
         * preso no aparelho — e e no aparelho que as vendas ficam. Sem a fila
         * ao lado, "3 por enviar" nao se explica de fora.
         */
        async diagnosticar() {
            let servidor = null;

            try {
                servidor = await (await fetch('/api/v1/invoicing/diagnose', { credentials: 'same-origin' })).json();
            } catch (e) {
                servidor = { erro: String(e && e.message || e) };
            }

            const fila = await window.SosPwa.getQueue();
            const vendas = await window.SosPwa.getPosSales();

            const relatorio = {
                servidor,
                aparelho: {
                    online: navigator.onLine,
                    fila_total: fila.length,
                    fila_com_erro: fila.filter(j => j.estado === 'failed').length,
                    fila,
                    vendas_locais: vendas.length,
                    vendas_por_emitir: vendas.filter(v => !v._synced).length,
                },
            };

            const texto = JSON.stringify(relatorio, null, 2);

            try { await navigator.clipboard.writeText(texto); } catch (_) {}

            const j = window.open('', '_blank');
            if (j) {
                j.document.write('<pre style="font:12px monospace;white-space:pre-wrap">'
                    + texto.replace(/</g, '&lt;') + '</pre>');
            } else {
                alert(texto.slice(0, 3000));
            }
        },

        async repetir(id) {
            // Um trabalho com erro fica parado de propósito, para não repetir
            // um pedido que talvez tenha chegado. Quem decide repetir é quem
            // está à caixa e sabe se a venda saiu.
            await window.SosPwa.retryFailedJob(id);
            await this.refresh();
        },
        busy: false,
        busyText: '',
        statusMsg: '',
        statusOk: true,
        detailsOpen: false,
        detail: { exemptProducts: 0, taxedProducts: 0, unsyncedClients: 0, posSales: 0, unsyncedPosSales: 0, queuePending: 0, queueFailed: 0, offlineAuth: '—', shift: '—' },

        async init() {
            await this.refresh();
            window.addEventListener('pwa:synced', () => this.refresh());
        },

        /** Kwanzas como se escrevem em Angola, e sem casas decimais a mais. */
        kz(valor) {
            return new Intl.NumberFormat('pt-AO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(Number(valor) || 0) + ' Kz';
        },

        /**
         * Desde que horas o turno está aberto.
         *
         * A data vem em ISO com fuso, por isso o telemóvel mostra-a na hora
         * dele — que é a de quem está ao balcão. Se vier ilegível, mostra-se
         * nada em vez de "Invalid Date".
         */
        horaDoTurno() {
            if (!this.shift.openedAt) return '';

            const quando = new Date(this.shift.openedAt);
            if (Number.isNaN(quando.getTime())) return '';

            return '{{ __('desde') }} ' + quando.toLocaleTimeString('pt-PT', {
                hour: '2-digit',
                minute: '2-digit',
            });
        },

        async run(label, fn) {
            if (this.busy) return;
            this.busy = true;
            this.busyText = label;
            this.statusMsg = '';
            try {
                await fn();
                this.statusOk = true;
                this.statusMsg = '✓ ' + label + ' concluído';
            } catch (err) {
                console.error(err);
                this.statusOk = false;
                this.statusMsg = '✗ ' + (err.message || 'Erro inesperado');
            } finally {
                this.busy = false;
                this.busyText = '';
                await this.refresh();
            }
        },

        // Sync incremental: envia pendentes + descarrega só alterações desde a última sync
        async partialSync() {
            await this.run('Sync parcial', async () => {
                if (!navigator.onLine) throw new Error('Sem internet — sync adiada');
                await window.SosPwa.sync(false);
            });
        },

        // Sync completa: limpa catálogo e re-descarrega tudo do servidor
        async forceSync() {
            await this.run('Sync completa', async () => {
                if (!navigator.onLine) throw new Error('Sem internet — impossível sync completa');
                await window.SosPwa.db.meta.delete('last_sync');
                await window.SosPwa.db.meta.delete('catalog_version');
                await window.SosPwa.db.products.clear();
                await window.SosPwa.db.clients.where('_synced').equals(1).delete();
                await window.SosPwa.sync(true);
            });
        },

        // Limpa catálogo local (preserva pendentes) — útil quando dados ficam "velhos"
        async clearCatalog() {
            if (!confirm('Limpar produtos e clientes sincronizados do dispositivo?\nDados pendentes de envio são preservados.')) return;
            await this.run('Limpar catálogo', async () => {
                await window.SosPwa.db.products.clear();
                await window.SosPwa.db.clients.where('_synced').equals(1).delete();
                await window.SosPwa.db.series.clear();
                await window.SosPwa.db.tax_rates.clear();
                await window.SosPwa.db.meta.delete('last_sync');
                await window.SosPwa.db.meta.delete('catalog_version');
                if (navigator.onLine) await window.SosPwa.sync(true);
            });
        },

        // Atualizar App: força o browser a descartar o SW + caches de páginas e a
        // buscar a versão mais recente ao servidor. NÃO toca no IndexedDB (dados).
        async forceUpdateApp() {
            if (!navigator.onLine) {
                this.statusOk = false;
                this.statusMsg = '✗ Sem internet — impossível atualizar a app';
                return;
            }
            await this.run('Atualizar app', async () => {
                // 1) Apagar TODOS os caches do Cache Storage (páginas/assets)
                if ('caches' in window) {
                    const keys = await caches.keys();
                    await Promise.all(keys.map(k => caches.delete(k)));
                }
                // 2) Forçar o SW a verificar nova versão; ativar logo se houver waiting
                if ('serviceWorker' in navigator) {
                    const regs = await navigator.serviceWorker.getRegistrations();
                    for (const reg of regs) {
                        try { await reg.update(); } catch (_) {}
                        if (reg.waiting) reg.waiting.postMessage({ type: 'SKIP_WAITING' });
                    }
                }
                // 3) Limpar flag de warmup para re-cachear tudo após reload
                try {
                    Object.keys(localStorage)
                        .filter(k => k.startsWith('soserp-pwa-warmed-'))
                        .forEach(k => localStorage.removeItem(k));
                } catch (_) {}
                // 4) Recarregar com cache-bust — garante HTML fresco do servidor
                setTimeout(() => {
                    window.location.href = window.location.pathname + '?_fresh=' + Date.now();
                }, 600);
            });
        },

        // Reset TOTAL — apaga tudo incluindo vendas/rascunhos não sincronizados
        async exportarCopia() {
            await this.run('Guardar cópia', async () => {
                const r = await window.SosPwa.exportarCopia();
                const c = r.contagens;

                if (!c.fila && !c.vendas && !c.clientes && !c.rascunhos) {
                    alert('Não há nada por sincronizar — a cópia saiu vazia.\n\nIsso é bom sinal: está tudo no servidor.');
                    return;
                }

                alert(
                    'Cópia guardada: ' + r.nome + '\n\n' +
                    c.fila + ' operação(ões) por enviar\n' +
                    c.vendas + ' venda(s)\n' +
                    c.clientes + ' cliente(s)\n' +
                    c.rascunhos + ' rascunho(s)\n\n' +
                    'Guarde este ficheiro. Se este aparelho se perder, importe-o em ' +
                    'Faturação → Importar Cópia Offline.'
                );
            });
        },

        async resetAll() {
            const pending = await window.SosPwa.refreshPendingCount();
            const warn = pending > 0
                ? '⚠️ ATENÇÃO: há ' + pending + ' registo(s) POR SINCRONIZAR que serão PERDIDOS!\n\n'
                : '';
            if (!confirm(warn + 'Apagar TODOS os dados locais do PWA?\nEsta ação não pode ser anulada.')) return;
            if (pending > 0 && !confirm('Confirma mesmo? As vendas/rascunhos pendentes NÃO chegarão ao servidor.')) return;
            await this.run('Reset total', async () => {
                const db = window.SosPwa.db;
                await db.products.clear();
                await db.clients.clear();
                await db.series.clear();
                await db.tax_rates.clear();
                await db.draft_documents.clear();
                await db.pos_sales.clear();
                await db.sync_queue.clear();
                await db.meta.clear();
                try { sessionStorage.clear(); localStorage.removeItem('soserp-pwa-warmed-{{ config('changelog.current', '1.0') }}'); } catch (_) {}
                if (navigator.onLine) await window.SosPwa.sync(true);
            });
        },

        async refresh() {
            const db = window.SosPwa.db;
            const user = await db.meta.get('user');
            this.userName = user?.value?.name || 'Utilizador';
            this.userEmail = user?.value?.email || '';

            const empresa = await db.meta.get('company');
            this.companyName = empresa?.value?.name || '';
            this.companyNif = empresa?.value?.nif || '';

            const armazem = await db.meta.get('warehouse');
            this.warehouseName = armazem?.value?.name || '';

            const turno = await db.meta.get('shift');
            this.shift = {
                open: !!turno?.value?.open,
                number: turno?.value?.number ?? null,
                openedAt: turno?.value?.opened_at ?? null,
                opening: Number(turno?.value?.opening_balance ?? 0),
                cash: Number(turno?.value?.cash_sales ?? 0),
                total: Number(turno?.value?.total_sales ?? 0),
            };

            const last = await db.meta.get('last_sync');
            this.lastSyncText = last ? new Date(last.value).toLocaleString('pt-PT') : 'nunca';

            this.counts.products = await db.products.count();
            this.counts.clients = await db.clients.count();
            this.counts.drafts = await db.draft_documents.count();
            this.counts.pending = await window.SosPwa.refreshPendingCount();

            this.fila = await window.SosPwa.getQueue();
            this.filaErros = this.fila.filter(j => j.estado === 'failed').length;

            // As facturas vendidas HOJE. Quem está ao balcão quer saber o que
            // já vendeu e se está tudo entregue ao servidor — não o número de
            // artigos no catálogo.
            const hoje = new Date().toISOString().slice(0, 10);
            const vendas = (await window.SosPwa.getPosSales())
                .filter(v => String(v.created_at || '').slice(0, 10) === hoje);

            this.vendas = {
                total: vendas.length,
                porEmitir: vendas.filter(v => !v._synced).length,
                valor: vendas.reduce((s, v) => s + (Number(v.total) || 0), 0),
                ultimas: vendas.slice(0, 8).map(v => ({
                    numero: v._synced ? v._server_number : v.provisional_number,
                    emitida: !!v._synced,
                    cliente: v.client_name || '{{ __('Consumidor Final') }}',
                    total: Number(v.total) || 0,
                    hora: String(v.created_at || '').slice(11, 16),
                })),
            };

            // Detalhes (painel de manutenção)
            const products = await db.products.toArray();
            this.detail.exemptProducts = products.filter(p => !(parseFloat(p.tax_rate) > 0)).length;
            this.detail.taxedProducts = products.length - this.detail.exemptProducts;
            this.detail.unsyncedClients = await db.clients.where('_synced').equals(0).count();
            this.detail.posSales = await db.pos_sales.count();
            this.detail.unsyncedPosSales = await db.pos_sales.where('_synced').equals(0).count();
            this.detail.queuePending = await db.sync_queue.where('status').equals('pending').count();
            this.detail.queueFailed = await db.sync_queue.where('status').equals('failed').count();
            const auth = await window.SosPwa.getOfflineAuthInfo?.();
            this.detail.offlineAuth = auth ? (auth.expired ? 'expirado' : 'ativo até ' + new Date(auth.expires_at).toLocaleDateString('pt-PT')) : 'não configurado';
            const shift = await db.meta.get('shift');
            this.detail.shift = shift?.value?.open ? 'aberto (' + (shift.value.number || '—') + ')' : 'fechado';
        },
    };
}
</script>
@endpush
@endsection
