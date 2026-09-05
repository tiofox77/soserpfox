<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-lime-600 to-green-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-file-alt text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">{{ __('Requisições de Compra') }}</h2>
                    <p class="text-lime-100 text-sm">{{ __('O pedido interno: quem precisa, do quê, e para quando') }}</p>
                </div>
            </div>
            <button wire:click="novaRequisicao"
                    class="bg-white/20 hover:bg-white/30 backdrop-blur-sm px-6 py-3 rounded-xl font-semibold transition">
                <i class="fas fa-plus mr-2"></i>{{ __('Nova requisição') }}
            </button>
        </div>
    </div>

    <!-- Resumo -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Por decidir') }}</p>
            <p class="text-3xl font-bold text-amber-600">{{ $resumo['submetidas'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Aprovadas') }}</p>
            <p class="text-3xl font-bold text-green-600">{{ $resumo['aprovadas'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Rascunhos') }}</p>
            <p class="text-3xl font-bold text-gray-500">{{ $resumo['rascunhos'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Total') }}</p>
            <p class="text-3xl font-bold text-gray-900">{{ $resumo['total'] }}</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl shadow-lg p-4 mb-6 flex flex-wrap gap-3">
        <div class="flex-1 min-w-[220px] relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text" wire:model.live.debounce.400ms="procurar"
                   placeholder="{{ __('Número, artigo ou justificação...') }}"
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-lime-500 focus:border-lime-500">
        </div>
        <select wire:model.live="estado" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-lime-500">
            <option value="todos">{{ __('Todos os estados') }}</option>
            @foreach(\App\Models\Compras\Requisicao::ESTADOS as $chave => $rotulo)
                <option value="{{ $chave }}">{{ __($rotulo) }}</option>
            @endforeach
        </select>
    </div>

    <!-- Lista -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        @forelse($requisicoes as $req)
            <div class="group px-6 py-4 border-b border-gray-100 hover:bg-gray-50 transition flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[220px]">
                    <div class="flex items-center gap-2 flex-wrap">
                        <button wire:click="$set('verId', {{ $req->id }})" class="font-bold text-gray-900 hover:text-lime-700">
                            {{ $req->numero }}
                        </button>
                        @php
                            $cores = [
                                'rascunho' => 'bg-gray-100 text-gray-700',
                                'submetida' => 'bg-amber-100 text-amber-800',
                                'aprovada' => 'bg-green-100 text-green-800',
                                'rejeitada' => 'bg-red-100 text-red-800',
                                'encomendada' => 'bg-blue-100 text-blue-800',
                                'cancelada' => 'bg-gray-200 text-gray-600',
                            ];
                        @endphp
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $cores[$req->estado] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ __($req->estadoRotulo()) }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $req->itens->count() }} {{ __('artigo(s)') }}
                        @if($req->warehouse) · {{ $req->warehouse->name }} @endif
                        @if($req->necessaria_em) · {{ __('para') }} {{ $req->necessaria_em->format('d/m/Y') }} @endif
                    </p>
                </div>

                <div class="text-right text-sm text-gray-500 min-w-[130px]">
                    <p>{{ $req->autor?->name }}</p>
                    <p class="text-xs">{{ $req->created_at->format('d/m/Y H:i') }}</p>
                </div>

                <div class="flex items-center gap-2 lg:opacity-0 lg:group-hover:opacity-100 transition">
                    <button wire:click="$set('verId', {{ $req->id }})"
                            class="p-2 text-gray-400 hover:text-lime-600 hover:bg-lime-50 rounded-lg" title="{{ __('Ver') }}">
                        <i class="fas fa-eye"></i>
                    </button>
                    @if($req->estado === 'rascunho')
                        <button wire:click="editar({{ $req->id }})"
                                class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg" title="{{ __('Editar') }}">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button wire:click="submeter({{ $req->id }})"
                                class="px-3 py-2 text-xs font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-lg">
                            <i class="fas fa-paper-plane mr-1"></i>{{ __('Submeter') }}
                        </button>
                    @endif
                    @if($req->estado === 'submetida' && auth()->user()->can('compras.requisicoes.decidir'))
                        <button wire:click="aprovar({{ $req->id }})"
                                class="px-3 py-2 text-xs font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg">
                            <i class="fas fa-check mr-1"></i>{{ __('Aprovar') }}
                        </button>
                        <button wire:click="abrirRecusa({{ $req->id }})"
                                class="px-3 py-2 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 rounded-lg">
                            <i class="fas fa-times mr-1"></i>{{ __('Recusar') }}
                        </button>
                    @endif
                    @if(!in_array($req->estado, ['encomendada', 'cancelada']))
                        <button wire:click="$set('confirmarCancelarId', {{ $req->id }})"
                                class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg" title="{{ __('Cancelar') }}">
                            <i class="fas fa-ban"></i>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <i class="fas fa-file-alt text-5xl text-gray-200 mb-4"></i>
                <p class="text-gray-500 font-semibold">{{ __('Ainda não há requisições') }}</p>
                <p class="text-sm text-gray-400 mt-1">{{ __('Comece por pedir o que falta — a encomenda vem depois.') }}</p>
            </div>
        @endforelse

        @if($requisicoes->hasPages())
            <div class="px-6 py-4">{{ $requisicoes->links() }}</div>
        @endif
    </div>

    <!-- ─── Modal: criar/editar ─────────────────────────────────────────── -->
    @if($showForm)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl my-8">
                <div class="px-6 py-4 bg-gradient-to-r from-lime-600 to-green-600 rounded-t-2xl text-white flex items-center justify-between">
                    <h3 class="text-lg font-bold">
                        <i class="fas fa-file-alt mr-2"></i>
                        {{ $editandoId ? __('Editar requisição') : __('Nova requisição') }}
                    </h3>
                    <button wire:click="$set('showForm', false)" class="text-white/80 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="p-6 space-y-5">
                    <div class="grid md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Armazém de destino') }}</label>
                            <select wire:model="warehouseId" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                                <option value="">{{ __('— sem destino —') }}</option>
                                @foreach($armazens as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Necessária em') }}</label>
                            <input type="date" wire:model="necessariaEm" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Justificação') }}</label>
                            <input type="text" wire:model="justificacao" placeholder="{{ __('Para quê?') }}"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                        </div>
                    </div>

                    <!-- Escolher do catálogo -->
                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('Procurar no catálogo') }}</label>
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.300ms="procuraArtigo"
                                   placeholder="{{ __('Nome ou código do artigo...') }}"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                            @if($this->sugestoes->isNotEmpty())
                                <div class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-xl max-h-64 overflow-y-auto">
                                    @foreach($this->sugestoes as $s)
                                        <button type="button" wire:click="adicionarDoCatalogo({{ $s->id }})"
                                                class="w-full text-left px-4 py-2.5 hover:bg-lime-50 border-b border-gray-50 last:border-0">
                                            <span class="font-medium text-gray-900">{{ $s->name }}</span>
                                            @if($s->code)<span class="text-xs text-gray-400 ml-2">{{ $s->code }}</span>@endif
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <button type="button" wire:click="adicionarLinhaLivre"
                                class="mt-3 text-sm font-semibold text-lime-700 hover:text-lime-900">
                            <i class="fas fa-plus-circle mr-1"></i>{{ __('Pedir algo que não está no catálogo') }}
                        </button>
                    </div>

                    <!-- Linhas -->
                    @if(count($linhas) > 0)
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold">{{ __('Artigo') }}</th>
                                        <th class="px-3 py-2 w-28 text-left font-semibold">{{ __('Qtd') }}</th>
                                        <th class="px-3 py-2 w-24 text-left font-semibold">{{ __('Unid.') }}</th>
                                        <th class="px-3 py-2 w-36 text-left font-semibold">{{ __('Custo estimado') }}</th>
                                        <th class="px-3 py-2 w-12"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($linhas as $i => $linha)
                                        <tr>
                                            <td class="px-4 py-2">
                                                <input type="text" wire:model="linhas.{{ $i }}.descricao"
                                                       placeholder="{{ __('O que precisa') }}"
                                                       class="w-full px-3 py-1.5 border border-gray-200 rounded-lg">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" step="0.001" min="0" wire:model="linhas.{{ $i }}.quantidade"
                                                       class="w-full px-3 py-1.5 border border-gray-200 rounded-lg">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="text" wire:model="linhas.{{ $i }}.unidade"
                                                       class="w-full px-3 py-1.5 border border-gray-200 rounded-lg">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" step="0.01" min="0" wire:model="linhas.{{ $i }}.custo_estimado"
                                                       class="w-full px-3 py-1.5 border border-gray-200 rounded-lg">
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button type="button" wire:click="removerLinha({{ $i }})"
                                                        class="text-gray-300 hover:text-red-600">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-gray-400 text-center py-6">
                            {{ __('Sem artigos ainda — procure no catálogo acima ou peça uma linha livre.') }}
                        </p>
                    @endif
                </div>

                <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                    <button wire:click="$set('showForm', false)"
                            class="px-5 py-2.5 text-gray-600 font-semibold hover:text-gray-900">{{ __('Cancelar') }}</button>
                    <button wire:click="guardar" wire:loading.attr="disabled"
                            class="px-6 py-2.5 bg-gradient-to-r from-lime-600 to-green-600 text-white rounded-xl font-semibold hover:from-lime-700 hover:to-green-700 shadow-lg">
                        <i class="fas fa-save mr-2"></i>{{ __('Guardar rascunho') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ─── Modal: ver detalhe ──────────────────────────────────────────── -->
    @if($verId && $this->detalhe)
        @php $d = $this->detalhe; @endphp
        <div class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-8">
                <div class="px-6 py-4 bg-gradient-to-r from-lime-600 to-green-600 rounded-t-2xl text-white flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold">{{ $d->numero }}</h3>
                        <p class="text-lime-100 text-sm">{{ __($d->estadoRotulo()) }} · {{ $d->autor?->name }}</p>
                    </div>
                    <button wire:click="$set('verId', null)" class="text-white/80 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="p-6 space-y-5">
                    @if($d->justificacao)
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">{{ __('Justificação') }}</p>
                            <p class="text-gray-800">{{ $d->justificacao }}</p>
                        </div>
                    @endif

                    @if($d->estado === 'rejeitada' && $d->motivo_recusa)
                        <div class="bg-red-50 border border-red-100 rounded-xl p-4">
                            <p class="text-xs font-semibold text-red-600 uppercase mb-1">{{ __('Motivo da recusa') }}</p>
                            <p class="text-red-900">{{ $d->motivo_recusa }}</p>
                            <p class="text-xs text-red-500 mt-2">
                                {{ $d->decisor?->name }} · {{ $d->decidida_em?->format('d/m/Y H:i') }}
                            </p>
                        </div>
                    @endif

                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">{{ __('Artigo') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ __('Pedido') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ __('Encomendado') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ __('Custo est.') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($d->itens as $item)
                                    <tr>
                                        <td class="px-4 py-2">
                                            <p class="text-gray-900">{{ $item->descricao }}</p>
                                            @if($item->notas)<p class="text-xs text-gray-400">{{ $item->notas }}</p>@endif
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format((float) $item->quantidade, 3, ',', '.'), '0'), ',') }} {{ $item->unidade }}</td>
                                        <td class="px-3 py-2 text-right {{ $item->porEncomendar() > 0 ? 'text-amber-600' : 'text-green-600' }}">
                                            {{ rtrim(rtrim(number_format((float) $item->quantidade_encomendada, 3, ',', '.'), '0'), ',') }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-500">
                                            {{ $item->custo_estimado !== null ? number_format((float) $item->custo_estimado, 2, ',', '.') : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($d->encomendas->isNotEmpty())
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">{{ __('Encomendas geradas') }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($d->encomendas as $e)
                                    <span class="px-3 py-1 bg-blue-50 text-blue-700 rounded-lg text-sm font-medium">
                                        {{ $e->numero }} <span class="text-blue-400">· {{ __($e->estadoRotulo()) }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-between items-center gap-3">
                    <div class="text-sm text-gray-500">
                        @if($d->estado === 'aprovada')
                            <i class="fas fa-arrow-right text-green-600 mr-1"></i>
                            {{ __('Aprovada — gere a encomenda no ecrã de Encomendas.') }}
                        @endif
                    </div>
                    <button wire:click="$set('verId', null)"
                            class="px-5 py-2.5 text-gray-600 font-semibold hover:text-gray-900">{{ __('Fechar') }}</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ─── Modal: recusar ──────────────────────────────────────────────── -->
    @if($showRejeitar)
        <div class="fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
                <div class="px-6 py-4 bg-gradient-to-r from-red-600 to-rose-600 rounded-t-2xl text-white">
                    <h3 class="text-lg font-bold"><i class="fas fa-times-circle mr-2"></i>{{ __('Recusar requisição') }}</h3>
                </div>
                <div class="p-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        {{ __('Porquê?') }} <span class="text-red-500">*</span>
                    </label>
                    <textarea wire:model="motivoRecusa" rows="3"
                              placeholder="{{ __('Quem pediu vai ler isto.') }}"
                              class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-500"></textarea>
                </div>
                <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                    <button wire:click="$set('showRejeitar', false)"
                            class="px-5 py-2.5 text-gray-600 font-semibold hover:text-gray-900">{{ __('Voltar') }}</button>
                    <button wire:click="rejeitar"
                            class="px-6 py-2.5 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700">
                        {{ __('Recusar') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ─── Modal: cancelar ─────────────────────────────────────────────── -->
    @if($confirmarCancelarId)
        <div class="fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-ban text-2xl text-red-600"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900">{{ __('Cancelar esta requisição?') }}</h3>
                <p class="text-sm text-gray-500 mt-2">
                    {{ __('Nada se apaga — a requisição fica cancelada no histórico.') }}
                </p>
                <div class="flex justify-center gap-3 mt-6">
                    <button wire:click="$set('confirmarCancelarId', null)"
                            class="px-5 py-2.5 text-gray-600 font-semibold hover:text-gray-900">{{ __('Voltar') }}</button>
                    <button wire:click="cancelar"
                            class="px-6 py-2.5 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700">
                        {{ __('Cancelar requisição') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
