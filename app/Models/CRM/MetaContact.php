<?php

namespace App\Models\CRM;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um contacto que escreveu por um canal do Meta, ligado ao seu lead no CRM.
 */
class MetaContact extends Model
{
    use BelongsToTenant;

    protected $table = 'meta_contacts';

    protected $fillable = [
        'tenant_id', 'channel', 'external_id', 'lead_id', 'name', 'phone',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
