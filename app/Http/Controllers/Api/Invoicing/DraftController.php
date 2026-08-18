<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\SalesProformaItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Recebe rascunhos criados offline no PWA e persiste no servidor com status='draft'.
 * Suporta 4 tipos: FT (Fatura), FR (Fatura-Recibo), NC (Nota de Crédito), proforma.
 * NOTA: documentos criados ficam SEM hash AGT — utilizador finaliza manualmente
 *       no módulo principal para obter validade fiscal.
 */
class DraftController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $tenantId = activeTenantId();
        if (!$tenantId) {
            return response()->json(['error' => 'No active tenant'], 403);
        }

        $validator = Validator::make($request->all(), [
            // SO documentos de VENDA. A nota de credito estava aqui e nao
            // podia: o createInvoice() escreve-a na tabela das VENDAS com
            // invoice_type=NC, e o que nasce e uma factura — nao estorna
            // nada, nao aponta para o documento original, e conta como
            // receita nos totais. As notas de credito a serio vivem em
            // invoicing_credit_notes, com serie e numeracao proprias.
            'doc_type' => 'required|in:FT,FR,proforma',
            'client_id' => 'nullable|integer',
            'client_local_uuid' => 'nullable|string',
            'notes' => 'nullable|string|max:2000',
            'reference' => 'nullable|string|max:255', // ex: nº FT original para NC
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            // IEC e Imposto de Selo: o dispositivo manda a ESCOLHA (o código
            // pautal, a verba) e nunca o valor. Quem apura é o servidor, pelo
            // ImpostosDaLinha — se o aparelho calculasse, o documento ficava
            // com dois apuramentos do mesmo imposto e a AGT recusa.
            'items.*.iec_pautal' => 'nullable|string|max:40',
            'items.*.is_verba'   => 'nullable|string|max:40',
            // Descontos do DOCUMENTO. O comercial incide antes do IVA e o
            // financeiro depois — a ordem nao e detalhe, muda o imposto.
            'discount_commercial' => 'nullable|numeric|min:0',
            'discount_financial'  => 'nullable|numeric|min:0',
            'delivery_date'       => 'nullable|date',
            'delivery_location'   => 'nullable|string|max:255',
            // Retenção na fonte. É a prestação de serviço que a justifica —
            // uma venda de mercadoria não retém IRT.
            'is_service'            => 'nullable|boolean',
            'withholding_percentage' => 'nullable|numeric|min:0|max:100',
            'local_uuid' => 'nullable|string|max:80',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $docType = $data['doc_type'];

        return DB::transaction(function () use ($data, $tenantId, $docType) {
            if ($docType === 'proforma') {
                return $this->createProforma($data, $tenantId);
            }
            return $this->createInvoice($data, $tenantId, $docType);
        });
    }

    private function createInvoice(array $data, int $tenantId, string $docType): JsonResponse
    {
        $invoice = new SalesInvoice();
        $invoice->tenant_id = $tenantId;
        $invoice->invoice_type = $docType; // FT, FR, NC
        // O cliente vem do MESMO resolvedor do POS.
        //
        // Aqui escrevia-se `$data['client_id'] ?? null` numa coluna que é NOT
        // NULL: um rascunho criado offline sem cliente — e no balcão a maioria
        // é sem cliente — rebentava contra a base de dados a cada tentativa de
        // sincronização, cinco vezes, e ficava marcado como erro permanente.
        // O documento nunca chegava ao sistema.
        $clienteResolvido = app(\App\Services\POS\PosSaleService::class)
            ->resolveClient($data, $tenantId);

        $invoice->client_id = $clienteResolvido->id;
        $invoice->invoice_date = $data['invoice_date'] ?? now()->toDateString();
        $invoice->due_date = $data['due_date'] ?? now()->addDays(30)->toDateString();
        // EMITIDO, NÃO RASCUNHO.
        //
        // Isto gravava status='draft' e alguém tinha de ir ao servidor carregar
        // em "Finalizar" para o documento ganhar número fiscal. Um documento
        // que o balcão deu por feito e que ficava à espera de um segundo passo
        // que ninguém via — e sem número, sem hash e sem comunicação.
        //
        // A numeração e o selo vêm do EmissorFiscal, o mesmo que o POS usa: a
        // série é a de emissão deste tipo de documento e o número sai dela.
        $tipoDeSerie = $docType === 'FR' ? 'pos' : 'invoice';
        $numeracao = app(\App\Services\Invoicing\EmissorFiscal::class)->numerar($tenantId, $tipoDeSerie);

        $invoice->series_id = $numeracao['serie']->id;
        $invoice->invoice_number = $numeracao['numero'];

        // Uma Fatura-Recibo é paga no acto; uma Fatura fica a aguardar.
        $invoice->status = $docType === 'FR' ? 'paid' : 'pending';
        $invoice->invoice_status = 'F';
        $invoice->invoice_status_date = now();
        $invoice->source_id = auth()->id() ?? 'PWA';
        $invoice->source_billing = 'P';
        // created_by é NOT NULL sem default: sem isto, TODA a sincronização de
        // rascunhos do PWA rebentava com "Field 'created_by' doesn't have a
        // default value" e o dispositivo ficava a retentar para sempre.
        $invoice->created_by = auth()->id();
        $invoice->system_entry_date = now();
        $invoice->notes = ($data['notes'] ?? '')
            . (isset($data['reference']) ? "\n\nReferência: " . $data['reference'] : '')
            . "\n\n[Criado via PWA Offline]";

        $totals = $this->calculateTotals(
            $data['items'],
            (float) ($data['discount_commercial'] ?? 0),
            (float) ($data['discount_financial'] ?? 0),
            (bool) ($data['is_service'] ?? false),
            $data['withholding_percentage'] ?? null
        );
        $invoice->subtotal = $totals['subtotal'];
        $invoice->net_total = $totals['subtotal'];
        $invoice->tax_amount = $totals['tax'];
        $invoice->tax_payable = $totals['tax'];
        $invoice->total = $totals['total'];
        $invoice->gross_total = $totals['total'];
        $invoice->discount_commercial = $totals['comercial'] ?? 0;
        $invoice->discount_financial = $totals['financeiro'] ?? 0;
        $invoice->discount_amount = ($totals['comercial'] ?? 0) + ($totals['financeiro'] ?? 0);
        $invoice->delivery_date = $data['delivery_date'] ?? null;
        $invoice->delivery_location = $data['delivery_location'] ?? null;
        $invoice->is_service = (bool) ($data['is_service'] ?? false);
        $invoice->irt_amount = $totals['retencao'] ?? 0;

        // O identificador local GRAVA-SE.
        //
        // Vinha na validação e era devolvido na resposta, mas nunca chegava à
        // coluna — portanto os rascunhos não tinham desduplicação nenhuma. Com
        // a rede a oscilar, o PWA reenvia o que não teve resposta e criava um
        // segundo rascunho da mesma factura; a pessoa finalizava os dois e
        // saíam dois documentos fiscais para a mesma venda.
        //
        // Antes de gravar, se já cá está, devolve-se o que existe.
        if (!empty($data['local_uuid'])) {
            $jaExiste = SalesInvoice::where('tenant_id', $tenantId)
                ->where('local_uuid', $data['local_uuid'])
                ->first();

            if ($jaExiste) {
                return response()->json([
                    'id'         => $jaExiste->id,
                    'local_uuid' => $data['local_uuid'],
                    'doc_type'   => $docType,
                    'status'     => $jaExiste->status,
                    'duplicated' => true,
                    'message'    => 'Rascunho já existente.',
                ]);
            }

            $invoice->local_uuid = $data['local_uuid'];
        }

        $invoice->save();

        $totalExtras = 0.0;

        foreach ($data['items'] as $idx => $itm) {
            $qty = (float) $itm['quantity'];
            $unitPrice = (float) $itm['unit_price'];
            // O SERVIDOR é a autoridade fiscal: nunca aceitar a taxa do
            // dispositivo quando o produto é conhecido. Um tablet com o catálogo
            // desactualizado enviava 14% e gerava faturas com IVA numa empresa
            // isenta. Sem product_id (item avulso) usa-se o valor recebido,
            // limitado à taxa do regime.
            $__tx = \App\Services\Invoicing\TaxResolver::forProductId($itm['product_id'] ?? null);
            $taxRate = !empty($itm['product_id'])
                ? (float) $__tx['rate']
                : min((float) ($itm['tax_rate'] ?? $__tx['rate']), (float) $__tx['rate']);
            $discountPercent = (float) ($itm['discount_percent'] ?? 0);

            $lineGross = $qty * $unitPrice;
            $discount = $lineGross * $discountPercent / 100;
            $lineNet = $lineGross - $discount;

            // IEC e Selo, apurados AQUI e nunca no dispositivo.
            $extras = \App\Services\Invoicing\ImpostosDaLinha::calcular(
                $lineNet,
                $itm['iec_pautal'] ?? null,
                $itm['is_verba'] ?? null
            );

            $iec = collect($extras)->firstWhere('tax_type', 'IEC')['tax_amount'] ?? 0;

            // O IVA incide sobre o líquido ACRESCIDO do IEC. O Imposto de Selo
            // NÃO entra nesta base. Sem isto, netTotal + taxPayable ≠ grossTotal
            // e a AGT recusa com "taxContribution não corresponde ao imposto
            // apurado".
            $taxAmount = ($lineNet + $iec) * $taxRate / 100;

            $line = new SalesInvoiceItem();
            $line->sales_invoice_id = $invoice->id;
            $line->product_id = $itm['product_id'] ?? null;
            $line->product_name = $itm['product_name'];
            $line->quantity = $qty;
            $line->unit_price = $unitPrice;
            $line->unit_price_base = $unitPrice;
            $line->discount_percent = $discountPercent;
            $line->discount_amount = $discount;
            $line->subtotal = $lineNet;
            $line->tax_rate = $taxRate;
            $line->tax_amount = $taxAmount;
            $line->total = $lineNet + $taxAmount;
            $line->order = $idx + 1;
            // Campos AGT: linha sem imposto TEM de levar motivo de isenção
            $line->tax_country_region = 'AO';
            $line->tax_code = $taxRate > 0 ? 'NOR' : 'ISE';
            if ($taxRate <= 0) {
                $tx = \App\Services\Invoicing\TaxResolver::forProductId($itm['product_id'] ?? null);
                $line->tax_exemption_code   = $tx['exemption_code'];
                $line->tax_exemption_reason = $tx['exemption_reason'];
            }
            $line->save();

            // Os impostos extra so DEPOIS de a linha existir: eles apontam
            // para ela numa tabela a parte, e sem id nao ha para onde apontar.
            $totalExtras += \App\Services\Invoicing\ImpostosDaLinha::gravar($line, $extras);

            // Regravar a linha depois de existirem as linhas de imposto.
            // O calculateTotals() do model corre em cada save e so consegue
            // ler o IEC quando a linha ja existe ($this->exists). No INSERT
            // acima ele leu IEC = 0 e sobrepos o $taxAmount correcto pelo
            // valor calculado so sobre o liquido. O cabecalho ficava certo e
            // a LINHA errada — e e da linha que o DocumentMapper recompoe o
            // payload da AGT. O ecra web ja se protegia assim.
            if ($iec > 0) {
                $line->save();
            }
        }

        // O selo só DEPOIS das linhas: o hash encadeia o documento inteiro,
        // e um documento sem linhas hasheia-se a si próprio vazio.
        app(\App\Services\Invoicing\EmissorFiscal::class)->selar($invoice, $tenantId);

        $invoice->refresh();

        return response()->json([
            'id' => $invoice->id,
            'doc_type' => $docType,
            'local_uuid' => $data['local_uuid'] ?? null,
            'invoice_number' => $invoice->invoice_number,
            'atcud' => $invoice->atcud,
            'total' => (float) $invoice->total,
            'status' => $invoice->status,
            'message' => 'Documento emitido: ' . $invoice->invoice_number,
        ], 201);
    }

    private function createProforma(array $data, int $tenantId): JsonResponse
    {
        $proforma = new SalesProforma();
        $proforma->tenant_id = $tenantId;
        // Ver a nota na factura: a mesma coluna, a mesma regra.
        $clienteResolvido = app(\App\Services\POS\PosSaleService::class)
            ->resolveClient($data, $tenantId);

        $proforma->client_id = $clienteResolvido->id;
        $proforma->proforma_date = $data['invoice_date'] ?? now()->toDateString();
        $proforma->valid_until = $data['due_date'] ?? now()->addDays(15)->toDateString();
        $proforma->status = 'draft';
        $proforma->created_by = auth()->id();   // NOT NULL sem default
        $proforma->notes = ($data['notes'] ?? '') . "\n\n[Criado via PWA Offline]";

        // NOTA: invoicing_sales_proformas NÃO tem net_total/tax_payable/gross_total.
        $totals = $this->calculateTotals(
            $data['items'],
            (float) ($data['discount_commercial'] ?? 0),
            (float) ($data['discount_financial'] ?? 0),
            (bool) ($data['is_service'] ?? false),
            $data['withholding_percentage'] ?? null
        );
        $proforma->subtotal = $totals['subtotal'];
        $proforma->tax_amount = $totals['tax'];
        $proforma->total = $totals['total'];
        $proforma->save();

        $totalExtras = 0.0;

        foreach ($data['items'] as $idx => $itm) {
            $qty = (float) $itm['quantity'];
            $unitPrice = (float) $itm['unit_price'];
            // O SERVIDOR é a autoridade fiscal: nunca aceitar a taxa do
            // dispositivo quando o produto é conhecido. Um tablet com o catálogo
            // desactualizado enviava 14% e gerava faturas com IVA numa empresa
            // isenta. Sem product_id (item avulso) usa-se o valor recebido,
            // limitado à taxa do regime.
            $__tx = \App\Services\Invoicing\TaxResolver::forProductId($itm['product_id'] ?? null);
            $taxRate = !empty($itm['product_id'])
                ? (float) $__tx['rate']
                : min((float) ($itm['tax_rate'] ?? $__tx['rate']), (float) $__tx['rate']);
            $discountPercent = (float) ($itm['discount_percent'] ?? 0);

            $lineGross = $qty * $unitPrice;
            $discount = $lineGross * $discountPercent / 100;
            $lineNet = $lineGross - $discount;

            // IEC e Selo, apurados AQUI e nunca no dispositivo.
            $extras = \App\Services\Invoicing\ImpostosDaLinha::calcular(
                $lineNet,
                $itm['iec_pautal'] ?? null,
                $itm['is_verba'] ?? null
            );

            $iec = collect($extras)->firstWhere('tax_type', 'IEC')['tax_amount'] ?? 0;

            // O IVA incide sobre o líquido ACRESCIDO do IEC. O Imposto de Selo
            // NÃO entra nesta base. Sem isto, netTotal + taxPayable ≠ grossTotal
            // e a AGT recusa com "taxContribution não corresponde ao imposto
            // apurado".
            $taxAmount = ($lineNet + $iec) * $taxRate / 100;

            $line = new SalesProformaItem();
            $line->sales_proforma_id = $proforma->id;
            $line->product_id = $itm['product_id'] ?? null;
            $line->product_name = $itm['product_name'];
            $line->quantity = $qty;
            $line->unit_price = $unitPrice;
            $line->discount_percent = $discountPercent;
            $line->discount_amount = $discount;
            $line->subtotal = $lineNet;
            $line->tax_rate = $taxRate;
            $line->tax_amount = $taxAmount;
            $line->total = $lineNet + $taxAmount;
            $line->order = $idx + 1;
            // Campos AGT — a proforma tem de os levar para a conversão em fatura
            $line->tax_country_region = 'AO';
            $line->tax_code = $taxRate > 0 ? 'NOR' : 'ISE';
            if ($taxRate <= 0) {
                $line->tax_exemption_code   = $__tx['exemption_code'];
                $line->tax_exemption_reason = $__tx['exemption_reason'];
            }
            $line->save();

            // Os impostos extra so DEPOIS de a linha existir: eles apontam
            // para ela numa tabela a parte, e sem id nao ha para onde apontar.
            $totalExtras += \App\Services\Invoicing\ImpostosDaLinha::gravar($line, $extras);

            // Regravar a linha depois de existirem as linhas de imposto.
            // O calculateTotals() do model corre em cada save e so consegue
            // ler o IEC quando a linha ja existe ($this->exists). No INSERT
            // acima ele leu IEC = 0 e sobrepos o $taxAmount correcto pelo
            // valor calculado so sobre o liquido. O cabecalho ficava certo e
            // a LINHA errada — e e da linha que o DocumentMapper recompoe o
            // payload da AGT. O ecra web ja se protegia assim.
            if ($iec > 0) {
                $line->save();
            }
        }

        return response()->json([
            'id' => $proforma->id,
            'doc_type' => 'proforma',
            'local_uuid' => $data['local_uuid'] ?? null,
            'proforma_number' => $proforma->proforma_number,
            'total' => (float) $proforma->total,
            'status' => 'draft',
            'message' => 'Proforma criada como rascunho.',
        ], 201);
    }

    /**
     * Os totais do documento.
     *
     * A ORDEM DOS DESCONTOS NÃO É DETALHE: o comercial incide ANTES do IVA e
     * baixa o imposto; o financeiro incide DEPOIS e não lhe toca. Trocá-los dá
     * um imposto diferente no mesmo documento — e é o imposto que vai para a
     * AGT.
     */
    private function calculateTotals(
        array $items,
        float $descontoComercial = 0,
        float $descontoFinanceiro = 0,
        bool $prestacaoDeServico = false,
        $percentagemRetencao = null
    ): array {
        $subtotal = 0;
        $tax = 0;
        $extrasTotal = 0;
        foreach ($items as $itm) {
            $qty = (float) $itm['quantity'];
            $unitPrice = (float) $itm['unit_price'];
            // O SERVIDOR é a autoridade fiscal: nunca aceitar a taxa do
            // dispositivo quando o produto é conhecido. Um tablet com o catálogo
            // desactualizado enviava 14% e gerava faturas com IVA numa empresa
            // isenta. Sem product_id (item avulso) usa-se o valor recebido,
            // limitado à taxa do regime.
            $__tx = \App\Services\Invoicing\TaxResolver::forProductId($itm['product_id'] ?? null);
            $taxRate = !empty($itm['product_id'])
                ? (float) $__tx['rate']
                : min((float) ($itm['tax_rate'] ?? $__tx['rate']), (float) $__tx['rate']);
            $discountPercent = (float) ($itm['discount_percent'] ?? 0);

            $lineGross = $qty * $unitPrice;
            $discount = $lineGross * $discountPercent / 100;
            $lineNet = $lineGross - $discount;

            // IEC e Selo, apurados AQUI e nunca no dispositivo.
            $extras = \App\Services\Invoicing\ImpostosDaLinha::calcular(
                $lineNet,
                $itm['iec_pautal'] ?? null,
                $itm['is_verba'] ?? null
            );

            $iec = collect($extras)->firstWhere('tax_type', 'IEC')['tax_amount'] ?? 0;

            // O IVA incide sobre o líquido ACRESCIDO do IEC. O Imposto de Selo
            // NÃO entra nesta base. Sem isto, netTotal + taxPayable ≠ grossTotal
            // e a AGT recusa com "taxContribution não corresponde ao imposto
            // apurado".
            $taxAmount = ($lineNet + $iec) * $taxRate / 100;

            $subtotal += $lineNet;
            $tax += $taxAmount;
            $extrasTotal += array_sum(array_column($extras, 'tax_amount'));
        }
        // Comercial: sai do líquido, e o imposto recalcula-se sobre o que
        // sobra. Aplicá-lo depois do IVA daria imposto sobre dinheiro que
        // o cliente não chegou a pagar.
        $comercial = min($descontoComercial, $subtotal);
        $liquido = $subtotal - $comercial;
        $imposto = $subtotal > 0 ? $tax * ($liquido / $subtotal) : 0;

        // Financeiro: desconto de pronto pagamento, já sobre o total com
        // imposto. Não mexe no que se entrega à AGT.
        // O IEC e o Selo ACRESCEM ao total: nao sao IVA, mas sao imposto a
        // cobrar ao cliente. Ficam de fora do desconto financeiro, que e um
        // acerto comercial e nao um alivio fiscal.
        // RETENÇÃO NA FONTE.
        //
        // Só existe em prestação de serviços — uma venda de mercadoria não
        // retém IRT. A percentagem indicada manda; sem ela, 6,5%, que é a
        // taxa corrente do IRT sobre serviços.
        //
        // A retenção NÃO é imposto do documento: é dinheiro que o cliente
        // entrega ao Estado em vez de o entregar a quem factura. Por isso
        // BAIXA o total a receber e não entra no imposto que vai à AGT.
        $pct = $percentagemRetencao !== null && $percentagemRetencao !== ''
            ? (float) $percentagemRetencao
            : 6.5;

        $retencao = $prestacaoDeServico ? round($liquido * $pct / 100, 2) : 0.0;

        $total = max(0, $liquido + $imposto + $extrasTotal - $descontoFinanceiro - $retencao);

        return [
            'subtotal'   => round($liquido, 2),
            'tax'        => round($imposto, 2),
            'total'      => round($total, 2),
            'comercial'  => round($comercial, 2),
            'financeiro' => round(min($descontoFinanceiro, $liquido + $imposto), 2),
            'extras'     => round($extrasTotal, 2),
            'retencao'   => $retencao,
        ];
    }
}
