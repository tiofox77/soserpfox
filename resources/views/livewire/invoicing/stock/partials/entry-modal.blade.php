{{-- Modal: Entrada de Stock em LOTE (cria/incrementa linhas em invoicing_stocks) --}}
@if($showEntryModal)
{{-- O clique no fundo só fecha ENQUANTO se está a compor a movimentação. No
     painel de sucesso não fecha nada: um toque ao lado apagava a referência do
     lote antes de o utilizador chegar a abrir o PDF. --}}
<div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 animate-fade-in"
     @if(blank($batchReference)) wire:click.self="closeEntryModal" @endif>
    <div class="bg-white rounded-2xl shadow-2xl w-[calc(100%-1rem)] sm:w-full max-w-3xl overflow-hidden flex flex-col max-h-[94vh] animate-scale-in">

        @php
            $opAddCount = collect($entryItems)->where('op', 'add')->count();
            $opSubCount = collect($entryItems)->where('op', 'sub')->count();
            $gravado    = filled($batchReference);
        @endphp
        {{-- Header --}}
        <div class="bg-gradient-to-r from-emerald-600 to-green-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-white font-bold text-lg flex items-center">
                <i class="fas {{ $gravado ? 'fa-circle-check' : 'fa-arrow-right-arrow-left' }} mr-2"></i>
                {{ $gravado ? 'Movimentação registada' : 'Movimentação de Stock' }}
                @if(!$gravado && count($entryItems) > 0)
                    <span class="ml-3 text-[11px] bg-white/20 px-2 py-0.5 rounded-full">
                        @if($opAddCount){{ $opAddCount }} +@endif@if($opAddCount && $opSubCount) · @endif@if($opSubCount){{ $opSubCount }} −@endif
                    </span>
                @endif
            </h3>
            <button wire:click="closeEntryModal" type="button"
                    class="btn-press text-white/80 hover:text-white w-9 h-9 inline-flex items-center justify-center rounded-lg hover:bg-white/10">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        @if($gravado)
            {{-- ===== Painel de sucesso: o documento do lote =====
                 O modal não fecha sozinho de propósito. A referência tem de ficar
                 à vista e o PDF abre por um link real — abri-lo por JS a seguir a
                 uma acção Livewire era bloqueado como popup. --}}
            <div class="p-6 space-y-4 overflow-y-auto">
                <div class="text-center py-2">
                    <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 flex items-center justify-center mb-3">
                        <i class="fas fa-check text-2xl text-emerald-600"></i>
                    </div>
                    <p class="text-gray-600 text-sm">Foram registados</p>
                    <p class="text-3xl font-extrabold text-gray-900">
                        {{ $batchOk }} <span class="text-lg font-bold text-gray-500">movimento(s)</span>
                    </p>
                    <p class="mt-2 inline-flex items-center gap-2 px-3 py-1 rounded-lg bg-gray-100 font-mono font-bold text-gray-800">
                        <i class="fas fa-hashtag text-gray-400 text-xs"></i>{{ $batchReference }}
                    </p>
                </div>

                @if(count($batchErrors) > 0)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                        <p class="text-xs font-bold text-amber-800 mb-1">
                            <i class="fas fa-triangle-exclamation mr-1"></i>
                            {{ count($batchErrors) }} linha(s) não foram registadas
                        </p>
                        <ul class="text-[11px] text-amber-800 list-disc list-inside space-y-0.5">
                            @foreach($batchErrors as $erro)
                                <li>{{ $erro }}</li>
                            @endforeach
                        </ul>
                        <p class="text-[11px] text-amber-700 mt-1.5">
                            O documento inclui apenas o que foi efectivamente registado.
                        </p>
                    </div>
                @endif

                <a href="{{ route('invoicing.stock.batch-pdf', ['reference' => $batchReference]) }}"
                   target="_blank" rel="noopener"
                   class="btn-press flex items-center justify-center gap-2 w-full px-5 py-3 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white rounded-xl font-bold shadow">
                    <i class="fas fa-file-pdf text-lg"></i> Abrir documento em PDF
                </a>

                <p class="text-[11px] text-gray-400 text-center">
                    Documento interno de conferência de armazém. Pode reimprimi-lo a qualquer
                    momento pela referência {{ $batchReference }}.
                </p>
            </div>

            <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex justify-end gap-2 border-t">
                <button type="button" wire:click="novaMovimentacao"
                        class="btn-press px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 font-semibold">
                    <i class="fas fa-plus mr-1"></i> Nova movimentação
                </button>
                <button type="button" wire:click="closeEntryModal"
                        class="btn-press px-5 py-2 bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-white rounded-lg font-bold shadow">
                    Concluir
                </button>
            </div>

        @else

        <div class="p-6 space-y-4 overflow-y-auto">

            {{-- Armazém + Notas --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-1">
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">
                        <i class="fas fa-warehouse mr-1 text-blue-600"></i> Armazém
                    </label>
                    <select wire:model="entryWarehouseId"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        <option value="">— Selecione —</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->name }}{{ $w->is_default ? ' (Default)' : '' }}</option>
                        @endforeach
                    </select>
                    @error('entryWarehouseId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">
                        <i class="fas fa-sticky-note mr-1 text-yellow-600"></i> Nota (aplicada a todos)
                    </label>
                    <input type="text" wire:model.defer="entryNotes" maxlength="500"
                           placeholder="Ex: Compra inicial, ajuste de inventário…"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            {{-- Procura de produtos --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">
                    <i class="fas fa-magnifying-glass mr-1 text-emerald-600"></i> Adicionar produto
                </label>
                <div class="relative">
                    <input type="text"
                           wire:model.live.debounce.300ms="entryProductSearch"
                           placeholder="Pesquisar por nome, código ou barcode…"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">

                    @if(strlen(trim($entryProductSearch)) > 0)
                        <div class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                            @forelse($this->entrySearchResults as $p)
                                <button type="button"
                                        wire:click="addEntryItem({{ $p->id }})"
                                        class="btn-press w-full text-left px-3 py-2 hover:bg-emerald-50 border-b border-gray-100 last:border-b-0 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900 text-sm truncate">{{ $p->name }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ $p->code ?: ($p->sku ?: $p->barcode ?: '—') }}
                                            @if($p->unit) · {{ $p->unit }} @endif
                                        </p>
                                    </div>
                                    <span class="text-xs text-emerald-600 font-bold shrink-0">
                                        <i class="fas fa-plus-circle"></i> Adicionar
                                    </span>
                                </button>
                            @empty
                                <div class="px-3 py-3 text-sm text-gray-500 text-center">
                                    <i class="fas fa-search-minus mr-1"></i> Nenhum produto encontrado
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>

            {{-- Tabela de items --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-bold text-gray-700">
                        <i class="fas fa-list-check mr-1 text-emerald-600"></i> Produtos a registar
                    </h4>
                    @if(count($entryItems) > 0)
                        <button wire:click="clearEntryItems" type="button" class="btn-press text-xs text-red-600 hover:underline px-2 py-1 rounded">
                            <i class="fas fa-trash mr-1"></i> Limpar tudo
                        </button>
                    @endif
                </div>

                @if(count($entryItems) === 0)
                    <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center text-gray-400">
                        <i class="fas fa-inbox text-3xl mb-2"></i>
                        <p class="text-sm">Pesquise e adicione produtos acima.</p>
                    </div>
                @else
                    {{-- Hint mobile: indica scroll horizontal --}}
                    <p class="md:hidden text-[11px] text-gray-400 mb-1 flex items-center gap-1">
                        <i class="fas fa-arrows-left-right"></i> Arraste lateralmente para ver mais
                    </p>
                    <div class="border border-gray-200 rounded-xl overflow-x-auto">
                        <table class="min-w-[720px] md:min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-[11px] font-bold text-gray-600 uppercase tracking-wider">Produto</th>
                                    <th class="px-3 py-2 text-center text-[11px] font-bold text-gray-600 uppercase tracking-wider w-28">Operação</th>
                                    <th class="px-3 py-2 text-center text-[11px] font-bold text-gray-600 uppercase tracking-wider w-28">Quantidade</th>
                                    <th class="px-3 py-2 text-center text-[11px] font-bold text-gray-600 uppercase tracking-wider w-32">Custo unit. (Kz)</th>
                                    <th class="px-3 py-2 text-center text-[11px] font-bold text-gray-600 uppercase tracking-wider w-32">Stock (atual → novo)</th>
                                    <th class="px-3 py-2 text-center text-[11px] font-bold text-gray-600 uppercase tracking-wider w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach($entryItems as $i => $item)
                                    @php
                                        $isSub = ($item['op'] ?? 'add') === 'sub';
                                        $delta = $isSub ? -1 : 1;
                                        $current = (float) ($item['current_qty'] ?? 0);
                                        $new = max(0, $current + $delta * (float) ($item['quantity'] ?? 0));
                                        $insufficient = $isSub && (float) ($item['quantity'] ?? 0) > $current;
                                    @endphp
                                    {{-- Uma actualização atrasada pode deixar uma
                                         linha sem produto (ver limparEntryItems).
                                         Ela é descartada do lado do servidor, mas
                                         o render pode apanhá-la a meio — e sem
                                         este `?? $i` a página rebentava aqui. --}}
                                    <tr wire:key="entry-item-{{ $item['product_id'] ?? 'x' . $i }}" class="{{ $isSub ? 'bg-red-50/40' : '' }}">
                                        <td class="px-3 py-2 align-middle">
                                            <p class="font-semibold text-gray-900 truncate">{{ $item['product_name'] }}</p>
                                            <p class="text-[11px] text-gray-500">
                                                <i class="fas fa-barcode mr-1"></i>{{ $item['product_code'] ?: '—' }}
                                                @if(!empty($item['unit'])) · {{ $item['unit'] }} @endif
                                            </p>
                                            @error('entryItems.'.$i.'.quantity') <p class="text-[11px] text-red-600">{{ $message }}</p> @enderror
                                        </td>
                                        {{-- Operacao: toggle + / − --}}
                                        <td class="px-3 py-2 align-middle text-center">
                                            <div class="inline-flex rounded-lg overflow-hidden border border-gray-300">
                                                <button type="button"
                                                        wire:click="$set('entryItems.{{ $i }}.op', 'add')"
                                                        class="btn-press px-2.5 py-1.5 text-sm font-bold transition {{ !$isSub ? 'bg-emerald-600 text-white' : 'bg-white text-gray-500 hover:bg-emerald-50' }}"
                                                        title="Adicionar">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <button type="button"
                                                        wire:click="$set('entryItems.{{ $i }}.op', 'sub')"
                                                        class="btn-press px-2.5 py-1.5 text-sm font-bold transition border-l border-gray-300 {{ $isSub ? 'bg-red-600 text-white' : 'bg-white text-gray-500 hover:bg-red-50' }}"
                                                        title="Subtrair">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 align-middle text-center">
                                            <input type="number" min="0" step="0.01"
                                                   wire:model.live.debounce.500ms="entryItems.{{ $i }}.quantity"
                                                   class="w-full px-2 py-1.5 border rounded-lg text-center font-bold focus:ring-2 focus:ring-emerald-500 {{ $insufficient ? 'border-red-400 text-red-700 bg-red-50' : 'border-gray-300' }}">
                                        </td>
                                        <td class="px-3 py-2 align-middle text-center">
                                            <input type="number" min="0" step="0.01"
                                                   wire:model.live.debounce.500ms="entryItems.{{ $i }}.unit_cost"
                                                   placeholder="0,00"
                                                   @disabled($isSub)
                                                   class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-center focus:ring-2 focus:ring-emerald-500 disabled:bg-gray-100 disabled:text-gray-400">
                                        </td>
                                        <td class="px-3 py-2 align-middle text-center">
                                            <div class="text-[11px] text-gray-500">{{ rtrim(rtrim(number_format($current, 2, '.', ''), '0'), '.') }}</div>
                                            <i class="fas fa-arrow-down text-[10px] {{ $isSub ? 'text-red-500' : 'text-emerald-600' }}"></i>
                                            <div class="text-sm font-bold {{ $insufficient ? 'text-red-600' : 'text-gray-900' }}">
                                                {{ rtrim(rtrim(number_format($new, 2, '.', ''), '0'), '.') }}
                                            </div>
                                            @if($insufficient)
                                                <div class="text-[10px] text-red-600 font-semibold">Stock insuf.</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 align-middle text-center">
                                            <button wire:click="removeEntryItem({{ $i }})" type="button"
                                                    class="btn-press p-1.5 text-gray-400 hover:bg-red-100 hover:text-red-600 rounded-lg transition" title="Remover">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td class="px-3 py-2 text-xs text-gray-500 text-right font-semibold" colspan="3">Custo total das entradas:</td>
                                    <td class="px-3 py-2 text-center text-sm font-bold text-purple-600">
                                        @php
                                            $total = 0;
                                            foreach ($entryItems as $it) {
                                                if (($it['op'] ?? 'add') !== 'sub') {
                                                    $total += (float) ($it['quantity'] ?? 0) * (float) ($it['unit_cost'] ?? 0);
                                                }
                                            }
                                        @endphp
                                        {{ number_format($total, 2, ',', '.') }} Kz
                                    </td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @error('entryItems') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @endif
            </div>
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col sm:flex-row justify-between sm:items-center gap-3 border-t">
            <div class="text-xs text-gray-500 leading-relaxed">
                @if(count($entryItems) > 0)
                    <i class="fas fa-circle-info mr-1"></i>
                    Vão ser registadas
                    @if($opAddCount)<span class="font-bold text-emerald-700">{{ $opAddCount }} entrada(s)</span>@endif
                    @if($opAddCount && $opSubCount) e @endif
                    @if($opSubCount)<span class="font-bold text-red-700">{{ $opSubCount }} saída(s)</span>@endif
                    no armazém selecionado, e gerado o documento em PDF.
                @endif
            </div>
            <div class="flex gap-2 justify-end shrink-0">
                <button type="button"
                        wire:click="closeEntryModal"
                        wire:loading.attr="disabled" wire:target="saveEntry"
                        class="btn-press px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 font-semibold disabled:opacity-50">
                    Cancelar
                </button>
                <button type="button"
                        wire:click="saveEntry"
                        wire:loading.attr="disabled"
                        @disabled(count($entryItems) === 0)
                        class="btn-press px-5 py-2 bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-white rounded-lg font-bold shadow disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-check mr-1"></i>
                    <span wire:loading.remove wire:target="saveEntry">Registar movimentações</span>
                    <span wire:loading wire:target="saveEntry">A processar…</span>
                </button>
            </div>
        </div>

        @endif
    </div>
</div>
@endif
