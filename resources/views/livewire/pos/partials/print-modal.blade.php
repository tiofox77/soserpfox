{{-- Modal Impressão de Ticket --}}
@if($showPrintModal && $lastInvoice)
<div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full" style="max-width: 520px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 flex items-center justify-between rounded-t-2xl flex-shrink-0">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-receipt mr-2"></i>Impressão de Ticket
            </h3>
            <button wire:click="closePrintModal" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        {{-- Ticket Preview (Ubuntu font, 480px, 14px base, #000) --}}
        <div id="ticket-print" class="ticket-thermal" style="width: 480px; max-width: 100%; margin: 0 auto; padding: 16px; background: #fff; font-family: 'Ubuntu', sans-serif; font-size: 14px; color: #000; overflow-y: auto; flex: 1; min-height: 0;">
            {{-- QR Code AGT (gerar antes do cabeçalho) --}}
            @php
                try {
                    $ticketQR = getAGTQRData($lastInvoice, 100);
                } catch (\Exception $e) {
                    $ticketQR = ['data' => '', 'image' => null, 'atcud' => ''];
                }
            @endphp

            {{-- Cabeçalho: Logo do sistema à esquerda + QR à direita --}}
            @php
                $tenant = auth()->user()->activeTenant();
            @endphp
            <div style="display: flex; align-items: flex-start; justify-content: space-between; border-bottom: 2px dashed #9ca3af; padding-bottom: 10px; margin-bottom: 10px;">
                <div style="flex: 1;">
                    @if(app_logo())
                        <img src="{{ app_logo() }}" alt="{{ app_name() }}" style="height: 48px; width: auto; margin-bottom: 6px;">
                    @endif
                    <h3 style="font-size: 15px; font-weight: 400; text-transform: uppercase; margin: 0;">{{ $tenant->company_name ?? $tenant->name }}</h3>
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
                <div class="flex justify-between">
                    <span class="font-bold">FATURA:</span>
                    <span>{{ $lastInvoice->invoice_number }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold">DATA:</span>
                    <span>{{ ($lastInvoice->system_entry_date ?? $lastInvoice->invoice_date)->format('d/m/Y H:i') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold">OPERADOR:</span>
                    <span>{{ auth()->user()->name }}</span>
                </div>
            </div>

            {{-- Dados Cliente --}}
            <div class="text-xs mb-3 pb-2 border-b border-dashed border-gray-400">
                <div class="font-bold mb-1">CLIENTE:</div>
                <div>{{ $lastInvoice->client->name }}</div>
                <div>NIF: {{ $lastInvoice->client->nif }}</div>
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
                        @foreach($lastInvoice->items as $item)
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
                $servicosTotal = $lastInvoice->items->filter(fn($i) => str_starts_with($i->description ?? '', '[SERVIÇO]'))->sum('subtotal');
                $irtAmount = $servicosTotal * ($irtRate / 100);
                $hasServices = $servicosTotal > 0;
            @endphp
            <div class="text-xs mb-3 pb-3 border-t-2 border-gray-400 pt-2 space-y-1">
                <div class="font-bold mb-1">RESUMO FISCAL:</div>
                
                {{-- Base Incidência --}}
                <div class="flex justify-between">
                    <span>Total Base Incidência IVA:</span>
                    <span>{{ number_format($lastInvoice->subtotal, 2) }} Kz</span>
                </div>
                
                {{-- Desconto se houver --}}
                @if($lastInvoice->discount_amount > 0)
                <div class="flex justify-between text-orange-600">
                    <span>Desconto Comercial:</span>
                    <span>-{{ number_format($lastInvoice->discount_amount, 2) }} Kz</span>
                </div>
                <div class="flex justify-between">
                    <span>Base após Desconto:</span>
                    <span>{{ number_format($lastInvoice->subtotal - $lastInvoice->discount_amount, 2) }} Kz</span>
                </div>
                @endif
                
                {{-- Total IVA --}}
                <div class="flex justify-between">
                    <span>Total IVA ({{ number_format($taxRate, 0) }}%):</span>
                    <span>{{ number_format($lastInvoice->tax_amount, 2) }} Kz</span>
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
                    <span>{{ number_format($lastInvoice->total - $irtAmount, 2) }} Kz</span>
                </div>
                
                @if($hasServices)
                <div class="text-[9px] text-gray-600 text-center mt-1">
                    (Valor líquido após retenção IRT)
                </div>
                @endif
            </div>

            {{-- Pagamento --}}
            <div class="text-xs mb-3 pb-3 border-b border-dashed border-gray-400 space-y-1">
                <div class="flex justify-between">
                    <span>Forma Pagamento:</span>
                    <span class="font-bold uppercase">{{ $lastInvoice->payment_method ?? 'Dinheiro' }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Valor Recebido:</span>
                    <span>{{ number_format($lastInvoice->paid_amount, 2) }} Kz</span>
                </div>
                @if($lastInvoice->paid_amount - $lastInvoice->total > 0)
                <div class="flex justify-between font-bold">
                    <span>Troco:</span>
                    <span>{{ number_format($lastInvoice->paid_amount - $lastInvoice->total, 2) }} Kz</span>
                </div>
                @endif
            </div>

            {{-- Certificação e Observações --}}
            @if($lastInvoice->notes)
            <div class="text-xs mb-3 pb-2 border-b border-dashed border-gray-400">
                <div class="font-bold mb-1">OBSERVAÇÕES:</div>
                <div>{{ $lastInvoice->notes }}</div>
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
                @if($lastInvoice->saft_hash)
                <p class="mt-2 font-mono text-[8px] break-all">HASH: {{ substr($lastInvoice->saft_hash, 0, 4) }}-{{ $lastInvoice->hash_control ?? '1' }}</p>
                @endif
                <p class="mt-2 font-bold">Obrigado pela sua preferência!</p>
            </div>
        </div>

        {{-- Botões --}}
        <div class="px-6 py-4 flex space-x-3 flex-shrink-0 border-t border-gray-200">
            <button wire:click="closePrintModal" 
                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                <i class="fas fa-times mr-2"></i>Fechar
            </button>
            <button onclick="printTicket()" 
                    class="flex-1 px-4 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl font-semibold hover:from-green-700 hover:to-green-800 transition shadow-lg">
                <i class="fas fa-print mr-2"></i>Imprimir
            </button>
        </div>
    </div>
</div>
@endif

{{-- Estilos termicos + funcao printTicket() — SEMPRE presentes (fora do condicional) para evitar Livewire morphdom nao executar script em DOM dinamico --}}
@once
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;700&display=swap" rel="stylesheet">
<style>
    /* Ticket POS — specs: Ubuntu 400/700, 14px base, #000, 480px wide */
    .ticket-thermal {
        width: 480px;
        max-width: 100%;
        margin: 0 auto;
        font-family: 'Ubuntu', sans-serif;
        font-size: 14px;
        color: #000;
        font-weight: 400;
    }
    .ticket-thermal *,
    .ticket-thermal p,
    .ticket-thermal span,
    .ticket-thermal div,
    .ticket-thermal td,
    .ticket-thermal th {
        font-family: 'Ubuntu', sans-serif;
        color: #000 !important;
    }
    /* Override Tailwind text-xs/[10px]/[9px]/[8px] dentro do ticket: tudo 14px */
    .ticket-thermal .text-xs,
    .ticket-thermal .text-\[10px\],
    .ticket-thermal .text-\[9px\],
    .ticket-thermal .text-\[8px\],
    .ticket-thermal .text-base,
    .ticket-thermal .text-lg {
        font-size: 14px !important;
    }
    /* Cabeçalho empresa */
    .ticket-thermal h3 {
        font-size: 15px;
        font-weight: 400;
        text-transform: uppercase;
        margin: 0;
    }
    /* Etiqueta TAX INVOICE */
    .ticket-thermal h4 {
        font-size: 14px;
        font-weight: 700;
        margin: 8px 0;
    }
    /* Tabelas condensadas */
    .ticket-thermal table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .ticket-thermal table th,
    .ticket-thermal table td {
        padding: 5px;
        font-size: 14px;
    }
    .ticket-thermal table th {
        font-weight: 700;
    }
    /* Totais e linhas bold */
    .ticket-thermal .font-bold {
        font-weight: 700;
    }
</style>
<script>
if (typeof window.printTicket !== 'function') {
    window.printTicket = function() {
        const ticketEl = document.getElementById('ticket-print');
        if (!ticketEl) { alert('Ticket não encontrado.'); return; }

        const printContents = ticketEl.innerHTML;
        const win = window.open('', '_blank', 'width=400,height=700');
        if (!win) {
            alert('O navegador bloqueou a janela de impressão. Permita pop-ups para este site.');
            return;
        }

        win.document.write(`
            <!DOCTYPE html>
            <html><head><meta charset="UTF-8"><title>Ticket</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;700&display=swap" rel="stylesheet">
            <style>
                /* THERMAL TICKET 80mm (72.1mm printable) */
                @page { size: 80mm auto; margin: 0; }
                * { margin: 0; padding: 0; box-sizing: border-box; color: #000 !important; }
                html, body { width: 80mm; }
                body {
                    font-family: 'Ubuntu', sans-serif;
                    font-size: 10px;
                    line-height: 1.25;
                    color: #000;
                    font-weight: 400;
                    width: 80mm;
                    max-width: 80mm;
                    margin: 0;
                    padding: 2mm 1mm;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                    word-wrap: break-word;
                    overflow-wrap: anywhere;
                }
                img { max-width: 100%; height: auto; }
                table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; }
                th, td { padding: 2px 1px; font-size: 10px; word-wrap: break-word; overflow-wrap: anywhere; vertical-align: top; }
                th { font-weight: 700; }
                h3 { font-size: 11px; font-weight: 700; text-transform: uppercase; margin: 0; }
                h4 { font-size: 11px; font-weight: 700; margin: 4px 0; text-align: center; }
                p { margin: 0; font-size: 10px; word-wrap: break-word; overflow-wrap: anywhere; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .text-left { text-align: left; }
                .font-bold { font-weight: 700; }
                .text-xs, .text-lg, .text-base, .text-\[10px\], .text-\[9px\], .text-\[8px\] { font-size: 10px; }
                .border-b { border-bottom: 1px dashed #999; }
                .border-b-2 { border-bottom: 2px dashed #666; }
                .border-t-2 { border-top: 1px solid #333; }
                .mb-1{margin-bottom:2px}.mb-2{margin-bottom:3px}.mb-3{margin-bottom:5px}
                .mt-1{margin-top:2px}.mt-2{margin-top:3px}
                .pb-2{padding-bottom:3px}.pb-3{padding-bottom:5px}
                .pt-1{padding-top:2px}.pt-2{padding-top:3px}
                .py-1{padding-top:2px;padding-bottom:2px}.pl-2{padding-left:3px}
                .space-y-1 > * + * { margin-top: 2px; }
                .flex { display: flex; }
                .justify-between { justify-content: space-between; }
                .mx-auto { margin: 0 auto; display: block; }
                .uppercase { text-transform: uppercase; }
                .break-all { word-break: break-all; }
                /* Header QR layout: shrink QR */
                #ticket-print-content > div:first-child img[alt*="QR"] { width: 60px !important; height: 60px !important; }
                @media print { html, body { width: 80mm; } body { padding: 1mm; } }
            </style>
            </head><body>${printContents}</body></html>
        `);
        win.document.close();

        const triggerPrint = () => {
            try { win.focus(); win.print(); } catch (e) { console.error('Erro imprimir:', e); }
            setTimeout(() => { try { win.close(); } catch(e) {} }, 800);
        };

        const images = win.document.images;
        if (!images || images.length === 0) { setTimeout(triggerPrint, 300); return; }
        let loaded = 0;
        const total = images.length;
        const onDone = () => { if (++loaded >= total) setTimeout(triggerPrint, 150); };
        for (let i = 0; i < total; i++) {
            const img = images[i];
            if (img.complete) onDone();
            else { img.addEventListener('load', onDone); img.addEventListener('error', onDone); }
        }
        setTimeout(() => { if (loaded < total) triggerPrint(); }, 3000);
    };
}
</script>
@endonce
