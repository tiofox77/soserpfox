<?php

namespace App\Models\Restaurant;

use App\Models\Product;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Um prato posto em primeiro lugar na carta pública.
 *
 * Vive em tabela própria e não numa coluna dos artigos: o catálogo é
 * partilhado com a facturação, o POS e o PWA, e uma coluna «destaque» lá
 * dentro seria uma decisão do restaurante a viajar por módulos que nada têm
 * que ver com isso.
 */
class MenuDestaque extends Model
{
    use BelongsToTenant;

    protected $table = 'restaurant_menu_destaques';

    protected $fillable = ['tenant_id', 'product_id', 'ordem'];

    /** Uma fila de destaques longa deixa de ser uma fila de destaques. */
    public const MAXIMO = 6;

    public function produto()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Os destaques de uma empresa, prontos a mostrar na carta pública.
     *
     * `withoutGlobalScopes` de propósito: quem abre a carta não tem sessão, e
     * o scope de empresa filtraria por null. O tenant vai explícito.
     */
    public static function paraCarta(int $tenantId)
    {
        return static::withoutGlobalScopes()
            ->where('restaurant_menu_destaques.tenant_id', $tenantId)
            ->join('invoicing_products as p', 'p.id', '=', 'restaurant_menu_destaques.product_id')
            // Um destaque que foi escondido ou ficou a zero não vai à carta:
            // vale a mesma regra dos outros pratos, e um prato em destaque que
            // já não se vende é pior do que destaque nenhum.
            ->where('p.is_active', true)
            ->where('p.price', '>', 0)
            ->orderBy('restaurant_menu_destaques.ordem')
            ->select('restaurant_menu_destaques.*')
            ->with('produto:id,name,description,price,featured_image,category_id')
            ->limit(self::MAXIMO)
            ->get();
    }
}
