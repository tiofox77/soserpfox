<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            ModuleSeeder::class,
            PlanSeeder::class,
            ModulePlansSeeder::class,
            SuperAdminSeeder::class,
            // Tabelas de referência AGT (DS.120 v1.1)
            AGTTaxExemptionCodeSeeder::class,
            AGTIsVerbaSeeder::class,
            AGTCaeCodeSeeder::class,
            AGTIecPautalCodeSeeder::class,
            // Os textos dos avisos de facturação ao cliente. Sem eles o serviço
            // de avisos não tem o que enviar e regista falha em cada tentativa.
            AvisosDeSubscricaoTemplatesSeeder::class,
        ]);
    }
}
