<?php

namespace Database\Seeders;

use App\Models\AGT\AGTIsVerba;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DS.120 v1.1 — Anexo 9.6: Verbas e taxas de Imposto de Selo (IS).
 *
 * Aplicado quando `taxType = IS` e `taxCode = <verba_no>`.
 * Fonte: Código do Imposto de Selo (CIS) de Angola.
 */
class AGTIsVerbaSeeder extends Seeder
{
    public function run(): void
    {
        $verbas = [
            ['verba_no' => 1,  'description' => 'Aquisição onerosa de bens imóveis', 'rate' => '0.3%',  'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 2,  'description' => 'Arrendamento e subarrendamento',     'rate' => '0.4%',  'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 3,  'description' => 'Cheques (livros)',                    'rate' => 'AOA 100', 'rate_type' => 'FIXED'],
            ['verba_no' => 4,  'description' => 'Contratos em geral',                  'rate' => '1%',    'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 5,  'description' => 'Empréstimos e crédito (operações financeiras)', 'rate' => '0.1%',  'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 6,  'description' => 'Escritos de quitação',                'rate' => '1%',    'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 7,  'description' => 'Garantias bancárias / fianças',       'rate' => '0.3%',  'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 8,  'description' => 'Juros (operações financeiras)',       'rate' => '0.2%',  'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 9,  'description' => 'Letras e livranças',                  'rate' => '0.5%',  'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 10, 'description' => 'Licenças (administrativas)',          'rate' => 'AOA 1000', 'rate_type' => 'FIXED'],
            ['verba_no' => 11, 'description' => 'Locação financeira (leasing)',        'rate' => '0.4%',  'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 12, 'description' => 'Notariado e actos notariais',         'rate' => '1%',    'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 13, 'description' => 'Operações aduaneiras',                'rate' => '0.5%',  'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 14, 'description' => 'Operações de seguros (prémios)',      'rate' => '0.4%',  'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 15, 'description' => 'Procurações e mandatos',              'rate' => 'AOA 500',  'rate_type' => 'FIXED'],
            ['verba_no' => 16, 'description' => 'Reconhecimento de assinaturas',       'rate' => 'AOA 200',  'rate_type' => 'FIXED'],
            ['verba_no' => 17, 'description' => 'Reportes e operações similares',      'rate' => '0.5%',  'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 18, 'description' => 'Sucessões e doações',                 'rate' => '1%',    'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 19, 'description' => 'Trespasse de estabelecimento',        'rate' => '5%',    'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 20, 'description' => 'Títulos de crédito',                  'rate' => '0.5%',  'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 21, 'description' => 'Transmissões gratuitas',              'rate' => '0.5%',  'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 22, 'description' => 'Trespasses (cessões de exploração)',  'rate' => '5%',    'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 23, 'description' => 'Operações com valores mobiliários',   'rate' => '0.3%',  'rate_type' => 'PERCENTAGE'],
            ['verba_no' => 24, 'description' => 'Outras situações sujeitas a IS',      'rate' => '1%',    'rate_type' => 'PERCENTAGE'],
        ];

        DB::transaction(function () use ($verbas) {
            foreach ($verbas as $row) {
                AGTIsVerba::updateOrCreate(
                    ['verba_no' => $row['verba_no']],
                    $row + ['is_active' => true]
                );
            }
        });
    }
}
