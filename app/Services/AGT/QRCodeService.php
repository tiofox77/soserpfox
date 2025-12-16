<?php

namespace App\Services\AGT;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de QR Code AGT Angola
 * Decreto Presidencial n.º 71/25
 * 
 * Gera QR Code conforme especificação técnica AGT (pág. 42 do PDF técnico)
 */
class QRCodeService
{
    // Campos obrigatórios do QR Code AGT
    const FIELD_NIF_EMISSOR = 'A';      // NIF do emissor
    const FIELD_NIF_CLIENTE = 'B';      // NIF do cliente
    const FIELD_PAIS_CLIENTE = 'C';     // País do cliente
    const FIELD_TIPO_DOCUMENTO = 'D';   // Tipo de documento
    const FIELD_ESTADO_DOCUMENTO = 'E'; // Estado do documento
    const FIELD_DATA_DOCUMENTO = 'F';   // Data do documento
    const FIELD_NUMERO_DOCUMENTO = 'G'; // Número único do documento
    const FIELD_ATCUD = 'H';            // Código único do documento
    const FIELD_ESPACO_FISCAL = 'I1';   // Espaço fiscal (AO)
    const FIELD_BASE_TRIBUTAVEL = 'I2'; // Base tributável IVA taxa normal
    const FIELD_TOTAL_IVA = 'I3';       // Total IVA taxa normal
    const FIELD_BASE_ISENTO = 'I4';     // Base tributável isenta
    const FIELD_BASE_REDUZIDA = 'I5';   // Base tributável taxa reduzida
    const FIELD_TOTAL_IVA_REDUZIDA = 'I6'; // Total IVA taxa reduzida
    const FIELD_NAO_SUJEITO = 'I7';     // Não sujeito a IVA
    const FIELD_RETENCAO = 'I8';        // Imposto de selo / retenção
    const FIELD_TOTAL_IMPOSTOS = 'N';   // Total de impostos
    const FIELD_TOTAL_DOCUMENTO = 'O';  // Total do documento
    const FIELD_HASH = 'Q';             // 4 caracteres do hash
    const FIELD_CERTIFICADO = 'R';      // Nº do certificado do software

