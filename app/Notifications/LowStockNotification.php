<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public $product;
    public $currentStock;
    public $minStock;

    public function __construct($product, $currentStock, $minStock)
    {
        $this->product = $product;
        $this->currentStock = $currentStock;
        $this->minStock = $minStock;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'low_stock',
            'title' => 'Estoque Baixo',
            'message' => "Produto '{$this->product->name}' está com estoque baixo: {$this->currentStock} unidades (mínimo: {$this->minStock})",
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'current_stock' => $this->currentStock,
            'min_stock' => $this->minStock,
            'icon' => 'fa-box-open',
            'color' => 'orange',
            'url' => '/products/' . $this->product->id,
        ];
    }
}
