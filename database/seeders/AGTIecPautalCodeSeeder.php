<?php

namespace Database\Seeders;

use App\Models\AGT\AGTIecPautalCode;
use Illuminate\Database\Seeder;

/**
 * Seeder dos códigos pautais IEC mais comuns (DS.120 Anexo 9.7).
 *
 * NOTA: Lista NÃO exaustiva. Cobre as categorias principais:
 *  - Bebidas alcoólicas (cerveja, vinho, espirituosos)
 *  - Tabaco
 *  - Combustíveis
 *  - Veículos automóveis
 *  - Bebidas não-alcoólicas com açúcar
 *
 * A lista completa deve ser carregada por CSV oficial da AGT/Aduana.
 */
class AGTIecPautalCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            // === Bebidas alcoólicas ===
            ['2203', 'Cerveja de malte', AGTIecPautalCode::CAT_ALCOHOL, 25.00, null, null],
            ['22030000', 'Cerveja de malte (todas)', AGTIecPautalCode::CAT_ALCOHOL, 25.00, null, null],
            ['2204', 'Vinhos de uvas frescas', AGTIecPautalCode::CAT_ALCOHOL, 30.00, null, null],
            ['2205', 'Vermutes e outros vinhos aromatizados', AGTIecPautalCode::CAT_ALCOHOL, 30.00, null, null],
            ['2206', 'Outras bebidas fermentadas (sidra, etc.)', AGTIecPautalCode::CAT_ALCOHOL, 30.00, null, null],
            ['2207', 'Álcool etílico não desnaturado ≥80% vol.', AGTIecPautalCode::CAT_ALCOHOL, 50.00, null, null],
            ['2208', 'Aguardentes, licores e outras bebidas espirituosas', AGTIecPautalCode::CAT_ALCOHOL, 50.00, null, null],

            // === Tabaco ===
            ['2401', 'Tabaco em bruto', AGTIecPautalCode::CAT_TOBACCO, 70.00, null, null],
            ['2402', 'Charutos, cigarrilhas e cigarros', AGTIecPautalCode::CAT_TOBACCO, 70.00, null, null],
            ['24021000', 'Charutos e cigarrilhas com tabaco', AGTIecPautalCode::CAT_TOBACCO, 70.00, null, null],
            ['24022000', 'Cigarros com tabaco', AGTIecPautalCode::CAT_TOBACCO, 70.00, null, null],
            ['2403', 'Outros produtos de tabaco', AGTIecPautalCode::CAT_TOBACCO, 70.00, null, null],

            // === Combustíveis ===
            ['2710', 'Óleos de petróleo (gasolina, gasóleo, etc.)', AGTIecPautalCode::CAT_FUEL, 5.00, null, null],
            ['27101200', 'Óleos leves (gasolina)', AGTIecPautalCode::CAT_FUEL, 5.00, null, null],
            ['27101900', 'Outros óleos de petróleo (gasóleo)', AGTIecPautalCode::CAT_FUEL, 5.00, null, null],
            ['2711', 'Gás de petróleo (GPL)', AGTIecPautalCode::CAT_FUEL, 2.00, null, null],

            // === Veículos automóveis (variável conforme cilindrada) ===
            ['8703', 'Automóveis de passageiros', AGTIecPautalCode::CAT_VEHICLE, 10.00, null, null],
            ['87032190', 'Veículos cilindrada ≤ 1000cc', AGTIecPautalCode::CAT_VEHICLE, 5.00, null, null],
            ['87032290', 'Veículos cilindrada 1000–1500cc', AGTIecPautalCode::CAT_VEHICLE, 10.00, null, null],
            ['87032390', 'Veículos cilindrada 1500–3000cc', AGTIecPautalCode::CAT_VEHICLE, 15.00, null, null],
            ['87032490', 'Veículos cilindrada > 3000cc', AGTIecPautalCode::CAT_VEHICLE, 30.00, null, null],
            ['8704', 'Veículos automóveis de transporte de mercadorias', AGTIecPautalCode::CAT_VEHICLE, 5.00, null, null],

            // === Bebidas não-alcoólicas com açúcar ===
            ['2202', 'Águas adicionadas de açúcar / refrigerantes', AGTIecPautalCode::CAT_BEVERAGE, 10.00, null, null],
            ['22021000', 'Águas com açúcar', AGTIecPautalCode::CAT_BEVERAGE, 10.00, null, null],
            ['22029900', 'Outras bebidas não-alcoólicas', AGTIecPautalCode::CAT_BEVERAGE, 10.00, null, null],

            // === Produtos de luxo (categoria genérica) ===
            ['7113', 'Joalharia de metais preciosos', AGTIecPautalCode::CAT_LUXURY, 25.00, null, null],
            ['7114', 'Ourivesaria de metais preciosos', AGTIecPautalCode::CAT_LUXURY, 25.00, null, null],
            ['9101', 'Relógios de pulso (caixa metal precioso)', AGTIecPautalCode::CAT_LUXURY, 20.00, null, null],
            ['9302', 'Armas curtas (revólveres e pistolas)', AGTIecPautalCode::CAT_LUXURY, 50.00, null, null],
        ];

        $rows = [];
        foreach ($codes as [$code, $desc, $cat, $rate, $specific, $unit]) {
            $rows[] = [
                'pautal_code'     => $code,
                'description'     => $desc,
                'category'        => $cat,
                'rate_percentage' => $rate,
                'specific_amount' => $specific,
                'unit_of_measure' => $unit,
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];
        }

        // Upsert (idempotente)
        foreach ($rows as $row) {
            AGTIecPautalCode::updateOrCreate(
                ['pautal_code' => $row['pautal_code']],
                $row
            );
        }

        $this->command?->info('AGTIecPautalCodeSeeder: ' . count($rows) . ' códigos pautais IEC seedados.');
    }
}