    /**
     * Gerar string de dados para QR Code
     */
    public function generateQRData($document, ?string $certificateNumber = null): string
    {
        $fields = [];

        // A - NIF do emissor
        $nifEmissor = $document->tenant?->nif ?? $document->tenant?->tax_id ?? '';
        $fields[self::FIELD_NIF_EMISSOR] = $nifEmissor;

        // B - NIF do cliente
        $nifCliente = $document->client?->nif ?? '999999999';
        $fields[self::FIELD_NIF_CLIENTE] = $nifCliente;

        // C - País do cliente
        $paisCliente = $document->client?->country ?? 'AO';
        $fields[self::FIELD_PAIS_CLIENTE] = $paisCliente;

        // D - Tipo de documento
        $tipoDocumento = $document->invoice_type ?? $this->getDocumentType($document);
        $fields[self::FIELD_TIPO_DOCUMENTO] = $tipoDocumento;

        // E - Estado do documento
        $estadoDocumento = $document->invoice_status ?? 'N';
        $fields[self::FIELD_ESTADO_DOCUMENTO] = $estadoDocumento;

        // F - Data do documento
        $dataDocumento = $document->invoice_date ?? $document->issue_date ?? now();
        $fields[self::FIELD_DATA_DOCUMENTO] = $dataDocumento->format('Ymd');

        // G - Número único do documento (identificador único interno)
        $numeroDocumento = $document->invoice_number 
            ?? $document->credit_note_number 
            ?? $document->debit_note_number 
            ?? $document->receipt_number;
        $fields[self::FIELD_NUMERO_DOCUMENTO] = $this->formatDocumentNumber($numeroDocumento);

        // H - ATCUD
        $atcud = $document->atcud ?? $this->generateATCUD($document);
        $fields[self::FIELD_ATCUD] = $atcud;

        // I1 - Espaço fiscal
        $fields[self::FIELD_ESPACO_FISCAL] = 'AO';

        // I2/I3 - Base e IVA taxa normal (14%)
        $baseNormal = $this->calculateTaxBase($document, 14);
        $ivaNormal = $this->calculateTaxAmount($document, 14);
        if ($baseNormal > 0) {
            $fields[self::FIELD_BASE_TRIBUTAVEL] = $this->formatAmount($baseNormal);
            $fields[self::FIELD_TOTAL_IVA] = $this->formatAmount($ivaNormal);
        }

        // I4 - Base isenta
        $baseIsento = $this->calculateTaxBase($document, 0);
        if ($baseIsento > 0) {
            $fields[self::FIELD_BASE_ISENTO] = $this->formatAmount($baseIsento);
        }

        // I5/I6 - Base e IVA taxa reduzida (7% ou 5%)
        $baseReduzida = $this->calculateTaxBase($document, 7) + $this->calculateTaxBase($document, 5);
        $ivaReduzido = $this->calculateTaxAmount($document, 7) + $this->calculateTaxAmount($document, 5);
        if ($baseReduzida > 0) {
            $fields[self::FIELD_BASE_REDUZIDA] = $this->formatAmount($baseReduzida);
            $fields[self::FIELD_TOTAL_IVA_REDUZIDA] = $this->formatAmount($ivaReduzido);
        }

        // I8 - Retenção na fonte
        $retencao = $document->irt_amount ?? 0;
        if ($retencao > 0) {
            $fields[self::FIELD_RETENCAO] = $this->formatAmount($retencao);
        }

        // N - Total de impostos
        $totalImpostos = $document->tax_amount ?? 0;
        $fields[self::FIELD_TOTAL_IMPOSTOS] = $this->formatAmount($totalImpostos);

        // O - Total do documento
        $totalDocumento = $document->gross_total ?? $document->total ?? 0;
        $fields[self::FIELD_TOTAL_DOCUMENTO] = $this->formatAmount($totalDocumento);

        // Q - 4 primeiros caracteres do hash
        $hash = $document->hash ?? $document->saft_hash ?? '';
        $fields[self::FIELD_HASH] = substr($hash, 0, 4);

        // R - Número do certificado do software
        $certificado = $certificateNumber 
            ?? $document->tenant?->invoicingSettings?->saft_software_cert 
            ?? $document->tenant?->invoicingSettings?->agt_software_certificate
            ?? '';
        $fields[self::FIELD_CERTIFICADO] = $certificado;

        // Construir string no formato AGT: A:valor*B:valor*...
        return $this->buildQRString($fields);
    }

    /**
     * Construir string QR no formato AGT
     */
    private function buildQRString(array $fields): string
    {
        $parts = [];
        
        foreach ($fields as $key => $value) {
            if (!empty($value) || $value === '0') {
                $parts[] = $key . ':' . $value;
            }
        }
        
        return implode('*', $parts);
    }

