<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductExpiringNotification extends Notification implements ShouldQueue
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
        
        $message = (new MailMessage)
            ->subject("⚠️ {$count} Produto(s) Expirando em Breve - {$this->tenant->name}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Você tem **{$count} lote(s)** de produtos que estão próximos da validade:");
        
        foreach ($this->batches->take(10) as $batch) {
            $days = $batch->days_until_expiry;
            $message->line("• **{$batch->product->name}** (Lote: {$batch->batch_number}) - Expira em **{$days} dia(s)** - Qtd: {$batch->quantity_available}");
        }
        
        if ($count > 10) {
            $message->line("... e mais " . ($count - 10) . " produtos.");
        }
        
        $message->action('Ver Relatório de Validade', url('/invoicing/expiry-report'))
                ->line('É importante tomar ação para evitar perdas!')
                ->line('Considere fazer promoções ou descontos para estes produtos.');
        
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
            'type' => 'product_expiring',
            'title' => 'Produtos Expirando em Breve',
            'message' => "{$this->batches->count()} lote(s) de produtos estão próximos da validade",
            'count' => $this->batches->count(),
            'tenant_id' => $this->tenant->id,
            'url' => '/invoicing/expiry-report',
            'icon' => 'exclamation-triangle',
            'color' => 'orange',
        ];
    }
}
