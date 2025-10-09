<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $toEmail;
    protected $data;

    /**
     * Create a new job instance.
     */
    public function __construct(string $toEmail, array $data)
    {
        $this->toEmail = $toEmail;
        $this->data = $data;
        
        \Log::info('📦 Job SendWelcomeEmail criado', [
            'to' => $toEmail,
            'data_keys' => array_keys($data),
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \Log::info('🚀 Job SendWelcomeEmail executando (contexto limpo, igual CLI)', [
            'to' => $this->toEmail,
        ]);

        try {
            // Usar o mesmo método que funciona no CLI
            \App\Models\EmailTemplate::sendEmail(
                templateSlug: 'welcome',
                toEmail: $this->toEmail,
                data: $this->data,
                tenantId: null
            );

            \Log::info('✅ Job SendWelcomeEmail concluído com sucesso');
        } catch (\Exception $e) {
            \Log::error('❌ Job SendWelcomeEmail falhou', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e; // Re-throw para retry automático
        }
    }
}
