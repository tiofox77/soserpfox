<?php

namespace App\Models\Events;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'events_events';

    protected $fillable = [
        'tenant_id', 'client_id', 'venue_id', 'event_number', 'name', 'description',
        'type', 'start_date', 'end_date', 'setup_start', 'teardown_end',
        'expected_attendees', 'total_value', 'status', 'notes', 'responsible_user_id',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'setup_start' => 'datetime',
        'teardown_end' => 'datetime',
        'total_value' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (!$event->event_number) {
                $event->event_number = self::generateEventNumber();
            }
        });
    }

    public static function generateEventNumber()
    {
        $year = date('Y');
        $lastEvent = self::where('tenant_id', activeTenantId())
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastEvent ? (intval(substr($lastEvent->event_number, -4)) + 1) : 1;

        return 'EVT' . $year . '/' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    // Relacionamentos
    public function client() { return $this->belongsTo(Client::class); }
    public function venue() { return $this->belongsTo(Venue::class); }
    public function responsible() { return $this->belongsTo(User::class, 'responsible_user_id'); }
    public function equipment() { return $this->hasMany(EventEquipment::class); }
    public function staff() { return $this->hasMany(EventStaff::class); }
    public function checklists() { return $this->hasMany(Checklist::class); }

    // Accessors
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'orcamento' => 'gray',
            'confirmado' => 'blue',
            'em_montagem' => 'yellow',
            'em_andamento' => 'green',
            'concluido' => 'green',
            'cancelado' => 'red',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'orcamento' => 'Orçamento',
            'confirmado' => 'Confirmado',
            'em_montagem' => 'Em Montagem',
            'em_andamento' => 'Em Andamento',
            'concluido' => 'Concluído',
            'cancelado' => 'Cancelado',
            default => $this->status,
        };
    }
}
