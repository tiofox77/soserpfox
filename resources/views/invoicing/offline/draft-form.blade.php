@extends('layouts.pwa', ['title' => 'Novo Documento'])

@section('content')
<div x-data="draftForm()" x-init="init()" x-cloak>
    <div class="mb-4 flex items-center gap-3">
        <a href="{{ route('invoicing.offline.drafts') }}" class="w-9 h-9 bg-white rounded-lg shadow flex items-center justify-center text-gray-600 hover:bg-gray-50">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900">Novo Documento</h1>
            <p class="text-xs text-gray-500">Documento · emitido ao sincronizar</p>
        </div>
    </div>

    {{-- Tipo de Documento --}}
    <div class="bg-white rounded-2xl shadow p-3 mb-3">
        <label class="block text-xs font-bold text-gray-600 uppercase mb-2 px-1">Tipo de Documento</label>
        <div class="grid grid-cols-2 gap-2">
            <template x-for="dt in docTypes" :key="dt.code">
                <button type="button" @click="form.doc_type = dt.code"
                    :class="form.doc_type === dt.code ? dt.activeClass : 'bg-white text-gray-600 border-gray-200'"
                    class="border-2 rounded-xl py-2.5 text-xs font-bold transition">
                    <i :class="dt.icon" class="block mb-0.5"></i>
                    <span x-text="dt.label"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- Cliente --}}
    <div class="bg-white rounded-2xl shadow p-3 mb-3">
        <label class="block text-xs font-bold text-gray-600 uppercase mb-2 px-1">Cliente</label>
        <button type="button" @click="showClientPicker = true"
                class="w-full text-left px-3 py-3 border-2 border-gray-200 rounded-xl flex items-center justify-between hover:border-blue-500">
            <div>
                <p class="font-semibold text-sm" x-text="selectedClient ? selectedClient.name : 'Selecionar cliente...'"></p>
                <p class="text-xs text-gray-500" x-show="selectedClient?.nif" x-text="'NIF: ' + (selectedClient?.nif || '')"></p>
            </div>
            <i class="fas fa-chevron-right text-gray-400"></i>
        </button>
        <p x-show="!selectedClient" class="text-[10px] text-gray-400 mt-1 px-1">Cliente é opcional para Consumidor Final</p>
    </div>

    {{-- Referência (para NC) --}}
    <div x-show="form.doc_type === 'NC'" class="bg-white rounded-2xl shadow p-3 mb-3">
        <label class="block text-xs font-bold text-gray-600 uppercase mb-2 px-1">Nº da Fatura Original <span class="text-red-500">*</span></label>
        <input x-model="form.reference" type="text" placeholder="Ex: FT 2025/123"
               class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-blue-500 focus:outline-none">
        <p class="text-[10px] text-amber-700 mt-1 px-1"><i class="fas fa-circle-info mr-1"></i>Será associada à fatura no momento da finalização</p>
    </div>

    {{-- Datas --}}
    <div class="bg-white rounded-2xl shadow p-3 mb-3 grid grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Data</label>
            <input x-model="form.invoice_date" type="date" class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl text-sm">
        </div>
        <div x-show="form.doc_type !== 'FR'">
            <label class="block text-xs font-bold text-gray-600 uppercase mb-1" x-text="form.doc_type === 'proforma' ? 'Válido até' : 'Vencimento'"></label>
            <input x-model="form.due_date" type="date" class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl text-sm">
        </div>
    </div>

    {{-- Itens --}}
    <div class="bg-white rounded-2xl shadow p-3 mb-3">
        <div class="flex items-center justify-between mb-2 px-1">
            <label class="text-xs font-bold text-gray-600 uppercase">Itens (<span x-text="form.items.length"></span>)</label>
            <button type="button" @click="showProductPicker = true" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold">
                <i class="fas fa-plus mr-1"></i>Adicionar
            </button>
        </div>
        <div class="space-y-2">
            <template x-for="(itm, idx) in form.items" :key="idx">
                <div class="border-2 border-gray-100 rounded-xl p-3">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <p class="font-semibold text-sm flex-1" x-text="itm.product_name"></p>
                        <button type="button" @click="form.items.splice(idx, 1)" class="text-red-500 hover:text-red-700 text-xs"><i class="fas fa-trash"></i></button>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase">Qtd</label>
                            <input x-model.number="itm.quantity" type="number" min="0.01" step="0.01" class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase">Preço</label>
                            <input x-model.number="itm.unit_price" type="number" min="0" step="0.01" class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase">IVA %</label>
                            {{-- As taxas são as DA EMPRESA, sincronizadas.
                                 Estavam aqui fixas (0/5/7/14) e o servidor mandava
                                 a mesma lista a toda a gente: numa empresa em não
                                 sujeição, os 14% ficavam a um toque de distância. --}}
                            <select x-model.number="itm.tax_rate" class="w-full px-1 py-1.5 border border-gray-200 rounded-lg text-sm">
                                <template x-for="t in taxas" :key="t.rate">
                                    <option :value="t.rate" x-text="t.label"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                    <p class="text-right text-xs text-gray-600 mt-1">
                        Subtotal: <strong x-text="formatMoney(itm.quantity * itm.unit_price)"></strong>
                        · IVA: <span x-text="formatMoney(itm.quantity * itm.unit_price * itm.tax_rate / 100)"></span>
                    </p>
                </div>
            </template>
            <div x-show="!form.items.length" class="text-center py-6 text-gray-400 italic text-sm">
                Sem itens. Toca em "Adicionar"
            </div>
        </div>
    </div>

    {{-- Notas --}}
    <div class="bg-white rounded-2xl shadow p-3 mb-3">
        <label class="block text-xs font-bold text-gray-600 uppercase mb-1 px-1">Notas</label>
        <textarea x-model="form.notes" rows="2" maxlength="2000"
                  class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl text-sm focus:border-blue-500 focus:outline-none"
                  placeholder="Observações..."></textarea>
    </div>

    {{-- Totais --}}
    <div class="bg-gradient-to-br from-blue-600 to-indigo-700 text-white rounded-2xl shadow-lg p-4 mb-3">
        <div class="flex justify-between text-sm opacity-90"><span>Subtotal</span><span x-text="formatMoney(totals.subtotal)"></span></div>
        <div class="flex justify-between text-sm opacity-90"><span>IVA</span><span x-text="formatMoney(totals.tax)"></span></div>
        <div class="border-t border-white/30 mt-2 pt-2 flex justify-between text-lg font-bold"><span>TOTAL</span><span x-text="formatMoney(totals.total)"></span></div>
    </div>

    <div x-show="!online" class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded-lg text-xs text-amber-900 mb-3">
        <i class="fas fa-wifi-slash mr-1"></i>
        <strong>Sem conexão.</strong> Será guardado e enviado quando voltar online.
    </div>

    <div class="bg-orange-50 border-l-4 border-orange-500 p-3 rounded-lg text-xs text-orange-900 mb-3">
        <i class="fas fa-circle-exclamation mr-1"></i>
        <strong>Documento definitivo.</strong> Ao sincronizar, o documento é emitido com número fiscal e hash. Sem rede fica em fila e sobe assim que houver ligação.
    </div>

    <div class="flex gap-3 pb-4">
        <a href="{{ route('invoicing.offline.drafts') }}" class="flex-1 text-center py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-bold text-sm hover:bg-gray-50">
            Cancelar
        </a>
        <button type="button" @click="save()" :disabled="saving || !canSave"
                class="flex-1 bg-gradient-to-r from-emerald-500 to-green-600 text-white py-3 rounded-xl font-bold text-sm shadow-lg disabled:opacity-50">
            <i class="fas fa-save mr-1" x-show="!saving"></i>
            <i class="fas fa-spinner fa-spin mr-1" x-show="saving"></i>
            <span x-text="saving ? 'A guardar...' : 'Emitir Documento'"></span>
        </button>
    </div>

    {{-- Modal selector de cliente --}}
    <div x-show="showClientPicker" @click.self="showClientPicker = false" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center" x-transition>
        <div class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl max-h-[80vh] flex flex-col" @click.stop>
            <div class="p-3 border-b flex items-center gap-2">
                <input x-model="clientSearch" type="search" placeholder="Pesquisar cliente..." class="flex-1 px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:outline-none" autofocus>
                <button @click="showClientPicker = false" class="text-gray-400 text-2xl px-1">&times;</button>
            </div>
            <div class="overflow-y-auto flex-1 p-2 space-y-1">
                <button type="button" @click="selectClient(null); showClientPicker = false" class="w-full text-left p-3 hover:bg-blue-50 rounded-lg">
                    <p class="font-semibold text-sm">Consumidor Final</p>
                    <p class="text-xs text-gray-500">Sem cliente identificado</p>
                </button>
                <template x-for="c in filteredClients" :key="c.id">
                    <button type="button" @click="selectClient(c); showClientPicker = false" class="w-full text-left p-3 hover:bg-blue-50 rounded-lg border-t">
                        <p class="font-semibold text-sm" x-text="c.name"></p>
                        <p class="text-xs text-gray-500" x-show="c.nif" x-text="'NIF: ' + c.nif"></p>
                    </button>
                </template>
                <div x-show="!filteredClients.length && clientSearch" class="text-center py-6 text-gray-400 text-sm italic">Nenhum encontrado</div>
            </div>
            <a href="{{ route('invoicing.offline.client-new') }}" class="block p-3 border-t bg-emerald-50 text-emerald-700 text-center font-bold text-sm">
                <i class="fas fa-user-plus mr-1"></i>Criar novo cliente
            </a>
        </div>
    </div>

    {{-- Modal selector de produto --}}
    <div x-show="showProductPicker" @click.self="showProductPicker = false" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center" x-transition>
        <div class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl max-h-[80vh] flex flex-col" @click.stop>
            <div class="p-3 border-b flex items-center gap-2">
                <input x-model="productSearch" type="search" placeholder="Pesquisar produto..." class="flex-1 px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:outline-none" autofocus>
                <button @click="showProductPicker = false" class="text-gray-400 text-2xl px-1">&times;</button>
            </div>
            <div class="overflow-y-auto flex-1 p-2 space-y-1">
                <template x-for="p in filteredProducts" :key="p.id">
                    <button type="button" @click="addItem(p); showProductPicker = false" class="w-full text-left p-3 hover:bg-emerald-50 rounded-lg border-b last:border-b-0">
                        <div class="flex justify-between items-start gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm truncate" x-text="p.name"></p>
                                <p class="text-xs text-gray-500" x-show="p.sku" x-text="'SKU: ' + p.sku"></p>
                            </div>
                            <p class="font-bold text-blue-700 text-sm whitespace-nowrap" x-text="formatMoney(p.price)"></p>
                        </div>
                    </button>
                </template>
                <div x-show="!filteredProducts.length" class="text-center py-6 text-gray-400 text-sm italic">Nenhum produto encontrado</div>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div x-show="successMsg" x-transition class="fixed bottom-24 inset-x-3 z-50 bg-emerald-600 text-white px-4 py-3 rounded-xl shadow-2xl text-sm">
        <i class="fas fa-check-circle mr-2"></i><span x-text="successMsg"></span>
    </div>
