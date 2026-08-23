<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A decisão POR-TENANT do super admin: "este tenant pode receber esta versão".
 * É o que permite o rollout controlado (canary) antes de abrir a todos.
 */
class AppUpdateTarget extends Model
{
    protected $table = 'app_update_targets';

    protected $fillable = ['tenant_id', 'versao'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
