<?php
namespace App\Models\Restaurant;
use App\Traits\BelongsToTenant;use Illuminate\Database\Eloquent\Model;
class RecipeItem extends Model {use BelongsToTenant;protected $table='restaurant_recipe_items';protected $fillable=['tenant_id','recipe_id','ingredient_product_id','quantity','unit','waste_percent'];protected $casts=['quantity'=>'decimal:4','waste_percent'=>'decimal:4'];public function ingredient(){return $this->belongsTo(\App\Models\Product::class,'ingredient_product_id');}public function recipe(){return $this->belongsTo(Recipe::class);}}
