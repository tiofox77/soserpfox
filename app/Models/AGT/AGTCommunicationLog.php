<?php

namespace App\Models\AGT;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenant;

class AGTCommunicationLog extends Model
{
    use HasFactory;

    protected $table = 'agt_communication_logs';

    protected $fillable = [
        'tenant_id',
        'submission_id',
        'service',
        'method',
        'endpoint',
        'request_headers',
        'request_body',
        'response_status',
        'response_headers',
        'response_body',
        'response_time',
        'success',
        'error_message',
        'ip_address',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_body' => 'array',
        'response_headers' => 'array',
        'response_body' => 'array',
        'response_time' => 'float',
        'success' => 'boolean',
    ];

    // =========================================
    // RELATIONSHIPS
    // =========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AGTSubmission::class, 'submission_id');
    }

    // =========================================
    // SCOPES
    // =========================================

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    public function scopeForService($query, string $service)
    {
        return $query->where('service', $service);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // =========================================
    // FACTORY METHOD
    // =========================================

    public static function log(
        int $tenantId,
        string $service,
        string $method,
        string $endpoint,
        ?array $requestHeaders,
        ?array $requestBody,
        ?int $responseStatus,
        ?array $responseHeaders,
        ?array $responseBody,
        float $responseTime,
        bool $success,
        ?string $errorMessage = null,
        ?int $submissionId = null
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'submission_id' => $submissionId,
            'service' => $service,
            'method' => $method,
            'endpoint' => $endpoint,
            'request_headers' => $requestHeaders,
            'request_body' => $requestBody,
            'response_status' => $responseStatus,
            'response_headers' => $responseHeaders,
            'response_body' => $responseBody,
            'response_time' => $responseTime,
            'success' => $success,
            'error_message' => $errorMessage,
            'ip_address' => request()->ip(),
        ]);
    }

    // =========================================
    // HELPERS
    // =========================================

    public function getFormattedResponseTime(): string
    {
        if ($this->response_time < 1000) {
            return round($this->response_time) . 'ms';
        }
        return round($this->response_time / 1000, 2) . 's';
    }

    public function isSuccess(): bool
    {
        return $this->success && $this->response_status >= 200 && $this->response_status < 300;
    }
}
