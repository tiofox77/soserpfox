<?php

namespace App\Models\AGT;

use Illuminate\Database\Eloquent\Model;

/**
 * Classificação das Actividades Económicas (CAE) — INE Angola.
 * DS.120 Anexo 9.5 — `eacCode` no envelope de factura.
 */
class AGTCaeCode extends Model
{
    protected $table = 'agt_cae_codes';

    protected $fillable = [
        'code',
        'level',
        'parent_code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public const LEVEL_SECTION  = 'section';
    public const LEVEL_DIVISION = 'division';
    public const LEVEL_GROUP    = 'group';
    public const LEVEL_CLASS    = 'class';
    public const LEVEL_SUBCLASS = 'subclass';

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_code', 'code');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_code', 'code');
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', trim($code))->first();
    }

    /** Validação simples (existe e está activo). */
    public static function isValid(string $code): bool
    {
        return static::where('code', trim($code))->where('is_active', true)->exists();
    }
}
