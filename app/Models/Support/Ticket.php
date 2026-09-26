<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Ticket extends Model
{
    protected $table = 'support_tickets';
    
    protected $fillable = [
        'tenant_id',
        'user_id',
        'ticket_number',
        'subject',
        'description',
        'images',
        'priority',
        'status',
        'category',
        'assigned_to',
        'resolved_at',
    ];
    
    protected $casts = [
        'resolved_at' => 'datetime',
        'images' => 'array',
    ];
    
    /**
     * O número seguinte desta empresa (TKT-000001…), único por empresa. Chamar
     * dentro de uma transacção: o lockForUpdate segura a última linha até ao
     * fim, e dois pedidos ao mesmo tempo não saem com o mesmo número.
     */
    public static function proximoNumero(int $tenantId): string
    {
        $ultimo = static::where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('ticket_number');

        $seguinte = $ultimo ? ((int) preg_replace('/\D/', '', $ultimo)) + 1 : 1;

        return 'TKT-'.str_pad((string) $seguinte, 6, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }
    
    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            'low' => 'gray',
            'medium' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'gray',
        };
    }
    
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'open' => 'green',
            'in_progress' => 'blue',
            'waiting_response' => 'yellow',
            'resolved' => 'purple',
            'closed' => 'gray',
            default => 'gray',
        };
    }
    /** A empresa a que este registo pertence. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

}
