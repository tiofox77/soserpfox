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
        'require_open_shift', 'reserve_stock_on_confirm',
        'consume_stock_on_kitchen', 'allow_negative_stock', 'next_order_number',
    ];

    protected $casts = [
        'require_open_shift' => 'boolean',
        'reserve_stock_on_confirm' => 'boolean',
        'consume_stock_on_kitchen' => 'boolean',
        'allow_negative_stock' => 'boolean',
    ];

    public static function forTenant(int $tenantId): self
    {
        return static::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['next_order_number' => 1]
        );
    }
}