    /**
     * Gerar imagem QR Code em Base64 (SVG)
     */
    public function generateQRImage($document, int $size = 150, ?string $certificateNumber = null): ?string
    {
        try {
            $data = $this->generateQRData($document, $certificateNumber);
            
            // Usar bacon/bacon-qr-code v3
            $renderer = new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd()
            );
            
            $writer = new Writer($renderer);
            $svg = $writer->writeString($data);
            
            return 'data:image/svg+xml;base64,' . base64_encode($svg);

        } catch (\Exception $e) {
            Log::error('QRCodeService: Erro ao gerar QR Code', [
                'error' => $e->getMessage(),
                'document_id' => $document->id ?? null,
            ]);
            return null;
        }
    }

    /**
     * Gerar QR Code como SVG inline
     */
    public function generateQRSvg($document, int $size = 150, ?string $certificateNumber = null): ?string
    {
        try {
            $data = $this->generateQRData($document, $certificateNumber);
            
            $renderer = new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd()
            );
            
            $writer = new Writer($renderer);
            return $writer->writeString($data);

        } catch (\Exception $e) {
            Log::error('QRCodeService: Erro ao gerar QR SVG', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Gerar QR Code e retornar dados do QR
     */
    public function generateForDocument($document, int $size = 80): array
    {
        try {
            $data = $this->generateQRData($document);
            $image = $this->generateQRImage($document, $size);
            
            return [
                'data' => $data,
                'image' => $image,
                'atcud' => $document->atcud ?? $this->generateATCUD($document),
            ];
        } catch (\Exception $e) {
            Log::error('QRCodeService: Erro', ['error' => $e->getMessage()]);
            return [
                'data' => '',
                'image' => null,
                'atcud' => '',
            ];
        }
    }

    // =========================================
    // HELPERS
    // =========================================

    private function getDocumentType($document): string
    {
        $class = get_class($document);
        
        return match (true) {
            str_contains($class, 'SalesInvoice') => $document->invoice_type ?? 'FT',
            str_contains($class, 'CreditNote') => 'NC',
            str_contains($class, 'DebitNote') => 'ND',
            str_contains($class, 'Receipt') => 'RC',
            str_contains($class, 'Proforma') => 'FP',
            default => 'FT',
        };
    }

    private function formatDocumentNumber(string $number): string
    {
        // Remover espaços e caracteres especiais, manter apenas alfanuméricos e /
        return preg_replace('/[^A-Za-z0-9\/]/', '', $number);
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function generateATCUD($document): string
    {
        $series = $document->series;
        $validationCode = $series?->atcud_validation_code ?? '0';
        $sequentialNumber = $document->id ?? 1;
        
        return $validationCode . '-' . $sequentialNumber;
    }

    private function calculateTaxBase($document, float $taxRate): float
    {
        $items = $document->items ?? collect();
        
        return $items->filter(function ($item) use ($taxRate) {
            return abs(($item->tax_rate ?? 0) - $taxRate) < 0.01;
        })->sum('subtotal') ?? 0;
    }

    private function calculateTaxAmount($document, float $taxRate): float
    {
        $items = $document->items ?? collect();
        
        return $items->filter(function ($item) use ($taxRate) {
            return abs(($item->tax_rate ?? 0) - $taxRate) < 0.01;
        })->sum('tax_amount') ?? 0;
    }

    /**
     * Validar dados do QR Code
     */
    public function validateQRData(string $qrData): array
    {
        $errors = [];
        $fields = [];

        // Parse da string QR
        $parts = explode('*', $qrData);
        foreach ($parts as $part) {
            $colonPos = strpos($part, ':');
            if ($colonPos !== false) {
                $key = substr($part, 0, $colonPos);
                $value = substr($part, $colonPos + 1);
                $fields[$key] = $value;
            }
        }

        // Validar campos obrigatórios
        $required = [
            self::FIELD_NIF_EMISSOR => 'NIF Emissor',
            self::FIELD_NIF_CLIENTE => 'NIF Cliente',
            self::FIELD_TIPO_DOCUMENTO => 'Tipo Documento',
            self::FIELD_DATA_DOCUMENTO => 'Data Documento',
            self::FIELD_NUMERO_DOCUMENTO => 'Número Documento',
            self::FIELD_ATCUD => 'ATCUD',
            self::FIELD_TOTAL_DOCUMENTO => 'Total Documento',
            self::FIELD_HASH => 'Hash',
        ];

        foreach ($required as $field => $name) {
            if (empty($fields[$field])) {
                $errors[] = "Campo obrigatório em falta: {$name} ({$field})";
            }
        }

        // Validar formato NIF
        if (!empty($fields[self::FIELD_NIF_EMISSOR]) && strlen($fields[self::FIELD_NIF_EMISSOR]) !== 9) {
            $errors[] = 'NIF Emissor deve ter 9 dígitos';
        }

        // Validar hash (4 caracteres)
        if (!empty($fields[self::FIELD_HASH]) && strlen($fields[self::FIELD_HASH]) !== 4) {
            $errors[] = 'Hash deve ter exatamente 4 caracteres';
        }

        return [
            'valid' => empty($errors),
            'fields' => $fields,
            'errors' => $errors,
        ];
    }
}
