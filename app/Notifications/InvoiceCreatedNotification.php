<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InvoiceCreatedNotification extends Notification
{
    use Queueable;

    public $invoice;

    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'invoice_created',
            'title' => 'Nova Fatura Emitida',
            'message' => "Fatura #{$this->invoice->invoice_number} no valor de " . number_format($this->invoice->total, 2) . " Kz foi emitida",
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'total' => $this->invoice->total,
            'icon' => 'fa-file-invoice',
            'color' => 'green',
            'url' => '/invoices/' . $this->invoice->id,
        ];
    }
}
