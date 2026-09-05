@php
    // A cor da casa, com um fallback que não é cinzento: uma carta é para dar
    // vontade, e um menu sem cor nenhuma parece um formulário.
    $cor = $definicoes->menu_primary_color ?: '#ea580c';
    $acento = $definicoes->menu_accent_color ?: $cor;
    $escuro = ($definicoes->menu_theme ?? 'claro') === 'escuro';
    $mesa = $this->mesaDoQr;
    // O caminho guardado é do disco público, mas um valor antigo pode ser um
    // URL inteiro (o campo existia antes de haver ecrã que o gravasse). Mesma
    // regra do image_url dos artigos: se já é um endereço, não se lhe toca.
    $comoUrl = function (?string $valor) {
        if (!$valor) {
            return null;
        }

        return filter_var($valor, FILTER_VALIDATE_URL)
            ? $valor
            : \Illuminate\Support\Facades\Storage::url($valor);
    };

    $logoSoserp = app_logo() ?: asset('images/logo.png');
    $logoDaCasa = $comoUrl($definicoes->menu_logo) ?: $logoSoserp;
    $capa = $comoUrl($definicoes->menu_cover);

    // O tema troca o chão da página. Os cartões e o texto seguem-no; a cor da
    // casa fica igual nos dois, senão a marca mudava com o tema.
    $fundo = $escuro ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-900';
    $cartao = $escuro ? 'bg-slate-900 ring-white/10' : 'bg-white ring-slate-200';
    $textoSuave = $escuro ? 'text-slate-400' : 'text-slate-500';
@endphp

