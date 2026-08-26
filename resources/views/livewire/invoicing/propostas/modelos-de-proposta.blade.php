<div>
    {{-- Cabeçalho (padrão da faturação) --}}
    <div class="mb-6 bg-gradient-to-r from-violet-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-file-invoice text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Modelos de Proposta</h2>
                    <p class="text-violet-100 text-sm">Desenhe uma vez, use em todos os orçamentos</p>
                </div>
            </div>
            @can('invoicing.sales.quotes.create')
                <button wire:click="$set('mostrarNovo', true)"
                        class="bg-white text-violet-700 px-5 py-2.5 rounded-xl font-semibold hover:bg-violet-50 transition shadow">
                    <i class="fas fa-plus mr-2"></i>Novo modelo
                </button>
            @endcan
        </div>
    </div>

    {{-- Explicação curta: sem ela, "modelo de proposta" não diz a ninguém o
         que ganha em usar isto. --}}
    @if($modelos->total() === 0)
        <div class="mb-6 bg-violet-50 border border-violet-200 rounded-2xl p-5 text-sm text-violet-900">
            <p class="mb-2">
                <strong>Um orçamento tem os números. Uma proposta ganha o negócio.</strong>
            </p>
            <p class="text-violet-800">
                Aqui monta-se o documento à volta dos números — capa, âmbito do trabalho, prazos, equipa,
                condições. O que muda de cliente para cliente fica como <em>campo a preencher</em> e é escrito
                em cada orçamento; o resto sai sempre igual e sempre bem.
            </p>
        </div>
    @endif

    <div class="mb-4">
        <div class="relative max-w-md">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Procurar modelo..."
                   class="w-full pl-11 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-transparent">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @forelse($modelos as $m)
            <div class="bg-white rounded-2xl shadow hover:shadow-lg transition border {{ $m->is_default ? 'border-violet-400 ring-2 ring-violet-100' : 'border-gray-100' }} p-5 flex flex-col">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                             style="background: {{ $m->estilo('cor_principal') }}1a; color: {{ $m->estilo('cor_principal') }}">
                            <i class="fas fa-file-lines"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-bold text-gray-900 truncate">{{ $m->nome }}</h3>
                            <p class="text-xs text-gray-500">{{ count((array) $m->blocos) }} secções · usado {{ $m->orcamentos_count }}x</p>
                        </div>
                    </div>
                    @if($m->is_default)
                        <span class="bg-violet-100 text-violet-700 text-[10px] font-bold px-2 py-1 rounded-full whitespace-nowrap">PADRÃO</span>
                    @endif
                </div>

                @if($m->descricao)
                    <p class="text-sm text-gray-600 mb-4 line-clamp-2">{{ $m->descricao }}</p>
                @endif

                <div class="mt-auto pt-3 border-t border-gray-100 flex flex-wrap items-center gap-2">
                    @can('invoicing.sales.quotes.edit')
                        <a href="{{ route('invoicing.sales.quote-templates.edit', $m->id) }}"
                           class="px-3 py-1.5 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-xs font-semibold transition">
                            <i class="fas fa-pen-ruler mr-1"></i>Editar
                        </a>
                    @endcan

                    <a href="{{ route('invoicing.sales.quote-templates.preview', $m->id) }}" target="_blank"
                       class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold transition">
                        <i class="fas fa-eye mr-1"></i>Ver PDF
                    </a>

                    @can('invoicing.sales.quotes.create')
                        <button wire:click="duplicar({{ $m->id }})"
                                class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold transition">
                            <i class="fas fa-copy mr-1"></i>Duplicar
                        </button>
                    @endcan

                    @can('invoicing.sales.quotes.edit')
                        @unless($m->is_default)
                            <button wire:click="tornarPadrao({{ $m->id }})"
                                    class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold transition">
                                <i class="fas fa-star mr-1"></i>Tornar padrão
                            </button>
                        @endunless
                    @endcan

                    @can('invoicing.sales.quotes.delete')
                        <button wire:click="eliminar({{ $m->id }})"
                                wire:confirm="Eliminar &quot;{{ $m->nome }}&quot;? Os orçamentos já feitos com ele não mudam."
                                class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-xs font-semibold transition ml-auto">
                            <i class="fas fa-trash"></i>
                        </button>
                    @endcan
                </div>
            </div>
        @empty
            <div class="md:col-span-2 xl:col-span-3 bg-white rounded-2xl shadow p-12 text-center">
                <div class="w-20 h-20 bg-violet-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-file-invoice text-violet-400 text-3xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Ainda não há modelos</h3>
                <p class="text-gray-500 mb-5">Comece por um pronto a usar — depois muda o que quiser.</p>
                @can('invoicing.sales.quotes.create')
                    <button wire:click="$set('mostrarNovo', true)"
                            class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl font-semibold transition">
                        <i class="fas fa-plus mr-2"></i>Criar o primeiro
                    </button>
                @endcan
            </div>
        @endforelse
    </div>

    @if($modelos->hasPages())
        <div class="mt-6">{{ $modelos->links() }}</div>
    @endif

    {{-- Escolher por onde começar --}}
    @if($mostrarNovo)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 p-4">
            <div class="max-w-4xl mx-auto my-10 bg-white rounded-2xl shadow-2xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Por onde quer começar?</h3>
                        <p class="text-sm text-gray-500">Todos são copiados para a sua empresa e podem ser mudados.</p>
                    </div>
                    <button wire:click="$set('mostrarNovo', false)" class="text-gray-400 hover:text-gray-700 p-2">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($arranque as $chave => $a)
                        <button wire:click="criarDeArranque('{{ $chave }}')" wire:loading.attr="disabled"
                                class="text-left border-2 border-gray-200 hover:border-violet-400 rounded-2xl p-5 transition group disabled:opacity-50">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-11 h-11 rounded-xl flex items-center justify-center"
                                     style="background: {{ $a['cor'] }}1a; color: {{ $a['cor'] }}">
                                    <i class="fas {{ $a['icone'] }} text-lg"></i>
                                </div>
                                <h4 class="font-bold text-gray-900 group-hover:text-violet-700">{{ $a['nome'] }}</h4>
                            </div>
                            <p class="text-sm text-gray-600">{{ $a['descricao'] }}</p>
                        </button>
                    @endforeach

                    <button wire:click="criarVazio" wire:loading.attr="disabled"
                            class="text-left border-2 border-dashed border-gray-300 hover:border-violet-400 rounded-2xl p-5 transition group md:col-span-2 disabled:opacity-50">
                        <div class="flex items-center gap-3 mb-1">
                            <div class="w-11 h-11 rounded-xl bg-gray-100 text-gray-500 flex items-center justify-center">
                                <i class="fas fa-plus text-lg"></i>
                            </div>
                            <h4 class="font-bold text-gray-900 group-hover:text-violet-700">Começar do zero</h4>
                        </div>
                        <p class="text-sm text-gray-600">Só cliente, itens e totais. Monta o resto à sua maneira.</p>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
