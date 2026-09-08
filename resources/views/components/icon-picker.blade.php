@props([
    /** O nome da propriedade Livewire a ligar. */
    'model' => 'icon',
    /**
     * COMO É QUE ESTE FORMULÁRIO GUARDA O ÍCONE. Há três convenções vivas na
     * base e nenhuma se pode mudar sem uma migração:
     *
     *   `curto`    → `fa-spa`      (catálogos, tipos de movimento)
     *   `completo` → `fas fa-spa`  (categorias do salão, que renderizam a
     *                               classe inteira)
     *   `nome`     → `spa`         (módulos, que montam `fas fa-{{ $icon }}`)
     */
    'formato' => 'curto',
])

{{--
    ESCOLHER UM ÍCONE — de uma galeria, e não escrevendo o código.

    Havia aqui um selector com uma lista de ícones escrita à mão que NINGUÉM
    usava: os três formulários que pedem um ícone continuavam todos com uma
    caixa de texto onde se escrevia `fas fa-spa` e se esperava pelo melhor.

    A LISTA VEM DO `App\Support\GaleriaDeIcones`, a mesma que os ecrãs em
    React recebem pela API. Era a quarta lista de ícones do projecto — e
    quatro listas do mesmo assunto divergem à primeira adição.

    O QUE ESTÁ GRAVADO NUNCA SE PERDE: um ícone antigo que não esteja na
    galeria aparece à mesma, no topo e marcado.
--}}

@php
    $galeria = \App\Support\GaleriaDeIcones::grupos();
@endphp

<div
    x-data="{
        aberto: false,
        procura: '',
        formato: @js($formato),
        grupos: @js($galeria),
        valor: @entangle($model),

        /* O código completo (`fa-x`) a partir do que está guardado. */
        get codigo() {
            const v = (this.valor || '').toString().trim();
            if (!v) return '';
            if (this.formato === 'nome') return 'fa-' + v;
            // `fas fa-spa` → fica-se com a última parte, que é a que identifica.
            return v.split(/\s+/).filter(p => p.startsWith('fa-')).pop() || v;
        },

        /* E o contrário: o que se grava, na convenção deste formulário. */
        guardar(codigo) {
            this.valor = this.formato === 'nome'
                ? codigo.replace(/^fa-/, '')
                : (this.formato === 'completo' ? 'fas ' + codigo : codigo);
            this.aberto = false;
        },

        /* O grupo do que está guardado, quando não está na galeria. */
        get proprio() {
            const c = this.codigo;
            if (!c) return [];
            const conhecido = this.grupos.some(g => g.icones.some(i => i.codigo === c));
            return conhecido ? [] : [{ nome: '{{ __('O que está guardado') }}', icones: [{ codigo: c, nome: '{{ __('actual') }}' }] }];
        },

        get visiveis() {
            const todos = [...this.proprio, ...this.grupos];
            const p = this.procura.trim().toLowerCase();
            if (!p) return todos;
            return todos
                .map(g => ({ ...g, icones: g.icones.filter(i => i.codigo.includes(p) || i.nome.toLowerCase().includes(p)) }))
                .filter(g => g.icones.length > 0);
        },

        get quantos() {
            return this.visiveis.reduce((s, g) => s + g.icones.length, 0);
        },
    }"
    class="relative"
>
    {{-- O botão mostra o que está escolhido, em tamanho de se ver. --}}
    <button
        @click="aberto = !aberto"
        type="button"
        :aria-expanded="aberto"
        :aria-label="'{{ __('Ícone') }}: ' + (valor || '{{ __('nenhum') }}')"
        class="flex w-full items-center gap-3 rounded-xl border-2 border-gray-300 bg-white px-3 py-2 text-left transition hover:border-indigo-400"
    >
        <span class="grid h-10 w-10 flex-none place-items-center rounded-lg bg-indigo-50 text-lg text-indigo-600">
            <template x-if="codigo"><i class="fas" :class="codigo"></i></template>
            <template x-if="!codigo"><i class="fas fa-icons text-gray-300"></i></template>
        </span>
        <span class="min-w-0 flex-1 truncate font-mono text-xs text-gray-600" x-text="valor || '{{ __('Escolher um ícone…') }}'"></span>
        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform" :class="{ 'rotate-180': aberto }"></i>
    </button>

    <div
        x-show="aberto"
        @click.away="aberto = false"
        @keydown.escape.window="aberto = false"
        x-transition
        class="absolute z-50 mt-2 w-full min-w-[20rem] rounded-2xl border border-gray-200 bg-white p-3 shadow-2xl"
        x-cloak
    >
        <div class="mb-3 flex items-center gap-2">
            <input
                x-model="procura"
                type="search"
                placeholder="{{ __('Procurar: carrinho, banco, café…') }}"
                aria-label="{{ __('Procurar ícone') }}"
                class="h-9 flex-1 rounded-lg border border-gray-300 px-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                @click.stop
            >
            <button
                x-show="valor"
                type="button"
                @click="valor = ''"
                class="h-9 rounded-lg px-3 text-xs font-semibold text-gray-500 hover:bg-gray-100"
            >{{ __('Sem ícone') }}</button>
        </div>

        <div class="max-h-64 overflow-y-auto pr-1">
            <p x-show="quantos === 0" class="py-6 text-center text-sm text-gray-400">
                {{ __('Nada com esse nome. Experimente outra palavra.') }}
            </p>

            <template x-for="g in visiveis" :key="g.nome">
                <div class="mb-3 last:mb-0">
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400" x-text="g.nome"></p>
                    <div class="grid grid-cols-6 gap-1 sm:grid-cols-8">
                        <template x-for="i in g.icones" :key="i.codigo">
                            <button
                                type="button"
                                @click="guardar(i.codigo)"
                                :title="i.nome + ' · ' + i.codigo"
                                :aria-label="i.nome"
                                :aria-pressed="codigo === i.codigo"
                                class="grid aspect-square place-items-center rounded-lg text-lg transition-all duration-150"
                                :class="codigo === i.codigo
                                    ? 'bg-indigo-600 text-white shadow-md'
                                    : 'text-gray-600 hover:-translate-y-0.5 hover:bg-indigo-50 hover:text-indigo-600'"
                            >
                                <i class="fas" :class="i.codigo"></i>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
