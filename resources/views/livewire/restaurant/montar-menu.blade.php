<div class="min-h-screen bg-slate-100 p-3 sm:p-5">
    <div class="mx-auto max-w-7xl space-y-4">

        <header>
            <a href="{{ route('restaurant.dashboard') }}" class="font-bold text-orange-600">← Restaurante</a>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-slate-950">Montar o Menu</h1>
                    <p class="text-slate-500">Escreva o prato e o preço, Enter, próximo. O que está aqui é o que aparece no POS, no PWA e na carta online.</p>
                </div>
                <div class="flex gap-2">
                    @if($definicoes->online_menu_enabled && $definicoes->menu_slug)
                        <a href="{{ route('restaurant.menu.online', $definicoes->menu_slug) }}" target="_blank"
                           class="rounded-xl border border-slate-200 bg-white px-4 py-3 font-bold text-slate-600 hover:bg-slate-50">
                            <i class="fas fa-qrcode mr-2 text-orange-500"></i>Ver carta online
                        </a>
                    @endif
                    <a href="{{ route('restaurant.products') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 font-bold text-slate-600 hover:bg-slate-50" title="Stock, códigos de barras, custos — o resto do artigo">
                        <i class="fas fa-sliders mr-2"></i>Ficha completa
                    </a>
                </div>
            </div>
        </header>

        @if($definicoes->require_recipe_for_products)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <i class="fas fa-book-open mr-2 text-amber-600"></i><b>Esta casa exige ficha técnica:</b>
                um prato sem ficha fica na carta mas não aparece no POS — os marcados a amarelo abaixo precisam dela.
                <a href="{{ route('restaurant.recipes') }}" class="font-black underline">Fichas técnicas →</a>
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-[290px_minmax(0,1fr)]">

            {{-- ── As categorias: a espinha da carta ─────────────────── --}}
            <aside class="self-start overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-slate-900 p-4 text-white">
                    <p class="text-xs font-black uppercase tracking-widest text-orange-300">A carta</p>
                    <h2 class="text-lg font-black">Categorias</h2>
                    <p class="text-xs text-slate-400">A ordem aqui é a ordem no POS e na carta online.</p>
                </div>

                <nav class="max-h-[52vh] space-y-1 overflow-y-auto p-2">
                    <button wire:click="$set('categoriaId', null)"
                            class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm font-bold {{ !$categoriaId ? 'bg-orange-600 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                        <span><i class="fas fa-border-all mr-2 w-4"></i>Tudo</span>
                        <span class="text-xs opacity-70">{{ $categorias->sum('products_count') }}</span>
                    </button>

                    @foreach($categorias as $c)
                        <div wire:key="cat-{{ $c->id }}"
                             class="group flex items-center gap-1 rounded-xl {{ $categoriaId === $c->id ? 'bg-orange-600 text-white' : 'text-slate-700 hover:bg-slate-100' }} {{ $c->is_active ? '' : 'opacity-50' }}">
                            <button wire:click="$set('categoriaId', {{ $c->id }})"
                                    class="flex min-w-0 flex-1 items-center justify-between px-3 py-2.5 text-left text-sm font-bold">
                                <span class="flex min-w-0 items-center">
                                    <i class="fas {{ $c->icon ?: 'fa-utensils' }} mr-2 w-4" @if($categoriaId !== $c->id) style="color: {{ $c->color }}" @endif></i>
                                    <span class="truncate">{{ $c->name }}</span>
                                </span>
                                <span class="ml-2 text-xs opacity-70">{{ $c->products_count }}</span>
                            </button>
                            <div class="hidden shrink-0 pr-1 group-hover:flex">
                                <button wire:click="moverCategoria({{ $c->id }}, 'cima')" class="grid h-7 w-6 place-items-center rounded opacity-60 hover:opacity-100" title="Subir"><i class="fas fa-caret-up"></i></button>
                                <button wire:click="moverCategoria({{ $c->id }}, 'baixo')" class="grid h-7 w-6 place-items-center rounded opacity-60 hover:opacity-100" title="Descer"><i class="fas fa-caret-down"></i></button>
                            </div>
                        </div>
                    @endforeach
                </nav>

                <div class="border-t border-slate-100 p-3">
                    <div class="flex gap-2">
                        <input type="text" wire:model="novaCategoria" wire:keydown.enter="criarCategoria"
                               placeholder="Nova categoria…"
                               class="min-w-0 flex-1 rounded-xl border-slate-300 text-sm">
                        <button wire:click="criarCategoria" class="rounded-xl bg-slate-900 px-3 font-black text-white" title="Criar"><i class="fas fa-plus"></i></button>
                    </div>
                    @error('novaCategoria')<p class="mt-1 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                    <a href="{{ route('restaurant.categories') }}" class="mt-2 block text-center text-xs font-bold text-slate-400 hover:text-orange-600">Cores, ícones e mais →</a>
                </div>
            </aside>

            {{-- ── Os pratos ──────────────────────────────────────────── --}}
            <section class="space-y-3">

                {{-- Criar: uma linha, Enter, próximo. --}}
                <div class="rounded-2xl border-2 border-dashed border-orange-300 bg-orange-50/60 p-4">
                    <p class="mb-2 text-xs font-black uppercase tracking-wide text-orange-700">
                        <i class="fas fa-plus mr-1"></i>Prato novo
                        @if($categoriaId) em <b>{{ $categorias->firstWhere('id', $categoriaId)?->name }}</b>
                        @else <span class="font-bold normal-case">(sem categoria — escolha uma à esquerda para já nascer arrumado)</span>@endif
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <input type="text" wire:model="novoPrato" wire:keydown.enter="criarPrato"
                               placeholder="Nome do prato — ex.: Mufete de cacusso"
                               class="min-w-48 flex-1 rounded-xl border-orange-300 bg-white focus:border-orange-500 focus:ring-orange-500">
                        <input type="text" inputmode="decimal" wire:model="novoPreco" wire:keydown.enter="criarPrato"
                               placeholder="Preço"
                               class="w-32 rounded-xl border-orange-300 bg-white text-right font-black focus:border-orange-500 focus:ring-orange-500">
                        <button wire:click="criarPrato" wire:loading.attr="disabled"
                                class="rounded-xl bg-orange-600 px-5 py-2.5 font-black text-white shadow hover:bg-orange-700 disabled:opacity-50">
                            <i class="fas fa-plus mr-1"></i>Pôr no menu
                        </button>
                    </div>
                    @error('novoPrato')<p class="mt-1 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                    @error('novoPreco')<p class="mt-1 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="search" wire:model.live.debounce.300ms="procurar" placeholder="Procurar na carta…"
                               class="w-full rounded-xl border-slate-300 pl-9">
                    </div>
                    <span class="text-sm font-bold text-slate-500">{{ $pratos->count() }} prato(s)</span>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="hidden grid-cols-[minmax(0,1fr)_170px_130px_110px] gap-2 border-b border-slate-100 bg-slate-50 px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-400 sm:grid">
                        <span>Prato</span><span>Categoria</span><span class="text-right">Preço (Kz)</span><span class="text-center">No menu</span>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @forelse($pratos as $prato)
                            <div wire:key="prato-{{ $prato->id }}"
                                 class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_170px_130px_110px] sm:items-center {{ $prato->is_active ? '' : 'bg-slate-50 opacity-60' }}">

                                <div class="min-w-0">
                                    {{-- O nome edita-se no sítio: sai do campo, grava. --}}
                                    <input type="text" value="{{ $prato->name }}"
                                           wire:change="mudarNome({{ $prato->id }}, $event.target.value)"
                                           class="w-full rounded-lg border-transparent bg-transparent p-1 font-bold text-slate-900 hover:border-slate-200 focus:border-orange-400 focus:bg-white focus:ring-orange-400">
                                    <div class="mt-0.5 flex flex-wrap gap-1 pl-1">
                                        @if($definicoes->require_recipe_for_products && !$comFicha->has($prato->id))
                                            <a href="{{ route('restaurant.recipes') }}"
                                               class="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-black text-amber-800"
                                               title="Sem ficha técnica este prato não aparece no POS">
                                                <i class="fas fa-book-open mr-0.5"></i>falta a ficha
                                            </a>
                                        @endif
                                        @if($prato->manage_stock)
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-500" title="Este artigo desconta stock próprio">stock</span>
                                        @endif
                                    </div>
                                </div>

                                <select wire:change="mudarCategoria({{ $prato->id }}, $event.target.value)"
                                        class="rounded-lg border-slate-200 py-1.5 text-sm">
                                    <option value="" @selected(!$prato->category_id)>— sem categoria —</option>
                                    @foreach($categorias as $c)
                                        <option value="{{ $c->id }}" @selected($prato->category_id === $c->id)>{{ $c->name }}</option>
                                    @endforeach
                                </select>

                                <input type="text" inputmode="decimal"
                                       value="{{ number_format((float) $prato->price, 2, ',', '.') }}"
                                       wire:change="mudarPreco({{ $prato->id }}, $event.target.value)"
                                       class="rounded-lg border-transparent bg-transparent p-1 text-right font-black tabular-nums text-slate-900 hover:border-slate-200 focus:border-orange-400 focus:bg-white focus:ring-orange-400">

                                <div class="text-center">
                                    <button wire:click="alternarDisponivel({{ $prato->id }})"
                                            class="rounded-xl px-3 py-1.5 text-xs font-black {{ $prato->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500' }}"
                                            title="{{ $prato->is_active ? 'Tirar do menu (não apaga nada)' : 'Voltar a pôr no menu' }}">
                                        {{ $prato->is_active ? 'No menu' : 'Escondido' }}
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="px-4 py-14 text-center text-slate-400">
                                <i class="fas fa-utensils mb-3 block text-4xl opacity-40"></i>
                                <p class="font-bold">{{ trim($procurar) !== '' ? 'Nada com esse nome.' : 'Ainda sem pratos aqui.' }}</p>
                                <p class="text-sm">Escreva o primeiro na caixa laranja acima — nome, preço, Enter.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <p class="text-xs text-slate-400">
                    <i class="fas fa-circle-info mr-1"></i>Daqui nada se apaga: um prato já vendido vive em facturas.
                    «Escondido» tira-o do POS e da carta sem partir documento nenhum. Stock, custos e códigos de barras ficam na
                    <a href="{{ route('restaurant.products') }}" class="font-bold underline">ficha completa</a>.
                </p>
            </section>
        </div>
    </div>
</div>
