@extends('layouts.pwa', ['title' => 'PWA Faturação — Início'])

@section('content')
<div x-data="pwaHome()" x-init="init()" x-cloak>
    <div class="bg-gradient-to-br from-blue-700 to-indigo-800 text-white rounded-2xl shadow-xl p-5 mb-4">
        <p class="text-xs opacity-80 uppercase font-bold">Olá</p>
        <h1 class="text-xl font-bold" x-text="userName">…</h1>
        <p class="text-sm opacity-90 mt-1">
            <i class="fas fa-clock mr-1"></i>
            Última sincronização: <span x-text="lastSyncText">a verificar…</span>
        </p>
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
            <a href="/api/v1/invoicing/diagnose" target="_blank" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-xl text-xs font-bold text-center">
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
        lastSyncText: 'a verificar…',
        counts: { products: 0, clients: 0, drafts: 0, pending: 0 },
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

            const last = await db.meta.get('last_sync');
            this.lastSyncText = last ? new Date(last.value).toLocaleString('pt-PT') : 'nunca';

            this.counts.products = await db.products.count();
            this.counts.clients = await db.clients.count();
            this.counts.drafts = await db.draft_documents.count();
            this.counts.pending = await window.SosPwa.refreshPendingCount();

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
