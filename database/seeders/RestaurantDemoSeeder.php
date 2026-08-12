<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Models\Restaurant\Reservation;
use App\Models\Restaurant\Recipe;
use App\Models\Restaurant\RecipeItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Invoicing\TaxResolver;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\RestaurantKitchenService;
use App\Services\Tenant\TenantModuleSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class RestaurantDemoSeeder extends Seeder
{
    private const EMAIL = 'softecangola@gmail.com';

    public function run(): void
    {
        $user = User::whereRaw('LOWER(email) = ?', [self::EMAIL])->first();
        $tenant = Tenant::whereRaw('LOWER(email) = ?', [self::EMAIL])->first();

        if (!$tenant && $user) {
            $tenant = $user->tenants()->first()
                ?? ($user->tenant_id ? Tenant::find($user->tenant_id) : null);
        }
        if (!$user && $tenant) {
            $user = $tenant->users()->first();
        }
        if (!$tenant || !$user) {
            throw new \RuntimeException('Não foi possível localizar utilizador e empresa para ' . self::EMAIL);
        }

        setPermissionsTeamId($tenant->id);

        // Ativação oficial: resolve Facturação e Tesouraria e semeia pré-requisitos.
        app(TenantModuleSyncService::class)->activateModule($tenant, 'restaurant');

        // Acesso explícito pedido para este utilizador, sem depender do nome da role.
        $permissions = Permission::where('name', 'like', 'restaurant.%')->get();
        $user->givePermissionTo($permissions);

        $warehouse = \App\Models\Invoicing\Warehouse::getDefault($tenant->id)
            ?? \App\Models\Invoicing\Warehouse::getOrCreateDefault($tenant->id);
        $settings = RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $settings->update(['default_warehouse_id' => $warehouse->id]);

        $venue = Venue::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'PRINCIPAL'],
            ['name' => 'Softec Restaurante Demo', 'warehouse_id' => $warehouse->id, 'is_active' => true]
        );

        $areas = collect([
            ['name' => 'Sala Principal', 'order' => 1],
            ['name' => 'Esplanada', 'order' => 2],
            ['name' => 'Zona VIP', 'order' => 3],
        ])->mapWithKeys(function (array $area) use ($tenant, $venue) {
            $model = Area::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'venue_id' => $venue->id, 'name' => $area['name']],
                ['sort_order' => $area['order'], 'is_active' => true]
            );
            return [$area['name'] => $model];
        });

        $tableDefinitions = [
            ['M01', 'Mesa 01', 'Sala Principal', 4], ['M02', 'Mesa 02', 'Sala Principal', 4],
            ['M03', 'Mesa 03', 'Sala Principal', 6], ['M04', 'Mesa 04', 'Sala Principal', 2],
            ['M05', 'Mesa 05', 'Sala Principal', 8], ['M06', 'Mesa 06', 'Sala Principal', 4],
            ['ESP01', 'Esplanada 01', 'Esplanada', 4], ['ESP02', 'Esplanada 02', 'Esplanada', 4],
            ['ESP03', 'Esplanada 03', 'Esplanada', 6], ['VIP01', 'Mesa VIP 01', 'Zona VIP', 6],
            ['VIP02', 'Mesa VIP 02', 'Zona VIP', 8], ['VIP03', 'Mesa VIP 03', 'Zona VIP', 4],
        ];
        foreach ($tableDefinitions as [$code, $name, $areaName, $capacity]) {
            DiningTable::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'venue_id' => $venue->id, 'code' => $code],
                ['area_id' => $areas[$areaName]->id, 'name' => $name, 'capacity' => $capacity, 'is_active' => true]
            );
        }

        $catalog = [
            'Entradas' => [
                ['ENT-001', 'Pastéis de Carne', 2500, 900],
                ['ENT-002', 'Chouriço Assado', 4500, 1800],
                ['ENT-003', 'Salada Tropical', 3500, 1200],
            ],
            'Pratos Principais' => [
                ['PRT-001', 'Muamba de Galinha', 8500, 3500],
                ['PRT-002', 'Calulu de Peixe', 9000, 3800],
                ['PRT-003', 'Funge com Carne Seca', 7500, 3000],
                ['PRT-004', 'Bife da Casa', 11000, 4800],
                ['PRT-005', 'Peixe Grelhado', 9500, 4100],
            ],
            'Bebidas' => [
                ['BEB-001', 'Água Mineral', 700, 250],
                ['BEB-002', 'Refrigerante', 1200, 450],
                ['BEB-003', 'Sumo Natural', 2000, 700],
                ['BEB-004', 'Café Expresso', 900, 250],
            ],
            'Sobremesas' => [
                ['SOB-001', 'Mousse de Maracujá', 2800, 900],
                ['SOB-002', 'Doce de Ginguba', 2500, 800],
                ['SOB-003', 'Fruta da Época', 2200, 700],
            ],
        ];

        $defaultTax = TaxResolver::defaultTax($tenant->id);
        $taxData = TaxResolver::forProduct(null, $tenant->id);
        $isExempt = (float) ($taxData['rate'] ?? 0) <= 0;
        $products = collect();

        foreach ($catalog as $categoryName => $items) {
            $category = Category::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $categoryName],
                ['slug' => \Illuminate\Support\Str::slug($categoryName), 'is_active' => true]
            );
            foreach ($items as [$sku, $name, $price, $cost]) {
                $product = Product::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'sku' => $sku],
                    [
                        'category_id' => $category->id,
                        'type' => 'produto',
                        'code' => $sku,
                        'name' => $name,
                        'description' => 'Artigo demonstrativo do módulo Restaurante',
                        'price' => $price,
                        'cost' => $cost,
                        'unit' => 'UN',
                        'tax_type' => $isExempt ? 'isento' : 'iva',
                        'tax_rate_id' => $isExempt ? null : $defaultTax?->id,
                        'exemption_reason' => $isExempt ? ($taxData['exemption_code'] ?? 'M04') : null,
                        'manage_stock' => false,
                        'is_active' => true,
                    ]
                );
                $products->put($sku, $product);
            }
        }

        $ingredientCategory = Category::withoutGlobalScopes()->updateOrCreate(['tenant_id'=>$tenant->id,'name'=>'Ingredientes'],['slug'=>'ingredientes','is_active'=>true]);
        $ingredients = collect();
        foreach ([['ING-GAL','Galinha', 'KG',3500],['ING-DEN','Óleo de Palma','L',2200],['ING-QUI','Quiabo','KG',1800]] as [$sku,$name,$unit,$cost]) {
            $ingredients[$sku]=Product::withoutGlobalScopes()->updateOrCreate(['tenant_id'=>$tenant->id,'sku'=>$sku],['category_id'=>$ingredientCategory->id,'type'=>'produto','code'=>$sku,'name'=>$name,'price'=>$cost,'cost'=>$cost,'unit'=>$unit,'tax_type'=>$isExempt?'isento':'iva','tax_rate_id'=>$isExempt?null:$defaultTax?->id,'exemption_reason'=>$isExempt?($taxData['exemption_code']??'M04'):null,'manage_stock'=>true,'is_active'=>true]);
        }
        $muambaRecipe=Recipe::withoutGlobalScopes()->updateOrCreate(['tenant_id'=>$tenant->id,'product_id'=>$products['PRT-001']->id],['yield_quantity'=>1,'yield_unit'=>'UN','is_active'=>true]);
        foreach ([['ING-GAL',.35,'KG',5],['ING-DEN',.05,'L',2],['ING-QUI',.12,'KG',8]] as [$sku,$qty,$unit,$waste]) RecipeItem::withoutGlobalScopes()->updateOrCreate(['tenant_id'=>$tenant->id,'recipe_id'=>$muambaRecipe->id,'ingredient_product_id'=>$ingredients[$sku]->id],['quantity'=>$qty,'unit'=>$unit,'waste_percent'=>$waste]);

        // Duas comandas demonstrativas, criadas pelo mesmo serviço usado pela UI.
        $service = app(RestaurantOrderService::class);
        $this->createDemoOrder($service, $tenant->id, $user->id, $venue, 'M01', 'demo-restaurant-m01', [
            ['PRT-001', 2, 'Pouco picante'], ['BEB-003', 2, null], ['SOB-001', 1, null],
        ], $products, true);
        $this->createDemoOrder($service, $tenant->id, $user->id, $venue, 'ESP01', 'demo-restaurant-esp01', [
            ['ENT-001', 1, null], ['PRT-005', 1, 'Bem passado'], ['BEB-001', 2, null],
        ], $products, false);

        $this->command?->info("Restaurante demo preparado para tenant {$tenant->id} ({$tenant->name}) e utilizador {$user->email}.");

        foreach (Order::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'confirmed')->with('items')->get() as $pendingOrder) {
            if ($pendingOrder->items->contains('kitchen_status', 'queued')) {
                app(RestaurantKitchenService::class)->dispatch($pendingOrder, $tenant->id, $user->id);
            }
        }

        foreach ([
            ['guest_name' => 'Ana Manuel', 'phone' => '923 000 001', 'guest_count' => 4, 'hour' => 19],
            ['guest_name' => 'José Domingos', 'phone' => '923 000 002', 'guest_count' => 2, 'hour' => 20],
        ] as $i => $demoReservation) {
            Reservation::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'reservation_number' => 'RES-DEMO-'.($i + 1)],
                ['venue_id' => $venue->id, 'table_id' => null, 'guest_name' => $demoReservation['guest_name'], 'phone' => $demoReservation['phone'], 'guest_count' => $demoReservation['guest_count'], 'reserved_at' => now()->setTime($demoReservation['hour'], 0), 'duration_minutes' => 120, 'status' => 'pending', 'created_by' => $user->id]
            );
        }
    }

    private function createDemoOrder(
        RestaurantOrderService $service,
        int $tenantId,
        int $userId,
        Venue $venue,
        string $tableCode,
        string $uuid,
        array $lines,
        $products,
        bool $confirm
    ): void {
        $existing = Order::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('local_uuid', $uuid)->first();
        if ($existing) {
            return;
        }
        $table = DiningTable::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('venue_id', $venue->id)->where('code', $tableCode)->firstOrFail();
        $order = $service->open([
            'venue_id' => $venue->id, 'table_id' => $table->id,
            'guest_count' => 2, 'local_uuid' => $uuid,
        ], $tenantId, $userId);
        foreach ($lines as [$sku, $quantity, $notes]) {
            $service->addItem($order, $products[$sku]->id, $quantity, $notes, $tenantId, $userId);
        }
        if ($confirm) {
            $service->confirm($order->fresh('items'), $tenantId, $userId);
        }
    }
}
