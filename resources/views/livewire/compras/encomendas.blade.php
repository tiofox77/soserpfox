<div>
    @php
        $coresEstado = [
            'rascunho' => 'bg-gray-100 text-gray-700',
            'enviada' => 'bg-blue-100 text-blue-800',
            'confirmada' => 'bg-indigo-100 text-indigo-800',
            'parcial' => 'bg-amber-100 text-amber-800',
            'recebida' => 'bg-green-100 text-green-800',
            'cancelada' => 'bg-gray-200 text-gray-600',
        ];
    @endphp

    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-lime-600 to-green-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-clipboard-list text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">{{ __('Encomendas de Compra') }}</h2>
                    <p class="text-lime-100 text-sm">{{ __('O que foi pedido ao fornecedor, o que já chegou, o que falta') }}</p>
                </div>
            </div>
            <button wire:click="novaEncomenda"
                    class="bg-white/20 hover:bg-white/30 backdrop-blur-sm px-6 py-3 rounded-xl font-semibold transition">
                <i class="fas fa-plus mr-2"></i>{{ __('Nova encomenda') }}
            </button>
        </div>
    </div>

    <!-- Resumo -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Em curso') }}</p>
            <p class="text-3xl font-bold text-blue-600">{{ $resumo['abertas'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Atrasadas') }}</p>
            <p class="text-3xl font-bold {{ $resumo['atrasadas'] > 0 ? 'text-red-600' : 'text-gray-300' }}">{{ $resumo['atrasadas'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Por facturar') }}</p>
            <p class="text-3xl font-bold text-amber-600">{{ $resumo['por_facturar'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Valor em curso') }}</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($resumo['valor_aberto'], 2, ',', '.') }}</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl shadow-lg p-4 mb-6 flex flex-wrap gap-3">
        <div class="flex-1 min-w-[220px] relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text" wire:model.live.debounce.400ms="procurar"
                   placeholder="{{ __('Número, fornecedor ou artigo...') }}"
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-lime-500 focus:border-lime-500">
        </div>
        <select wire:model.live="estado" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-lime-500">
            <option value="todos">{{ __('Todos os estados') }}</option>
            @foreach(\App\Models\Compras\Encomenda::ESTADOS as $chave => $rotulo)
                <option value="{{ $chave }}">{{ __($rotulo) }}</option>
            @endforeach
        </select>
    </div>

    <!-- Lista -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        @forelse($encomendas as $enc)
            @php
                $pedido = (float) $enc->itens->sum('quantidade');
                $chegou = (float) $enc->itens->sum('quantidade_recebida');
                $percent = $pedido > 0 ? min(100, round($chegou / $pedido * 100)) : 0;
            @endphp
            <div class="group px-6 py-4 border-b border-gray-100 hover:bg-gray-50 transition flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[240px]">
                    <div class="flex items-center gap-2 flex-wrap">
                        <button wire:click="$set('verId', {{ $enc->id }})" class="font-bold text-gray-900 hover:text-lime-700">
                            {{ $enc->numero }}
                        </button>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $coresEstado[$enc->estado] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ __($enc->estadoRotulo()) }}
                        </span>
                        @if($enc->estaAtrasada())
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                <i class="fas fa-clock mr-1"></i>{{ __('Atrasada') }}
                            </span>
                        @endif
                        @if($enc->purchase_invoice_id)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">
                                <i class="fas fa-file-invoice mr-1"></i>{{ __('Facturada') }}
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $enc->fornecedor?->name }}
                        @if($enc->entrega_prevista) · {{ __('entrega') }} {{ $enc->entrega_prevista->format('d/m/Y') }} @endif
                    </p>
                    @if(in_array($enc->estado, ['parcial', 'recebida']) || $chegou > 0)
                        <div class="mt-2 w-40 bg-gray-100 rounded-full h-1.5">
                            <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ $percent }}%"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">{{ $percent }}% {{ __('recebido') }}</p>
                    @endif
                </div>

                <div class="text-right min-w-[130px]">
                    <p class="font-bold text-gray-900">{{ number_format((float) $enc->total, 2, ',', '.') }}</p>
                    <p class="text-xs text-gray-400">{{ $enc->moeda }}</p>
                </div>

                <div class="flex items-center gap-2 lg:opacity-0 lg:group-hover:opacity-100 transition">
                    <button wire:click="$set('verId', {{ $enc->id }})"
                            class="p-2 text-gray-400 hover:text-lime-600 hover:bg-lime-50 rounded-lg" title="{{ __('Ver') }}">
                        <i class="fas fa-eye"></i>
                    </button>
                    @if($enc->estado === 'rascunho')
                        <button wire:click="editar({{ $enc->id }})"
                                class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg" title="{{ __('Editar') }}">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button wire:click="enviar({{ $enc->id }})"
                                class="px-3 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg">
                            <i class="fas fa-paper-plane mr-1"></i>{{ __('Enviar') }}
                        </button>
                    @endif
                    @if($enc->estado === 'enviada')
                        <button wire:click="confirmar({{ $enc->id }})"
                                class="px-3 py-2 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg">
                            <i class="fas fa-check mr-1"></i>{{ __('Confirmar') }}
                        </button>
                    @endif
                    @if(in_array($enc->estado, ['enviada', 'confirmada', 'parcial']) && auth()->user()->can('compras.encomendas.receber'))
                        <button wire:click="abrirRecepcao({{ $enc->id }})"
                                class="px-3 py-2 text-xs font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg">
                            <i class="fas fa-dolly mr-1"></i>{{ __('Receber') }}
                        </button>
                    @endif
                    @if(in_array($enc->estado, ['parcial', 'recebida']) && !$enc->purchase_invoice_id)
                        <button wire:click="facturar({{ $enc->id }})"
                                class="px-3 py-2 text-xs font-semibold text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-lg">
                            <i class="fas fa-file-invoice mr-1"></i>{{ __('Facturar') }}
                        </button>
                    @endif
                    @if($enc->estado !== 'cancelada')
                        <button wire:click="$set('confirmarCancelarId', {{ $enc->id }})"
                                class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg" title="{{ __('Cancelar') }}">
                            <i class="fas fa-ban"></i>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <i class="fas fa-clipboard-list text-5xl text-gray-200 mb-4"></i>
                <p class="text-gray-500 font-semibold">{{ __('Ainda não há encomendas') }}</p>
                <p class="text-sm text-gray-400 mt-1">{{ __('Crie uma de raiz, ou puxe as linhas de uma requisição aprovada.') }}</p>
            </div>
        @endforelse

        @if($encomendas->hasPages())
            <div class="px-6 py-4">{{ $encomendas->links() }}</div>
        @endif
    </div>

    <!-- ─── Modal: criar/editar ─────────────────────────────────────────── -->
    @if($showForm)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl my-8">
                <div class="px-6 py-4 bg-gradient-to-r from-lime-600 to-green-600 rounded-t-2xl text-white flex items-center justify-between">
                    <h3 class="text-lg font-bold">
                        <i class="fas fa-clipboard-list mr-2"></i>
                        {{ $editandoId ? __('Editar encomenda') : __('Nova encomenda') }}
                    </h3>
                    <button wire:click="$set('showForm', false)" class="text-white/80 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="p-6 space-y-5">
                    <div class="grid md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                {{ __('Fornecedor') }} <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="supplierId" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                                <option value="">{{ __('— escolher —') }}</option>
                                @foreach($fornecedores as $f)
                                    <option value="{{ $f->id }}">{{ $f->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Armazém de entrada') }}</label>
                            <select wire:model="warehouseId" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                                <option value="">{{ __('— escolher —') }}</option>
                                @foreach($armazens as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Data') }}</label>
                            <input type="date" wire:model="dataEncomenda" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Entrega prevista') }}</label>
                            <input type="date" wire:model="entregaPrevista" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                        </div>
                    </div>

                    <!-- Puxar de uma requisição aprovada -->
                    @if(!$editandoId && $this->requisicoesAbertas->isNotEmpty())
                        <div class="bg-amber-50 border border-amber-100 rounded-xl p-4">
                            <p class="text-sm font-semibold text-amber-900 mb-2">
                                <i class="fas fa-file-alt mr-1"></i>{{ __('Requisições aprovadas à espera de encomenda') }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($this->requisicoesAbertas as $r)
                                    <button type="button" wire:click="daRequisicao({{ $r->id }})"
                                            class="px-3 py-1.5 bg-white border border-amber-200 rounded-lg text-sm font-medium text-amber-900 hover:bg-amber-100">
                                        {{ $r->numero }}
                                        <span class="text-amber-500 text-xs">
                                            ({{ $r->itens->filter(fn($i) => $i->porEncomendar() > 0)->count() }} {{ __('linhas') }})
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                            <p class="text-xs text-amber-700 mt-2">
                                {{ __('Escolha o fornecedor acima e clique numa — cria a encomenda só com o que falta.') }}
                            </p>
                        </div>
                    @endif

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
                            <i class="fas fa-plus-circle mr-1"></i>{{ __('Linha livre (fora do catálogo)') }}
                        </button>
                    </div>

                    <!-- Linhas -->
                    @if(count($linhas) > 0)
                        <div class="border border-gray-200 rounded-xl overflow-x-auto">
                            <table class="w-full text-sm min-w-[640px]">
                                <thead class="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold">{{ __('Artigo') }}</th>
                                        <th class="px-3 py-2 w-24 text-left font-semibold">{{ __('Qtd') }}</th>
                                        <th class="px-3 py-2 w-32 text-left font-semibold">{{ __('Preço') }}</th>
                                        <th class="px-3 py-2 w-24 text-left font-semibold">{{ __('Desc. %') }}</th>
                                        <th class="px-3 py-2 w-12"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($linhas as $i => $linha)
                                        <tr>
                                            <td class="px-4 py-2">
                                                <input type="text" wire:model="linhas.{{ $i }}.descricao"
                                                       placeholder="{{ __('Descrição') }}"
                                                       class="w-full px-3 py-1.5 border border-gray-200 rounded-lg">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" step="0.001" min="0" wire:model="linhas.{{ $i }}.quantidade"
                                                       class="w-full px-3 py-1.5 border border-gray-200 rounded-lg">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" step="0.01" min="0" wire:model="linhas.{{ $i }}.preco_unitario"
                                                       class="w-full px-3 py-1.5 border border-gray-200 rounded-lg">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" step="0.01" min="0" max="100" wire:model="linhas.{{ $i }}.desconto_percent"
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
                        <p class="text-xs text-gray-400">
                            <i class="fas fa-info-circle mr-1"></i>
                            {{ __('O IVA de cada linha é resolvido pelo artigo no momento de gravar — não se escreve aqui.') }}
                        </p>
                    @else
                        <p class="text-sm text-gray-400 text-center py-6">
                            {{ __('Sem artigos ainda — procure no catálogo acima.') }}
                        </p>
                    @endif

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Notas') }}</label>
                        <textarea wire:model="notas" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl"></textarea>
                    </div>
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

    <!-- ─── Modal: recepção ─────────────────────────────────────────────── -->
    @if($receberId && $this->recepcao)
        @php $r = $this->recepcao; @endphp
        <div class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-8">
                <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-emerald-600 rounded-t-2xl text-white flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold"><i class="fas fa-dolly mr-2"></i>{{ __('Receber mercadoria') }}</h3>
                        <p class="text-green-100 text-sm">{{ $r->numero }} · {{ $r->fornecedor?->name }}</p>
                    </div>
                    <button wire:click="$set('receberId', null)" class="text-white/80 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-sm text-amber-900">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        {{ __('Confirmar dá ENTRADA DE STOCK real em') }} <strong>{{ $r->warehouse?->name ?? __('(sem armazém)') }}</strong>.
                        {{ __('Escreva o que chegou mesmo — não o que foi pedido.') }}
                    </div>

                    <div class="border border-gray-200 rounded-xl overflow-x-auto">
                        <table class="w-full text-sm min-w-[560px]">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">{{ __('Artigo') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ __('Encomendado') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ __('Já recebido') }}</th>
                                    <th class="px-3 py-2 w-32 text-left font-semibold">{{ __('Chegou agora') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($r->itens as $item)
                                    <tr class="{{ $item->porReceber() <= 0 ? 'opacity-50' : '' }}">
                                        <td class="px-4 py-2">
                                            <p class="text-gray-900">{{ $item->descricao }}</p>
                                            @if(!$item->product_id)
                                                <p class="text-xs text-amber-600">
                                                    <i class="fas fa-info-circle mr-1"></i>{{ __('fora do catálogo — não move stock') }}
                                                </p>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-500">
                                            {{ rtrim(rtrim(number_format((float) $item->quantidade, 3, ',', '.'), '0'), ',') }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-500">
                                            {{ rtrim(rtrim(number_format((float) $item->quantidade_recebida, 3, ',', '.'), '0'), ',') }}
                                        </td>
                                        <td class="px-3 py-2">
                                            @if($item->porReceber() > 0)
                                                <input type="number" step="0.001" min="0" max="{{ $item->porReceber() }}"
                                                       wire:model="recebido.{{ $item->id }}"
                                                       class="w-full px-3 py-1.5 border border-gray-200 rounded-lg">
                                            @else
                                                <span class="text-xs text-green-600 font-semibold">
                                                    <i class="fas fa-check mr-1"></i>{{ __('completo') }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                    <button wire:click="$set('receberId', null)"
                            class="px-5 py-2.5 text-gray-600 font-semibold hover:text-gray-900">{{ __('Cancelar') }}</button>
                    <button wire:click="receber" wire:loading.attr="disabled"
                            class="px-6 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl font-semibold hover:from-green-700 hover:to-emerald-700 shadow-lg">
                        <i class="fas fa-dolly mr-2"></i>{{ __('Confirmar entrada') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ─── Modal: ver detalhe ──────────────────────────────────────────── -->
    @if($verId && $this->detalhe)
        @php $d = $this->detalhe; @endphp
        <div class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl my-8">
                <div class="px-6 py-4 bg-gradient-to-r from-lime-600 to-green-600 rounded-t-2xl text-white flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold">{{ $d->numero }}</h3>
                        <p class="text-lime-100 text-sm">
                            {{ __($d->estadoRotulo()) }} · {{ $d->fornecedor?->name }}
                            @if($d->requisicao) · {{ __('da requisição') }} {{ $d->requisicao->numero }} @endif
                        </p>
                    </div>
                    <button wire:click="$set('verId', null)" class="text-white/80 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-semibold">{{ __('Data') }}</p>
                            <p class="text-gray-900">{{ $d->data_encomenda?->format('d/m/Y') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-semibold">{{ __('Entrega prevista') }}</p>
                            <p class="{{ $d->estaAtrasada() ? 'text-red-600 font-semibold' : 'text-gray-900' }}">
                                {{ $d->entrega_prevista?->format('d/m/Y') ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-semibold">{{ __('Armazém') }}</p>
                            <p class="text-gray-900">{{ $d->warehouse?->name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase font-semibold">{{ __('Criada por') }}</p>
                            <p class="text-gray-900">{{ $d->autor?->name }}</p>
                        </div>
                    </div>

                    @if($d->factura)
                        <div class="bg-purple-50 border border-purple-100 rounded-xl p-4 text-sm">
                            <p class="font-semibold text-purple-900">
                                <i class="fas fa-file-invoice mr-1"></i>{{ __('Factura de compra') }} {{ $d->factura->invoice_number }}
                            </p>
                            <p class="text-purple-700 text-xs mt-1">
                                {{ __('A mercadoria já tinha entrado na recepção — esta factura não repete a entrada de stock.') }}
                            </p>
                        </div>
                    @endif

                    <div class="border border-gray-200 rounded-xl overflow-x-auto">
                        <table class="w-full text-sm min-w-[600px]">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">{{ __('Artigo') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ __('Qtd') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ __('Recebido') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ __('Preço') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ __('IVA') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ __('Total') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($d->itens as $item)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-900">{{ $item->descricao }}</td>
                                        <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format((float) $item->quantidade, 3, ',', '.'), '0'), ',') }}</td>
                                        <td class="px-3 py-2 text-right {{ $item->porReceber() > 0 ? 'text-amber-600' : 'text-green-600' }}">
                                            {{ rtrim(rtrim(number_format((float) $item->quantidade_recebida, 3, ',', '.'), '0'), ',') }}
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ number_format((float) $item->preco_unitario, 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right text-gray-500">{{ rtrim(rtrim(number_format((float) $item->tax_rate, 2, ',', '.'), '0'), ',') }}%</td>
                                        <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $item->total, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 font-semibold text-gray-900">
                                @if((float) $d->desconto > 0)
                                    <tr class="text-gray-500 font-normal">
                                        <td colspan="5" class="px-4 py-2 text-right">{{ __('Desconto nas linhas') }}</td>
                                        <td class="px-3 py-2 text-right">−{{ number_format((float) $d->desconto, 2, ',', '.') }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td colspan="5" class="px-4 py-2 text-right">{{ __('Subtotal') }} <span class="font-normal text-gray-400 text-xs">({{ __('já com desconto') }})</span></td>
                                    <td class="px-3 py-2 text-right">{{ number_format((float) $d->subtotal, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="px-4 py-2 text-right">{{ __('IVA') }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format((float) $d->imposto, 2, ',', '.') }}</td>
                                </tr>
                                <tr class="text-lg">
                                    <td colspan="5" class="px-4 py-2 text-right">{{ __('Total') }}</td>
                                    <td class="px-3 py-2 text-right text-lime-700">{{ number_format((float) $d->total, 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if($d->notas)
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">{{ __('Notas') }}</p>
                            <p class="text-gray-800 whitespace-pre-line">{{ $d->notas }}</p>
                        </div>
                    @endif
                </div>

                <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                    <button wire:click="$set('verId', null)"
                            class="px-5 py-2.5 text-gray-600 font-semibold hover:text-gray-900">{{ __('Fechar') }}</button>
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
                <h3 class="text-lg font-bold text-gray-900">{{ __('Cancelar esta encomenda?') }}</h3>
                <p class="text-sm text-gray-500 mt-2">
                    {{ __('Nada se apaga. Se já tiver entrado mercadoria, o cancelamento é recusado — isso trata-se por devolução.') }}
                </p>
                <div class="flex justify-center gap-3 mt-6">
                    <button wire:click="$set('confirmarCancelarId', null)"
                            class="px-5 py-2.5 text-gray-600 font-semibold hover:text-gray-900">{{ __('Voltar') }}</button>
                    <button wire:click="cancelar"
                            class="px-6 py-2.5 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700">
                        {{ __('Cancelar encomenda') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
