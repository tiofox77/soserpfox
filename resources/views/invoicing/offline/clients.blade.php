@extends('layouts.pwa', ['title' => 'Clientes Offline'])

@section('content')
<div x-data="clientsList()" x-init="init()" x-cloak class="-mx-1">
    {{-- Cabeçalho --}}
    <div class="bg-gradient-to-br from-cyan-600 to-blue-700 text-white rounded-2xl shadow-lg p-4 mb-3">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold flex items-center gap-2"><i class="fas fa-users"></i>Clientes</h1>
                <p class="text-xs opacity-90 mt-0.5">
                    <span x-text="clients.length"></span> {{ __('neste aparelho') }} ·
                    <span x-text="pendingCount" class="font-bold"></span> por sincronizar
                </p>
            </div>
            <a href="{{ route('invoicing.offline.client-new') }}" class="bg-white/20 hover:bg-white/30 backdrop-blur px-3 py-2 rounded-xl text-sm font-bold transition">
                <i class="fas fa-plus mr-1"></i>Novo
            </a>
        </div>
    </div>

    {{-- Pesquisa --}}
    <div class="sticky top-14 z-30 bg-slate-50/95 backdrop-blur py-2 mb-1">
        <div class="flex items-center gap-2 bg-white rounded-2xl shadow-sm px-3 h-12">
            <i class="fas fa-magnifying-glass text-gray-400"></i>
            <input x-model="search" type="search" placeholder="Pesquisar por nome ou NIF…" class="flex-1 bg-transparent text-sm focus:outline-none">
            <button @click="search = ''" x-show="search" class="text-gray-400 text-xl leading-none px-1">&times;</button>
        </div>
    </div>

    {{-- Lista --}}
    <div class="space-y-2 px-1">
        <template x-for="c in filtered" :key="c.id">
            <div class="bg-white rounded-2xl shadow-sm p-3 flex items-center gap-3 border border-gray-100">
                <div class="w-11 h-11 rounded-full flex items-center justify-center text-white font-bold text-sm shrink-0"
                     :class="isCompany(c) ? 'bg-blue-500' : 'bg-purple-500'"
                     x-text="initials(c.name)"></div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="font-semibold text-sm truncate" x-text="c.name"></p>
                        <span x-show="!c._synced" class="text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-bold whitespace-nowrap">PENDENTE</span>
                    </div>
                    <p class="text-xs text-gray-500" x-show="c.nif">NIF: <strong x-text="c.nif"></strong></p>
                    <p class="text-xs text-gray-400 truncate" x-show="c.email || c.phone || c.mobile">
                        <i class="fas fa-circle-info mr-0.5 opacity-60"></i><span x-text="c.email || c.phone || c.mobile"></span>
                    </p>
                </div>
                <span class="text-[10px] font-bold px-2 py-1 rounded-full whitespace-nowrap"
                      :class="isCompany(c) ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                      x-text="isCompany(c) ? 'Empresa' : 'Singular'"></span>
            </div>
        </template>
        <div x-show="!filtered.length" class="text-center py-16 text-gray-400">
            <i class="fas fa-users-slash text-5xl mb-3 block opacity-40"></i>
            <p class="text-sm font-medium" x-text="search ? '{{ __('Nenhum cliente encontrado') }}' : '{{ __('Ainda não há clientes neste aparelho') }}'"></p>
            <a href="{{ route('invoicing.offline.client-new') }}" class="inline-block mt-3 text-xs bg-emerald-600 text-white px-4 py-2 rounded-lg font-bold">
                <i class="fas fa-user-plus mr-1"></i>Criar cliente
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
function clientsList() {
    return {
        clients: [],
        search: '',
        pendingCount: 0,

        async init() {
            await this.refresh();
            window.addEventListener('pwa:synced', () => this.refresh());
        },

        async refresh() {
            this.clients = await window.SosPwa.db.clients.toArray();
            this.pendingCount = this.clients.filter(c => !c._synced).length;
        },

        get filtered() {
            const s = this.search.toLowerCase().trim();
            if (!s) return this.clients.slice(0, 200);
            return this.clients.filter(c =>
                (c.name || '').toLowerCase().includes(s) ||
                (c.nif || '').toLowerCase().includes(s)
            ).slice(0, 200);
        },

        // Aceita valores canónicos (pessoa_juridica) e legados (empresa)
        isCompany(c) {
            return c.type === 'pessoa_juridica' || c.type === 'empresa' || c.type === 'juridica';
        },

        initials(name) {
            return (name || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
        },
    };
}
</script>
@endpush
@endsection
