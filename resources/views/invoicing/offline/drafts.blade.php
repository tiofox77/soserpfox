@extends('layouts.pwa', ['title' => 'Documentos Locais'])

@section('content')
<div x-data="draftsList()" x-init="init()" x-cloak>
    {{-- Cabeçalho --}}
    <div class="bg-gradient-to-br from-blue-700 to-indigo-800 text-white rounded-2xl shadow-lg p-4 mb-3">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold flex items-center gap-2"><i class="fas fa-file-invoice"></i>Documentos</h1>
                <p class="text-xs opacity-90 mt-0.5"><span x-text="drafts.length"></span> total · <span class="font-bold" x-text="pendingCount"></span> por sincronizar</p>
            </div>
            <a href="{{ route('invoicing.offline.draft-new') }}" class="bg-white/20 hover:bg-white/30 backdrop-blur px-3 py-2 rounded-xl text-sm font-bold transition">
                <i class="fas fa-plus mr-1"></i>Novo
            </a>
        </div>
    </div>

    {{-- Filtros por tipo --}}
    <div class="sticky top-14 z-30 bg-slate-50/95 backdrop-blur flex gap-2 mb-3 overflow-x-auto py-2 text-xs no-scrollbar">
        <button @click="typeFilter = 'all'" :class="typeFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600'" class="px-3.5 py-2 rounded-full font-semibold shadow-sm whitespace-nowrap">Todos</button>
        <button @click="typeFilter = 'FT'" :class="typeFilter === 'FT' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600'" class="px-3.5 py-2 rounded-full font-semibold shadow-sm whitespace-nowrap">Faturas</button>
        <button @click="typeFilter = 'FR'" :class="typeFilter === 'FR' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600'" class="px-3.5 py-2 rounded-full font-semibold shadow-sm whitespace-nowrap">FR</button>
        <button @click="typeFilter = 'proforma'" :class="typeFilter === 'proforma' ? 'bg-amber-600 text-white' : 'bg-white text-gray-600'" class="px-3.5 py-2 rounded-full font-semibold shadow-sm whitespace-nowrap">Proformas</button>
        {{-- O FILTRO "NC" SAIU, porque o PWA não faz notas de crédito.

             O formulário deixou de as oferecer há muito — o servidor escrevia-as
             na tabela das VENDAS e nascia uma factura que não estornava nada.
             O filtro ficou para trás e prometia uma lista que nunca pode ter
             nada: quem lá tocava concluía que as suas notas de crédito se
             tinham perdido. O rótulo continua no mapa de tipos, para uma NC
             antiga vinda do servidor continuar a mostrar-se com o nome certo. --}}
    </div>

    <div class="space-y-2">
        <template x-for="d in filtered" :key="d.local_uuid">
            <div class="bg-white rounded-xl shadow p-3">
                <div class="flex items-start justify-between gap-2 mb-1.5">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                              :class="badgeClass(d.doc_type)" x-text="docTypeLabel(d.doc_type)"></span>
                        <span x-show="d._synced" class="inline-flex items-center gap-1 text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold">
                            <i class="fas fa-check"></i>Sync
                        </span>
                        <span x-show="!d._synced" class="inline-flex items-center gap-1 text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-bold">
                            <i class="fas fa-clock"></i>Pendente
                        </span>
                    </div>
                    <button @click="remove(d)" class="text-red-400 hover:text-red-600 text-xs"><i class="fas fa-trash"></i></button>
                </div>
                <p class="font-semibold text-sm" x-text="d.client_name || 'Consumidor Final'"></p>
                {{-- O que se passa com ele, quando não é «à espera de rede».
                     Um documento que o servidor recusou dizia «Pendente» para
                     sempre; o motivo ficava na fila, onde ninguém olha. --}}
                <div x-show="d._estado_fila === 'failed'" x-cloak
                     class="mt-1.5 rounded-lg bg-red-50 border border-red-200 px-2.5 py-2 text-[11px] text-red-800">
                    <p class="font-bold"><i class="fas fa-triangle-exclamation mr-1"></i>Não foi aceite pelo servidor</p>
                    <p class="mt-0.5 break-words" x-text="d._erro || ''"></p>
                    <button type="button" @click="tentarDeNovo()"
                            class="mt-1.5 inline-flex items-center gap-1 rounded-md bg-red-600 px-2.5 py-1 text-[11px] font-bold text-white">
                        <i class="fas fa-rotate-right"></i>Tentar outra vez
                    </button>
                </div>
                <p x-show="d._estado_fila === 'pending' && d._erro" x-cloak
                   class="mt-1 text-[10px] text-amber-700" x-text="'Última tentativa: ' + d._erro"></p>
                <div class="flex justify-between items-end mt-1">
                    <div>
                        <p class="text-xs text-gray-500" x-text="formatDate(d.created_at)"></p>
                        <p class="text-xs text-gray-500" x-text="(d.items?.length || 0) + ' itens'"></p>
                        <p class="text-[10px] text-emerald-700 font-bold" x-show="d._server_number" x-text="'Nº: ' + d._server_number"></p>
                    </div>
                    <p class="text-lg font-bold text-blue-700" x-text="formatMoney(d.total)"></p>
                </div>
                {{-- Imprimir, com ou sem rede — como o talão do POS. Com rede
                     e por sincronizar, espera uns segundos pelo número fiscal;
                     sem rede sai já, com a faixa de PROVISÓRIO. --}}
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <button @click="imprimir(d)" :disabled="printing === d.local_uuid"
                            class="rounded-lg bg-slate-800 py-2 text-xs font-bold text-white disabled:opacity-50">
                        <span x-show="printing !== d.local_uuid"><i class="fas fa-print mr-1"></i>{{ __('Imprimir') }}</span>
                        <span x-show="printing === d.local_uuid"><i class="fas fa-spinner fa-spin mr-1"></i>{{ __('A obter o número…') }}</span>
                    </button>
                    {{-- Em PDF, para o WhatsApp: sem rede faz-se no aparelho;
                         emitido e com rede, vai o PDF do servidor. --}}
                    <button @click="partilhar(d)" :disabled="partilhando === d.local_uuid" data-ensaio="partilhar-pdf"
                            class="rounded-lg bg-teal-700 py-2 text-xs font-bold text-white disabled:opacity-50">
                        <span x-show="partilhando !== d.local_uuid"><i class="fas fa-file-pdf mr-1"></i>{{ __('PDF · WhatsApp') }}</span>
                        <span x-show="partilhando === d.local_uuid"><i class="fas fa-spinner fa-spin mr-1"></i>{{ __('A gerar o PDF…') }}</span>
                    </button>
                </div>
            </div>
        </template>
        <div x-show="!filtered.length" class="text-center py-16 text-gray-400 italic text-sm">
            <i class="fas fa-inbox text-4xl mb-2 block"></i>
            Nenhum documento ainda.<br>Toca em "+ Novo" para começar.
        </div>
    </div>

    <div class="mt-6 bg-blue-50 border-l-4 border-blue-500 p-3 rounded-lg text-[11px] text-blue-900">
        <p class="font-bold mb-1"><i class="fas fa-circle-info mr-1"></i>O que acontece a seguir</p>
        Os documentos sobem já emitidos, com número fiscal e hash.
        Para emitir com validade fiscal, abre o documento no servidor e usa o botão <strong>"Finalizar"</strong> para obter o número AGT.
    </div>
