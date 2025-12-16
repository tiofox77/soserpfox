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
}
