<?php
namespace App\Services\Restaurant;
use App\Models\Invoicing\StockMovement;use App\Models\Restaurant\Order;use App\Models\Restaurant\OrderItem;use App\Models\Restaurant\Recipe;use App\Models\Restaurant\RestaurantSettings;use Illuminate\Support\Facades\DB;use InvalidArgumentException;
class RestaurantStockService {
 public function consume(Order $order, int $tenantId, ?int $userId): void
 {
  DB::transaction(function () use ($order, $tenantId, $userId) {
   $settings = RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $tenantId)->firstOrFail();
   $warehouse = $order->venue?->warehouse_id ?: $settings->default_warehouse_id;
   if (!$warehouse) {
    throw new InvalidArgumentException('Configure o armazém de consumo do restaurante.');
   }

   $items = OrderItem::withoutGlobalScopes()->where('tenant_id', $tenantId)
    ->where('order_id', $order->id)->whereNull('stock_consumed_at')->lockForUpdate()->get();
   if ($items->isEmpty()) {
    return;
   }

   // Pré-carregar receitas e produtos de UMA vez (era 2–3 queries por linha:
   // um Recipe::first() + um Product::find() em cada iteração). Agora fica em
   // dois SELECT, indexados por product_id, e o resto é em memória.
   $productIds = $items->pluck('product_id')->unique()->values();
   $recipes = Recipe::withoutGlobalScopes()->with('items')
    ->where('tenant_id', $tenantId)->whereIn('product_id', $productIds)
    ->where('is_active', true)->get()->keyBy('product_id');
   $produtos = \App\Models\Product::withoutGlobalScopes()
    ->where('tenant_id', $tenantId)->whereIn('id', $productIds)->get()->keyBy('id');

   foreach ($items as $line) {
    $recipe = $recipes->get($line->product_id);
    if ($recipe && $recipe->items->isNotEmpty()) {
     foreach ($recipe->items as $ingredient) {
      $qty = ((float) $ingredient->quantity / max(.0001, (float) $recipe->yield_quantity))
       * (float) $line->quantity * (1 + ((float) $ingredient->waste_percent / 100));
      // create() (não insert em massa): o StockObserver tem de correr para
      // manter o agregado invoicing_stocks — ver memória stock-integrity.
      StockMovement::withoutGlobalScopes()->create(['tenant_id' => $tenantId, 'warehouse_id' => $warehouse, 'product_id' => $ingredient->ingredient_product_id, 'type' => 'out', 'quantity' => $qty, 'reference_type' => Order::class, 'reference_id' => $order->id, 'user_id' => $userId, 'notes' => 'Consumo receita ' . $order->order_number]);
     }
    } else {
     $product = $produtos->get($line->product_id);
     if ($product?->manage_stock) {
      StockMovement::withoutGlobalScopes()->create(['tenant_id' => $tenantId, 'warehouse_id' => $warehouse, 'product_id' => $line->product_id, 'type' => 'out', 'quantity' => (float) $line->quantity, 'reference_type' => Order::class, 'reference_id' => $order->id, 'user_id' => $userId, 'notes' => 'Venda direta restaurante ' . $order->order_number]);
     }
    }
   }

   // Marcar tudo de uma vez, em vez de um UPDATE por linha.
   OrderItem::withoutGlobalScopes()->whereIn('id', $items->pluck('id'))->update(['stock_consumed_at' => now()]);
  });
 }
 public function waste(int $productId,float $quantity,int $warehouseId,string $reason,int $tenantId,?int $userId):StockMovement {if($quantity<=0||trim($reason)==='')throw new InvalidArgumentException('Informe quantidade e motivo do desperdício.');return DB::transaction(fn()=>StockMovement::withoutGlobalScopes()->create(['tenant_id'=>$tenantId,'warehouse_id'=>$warehouseId,'product_id'=>$productId,'type'=>'out','quantity'=>$quantity,'reference_type'=>'restaurant_waste','user_id'=>$userId,'notes'=>'Desperdício: '.$reason]));}
}
