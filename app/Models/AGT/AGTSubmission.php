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

    protected $fillable = [
        'tenant_id',
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

    public function scopeNeedsRetry($query, $maxRetries = 3)
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

    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->retry_count < $maxRetries && 
               in_array($this->status, [self::STATUS_PENDING, self::STATUS_REJECTED]);
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

    public function markAsValidated(string $agtReference, string $atcud, array $responsePayload): void
    {
        $this->update([
            'status' => self::STATUS_VALIDATED,
            'agt_reference' => $agtReference,
            'atcud' => $atcud,
            'response_payload' => $responsePayload,
            'validated_at' => now(),
            'error_message' => null,
            'error_code' => null,
        ]);

        // Atualizar documento original
        if ($this->document) {
            $this->document->update([
                'agt_status' => 'validated',
                'agt_reference' => $agtReference,
                'atcud' => $atcud,
                'agt_validated_at' => now(),
            ]);
        }
    }

    public function markAsRejected(string $errorCode, string $errorMessage, array $responsePayload): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'response_payload' => $responsePayload,
            'rejected_at' => now(),
        ]);

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
        $documentNumber = $document->invoice_number 
            ?? $document->credit_note_number 
            ?? $document->debit_note_number 
            ?? $document->receipt_number 
            ?? 'UNKNOWN';

        return self::create([
            'tenant_id' => $document->tenant_id,
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
