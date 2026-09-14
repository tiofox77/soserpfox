<?php

namespace App\Models\AGT;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Tenant;

class AGTSubmission extends Model
{
    use HasFactory;

    protected $table = 'agt_submissions';

    const STATUS_PENDING = 'pending';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_VALIDATED = 'validated';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * As tentativas que o botão «Reenviar» dá, e a partir das quais só
     * «Repor e reenviar» a desbloqueia. Uma constante só: o `canRetry()`, o
     * serviço que recusa e o ecrã que mostra o botão liam cada um o seu 3.
     *
     * Não é o tecto do despacho automático (DespachoPendentes::TENTATIVAS_MAX),
     * que insiste mais um pouco sozinho antes de desistir.
     */
    public const MAX_TENTATIVAS_DO_BOTAO = 3;

    protected $fillable = [
        'tenant_id',
        'agt_environment',
        'document_type',
        'document_id',
        'document_number',
        'document_type_code',
        'agt_reference',
        'atcud',
        'status',
        'jws_signature',
        'hash',
        'request_payload',
        'response_payload',
        'error_message',
        'error_code',
        'retry_count',
        'submitted_at',
        'validated_at',
        'rejected_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
        'rejected_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    // =========================================
    // RELATIONSHIPS
    // =========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(AGTCommunicationLog::class, 'submission_id');
    }

