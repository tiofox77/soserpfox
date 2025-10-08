<?php

namespace Database\Seeders;

use App\Models\EquipmentCategory;
use Illuminate\Database\Seeder;

class EquipmentCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Som e Áudio', 'icon' => '🔊', 'color' => '#8b5cf6', 'sort_order' => 1],
            ['name' => 'Iluminação', 'icon' => '💡', 'color' => '#f59e0b', 'sort_order' => 2],
            ['name' => 'Vídeo', 'icon' => '📹', 'color' => '#ef4444', 'sort_order' => 3],
            ['name' => 'Estruturas', 'icon' => '🏗️', 'color' => '#6b7280', 'sort_order' => 4],
            ['name' => 'Efeitos Especiais', 'icon' => '✨', 'color' => '#ec4899', 'sort_order' => 5],
            ['name' => 'Decoração', 'icon' => '🎨', 'color' => '#10b981', 'sort_order' => 6],
            ['name' => 'Mobiliário', 'icon' => '🪑', 'color' => '#3b82f6', 'sort_order' => 7],
            ['name' => 'Energia', 'icon' => '⚡', 'color' => '#eab308', 'sort_order' => 8],
            ['name' => 'Outros', 'icon' => '📁', 'color' => '#64748b', 'sort_order' => 99],
        ];

        // Buscar todos os tenants ativos
        $tenants = \App\Models\Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            foreach ($categories as $category) {
                EquipmentCategory::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $category['name'],
                    ],
                    [
                        'icon' => $category['icon'],
                        'color' => $category['color'],
                        'sort_order' => $category['sort_order'],
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info('✅ Categorias de equipamentos criadas com sucesso!');
    }
}
