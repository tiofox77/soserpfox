<?php

use App\Models\Invoicing\Warehouse;

if (!function_exists('defaultWarehouse')) {
    /**
     * Obtém o armazém padrão do tenant ativo
     * 
     * @return Warehouse|null
     */
    function defaultWarehouse()
    {
        return Warehouse::where('tenant_id', activeTenantId())
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
}

if (!function_exists('defaultWarehouseId')) {
    /**
     * Obtém o ID do armazém padrão do tenant ativo
     * 
     * @return int|null
     */
    function defaultWarehouseId()
    {
        $warehouse = defaultWarehouse();
        return $warehouse ? $warehouse->id : null;
    }
}

if (!function_exists('getOrCreateDefaultWarehouse')) {
    /**
     * Obtém o armazém padrão ou cria um se não existir
     * 
     * @return Warehouse
     */
    function getOrCreateDefaultWarehouse()
    {
        $warehouse = defaultWarehouse();
        
        if (!$warehouse) {
            $warehouse = Warehouse::create([
                'tenant_id' => activeTenantId(),
                'name' => 'Armazém Principal',
                'code' => 'ARM-001',
                'location' => 'Sede',
                'is_active' => true,
                'is_default' => true,
            ]);
        }
        
        return $warehouse;
    }
}
