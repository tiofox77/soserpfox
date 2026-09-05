<?php

namespace App\Models\Restaurant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class RestaurantSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'restaurant_settings';

    protected $fillable = [
        'tenant_id', 'default_warehouse_id', 'default_client_id',
        'require_open_shift', 'use_kitchen_workflow', 'require_recipe_for_products', 'reserve_stock_on_confirm',
        'consume_stock_on_kitchen', 'allow_negative_stock', 'next_order_number',

        // A taxa de serviço é da casa e entra na factura; a gorjeta é do
        // pessoal e não entra. Ver a migração para o porquê.
        'service_charge_percent', 'service_charge_product_id', 'tips_enabled',
        'kitchen_auto_print',

        // Menu online — a carta pública.
        'menu_slug', 'online_menu_enabled', 'menu_whatsapp_enabled', 'menu_orders_enabled',
        'menu_whatsapp_number', 'menu_title', 'menu_description', 'menu_logo',
        'menu_primary_color', 'menu_show_prices',
        // Aparência: capa, segunda cor, tema e o título da fila de destaques.
        'menu_cover', 'menu_accent_color', 'menu_theme', 'menu_destaques_titulo',
    ];

    protected $casts = [
        'require_open_shift' => 'boolean',
        'use_kitchen_workflow' => 'boolean',
        'kitchen_auto_print' => 'boolean',
        'require_recipe_for_products' => 'boolean',
        'reserve_stock_on_confirm' => 'boolean',
        'consume_stock_on_kitchen' => 'boolean',
        'allow_negative_stock' => 'boolean',
        'service_charge_percent' => 'decimal:2',
        'tips_enabled' => 'boolean',
        'online_menu_enabled' => 'boolean',
        'menu_whatsapp_enabled' => 'boolean',
        'menu_orders_enabled' => 'boolean',
        'menu_show_prices' => 'boolean',
    ];

    public static function forTenant(int $tenantId): self
    {
        return static::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['next_order_number' => 1]
        );
    }

    /**
     * O restaurante por trás de um endereço público.
     *
     * `withoutGlobalScopes` de propósito: quem abre a carta não tem sessão
     * nem empresa activa — é um cliente com o telemóvel na mão. O escopo é o
     * próprio slug, que é único na tabela inteira.
     *
     * Devolve null se o menu não existir OU estiver desligado. Quem chama
     * trata os dois casos da mesma maneira: a página não existe. Distinguir
     * "não há" de "está desligado" dizia a estranhos que aquele restaurante é
     * cliente do sistema e desligou a carta — não é informação de ninguém.
     */
    public static function porSlugPublico(?string $slug): ?self
    {
        if (blank($slug)) {
            return null;
        }

        $definicoes = static::withoutGlobalScopes()
            ->where('menu_slug', $slug)
            ->first();

        return $definicoes?->online_menu_enabled ? $definicoes : null;
    }

    /** O endereço público da carta, se houver. */
    public function urlDoMenu(): ?string
    {
        return $this->menu_slug ? url('/menu/' . $this->menu_slug) : null;
    }

    /**
     * O endereço de uma MESA.
     *
     * É este que vai no QR colado à mesa: o cliente abre a carta já com a mesa
     * identificada, e o pedido não precisa de lhe perguntar onde está sentado
     * — que é a pergunta a que mais gente responde mal.
     */
    public function urlDaMesa(string $codigoDaMesa): ?string
    {
        return $this->menu_slug
            ? url('/menu/' . $this->menu_slug . '/' . rawurlencode($codigoDaMesa))
            : null;
    }
}
