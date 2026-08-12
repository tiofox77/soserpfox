<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Quem gerou o evento, se tinha sessão iniciada.
     *
     * Um evento com user_id não é uma visita anónima: é um cliente a
     * trabalhar. O ecrã de analytics contava-os como visitantes e eles
     * desapareciam no meio de quem passa pelo site.
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    /** Visitas de quem ainda não tem conta. */
    public function scopeDeVisitantes($query)
    {
        return $query->whereNull('user_id');
    }

    /** Actividade de quem já está dentro do sistema. */
    public function scopeDeUtilizadores($query)
    {
        return $query->whereNotNull('user_id');
    }
}
