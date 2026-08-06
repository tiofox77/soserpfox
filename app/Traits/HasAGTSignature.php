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
        // NOTA: Pular se gross_total não estiver definido (hash será gerado manualmente
        // após cálculo de totais em InvoiceCreate/CreditNoteCreate/etc.)
        static::creating(function ($model) {
            if (config('app.agt_auto_sign', true)) {
                $grossTotal = $model->gross_total ?? $model->total ?? null;
                $hasNumber = !empty($model->invoice_number ?? $model->credit_note_number ?? $model->debit_note_number);
                
                // Só assinar automaticamente se o documento tiver totais e número
                if ($grossTotal && $grossTotal > 0 && $hasNumber) {
                    $model->generateHashAndSign();
                }
            }
        });

        // Antes de atualizar - bloquear se já assinado
        static::updating(function ($model) {
            if ($model->isAGTLocked()) {
                $protectedFields = [
                    'invoice_number', 'credit_note_number', 'debit_note_number',
                    'invoice_date', 'issue_date', 'gross_total', 'total',
                    'net_total', 'subtotal', 'tax_amount', 'client_id',
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
            $signatureService = new SignatureService((int) ($this->tenant_id ?: activeTenantId()));
            
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

        // O anterior é o anterior A ESTE, não o último da série.
        //
        // Sem isto, pegava-se no de maior id — incluindo os EMITIDOS DEPOIS.
        // Num documento novo passava despercebido (é ele o maior e ainda não
        // tem hash), mas ao voltar a assinar um documento já existente a
        // cadeia partia-se: três documentos seguidos ficavam a apontar todos
        // para o mesmo anterior, e o encadeamento SAF-T deixa de provar
        // sequência nenhuma.
        if ($this->exists && $this->getKey()) {
            $query->where($this->getKeyName(), '<', $this->getKey());
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

    /**
     * Submete à AGT. NUNCA lança excepção.
     *
     * A comunicação com a AGT não pode impedir emitir, gravar ou imprimir um
     * documento: enquanto a empresa não tiver as suas chaves configuradas — ou
     * se a AGT estiver em baixo — o documento é gravado na mesma e a falha fica
     * registada para o administrador reprocessar. Antes, quem chamasse isto sem
     * try/catch (a Nota de Crédito, por exemplo) via a emissão rebentar depois
     * de o documento já estar gravado, sem sequer chegar à página seguinte.
     *
     * O AGTService já apanha as suas próprias falhas; o que faltava proteger era
     * a construção do serviço (chaves ausentes, definições em falta).
     */
    public function submitToAGT(): array
    {
        $numero = $this->invoice_number
            ?? $this->receipt_number
            ?? $this->credit_note_number
            ?? $this->debit_note_number
            ?? $this->guide_number
            ?? (string) $this->id;

        // Empresa sem chaves/credenciais: nem sequer tentar a chamada.
        // Sem esta guarda cada venda ficava ~10 s pendurada num timeout de rede
        // à espera da AGT antes de desistir — no POS parece o sistema bloqueado.
        if (!$this->temConfiguracaoAGT()) {
            \Illuminate\Support\Facades\Log::warning('AGT: submissão ignorada — empresa sem chaves configuradas', [
                'documento' => class_basename($this),
                'numero'    => $numero,
                'tenant_id' => $this->tenant_id,
            ]);

            // Auditar também esta saída: "não comunicado por falta de chaves" é
            // exactamente o que se quer poder provar mais tarde, e é a saída
            // mais frequente numa empresa ainda por configurar.
            $this->auditarComunicacao(false, $numero, [
                'motivo'  => 'empresa sem chaves AGT configuradas',
                'ignorado' => true,
            ]);

            return [
                'success' => false,
                'skipped' => true,
                'errors'  => ['Empresa sem chaves AGT configuradas.'],
                'error'   => 'Empresa sem chaves AGT configuradas.',
            ];
        }

        try {
            $agtService = new AGTService($this->tenant_id);
            $resultado = $agtService->submitToAGT($this);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AGT: falha ao submeter documento', [
                'documento' => class_basename($this),
                'numero'    => $numero,
                'tenant_id' => $this->tenant_id,
                'erro'      => $e->getMessage(),
                'ficheiro'  => basename($e->getFile()) . ':' . $e->getLine(),
            ]);

            $resultado = [
                'success' => false,
                'errors'  => [$e->getMessage()],
                'error'   => $e->getMessage(),
            ];
        }

        // Marcar o documento para o administrador o encontrar e reprocessar.
        // Sem isto uma falha ficava só no log e o documento parecia normal.
        if (!($resultado['success'] ?? false)) {
            $erro = $resultado['error'] ?? ($resultado['errors'][0] ?? 'erro desconhecido');

            \Illuminate\Support\Facades\Log::warning('AGT: documento por comunicar', [
                'documento' => class_basename($this),
                'numero'    => $numero,
                'tenant_id' => $this->tenant_id,
                'motivo'    => $erro,
            ]);

            try {
                if (blank($this->agt_status) && \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'agt_status')) {
                    $this->forceFill(['agt_status' => 'pending'])->saveQuietly();
                }
            } catch (\Throwable $e) {
                // Marcar o estado é auxiliar: nunca pode fazer falhar a emissão.
            }
        }

        $this->auditarComunicacao($resultado['success'] ?? false, $numero, [
            'request_id' => $resultado['requestID'] ?? null,
            'erro'       => ($resultado['success'] ?? false)
                ? null
                : ($resultado['error'] ?? ($resultado['errors'][0] ?? null)),
        ]);

        return $resultado;
    }

    /**
     * Regista a comunicação à AGT na trilha de auditoria.
     *
     * É um ACTO FISCAL, não uma alteração de modelo: o observer não o vê. E é
     * precisamente o que se quer poder provar mais tarde — que o documento foi
     * (ou não foi) comunicado, quando, por quem e com que resposta.
     *
     * Passa por aqui a submissão de FT, FR, NC, ND e guias.
     */
    protected function auditarComunicacao(bool $sucesso, ?string $numero, array $extra = []): void
    {
        try {
            app(\App\Services\Audit\AuditRecorder::class)->acto(
                $sucesso ? 'agt_submetido' : 'agt_falhou',
                $this->tenant_id,
                array_merge([
                    'documento' => $numero,
                    'tipo'      => class_basename($this),
                ], $extra),
                $this
            );
        } catch (\Throwable $e) {
            // Auditar nunca pode fazer falhar a comunicação nem a emissão.
        }
    }

    /**
     * Há o mínimo para falar com a AGT?
     *
     * São duas coisas distintas e ambas obrigatórias:
     *   · Basic Auth do PRODUTOR (AGT_API_USERNAME/PASSWORD no .env) — é do
     *     sistema, igual para todas as empresas;
     *   · chaves RSA do CONTRIBUINTE — essas sim, cada empresa liga as suas.
     *
     * Enquanto faltar qualquer uma, os documentos são emitidos, gravados e
     * impressos na mesma; a comunicação fica por fazer e registada no log.
     */
    public function temConfiguracaoAGT(): bool
    {
        try {
            $tenantId = (int) ($this->tenant_id ?: 0);
            if ($tenantId <= 0) {
                return false;
            }

            if (blank(config('services.agt.username')) || blank(config('services.agt.password'))) {
                return false;
            }

            return (new \App\Services\AGT\SignatureService($tenantId))->hasKeys();
        } catch (\Throwable $e) {
            // Na dúvida não tenta: melhor não comunicar do que travar a emissão.
            return false;
        }
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
