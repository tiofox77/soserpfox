<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DefaultClientSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Para cada tenant, criar cliente "Consumidor Final"
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            // Verificar se já existe
            $exists = Client::where('tenant_id', $tenant->id)
                ->where('nif', '999999999')
                ->exists();
            
            if (!$exists) {
                Client::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Consumidor Final',
                    'nif' => '999999999', // NIF genérico Angola
                    'type' => 'pessoa_fisica',
                    'email' => 'consumidor.final@sistema.ao',
                    'phone' => '999999999',
                    'address' => 'Angola',
                    'city' => 'Luanda',
                    'country' => 'Angola',
                    'tax_regime' => 'geral',
                    'is_iva_subject' => true,
                    'is_active' => true,
                    'notes' => 'Cliente padrão para vendas a consumidor final sem identificação específica (conforme legislação angolana)',
                ]);
            }
        }
        
        $this->command->info('Cliente padrão "Consumidor Final" criado para todos os tenants!');
    }
}
