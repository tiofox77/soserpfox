<?php

namespace Database\Seeders;

use App\Models\AGT\AGTTaxExemptionCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DS.120 v1.1 — Anexos 9.1 (IVA), 9.2 (IS), 9.3 (IEC).
 *
 * Estes códigos são exigidos pelo campo `taxExemptionCode` em qualquer linha
 * de factura com `taxCode = ISE` (isento) ou `taxType = NS` (não sujeito).
 */
class AGTTaxExemptionCodeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach ($this->iva() as $row) {
                AGTTaxExemptionCode::updateOrCreate(
                    ['code' => $row['code']],
                    $row + ['tax_type' => 'IVA', 'is_active' => true]
                );
            }
            foreach ($this->is() as $row) {
                AGTTaxExemptionCode::updateOrCreate(
                    ['code' => $row['code']],
                    $row + ['tax_type' => 'IS', 'is_active' => true]
                );
            }
            foreach ($this->iec() as $row) {
                AGTTaxExemptionCode::updateOrCreate(
                    ['code' => $row['code']],
                    $row + ['tax_type' => 'IEC', 'is_active' => true]
                );
            }
        });
    }

    /**
     * Anexo 9.1 — Códigos de isenção/não sujeição de IVA.
     * Fonte: DS.120 v1.1 (Nov 2025), Min. Finanças.
     */
    private function iva(): array
    {
        return [
            ['code' => 'M01', 'description' => 'Artigo 12.º do CIVA — Isenções nas operações internas'],
            ['code' => 'M02', 'description' => 'Artigo 14.º do CIVA — Isenções nas importações'],
            ['code' => 'M04', 'description' => 'Artigo 15.º do CIVA — Isenções nas exportações'],
            ['code' => 'M05', 'description' => 'Artigo 16.º do CIVA — Isenções em operações assimiladas a exportações'],
            ['code' => 'M06', 'description' => 'Artigo 17.º do CIVA — Isenções em operações de transporte internacional'],
            ['code' => 'M07', 'description' => 'Artigo 18.º do CIVA — Isenções em transmissões para zonas francas'],
            ['code' => 'M08', 'description' => 'Artigo 19.º do CIVA — Isenções nas operações da indústria petrolífera'],
            ['code' => 'M09', 'description' => 'Artigo 51.º do CIVA — Regime de não sujeição'],
            ['code' => 'M10', 'description' => 'Regime de isenção (Art. 53.º CIVA)'],
            ['code' => 'M11', 'description' => 'Regime particular do tabaco (Decreto-Lei n.º 346/85)'],
            ['code' => 'M12', 'description' => 'Regime da margem de lucro — Agências de viagens'],
            ['code' => 'M13', 'description' => 'Regime da margem de lucro — Bens em segunda mão'],
            ['code' => 'M14', 'description' => 'Regime da margem de lucro — Objectos de arte'],
            ['code' => 'M15', 'description' => 'Regime da margem de lucro — Objectos de colecção e antiguidades'],
            ['code' => 'M16', 'description' => 'Isenção (Art. 14.º RITI — Regime IVA nas Transações Intracomunitárias)'],
            ['code' => 'M19', 'description' => 'Outras isenções (regimes especiais)'],
            ['code' => 'M20', 'description' => 'IVA - Regime forfetário'],
            ['code' => 'M21', 'description' => 'IVA – Não confere direito à dedução (Art. 54.º n.º 3 CIVA)'],
            ['code' => 'M25', 'description' => 'Mercadorias à consignação (Art. 38.º n.º 1 al. a) CIVA)'],
            ['code' => 'M26', 'description' => 'Isenção de IVA com direito à dedução no cabaz alimentar'],
            ['code' => 'M30', 'description' => 'IVA - Autoliquidação (Art. 2.º n.º 1 al. i) CIVA — Sucatas)'],
            ['code' => 'M31', 'description' => 'IVA - Autoliquidação (Art. 2.º n.º 1 al. j) CIVA — Serviços de construção civil)'],
            ['code' => 'M32', 'description' => 'IVA - Autoliquidação (Art. 2.º n.º 1 al. l) CIVA — Emissão de CO2)'],
            ['code' => 'M33', 'description' => 'IVA - Autoliquidação (Art. 2.º n.º 1 al. m) CIVA — Cortiça e outros produtos)'],
            ['code' => 'M34', 'description' => 'IVA - Autoliquidação (Art. 2.º n.º 1 al. n) CIVA — Electricidade/gás)'],
            ['code' => 'M40', 'description' => 'Isenção ao abrigo de Acordo Internacional'],
            ['code' => 'M41', 'description' => 'Isenção de IVA — Diplomatas (reciprocidade)'],
            ['code' => 'M42', 'description' => 'Isenção de IVA — Forças Armadas estrangeiras'],
            ['code' => 'M50', 'description' => 'Isenção temporária (regimes excepcionais)'],
            ['code' => 'M60', 'description' => 'Operações fora do campo do imposto (não sujeição)'],
            ['code' => 'M70', 'description' => 'Operações isentas — Serviços financeiros'],
            ['code' => 'M71', 'description' => 'Operações isentas — Seguros e resseguros'],
            ['code' => 'M72', 'description' => 'Operações isentas — Locação de imóveis para habitação'],
            ['code' => 'M73', 'description' => 'Operações isentas — Saúde'],
            ['code' => 'M74', 'description' => 'Operações isentas — Educação'],
            ['code' => 'M75', 'description' => 'Operações isentas — Cultura e desporto'],
            ['code' => 'M76', 'description' => 'Operações isentas — Serviços postais públicos'],
            ['code' => 'M80', 'description' => 'Outras isenções (especificar em taxExemptionReason)'],
            ['code' => 'M90', 'description' => 'IVA — Não sujeição por falta de incidência objectiva'],
            ['code' => 'M91', 'description' => 'IVA — Não sujeição por falta de incidência subjectiva'],
            ['code' => 'M92', 'description' => 'IVA — Não sujeição por falta de incidência territorial'],
            ['code' => 'M93', 'description' => 'IVA — Outras situações de não sujeição'],
        ];
    }

    /**
     * Anexo 9.2 — Códigos de isenção de Imposto de Selo (IS).
     */
    private function is(): array
    {
        return [
            ['code' => 'S01', 'description' => 'Isenção subjectiva (Art. 6.º CIS — Estado, autarquias, IPSS)'],
            ['code' => 'S02', 'description' => 'Isenção objectiva (operações isentas por natureza)'],
            ['code' => 'S03', 'description' => 'Isenção em operações financeiras (Art. 7.º CIS)'],
        ];
    }

    /**
     * Anexo 9.3 — Códigos de isenção de Imposto Especial sobre Consumo (IEC).
     */
    private function iec(): array
    {
        return [
            ['code' => 'I01', 'description' => 'Isenção — Exportação de produtos sujeitos a IEC'],
            ['code' => 'I02', 'description' => 'Isenção — Reservas estratégicas do Estado'],
            ['code' => 'I03', 'description' => 'Isenção — Combustíveis para aviação internacional'],
            ['code' => 'I04', 'description' => 'Isenção — Combustíveis para navegação marítima internacional'],
            ['code' => 'I05', 'description' => 'Isenção — Forças Armadas e segurança pública'],
            ['code' => 'I06', 'description' => 'Isenção — Diplomatas (reciprocidade)'],
            ['code' => 'I07', 'description' => 'Isenção — Bens destinados a fins humanitários'],
            ['code' => 'I08', 'description' => 'Isenção — Amostras sem valor comercial'],
            ['code' => 'I09', 'description' => 'Isenção — Bens em regime de admissão temporária'],
            ['code' => 'I10', 'description' => 'Isenção — Bens em regime de trânsito'],
            ['code' => 'I11', 'description' => 'Isenção — Bens em entreposto aduaneiro'],
            ['code' => 'I12', 'description' => 'Isenção — Mostruários e materiais publicitários'],
            ['code' => 'I13', 'description' => 'Isenção — Produtos destruídos sob controlo aduaneiro'],
            ['code' => 'I14', 'description' => 'Isenção — Bens reexportados'],
            ['code' => 'I15', 'description' => 'Isenção — Investigação científica e desenvolvimento'],
            ['code' => 'I16', 'description' => 'Outras isenções (especificar em taxExemptionReason)'],
        ];
    }
}
