{{-- Modal Impressão de Ticket

     O QUE AQUI SE TRADUZ E O QUE NÃO SE TRADUZ

     Traduz-se a JANELA: o título, os botões, os avisos do JavaScript. É
     interface, e quem está à caixa lê-a na sua língua.

     NÃO se traduz o TALÃO (o bloco #ticket-print). O que ele imprime é uma
     FACTURA RECIBO — documento fiscal angolano, certificado AGT, com as
     menções legais obrigatórias ("Processado por programa validado", o número
     do certificado, o ATCUD, o hash SAFT). A língua oficial desses documentos
     é o português (docs/PLANO-MULTILINGUA.md, decisão 3): traduzir uma menção
     legal é entregar ao cliente um documento que a AGT não reconhece.

     Portanto: se um dia alguém achar que o talão "ficou por traduzir", não
     ficou — ficou de propósito. --}}
@if($showPrintModal && $lastInvoice)
<div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full" style="max-width: 520px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 flex items-center justify-between rounded-t-2xl flex-shrink-0">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-receipt mr-2"></i>{{ __('Impressão de Ticket') }}
            </h3>
            <button wire:click="closePrintModal" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        {{-- EM QUE PAPEL. O que aparece primeiro é o que a empresa
             configurou; trocar aqui não muda a configuração, só esta venda. --}}
        <div class="px-6 pt-4 flex-shrink-0">
            <div class="inline-flex rounded-xl bg-gray-100 p-1 w-full">
                <button type="button" wire:click="trocarFormatoImpressao('a4')"
                        class="flex-1 px-4 py-2 rounded-lg text-sm font-bold transition {{ $formatoImpressao === 'a4' ? 'bg-white text-green-700 shadow' : 'text-gray-500 hover:text-gray-700' }}">
                    <i class="fas fa-file-invoice mr-2"></i>{{ __('Factura A4') }}
                </button>
                <button type="button" wire:click="trocarFormatoImpressao('talao')"
                        class="flex-1 px-4 py-2 rounded-lg text-sm font-bold transition {{ $formatoImpressao === 'talao' ? 'bg-white text-green-700 shadow' : 'text-gray-500 hover:text-gray-700' }}">
                    <i class="fas fa-receipt mr-2"></i>{{ __('Talão 80 mm') }}
                </button>
            </div>
        </div>

        @if($formatoImpressao === 'a4')
            {{-- A pré-visualização do servidor, dentro do modal. É o mesmo
                 papel que sai nos Documentos, e é o mesmo que vai imprimir. --}}
            <div class="px-6 py-4 overflow-y-auto" style="flex: 1 1 auto;">
                <iframe id="a4-preview"
                        src="{{ route('invoicing.sales.invoices.preview', $lastInvoice->id) }}"
                        class="w-full rounded-xl border border-gray-200 bg-white"
                        style="height: 46vh;"
                        title="{{ __('Pré-visualização da factura') }}"></iframe>
            </div>
        @else

        {{-- Ticket Preview (Ubuntu font, 480px, 14px base, #000) --}}
        <div id="ticket-print" class="ticket-thermal" style="width: 480px; max-width: 100%; margin: 0 auto; padding: 16px; background: #fff; font-family: 'Ubuntu', sans-serif; font-size: 14px; color: #000; overflow-y: auto; flex: 1; min-height: 0;">
            {{-- O CORPO DO TALÃO vive numa parcial: o balcão em React imprime
                 o MESMO papel, e um documento fiscal não pode ter duas
                 versões. Ver `pdf/invoicing/_talao-corpo`. --}}
            @include('pdf.invoicing._talao-corpo', ['invoice' => $lastInvoice])
        </div>

        @endif

        {{-- Botões --}}
        <div class="px-6 py-4 flex space-x-3 flex-shrink-0 border-t border-gray-200">
            <button wire:click="closePrintModal" 
                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                <i class="fas fa-times mr-2"></i>{{ __('Fechar') }}
            </button>
            <button onclick="{{ $formatoImpressao === 'a4' ? 'imprimirA4()' : 'printTicket()' }}"
                    class="flex-1 px-4 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl font-semibold hover:from-green-700 hover:to-green-800 transition shadow-lg">
                <i class="fas fa-print mr-2"></i>{{ __('Imprimir') }}
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
// Daqui para baixo é JavaScript: as cadeias vão dentro de __(), o window.__ de
// partials/js-traducoes.blade.php — e NUNCA na forma de echo do Blade. Um par
// de chavetas duplas escrito aqui, mesmo dentro deste comentário, era compilado
// à mesma e rebentava a página inteira: o Blade não sabe que isto é JavaScript.
//
// O dicionário é definido no fim do layout, portanto só existe depois desta
// página estar montada; estas chamadas correm todas dentro de funções (ao
// clicar), quando já lá está.
/*
 * O A4 IMPRIME-SE PELA PRÉ-VISUALIZAÇÃO DO SERVIDOR.
 *
 * Abre-se a mesma página que o modal mostra numa janela própria; ela já
 * traz o seu auto-print quando é aberta a partir de outra janela. Não se
 * imprime o iframe directamente porque, num iframe, o browser imprime a
 * página que o contém, não o que está lá dentro.
 */
if (typeof window.imprimirA4 !== 'function') {
    window.imprimirA4 = function () {
        const moldura = document.getElementById('a4-preview');
        if (!moldura) { alert(__('Pré-visualização não encontrada.')); return; }

        const janela = window.open(moldura.src, '_blank', 'width=900,height=1000');
        if (!janela) {
            alert(__('O navegador bloqueou a janela de impressão. Permita pop-ups para este site.'));
        }
    };
}

if (typeof window.printTicket !== 'function') {
    window.printTicket = function() {
        const ticketEl = document.getElementById('ticket-print');
        if (!ticketEl) { alert(__('Ticket não encontrado.')); return; }

        const printContents = ticketEl.innerHTML;
        const win = window.open('', '_blank', 'width=400,height=700');
        if (!win) {
            alert(__('O navegador bloqueou a janela de impressão. Permita pop-ups para este site.'));
            return;
        }

        win.document.write(`
            <!DOCTYPE html>
            <html><head><meta charset="UTF-8"><title>${__('Ticket')}</title>
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
