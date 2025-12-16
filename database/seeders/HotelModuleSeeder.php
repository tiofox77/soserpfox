<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class HotelModuleSeeder extends Seeder
{
    /**
     * Seed the Hotel module.
     */
    public function run(): void
    {
        Module::updateOrCreate(
            ['slug' => 'hotel'],
            [
                'name' => 'Gestão de Hotel',
                'description' => 'Sistema completo de gestão hoteleira com booking online, reservas, check-in/out, housekeeping e analytics.',
                'icon' => 'hotel',
                'version' => '1.0.0',
                'order' => 15,
                'is_active' => true,
                'is_core' => false,
            ]
        );

        $this->command->info('✅ Módulo Hotel criado/atualizado com sucesso!');
    }
}
