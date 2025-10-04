<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Warehouse;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            // Buscar categorias, marcas e taxa de IVA
            $categories = Category::where('tenant_id', $tenant->id)->get();
            $brands = Brand::where('tenant_id', $tenant->id)->get();
            $taxRate = TaxRate::where('tenant_id', $tenant->id)
                ->where('name', 'IVA 14%')
                ->first();
            $warehouse = Warehouse::where('tenant_id', $tenant->id)
                ->where('is_default', true)
                ->first();

            if ($categories->isEmpty() || $brands->isEmpty()) {
                $this->command->warn("Tenant {$tenant->name}: Categorias ou Marcas não encontradas. Pulando...");
                continue;
            }

            // Produtos de Eletrônicos
            $electronicsCategory = $categories->where('slug', 'computadores')->first();
            $smartphonesCategory = $categories->where('slug', 'smartphones-tablets')->first();
            $audioCategory = $categories->where('slug', 'audio-video')->first();

            $samsung = $brands->where('slug', 'samsung')->first();
            $apple = $brands->where('slug', 'apple')->first();
            $dell = $brands->where('slug', 'dell')->first();
            $hp = $brands->where('slug', 'hp')->first();
            $sony = $brands->where('slug', 'sony')->first();

            $products = [];

            // Computadores
            if ($electronicsCategory && $dell) {
                $products[] = [
                    'code' => 'COMP-001',
                    'name' => 'Notebook Dell Inspiron 15',
                    'description' => 'Intel Core i5, 8GB RAM, 256GB SSD, Windows 11',
                    'category_id' => $electronicsCategory->id,
                    'brand_id' => $dell->id,
                    'type' => 'produto',
                    'price' => 450000,
                    'cost' => 350000,
                    'unit' => 'UN',
                    'manage_stock' => true,
                    'stock_quantity' => 15,
                    'stock_min' => 5,
                    'stock_max' => 30,
                ];
            }

            if ($electronicsCategory && $hp) {
                $products[] = [
                    'code' => 'COMP-002',
                    'name' => 'Desktop HP ProDesk 400',
                    'description' => 'Intel Core i7, 16GB RAM, 512GB SSD, Monitor 24"',
                    'category_id' => $electronicsCategory->id,
                    'brand_id' => $hp->id,
                    'type' => 'produto',
                    'price' => 550000,
                    'cost' => 450000,
                    'unit' => 'UN',
                    'manage_stock' => true,
                    'stock_quantity' => 8,
                    'stock_min' => 3,
                    'stock_max' => 15,
                ];
            }

            // Smartphones
            if ($smartphonesCategory && $samsung) {
                $products[] = [
                    'code' => 'PHONE-001',
                    'name' => 'Samsung Galaxy S23',
                    'description' => '128GB, 8GB RAM, 5G, Câmera 50MP',
                    'category_id' => $smartphonesCategory->id,
                    'brand_id' => $samsung->id,
                    'type' => 'produto',
                    'price' => 380000,
                    'cost' => 300000,
                    'unit' => 'UN',
                    'manage_stock' => true,
                    'stock_quantity' => 25,
                    'stock_min' => 10,
                    'stock_max' => 50,
                ];
            }

            if ($smartphonesCategory && $apple) {
                $products[] = [
                    'code' => 'PHONE-002',
                    'name' => 'iPhone 14 Pro',
                    'description' => '256GB, 6GB RAM, 5G, Câmera ProRAW',
                    'category_id' => $smartphonesCategory->id,
                    'brand_id' => $apple->id,
                    'type' => 'produto',
                    'price' => 680000,
                    'cost' => 550000,
                    'unit' => 'UN',
                    'manage_stock' => true,
                    'stock_quantity' => 12,
                    'stock_min' => 5,
                    'stock_max' => 20,
                ];
            }

            // Áudio & Vídeo
            if ($audioCategory && $sony) {
                $products[] = [
                    'code' => 'TV-001',
                    'name' => 'Smart TV Sony Bravia 55"',
                    'description' => '4K UHD, Android TV, HDR, 120Hz',
                    'category_id' => $audioCategory->id,
                    'brand_id' => $sony->id,
                    'type' => 'produto',
                    'price' => 420000,
                    'cost' => 350000,
                    'unit' => 'UN',
                    'manage_stock' => true,
                    'stock_quantity' => 10,
                    'stock_min' => 3,
                    'stock_max' => 20,
                ];

                $products[] = [
                    'code' => 'AUDIO-001',
                    'name' => 'Headphones Sony WH-1000XM5',
                    'description' => 'Cancelamento de ruído, Bluetooth, 30h bateria',
                    'category_id' => $audioCategory->id,
                    'brand_id' => $sony->id,
                    'type' => 'produto',
                    'price' => 85000,
                    'cost' => 65000,
                    'unit' => 'UN',
                    'manage_stock' => true,
                    'stock_quantity' => 30,
                    'stock_min' => 15,
                    'stock_max' => 60,
                ];
            }

            // Alimentos & Bebidas
            $bebidasCategory = $categories->where('slug', 'bebidas')->first();
            $cocaCola = $brands->where('slug', 'coca-cola')->first();
            $nestle = $brands->where('slug', 'nestle')->first();

            if ($bebidasCategory && $cocaCola) {
                $products[] = [
                    'code' => 'BEB-001',
                    'name' => 'Coca-Cola 2L',
                    'description' => 'Refrigerante Coca-Cola 2 litros',
                    'category_id' => $bebidasCategory->id,
                    'brand_id' => $cocaCola->id,
                    'type' => 'produto',
                    'price' => 450,
                    'cost' => 300,
                    'unit' => 'UN',
                    'manage_stock' => true,
                    'stock_quantity' => 200,
                    'stock_min' => 50,
                    'stock_max' => 500,
                ];

                $products[] = [
                    'code' => 'BEB-002',
                    'name' => 'Água Mineral 1.5L',
                    'description' => 'Água mineral natural 1.5 litros',
                    'category_id' => $bebidasCategory->id,
                    'brand_id' => $cocaCola->id,
                    'type' => 'produto',
                    'price' => 200,
                    'cost' => 120,
                    'unit' => 'UN',
                    'manage_stock' => true,
                    'stock_quantity' => 300,
                    'stock_min' => 100,
                    'stock_max' => 600,
                ];
            }

            $merceariaCategory = $categories->where('slug', 'mercearia')->first();
            if ($merceariaCategory && $nestle) {
                $products[] = [
                    'code' => 'MERC-001',
                    'name' => 'Nescafé Gold 100g',
                    'description' => 'Café solúvel premium 100g',
                    'category_id' => $merceariaCategory->id,
                    'brand_id' => $nestle->id,
                    'type' => 'produto',
                    'price' => 1200.00,
                    'cost' => 850.00,
                    'unit' => 'UN',
                    'manage_stock' => true,
                    'stock_quantity' => 80,
                    'stock_min' => 20,
                    'stock_max' => 150,
                ];
            }

            // Vestuário
            $vestuarioCategory = $categories->where('slug', 'vestuario')->first();
            $nike = $brands->where('slug', 'nike')->first();
            $adidas = $brands->where('slug', 'adidas')->first();

            if ($vestuarioCategory && $nike) {
                $products[] = [
                    'code' => 'VEST-001',
                    'name' => 'Ténis Nike Air Max',
                    'description' => 'Ténis desportivo Nike Air Max, diversos tamanhos',
                    'category_id' => $vestuarioCategory->id,
                    'brand_id' => $nike->id,
                    'type' => 'produto',
                    'price' => 28000.00,
                    'cost' => 20000.00,
                    'unit' => 'PAR',
                    'manage_stock' => true,
                    'stock_quantity' => 45,
                    'stock_min' => 15,
                    'stock_max' => 100,
                ];
            }

            if ($vestuarioCategory && $adidas) {
                $products[] = [
                    'code' => 'VEST-002',
                    'name' => 'T-Shirt Adidas Original',
                    'description' => 'Camiseta desportiva Adidas, 100% algodão',
                    'category_id' => $vestuarioCategory->id,
                    'brand_id' => $adidas->id,
                    'type' => 'produto',
                    'price' => 4500,
                    'cost' => 3000,
                    'unit' => 'UN',
                    'manage_stock' => true,
                    'stock_quantity' => 120,
                    'stock_min' => 40,
                    'stock_max' => 200,
                ];
            }

            // Criar produtos
            foreach ($products as $productData) {
                $product = Product::create([
                    'tenant_id' => $tenant->id,
                    'code' => $productData['code'],
                    'name' => $productData['name'],
                    'description' => $productData['description'],
                    'category_id' => $productData['category_id'],
                    'brand_id' => $productData['brand_id'],
                    'type' => $productData['type'],
                    'price' => $productData['price'],
                    'cost' => $productData['cost'],
                    'unit' => $productData['unit'],
                    'tax_type' => 'iva',
                    'tax_rate_id' => $taxRate ? $taxRate->id : null,
                    'manage_stock' => $productData['manage_stock'],
                    'stock_quantity' => $productData['stock_quantity'],
                    'stock_min' => $productData['stock_min'],
                    'stock_max' => $productData['stock_max'],
                    'is_active' => true,
                ]);

                // Criar stock no armazém padrão
                if ($warehouse && $productData['manage_stock']) {
                    Stock::create([
                        'tenant_id' => $tenant->id,
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $product->id,
                        'quantity' => $productData['stock_quantity'],
                    ]);
                }
            }

            $this->command->info("Produtos criados para tenant: {$tenant->name}");
        }

        $this->command->info('Todos os produtos foram criados com sucesso!');
    }
}
