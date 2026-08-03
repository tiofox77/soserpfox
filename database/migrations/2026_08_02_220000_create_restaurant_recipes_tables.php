<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('restaurant_recipes',function(Blueprint $t){$t->id();$t->foreignId('tenant_id')->constrained()->cascadeOnDelete();$t->foreignId('product_id')->constrained('invoicing_products')->cascadeOnDelete();$t->decimal('yield_quantity',18,4)->default(1);$t->string('yield_unit',10)->default('UN');$t->boolean('is_active')->default(true);$t->timestamps();$t->unique(['tenant_id','product_id']);});
  Schema::create('restaurant_recipe_items',function(Blueprint $t){$t->id();$t->foreignId('tenant_id')->constrained()->cascadeOnDelete();$t->foreignId('recipe_id')->constrained('restaurant_recipes')->cascadeOnDelete();$t->foreignId('ingredient_product_id')->constrained('invoicing_products')->restrictOnDelete();$t->decimal('quantity',18,4);$t->string('unit',10)->default('UN');$t->decimal('waste_percent',8,4)->default(0);$t->timestamps();$t->unique(['tenant_id','recipe_id','ingredient_product_id'],'restaurant_recipe_ingredient_unique');});
  Schema::table('restaurant_order_items',function(Blueprint $t){$t->timestamp('stock_consumed_at')->nullable()->after('kitchen_status');});
 }
 public function down():void {Schema::table('restaurant_order_items',fn(Blueprint $t)=>$t->dropColumn('stock_consumed_at'));Schema::dropIfExists('restaurant_recipe_items');Schema::dropIfExists('restaurant_recipes');}
};
