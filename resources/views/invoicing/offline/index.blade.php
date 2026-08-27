@extends('layouts.pwa', ['title' => 'PWA Faturação — Início'])

@section('content')
<div x-data="pwaHome()" x-init="init()" x-cloak>
    <div class="bg-gradient-to-br from-blue-700 to-indigo-800 text-white rounded-2xl shadow-xl p-5 mb-4">
        <p class="text-xs opacity-80 uppercase font-bold">{{ __('Olá') }}</p>
        <h1 class="text-xl font-bold" x-text="userName">…</h1>
        <p class="text-xs opacity-75" x-show="userEmail" x-text="userEmail"></p>

        {{-- Empresa e armazém: quem trabalha em mais do que uma empresa precisa
             de saber em qual está ANTES de vender, e não depois. --}}
        <div class="mt-3 pt-3 border-t border-white/20 space-y-1 text-sm">
            <p x-show="companyName">
                <i class="fas fa-building mr-1.5 opacity-75"></i>
                <span class="font-semibold" x-text="companyName"></span>
                <span class="opacity-70 text-xs" x-show="companyNif" x-text="' · NIF ' + companyNif"></span>
            </p>
            <p x-show="warehouseName">
                <i class="fas fa-warehouse mr-1.5 opacity-75"></i>
                <span x-text="warehouseName"></span>
            </p>
            <p class="opacity-90">
                <i class="fas fa-clock mr-1.5 opacity-75"></i>
                {{ __('Última sincronização:') }} <span x-text="lastSyncText">a verificar…</span>
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

            <a href="{{ route('invoicing.offline.pos') }}"
               class="shrink-0 px-3 py-2 rounded-xl text-xs font-bold transition"
               :class="shift.open ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-blue-600 hover:bg-blue-700 text-white'"
               x-text="shift.open ? '{{ __('Ir para o POS') }}' : '{{ __('Abrir turno') }}'"></a>
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

    {{-- KPIs do cache local --}}
    <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-blue-500">
            <p class="text-xs font-bold text-blue-600 uppercase">Produtos em cache</p>
            <p class="text-2xl font-bold mt-1" x-text="counts.products">—</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-cyan-500">
            <p class="text-xs font-bold text-cyan-600 uppercase">Clientes em cache</p>
            <p class="text-2xl font-bold mt-1" x-text="counts.clients">—</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-amber-500">
            <p class="text-xs font-bold text-amber-600 uppercase">Rascunhos locais</p>
            <p class="text-2xl font-bold mt-1" x-text="counts.drafts">—</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-red-500">
            <p class="text-xs font-bold text-red-600 uppercase">Por sincronizar</p>
            <p class="text-2xl font-bold mt-1 text-red-700" x-text="counts.pending">—</p>
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

        <template x-if="!vendas.ultimas.length">
            <p class="text-xs text-gray-400 text-center py-3">{{ __('Ainda não há vendas hoje.') }}</p>
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

    {{-- Ações --}}
    <div class="space-y-3">
        <a href="{{ route('invoicing.offline.pos') }}" class="block bg-gradient-to-r from-orange-500 to-red-600 text-white rounded-xl shadow-lg p-4 hover:shadow-xl transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs opacity-90 uppercase font-bold">Venda rápida</p>
                    <p class="text-lg font-bold">POS — Ponto de Venda</p>
                    <p class="text-xs opacity-80">Emite Fatura-Recibo offline em segundos</p>
                </div>
                <i class="fas fa-cash-register text-3xl opacity-80"></i>
            </div>
        </a>
        {{-- O restaurante só aparece a quem tem o módulo. A verificação é do
             servidor e fica gravada na cópia que o service worker guarda, que
             é a que se vê sem rede. --}}
        @if(auth()->check() && optional(auth()->user()->activeTenant())->hasModule('restaurant'))
            <a href="{{ route('invoicing.offline.restaurant') }}" class="block bg-gradient-to-r from-amber-500 to-orange-600 text-white rounded-xl shadow-lg p-4 hover:shadow-xl transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs opacity-90 uppercase font-bold">{{ __('Sala') }}</p>
                        <p class="text-lg font-bold">{{ __('POS Restaurante') }}</p>
                        <p class="text-xs opacity-80">{{ __('Mesas, comandas e conta — funciona sem rede') }}</p>
                    </div>
                    <i class="fas fa-utensils text-3xl opacity-80"></i>
                </div>
            </a>
        @endif
        <a href="{{ route('invoicing.offline.draft-new') }}" class="block bg-gradient-to-r from-emerald-500 to-green-600 text-white rounded-xl shadow-lg p-4 hover:shadow-xl transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs opacity-90 uppercase font-bold">Novo</p>
                    <p class="text-lg font-bold">Rascunho de Fatura</p>
                    <p class="text-xs opacity-80">Cria rascunho mesmo sem internet</p>
                </div>
                <i class="fas fa-file-circle-plus text-3xl opacity-80"></i>
            </div>
        </a>
        <a href="{{ route('invoicing.offline.client-new') }}" class="block bg-gradient-to-r from-purple-500 to-fuchsia-600 text-white rounded-xl shadow-lg p-4 hover:shadow-xl transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs opacity-90 uppercase font-bold">Novo</p>
                    <p class="text-lg font-bold">Cliente</p>
                    <p class="text-xs opacity-80">Sincroniza automaticamente quando voltar online</p>
                </div>
                <i class="fas fa-user-plus text-3xl opacity-80"></i>
            </div>
        </a>
    </div>

    {{-- Painel de manutenção / sincronização --}}
    <div class="mt-4 bg-white rounded-2xl shadow p-4">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-bold text-gray-600 uppercase"><i class="fas fa-screwdriver-wrench mr-1"></i>Manutenção &amp; Sincronização</p>
            <span x-show="busy" class="text-[10px] text-blue-600 font-bold"><i class="fas fa-spinner fa-spin mr-1"></i><span x-text="busyText"></span></span>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <button @click="partialSync()" :disabled="busy" class="bg-blue-100 hover:bg-blue-200 text-blue-800 px-3 py-2.5 rounded-xl text-xs font-bold disabled:opacity-50">
                <i class="fas fa-rotate mr-1"></i>Sync Parcial
                <span class="block text-[9px] font-normal opacity-70">só alterações recentes</span>
            </button>
            <button @click="forceSync()" :disabled="busy" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-800 px-3 py-2.5 rounded-xl text-xs font-bold disabled:opacity-50">
                <i class="fas fa-arrows-rotate mr-1"></i>Sync Completa
                <span class="block text-[9px] font-normal opacity-70">re-descarrega catálogo todo</span>
            </button>
            <button @click="clearCatalog()" :disabled="busy" class="bg-amber-100 hover:bg-amber-200 text-amber-800 px-3 py-2.5 rounded-xl text-xs font-bold disabled:opacity-50">
                <i class="fas fa-broom mr-1"></i>Limpar Catálogo
                <span class="block text-[9px] font-normal opacity-70">produtos + clientes sincronizados</span>
            </button>
            <button @click="resetAll()" :disabled="busy" class="bg-red-100 hover:bg-red-200 text-red-800 px-3 py-2.5 rounded-xl text-xs font-bold disabled:opacity-50">
                <i class="fas fa-triangle-exclamation mr-1"></i>Reset Total
                <span class="block text-[9px] font-normal opacity-70">apaga TUDO incl. pendentes</span>
            </button>
        </div>

        {{-- A cópia fica LOGO A SEGUIR ao Reset Total, e de propósito: é o
             botão que faz perder tudo, e quem lá chega deve ver primeiro a
             forma de salvar o que ainda não foi enviado. --}}
        <div class="mt-2">
            <button @click="exportarCopia()" :disabled="busy"
                    class="w-full bg-emerald-100 hover:bg-emerald-200 text-emerald-800 px-3 py-2.5 rounded-xl text-xs font-bold disabled:opacity-50">
                <i class="fas fa-download mr-1"></i>Guardar cópia do que falta enviar
                <span class="block text-[9px] font-normal opacity-70">
                    ficheiro para importar no sistema se este aparelho se perder
                </span>
            </button>
        </div>
        <div class="mt-2 grid grid-cols-3 gap-2">
            <button @click="forceUpdateApp()" :disabled="busy" class="bg-purple-100 hover:bg-purple-200 text-purple-800 px-3 py-2 rounded-xl text-xs font-bold disabled:opacity-50">
                <i class="fas fa-cloud-arrow-down mr-1"></i>Atualizar App
            </button>
            {{-- O diagnostico do servidor nao ve a fila, que vive no aparelho.
                 Sem ela, uma venda presa e invisivel de fora. --}}
            <button type="button" @click="diagnosticar" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-xl text-xs font-bold text-center">
                <i class="fas fa-stethoscope mr-1"></i>Diagnosticar
            </button>
            <a href="/api/v1/invoicing/diagnose" target="_blank" class="hidden">
                <i class="fas fa-stethoscope mr-1"></i>Diagnosticar
            </a>
            <button @click="detailsOpen = !detailsOpen" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-xl text-xs font-bold">
                <i class="fas fa-circle-info mr-1"></i><span x-text="detailsOpen ? 'Ocultar' : 'Detalhes'"></span>
            </button>
        </div>
        <p class="mt-2 text-[10px] text-gray-400 text-center">Versão instalada: <strong>{{ config('changelog.current', '—') }}</strong></p>
        <p x-show="statusMsg" x-text="statusMsg" class="mt-2 text-[11px] font-bold" :class="statusOk ? 'text-emerald-700' : 'text-red-600'"></p>

        {{-- Detalhes do cache local --}}
        <div x-show="detailsOpen" x-cloak class="mt-3 bg-gray-50 rounded-xl p-3 text-[11px] text-gray-700 space-y-1 font-mono">
            <p>Última sync: <strong x-text="lastSyncText"></strong></p>
            <p>Produtos: <strong x-text="counts.products"></strong> · Isentos (0%): <strong x-text="detail.exemptProducts"></strong> · Com IVA: <strong x-text="detail.taxedProducts"></strong></p>
            <p>Clientes: <strong x-text="counts.clients"></strong> (<span x-text="detail.unsyncedClients"></span> por sincronizar)</p>
            <p>Vendas POS locais: <strong x-text="detail.posSales"></strong> (<span x-text="detail.unsyncedPosSales"></span> por sincronizar)</p>
            <p>Fila de sync: <strong x-text="detail.queuePending"></strong> pendentes · <strong x-text="detail.queueFailed"></strong> com erro</p>
            <p>Login offline: <strong x-text="detail.offlineAuth"></strong></p>
            <p>Turno: <strong x-text="detail.shift"></strong></p>
        </div>
    </div>

    <div class="mt-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg text-xs text-blue-900">
        <p class="font-bold mb-1"><i class="fas fa-circle-info mr-1"></i>Como funciona o modo PWA</p>
        <ul class="list-disc ml-5 space-y-1">
            <li>Catálogo e clientes são guardados no dispositivo</li>
            <li>Rascunhos de fatura criados offline são <strong>enviados ao servidor</strong> ao reconectar</li>
            <li>A <strong>numeração fiscal e hash AGT</strong> só são atribuídos no servidor (online)</li>
            <li>Rascunhos não têm validade fiscal até serem finalizados</li>
        </ul>
    </div>
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
