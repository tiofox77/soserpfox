@extends('layouts.pwa', ['title' => __('POS Restaurante')])

@section('content')
@php
    // O ícone do PWA, e não o logótipo da empresa — mesma razão do POS de
    // balcão: o logótipo do tenant não está pré-guardado pelo service worker e
    // offline daria um quadrado partido em cada cartão.
    $logoDoPos = asset('pwa/icon-192x192.png');
@endphp
<div x-data="posRestaurante()" x-init="init()" x-cloak class="-mx-4 -my-4">

    {{-- ============ A EMPRESA NÃO TEM O MÓDULO ============
         Só se chega aqui escrevendo o endereço à mão (a rota é fechada pelo
         módulo e o menu não mostra a entrada). Mas o aparelho pode ter a
         página guardada de quando ainda tinha o módulo, e aí é este ecrã que
         aparece — e não uma sala vazia sem explicação. --}}
    <div x-show="semModulo" class="p-6 text-center" x-cloak>
        <div class="bg-white rounded-2xl shadow-sm p-8 max-w-md mx-auto mt-10">
            <i class="fas fa-utensils text-5xl text-slate-300 mb-4 block"></i>
            <h2 class="text-lg font-bold text-slate-800">{{ __('Restaurante não activo') }}</h2>
            <p class="text-sm text-slate-500 mt-2">
                {{ __('Esta empresa não tem o módulo de Restaurante. Fale com o administrador para o activar.') }}
            </p>
            <a href="{{ route('invoicing.offline.index') }}" class="inline-block mt-5 bg-blue-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold">
                {{ __('Voltar ao início') }}
            </a>
        </div>
    </div>

    <div x-show="!semModulo">

    {{-- ============ AVISOS DA SINCRONIZAÇÃO ============
         Uma mesa que passou a balcão ou um preço que mudou não pode ficar só
         no registo do servidor: quem está na sala tem de o ver. --}}
    <div x-show="avisos.length" x-cloak class="mx-3 mt-3 rounded-2xl bg-amber-50 border border-amber-200 p-3">
        <div class="flex items-start gap-2">
            <i class="fas fa-triangle-exclamation text-amber-500 mt-0.5"></i>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-amber-800">{{ __('A sincronização mudou alguma coisa') }}</p>
                <ul class="mt-1 space-y-0.5">
                    <template x-for="(a, i) in avisos" :key="i">
                        <li class="text-[11px] text-amber-700" x-text="a"></li>
                    </template>
                </ul>
            </div>
            <button @click="avisos = []" class="text-amber-400 text-lg leading-none px-1">&times;</button>
        </div>
    </div>

    {{-- ============ TURNO FECHADO ============
         O servidor recusa abrir comandas sem turno. Dizê-lo agora, e não
         quando a comanda já subiu e voltou recusada com a comida servida. --}}
    <div x-show="pronto && !turno.open" x-cloak class="mx-3 mt-3 rounded-2xl bg-red-50 border border-red-200 p-3 flex items-center gap-3">
        <i class="fas fa-lock text-red-500"></i>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-bold text-red-800">{{ __('Turno fechado') }}</p>
            <p class="text-[11px] text-red-600">{{ __('Sem turno aberto as comandas não sobem.') }}</p>
        </div>
        {{-- ABRE AQUI, e não noutro ecrã.
             Mandava para o POS de balcão: com a sala cheia, ninguém faz essa
             viagem — tiravam-se as comandas na mesma, o servidor recusava-as
             por falta de turno, e a comida já tinha saído. --}}
        <button @click="openOpenShiftModal()" class="shrink-0 bg-red-600 text-white text-xs font-bold px-3 py-2 rounded-xl">
            {{ __('Abrir turno') }}
        </button>
    </div>

    {{-- ================================================================
         VISTA: A SALA
         ================================================================ --}}
    <section x-show="vista === 'sala'" class="p-3 space-y-3">

        {{-- Sala e zona --}}
        <div class="flex gap-2 items-center">
            <div class="flex-1 flex items-center gap-2 bg-white rounded-2xl shadow-sm px-3 h-12" x-show="salas.length > 1">
                <i class="fas fa-store text-gray-400"></i>
                <select x-model.number="salaId" @change="carregarSala()"
                        class="flex-1 bg-transparent text-sm font-semibold focus:outline-none border-0 py-0">
                    <template x-for="s in salas" :key="s.id">
                        <option :value="s.id" x-text="s.name"></option>
                    </template>
                </select>
            </div>

            <button @click="abrirBalcao()" :disabled="!turno.open"
                    class="shrink-0 h-12 px-4 rounded-2xl bg-slate-900 text-white text-sm font-bold shadow disabled:opacity-40 disabled:cursor-not-allowed">
                <i class="fas fa-bag-shopping mr-1.5 text-orange-300"></i>{{ __('Balcão') }}
            </button>

            {{-- O CADEADO DO TURNO, o mesmo do POS de balcão: verde aberto,
                 vermelho fechado, e toca-se para abrir ou fechar. Quem está na
                 sala tem de poder abrir a caixa sem sair daqui. --}}
            <button @click="manageShift()"
                    :class="turno.open ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                    class="shrink-0 h-12 px-3 rounded-2xl text-[10px] font-bold flex flex-col items-center justify-center transition"
                    title="{{ __('Gerir turno') }}">
                <i :class="turno.open ? 'fas fa-lock-open' : 'fas fa-triangle-exclamation'" class="text-sm"></i>
                <span x-text="turno.open ? __('Turno') : __('S/ turno')"></span>
            </button>
        </div>

        {{-- Zonas --}}
        <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1" x-show="zonas.length">
            <button @click="zonaId = null; carregarMesas()"
                    :class="!zonaId ? 'bg-orange-600 text-white' : 'bg-white text-slate-600'"
                    class="shrink-0 px-4 h-10 rounded-xl text-xs font-bold shadow-sm whitespace-nowrap">
                {{ __('Todas') }}
            </button>
            <template x-for="z in zonas" :key="z.id">
                <button @click="zonaId = z.id; carregarMesas()"
                        :class="zonaId === z.id ? 'bg-orange-600 text-white' : 'bg-white text-slate-600'"
                        class="shrink-0 px-4 h-10 rounded-xl text-xs font-bold shadow-sm whitespace-nowrap"
                        x-text="z.name"></button>
            </template>
        </div>

        {{-- Comandas ao balcão (sem mesa): não têm onde aparecer na planta --}}
        <div x-show="comandasSemMesa.length" x-cloak class="space-y-2">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ __('Ao balcão') }}</p>
            <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
                <template x-for="c in comandasSemMesa" :key="c.local_uuid">
                    <button @click="abrirComanda(c.local_uuid)"
                            class="shrink-0 min-w-[150px] text-left bg-white rounded-2xl shadow-sm border border-slate-200 p-3">
                        <p class="text-[10px] font-bold text-slate-400" x-text="c._server_number || __('Por sincronizar')"></p>
                        <p class="text-sm font-bold text-slate-800" x-text="__n(':n artigo|:n artigos', (c.items||[]).length, { n: (c.items||[]).length })"></p>
                        <p class="text-sm font-black text-orange-600" x-text="formatMoney(c.total) + ' Kz'"></p>
                    </button>
                </template>
            </div>
        </div>

        {{-- ============ ÚLTIMAS CONTAS ============
             Para reimprimir. A primeira impressão falha mais do que se pensa —
             papel a acabar, impressora desligada — e o talão provisório passa a
             definitivo assim que a comanda sobe. Sem isto, o cliente que volta
             a pedir a factura ficava sem ela. --}}
        <div x-show="contasFechadas.length" x-cloak class="space-y-2">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ __('Últimas contas') }}</p>
            <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
                <template x-for="c in contasFechadas" :key="c.local_uuid">
                    <div class="shrink-0 min-w-[190px] bg-white rounded-2xl shadow-sm border border-slate-200 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold truncate"
                                   :class="c._invoice_number ? 'text-emerald-600' : 'text-amber-600'"
                                   x-text="c._invoice_number || __('Por sincronizar')"></p>
                                <p class="text-sm font-black text-slate-800" x-text="formatMoney(c.total) + ' Kz'"></p>
                                <p class="text-[10px] text-slate-400" x-text="nomeDaMesa(c.table_id)"></p>
                            </div>
                            <button @click="imprimirTalao(c.local_uuid)"
                                    class="shrink-0 h-9 w-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600"
                                    :title="__('Imprimir talão')">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ============ A PLANTA ============ --}}
        <div x-show="mesas.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-2.5">
            <template x-for="m in mesas" :key="m.id">
                <button @click="escolherMesa(m)" :disabled="!turno.open && !m.comanda"
                        :class="corDaMesa(m)"
                        class="relative rounded-2xl border-2 p-3 text-left shadow-sm transition hover:-translate-y-0.5 disabled:opacity-45 disabled:cursor-not-allowed disabled:hover:translate-y-0">

                    <div class="flex items-start justify-between gap-1">
                        <span class="text-sm font-black leading-tight truncate" x-text="m.name || m.code"></span>
                        <span class="shrink-0 text-[10px] font-bold opacity-70">
                            <i class="fas fa-user-group mr-0.5"></i><span x-text="m.capacity || 0"></span>
                        </span>
                    </div>

                    <p class="mt-1 text-[10px] font-bold uppercase tracking-wide opacity-70" x-text="rotuloDoEstado(m.status)"></p>

                    {{-- Uma mesa ocupada mostra o que já lá está: é o número que
                         o empregado precisa de dizer quando lhe perguntam
                         quanto é, e sem rede não há mais nenhum sítio onde o ir
                         buscar. --}}
                    <template x-if="m.comanda">
                        <div class="mt-2 pt-2 border-t border-current/15">
                            <p class="text-sm font-black" x-text="formatMoney(m.comanda.total) + ' Kz'"></p>
                            <p class="text-[10px] font-semibold opacity-70"
                               x-text="__n(':n artigo|:n artigos', m.comanda.artigos, { n: m.comanda.artigos })"></p>
                        </div>
                    </template>

                    <span x-show="m.comanda && !m.comanda.numero" x-cloak
                          class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-current opacity-60"
                          :title="__('Por sincronizar')"></span>
                </button>
            </template>
        </div>

        {{-- Sala por montar.

             DUAS CAUSAS, DUAS MENSAGENS. Isto dizia sempre "Sem mesas
             sincronizadas" — mesmo com as mesas todas no aparelho, quando o
             que estava vazio era a sala ou a zona escolhida. Mandava
             sincronizar outra vez, o que não resolvia nada, e fazia parecer
             uma avaria de sincronização o que era uma questão de escolha. --}}
        <div x-show="pronto && !mesas.length && !comandasSemMesa.length" x-cloak class="text-center py-16 text-slate-400">
            <i class="fas fa-chair text-5xl mb-3 block opacity-40"></i>

            <template x-if="todasAsMesas.length">
                <div>
                    <p class="text-sm font-medium">{{ __('Esta sala não tem mesas') }}</p>
                    <p class="text-xs mt-1" x-text="zonaId
                        ? '{{ __('A zona escolhida está vazia — veja as outras zonas.') }}'
                        : '{{ __('As mesas estão noutra sala — escolha-a acima.') }}'"></p>
                    <button @click="zonaId = null" x-show="zonaId"
                            class="mt-4 text-xs bg-slate-800 text-white px-4 py-2 rounded-lg font-bold">
                        <i class="fas fa-layer-group mr-1"></i>{{ __('Ver a sala toda') }}
                    </button>
                </div>
            </template>

            <template x-if="!todasAsMesas.length">
                <div>
                    <p class="text-sm font-medium">{{ __('Sem mesas sincronizadas') }}</p>
                    <p class="text-xs mt-1">{{ __('Monte a sala em Restaurante → Salas e sincronize.') }}</p>
                    <button @click="window.SosPwa.sync(true)" class="mt-4 text-xs bg-blue-600 text-white px-4 py-2 rounded-lg font-bold">
                        <i class="fas fa-rotate mr-1"></i>{{ __('Sincronizar') }}
                    </button>
                </div>
            </template>
        </div>
    </section>

    {{-- ================================================================
         VISTA: A COMANDA
         Mesma geometria do POS de balcão, e pela mesma razão: `dvh` para o
         cromado do browser no telemóvel, e a barra de pesquisa `lg:static`
         porque a partir de lg a coluna dos pratos já tem o seu próprio scroll
         — sticky ali tapava a primeira fila de cartões.
         ================================================================ --}}
    <section x-show="vista === 'comanda'" x-cloak>
        <div class="lg:grid lg:grid-cols-12 lg:h-[calc(100dvh-137px)]">

            {{-- ---------- PRATOS ---------- --}}
            <div class="lg:col-span-7 xl:col-span-8 bg-slate-100 lg:h-full lg:flex lg:flex-col lg:overflow-hidden">

                <div class="sticky top-14 lg:static z-30 bg-slate-100/95 backdrop-blur px-3 pt-3 pb-2 space-y-2 border-b border-slate-200">
                    <div class="flex gap-2 items-center">
                        <button @click="voltarSala()" class="shrink-0 h-12 w-12 rounded-2xl bg-white shadow-sm text-slate-600" :title="__('Voltar à sala')">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <div class="flex-1 flex items-center gap-2 bg-white rounded-2xl shadow-sm px-3 h-12">
                            <i class="fas fa-magnifying-glass text-gray-400"></i>
                            <input x-model="pesquisa" type="search" inputmode="search"
                                   placeholder="{{ __('Procurar prato…') }}"
                                   class="flex-1 bg-transparent text-sm focus:outline-none">
                            <button @click="pesquisa = ''" x-show="pesquisa" class="text-gray-400 text-xl leading-none px-1">&times;</button>
                        </div>
                    </div>

                    <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
                        <button @click="categoria = null"
                                :class="!categoria ? 'bg-orange-600 text-white' : 'bg-white text-slate-600'"
                                class="shrink-0 px-4 h-9 rounded-xl text-xs font-bold shadow-sm whitespace-nowrap">
                            {{ __('Todos') }}
                        </button>
                        <template x-for="c in categorias" :key="c">
                            <button @click="categoria = c"
                                    :class="categoria === c ? 'bg-orange-600 text-white' : 'bg-white text-slate-600'"
                                    class="shrink-0 px-4 h-9 rounded-xl text-xs font-bold shadow-sm whitespace-nowrap"
                                    x-text="c"></button>
                        </template>
                    </div>
                </div>

                <div class="px-3 py-3 lg:flex-1 lg:overflow-y-auto">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-2.5">
                        <template x-for="p in pratosVisiveis" :key="p.id">
                            <button @click="juntar(p)"
                                    class="bg-white rounded-2xl shadow-sm p-2 flex flex-col text-left border border-transparent hover:border-orange-300 active:scale-[.98] transition">
                                <div class="relative w-full h-20 sm:h-24 rounded-xl bg-gradient-to-br from-orange-50 to-amber-50 border border-orange-100 flex items-center justify-center mb-2 overflow-hidden">
                                    <img src="{{ $logoDoPos }}" alt=""
                                         class="h-8 sm:h-10 w-auto object-contain opacity-25 select-none pointer-events-none" draggable="false">
                                    <span class="absolute bottom-1 left-1 w-5 h-5 rounded-full bg-white/85 flex items-center justify-center shadow-sm">
                                        <i class="fas fa-bowl-food text-[10px] text-orange-500"></i>
                                    </span>
                                    <span x-show="quantidadeNaComanda(p) > 0" x-cloak
                                          class="absolute top-1 right-1 bg-emerald-600 text-white text-[11px] font-bold min-w-[24px] h-6 px-1 rounded-full flex items-center justify-center shadow"
                                          x-text="quantidadeNaComanda(p)"></span>
                                </div>
                                <p class="font-semibold text-[13px] leading-tight line-clamp-2 mb-0.5" x-text="p.name"></p>
                                <p class="text-[10px] text-gray-400 truncate" x-show="p.category" x-text="p.category"></p>
                                <div class="mt-auto pt-1.5">
                                    <span class="font-bold text-orange-700 text-sm whitespace-nowrap" x-text="formatMoney(p.price)"></span>
                                </div>
                            </button>
                        </template>
                    </div>

                    <div x-show="pratosFiltrados.length > limite" class="mt-4 text-center">
                        <button @click="limite += 40" class="bg-white border border-gray-200 text-gray-700 px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm">
                            <i class="fas fa-chevron-down mr-1"></i>{{ __('Mostrar mais') }}
                        </button>
                    </div>

                    <div x-show="!pratosFiltrados.length" x-cloak class="text-center py-16 text-gray-400">
                        <i class="fas fa-bowl-food text-5xl mb-3 block opacity-40"></i>
                        <p class="text-sm font-medium" x-text="pesquisa || categoria ? __('Nenhum prato encontrado') : __('Sem pratos sincronizados')"></p>
                        {{-- Quando a empresa exige ficha técnica, um menu vazio
                             quase nunca é falta de sincronização: são pratos
                             sem ficha. Dizê-lo poupa uma chamada ao suporte. --}}
                        <p x-show="definicoes.require_recipe_for_products" x-cloak class="text-[11px] mt-2 max-w-xs mx-auto">
                            {{ __('Esta empresa só vende pratos com ficha técnica activa.') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- ---------- A CONTA ---------- --}}
            <div class="lg:col-span-5 xl:col-span-4 bg-white lg:h-full lg:flex lg:flex-col lg:border-l border-slate-200">

                <div class="px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-slate-900 to-slate-800 text-white">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-orange-300"
                               x-text="comanda?.table_id ? (nomeDaMesa(comanda.table_id)) : __('Venda ao balcão')"></p>
                            <h2 class="text-lg font-black truncate"
                                x-text="comanda?._server_number || __('Comanda por sincronizar')"></h2>
                            <p class="text-[11px] opacity-70"
                               x-text="__n(':n pessoa|:n pessoas', comanda?.guest_count || 1, { n: comanda?.guest_count || 1 })"></p>
                        </div>
                        <button @click="voltarSala()" class="shrink-0 h-9 w-9 rounded-xl bg-white/10 hover:bg-white/20">
                            <i class="fas fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <div class="lg:flex-1 lg:overflow-y-auto divide-y divide-slate-100">
                    <template x-for="i in (comanda?.items || [])" :key="i.local_uuid">
                        <div class="px-3 py-2.5 flex items-center gap-2">
                            <div class="shrink-0">
                                <template x-if="!i.enviado">
                                    <div class="flex items-center rounded-xl border border-orange-200 bg-orange-50 overflow-hidden">
                                        <button @click="alterar(i, -1)" class="h-9 w-8 font-black text-orange-700 hover:bg-orange-100" aria-label="{{ __('Diminuir') }}">&minus;</button>
                                        <span class="px-2 text-sm font-black text-slate-800" x-text="i.quantity"></span>
                                        <button @click="alterar(i, 1)" class="h-9 w-8 font-black text-orange-700 hover:bg-orange-100" aria-label="{{ __('Aumentar') }}">+</button>
                                    </div>
                                </template>
                                {{-- Já foi para a cozinha: a quantidade fixa-se.
                                     Foi cozinhado — mexer aqui seria mentir ao
                                     stock e à cozinha, e o servidor recusa na
                                     mesma. --}}
                                <template x-if="i.enviado">
                                    <div class="h-9 min-w-[46px] px-2 rounded-xl bg-violet-50 border border-violet-200 flex items-center justify-center">
                                        <span class="text-sm font-black text-violet-700" x-text="i.quantity + '×'"></span>
                                    </div>
                                </template>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-slate-800 truncate" x-text="i.product_name"></p>
                                <p class="text-[11px] text-slate-400">
                                    <span x-text="formatMoney(i.unit_price)"></span> Kz
                                    <span x-show="i.enviado" class="text-violet-600 font-bold ml-1">· {{ __('na cozinha') }}</span>
                                </p>
                                <p x-show="i.notes" x-cloak class="text-[11px] text-amber-600 italic truncate" x-text="i.notes"></p>
                            </div>

                            <div class="text-right shrink-0">
                                <b class="block text-sm text-slate-800" x-text="formatMoney(i.quantity * i.unit_price)"></b>
                                <button x-show="!i.enviado" @click="remover(i)" class="text-red-400 hover:text-red-600 text-xs mt-0.5">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <div x-show="!(comanda?.items || []).length" x-cloak class="text-center py-16 text-slate-400">
                        <i class="fas fa-utensils text-5xl mb-3 block opacity-30"></i>
                        <p class="text-sm font-medium">{{ __('Comanda vazia') }}</p>
                        <p class="text-xs mt-1">{{ __('Toque num prato para o juntar.') }}</p>
                    </div>
                </div>

                <div class="border-t border-slate-200 p-3 space-y-2 bg-slate-50">
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>{{ __('Base') }}</span>
                        <span x-text="formatMoney(comanda?.subtotal)"></span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>{{ __('Imposto') }}</span>
                        <span x-text="formatMoney(comanda?.tax)"></span>
                    </div>
                    <div class="flex justify-between items-baseline">
                        <span class="text-sm font-bold text-slate-700">{{ __('Total') }}</span>
                        <span class="text-2xl font-black text-orange-600" x-text="formatMoney(comanda?.total) + ' Kz'"></span>
                    </div>

                    <button @click="enviarCozinha()" x-show="temPorEnviar"
                            class="w-full rounded-2xl bg-violet-600 hover:bg-violet-700 text-white p-3 font-black transition">
                        <i class="fas fa-fire-burner mr-2"></i>
                        <span x-text="definicoes.use_kitchen_workflow === false ? __('Confirmar pedido') : __('Enviar à cozinha')"></span>
                    </button>

                    <button @click="abrirReceber()" :disabled="!(comanda?.items || []).length"
                            class="w-full rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white p-4 text-lg font-black shadow-lg shadow-emerald-600/20 disabled:opacity-40 disabled:cursor-not-allowed transition">
                        <i class="fas fa-cash-register mr-2"></i>{{ __('Receber e faturar') }}
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- ================================================================
         MODAL: RECEBER
         ================================================================ --}}
    <div x-show="mostrarReceber" x-cloak class="fixed inset-0 z-[110] bg-slate-950/75 p-3 overflow-y-auto">
        <div class="min-h-full flex items-center justify-center">
            <section class="w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-slate-950 to-slate-800 p-5 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-orange-300">{{ __('Fechar conta') }}</p>
                            <h2 class="text-xl font-black" x-text="comanda?._server_number || __('Comanda offline')"></h2>
                        </div>
                        <button @click="mostrarReceber = false" class="h-9 w-9 rounded-xl bg-white/10 font-black">&times;</button>
                    </div>
                    <div class="mt-4 flex items-center justify-between rounded-2xl bg-white/10 p-3">
                        <span class="text-sm opacity-80">{{ __('Total a receber') }}</span>
                        <strong class="text-2xl text-orange-300" x-text="formatMoney(comanda?.total) + ' Kz'"></strong>
                    </div>
                </div>

                <div class="p-5 space-y-3">
                    <div>
                        <label class="text-xs font-bold text-slate-600">{{ __('Documento') }}</label>
                        <div class="mt-1 grid grid-cols-2 gap-2">
                            <button @click="recebimento.document_type = 'FR'"
                                    :class="recebimento.document_type === 'FR' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'"
                                    class="rounded-xl py-2.5 text-sm font-bold">{{ __('Fatura-Recibo') }}</button>
                            <button @click="recebimento.document_type = 'FT'"
                                    :class="recebimento.document_type === 'FT' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'"
                                    class="rounded-xl py-2.5 text-sm font-bold">{{ __('Fatura') }}</button>
                        </div>
                        {{-- A FT fica por pagar de propósito: é uma conta a
                             receber, não dinheiro em caixa. Dizê-lo aqui evita
                             que se escolha a errada por hábito. --}}
                        <p x-show="recebimento.document_type === 'FT'" x-cloak class="text-[11px] text-amber-600 mt-1.5">
                            {{ __('A fatura fica por liquidar — sem entrada de dinheiro em caixa.') }}
                        </p>
                    </div>

                    <div x-show="recebimento.document_type === 'FR'">
                        <label class="text-xs font-bold text-slate-600">{{ __('Pagamento') }}</label>
                        <select x-model.number="recebimento.payment_method_id"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            <template x-for="m in metodos" :key="m.id">
                                <option :value="m.id" x-text="m.name"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-600">{{ __('Cliente (opcional)') }}</label>
                        <select x-model="recebimento.client_id" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            <option value="">{{ __('Consumidor Final') }}</option>
                            <template x-for="c in clientes" :key="c.id">
                                <option :value="c.id" x-text="c.name"></option>
                            </template>
                        </select>
                    </div>

                    {{-- O número fiscal sai do servidor. Prometê-lo aqui seria
                         mentira: offline não há numeração da AGT que se possa
                         inventar, e quem está na sala tem de saber que o talão
                         definitivo chega quando a rede voltar. --}}
                    <p class="text-[11px] text-slate-500 bg-slate-50 rounded-xl p-3">
                        <i class="fas fa-circle-info mr-1 text-slate-400"></i>
                        <span x-show="online">{{ __('O documento é emitido agora e recebe já o número fiscal.') }}</span>
                        <span x-show="!online" x-cloak>{{ __('Sem rede: a conta fica fechada aqui e o número fiscal é atribuído quando sincronizar.') }}</span>
                    </p>

                    <button @click="confirmarRecebimento()" :disabled="aReceber"
                            class="w-full rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white p-4 text-lg font-black shadow-lg disabled:opacity-50">
                        <span x-show="!aReceber"><i class="fas fa-check mr-2"></i>{{ __('Confirmar') }}</span>
                        <span x-show="aReceber" x-cloak><i class="fas fa-spinner fa-spin mr-2"></i>{{ __('A processar…') }}</span>
                    </button>
                    <button @click="mostrarReceber = false" class="w-full p-2 text-sm font-bold text-slate-500">{{ __('Voltar à comanda') }}</button>
                </div>
            </section>
        </div>
    </div>

    {{-- ================================================================
         MODAL: CONTA FECHADA
         ================================================================ --}}
    <div x-show="recibo" x-cloak class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/75 p-4">
        <section class="w-full max-w-sm bg-white rounded-3xl shadow-2xl overflow-hidden">
            <div class="p-7 text-center text-white" :class="recibo?.numero ? 'bg-emerald-600' : 'bg-amber-500'">
                <i class="fas text-5xl" :class="recibo?.numero ? 'fa-circle-check' : 'fa-clock'"></i>
                <p class="mt-3 text-[10px] font-black uppercase tracking-widest"
                   x-text="recibo?.numero ? __('Documento emitido') : __('Guardado neste aparelho')"></p>
                <h2 class="text-xl font-black" x-text="recibo?.numero || recibo?.provisorio || __('Sobe quando houver rede')"></h2>
            </div>
            <div class="p-5 space-y-3">
                <p class="text-center text-2xl font-black text-slate-800" x-text="formatMoney(recibo?.total) + ' Kz'"></p>

                {{-- O TALÃO É O QUE O CLIENTE LEVA.
                     Sem rede sai provisório, com o aviso a dizê-lo — é o mesmo
                     papel do balcão, e não uma versão de segunda. Quando a
                     comanda subir, reimprime-se com número, ATCUD e QR. --}}
                <button @click="imprimirTalao(recibo.local_uuid)"
                        class="w-full rounded-xl bg-slate-900 hover:bg-slate-800 text-white p-4 font-black transition">
                    <i class="fas fa-print mr-2 text-orange-300"></i>{{ __('Imprimir talão') }}
                </button>

                <p x-show="!recibo?.numero" x-cloak class="text-[11px] text-amber-700 text-center">
                    {{ __('Sai como provisório. Reimprima depois de sincronizar para levar o número fiscal.') }}
                </p>

                <button @click="recibo = null; voltarSala()" class="w-full rounded-xl border border-slate-300 p-3 font-bold text-slate-600">
                    {{ __('Continuar') }}
                </button>
            </div>
        </section>
    </div>

    {{-- Os modais do turno: os MESMOS do POS de balcão, não uma segunda
         versão deles. Ver resources/views/partials/pwa-turno.blade.php --}}
    @include('partials.pwa-turno')

    </div>{{-- fim de !semModulo --}}
