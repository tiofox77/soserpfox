<?php

use App\Services\AGT\QRCodeService;

/**
 * Gerar QR Code AGT para documento
 * 
 * @param mixed $document Documento (SalesInvoice, CreditNote, DebitNote, etc.)
 * @param int $size Tamanho do QR Code em pixels
 * @return string|null Imagem base64 do QR Code
 */
function generateAGTQRCode($document, int $size = 80): ?string
{
    try {
        $service = app(QRCodeService::class);
        return $service->generateQRImage($document, $size);
    } catch (\Exception $e) {
        \Log::error('QRCodeHelper: Erro ao gerar QR', ['error' => $e->getMessage()]);
        return null;
    }
}

/**
 * Gerar dados do QR Code AGT
 * 
 * @param mixed $document
 * @return array ['data' => string, 'image' => string|null, 'atcud' => string]
 */
function getAGTQRData($document, int $size = 80): array
{
    try {
        $service = app(QRCodeService::class);
        return $service->generateForDocument($document, $size);
    } catch (\Exception $e) {
        \Log::error('QRCodeHelper: Erro', ['error' => $e->getMessage()]);
        return ['data' => '', 'image' => null, 'atcud' => ''];
    }
}

/**
 * Gerar ATCUD para documento
 */
function generateATCUD($document): string
{
    // Se já está gravado, é esse que vale — é o que foi para a AGT.
    if (!empty($document->atcud)) {
        return $document->atcud;
    }

    $series = $document->series ?? null;
    if (!$series) {
        return '';
    }

    // Sequencial = posição na série (o /000003 do número fiscal). Usar o id da
    // base de dados gerava um ATCUD que a AGT não reconhece.
    $numero = $document->invoice_number
        ?? $document->receipt_number
        ?? $document->credit_note_number
        ?? $document->debit_note_number
        ?? $document->document_number
        ?? '';

    return $series->generateATCUD(
        $numero !== '' ? $series->nextSequentialFromDocumentNumber($numero) : 1
    );
}
