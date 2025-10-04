<?php

namespace Database\Seeders;

use App\Models\Invoicing\Warehouse;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Cria armazém padrão para cada tenant
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            // Verifica se já existe um armazém padrão
            $existingDefault = Warehouse::where('tenant_id', $tenant->id)
                ->where('is_default', true)
                ->first();

            if (!$existingDefault) {
                Warehouse::create([
                    'tenant_id' => $tenant->id,
                    'code' => 'ARM-PRINCIPAL',
                    'name' => 'Armazém Principal',
                    'location' => 'Sede',
                    'address' => $tenant->address ?? '',
                    'city' => 'Luanda',
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Armazéns padrão criados com sucesso!');
    }
}