</div>

@push('scripts')
<script>
function posRestaurante() {
    return {
        pronto: false,
        semModulo: false,
        vista: 'sala',
        online: navigator.onLine,

        salas: [], zonas: [], mesas: [], todasAsMesas: [],
        salaId: null, zonaId: null,
        definicoes: {},

        // O TURNO É O MESMO DO POS DE BALCÃO, e vem do mesmo sítio:
        // public/js/pwa-turno.js. Antes o restaurante só sabia LER o estado do
        // turno e mandava o empregado ao outro ecrã para o abrir — uma viagem
        // que ninguém faz com a sala cheia, e as comandas saíam na mesma para
        // serem recusadas depois, com a comida já servida.
        ...TurnoDoPwa(),

        // O ecrã fala em `turno`; o motor partilhado fala em `shift`. Um
        // alias, e não uma segunda cópia: assim continuam a ser a mesma coisa.
        get turno() { return this.shift; },

        todosOsPratos: [],
        pesquisa: '', categoria: null, limite: 40,

        comanda: null,
        comandasSemMesa: [],
        contasFechadas: [],

        mostrarReceber: false,
        aReceber: false,
        recebimento: { document_type: 'FR', client_id: '', payment_method_id: null },
        metodos: [], clientes: [],
        recibo: null,
        avisos: [],

        async init() {
            await this.esperarMotor();

            this.semModulo = !(await window.SosPwa.temModulo('restaurant'));

            if (this.semModulo) {
                this.pronto = true;
                return;
            }

            await this.iniciarTurno();
            await this.carregarTudo();
            this.pronto = true;

            // Uma sincronização muda mesas, pratos e o estado do turno — o ecrã
            // tem de acompanhar, senão continua a mostrar a sala de há uma hora.
            window.addEventListener('pwa:synced', () => this.carregarTudo());

            window.addEventListener('pwa:comanda-sincronizada', (e) => {
                if (e.detail?.avisos?.length) {
                    this.avisos = [...this.avisos, ...e.detail.avisos];
                }

                // O número fiscal chega segundos depois de o cliente pagar, e
                // quem está com o talão na mão ainda ali está. Trocar o
                // provisório pelo definitivo poupa uma reimpressão.
                if (this.recibo && e.detail?.local_uuid === this.recibo.local_uuid) {
                    this.recibo = { ...this.recibo, numero: e.detail.invoice_number || this.recibo.numero };
                }

                this.carregarTudo();
            });

            window.addEventListener('online', () => { this.online = true; });
            window.addEventListener('offline', () => { this.online = false; });
        },

        /**
         * O motor pode ainda não ter carregado quando o Alpine arranca — é uma
         * corrida que acontece de verdade num telemóvel lento e deixava o ecrã
         * em branco sem um erro que se visse.
         */
        async esperarMotor() {
            for (let i = 0; i < 100 && !window.SosPwa; i++) {
                await new Promise((r) => setTimeout(r, 100));
            }
        },

        async carregarTudo() {
            const r = window.SosPwa.restaurante;

            this.definicoes = await r.definicoes();
            this.salas = await r.salas();

            // A SALA POR OMISSÃO É UMA QUE TENHA MESAS, e não a primeira da
            // lista. Numa empresa com mais do que uma sala, a primeira por
            // ordem pode estar vazia — e o empregado abria o PWA num salão
            // sem mesa nenhuma, com as mesas todas no aparelho, na sala do
            // lado. Foi o que aconteceu a testar num Android.
            if (!this.salaId && this.salas.length) {
                const todas = await r.mesas();
                const comMesas = this.salas.find((s) => todas.some((m) => m.venue_id === s.id));

                this.salaId = (comMesas || this.salas[0]).id;
            }

            this.zonas = await r.zonas(this.salaId);
            this.todosOsPratos = await r.pratos();
            this.shift = (await window.SosPwa.getShift()) || { open: false };
            // Os métodos de pagamento vêm na sincronização geral e ficam no
            // aparelho: o fecho de uma comanda precisa do ID do método para
            // lançar o recebimento na caixa certa.
            this.metodos = (await window.SosPwa.db.meta.get('payment_methods'))?.value || [];

            if (!this.recebimento.payment_method_id && this.metodos.length) {
                this.recebimento.payment_method_id = this.metodos[0].id;
            }

            this.clientes = (await window.SosPwa.getClients()).slice(0, 200);

            await this.carregarMesas();
            await this.recarregarComanda();
        },

        async carregarSala() {
            this.zonaId = null;
            this.zonas = await window.SosPwa.restaurante.zonas(this.salaId);
            await this.carregarMesas();
        },

        async carregarMesas() {
            this.mesas = await window.SosPwa.restaurante.mesas(this.salaId, this.zonaId);
            this.todasAsMesas = await window.SosPwa.restaurante.mesas();
            this.comandasSemMesa = (await window.SosPwa.restaurante.comandas())
                .filter((c) => !c.table_id);

            // Só as últimas: a lista serve para reimprimir o que acabou de
            // sair, não para ser um histórico — esse vive no sistema.
            this.contasFechadas = (await window.SosPwa.restaurante.comandas(true))
                .filter((c) => c.status === 'fechada')
                .slice(0, 8);
        },

        // ---- A sala ----

        rotuloDoEstado(estado) {
            const rotulos = {
                available: @json(__('Livre')),
                reserved: @json(__('Reservada')),
                occupied: @json(__('Ocupada')),
                waiting_kitchen: @json(__('Na cozinha')),
                served: @json(__('Servida')),
                billing: @json(__('A pedir conta')),
                cleaning: @json(__('Em limpeza')),
                blocked: @json(__('Bloqueada')),
            };

            return rotulos[estado] || estado;
        },

        corDaMesa(mesa) {
            const cores = {
                available: 'bg-emerald-50 border-emerald-300 text-emerald-800',
                reserved: 'bg-cyan-50 border-cyan-300 text-cyan-800',
                occupied: 'bg-amber-50 border-amber-400 text-amber-900',
                waiting_kitchen: 'bg-violet-50 border-violet-300 text-violet-800',
                served: 'bg-sky-50 border-sky-300 text-sky-800',
                billing: 'bg-orange-50 border-orange-300 text-orange-800',
                cleaning: 'bg-slate-100 border-slate-300 text-slate-500',
                blocked: 'bg-red-50 border-red-300 text-red-700',
            };

            return cores[mesa.status] || 'bg-white border-slate-200 text-slate-700';
        },

        /**
         * Procura em TODAS as mesas e não só nas da zona escolhida: a lista
         * das últimas contas mostra mesas de qualquer zona, e uma conta que
         * dissesse só "Mesa" não ajudava ninguém a encontrá-la.
         */
        nomeDaMesa(id) {
            if (!id) { return @json(__('Balcão')); }

            const m = this.todasAsMesas.find((x) => x.id === id)
                || this.mesas.find((x) => x.id === id);

            return m ? (m.name || m.code) : @json(__('Mesa'));
        },

        async escolherMesa(mesa) {
            if (mesa.comanda) {
                return this.abrirComanda(mesa.comanda.local_uuid);
            }

            if (['blocked', 'cleaning'].includes(mesa.status)) {
                return alert(@json(__('Esta mesa não está disponível para atendimento.')));
            }

            const comanda = await window.SosPwa.restaurante.abrir({
                venue_id: this.salaId,
                table_id: mesa.id,
                guest_count: Math.max(1, mesa.capacity || 1),
            });

            await this.abrirComanda(comanda.local_uuid);
        },

        async abrirBalcao() {
            const comanda = await window.SosPwa.restaurante.abrir({
                venue_id: this.salaId,
                channel: 'counter',
                guest_count: 1,
            });

            await this.abrirComanda(comanda.local_uuid);
        },

        async abrirComanda(uuid) {
            this.comanda = await window.SosPwa.restaurante.comanda(uuid);
            this.vista = 'comanda';
            this.pesquisa = '';
            this.categoria = null;
            this.limite = 40;
        },

        async recarregarComanda() {
            if (!this.comanda) { return; }

            this.comanda = await window.SosPwa.restaurante.comanda(this.comanda.local_uuid);
        },

        async voltarSala() {
            // Uma comanda aberta por engano, sem nada dentro, não fica a
            // ocupar a mesa: quem toca na mesa errada não devia ter de a ir
            // desbloquear a outro sítio.
            if (this.comanda && !(this.comanda.items || []).length && !this.comanda.enviada_cozinha) {
                await window.SosPwa.restaurante.descartar(this.comanda.local_uuid).catch(() => {});
            }

            this.comanda = null;
            this.vista = 'sala';
            await this.carregarMesas();
        },

        // ---- Os pratos ----

        get categorias() {
            return [...new Set(this.todosOsPratos.map((p) => p.category).filter(Boolean))].sort();
        },

        /**
         * Derivado, e não uma lista guardada que alguém tem de se lembrar de
         * refazer: escrever na pesquisa muda o ecrã sozinho. A versão anterior
         * era uma propriedade actualizada à mão e bastava esquecer uma chamada
         * para a grelha ficar a mostrar o resultado da pesquisa anterior.
         */
        get pratosFiltrados() {
            const termo = (this.pesquisa || '').toLowerCase().trim();

            return this.todosOsPratos.filter((p) => {
                if (this.categoria && p.category !== this.categoria) { return false; }
                if (!termo) { return true; }

                return (p.name || '').toLowerCase().includes(termo)
                    || (p.sku || '').toLowerCase().includes(termo);
            });
        },

        get pratosVisiveis() {
            return this.pratosFiltrados.slice(0, this.limite);
        },

        quantidadeNaComanda(prato) {
            return (this.comanda?.items || [])
                .filter((i) => i.product_id === prato.id)
                .reduce((s, i) => s + (parseFloat(i.quantity) || 0), 0);
        },

        get temPorEnviar() {
            return (this.comanda?.items || []).some((i) => !i.enviado);
        },

        // ---- A conta ----

        async juntar(prato) {
            this.comanda = await window.SosPwa.restaurante.juntar(this.comanda.local_uuid, {
                product_id: prato.id,
                product_name: prato.name,
                quantity: 1,
                unit_price: prato.price,
                tax_rate: prato.tax_rate,
            });
        },

        async alterar(item, delta) {
            try {
                this.comanda = await window.SosPwa.restaurante.alterarQuantidade(
                    this.comanda.local_uuid, item.local_uuid, delta
                );
            } catch (e) {
                alert(e.message);
            }
        },

        async remover(item) {
            try {
                this.comanda = await window.SosPwa.restaurante.removerArtigo(
                    this.comanda.local_uuid, item.local_uuid
                );
            } catch (e) {
                alert(e.message);
            }
        },

        async enviarCozinha() {
            try {
                this.comanda = await window.SosPwa.restaurante.mandarParaCozinha(this.comanda.local_uuid);
                await this.carregarMesas();
            } catch (e) {
                alert(e.message);
            }
        },

        abrirReceber() {
            this.recebimento.client_id = this.comanda?.client_id || '';
            this.mostrarReceber = true;
        },

        async confirmarRecebimento() {
            this.aReceber = true;

            try {
                const fechada = await window.SosPwa.restaurante.receber(this.comanda.local_uuid, {
                    document_type: this.recebimento.document_type,
                    client_id: this.recebimento.client_id ? parseInt(this.recebimento.client_id, 10) : null,
                    payment_method_id: this.recebimento.document_type === 'FR'
                        ? this.recebimento.payment_method_id
                        : null,
                });

                // Com rede, espera-se pelo número fiscal: o cliente não devia
                // sair com um talão sem número quando havia internet. Sem rede,
                // ou se demorar de mais, segue como pendente — a fila trata.
                const comNumero = await this.esperarPeloNumero(fechada.local_uuid);

                this.mostrarReceber = false;
                this.recibo = {
                    local_uuid: fechada.local_uuid,
                    numero: comNumero?._invoice_number || null,
                    provisorio: fechada.provisional_number,
                    total: comNumero?.total ?? fechada.total,
                };

                this.comanda = null;
                await this.carregarTudo();
            } catch (e) {
                alert(e.message);
            } finally {
                this.aReceber = false;
            }
        },

        async esperarPeloNumero(uuid, msLimite = 8000) {
            if (!navigator.onLine) { return null; }

            const fim = Date.now() + msLimite;

            window.SosPwa.sync(false);

            while (Date.now() < fim) {
                const c = await window.SosPwa.restaurante.comanda(uuid);

                if (c?._invoice_number) { return c; }

                await new Promise((r) => setTimeout(r, 400));
            }

            return null;
        },

        /**
         * Imprime o talão da conta — o mesmo do balcão, porque é o mesmo
         * documento fiscal. Muda o cabeçalho: leva a mesa e o número da
         * comanda.
         */
        async imprimirTalao(uuid) {
            const talao = await window.SosPwa.restaurante.talao(uuid);

            if (!talao) {
                return alert(@json(__('Não foi possível montar o talão desta conta.')));
            }

            if (!window.PosOfflineTicket) {
                return alert(@json(__('O módulo de impressão não carregou. Recarregue a página com internet.')));
            }

            const empresa = (await window.SosPwa.db.meta.get('company'))?.value || {};

            window.PosOfflineTicket.print(talao, empresa);
        },

        formatMoney(v) {
            return new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v || 0);
        },
    };
}
</script>
@endpush
@endsection
