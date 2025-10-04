<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        $suppliers = [
            [
                'type' => 'pessoa_juridica',
                'name' => 'Tech Supply Angola, Lda',
                'nif' => '5417890123',
                'email' => 'vendas@techsupply.ao',
                'phone' => '+244 222 123 456',
                'mobile' => '+244 923 456 789',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Rua Rainha Ginga, 45',
                'postal_code' => '1000',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Distribuidora Premium, SA',
                'nif' => '5418901234',
                'email' => 'comercial@distribuidorapremium.ao',
                'phone' => '+244 222 234 567',
                'mobile' => '+244 924 567 890',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Avenida 4 de Fevereiro, 123',
                'postal_code' => '1100',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Importadora Sul, Lda',
                'nif' => '5419012345',
                'email' => 'info@importadorasul.ao',
                'phone' => '+244 222 345 678',
                'mobile' => '+244 925 678 901',
                'country' => 'Angola',
                'province' => 'Benguela',
                'city' => 'Benguela',
                'address' => 'Rua do Porto, 78',
                'postal_code' => '2100',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Global Tech Suppliers',
                'nif' => '5410123456',
                'email' => 'sales@globaltech.com',
                'phone' => '+351 21 345 6789',
                'mobile' => '+351 91 234 5678',
                'country' => 'Portugal',
                'province' => null,
                'city' => 'Lisboa',
                'address' => 'Avenida da Liberdade, 250',
                'postal_code' => '1250-096',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Bebidas & Cia, Lda',
                'nif' => '5411234567',
                'email' => 'comercial@bebidasecia.ao',
                'phone' => '+244 222 456 789',
                'mobile' => '+244 926 789 012',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Zona Industrial de Viana, Lote 15',
                'postal_code' => '1500',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Alimentar Distribuição, SA',
                'nif' => '5412345678',
                'email' => 'vendas@alimentardistribuicao.ao',
                'phone' => '+244 222 567 890',
                'mobile' => '+244 927 890 123',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Rua Engrácia Fragoso, 89',
                'postal_code' => '1200',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Ferramentas Pro, Lda',
                'nif' => '5413456789',
                'email' => 'info@ferramentaspro.ao',
                'phone' => '+244 222 678 901',
                'mobile' => '+244 928 901 234',
                'country' => 'Angola',
                'province' => 'Huíla',
                'city' => 'Lubango',
                'address' => 'Avenida Norton de Matos, 156',
                'postal_code' => '3100',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Moda & Estilo Importações',
                'nif' => '5414567890',
                'email' => 'compras@modaestilo.ao',
                'phone' => '+244 222 789 012',
                'mobile' => '+244 929 012 345',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Rua Direita do Palanca, 234',
                'postal_code' => '1300',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Electrónica Mundial, SA',
                'nif' => '5415678901',
                'email' => 'vendas@electronicamundial.ao',
                'phone' => '+244 222 890 123',
                'mobile' => '+244 930 123 456',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Avenida Deolinda Rodrigues, 567',
                'postal_code' => '1400',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Oficina Peças & Serviços',
                'nif' => '5416789012',
                'email' => 'pecas@oficinapecas.ao',
                'phone' => '+244 222 901 234',
                'mobile' => '+244 931 234 567',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Zona Industrial de Cacuaco, Armazém 12',
                'postal_code' => '1600',
            ],
        ];

        foreach ($tenants as $tenant) {
            foreach ($suppliers as $supplierData) {
                Supplier::create([
                    'tenant_id' => $tenant->id,
                    'type' => $supplierData['type'],
                    'name' => $supplierData['name'],
                    'nif' => $supplierData['nif'],
                    'email' => $supplierData['email'],
                    'phone' => $supplierData['phone'],
                    'mobile' => $supplierData['mobile'],
                    'country' => $supplierData['country'],
                    'province' => $supplierData['province'],
                    'city' => $supplierData['city'],
                    'address' => $supplierData['address'],
                    'postal_code' => $supplierData['postal_code'],
                ]);
            }

            $this->command->info("Fornecedores criados para tenant: {$tenant->name}");
        }

        $this->command->info('Todos os fornecedores foram criados com sucesso!');
    }
}