</div>

@push('scripts')
<script>
function draftsList() {
    return {
        drafts: [],
        typeFilter: 'all',
        pendingCount: 0,
        printing: null,

        async init() {
            await this.refresh();
            window.addEventListener('pwa:synced', () => this.refresh());
        },

        async refresh() {
            this.drafts = await window.SosPwa.getDrafts();
            this.pendingCount = this.drafts.filter(d => !d._synced).length;
        },

        partilhando: null,

        async partilhar(d) {
            if (this.partilhando) return;
            this.partilhando = d.local_uuid;
            try {
                const r = await window.SosPwa.partilharPdf('documento', d.local_uuid);
                if (r.modo === 'descarregado') alert('PDF descarregado — anexe-o na conversa.');
            } catch (e) {
                if (e && e.name === 'AbortError') return;
                alert('Não foi possível gerar o PDF: ' + e.message);
            } finally {
                this.partilhando = null;
            }
        },

        /** Repõe os trabalhos falhados na fila e sincroniza — o sync(true) já o faz. */
        async tentarDeNovo() {
            try { await window.SosPwa.sync(true); } catch (_) {}
            await this.refresh();
        },

        get filtered() {
            if (this.typeFilter === 'all') return this.drafts;
            return this.drafts.filter(d => d.doc_type === this.typeFilter);
        },

        docTypeLabel(t) {
            return { FT: 'Fatura', FR: 'Fat-Recibo', NC: 'Nota Crédito', proforma: 'Proforma' }[t] || t;
        },

        badgeClass(t) {
            return {
                FT: 'bg-blue-100 text-blue-700',
                FR: 'bg-emerald-100 text-emerald-700',
                NC: 'bg-red-100 text-red-700',
                proforma: 'bg-amber-100 text-amber-700',
            }[t] || 'bg-gray-100 text-gray-700';
        },

        formatMoney(v) {
            return new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v || 0);
        },

        formatDate(iso) {
            if (!iso) return '';
            return new Date(iso).toLocaleString('pt-PT', { dateStyle: 'short', timeStyle: 'short' });
        },

        async remove(d) {
            if (!confirm('Apagar este documento da lista local? Se já foi emitido, continua no servidor — um documento fiscal não se apaga.')) return;
            await window.SosPwa.db.draft_documents.where('local_uuid').equals(d.local_uuid).delete();
            await this.refresh();
        },

        async imprimir(d) {
            this.printing = d.local_uuid;
            try {
                // O motor espera pelo número fiscal quando há rede; sem rede
                // imprime já com a faixa de provisório. A lista refresca-se
                // porque a espera pode ter trazido o número.
                await window.SosPwa.imprimirDocumento(d.local_uuid);
                await this.refresh();
            } catch (e) {
                alert(e.message);
            } finally {
                this.printing = null;
            }
        },
    };
}
</script>
@endpush
@endsection
