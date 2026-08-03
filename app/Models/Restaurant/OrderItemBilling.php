<?php

namespace App\Models\Restaurant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OrderItemBilling extends Model
{
    use BelongsToTenant;
    protected $table = 'restaurant_order_item_billings';
    protected $fillable = ['tenant_id', 'order_id', 'order_item_id', 'sales_invoice_id', 'sales_invoice_item_id', 'quantity', 'amount'];
    protected $casts = ['quantity' => 'decimal:4', 'amount' => 'decimal:2'];
}
