<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class FeatureRequestVote extends Model
{
    protected $fillable = ['request_id', 'user_id'];
    
    public function request(): BelongsTo
    {
        return $this->belongsTo(FeatureRequest::class, 'request_id');
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
