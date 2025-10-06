<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $batches;
    protected $tenant;

    /**
     * Create a new notification instance.
     */
    public function __construct($batches, $tenant)
    {
        $this->batches = $batches;
        $this->tenant = $tenant;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->batches->count();
        $totalValue = $this->batches->sum(function($batch) {
            return $batch->quantity_available * $batch->cost_price;
        });
        
        $message = (new MailMessage)
            ->subject("🔴 {$count} Produto(s) Expirado(s) - {$this->tenant->name}")
            ->error()
            ->greeting("Atenção, {$notifiable->name}!")
            ->line("Você tem **{$count} lote(s)** de produtos que já expiraram:")
            ->line("**Valor Total em Risco: " . number_format($totalValue, 2, ',', '.') . " Kz**");
        
        foreach ($this->batches->take(10) as $batch) {
            $daysAgo = abs($batch->days_until_expiry);
            $value = $batch->quantity_available * $batch->cost_price;
            $message->line("• **{$batch->product->name}** (Lote: {$batch->batch_number}) - Expirado há **{$daysAgo} dia(s)** - Qtd: {$batch->quantity_available} - Valor: " . number_format($value, 2, ',', '.') . " Kz");
        }
        
        if ($count > 10) {
            $message->line("... e mais " . ($count - 10) . " produtos.");
        }
        
        $message->action('Ver Relatório de Validade', url('/invoicing/expiry-report?type=expired'))
                ->line('⚠️ **AÇÃO URGENTE NECESSÁRIA!**')
                ->line('• Retirar produtos do estoque')
                ->line('• Registrar baixa/perda')
                ->line('• Analisar causa da perda');
        
        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'product_expired',
            'title' => 'Produtos Expirados',
            'message' => "{$this->batches->count()} lote(s) de produtos já expiraram",
            'count' => $this->batches->count(),
            'tenant_id' => $this->tenant->id,
            'url' => '/invoicing/expiry-report?type=expired',
            'icon' => 'times-circle',
            'color' => 'red',
        ];
    }
}
