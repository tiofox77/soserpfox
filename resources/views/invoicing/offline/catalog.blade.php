@extends('layouts.pwa', ['title' => 'Catálogo Offline'])

@section('content')
<div x-data="catalog()" x-init="init()" x-cloak>
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-gray-900 mb-1"><i class="fas fa-box mr-2 text-blue-600"></i>Catálogo</h1>
        <p class="text-xs text-gray-500">Funciona offline — <span x-text="products.length"></span> produtos em cache</p>
    </div>

    <div class="mb-3">
        <input x-model="search" type="search" placeholder="Pesquisar por nome, SKU, código..."
               class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm">
    </div>

    <div class="flex gap-2 mb-3 text-xs">
        <button @click="typeFilter = 'all'" :class="typeFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1.5 rounded-full font-semibold shadow">Todos</button>
        <button @click="typeFilter = 'produto'" :class="typeFilter === 'produto' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1.5 rounded-full font-semibold shadow">Produtos</button>
        <button @click="typeFilter = 'servico'" :class="typeFilter === 'servico' ? 'bg-purple-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1.5 rounded-full font-semibold shadow">Serviços</button>
    </div>

    <div class="space-y-2">
        <template x-for="p in filtered" :key="p.id">
            <div class="bg-white rounded-xl shadow p-3 flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm truncate" x-text="p.name"></p>
                    <div class="flex gap-3 text-xs text-gray-500 mt-0.5">
                        <span x-show="p.sku"><i class="fas fa-barcode mr-1"></i><span x-text="p.sku"></span></span>
                        <span x-show="p.type === 'servico'" class="text-purple-600 font-semibold">Serviço</span>
                        <span x-show="p.type !== 'servico'">Stock: <strong x-text="p.stock_quantity"></strong></span>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-bold text-blue-700" x-text="formatMoney(p.price)"></p>
                    <p class="text-xs text-gray-500">IVA <span x-text="p.tax_rate"></span>%</p>
                </div>
            </div>
        </template>
        <div x-show="!filtered.length" class="text-center py-12 text-gray-400 italic text-sm">
            Nenhum produto encontrado
        </div>
    </div>
</div>

@push('scripts')
<script>
function catalog() {
    return {
        products: [],
        search: '',
        typeFilter: 'all',

        async init() {
            this.products = await window.SosPwa.db.products.toArray();
            window.addEventListener('pwa:synced', async () => {
                this.products = await window.SosPwa.db.products.toArray();
            });
            // Rede de segurança: catálogo vazio mas online → força sync e recarrega.
            if (navigator.onLine && !this.products.length) {
                try { await window.SosPwa.sync(true); }
                catch (e) { console.error('[Catálogo] sync inicial falhou', e); }
                this.products = await window.SosPwa.db.products.toArray();
            }
        },

        get filtered() {
            const s = this.search.toLowerCase().trim();
            return this.products.filter(p => {
                if (this.typeFilter !== 'all' && p.type !== this.typeFilter) return false;
                if (!s) return true;
                return (p.name || '').toLowerCase().includes(s)
                    || (p.sku || '').toLowerCase().includes(s)
                    || (p.barcode || '').toLowerCase().includes(s);
            }).slice(0, 200);
        },

        formatMoney(v) {
            return new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 2 }).format(v || 0);
        },
    };
}
</script>
@endpush
@endsection
