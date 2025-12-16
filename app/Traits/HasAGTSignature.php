<?php

namespace App\Traits;

use App\Services\AGT\AGTService;
use App\Services\AGT\SignatureService;
use App\Services\AGT\QRCodeService;
use App\Models\AGT\AGTSubmission;

/**
 * Trait para documentos com assinatura AGT
 * Adiciona funcionalidades de hash, JWS e QR Code
 */
trait HasAGTSignature
{
    // =========================================
    // BOOT - EVENTOS DO MODELO
    // =========================================

    protected static function bootHasAGTSignature()
    {
        // Antes de criar - gerar hash e assinar
        static::creating(function ($model) {
            if (config('app.agt_auto_sign', true)) {
                $model->generateHashAndSign();
            }
        });

        // Antes de atualizar - bloquear se já assinado
        static::updating(function ($model) {
            if ($model->isAGTLocked()) {
                $protectedFields = [
                    'invoice_number', 'credit_note_number', 'debit_note_number',
                    'invoice_date', 'issue_date', 'gross_total', 'total',
                    'net_total', 'subtotal', 'tax_amount', 'client_id',
                    'hash', 'saft_hash', 'jws_signature', 'atcud',
                ];

                foreach ($protectedFields as $field) {
                    if ($model->isDirty($field) && $model->getOriginal($field) !== null) {
                        throw new \Exception(
                            "Campo '{$field}' não pode ser alterado após assinatura AGT. " .
                            "Use Nota de Crédito para correções."
                        );
                    }
                }
            }
        });
    }

    // =========================================
    // VERIFICAÇÕES DE ESTADO
    // =========================================

    public function isAGTLocked(): bool
    {
        return !empty($this->jws_signature) || 
               $this->agt_status === 'validated' ||
               $this->agt_status === 'submitted';
    }

    public function isAGTValidated(): bool
    {
        return $this->agt_status === 'validated';
    }

    public function isAGTPending(): bool
    {
        return $this->agt_status === 'pending' || empty($this->agt_status);
    }

    public function hasAGTSignature(): bool
    {
        return !empty($this->jws_signature);
    }

    public function hasHash(): bool
    {
        return !empty($this->hash) || !empty($this->saft_hash);
    }

    // =========================================
    // GERAÇÃO DE HASH E ASSINATURA
    // =========================================

    public function generateHashAndSign(): bool
    {
        try {
            $signatureService = new SignatureService();
            
            if (!$signatureService->hasKeys()) {
                return false;
            }

            // Buscar hash anterior
            $this->hash_previous = $this->getPreviousHash();

            // Gerar hash SAFT
            $hash = $signatureService->generateHash($this);
            if ($hash) {
                $this->hash = $hash;
                $this->saft_hash = $hash;
            }

            // Gerar assinatura JWS
            $jws = $signatureService->signDocument($this);
            if ($jws) {
                $this->jws_signature = $jws;
            }

            return true;

        } catch (\Exception $e) {
            \Log::error('HasAGTSignature: Erro ao gerar hash/assinatura', [
                'error' => $e->getMessage(),
                'model' => get_class($this),
                'id' => $this->id ?? null,
            ]);
            return false;
        }
    }

    public function getPreviousHash(): ?string
    {
        $query = static::where('tenant_id', $this->tenant_id)
            ->whereNotNull('hash')
            ->where('hash', '!=', '');

        // Filtrar pelo mesmo tipo de documento se possível
        if (isset($this->series_id) && $this->series_id) {
            $query->where('series_id', $this->series_id);
        }

        $previous = $query->orderBy('id', 'desc')->first();

        return $previous?->hash ?? $previous?->saft_hash ?? '';
    }

    // =========================================
    // QR CODE
    // =========================================

    public function getQRCodeData(): string
    {
        $qrService = new QRCodeService();
        return $qrService->generateQRData($this);
    }

    public function getQRCodeImage(int $size = 150): ?string
    {
        $qrService = new QRCodeService();
        return $qrService->generateQRImage($this, $size);
    }

    public function getQRCodeSvg(int $size = 150): ?string
    {
        $qrService = new QRCodeService();
        return $qrService->generateQRSvg($this, $size);
    }

    // =========================================
    // ATCUD
    // =========================================

    public function generateATCUD(): string
    {
        $series = $this->series;
        $validationCode = $series?->atcud_validation_code ?? '0';
        $sequentialNumber = $this->id ?? 1;
        
        return $validationCode . '-' . $sequentialNumber;
    }

    public function ensureATCUD(): void
    {
        if (empty($this->atcud)) {
            $this->atcud = $this->generateATCUD();
            $this->saveQuietly();
        }
    }

    // =========================================
    // SUBMISSÃO AGT
    // =========================================

    public function submitToAGT(): array
    {
        $agtService = new AGTService($this->tenant_id);
        return $agtService->submitToAGT($this);
    }

    public function getAGTSubmissions()
    {
        return AGTSubmission::where('document_type', get_class($this))
            ->where('document_id', $this->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getLatestAGTSubmission(): ?AGTSubmission
    {
        return AGTSubmission::where('document_type', get_class($this))
            ->where('document_id', $this->id)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    // =========================================
    // VALIDAÇÃO CONFORMIDADE
    // =========================================

    public function validateAGTCompliance(): array
    {
        $agtService = new AGTService($this->tenant_id);
        return $agtService->validateDocument($this);
    }

    public function canBeCancelled(): array
    {
        $agtService = new AGTService($this->tenant_id);
        return $agtService->canCancel($this);
    }

    // =========================================
    // PROCESSAR COMPLETO
    // =========================================

    public function processForAGT(bool $autoSubmit = false): array
    {
        $agtService = new AGTService($this->tenant_id);
        return $agtService->processDocument($this, $autoSubmit);
    }

    // =========================================
    // ATRIBUTOS COMPUTADOS
    // =========================================

    public function getHashShortAttribute(): string
    {
        $hash = $this->hash ?? $this->saft_hash ?? '';
        return substr($hash, 0, 4);
    }

    public function getAgtStatusLabelAttribute(): string
    {
        return match ($this->agt_status) {
            'pending' => 'Pendente',
            'submitted' => 'Submetido',
            'validated' => 'Validado',
            'rejected' => 'Rejeitado',
            default => 'Não submetido',
        };
    }

    public function getAgtStatusColorAttribute(): string
    {
        return match ($this->agt_status) {
            'pending' => 'yellow',
            'submitted' => 'blue',
            'validated' => 'green',
            'rejected' => 'red',
            default => 'gray',
        };
    }
}
