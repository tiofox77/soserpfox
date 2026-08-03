{{--
    Assinatura electrónica AGT - Barra vertical lateral esquerda
    Variáveis disponíveis:
    - $document: documento (invoice, proforma, receipt, credit_note, etc)
    - $documentLabel: rótulo (ex: "Fatura", "Proforma", "Recibo")
    - $tenant (opcional)
--}}
@php
    $signDate = $document->created_at ?? $document->invoice_date ?? now();
    if (!($signDate instanceof \DateTimeInterface)) {
        $signDate = \Carbon\Carbon::parse($signDate);
    }
    $signDateUtc = $signDate->copy()->setTimezone('UTC')->format('Y/m/d H:i') . ' UTC';
    $hashFull = $document->hash ?? $document->saft_hash ?? '';
    $hashShort = $hashFull ? substr($hashFull, 0, 60) : '';
    $verifyUrl = config('app.url') . '/v/' . ($document->id ?? '') . '/' . ($hashShort ? substr($hashFull, 0, 32) : '');
    $docNumber = $document->invoice_number ?? $document->document_number ?? $document->number ?? '';
    $label = $documentLabel ?? 'Documento Electrónico SOS ERP';
@endphp

<div class="agt-signature-sidebar">
    <div class="agt-signature-vertical">
        <span class="agt-sign-label">{{ $label }}{{ $docNumber ? ' - ' . $docNumber : '' }} - {{ $signDateUtc }}</span>
        <span class="agt-sign-url">{{ $verifyUrl }}</span>
    </div>
</div>

<style>
    .page-wrapper { position: relative !important; }
    .agt-signature-sidebar {
        position: absolute;
        left: 3mm;
        bottom: 25mm;
        width: 7mm;
        z-index: 999;
        pointer-events: none;
    }
    .agt-signature-vertical {
        transform: rotate(-90deg);
        transform-origin: left top;
        white-space: nowrap;
        font-family: Arial, sans-serif;
        font-size: 6px;
        color: #bbb;
        line-height: 1.5;
        position: absolute;
        left: 3mm;
        bottom: 0;
        width: 100mm;
    }
    .agt-signature-vertical .agt-sign-label {
        display: block;
        font-weight: normal;
        color: #aaa;
        margin-bottom: 1px;
    }
    .agt-signature-vertical .agt-sign-url {
        display: block;
        color: #c5c5c5;
        font-size: 5.5px;
    }
</style>
