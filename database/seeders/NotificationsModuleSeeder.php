<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class NotificationsModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar o módulo de Notificações
        $module = Module::updateOrCreate(
            ['slug' => 'notifications'],
            [
                'name' => 'Notificações',
                'description' => 'Módulo de notificações multi-canal: Email, SMS e WhatsApp. Permite configurar e enviar notificações automáticas para funcionários.',
                'icon' => 'ri-notification-3-line',
                'is_active' => true,
                'is_core' => false,
                'order' => 100,
            ]
        );

        $this->command->info('✅ Módulo de Notificações criado: ' . $module->name);
        $this->command->info('   ID: ' . $module->id);
        $this->command->info('   Slug: ' . $module->slug);
        $this->command->info('');
        $this->command->info('🎉 Módulo disponível para ativação pelos tenants!');
        $this->command->info('   Acesse: /superadmin/modules para visualizar');
    }
}
