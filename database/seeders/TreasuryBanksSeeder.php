<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Treasury\Bank;

class TreasuryBanksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banks = [
            [
                'name' => 'Banco de Fomento Angola',
                'code' => 'BFA',
                'swift_code' => 'BFAOAOAO',
                'country' => 'AO',
                'website' => 'https://www.bfa.ao',
                'phone' => '+244 222 638 900',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Angolano de Investimentos',
                'code' => 'BAI',
                'swift_code' => 'BAAOAOAO',
                'country' => 'AO',
                'website' => 'https://www.bancobai.ao',
                'phone' => '+244 222 691 919',
                'is_active' => true,
            ],
            [
                'name' => 'Banco BIC',
                'code' => 'BIC',
                'swift_code' => 'BICAAOAO',
                'country' => 'AO',
                'website' => 'https://www.bancobic.ao',
                'phone' => '+244 222 638 900',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Económico',
                'code' => 'BE',
                'swift_code' => 'BECOAOAO',
                'country' => 'AO',
                'website' => 'https://www.be.co.ao',
                'phone' => '+244 222 445 000',
                'is_active' => true,
            ],
            [
                'name' => 'Banco de Poupança e Crédito',
                'code' => 'BPC',
                'swift_code' => 'BPCOAOAO',
                'country' => 'AO',
                'website' => 'https://www.bpc.ao',
                'phone' => '+244 222 693 939',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Millennium Atlântico',
                'code' => 'BMA',
                'swift_code' => 'BMATAOAO',
                'country' => 'AO',
                'website' => 'https://www.millenniumbcp.co.ao',
                'phone' => '+244 222 693 000',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Sol',
                'code' => 'SOL',
                'swift_code' => 'BSOLAOAO',
                'country' => 'AO',
                'website' => 'https://www.bancosol.ao',
                'phone' => '+244 222 638 400',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Keve',
                'code' => 'KEVE',
                'swift_code' => 'KEVDAOAO',
                'country' => 'AO',
                'website' => 'https://www.bancokeve.ao',
                'phone' => '+244 222 010 300',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Caixa Geral Angola',
                'code' => 'BCGA',
                'swift_code' => 'CGDLAOAO',
                'country' => 'AO',
                'website' => 'https://www.cgd.ao',
                'phone' => '+244 222 638 100',
                'is_active' => true,
            ],
            [
                'name' => 'Banco BAI Micro Finanças',
                'code' => 'BMF',
                'swift_code' => 'BMFAAOAO',
                'country' => 'AO',
                'website' => 'https://www.baimicro.ao',
                'phone' => '+244 222 010 400',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Comercial Angolano',
                'code' => 'BCA',
                'swift_code' => 'BCAMAOAO',
                'country' => 'AO',
                'website' => 'https://www.bca.ao',
                'phone' => '+244 222 638 700',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Standard Bank Angola',
                'code' => 'SBA',
                'swift_code' => 'SBICAOAO',
                'country' => 'AO',
                'website' => 'https://www.standardbank.co.ao',
                'phone' => '+244 222 630 200',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Prestígio',
                'code' => 'BP',
                'swift_code' => 'BPSTAOAO',
                'country' => 'AO',
                'website' => 'https://www.bancoprestigio.ao',
                'phone' => '+244 222 010 500',
                'is_active' => true,
            ],
            [
                'name' => 'Banco VTB África',
                'code' => 'VTB',
                'swift_code' => 'VTBAAOAO',
                'country' => 'AO',
                'website' => 'https://www.vtb.co.ao',
                'phone' => '+244 222 010 600',
                'is_active' => true,
            ],
            [
                'name' => 'Banco Yetu',
                'code' => 'YETU',
                'swift_code' => 'YETUAOAO',
                'country' => 'AO',
                'website' => 'https://www.bancoyetu.ao',
                'phone' => '+244 222 010 700',
                'is_active' => true,
            ],
            [
                'name' => 'Finibanco Angola',
                'code' => 'FINI',
                'swift_code' => 'FINBAOAO',
                'country' => 'AO',
                'website' => 'https://www.finibanco.ao',
                'phone' => '+244 222 010 800',
                'is_active' => true,
            ],
        ];

        foreach ($banks as $bank) {
            Bank::updateOrCreate(
                ['code' => $bank['code']],
                $bank
            );
        }
    }
}