</div>

@push('scripts')
<script>
function draftForm() {
    return {
        online: navigator.onLine,
        saving: false,
        successMsg: '',
        showClientPicker: false,
        showProductPicker: false,
        clientSearch: '',
        productSearch: '',
        allClients: [],
        allProducts: [],
        selectedClient: null,

        docTypes: [
            { code: 'FT', label: 'Fatura', icon: 'fas fa-file-invoice', activeClass: 'bg-blue-600 text-white border-blue-600' },
            { code: 'FR', label: 'Fatura-Recibo', icon: 'fas fa-receipt', activeClass: 'bg-emerald-600 text-white border-emerald-600' },
            { code: 'proforma', label: 'Proforma', icon: 'fas fa-file-lines', activeClass: 'bg-amber-600 text-white border-amber-600' },
            // A Nota de Credito saiu daqui: nao e um documento de venda, e o
            // servidor escrevia-a na tabela das VENDAS — nascia uma factura que
            // nao estornava nada e contava como receita. As notas de credito
            // fazem-se no sistema online, contra o documento original.
        ],

        form: {
            doc_type: 'FT',
            client_id: null,
            client_local_uuid: null,
            client_name: '',
            invoice_date: new Date().toISOString().slice(0, 10),
            due_date: new Date(Date.now() + 30*86400000).toISOString().slice(0, 10),
            reference: '',
            notes: '',
            items: [],
        },

        /** As taxas da empresa, sincronizadas. Nunca uma lista inventada. */
        taxas: [],

        async init() {
            await this.loadCatalog();
            // Recarrega quando o catálogo sincroniza (evita ter de fazer refresh manual)
            window.addEventListener('pwa:synced', () => this.loadCatalog());
            // Rede de segurança: catálogo vazio mas online → força sync e recarrega
            if (navigator.onLine && !this.allProducts.length) {
                try { await window.SosPwa.sync(true); }
                catch (e) { console.error('[Documentos] sync inicial falhou', e); }
                await this.loadCatalog();
            }
        },

        async loadCatalog() {
            this.allProducts = await window.SosPwa.db.products.toArray();
            this.allClients = await window.SosPwa.db.clients.toArray();

            const taxas = await window.SosPwa.db.tax_rates.toArray();

            // Sem taxas sincronizadas fica SÓ a isenta. Oferecer 14% a quem
            // ainda não sincronizou é adivinhar o regime da empresa — e
            // adivinhar a favor do imposto é o pior lado para errar.
            this.taxas = taxas.length
                ? taxas
                : [{ rate: 0, label: @json(__('Isento (0%)')) }];
        },

        get filteredProducts() {
            const s = this.productSearch.toLowerCase().trim();
            const list = !s ? this.allProducts : this.allProducts.filter(p =>
                (p.name || '').toLowerCase().includes(s) ||
                (p.sku || '').toLowerCase().includes(s) ||
                (p.barcode || '').toLowerCase().includes(s));
            return list.slice(0, 100);
        },

        get filteredClients() {
            const s = this.clientSearch.toLowerCase().trim();
            const list = !s ? this.allClients : this.allClients.filter(c =>
                (c.name || '').toLowerCase().includes(s) ||
                (c.nif || '').toLowerCase().includes(s));
            return list.slice(0, 100);
        },

        get totals() {
            let subtotal = 0, tax = 0;
            for (const i of this.form.items) {
                const qty = parseFloat(i.quantity) || 0;
                const price = parseFloat(i.unit_price) || 0;
                const taxRate = parseFloat(i.tax_rate) || 0;
                const net = qty * price;
                subtotal += net;
                tax += net * taxRate / 100;
            }
            return { subtotal, tax, total: subtotal + tax };
        },

        get canSave() {
            if (!this.form.items.length) return false;
            if (this.form.doc_type === 'NC' && !this.form.reference.trim()) return false;
            return true;
        },

        selectClient(c) {
            if (!c) {
                this.selectedClient = null;
                this.form.client_id = null;
                this.form.client_local_uuid = null;
                this.form.client_name = 'Consumidor Final';
                return;
            }
            this.selectedClient = c;
            this.form.client_name = c.name;
            // Se ID for numérico (já sincronizado) usa como client_id, senão local_uuid
            if (Number.isInteger(c.id)) {
                this.form.client_id = c.id;
                this.form.client_local_uuid = null;
            } else {
                this.form.client_id = null;
                this.form.client_local_uuid = c.local_uuid;
            }
        },

        addItem(p) {
            this.form.items.push({
                product_id: Number.isInteger(p.id) ? p.id : null,
                product_name: p.name,
                quantity: 1,
                unit_price: p.price || 0,
                // NÃO usar "|| 14": 0% (isento) é falsy e viraria 14%.
                tax_rate: Number.isFinite(parseFloat(p.tax_rate)) ? parseFloat(p.tax_rate) : 0,
                discount_percent: 0,
            });
        },

        formatMoney(v) {
            return new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v || 0);
        },

        async save() {
            if (this.saving || !this.canSave) return;
            this.saving = true;
            try {
                await window.SosPwa.createDraftOffline({ ...this.form });
                this.successMsg = navigator.onLine
                    ? 'Documento guardado — a emitir no servidor…'
                    : 'Documento guardado. Sai emitido assim que houver rede.';
                setTimeout(() => {
                    window.location.href = '{{ route("invoicing.offline.drafts") }}';
                }, 1200);
            } catch (err) {
                console.error(err);
                alert('Erro: ' + err.message);
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
@endsection
