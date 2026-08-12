<?php

namespace Database\Seeders;

use App\Models\AGT\AGTCaeCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DS.120 v1.1 — Anexo 9.5: CAE (Classificação das Actividades Económicas).
 *
 * Fonte: INE Angola — CAE Rev. 2 (lista oficial). Este seeder inclui:
 *   - Todas as 21 SECÇÕES (A..U)
 *   - Divisões mais comuns para PME angolanas
 *
 * A lista completa (~700 códigos) pode ser importada via CSV em
 * `database/seeders/data/cae_full.csv` quando disponibilizada pelo INE.
 */
class AGTCaeCodeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Secções (1ª letra)
            foreach ($this->sections() as $row) {
                AGTCaeCode::updateOrCreate(
                    ['code' => $row['code']],
                    $row + ['level' => 'section', 'is_active' => true]
                );
            }
            // Divisões e classes principais
            foreach ($this->divisions() as $row) {
                AGTCaeCode::updateOrCreate(
                    ['code' => $row['code']],
                    $row + ['is_active' => true]
                );
            }
        });
    }

    /** As 21 Secções da CAE Rev. 2. */
    private function sections(): array
    {
        return [
            ['code' => 'A', 'parent_code' => null, 'description' => 'Agricultura, produção animal, caça, floresta e pesca'],
            ['code' => 'B', 'parent_code' => null, 'description' => 'Indústrias extractivas'],
            ['code' => 'C', 'parent_code' => null, 'description' => 'Indústrias transformadoras'],
            ['code' => 'D', 'parent_code' => null, 'description' => 'Electricidade, gás, vapor, água quente e fria e ar frio'],
            ['code' => 'E', 'parent_code' => null, 'description' => 'Captação, tratamento e distribuição de água; saneamento, gestão de resíduos e despoluição'],
            ['code' => 'F', 'parent_code' => null, 'description' => 'Construção'],
            ['code' => 'G', 'parent_code' => null, 'description' => 'Comércio por grosso e a retalho; reparação de veículos automóveis e motociclos'],
            ['code' => 'H', 'parent_code' => null, 'description' => 'Transportes e armazenagem'],
            ['code' => 'I', 'parent_code' => null, 'description' => 'Alojamento, restauração e similares'],
            ['code' => 'J', 'parent_code' => null, 'description' => 'Actividades de informação e comunicação'],
            ['code' => 'K', 'parent_code' => null, 'description' => 'Actividades financeiras e de seguros'],
            ['code' => 'L', 'parent_code' => null, 'description' => 'Actividades imobiliárias'],
            ['code' => 'M', 'parent_code' => null, 'description' => 'Actividades de consultoria, científicas, técnicas e similares'],
            ['code' => 'N', 'parent_code' => null, 'description' => 'Actividades administrativas e dos serviços de apoio'],
            ['code' => 'O', 'parent_code' => null, 'description' => 'Administração pública e defesa; segurança social obrigatória'],
            ['code' => 'P', 'parent_code' => null, 'description' => 'Educação'],
            ['code' => 'Q', 'parent_code' => null, 'description' => 'Actividades de saúde humana e apoio social'],
            ['code' => 'R', 'parent_code' => null, 'description' => 'Actividades artísticas, de espectáculos, desportivas e recreativas'],
            ['code' => 'S', 'parent_code' => null, 'description' => 'Outras actividades de serviços'],
            ['code' => 'T', 'parent_code' => null, 'description' => 'Actividades das famílias empregadoras de pessoal doméstico'],
            ['code' => 'U', 'parent_code' => null, 'description' => 'Actividades dos organismos internacionais e outras instituições extra-territoriais'],
        ];
    }

    /** Divisões e classes mais comuns. */
    private function divisions(): array
    {
        return [
            // C — Indústrias transformadoras
            ['code' => '10', 'level' => 'division', 'parent_code' => 'C', 'description' => 'Indústrias alimentares'],
            ['code' => '11', 'level' => 'division', 'parent_code' => 'C', 'description' => 'Indústria das bebidas'],
            ['code' => '14', 'level' => 'division', 'parent_code' => 'C', 'description' => 'Indústria do vestuário'],
            ['code' => '15', 'level' => 'division', 'parent_code' => 'C', 'description' => 'Indústria do couro e dos produtos do couro'],
            ['code' => '16', 'level' => 'division', 'parent_code' => 'C', 'description' => 'Indústrias da madeira e da cortiça'],
            ['code' => '25', 'level' => 'division', 'parent_code' => 'C', 'description' => 'Fabricação de produtos metálicos, excepto máquinas e equipamentos'],

            // F — Construção
            ['code' => '41', 'level' => 'division', 'parent_code' => 'F', 'description' => 'Promoção imobiliária; construção de edifícios'],
            ['code' => '42', 'level' => 'division', 'parent_code' => 'F', 'description' => 'Engenharia civil'],
            ['code' => '43', 'level' => 'division', 'parent_code' => 'F', 'description' => 'Actividades especializadas de construção'],

            // G — Comércio
            ['code' => '45', 'level' => 'division', 'parent_code' => 'G', 'description' => 'Comércio, manutenção e reparação de veículos automóveis e motociclos'],
            ['code' => '46', 'level' => 'division', 'parent_code' => 'G', 'description' => 'Comércio por grosso (excepto de veículos automóveis e motociclos)'],
            ['code' => '47', 'level' => 'division', 'parent_code' => 'G', 'description' => 'Comércio a retalho (excepto de veículos automóveis e motociclos)'],
            ['code' => '47190', 'level' => 'class',  'parent_code' => '47', 'description' => 'Comércio a retalho em outros estabelecimentos não especializados'],
            ['code' => '47210', 'level' => 'class',  'parent_code' => '47', 'description' => 'Comércio a retalho de frutas e produtos hortícolas'],
            ['code' => '47711', 'level' => 'class',  'parent_code' => '47', 'description' => 'Comércio a retalho de vestuário para adultos'],

            // H — Transportes
            ['code' => '49', 'level' => 'division', 'parent_code' => 'H', 'description' => 'Transportes terrestres e transportes por oleodutos ou gasodutos'],
            ['code' => '52', 'level' => 'division', 'parent_code' => 'H', 'description' => 'Armazenagem e actividades auxiliares dos transportes'],

            // I — Alojamento e restauração
            ['code' => '55', 'level' => 'division', 'parent_code' => 'I', 'description' => 'Alojamento'],
            ['code' => '56', 'level' => 'division', 'parent_code' => 'I', 'description' => 'Restauração e similares'],
            ['code' => '56101', 'level' => 'class', 'parent_code' => '56', 'description' => 'Restaurantes tipo tradicional'],

            // J — Informação e comunicação
            ['code' => '62', 'level' => 'division', 'parent_code' => 'J', 'description' => 'Consultoria e programação informática e actividades relacionadas'],
            ['code' => '62010', 'level' => 'class', 'parent_code' => '62', 'description' => 'Actividades de programação informática'],
            ['code' => '62020', 'level' => 'class', 'parent_code' => '62', 'description' => 'Actividades de consultoria em informática'],
            ['code' => '63', 'level' => 'division', 'parent_code' => 'J', 'description' => 'Actividades dos serviços de informação'],

            // K — Financeiras
            ['code' => '64', 'level' => 'division', 'parent_code' => 'K', 'description' => 'Actividades de serviços financeiros, excepto seguros e fundos de pensões'],
            ['code' => '65', 'level' => 'division', 'parent_code' => 'K', 'description' => 'Seguros, resseguros e fundos de pensões'],

            // L — Imobiliárias
            ['code' => '68', 'level' => 'division', 'parent_code' => 'L', 'description' => 'Actividades imobiliárias'],

            // M — Consultoria
            ['code' => '69', 'level' => 'division', 'parent_code' => 'M', 'description' => 'Actividades jurídicas e de contabilidade'],
            ['code' => '70', 'level' => 'division', 'parent_code' => 'M', 'description' => 'Actividades das sedes sociais e de consultoria para a gestão'],
            ['code' => '71', 'level' => 'division', 'parent_code' => 'M', 'description' => 'Actividades de arquitectura, de engenharia e técnicas afins'],

            // P — Educação
            ['code' => '85', 'level' => 'division', 'parent_code' => 'P', 'description' => 'Educação'],

            // Q — Saúde
            ['code' => '86', 'level' => 'division', 'parent_code' => 'Q', 'description' => 'Actividades de saúde humana'],

            // S — Outros serviços
            ['code' => '95', 'level' => 'division', 'parent_code' => 'S', 'description' => 'Reparação de computadores e de bens de uso pessoal e doméstico'],
            ['code' => '96', 'level' => 'division', 'parent_code' => 'S', 'description' => 'Outras actividades de serviços pessoais'],
            ['code' => '96021', 'level' => 'class', 'parent_code' => '96', 'description' => 'Salões de cabeleireiro'],
            ['code' => '96022', 'level' => 'class', 'parent_code' => '96', 'description' => 'Institutos de beleza'],
        ];
    }
}
