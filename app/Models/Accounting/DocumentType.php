<?php

namespace App\Models\Accounting;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentType extends Model
{
    use SoftDeletes;

    protected $table = 'accounting_document_types';

    protected $fillable = [
        'tenant_id',
        'code',
        'description',
        'journal_code',
        'journal_id',
        'recapitulativos',
        'retencao_fonte',
        'bal_financeira',
        'bal_analitica',
        'rec_informacao',
        'tipo_doc_imo',
        'calculo_fluxo_caixa',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'recapitulativos' => 'boolean',
        'retencao_fonte' => 'boolean',
        'bal_financeira' => 'boolean',
        'bal_analitica' => 'boolean',
        'rec_informacao' => 'integer',
        'tipo_doc_imo' => 'integer',
        'calculo_fluxo_caixa' => 'integer',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Relacionamento com Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relacionamento com Journal (Diário Contabilístico)
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * Scope para documentos ativos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para tenant específico
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope para documentos recapitulativos
     */
    public function scopeRecapitulativos($query)
    {
        return $query->where('recapitulativos', true);
    }

    /**
     * Scope para documentos com retenção na fonte
     */
    public function scopeWithRetencao($query)
    {
        return $query->where('retencao_fonte', true);
    }

    /**
     * Scope para documentos de balancete financeira
     */
    public function scopeBalFinanceira($query)
    {
        return $query->where('bal_financeira', true);
    }

    /**
     * Scope ordenado por display_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('code');
    }

    /**
     * Get formatted name (code + description)
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->code} - {$this->description}";
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return $this->is_active ? 'green' : 'gray';
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Ativo' : 'Inativo';
    }
}
