<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class FeatureRequest extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
        'description',
        'images',
        'status',
        'votes_count',
    ];
    
    protected $casts = [
        'images' => 'array',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function votes(): HasMany
    {
        return $this->hasMany(FeatureRequestVote::class, 'request_id');
    }
    
    public function comments(): HasMany
    {
        return $this->hasMany(FeatureRequestComment::class, 'request_id');
    }
    
    public function hasVoted($userId)
    {
        return $this->votes()->where('user_id', $userId)->exists();
    }
    
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'gray',
            'under_review' => 'yellow',
            'planned' => 'blue',
            'in_development' => 'purple',
            'completed' => 'green',
            'rejected' => 'red',
            default => 'gray',
        };
    }
}
