<?php

namespace App\Models\CRM;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um lead: alguém que ainda não é cliente mas pode vir a ser.
 *
 * Quando se converte, vira um cliente DA FACTURAÇÃO (invoicing_clients) — um
 * cadastro só, não uma segunda lista a divergir da primeira.
 */
class Lead extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_leads';

    protected $fillable = [
        'tenant_id', 'name', 'company', 'phone', 'email', 'source', 'status',
        'notes', 'lost_reason', 'assigned_to', 'converted_client_id', 'created_by',
    ];

    public const ORIGENS = [
        'indicacao' => 'Indicação',
        'telefone' => 'Telefone',
        'whatsapp' => 'WhatsApp',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'website' => 'Website',
        'feira' => 'Feira / evento',
        'visita' => 'Visita',
        'outro' => 'Outro',
    ];

    public const ESTADOS = [
        'novo' => 'Novo',
        'contactado' => 'Contactado',
        'qualificado' => 'Qualificado',
        'convertido' => 'Convertido',
        'perdido' => 'Perdido',
    ];

    /** Ainda em jogo: nem convertido nem perdido. */
    public const ABERTOS = ['novo', 'contactado', 'qualificado'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'lead_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::ESTADOS[$this->status] ?? $this->status;
    }

    public function getSourceLabelAttribute(): string
    {
        return self::ORIGENS[$this->source] ?? $this->source;
    }
}
