{{--
    Modal de visualização de Nota de Crédito / Nota de Débito.

    Espelha o modal da factura de venda e acrescenta o que é próprio de um
    documento RECTIFICATIVO (Art. 12º Decreto 71/25): o documento corrigido, o
    motivo, a expressão obrigatória e a referência de cada linha.

    As duas notas são estruturalmente idênticas, por isso partilham este ficheiro
    — mesmo padrão do partials/tax-summary, reutilizado pelos 5 PDFs. Evita duas
    cópias de ~200 linhas a divergir com o tempo.

    Espera:
      $doc      documento (CreditNote|DebitNote) com ->items, ->client, ->invoice
      $numero   número do documento
      $titulo   "Nota de Crédito" | "Nota de Débito"
      $grad     classes literais do gradiente do cabeçalho
      $accent   classe literal da cor de destaque (ícones)
      $chipBg   classe literal do fundo do avatar do cliente
      $close    nome do método Livewire que fecha o modal
      $rotaPreview / $rotaPdf   nomes das rotas

    As classes Tailwind vêm inteiras por parâmetro (e não montadas a partir de um
    nome de cor) para que cada listagem declare explicitamente a sua paleta e o
    partial não tenha de conhecer nenhuma delas.
--}}
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full my-8 animate-scale-in">
        {{-- Cabeçalho --}}
        <div class="bg-gradient-to-r {{ $grad }} px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-file-invoice mr-2"></i>
                {{ $titulo }} {{ $numero }}
            </h3>
            <button wire:click="{{ $close }}" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <div class="p-6 max-h-[calc(100vh-200px)] overflow-y-auto">
            {{-- Cliente --}}
            <div class="mb-6">
                <h4 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-user mr-2 {{ $accent }}"></i>
                    Informações do Cliente
                </h4>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="font-bold text-gray-900">{{ $doc->client->name ?? 'Consumidor Final' }}</p>
                    @if($doc->client?->nif)
                    <p class="text-sm text-gray-600">NIF: {{ $doc->client->nif }}</p>
                    @endif
                    @if($doc->client?->email)
                    <p class="text-sm text-gray-600">Email: {{ $doc->client->email }}</p>
                    @endif
                    @if($doc->client?->phone)
                    <p class="text-sm text-gray-600">Tel: {{ $doc->client->phone }}</p>
                    @endif
                </div>
            </div>

            {{-- Documento rectificado: é o que distingue uma nota de uma factura.
                 A AGT exige a referência ao documento corrigido e o motivo. --}}
            <div class="mb-6">
                <h4 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-link mr-2 {{ $accent }}"></i>
                    Documento Rectificado
                </h4>
                <div class="bg-gray-50 rounded-lg p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Factura de origem</p>
                        @if($doc->invoice)
                            <a href="{{ route('invoicing.sales.invoices.preview', $doc->invoice->id) }}" target="_blank"
                               class="font-mono text-sm font-bold text-blue-700 hover:underline">
                                {{ $doc->invoice->invoice_number }}
                            </a>
                        @else
                            <p class="text-sm text-gray-400">Sem factura associada</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Motivo</p>
                        <p class="text-sm font-semibold text-gray-900">{{ $doc->reason_label }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Expressão (Art. 12º)</p>
                        <p class="text-sm font-semibold text-gray-900">
                            {{ $doc->reason_text ?? 'Rectificação' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Datas e estado --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Data de emissão:</p>
                    <p class="font-bold text-gray-900">{{ $doc->issue_date?->format('d/m/Y') ?? '-' }}</p>
                </div>
                @if($doc->due_date ?? null)
                <div>
                    <p class="text-sm text-gray-600 mb-1">Vencimento:</p>
                    <p class="font-bold text-gray-900">{{ $doc->due_date->format('d/m/Y') }}</p>
                </div>
                @endif
                <div>
                    <p class="text-sm text-gray-600 mb-1">Estado:</p>
                    <span class="px-3 py-1 text-xs font-bold rounded-full
                        {{ $doc->status === 'issued' ? 'bg-green-100 text-green-800' : '' }}
                        {{ $doc->status === 'draft' ? 'bg-gray-100 text-gray-800' : '' }}
                        {{ $doc->status === 'paid' ? 'bg-blue-100 text-blue-800' : '' }}
                        {{ $doc->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}">
                        {{ $doc->status_label }}
                    </span>
                </div>
                <div>
                    <p class="text-sm text-gray-600 mb-1">Emitido por:</p>
                    <p class="font-bold text-gray-900 text-sm">{{ $doc->creator->name ?? '—' }}</p>
                </div>
            </div>

            {{-- Dados fiscais AGT: ATCUD e hash são obrigatórios no documento
                 impresso; mostrá-los aqui evita ter de abrir o PDF para os ver. --}}
            <div class="mb-6 bg-slate-50 border border-slate-200 rounded-lg p-4">
                <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                    <i class="fas fa-shield-halved mr-2 {{ $accent }}"></i>
                    Dados Fiscais AGT
                </h4>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                        <p class="text-gray-500 mb-1">ATCUD</p>
                        <p class="font-mono font-bold text-gray-900">{{ $doc->atcud ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 mb-1">Hash SAFT</p>
                        <p class="font-mono font-bold text-gray-900">
                            {{ $doc->saft_hash ? substr($doc->saft_hash, 0, 4) . '…' : '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-500 mb-1">Estado SAFT</p>
                        <p class="font-bold text-gray-900">
                            @php
                                $__estados = ['N' => 'N — Normal', 'F' => 'F — Facturado', 'A' => 'A — Anulado'];
                            @endphp
                            {{ $__estados[$doc->invoice_status] ?? ($doc->invoice_status ?: '—') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-500 mb-1">Submissão AGT</p>
                        @if($doc->agt_status === 'validated')
                            <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded font-bold">Validado</span>
                        @elseif($doc->agt_status === 'submitted')
                            <span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded font-bold">Submetido</span>
                        @elseif($doc->agt_status === 'rejected')
                            <span class="px-2 py-0.5 bg-red-100 text-red-800 rounded font-bold">Rejeitado</span>
                        @else
                            <span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded font-bold">Não submetido</span>
                        @endif
                    </div>
                </div>
                @if($doc->agt_reference)
                <p class="text-xs text-gray-500 mt-2">
                    Referência AGT: <span class="font-mono">{{ $doc->agt_reference }}</span>
                    @if($doc->agt_submitted_at)
                        · {{ $doc->agt_submitted_at->format('d/m/Y H:i') }}
                    @endif
                </p>
                @endif
            </div>

            {{-- Linhas --}}
            <div class="mb-6">
                <h4 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-box mr-2 {{ $accent }}"></i>
                    Produtos / Serviços
                </h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-bold text-gray-700">Descrição</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700">Qtd</th>
                                <th class="px-3 py-2 text-right text-xs font-bold text-gray-700">Preço</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700">Desc%</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700">IVA</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700">Ref.</th>
                                <th class="px-3 py-2 text-right text-xs font-bold text-gray-700">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($doc->items as $__item)
                            <tr>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-gray-900 text-sm">{{ $__item->description }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $__item->unit }}
                                        @if($__item->tax_code)
                                            · SAFT {{ $__item->tax_code }}
                                        @endif
                                        @if($__item->tax_country_region && $__item->tax_country_region !== 'AO')
                                            · {{ $__item->tax_country_region }}
                                        @endif
                                    </p>
                                </td>
                                <td class="px-3 py-3 text-center text-sm">{{ rtrim(rtrim(number_format($__item->quantity, 2, ',', ''), '0'), ',') }}</td>
                                <td class="px-3 py-3 text-right text-sm">{{ number_format($__item->unit_price, 2, ',', '.') }}</td>
                                <td class="px-3 py-3 text-center text-sm">{{ rtrim(rtrim(number_format($__item->discount_percent, 2, ',', ''), '0'), ',') }}%</td>
                                <td class="px-3 py-3 text-center text-sm">
                                    {{ rtrim(rtrim(number_format($__item->tax_rate, 2, ',', ''), '0'), ',') }}%
                                    @if($__item->tax_exemption_code)
                                        <span class="block text-xs text-amber-700 font-semibold">{{ $__item->tax_exemption_code }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if($__item->reference_invoice_no)
                                        <span class="text-xs font-mono text-blue-700">{{ $__item->reference_invoice_no }}</span>
                                        <span class="block text-xs text-gray-400">linha {{ $__item->reference_item_line_no }}</span>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right font-bold text-sm">{{ number_format($__item->total, 2, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Totais --}}
            @php
                // IEC / Imposto de Selo das linhas: se foram declarados à AGT têm
                // de estar visíveis aqui, senão o total não reconcilia.
                $__extras = collect();
                $__primeiro = $doc->items->first();
                if ($__primeiro) {
                    $__extras = \App\Models\Invoicing\LineTax::where('line_type', get_class($__primeiro))
                        ->whereIn('line_id', $doc->items->pluck('id'))
                        ->get()
                        ->groupBy(fn($t) => $t->tax_type . '|' . $t->verba_no . '|' . $t->pautal_code);
                }

                $__retencoes = collect();
                try {
                    $__retencoes = \Illuminate\Support\Facades\DB::table('invoicing_withholding_taxes')
                        ->where('document_type', get_class($doc))
                        ->where('document_id', $doc->id)
                        ->get();
                } catch (\Throwable $e) {
                    // tabela ausente nesta instalação
                }

                $__regiao = $doc->items->first()->tax_country_region ?? 'AO';
            @endphp
            <div class="bg-gray-50 rounded-lg p-4">
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-semibold">{{ number_format($doc->subtotal, 2, ',', '.') }} Kz</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">IVA:</span>
                        <span class="font-semibold">{{ number_format($doc->tax_amount, 2, ',', '.') }} Kz</span>
                    </div>

                    @foreach($__extras as $__grupo)
                    @php
                        $__e = $__grupo->first();
                        $__rotulo = $__e->tax_type === 'IEC'
                            ? 'IEC' . ($__e->pautal_code ? ' (cód. ' . $__e->pautal_code . ')' : '')
                            : 'Imposto de Selo' . ($__e->verba_no ? ' (verba ' . $__e->verba_no . ')' : '');
                    @endphp
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">
                            {{ $__rotulo }}
                            @if((float) $__e->tax_percentage > 0)
                                <span class="text-xs text-gray-400">{{ rtrim(rtrim(number_format((float) $__e->tax_percentage, 2, ',', ''), '0'), ',') }}%</span>
                            @endif
                        </span>
                        <span class="font-semibold {{ $__e->tax_type === 'IEC' ? 'text-orange-700' : 'text-purple-700' }}">
                            {{ number_format($__grupo->sum(fn($t) => (float) $t->tax_amount), 2, ',', '.') }} Kz
                        </span>
                    </div>
                    @endforeach

                    @if($__regiao === 'AO-CAB')
                    <div class="flex justify-between text-xs">
                        <span class="text-amber-700 font-semibold">Região fiscal:</span>
                        <span class="text-amber-700 font-semibold">Cabinda (AO-CAB) — regime próprio</span>
                    </div>
                    @endif

                    @foreach($__retencoes as $__ret)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">
                            Retenção {{ $__ret->withholding_tax_type }}
                            @if((float) $__ret->withholding_tax_percentage > 0)
                                <span class="text-xs text-gray-400">{{ rtrim(rtrim(number_format((float) $__ret->withholding_tax_percentage, 2, ',', ''), '0'), ',') }}%</span>
                            @endif
                        </span>
                        <span class="font-semibold text-rose-700">-{{ number_format((float) $__ret->withholding_tax_amount, 2, ',', '.') }} Kz</span>
                    </div>
                    @endforeach

                    <div class="flex justify-between pt-2 border-t-2 border-gray-300">
                        <span class="text-lg font-bold text-gray-900">TOTAL:</span>
                        <span class="text-2xl font-bold {{ $accent }}">{{ number_format($doc->total, 2, ',', '.') }} Kz</span>
                    </div>
                </div>
            </div>

            @if($doc->notes)
            <div class="mt-6">
                <h4 class="text-sm font-bold text-gray-700 mb-2">Notas:</h4>
                <p class="text-sm text-gray-600">{{ $doc->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Rodapé --}}
        <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex flex-wrap justify-end gap-3">
            <button wire:click="{{ $close }}"
                    class="px-4 py-2 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                Fechar
            </button>
            <a href="{{ route($rotaPreview, $doc->id) }}" target="_blank"
               class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-semibold transition">
                <i class="fas fa-file-alt mr-2"></i>Preview
            </a>
            <a href="{{ route($rotaPdf, $doc->id) }}" target="_blank"
               class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition">
                <i class="fas fa-file-pdf mr-2"></i>PDF
            </a>
        </div>
    </div>
</div>
