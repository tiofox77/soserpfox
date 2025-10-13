<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InvoiceExpiringNotification extends Notification
{
    use Queueable;

    public $invoice;
    public $daysUntilDue;

    public function __construct($invoice, $daysUntilDue)
    {
        $this->invoice = $invoice;
        $this->daysUntilDue = $daysUntilDue;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject("🔔 Lembrete: Fatura #{$this->invoice->invoice_number} vence em {$this->daysUntilDue} dia(s)")
            ->greeting('Olá, ' . $notifiable->name)
            ->line("A fatura #{$this->invoice->invoice_number} vence em {$this->daysUntilDue} dia(s).")
            ->line("**Cliente:** {$this->invoice->client_name}")
            ->line("**Valor:** " . number_format($this->invoice->total, 2) . " Kz")
            ->line("**Data de Vencimento:** " . $this->invoice->due_date->format('d/m/Y'))
            ->action('Ver Fatura', url('/invoices/' . $this->invoice->id))
            ->line('Lembre o cliente sobre o pagamento para evitar atrasos.');
    }

    public function toArray($notifiable)
    {
        $color = $this->daysUntilDue <= 3 ? 'orange' : 'yellow';
        
        return [
            'type' => 'invoice_expiring',
            'title' => 'Fatura Vencendo em Breve',
            'message' => "Fatura #{$this->invoice->invoice_number} vence em {$this->daysUntilDue} dia(s) - Cliente: {$this->invoice->client_name} - Valor: " . number_format($this->invoice->total, 2) . " Kz",
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'client_name' => $this->invoice->client_name,
            'total' => $this->invoice->total,
            'due_date' => $this->invoice->due_date->format('Y-m-d'),
            'days_until_due' => $this->daysUntilDue,
            'icon' => 'fa-clock',
            'color' => $color,
            'url' => '/invoices/' . $this->invoice->id,
        ];
    }
}