    // =========================================
    // SCOPES
    // =========================================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeValidated($query)
    {
        return $query->where('status', self::STATUS_VALIDATED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Só as do ambiente pedido. Homologação e produção são universos
     * separados: uma submissão de um nunca pode ser enviada, consultada ou
     * dada por validada pelo outro.
     */
    public function scopeDoAmbiente($query, string $ambiente)
    {
        return $query->where('agt_environment', $ambiente === 'production' ? 'production' : 'sandbox');
    }

    public function scopeNeedsRetry($query, $maxRetries = self::MAX_TENTATIVAS_DO_BOTAO)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_REJECTED])
            ->where('retry_count', '<', $maxRetries);
    }

    // =========================================
    // HELPER METHODS
    // =========================================

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function canRetry(int $maxRetries = self::MAX_TENTATIVAS_DO_BOTAO): bool
    {
        return $this->retry_count < $maxRetries &&
               in_array($this->status, [self::STATUS_PENDING, self::STATUS_REJECTED]);
    }

    /** Pertence ao ambiente que a empresa tem activo? */
    public function eDoAmbiente(string $ambiente): bool
    {
        return ($this->agt_environment === 'production' ? 'production' : 'sandbox')
            === ($ambiente === 'production' ? 'production' : 'sandbox');
    }

    /** O botão «Reenviar»: por enviar ou recusada, com tentativas, e do ambiente activo. */
    public function podeReenviar(string $ambienteActivo): bool
    {
        return $this->eDoAmbiente($ambienteActivo) && $this->canRetry();
    }

    /**
     * O botão «Repor e reenviar»: SÓ quando o «Reenviar» já não chega.
     *
     * Uma enviada (`submitted`) está na AGT à espera de veredicto — repô-la
     * era mandar outra vez um documento que ela pode estar a aceitar nesse
     * instante. Uma cancelada acabou. E uma com tentativas por gastar não
     * precisa de reposição nenhuma: repor apagava o rasto das que falharam.
     */
    public function podeRepor(string $ambienteActivo): bool
    {
        return $this->eDoAmbiente($ambienteActivo)
            && in_array($this->status, [self::STATUS_PENDING, self::STATUS_REJECTED], true)
            && (int) $this->retry_count >= self::MAX_TENTATIVAS_DO_BOTAO;
    }

    // =========================================
    // STATUS TRANSITIONS
    // =========================================

    public function markAsSubmitted(array $requestPayload): void
    {
        $this->update([
            'status' => self::STATUS_SUBMITTED,
            'request_payload' => $requestPayload,
            'submitted_at' => now(),
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * A comunicação falhou — o pedido pode nem ter chegado à AGT.
     *
     * NÃO é uma recusa. "Rejeitado" é um veredicto do fisco sobre o documento;
     * um DNS que não resolve ou uma ligação que cai não dizem nada sobre ele.
     *
     * Tratava-se tudo como recusa: um `cURL error 28: Resolving timed out`
     * deixava o documento marcado como rejeitado, fora da lista de pendentes,
     * e nunca mais era tentado. Ficava para sempre por comunicar à AGT, com o
     * ecrã a dizer que tinha sido recusado — e a factura impressa a remeter
     * para um quiosque onde nunca ia aparecer.
     *
     * Fica em pending, que é o estado de quem ainda tem de ir. O submissionUUID
     * é o mesmo, por isso reenviar é seguro mesmo que a primeira tentativa
     * tenha chegado: a AGT reconhece-o e não duplica.
     */
    public function markAsCommunicationFailure(string $motivo): void
    {
        $this->update([
            'status'        => self::STATUS_PENDING,
            'error_code'    => 'COMMS',
            'error_message' => $motivo,
            'retry_count'   => $this->retry_count + 1,
        ]);
    }

    public function markAsValidated(string $agtReference, ?string $atcud, array $responsePayload): void
    {
        $submissionFields = [
            'status' => self::STATUS_VALIDATED,
            'agt_reference' => $agtReference,
            'response_payload' => $responsePayload,
            'validated_at' => now(),
            'error_message' => null,
            'error_code' => null,
        ];
        if (filled($atcud)) {
            $submissionFields['atcud'] = $atcud;
        }
        $this->update($submissionFields);

        // Atualizar documento original
        if ($this->document) {
            $documentFields = [
                'agt_status' => 'validated',
                'agt_reference' => $agtReference,
                'agt_validated_at' => now(),
            ];
            // ObterEstado não devolve ATCUD. Nunca apagar o ATCUD gerado pela
            // série fiscal quando a validação assíncrona termina.
            if (filled($atcud)) {
                $documentFields['atcud'] = $atcud;
            }

            // Validado pela AGT ⇒ DEFINITIVO (invoice_status = 'F').
            //
            // Sem isto o documento ficava em 'N' e as consequências eram graves:
            //  · o guarda de editar/apagar testa invoice_status !== 'F', logo um
            //    documento já aceite pelo fisco continuava alterável — viola a
            //    inviolabilidade exigida pelo Decreto 71/25 (Art. 3.º, n.º 4);
            //  · o SalesInvoiceAccountingObserver dispara na transição para 'F',
            //    pelo que a venda nunca chegava à contabilidade.
            // O método finalizeInvoice() existia mas nunca era chamado.
            if (\Illuminate\Support\Facades\Schema::hasColumn(
                    $this->document->getTable(), 'invoice_status'
                ) && $this->document->invoice_status !== 'F') {
                $documentFields['invoice_status'] = 'F';
                $documentFields['invoice_status_date'] = now();
            }

            $this->document->update($documentFields);
        }
    }

    /**
     * @param bool $contarTentativa  true só no envio que a AGT recusou NA HORA.
     *   A recusa que chega depois, pela consulta do estado, é o desfecho de um
     *   envio que o markAsSubmitted() já contou — contá-la outra vez gastava
     *   duas tentativas por documento.
     */
    public function markAsRejected(string $errorCode, string $errorMessage, array $responsePayload, bool $contarTentativa = false): void
    {
        $campos = [
            'status' => self::STATUS_REJECTED,
            'error_code' => mb_substr($errorCode, 0, 255),
            'error_message' => $errorMessage,
            'response_payload' => $responsePayload,
            'rejected_at' => now(),
        ];

        if ($contarTentativa) {
            $campos['retry_count'] = (int) $this->retry_count + 1;
        }

        $this->update($campos);

        // Atualizar documento original
        if ($this->document) {
            $this->document->update([
                'agt_status' => 'rejected',
            ]);
        }
    }

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    // =========================================
    // FACTORY METHODS
    // =========================================

    public static function createForDocument($document, string $documentTypeCode): self
    {
        if ((int) ($document->tenant_id ?? 0) < 1) {
            throw new \InvalidArgumentException('Documento AGT sem tenant valido.');
        }
        $documentNumber = $document->invoice_number 
            ?? $document->credit_note_number 
            ?? $document->debit_note_number 
            ?? $document->receipt_number 
            ?? 'UNKNOWN';

        return self::create([
            'tenant_id' => $document->tenant_id,
            // Homologação e produção são universos separados: sem isto as duas
            // apareciam misturadas e um documento validado em sandbox parecia
            // validado em produção.
            'agt_environment' => \App\Models\Invoicing\InvoicingSettings::forTenant(
                (int) $document->tenant_id
            )->agt_environment ?: 'sandbox',
            'document_type' => get_class($document),
            'document_id' => $document->id,
            'document_number' => $documentNumber,
            'document_type_code' => $documentTypeCode,
            'status' => self::STATUS_PENDING,
            'hash' => $document->hash ?? $document->saft_hash ?? null,
            'jws_signature' => $document->jws_signature ?? null,
        ]);
    }
}
