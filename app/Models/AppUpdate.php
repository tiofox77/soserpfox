<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Uma versão publicada do soserp (lado servidor). O `manifesto` é o token
 * SOSERP-UPD assinado que o cliente verifica antes de aplicar.
 */
class AppUpdate extends Model
{
    protected $table = 'app_updates';

    protected $fillable = [
        'versao', 'min_versao', 'notas', 'pacote_url', 'pacote_sha256',
        'obrigatorio', 'rollout', 'manifesto',
    ];

    protected $casts = [
        'obrigatorio' => 'boolean',
    ];

    public const ROLLOUT_NONE = 'none';
    public const ROLLOUT_ALL  = 'all';

    public function targets()
    {
        return $this->hasMany(AppUpdateTarget::class, 'versao', 'versao');
    }
}
