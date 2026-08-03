<?php

namespace App\Models\AGT;

use Illuminate\Database\Eloquent\Model;

/**
 * Verbas de Imposto de Selo — DS.120 Anexo 9.6.
 *
 * Usado quando `taxType = IS`, em que `taxCode` deve ser o número da verba (1..24).
 */
class AGTIsVerba extends Model
{
    protected $table = 'agt_is_verbas';

    protected $fillable = [
        'verba_no',
        'description',
        'rate',
        'rate_type',
        'is_active',
    ];

    protected $casts = [
        'verba_no'  => 'integer',
        'is_active' => 'boolean',
    ];

    public const RATE_TYPE_PERCENTAGE = 'PERCENTAGE';
    public const RATE_TYPE_FIXED      = 'FIXED';

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public static function findByNumber(int $verbaNo): ?self
    {
        return static::where('verba_no', $verbaNo)->first();
    }
}
