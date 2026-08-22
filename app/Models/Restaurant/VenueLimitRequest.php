<?php

namespace App\Models\Restaurant;

use App\Models\Tenant;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class VenueLimitRequest extends Model
{
    use BelongsToTenant;

    protected $table = 'restaurant_venue_limit_requests';

    protected $fillable = [
        'tenant_id', 'requested_by', 'current_limit', 'requested_limit', 'status',
        'reason', 'admin_notes', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'current_limit' => 'integer',
        'requested_limit' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class)->withTrashed(); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
