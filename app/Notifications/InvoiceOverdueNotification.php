<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InvoiceOverdueNotification extends Notification
{
    use Queueable;

    public $invoice;
    public $daysOverdue;

    public function __construct($invoice, $daysOverdue)
    {
        $this->invoice = $invoice;
        $this->daysOverdue = $daysOverdue;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        $urgency = $this->daysOverdue > 30 ? 'URGENTE' : 'IMPORTANTE';
        
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject("⚠️ {$urgency}: Fatura Vencida #{$this->invoice->invoice_number}")
            ->greeting('Olá, ' . $notifiable->name)
            ->line("A fatura #{$this->invoice->invoice_number} está vencida há {$this->daysOverdue} dia(s).")
            ->line("**Cliente:** {$this->invoice->client_name}")
            ->line("**Valor:** " . number_format($this->invoice->total, 2) . " Kz")
            ->line("**Data de Vencimento:** " . $this->invoice->due_date->format('d/m/Y'))
            ->when($this->daysOverdue > 30, function($mail) {
                return $mail->line('⚠️ **ATENÇÃO:** Esta fatura está vencida há mais de 30 dias!');
            })
            ->action('Ver Fatura', url('/invoices/' . $this->invoice->id))
            ->line('Entre em contato com o cliente para regularizar o pagamento.');
    }

    public function toArray($notifiable)
    {
        $urgencyLevel = $this->daysOverdue > 30 ? 'critical' : ($this->daysOverdue > 15 ? 'high' : 'medium');
        $color = $this->daysOverdue > 30 ? 'red' : ($this->daysOverdue > 15 ? 'orange' : 'yellow');
        
        return [
            'type' => 'invoice_overdue',
            'title' => 'Fatura Vencida',
            'message' => "Fatura #{$this->invoice->invoice_number} vencida há {$this->daysOverdue} dia(s) - Cliente: {$this->invoice->client_name} - Valor: " . number_format($this->invoice->total, 2) . " Kz",
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'client_name' => $this->invoice->client_name,
            'total' => $this->invoice->total,
            'due_date' => $this->invoice->due_date->format('Y-m-d'),
            'days_overdue' => $this->daysOverdue,
            'urgency' => $urgencyLevel,
            'icon' => 'fa-exclamation-triangle',
            'color' => $color,
            'url' => '/invoices/' . $this->invoice->id,
        ];
    }
}
