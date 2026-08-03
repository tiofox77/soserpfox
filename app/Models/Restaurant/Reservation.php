<?php

namespace App\Models\Restaurant;

use App\Models\Client;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use BelongsToTenant;
    protected $table = 'restaurant_reservations';
    protected $fillable = ['tenant_id', 'venue_id', 'table_id', 'client_id', 'reservation_number', 'guest_name', 'phone', 'email', 'guest_count', 'reserved_at', 'duration_minutes', 'status', 'notes', 'created_by'];
    protected $casts = ['reserved_at' => 'datetime'];
    public function venue(): BelongsTo { return $this->belongsTo(Venue::class); }
    public function table(): BelongsTo { return $this->belongsTo(DiningTable::class, 'table_id'); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
