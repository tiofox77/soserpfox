<?php
namespace App\Models\Restaurant;
use App\Traits\BelongsToTenant;use Illuminate\Database\Eloquent\Model;
class Recipe extends Model {use BelongsToTenant;protected $table='restaurant_recipes';protected $fillable=['tenant_id','product_id','yield_quantity','yield_unit','is_active'];protected $casts=['yield_quantity'=>'decimal:4','is_active'=>'boolean'];public function product(){return $this->belongsTo(\App\Models\Product::class);}public function items(){return $this->hasMany(RecipeItem::class);}}
