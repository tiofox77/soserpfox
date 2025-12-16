<?php

namespace Database\Seeders\Accounting;

use Illuminate\Database\Seeder;
use App\Models\Accounting\DocumentType;
use App\Models\Accounting\Journal;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = $this->getDocumentTypes();
        
        $imported = 0;
        foreach ($types as $index => $typeData) {
            // Buscar o journal pelo código
            $journal = Journal::where('code', $typeData['journal_code'])->first();

            try {
                DocumentType::create([
                    'code' => $typeData['code'],
                    'description' => $typeData['description'],
                    'journal_code' => $typeData['journal_code'],
                    'journal_id' => $journal?->id,
                    'recapitulativos' => $typeData['recapitulativos'],
                    'retencao_fonte' => $typeData['retencao_fonte'],
                    'bal_financeira' => $typeData['bal_financeira'],
                    'bal_analitica' => $typeData['bal_analitica'],
                    'rec_informacao' => $typeData['rec_informacao'],
                    'tipo_doc_imo' => $typeData['tipo_doc_imo'],
                    'calculo_fluxo_caixa' => $typeData['calculo_fluxo_caixa'],
                    'is_active' => true,
                    'display_order' => $index,
                ]);
                $imported++;
                \Log::info("✅ Tipo de documento importado: {$typeData['code']} - {$typeData['description']}");
            } catch (\Exception $e) {
                \Log::warning("⚠️ Erro ao importar {$typeData['code']}: " . $e->getMessage());
            }
        }

        \Log::info("📊 Importação concluída: $imported tipos de documento importados");
    }

    /**
     * Executar seeder para um tenant específico
     */
    public function runForTenant($tenantId): void
    {
        $types = $this->getDocumentTypes();

        $imported = 0;

        foreach ($types as $index => $typeData) {
            // Buscar journal do tenant
            $journal = Journal::where('tenant_id', $tenantId)
                ->where('code', $typeData['journal_code'])
                ->first();

            // Usar updateOrCreate para evitar duplicação
            DocumentType::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'code' => $typeData['code'],
                ],
                [
                    'description' => $typeData['description'],
                    'journal_code' => $typeData['journal_code'],
                    'journal_id' => $journal?->id,
                    'recapitulativos' => $typeData['recapitulativos'],
                    'retencao_fonte' => $typeData['retencao_fonte'],
                    'bal_financeira' => $typeData['bal_financeira'],
                    'bal_analitica' => $typeData['bal_analitica'],
                    'rec_informacao' => $typeData['rec_informacao'],
                    'tipo_doc_imo' => $typeData['tipo_doc_imo'],
                    'calculo_fluxo_caixa' => $typeData['calculo_fluxo_caixa'],
                    'is_active' => true,
                    'display_order' => $index,
                ]
            );

            $imported++;
        }

        \Log::info("✅ $imported tipos de documento sincronizados para tenant $tenantId");
    }

    /**
     * Retorna array com todos os tipos de documentos
     */
    private function getDocumentTypes(): array
    {
        return [
            ['code' => '101', 'description' => 'Abertura', 'journal_code' => '10', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '211', 'description' => 'Caixa AKZ - Pagamentos', 'journal_code' => '21', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '212', 'description' => 'Caixa AKZ - Recebimentos', 'journal_code' => '21', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '221', 'description' => 'Caixa USD - Pagamentos', 'journal_code' => '22', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '222', 'description' => 'Caixa USD - Recebimentos', 'journal_code' => '22', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '311', 'description' => 'Fatura - n/Factura', 'journal_code' => '31', 'recapitulativos' => true, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '321', 'description' => 'Recibo MN - n/Recibo', 'journal_code' => '32', 'recapitulativos' => true, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '341', 'description' => 'Recibo OM - n/Recibo', 'journal_code' => '34', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '351', 'description' => 'Vendas OM - n/Factura', 'journal_code' => '35', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '361', 'description' => 'Vendas MN - N/C', 'journal_code' => '36', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '371', 'description' => 'Devoluções', 'journal_code' => '37', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '411', 'description' => 'Compras - n/Factura', 'journal_code' => '41', 'recapitulativos' => true, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '421', 'description' => 'Pagamento - n/Recibo', 'journal_code' => '42', 'recapitulativos' => true, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '441', 'description' => 'Pagamento OM - n/Recibo', 'journal_code' => '44', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '451', 'description' => 'Compras OM - n/Factura', 'journal_code' => '45', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '461', 'description' => 'Compras MN - n/N.C.', 'journal_code' => '46', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '471', 'description' => 'Devoluções', 'journal_code' => '47', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '511', 'description' => 'Vendas MN - n/Factura', 'journal_code' => '51', 'recapitulativos' => true, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '521', 'description' => 'Vendas MN - n/N.D.', 'journal_code' => '52', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '531', 'description' => 'Vendas OM - n/N.D.', 'journal_code' => '53', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '541', 'description' => 'Imo. MN - n/Factura', 'journal_code' => '54', 'recapitulativos' => true, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '561', 'description' => 'V.Dinheiro MN - n/V.D.', 'journal_code' => '56', 'recapitulativos' => true, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '581', 'description' => 'V.Dinheiro OM - n/V.D.', 'journal_code' => '58', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '591', 'description' => 'Vendas - Acertos', 'journal_code' => '59', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '611', 'description' => 'Salários - Vencimentos', 'journal_code' => '61', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '612', 'description' => 'Salários - Subs. Férias', 'journal_code' => '61', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '613', 'description' => 'Salários - Subs. Natal', 'journal_code' => '61', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '614', 'description' => 'Salários - Venc. Extr.', 'journal_code' => '61', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '621', 'description' => 'Apuramento do IVA', 'journal_code' => '62', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '631', 'description' => 'Regularizações Mensais', 'journal_code' => '63', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '691', 'description' => 'Reavaliações', 'journal_code' => '69', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '711', 'description' => 'Reg.-Custos Dif.c/Pessoal', 'journal_code' => '71', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '712', 'description' => 'Reg.-Outros Custos Dif.', 'journal_code' => '71', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '713', 'description' => 'Reg.-Amortizações', 'journal_code' => '71', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '714', 'description' => 'Reg.-CVMC', 'journal_code' => '71', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '715', 'description' => 'Outras Regularizações', 'journal_code' => '71', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '721', 'description' => 'Ap. Res. - Operacionais', 'journal_code' => '72', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '722', 'description' => 'Ap. Res. - Financeiros', 'journal_code' => '72', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '723', 'description' => 'Ap. Res. - Correntes', 'journal_code' => '72', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '724', 'description' => 'Ap. Res.- Extraordinários', 'journal_code' => '72', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '725', 'description' => 'Ap. Res. - Antes Impostos', 'journal_code' => '72', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '726', 'description' => 'Apuramento Imposto', 'journal_code' => '72', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '727', 'description' => 'Ap. Res. - Líquidos', 'journal_code' => '72', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
            ['code' => '728', 'description' => 'Ap. Resultados não Operac', 'journal_code' => '72', 'recapitulativos' => false, 'retencao_fonte' => false, 'bal_financeira' => true, 'bal_analitica' => false, 'rec_informacao' => 0, 'tipo_doc_imo' => 0, 'calculo_fluxo_caixa' => 0],
        ];
    }
}
