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
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase">Qtd</label>
                            <input x-model.number="itm.quantity" type="number" min="0.01" step="0.01" class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase">Preço</label>
                            <input x-model.number="itm.unit_price" type="number" min="0" step="0.01" class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase">Desc %</label>
                            <input x-model.number="itm.discount_percent" type="number" min="0" max="100" step="0.01"
                                   class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm">
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

                    {{-- IEC e Imposto de Selo.

                         O que se escolhe aqui é o CÓDIGO, não o valor: quem
                         apura é o servidor. Um valor calculado no aparelho
                         daria dois apuramentos do mesmo imposto no documento,
                         e a AGT recusa-o. --}}
                    <div class="grid grid-cols-2 gap-2 mt-2" x-show="pautais.length || verbas.length" x-cloak>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase">+ IEC</label>
                            <select x-model="itm.iec_pautal" class="w-full px-1 py-1.5 border border-gray-200 rounded-lg text-xs">
                                <option :value="null">{{ __('Sem IEC') }}</option>
                                <template x-for="p in pautais" :key="p.pautal_code">
                                    <option :value="p.pautal_code"
                                            x-text="p.pautal_code + ' · ' + p.description + ' (' + p.rate_percentage + '%)'"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase">+ Selo</label>
                            <select x-model="itm.is_verba" class="w-full px-1 py-1.5 border border-gray-200 rounded-lg text-xs">
                                <option :value="null">{{ __('Sem selo') }}</option>
                                <template x-for="v in verbas" :key="v.verba_no">
                                    <option :value="v.verba_no"
                                            x-text="v.verba_no + ' · ' + v.description"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <p class="text-right text-xs text-gray-600 mt-1">
                        Subtotal: <strong x-text="formatMoney(liquidoDaLinha(itm))"></strong>
                        · IVA: <span x-text="formatMoney(liquidoDaLinha(itm) * itm.tax_rate / 100)"></span>
                    </p>
                </div>
            </template>
            <div x-show="!form.items.length" class="text-center py-6 text-gray-400 italic text-sm">
                Sem itens. Toca em "Adicionar"
            </div>
        </div>
    </div>

    {{-- Descontos do documento e entrega.

         O comercial incide ANTES do IVA e baixa o imposto; o financeiro
         incide DEPOIS e não lhe toca. Quem apura é o servidor — aqui só se
         escrevem os valores, para o total no ecrã bater com o do documento. --}}
    <div class="bg-white rounded-2xl shadow p-3 mb-3">
        <p class="text-xs font-bold text-gray-500 uppercase mb-2">{{ __('Descontos') }}</p>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase">{{ __('Comercial (antes do IVA)') }}</label>
                <input x-model.number="form.discount_commercial" type="number" min="0" step="0.01"
                       class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase">{{ __('Financeiro (após IVA)') }}</label>
                <input x-model.number="form.discount_financial" type="number" min="0" step="0.01"
                       class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow p-3 mb-3">
        <p class="text-xs font-bold text-gray-500 uppercase mb-2">{{ __('Entrega') }}</p>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase">{{ __('Data') }}</label>
                <input x-model="form.delivery_date" type="date"
                       class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase">{{ __('Local') }}</label>
                <input x-model="form.delivery_location" type="text" placeholder="{{ __('Local de entrega dos bens') }}"
                       class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm">
            </div>
        </div>
    </div>

    {{-- Retenção na fonte.

         Só existe em prestação de serviços — uma venda de mercadoria não
         retém IRT. E não é imposto do documento: é dinheiro que o cliente
         entrega ao Estado em vez de o entregar a quem factura, por isso
         baixa o total a receber e não mexe no IVA. --}}
    <div class="bg-white rounded-2xl shadow p-3 mb-3">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" x-model="form.is_service" class="w-4 h-4 rounded border-gray-300">
            <span class="text-sm font-semibold text-gray-700">{{ __('É prestação de serviço') }}</span>
        </label>

        <div x-show="form.is_service" x-cloak class="mt-2">
            <label class="block text-[10px] font-bold text-gray-500 uppercase">{{ __('Retenção %') }}</label>
            <input x-model.number="form.withholding_percentage" type="number" min="0" max="100" step="0.01"
                   class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm">
            <p class="text-[11px] text-gray-400 mt-1">
                {{ __('6,5% é a taxa corrente do IRT sobre serviços. Deixe assim se não souber.') }}
            </p>
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
        <div x-show="totals.retencao > 0" x-cloak class="flex justify-between text-sm opacity-90"><span>{{ __('Retenção na fonte') }}</span><span x-text='"-" + formatMoney(totals.retencao)'></span></div>
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
    {{-- Documento gravado: imprimir É a razão de muitos o emitirem — o papel
         para o cliente. Antes disto o ecrã redireccionava sozinho e não havia
         impressão em lado nenhum; agora pergunta, como o POS faz com o talão. --}}
    <div x-show="successMsg" x-transition class="fixed bottom-24 inset-x-3 z-50 rounded-xl bg-emerald-600 px-4 py-3 text-sm text-white shadow-2xl">
        <p><i class="fas fa-check-circle mr-2"></i><span x-text="successMsg"></span></p>
        <div class="mt-2 grid grid-cols-3 gap-2" x-show="savedUuid">
            <button @click="imprimirAgora()" :disabled="printing"
                    class="rounded-lg bg-white/95 py-2.5 text-xs font-black text-emerald-800 disabled:opacity-60">
                <span x-show="!printing"><i class="fas fa-print mr-1"></i>{{ __('Imprimir') }}</span>
                <span x-show="printing"><i class="fas fa-spinner fa-spin mr-1"></i>{{ __('A obter o número…') }}</span>
            </button>
            <button @click="partilharAgora()" :disabled="partilhando" data-ensaio="partilhar-pdf"
                    class="rounded-lg bg-teal-900/70 py-2.5 text-xs font-black text-white disabled:opacity-60">
                <span x-show="!partilhando"><i class="fas fa-file-pdf mr-1"></i>{{ __('PDF · WhatsApp') }}</span>
                <span x-show="partilhando"><i class="fas fa-spinner fa-spin mr-1"></i>{{ __('A gerar o PDF…') }}</span>
            </button>
            <a href="{{ route('invoicing.offline.drafts') }}"
               class="rounded-lg bg-emerald-800/60 py-2.5 text-center text-xs font-black text-white">
                {{ __('Ver documentos') }}
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
function draftForm() {
    return {
        online: navigator.onLine,
        saving: false,
        successMsg: '',
        savedUuid: null,
        printing: false,
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
            // Descontos do documento. O comercial incide ANTES do IVA e o
            // financeiro depois — quem apura e o servidor, aqui so se enviam.
            discount_commercial: 0,
            discount_financial: 0,
            delivery_date: '',
            delivery_location: '',
            is_service: false,
            withholding_percentage: 6.5,
            items: [],
        },

        /** As taxas da empresa, sincronizadas. Nunca uma lista inventada. */
        taxas: [],

        /** Tabelas da AGT, sincronizadas. */
        pautais: [],
        verbas: [],

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
            this.pautais = await window.SosPwa.db.iec_pautais.toArray();
            this.verbas = await window.SosPwa.db.is_verbas.toArray();

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

        /** O líquido de uma linha, já com o desconto dela. */
        liquidoDaLinha(itm) {
            const bruto = (parseFloat(itm.quantity) || 0) * (parseFloat(itm.unit_price) || 0);
            const desc = parseFloat(itm.discount_percent) || 0;

            return bruto - (bruto * desc / 100);
        },

        get totals() {
            let subtotal = 0;
            let tax = 0;

            for (const itm of this.form.items) {
                const liquido = this.liquidoDaLinha(itm);
                subtotal += liquido;
                tax += liquido * (parseFloat(itm.tax_rate) || 0) / 100;
            }

            // A MESMA ordem do servidor: o comercial sai do líquido e o imposto
            // recalcula-se sobre o que sobra; o financeiro sai do total já com
            // imposto. Se o ecrã contasse de outra maneira, o total mostrado ao
            // cliente não bateria com o do documento emitido.
            const comercial = Math.min(parseFloat(this.form.discount_commercial) || 0, subtotal);
            const liquido = subtotal - comercial;
            const imposto = subtotal > 0 ? tax * (liquido / subtotal) : 0;
            const financeiro = parseFloat(this.form.discount_financial) || 0;

            // A MESMA regra do servidor: so ha retencao em prestacao de
            // servico, e ela baixa o total a receber sem mexer no imposto.
            const pctRet = parseFloat(this.form.withholding_percentage);
            const retencao = this.form.is_service
                ? Math.round(liquido * (Number.isFinite(pctRet) ? pctRet : 6.5)) / 100
                : 0;

            return {
                subtotal: liquido,
                tax: imposto,
                retencao,
                total: Math.max(0, liquido + imposto - financeiro - retencao),
            };
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
                // A ESCOLHA do IEC e do Selo. Nunca o valor: quem apura e o
                // servidor, pelo ImpostosDaLinha.
                iec_pautal: null,
                is_verba: null,
            });
        },

        formatMoney(v) {
            return new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v || 0);
        },

        async save() {
            if (this.saving || !this.canSave) return;
            this.saving = true;
            try {
                const record = await window.SosPwa.createDraftOffline({ ...this.form });

                // Sem redireccionamento automático: quem emite quase sempre
                // quer o papel a seguir, e o salto para a lista deixava-o sem
                // botão nenhum de imprimir. Fica o aviso com as duas saídas.
                this.savedUuid = record.local_uuid;
                this.successMsg = navigator.onLine
                    ? 'Documento guardado — a emitir no servidor…'
                    : 'Documento guardado. Sai emitido assim que houver rede.';
            } catch (err) {
                console.error(err);
                alert('Erro: ' + err.message);
                this.saving = false;
            }
        },

        partilhando: false,

        async partilharAgora() {
            if (!this.savedUuid || this.partilhando) return;
            this.partilhando = true;
            try {
                const r = await window.SosPwa.partilharPdf('documento', this.savedUuid);
                if (r.modo === 'descarregado') alert('PDF descarregado — anexe-o na conversa.');
            } catch (e) {
                if (e && e.name === 'AbortError') return;
                alert('Não foi possível gerar o PDF: ' + e.message);
            } finally {
                this.partilhando = false;
            }
        },

        async imprimirAgora() {
            if (!this.savedUuid || this.printing) return;
            this.printing = true;
            try {
                // Com rede, o motor espera uns segundos pelo número fiscal —
                // o mesmo prazo do talão do POS; sem rede sai já, marcado
                // como provisório.
                const doc = await window.SosPwa.imprimirDocumento(this.savedUuid);

                if (doc._server_number) {
                    this.successMsg = 'Emitido: ' + doc._server_number;
                }
            } catch (e) {
                alert(e.message);
            } finally {
                this.printing = false;
            }
        },
    };
}
</script>
@endpush
@endsection
