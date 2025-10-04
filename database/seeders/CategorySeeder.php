<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        $categories = [
            // Categorias Principais
            [
                'name' => 'Eletrônicos',
                'slug' => 'eletronicos',
                'description' => 'Produtos eletrônicos e tecnologia',
                'icon' => 'fa-laptop',
                'color' => '#3B82F6',
                'parent_id' => null,
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => 'Alimentos & Bebidas',
                'slug' => 'alimentos-bebidas',
                'description' => 'Produtos alimentícios e bebidas',
                'icon' => 'fa-utensils',
                'color' => '#10B981',
                'parent_id' => null,
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => 'Vestuário',
                'slug' => 'vestuario',
                'description' => 'Roupas, calçados e acessórios',
                'icon' => 'fa-tshirt',
                'color' => '#8B5CF6',
                'parent_id' => null,
                'is_active' => true,
                'order' => 3,
            ],
            [
                'name' => 'Casa & Decoração',
                'slug' => 'casa-decoracao',
                'description' => 'Móveis, decoração e utensílios domésticos',
                'icon' => 'fa-home',
                'color' => '#F59E0B',
                'parent_id' => null,
                'is_active' => true,
                'order' => 4,
            ],
            [
                'name' => 'Ferramentas & Construção',
                'slug' => 'ferramentas-construcao',
                'description' => 'Ferramentas, materiais de construção',
                'icon' => 'fa-wrench',
                'color' => '#EF4444',
                'parent_id' => null,
                'is_active' => true,
                'order' => 5,
            ],
            [
                'name' => 'Saúde & Beleza',
                'slug' => 'saude-beleza',
                'description' => 'Produtos de saúde, higiene e beleza',
                'icon' => 'fa-heart',
                'color' => '#EC4899',
                'parent_id' => null,
                'is_active' => true,
                'order' => 6,
            ],
            [
                'name' => 'Escritório & Papelaria',
                'slug' => 'escritorio-papelaria',
                'description' => 'Material de escritório e papelaria',
                'icon' => 'fa-pen',
                'color' => '#6366F1',
                'parent_id' => null,
                'is_active' => true,
                'order' => 7,
            ],
        ];

        foreach ($tenants as $tenant) {
            $createdCategories = [];

            foreach ($categories as $categoryData) {
                $category = Category::create([
                    'tenant_id' => $tenant->id,
                    'name' => $categoryData['name'],
                    'slug' => $categoryData['slug'],
                    'description' => $categoryData['description'],
                    'icon' => $categoryData['icon'],
                    'color' => $categoryData['color'],
                    'parent_id' => $categoryData['parent_id'],
                    'is_active' => $categoryData['is_active'],
                    'order' => $categoryData['order'],
                ]);

                $createdCategories[$categoryData['slug']] = $category->id;
            }

            // Subcategorias de Eletrônicos
            $electronicsCat = $createdCategories['eletronicos'];
            Category::create([
                'tenant_id' => $tenant->id,
                'name' => 'Computadores',
                'slug' => 'computadores',
                'description' => 'Desktops, notebooks e acessórios',
                'icon' => 'fa-desktop',
                'color' => '#3B82F6',
                'parent_id' => $electronicsCat,
                'is_active' => true,
                'order' => 1,
            ]);

            Category::create([
                'tenant_id' => $tenant->id,
                'name' => 'Smartphones & Tablets',
                'slug' => 'smartphones-tablets',
                'description' => 'Telemóveis e tablets',
                'icon' => 'fa-mobile',
                'color' => '#3B82F6',
                'parent_id' => $electronicsCat,
                'is_active' => true,
                'order' => 2,
            ]);

            Category::create([
                'tenant_id' => $tenant->id,
                'name' => 'Áudio & Vídeo',
                'slug' => 'audio-video',
                'description' => 'TVs, colunas, headphones',
                'icon' => 'fa-headphones',
                'color' => '#3B82F6',
                'parent_id' => $electronicsCat,
                'is_active' => true,
                'order' => 3,
            ]);

            // Subcategorias de Alimentos
            $foodCat = $createdCategories['alimentos-bebidas'];
            Category::create([
                'tenant_id' => $tenant->id,
                'name' => 'Bebidas',
                'slug' => 'bebidas',
                'description' => 'Água, sumos, refrigerantes',
                'icon' => 'fa-glass-water',
                'color' => '#10B981',
                'parent_id' => $foodCat,
                'is_active' => true,
                'order' => 1,
            ]);

            Category::create([
                'tenant_id' => $tenant->id,
                'name' => 'Mercearia',
                'slug' => 'mercearia',
                'description' => 'Produtos de mercearia geral',
                'icon' => 'fa-shopping-basket',
                'color' => '#10B981',
                'parent_id' => $foodCat,
                'is_active' => true,
                'order' => 2,
            ]);
        }

        $this->command->info('Categorias criadas com sucesso para todos os tenants!');
    }
}
