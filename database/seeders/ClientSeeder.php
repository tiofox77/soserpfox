<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        $clients = [
            // Pessoas Jurídicas
            [
                'type' => 'pessoa_juridica',
                'name' => 'Sonangol EP',
                'nif' => '5000123456',
                'email' => 'compras@sonangol.co.ao',
                'phone' => '+244 222 630 000',
                'mobile' => '+244 923 000 000',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Rua Rainha Ginga, 29',
                'postal_code' => '1000',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Unitel SA',
                'nif' => '5000234567',
                'email' => 'procurement@unitel.ao',
                'phone' => '+244 222 693 000',
                'mobile' => '+244 924 000 000',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Avenida Comandante Valódia, 203',
                'postal_code' => '1100',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'BFA - Banco de Fomento Angola',
                'nif' => '5000345678',
                'email' => 'compras@bfa.ao',
                'phone' => '+244 222 638 900',
                'mobile' => '+244 925 111 000',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Avenida 4 de Fevereiro, 151',
                'postal_code' => '1000',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Empresa Pública de Águas (EPAL Angola)',
                'nif' => '5000456789',
                'email' => 'logistica@epal.ao',
                'phone' => '+244 222 395 000',
                'mobile' => '+244 926 222 000',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Rua Cerqueira Lukoki, 25',
                'postal_code' => '1200',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Hotel Epic Sana Luanda',
                'nif' => '5000567890',
                'email' => 'compras@epicsanaluanda.ao',
                'phone' => '+244 222 692 000',
                'mobile' => '+244 927 333 000',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Avenida 4 de Fevereiro, Marginal',
                'postal_code' => '1000',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Shoprite Angola, Lda',
                'nif' => '5000678901',
                'email' => 'fornecedores@shoprite.ao',
                'phone' => '+244 222 445 000',
                'mobile' => '+244 928 444 000',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Bairro do Talatona, Xyami Shopping',
                'postal_code' => '1500',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Refriango - Coca-Cola Angola',
                'nif' => '5000789012',
                'email' => 'compras@refriango.ao',
                'phone' => '+244 222 445 500',
                'mobile' => '+244 929 555 000',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Zona Industrial de Viana',
                'postal_code' => '1500',
            ],
            [
                'type' => 'pessoa_juridica',
                'name' => 'Constructora Soares da Costa Angola',
                'nif' => '5000890123',
                'email' => 'admin@soaresdacosta.ao',
                'phone' => '+244 222 330 000',
                'mobile' => '+244 930 666 000',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Rua Kwame Nkrumah, 234',
                'postal_code' => '1300',
            ],
            
            // Pessoas Físicas
            [
                'type' => 'pessoa_fisica',
                'name' => 'João Manuel dos Santos',
                'nif' => '004123456LA035',
                'email' => 'joao.santos@email.com',
                'phone' => '+244 222 123 456',
                'mobile' => '+244 923 456 789',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Bairro da Maianga, Rua 15, Casa 45',
                'postal_code' => '1200',
            ],
            [
                'type' => 'pessoa_fisica',
                'name' => 'Maria da Conceição Silva',
                'nif' => '004234567LA035',
                'email' => 'maria.silva@email.com',
                'phone' => '+244 222 234 567',
                'mobile' => '+244 924 567 890',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Urbanização Nova Vida, Rua A, Casa 12',
                'postal_code' => '1500',
            ],
            [
                'type' => 'pessoa_fisica',
                'name' => 'António Carlos Fernandes',
                'nif' => '004345678LA035',
                'email' => 'antonio.fernandes@email.com',
                'phone' => '+244 222 345 678',
                'mobile' => '+244 925 678 901',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Bairro Miramar, Avenida Mortala Mohamed, Edifício 5, Apto 301',
                'postal_code' => '1400',
            ],
            [
                'type' => 'pessoa_fisica',
                'name' => 'Ana Paula Rodrigues',
                'nif' => '004456789LA035',
                'email' => 'ana.rodrigues@email.com',
                'phone' => '+244 222 456 789',
                'mobile' => '+244 926 789 012',
                'country' => 'Angola',
                'province' => 'Benguela',
                'city' => 'Benguela',
                'address' => 'Bairro Praia Morena, Rua 3, Casa 78',
                'postal_code' => '2100',
            ],
            [
                'type' => 'pessoa_fisica',
                'name' => 'Pedro Miguel Costa',
                'nif' => '004567890LA035',
                'email' => 'pedro.costa@email.com',
                'phone' => '+244 222 567 890',
                'mobile' => '+244 927 890 123',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Bairro Kinaxixe, Rua Amílcar Cabral, Edifício 12, 4º andar',
                'postal_code' => '1000',
            ],
            [
                'type' => 'pessoa_fisica',
                'name' => 'Isabel Maria Sousa',
                'nif' => '004678901LA035',
                'email' => 'isabel.sousa@email.com',
                'phone' => '+244 222 678 901',
                'mobile' => '+244 928 901 234',
                'country' => 'Angola',
                'province' => 'Huíla',
                'city' => 'Lubango',
                'address' => 'Bairro Comandante Cowboy, Rua 7, Casa 23',
                'postal_code' => '3100',
            ],
            [
                'type' => 'pessoa_fisica',
                'name' => 'Carlos Alberto Mendes',
                'nif' => '004789012LA035',
                'email' => 'carlos.mendes@email.com',
                'phone' => '+244 222 789 012',
                'mobile' => '+244 929 012 345',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Condomínio Jardim de Rosas, Bloco B, Apto 15',
                'postal_code' => '1500',
            ],
            [
                'type' => 'pessoa_fisica',
                'name' => 'Teresa de Jesus Martins',
                'nif' => '004890123LA035',
                'email' => 'teresa.martins@email.com',
                'phone' => '+244 222 890 123',
                'mobile' => '+244 930 123 456',
                'country' => 'Angola',
                'province' => 'Luanda',
                'city' => 'Luanda',
                'address' => 'Bairro Alvalade, Rua 21 de Janeiro, Casa 67',
                'postal_code' => '1200',
            ],
        ];

        foreach ($tenants as $tenant) {
            foreach ($clients as $clientData) {
                Client::create([
                    'tenant_id' => $tenant->id,
                    'type' => $clientData['type'],
                    'name' => $clientData['name'],
                    'nif' => $clientData['nif'],
                    'email' => $clientData['email'],
                    'phone' => $clientData['phone'],
                    'mobile' => $clientData['mobile'],
                    'country' => $clientData['country'],
                    'province' => $clientData['province'],
                    'city' => $clientData['city'],
                    'address' => $clientData['address'],
                    'postal_code' => $clientData['postal_code'],
                ]);
            }

            $this->command->info("Clientes criados para tenant: {$tenant->name}");
        }

        $this->command->info('Todos os clientes foram criados com sucesso!');
    }
}
