<?php

namespace App\Models\Invoicing;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registo de actividade sobre produtos: quem criou, alterou, eliminou ou
 * restaurou, quando e o quê.
 *
 * Não usa o trait BelongsToTenant de propósito: o registo é escrito também a
 * partir de observers e comandos, onde nem sempre há sessão autenticada. O
 * tenant_id é sempre gravado explicitamente e filtrado nas leituras.
 */
class ProductActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const ACCAO_CRIADO     = 'criado';
    public const ACCAO_ACTUALIZADO = 'actualizado';
    public const ACCAO_ELIMINADO  = 'eliminado';
    public const ACCAO_RESTAURADO = 'restaurado';
    public const ACCAO_STOCK      = 'stock';

    protected $table = 'product_activity_logs';

    protected $fillable = [
        'tenant_id', 'product_id', 'user_id', 'action',
        'product_name', 'description', 'changes', 'ip_address',
    ];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Regista uma acção. Nunca lança: um problema no registo não pode impedir a
     * operação que o utilizador está a fazer.
     */
    public static function registar(
        Product $produto,
        string $accao,
        ?string $descricao = null,
        array $alteracoes = []
    ): void {
        try {
            static::create([
                'tenant_id'    => $produto->tenant_id,
                'product_id'   => $produto->id,
                'user_id'      => auth()->id(),
                'action'       => $accao,
                'product_name' => $produto->name,
                'description'  => $descricao,
                'changes'      => $alteracoes ?: null,
                'ip_address'   => request()?->ip(),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ProductActivityLog: falha ao registar', [
                'product_id' => $produto->id,
                'action'     => $accao,
                'erro'       => $e->getMessage(),
            ]);
        }
    }

    /** Etiqueta legível da acção. */
    public function getAccaoLabelAttribute(): string
    {
        return [
            self::ACCAO_CRIADO      => 'Criado',
            self::ACCAO_ACTUALIZADO => 'Actualizado',
            self::ACCAO_ELIMINADO   => 'Eliminado',
            self::ACCAO_RESTAURADO  => 'Restaurado',
            self::ACCAO_STOCK       => 'Stock',
        ][$this->action] ?? ucfirst($this->action);
    }

    /** Cor do estado, em classes literais (o Tailwind vem de CDN mas mantém-se explícito). */
    public function getAccaoCorAttribute(): string
    {
        return [
            self::ACCAO_CRIADO      => 'bg-green-100 text-green-800',
            self::ACCAO_ACTUALIZADO => 'bg-blue-100 text-blue-800',
            self::ACCAO_ELIMINADO   => 'bg-red-100 text-red-800',
            self::ACCAO_RESTAURADO  => 'bg-amber-100 text-amber-800',
            self::ACCAO_STOCK       => 'bg-purple-100 text-purple-800',
        ][$this->action] ?? 'bg-gray-100 text-gray-800';
    }
}
