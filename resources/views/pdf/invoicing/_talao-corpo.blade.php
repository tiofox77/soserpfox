{{--
    O TALÃO DE 80 mm — o corpo, numa parcial.

    Está aqui e não dentro do modal do POS porque agora há DOIS ecrãs a
    imprimi-lo: o balcão em Livewire e o balcão em React. Um talão desenhado
    em dois sítios é o mesmo documento fiscal com duas versões, e é
    precisamente isso que não pode acontecer.

    NÃO SE TRADUZ. O que aqui se imprime é uma factura em português de
    Angola, para a AGT e para o cliente — muda com a empresa, não com a
    língua de quem está ao balcão.

    Espera $invoice (com items, client e series). Quem põe o invólucro e o
    CSS de impressão é quem inclui: o modal, ou a página do talão.
--}}
            {{-- QR Code AGT (gerar antes do cabeçalho) --}}
            @php
                try {
                    $ticketQR = getAGTQRData($invoice, 100);
                } catch (\Exception $e) {
                    $ticketQR = ['data' => '', 'image' => null, 'atcud' => ''];
                }
            @endphp

            {{-- Cabeçalho: Logo do sistema à esquerda + QR à direita --}}
            @php
                $tenant = auth()->user()->activeTenant();
                $ticketLogo = null;
                if ($tenant?->logo) {
                    $ticketLogo = str_starts_with($tenant->logo, 'http')
                        ? $tenant->logo
                        : asset('storage/' . ltrim($tenant->logo, '/'));
                }
                $ticketLogo = $ticketLogo ?: app_logo();
            @endphp
            <div style="display: flex; align-items: flex-start; justify-content: space-between; border-bottom: 2px dashed #9ca3af; padding-bottom: 10px; margin-bottom: 10px;">
                <div style="flex: 1;">
                    @if($ticketLogo)
                        <img src="{{ $ticketLogo }}" alt="{{ $tenant?->nomeParaDocumentos() ?: app_name() }}" style="display: block; width: 160px; height: 48px; max-width: 100%; object-fit: contain; object-position: left center; margin-bottom: 6px;">
                    @endif
                    <h3 style="font-size: 15px; font-weight: 400; text-transform: uppercase; margin: 0;">{{ $tenant->nomeParaDocumentos() }}</h3>
                    <p style="font-size: 14px; margin: 0;">NIF: {{ $tenant->nif ?? 'N/A' }}</p>
                    <p style="font-size: 14px; margin: 0;">{{ $tenant->address ?? 'Endereço' }}</p>
                    <p style="font-size: 14px; margin: 0;">Tel: {{ $tenant->phone ?? 'Telefone' }}</p>
                </div>
                @if(!empty($ticketQR['image']))
                <div style="flex-shrink: 0; text-align: center; margin-left: 10px;">
                    <img src="{{ $ticketQR['image'] }}" alt="QR Code AGT" style="width: 100px; height: 100px;" />
                    {{-- Série ainda por registar na AGT não tem ATCUD --}}
                    @if(!empty($ticketQR['atcud']))
                    <p style="font-size: 14px; color: #000; margin-top: 2px;">ATCUD: {{ $ticketQR['atcud'] }}</p>
                    @endif
                </div>
                @endif
            </div>

            {{-- Etiqueta TAX INVOICE --}}
            <h4 style="font-size: 14px; font-weight: 700; margin: 8px 0; text-align: center;">FACTURA RECIBO</h4>

            {{-- Dados Fatura --}}
            <div class="text-xs mb-3 space-y-1">
                {{-- OS DOIS NÚMEROS: a série da CASA em cima, a da AGT por
                     baixo e mais pequena.

                     O que estava gravado em `invoice_number` é o número na
                     série da AGT — críptico (FR4226S61354N/000229) e não é por
                     ele que a empresa chama o documento. Quem atende ao balcão
                     e quem confere a caixa procuram pela série interna
                     (SOSFR/000130). A da AGT continua impressa, que é a que a
                     autoridade reconhece — só deixa de ser a primeira.

                     É a mesma ordem que a lista de facturas e a pré-visualização
                     já usam. Ver os helpers `numeroInterno()` / `numeroAgt()`. --}}
                <div class="flex justify-between">
                    <span class="font-bold">FATURA:</span>
                    <span class="font-bold">{{ $invoice->numeroInterno() }}</span>
                </div>
                @if($invoice->numeroAgt() && $invoice->numeroAgt() !== $invoice->numeroInterno())
                <div class="flex justify-between" style="margin-top: -2px;">
                    <span></span>
                    <span style="font-size: 11px; color: #444;">AGT: {{ $invoice->numeroAgt() }}</span>
                </div>
                @endif
                <div class="flex justify-between">
                    <span class="font-bold">DATA:</span>
                    <span>{{ ($invoice->system_entry_date ?? $invoice->invoice_date)->format('d/m/Y H:i') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold">OPERADOR:</span>
                    <span>{{ auth()->user()->name }}</span>
                </div>
            </div>

            {{-- Dados Cliente --}}
            <div class="text-xs mb-3 pb-2 border-b border-dashed border-gray-400">
                <div class="font-bold mb-1">CLIENTE:</div>
                <div>{{ $invoice->client->name }}</div>
                <div>NIF: {{ $invoice->client->nif }}</div>
            </div>

            {{-- Itens --}}
            <div class="text-xs mb-3">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-400">
                            <th class="text-left py-1">ITEM</th>
                            <th class="text-center">QTD</th>
                            <th class="text-right">PREÇO</th>
                            <th class="text-right">SUBTOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                        @php
                            $isItemService = str_starts_with($item->description ?? '', '[SERVIÇO]');
                        @endphp
                        <tr class="border-b border-dotted border-gray-300">
                            <td class="py-1">
                                @if($isItemService)
                                <span class="text-purple-600">●</span>
                                @endif
                                {{ $item->product_name }}
                            </td>
                            <td class="text-center">{{ number_format($item->quantity, 0) }}</td>
                            <td class="text-right">{{ number_format($item->unit_price, 0) }}</td>
                            <td class="text-right">{{ number_format($item->subtotal, 0) }}</td>
                        </tr>
                        <tr class="text-[10px] text-gray-600">
                            <td colspan="4" class="pl-2 pb-1">
                                @if($item->tax_rate > 0)
                                IVA {{ number_format($item->tax_rate, 0) }}%: {{ number_format($item->tax_amount, 2) }} Kz
                                @if($isItemService)
                                 | <span class="text-purple-600">IRT {{ number_format($irtRate ?? 6.5, 1) }}%</span>
                                @endif
                                @else
                                Isento de IVA
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Resumo Fiscal SAFT --}}
            @php
                $settings = \App\Models\Invoicing\InvoicingSettings::forTenant(activeTenantId());
                $taxRate = $settings->default_tax_rate ?? 14;
                $irtRate = $settings->default_irt_rate ?? 6.5;
                
                // Calcular valores de serviços para IRT
                $servicosTotal = $invoice->items->filter(fn($i) => str_starts_with($i->description ?? '', '[SERVIÇO]'))->sum('subtotal');
                $irtAmount = $servicosTotal * ($irtRate / 100);
                $hasServices = $servicosTotal > 0;
            @endphp
            <div class="text-xs mb-3 pb-3 border-t-2 border-gray-400 pt-2 space-y-1">
                <div class="font-bold mb-1">RESUMO FISCAL:</div>
                
                {{-- Base Incidência --}}
                <div class="flex justify-between">
                    <span>Total Base Incidência IVA:</span>
                    <span>{{ number_format($invoice->subtotal, 2) }} Kz</span>
                </div>
                
                {{-- Desconto se houver --}}
                @if($invoice->discount_amount > 0)
                <div class="flex justify-between text-orange-600">
                    <span>Desconto Comercial:</span>
                    <span>-{{ number_format($invoice->discount_amount, 2) }} Kz</span>
                </div>
                <div class="flex justify-between">
                    <span>Base após Desconto:</span>
                    <span>{{ number_format($invoice->subtotal - $invoice->discount_amount, 2) }} Kz</span>
                </div>
                @endif
                
                {{-- Total IVA --}}
                <div class="flex justify-between">
                    <span>Total IVA ({{ number_format($taxRate, 0) }}%):</span>
                    <span>{{ number_format($invoice->tax_amount, 2) }} Kz</span>
                </div>
                
                {{-- Retenção IRT para Serviços --}}
                @if($hasServices)
                <div class="flex justify-between text-purple-700">
                    <span>Retenção IRT ({{ number_format($irtRate, 1) }}%):</span>
                    <span>-{{ number_format($irtAmount, 2) }} Kz</span>
                </div>
                <div class="text-[9px] text-purple-600 pl-2">
                    Base serviços: {{ number_format($servicosTotal, 2) }} Kz
                </div>
                @endif
                
                {{-- Total Geral --}}
                <div class="flex justify-between font-bold text-base border-t-2 border-gray-900 pt-1 mt-1">
                    <span>TOTAL GERAL:</span>
                    <span>{{ number_format($invoice->total - $irtAmount, 2) }} Kz</span>
                </div>
                
                @if($hasServices)
                <div class="text-[9px] text-gray-600 text-center mt-1">
                    (Valor líquido após retenção IRT)
                </div>
                @endif
            </div>

            {{-- Pagamento --}}
            <div class="text-xs mb-3 pb-3 border-b border-dashed border-gray-400 space-y-1">
                @php $formas = $invoice->relationLoaded('payments') ? $invoice->payments : $invoice->payments()->get(); @endphp
                @if($formas->count() > 1)
                    {{-- Pago em várias formas: uma linha por cada. --}}
                    <p class="font-bold">Formas de Pagamento:</p>
                    @foreach($formas as $f)
                        <div class="flex justify-between pl-2">
                            <span class="uppercase">{{ $f->payment_method }}</span>
                            <span>{{ number_format($f->amount, 2) }} Kz</span>
                        </div>
                    @endforeach
                @else
                    <div class="flex justify-between">
                        <span>Forma Pagamento:</span>
                        <span class="font-bold uppercase">{{ $formas->first()->payment_method ?? ($invoice->payment_method ?? 'Dinheiro') }}</span>
                    </div>
                @endif
                <div class="flex justify-between">
                    <span>Valor Recebido:</span>
                    <span>{{ number_format($invoice->paid_amount, 2) }} Kz</span>
                </div>
                @if($invoice->paid_amount - $invoice->total > 0)
                <div class="flex justify-between font-bold">
                    <span>Troco:</span>
                    <span>{{ number_format($invoice->paid_amount - $invoice->total, 2) }} Kz</span>
                </div>
                @endif
            </div>

            {{-- Certificação e Observações --}}
            @if($invoice->notes)
            <div class="text-xs mb-3 pb-2 border-b border-dashed border-gray-400">
                <div class="font-bold mb-1">OBSERVAÇÕES:</div>
                <div>{{ $invoice->notes }}</div>
            </div>
            @endif

            {{-- Rodapé SAFT Angola --}}
            <div class="text-[10px] text-center space-y-1 text-gray-700">
                <p class="font-bold mb-2">═══════════════════════</p>
                <p class="font-bold">Processado por programa validado</p>
                {{-- Pelo AGTHelper, que resolve o número do AMBIENTE da empresa.
                     Lido directamente da definição única, o talão dizia FE/324
                     (produção) em documentos emitidos em homologação. --}}
                <p class="font-bold">Certificado AGT Nº {{ \App\Helpers\AGTHelper::softwareValidationNumber() }}</p>
                <p class="mt-1">Software: SOS ERP - SOLUÇÕES EMPRESARIAIS</p>
                @if($invoice->saft_hash)
                <p class="mt-2 font-mono text-[8px] break-all">HASH: {{ substr($invoice->saft_hash, 0, 4) }}-{{ $invoice->hash_control ?? '1' }}</p>
                @endif
                <p class="mt-2 font-bold">Obrigado pela sua preferência!</p>
            </div>