<div class="min-h-screen {{ $fundo }}" style="--cor: {{ $cor }}; --acento: {{ $acento }}; background-image: radial-gradient(circle at 8% 2%, {{ $cor }}12 0, transparent 24rem), radial-gradient(circle at 95% 22%, {{ $acento }}0d 0, transparent 22rem);">

    {{-- ============ CABEÇALHO ============ --}}
    <header class="relative overflow-hidden text-white" style="background: linear-gradient(135deg, #0f172a 0%, #172554 54%, {{ $cor }} 145%)">
        {{-- A capa, quando a casa pôs uma. Fica POR BAIXO do gradiente com um
             véu escuro por cima: a fotografia dá o ambiente, o texto continua
             a ler-se. Sem capa, o cabeçalho é o de sempre. --}}
        @if($capa)
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ $capa }}')"></div>
            <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(15,23,42,.88) 0%, rgba(23,37,84,.78) 54%, {{ $cor }}cc 145%)"></div>
        @endif
        <div class="absolute -right-16 -top-20 h-64 w-64 rounded-full opacity-25" style="background: {{ $cor }}"></div>
        <div class="absolute -bottom-24 -left-16 h-52 w-52 rounded-full border border-white/10"></div>
        <div class="relative max-w-5xl mx-auto px-4 {{ $capa ? 'py-10 sm:py-16' : 'py-6 sm:py-9' }} sm:px-6">
            <div class="flex items-start gap-4 sm:items-center sm:gap-6">
                <div class="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-3xl bg-white p-2 shadow-2xl ring-1 ring-white/30 sm:h-24 sm:w-24">
                    <img src="{{ $logoDaCasa }}" alt="{{ $definicoes->menu_title ?: 'SOS ERP' }}"
                         class="h-full w-full object-contain"
                         onerror="this.onerror=null;this.src='{{ $logoSoserp }}'">
                </div>
                <div class="min-w-0 flex-1">
                    <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-widest text-white/90 backdrop-blur">
                        <i class="fas fa-utensils"></i> {{ __('Menu digital') }}
                    </div>
                    <h1 class="text-2xl font-black leading-tight sm:text-4xl">
                        {{ $definicoes->menu_title ?: __('A nossa carta') }}
                    </h1>
                    @if($definicoes->menu_description)
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-white/75 sm:text-base">{{ $definicoes->menu_description }}</p>
                    @endif
                </div>
            </div>

            {{-- A MESA, quando se chegou por um QR colado a ela.
                 É a informação que mais falta nos pedidos que chegam por
                 telemóvel — e é a que o QR já sabe. Mostrar que ela foi
                 reconhecida poupa ao cliente a dúvida de a ter de dizer. --}}
            @if($mesa)
                <div class="mt-5 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-semibold backdrop-blur">
                    <i class="fas fa-location-dot"></i>
                    {{ $this->rotuloDaMesa() }}
                </div>
            @elseif($mesa === null && $this->mesa)
                {{-- O código veio no endereço mas não existe nesta casa: um QR
                     antigo, de uma mesa que já foi removida. Dizer, em vez de
                     fingir que se sabe onde a pessoa está sentada. --}}
                <div class="mt-5 inline-flex items-center gap-2 bg-black/25 px-4 py-2 rounded-full text-sm">
                    <i class="fas fa-circle-question"></i>
                    {{ __('Mesa não reconhecida — diga-a ao fazer o pedido') }}
                </div>
            @endif

            <div class="mt-5 flex items-center gap-2 text-[11px] text-white/60">
                <span>{{ __('Menu disponibilizado por') }}</span>
                <span class="inline-flex items-center rounded-lg bg-white px-2 py-1">
                    <img src="{{ $logoSoserp }}" alt="SOS ERP" class="h-5 w-auto object-contain">
                </span>
            </div>
        </div>
    </header>

    <div class="mx-auto max-w-5xl px-4 py-5 pb-40 sm:px-6 sm:py-7">

        {{-- ============ PESQUISA E CATEGORIAS ============ --}}
        <div class="mb-4 flex h-14 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 shadow-sm transition focus-within:border-transparent focus-within:ring-2" style="--tw-ring-color: {{ $cor }}55">
            <i class="fas fa-magnifying-glass shrink-0 text-slate-400"></i>
            <input wire:model.live.debounce.300ms="pesquisa" type="search"
                   placeholder="{{ __('Procurar na carta…') }}"
                   aria-label="{{ __('Procurar prato') }}"
                   class="min-w-0 flex-1 border-0 bg-transparent py-0 text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-0">
        </div>

        @if($categorias->count())
            <div class="flex gap-2 overflow-x-auto pb-2 -mx-1 px-1">
                <button wire:click="$set('categoriaId', null)"
                        class="h-10 shrink-0 whitespace-nowrap rounded-full border px-4 text-xs font-bold shadow-sm transition
                               {{ $categoriaId === null ? 'border-transparent text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300' }}"
                        @style(['background:' . $cor => $categoriaId === null])>
                    {{ __('Tudo') }}
                </button>
                @foreach($categorias as $categoria)
                    <button wire:click="$set('categoriaId', {{ $categoria->id }})"
                            class="h-10 shrink-0 whitespace-nowrap rounded-full border px-4 text-xs font-bold shadow-sm transition
                                   {{ $categoriaId === $categoria->id ? 'border-transparent text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300' }}"
                            @style(['background:' . $cor => $categoriaId === $categoria->id])>
                        {{ $categoria->name }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- ============ DESTAQUES ============
             O que a casa quer vender hoje, em primeiro lugar. Só aparece na
             vista sem filtros: a meio de uma pesquisa ou dentro de uma
             categoria, uma fila de destaques é ruído sobre o que se procura. --}}
        @if($destaques->isNotEmpty() && $categoriaId === null && trim($pesquisa) === '')
            <div class="mt-6 mb-2 flex items-center gap-2">
                <i class="fas fa-star text-sm" style="color: {{ $acento }}"></i>
                <h2 class="text-sm font-black uppercase tracking-widest" style="color: {{ $acento }}">
                    {{ $definicoes->menu_destaques_titulo ?: __('Sugestões da casa') }}
                </h2>
            </div>

            <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($destaques as $d)
                    @php $p = $d->produto; @endphp
                    @continue(!$p)
                    <div class="flex gap-3 overflow-hidden rounded-2xl p-3 shadow-sm ring-1 {{ $cartao }}"
                         style="border-left: 4px solid {{ $acento }}">
                        @if($p->image_url)
                            <img src="{{ $p->image_url }}" alt="{{ $p->name }}" loading="lazy"
                                 class="h-16 w-16 shrink-0 rounded-xl object-cover">
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-bold">{{ $p->name }}</p>
                            @if($p->description)
                                <p class="mt-0.5 line-clamp-2 text-xs {{ $textoSuave }}">{{ $p->description }}</p>
                            @endif
                            <div class="mt-1.5 flex items-center justify-between gap-2">
                                @if($definicoes->menu_show_prices)
                                    <span class="text-sm font-black" style="color: {{ $acento }}">
                                        {{ number_format((float) $p->price, 2, ',', '.') }}
                                    </span>
                                @else
                                    <span></span>
                                @endif
                                @if($definicoes->menu_orders_enabled)
                                    <button wire:click="escolher({{ $p->id }})"
                                            class="rounded-full px-3 py-1 text-xs font-bold text-white transition hover:opacity-90"
                                            style="background: {{ $acento }}">
                                        <i class="fas fa-plus mr-1"></i>{{ __('Juntar') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ============ OS PRATOS ============ --}}
        @if($pratos->isEmpty())
            <div class="my-8 rounded-3xl border border-dashed border-slate-300 bg-white/70 px-6 py-16 text-center">
                <div class="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-2xl bg-slate-100">
                    <i class="fas fa-utensils text-2xl text-slate-400"></i>
                </div>
                <p class="font-bold text-slate-700">{{ __('Nenhum prato encontrado') }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Experimente outra categoria ou pesquisa.') }}</p>
            </div>
        @else
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                @foreach($pratos as $prato)
                    @php
                        $quantidade = $escolhas[$prato->id] ?? 0;
                        $imagemPrato = $prato->image_url ?: $logoSoserp;
                        $semImagem = !$prato->image_url;
                    @endphp
                    <article class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                        <div class="relative h-44 overflow-hidden sm:h-48 {{ $semImagem ? 'bg-gradient-to-br from-slate-50 to-slate-100' : 'bg-slate-100' }}">
                            <img src="{{ $imagemPrato }}" alt="{{ $prato->name }}" loading="lazy"
                                 class="h-full w-full transition duration-500 group-hover:scale-[1.03] {{ $semImagem ? 'object-contain p-10 opacity-75' : 'object-cover' }}"
                                 onerror="this.onerror=null;this.src='{{ $logoSoserp }}';this.style.objectFit='contain';this.style.padding='2.5rem';this.style.opacity='.75'">
                            @if($prato->category?->name)
                                <span class="absolute left-3 top-3 max-w-[80%] truncate rounded-full bg-slate-950/70 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow backdrop-blur">
                                    {{ $prato->category->name }}
                                </span>
                            @endif
                            @if($quantidade > 0)
                                <span class="absolute right-3 top-3 grid h-9 min-w-9 place-items-center rounded-full px-2 text-sm font-black text-white shadow-lg" style="background: {{ $cor }}">
                                    {{ $quantidade }}
                                </span>
                            @endif
                        </div>

                        <div class="p-4 sm:p-5">
                            <p class="text-base font-extrabold leading-snug text-slate-900">{{ $prato->name }}</p>
                            @if($prato->description)
                                <p class="mt-1.5 line-clamp-2 min-h-9 text-xs leading-relaxed text-slate-500">{{ $prato->description }}</p>
                            @endif

                            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                                @if($definicoes->menu_show_prices)
                                    <p class="text-lg font-black" style="color: {{ $cor }}">
                                        {{ number_format((float) $prato->price, 2, ',', '.') }} <span class="text-xs">Kz</span>
                                    </p>
                                @else
                                    <span></span>
                                @endif

                                @if($definicoes->menu_whatsapp_enabled || $definicoes->menu_orders_enabled)
                                    <div class="flex shrink-0 items-center gap-2">
                                        @if($quantidade > 0)
                                            <button wire:click="retirar({{ $prato->id }})"
                                                    aria-label="{{ __('Retirar uma unidade de :prato', ['prato' => $prato->name]) }}"
                                                    class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-slate-50 text-lg font-bold text-slate-700 transition hover:bg-slate-100">&minus;</button>
                                            <span class="w-6 text-center font-black text-slate-800">{{ $quantidade }}</span>
                                        @endif
                                        <button wire:click="escolher({{ $prato->id }})"
                                                aria-label="{{ __('Adicionar :prato', ['prato' => $prato->name]) }}"
                                                class="grid h-10 w-10 place-items-center rounded-xl text-lg font-bold text-white shadow-md transition hover:brightness-95 active:scale-95"
                                                style="background: {{ $cor }}">+</button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        <div class="mt-10 flex items-center justify-center gap-2 text-xs text-slate-400">
            <span>{{ __('Menu digital seguro por') }}</span>
            <img src="{{ $logoSoserp }}" alt="SOS ERP" class="h-7 w-auto object-contain opacity-70">
        </div>
    </div>

    {{-- PEDIDO ENVIADO.
         Diz o que vai acontecer a seguir, e não só "obrigado": quem está
         sentado a uma mesa quer saber se alguém vai aparecer. --}}
    @if($pedidoEnviado)
        <div class="fixed inset-0 z-50 grid place-items-center bg-slate-950/70 p-4" wire:click="$set('pedidoEnviado', false)">
            <div class="w-full max-w-sm rounded-3xl bg-white p-7 text-center shadow-2xl" wire:click.stop>
                <div class="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-full" style="background: {{ $cor }}22">
                    <i class="fas fa-check text-2xl" style="color: {{ $cor }}"></i>
                </div>
                <h2 class="text-lg font-bold text-slate-800">{{ __('Pedido enviado') }}</h2>
                <p class="mt-2 text-sm text-slate-500">
                    {{ __('Um empregado vai confirmar o pedido na sua mesa. Se precisar de alterar alguma coisa, diga-lhe.') }}
                </p>
                <button wire:click="$set('pedidoEnviado', false)"
                        class="mt-5 w-full rounded-2xl py-3 font-bold text-white" style="background: {{ $cor }}">
                    {{ __('Continuar a ver a carta') }}
                </button>
            </div>
        </div>
    @endif

    {{-- ============ O PEDIDO ============ --}}
    @if($escolhas)
        <div class="fixed bottom-0 inset-x-0 z-40 bg-white border-t border-slate-200 shadow-2xl">
            <div class="max-w-5xl mx-auto px-4 py-3 sm:px-6">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-sm">
                        <span class="text-slate-500">{{ __('Escolheu') }}</span>
                        <strong class="text-slate-800 ml-1">
                            {{ trans_choice(':n artigo|:n artigos', array_sum($escolhas), ['n' => array_sum($escolhas)]) }}
                        </strong>
                        @if($definicoes->menu_show_prices)
                            <span class="text-slate-400 mx-1">·</span>
                            <strong style="color: {{ $cor }}">
                                {{ number_format($this->totalEscolhido, 2, ',', '.') }} Kz
                            </strong>
                        @endif
                    </div>
                    <button wire:click="limpar" class="text-xs text-slate-400 underline">{{ __('Limpar') }}</button>
                </div>

                @error('pedido')
                    <p class="mb-2 rounded-xl bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">{{ $message }}</p>
                @enderror

                {{-- PEDIR AQUI MESMO. Só aparece com os pedidos ligados E com a
                     mesa reconhecida: um pedido sem destino não serve a
                     ninguém, e a mesa é a única coisa que amarra este pedido a
                     alguém que está mesmo lá dentro. --}}
                @if($definicoes->menu_orders_enabled && $mesa)
                    <div class="mb-2 grid gap-2 sm:grid-cols-2">
                        <input wire:model="nome" placeholder="{{ __('O seu nome (opcional)') }}"
                               class="rounded-xl border-slate-300 text-sm">
                        <input wire:model="observacoes" placeholder="{{ __('Alguma indicação? (opcional)') }}"
                               class="rounded-xl border-slate-300 text-sm">
                    </div>
                    <button wire:click="enviarPedido" wire:loading.attr="disabled"
                            class="flex items-center justify-center gap-2 w-full py-3.5 mb-2 rounded-2xl text-white font-bold shadow-lg transition disabled:opacity-60"
                            style="background: {{ $cor }}">
                        <i class="fas fa-paper-plane"></i>
                        <span wire:loading.remove wire:target="enviarPedido">{{ __('Enviar pedido para a cozinha') }}</span>
                        <span wire:loading wire:target="enviarPedido">{{ __('A enviar…') }}</span>
                    </button>
                @endif

                @if($this->linkDoWhatsapp)
                    {{-- O pedido vai como MENSAGEM, com a mesa à cabeça. Não
                         toca na base de dados: quem lança a comanda é quem está
                         no restaurante, e essa é a diferença entre um pedido e
                         uma encomenda que ninguém confirmou. --}}
                    <a href="{{ $this->linkDoWhatsapp }}" target="_blank" rel="noopener"
                       class="flex items-center justify-center gap-2 w-full py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-lg transition">
                        <i class="fab fa-whatsapp text-lg"></i>
                        {{ __('Enviar pedido por WhatsApp') }}
                    </a>
                @else
                    <p class="text-center text-xs text-slate-500 py-3">
                        {{ __('Mostre esta lista ao empregado para fazer o pedido.') }}
                    </p>
                @endif
            </div>
        </div>
    @endif
</div>
