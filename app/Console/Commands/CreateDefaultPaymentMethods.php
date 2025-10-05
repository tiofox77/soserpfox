<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Treasury\PaymentMethod;
use App\Models\Tenant;

class CreateDefaultPaymentMethods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'treasury:create-default-payment-methods {--tenant= : Tenant ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Criar métodos de pagamento padrão para todos os tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->option('tenant');
        
        if (!$tenantId) {
            // Buscar todos os tenants
            $tenants = Tenant::all();
            
            if ($tenants->isEmpty()) {
                $this->error('Nenhum tenant encontrado!');
                return 1;
            }
            
            foreach ($tenants as $tenant) {
                $this->createPaymentMethodsForTenant($tenant->id);
            }
        } else {
            $this->createPaymentMethodsForTenant($tenantId);
        }
        
        $this->info('✅ Métodos de pagamento padrão criados com sucesso!');
        return 0;
    }
    
    private function createPaymentMethodsForTenant($tenantId)
    {
        $this->info("Criando métodos de pagamento para Tenant ID: {$tenantId}");
        
        // Métodos de pagamento padrão do sistema
        $defaultMethods = [
            [
                'code' => 'cash',
                'name' => '💵 Dinheiro',
                'type' => 'cash',
                'description' => 'Pagamento em dinheiro',
                'requires_reference' => false,
            ],
            [
                'code' => 'transfer',
                'name' => '🏦 Transferência Bancária',
                'type' => 'bank_transfer',
                'description' => 'Transferência bancária',
                'requires_reference' => true,
            ],
            [
                'code' => 'multicaixa',
                'name' => '💳 Multicaixa Express',
                'type' => 'card',
                'description' => 'Pagamento via Multicaixa Express',
                'requires_reference' => true,
            ],
            [
                'code' => 'tpa',
                'name' => '💳 TPA (Terminal POS)',
                'type' => 'card',
                'description' => 'Pagamento via Terminal POS',
                'requires_reference' => true,
            ],
            [
                'code' => 'mbway',
                'name' => '📱 MB Way',
                'type' => 'digital_payment',
                'description' => 'Pagamento via MB Way',
                'requires_reference' => true,
            ],
            [
                'code' => 'cheque',
                'name' => '📝 Cheque',
                'type' => 'cheque',
                'description' => 'Pagamento com cheque',
                'requires_reference' => true,
            ],
        ];
        
        foreach ($defaultMethods as $method) {
            // Verificar se já existe
            $exists = PaymentMethod::where('tenant_id', $tenantId)
                ->where('code', $method['code'])
                ->exists();
            
            if ($exists) {
                $this->warn("  ⚠️  {$method['name']} já existe");
                continue;
            }
            
            // Criar método de pagamento
            PaymentMethod::create([
                'tenant_id' => $tenantId,
                'code' => $method['code'],
                'name' => $method['name'],
                'type' => $method['type'],
                'description' => $method['description'],
                'requires_reference' => $method['requires_reference'],
                'is_active' => true,
                'is_default' => $method['code'] === 'cash', // Dinheiro é padrão
            ]);
            
            $this->info("  ✅ {$method['name']} criado");
        }
    }
}
