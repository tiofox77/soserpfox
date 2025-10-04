<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        $brands = [
            // Eletrônicos
            [
                'name' => 'Samsung',
                'slug' => 'samsung',
                'description' => 'Marca líder em eletrônicos e tecnologia',
                'icon' => 'fa-mobile',
                'logo' => null,
                'website' => 'https://www.samsung.com',
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => 'Apple',
                'slug' => 'apple',
                'description' => 'Inovação em tecnologia premium',
                'icon' => 'fa-apple',
                'logo' => null,
                'website' => 'https://www.apple.com',
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => 'LG',
                'slug' => 'lg',
                'description' => 'Eletrônicos e eletrodomésticos',
                'icon' => 'fa-tv',
                'logo' => null,
                'website' => 'https://www.lg.com',
                'is_active' => true,
                'order' => 3,
            ],
            [
                'name' => 'Sony',
                'slug' => 'sony',
                'description' => 'Tecnologia e entretenimento',
                'icon' => 'fa-gamepad',
                'logo' => null,
                'website' => 'https://www.sony.com',
                'is_active' => true,
                'order' => 4,
            ],
            [
                'name' => 'HP',
                'slug' => 'hp',
                'description' => 'Computadores e impressoras',
                'icon' => 'fa-print',
                'logo' => null,
                'website' => 'https://www.hp.com',
                'is_active' => true,
                'order' => 5,
            ],
            [
                'name' => 'Dell',
                'slug' => 'dell',
                'description' => 'Soluções de computação',
                'icon' => 'fa-laptop',
                'logo' => null,
                'website' => 'https://www.dell.com',
                'is_active' => true,
                'order' => 6,
            ],
            [
                'name' => 'Lenovo',
                'slug' => 'lenovo',
                'description' => 'Computadores e dispositivos móveis',
                'icon' => 'fa-desktop',
                'logo' => null,
                'website' => 'https://www.lenovo.com',
                'is_active' => true,
                'order' => 7,
            ],
            // Alimentos & Bebidas
            [
                'name' => 'Coca-Cola',
                'slug' => 'coca-cola',
                'description' => 'Bebidas refrescantes',
                'icon' => 'fa-bottle-droplet',
                'logo' => null,
                'website' => 'https://www.coca-cola.com',
                'is_active' => true,
                'order' => 8,
            ],
            [
                'name' => 'Nestlé',
                'slug' => 'nestle',
                'description' => 'Nutrição, saúde e bem-estar',
                'icon' => 'fa-mug-hot',
                'logo' => null,
                'website' => 'https://www.nestle.com',
                'is_active' => true,
                'order' => 9,
            ],
            [
                'name' => 'Unilever',
                'slug' => 'unilever',
                'description' => 'Alimentos e produtos de higiene',
                'icon' => 'fa-soap',
                'logo' => null,
                'website' => 'https://www.unilever.com',
                'is_active' => true,
                'order' => 10,
            ],
            // Vestuário
            [
                'name' => 'Nike',
                'slug' => 'nike',
                'description' => 'Artigos desportivos e vestuário',
                'icon' => 'fa-shoe-prints',
                'logo' => null,
                'website' => 'https://www.nike.com',
                'is_active' => true,
                'order' => 11,
            ],
            [
                'name' => 'Adidas',
                'slug' => 'adidas',
                'description' => 'Desporto e estilo de vida',
                'icon' => 'fa-basketball',
                'logo' => null,
                'website' => 'https://www.adidas.com',
                'is_active' => true,
                'order' => 12,
            ],
            // Ferramentas
            [
                'name' => 'Bosch',
                'slug' => 'bosch',
                'description' => 'Ferramentas e tecnologia',
                'icon' => 'fa-screwdriver-wrench',
                'logo' => null,
                'website' => 'https://www.bosch.com',
                'is_active' => true,
                'order' => 13,
            ],
            [
                'name' => 'Makita',
                'slug' => 'makita',
                'description' => 'Ferramentas elétricas profissionais',
                'icon' => 'fa-hammer',
                'logo' => null,
                'website' => 'https://www.makita.com',
                'is_active' => true,
                'order' => 14,
            ],
            // Genérica
            [
                'name' => 'Genérica',
                'slug' => 'generica',
                'description' => 'Produtos sem marca específica',
                'icon' => 'fa-box',
                'logo' => null,
                'website' => null,
                'is_active' => true,
                'order' => 99,
            ],
        ];

        foreach ($tenants as $tenant) {
            foreach ($brands as $brandData) {
                Brand::create([
                    'tenant_id' => $tenant->id,
                    'name' => $brandData['name'],
                    'slug' => $brandData['slug'],
                    'description' => $brandData['description'],
                    'icon' => $brandData['icon'],
                    'logo' => $brandData['logo'],
                    'website' => $brandData['website'],
                    'is_active' => $brandData['is_active'],
                    'order' => $brandData['order'],
                ]);
            }
        }

        $this->command->info('Marcas criadas com sucesso para todos os tenants!');
    }
}
