<?php

namespace App\Services\AGT;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use Illuminate\Database\Eloquent\Model;

/**
 * AGT v1.2 — Mapper Eloquent → array de payload AGT.
 *
 * Aceita SalesInvoice (FT/FR), CreditNote (NC), DebitNote (ND), Receipt (RC)
 * e devolve a estrutura esperada por AGTPayloadBuilder::buildDocument().
 */
class DocumentMapper
{
    /** Default EAC (CAE) se o tenant não tiver configurado. */
    private const DEFAULT_EAC = '00000';

    /**
     * Tradução de nomenclaturas antigas para os tipos que a AGT aceita.
     * A lista fechada é [IRT, II, IS, IVA, IP, IAC, OU]; 'IPU' e 'IPC' existiam
     * na aplicação e faziam a validação do payload recusar o pedido inteiro.
     */
    public const TIPOS_RETENCAO_AGT = [
        'IPU' => 'IP',    // Imposto Predial Urbano → Imposto Predial
        'IPC' => 'IAC',   // Prestação de Capitais → Aplicação de Capitais
        'IRT' => 'IRT',
        'II'  => 'II',
        'IS'  => 'IS',
        'IVA' => 'IVA',
        'IP'  => 'IP',
        'IAC' => 'IAC',
        'OU'  => 'OU',
    ];

    /**
     * Mapear um modelo de documento para o array AGT v1.2.
     */
    public function map(Model $document): array
    {
        $type = $this->resolveType($document);
        $client = $document->client ?? $document->invoice?->client ?? null;
        if ($client && (int) $client->tenant_id !== (int) $document->tenant_id) {
            throw new \DomainException('O cliente do documento pertence a outro tenant.');
        }
        if ($document instanceof Receipt
            && $document->invoice
            && (int) $document->invoice->tenant_id !== (int) $document->tenant_id) {
            throw new \DomainException('A factura de origem do recibo pertence a outro tenant.');
        }
        $tenantSettings = $this->getSettings($document);

        $eacCode = $document->eac_code
            ?? $tenantSettings?->agt_eac_code
            ?? self::DEFAULT_EAC;

        $documentNo  = $this->resolveDocumentNumber($document, $type);
        $documentDate = $this->resolveDocumentDate($document);
        $systemEntry  = $this->resolveSystemEntryDate($document);

        $companyName = $client?->name ?? $client?->company_name ?? 'Consumidor Final';
        // DS.120 §4.1: NIF de consumidor anónimo = 999999999 (nove noves).
        $customerNif = preg_replace('/\D+/', '', (string) ($client?->nif ?? ''));
        if (!in_array(strlen($customerNif), [9, 10, 14], true) || preg_match('/^0+$/', $customerNif)) {
            $customerNif = '999999999';
        }
        // ISO 3166-1 alfa-2, sempre — é o que a DS.120 exige em
        // `customerCountry`. A conversão anterior só conhecia «ANGOLA» e
        // deixava passar tudo o resto tal como estava: um cliente gravado com
        // «Portugal» seguia para a AGT como «PORTUGAL», oito caracteres num
        // campo de dois. O que não se reconhece cai no país da casa, porque
        // um documento tem de sair — e o ecrã já não deixa criar mais desses.
        $customerCountry = \App\Support\Geografia::normalizarPais($client?->country)
            ?? \App\Support\Geografia::PAIS_PADRAO;

        $items = $this->itensFrescos($document);
        // A NC inverte o sinal (preenche debitAmount); a ND acresce à dívida,
        // como uma factura, logo mantém creditAmount.
        $isCreditNote = ($type === 'NC');
        // Ambas são documentos RECTIFICATIVOS (Art. 12º Decreto 71/25) e por
        // isso ambas têm de referenciar o documento corrigido e o motivo. Antes
        // só a NC emitia referenceInfo e a ND seguia sem referência.
        $isRectifying = in_array($type, ['NC', 'ND'], true);

        // O desconto do DOCUMENTO desce às linhas antes de elas saírem: o
        // balcão grava-o só no documento, e as linhas iam ao preço cheio com o
        // netTotal descontado (E23). Ver DescontoDoDocumento.
        $valores = \App\Services\Invoicing\DescontoDoDocumento::repartir($document, $items);

        $lines = [];
        $lineNo = 0;
        foreach ($items as $item) {
            $lines[] = $this->mapLine($item, $lineNo + 1, $isCreditNote, $eacCode, $isRectifying, $valores[$lineNo]);
            $lineNo++;
        }

        // Os totais derivam das LINHAS já construídas, que é exactamente o que a
        // AGT valida ("taxPayable não corresponde à soma dos impostos de todas
        // as linhas"). Antes somava-se o tax_payable gravado MAIS os extras, e o
        // IEC/IS entrava duas vezes.
        $totals = $this->mapTotals($document, $lines);

        $doc = [
            'documentNo'      => $documentNo,
            'documentType'    => $type,
            'documentStatus'  => $document->document_status_code ?? 'N',
            'documentDate'    => $documentDate,
            'systemEntryDate' => $systemEntry,
            'eacCode'         => $eacCode,
            'customerTaxID'   => $customerNif,
            'customerCountry' => $customerCountry,
            'companyName'     => $companyName,
            'lines'           => $lines,
            'documentTotals'  => $totals,
        ];

        if ($document instanceof Receipt) {
            $invoice = $document->invoice;
            $doc['paymentReceipt'] = [
                'paymentMechanism' => $this->mapPaymentMechanism($document->payment_method),
                'paymentAmount' => (float) $document->amount_paid,
                'paymentDate' => optional($document->payment_date)->format('Y-m-d') ?: now()->format('Y-m-d'),
                'sourceDocuments' => $invoice ? [[
                    'lineNo' => 1,
                    'sourceDocumentID' => [
                        'originatingON' => (string) $invoice->invoice_number,
                        'documentDate' => optional($invoice->invoice_date)->format('Y-m-d') ?: now()->format('Y-m-d'),
                    ],
                    'creditAmount' => (float) ($invoice->net_total ?? $invoice->subtotal),
                ]] : [],
            ];
        }

        // Withholding (IRT/IPU/IPC) se aplicável
        $withholding = $this->mapWithholdings($document);
        if (!empty($withholding)) {
            $doc['withholdingTaxList'] = $withholding;
        }

        return $doc;
    }

    /**
     * As linhas do documento, lidas de fresco quando a colecção pode estar
     * em cache vazia.
     *
     * O documento é gravado ANTES das suas linhas, e quem corre no evento
     * `created` — o observer que desconta stock, por exemplo — lê
     * `$documento->items` nesse momento. O Eloquent guarda essa colecção
     * vazia e nunca mais a consulta. A submissão à AGT, feita a seguir com o
     * MESMO objecto, ia buscar a colecção em cache e enviava um documento com
     * documentTotals preenchidos e ZERO linhas.
     *
     * A AGT recusava-o. Medido: o payload gravado da FR/000048 não tinha
     * linha nenhuma, embora o talão mostrasse o artigo e a base de dados
     * tivesse a linha lá.
     */
    private function itensFrescos(Model $document)
    {
        if (!method_exists($document, 'items')) {
            return collect();
        }

        // Só se estiver carregada E vazia: é esse o caso suspeito. Carregada
        // com conteúdo, ou por carregar, comporta-se como sempre.
        if ($document->relationLoaded('items') && $document->items->isEmpty()) {
            return $document->items()->get();
        }

        return $document->items ?? collect();
    }

    /**
     * Resolver tipo de documento AGT (DS.120 §4.1 — 18 tipos suportados).
     * Valida contra `AGTDocumentType` enum; tipos desconhecidos caem em FT.
     */
    /**
     * O código AGT do tipo de documento (FT, FR, NC, ND, RC…), sem construir
     * o payload todo.
     *
     * Existe para se poder ENFILEIRAR um documento (criar a submissão
     * pendente) sem o mapear nem o assinar — o mapeamento completo e a
     * assinatura ficam para o momento do envio, à boleia do tráfego.
     */
    public function documentTypeCode(Model $document): string
    {
        return $this->resolveType($document);
    }

    private function resolveType(Model $document): string
    {
        $raw = null;
        if ($document instanceof SalesInvoice) {
            $raw = $document->invoice_type ?? 'FT';
        } elseif ($document instanceof CreditNote) {
            $raw = 'NC';
        } elseif ($document instanceof DebitNote) {
            $raw = 'ND';
        } elseif ($document instanceof Receipt) {
            // Pode ser RC (recibo associado), RG (genérico) ou RA (adiantamento) consoante a flag.
            $raw = $document->receipt_kind ?? 'RC';
        } elseif ($document instanceof \App\Models\Invoicing\TransportGuide) {
            // A AGT (DS.120) trata guias de transporte/remessa como GF (Guia de Frete).
            $raw = 'GF';
        }

        $resolved = \App\Enums\AGTDocumentType::tryFromCode($raw);
        return $resolved?->value ?? 'FT';
    }

    private function resolveDocumentNumber(Model $document, string $type): string
    {
        return (string) (
            $document->invoice_number
            ?? $document->credit_note_number
            ?? $document->debit_note_number
            ?? $document->receipt_number
            ?? $document->guide_number
            ?? ''
        );
    }

    private function resolveDocumentDate(Model $document): string
    {
        $date = $document->invoice_date
            ?? $document->issue_date
            ?? $document->payment_date
            ?? $document->receipt_date
            ?? now();
        return $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : (string) $date;
    }

    /**
     * O system_entry_date tal como foi assinado, sem conversão de fuso.
     *
     * Havia aqui um ->utc(), e ele era inofensivo apenas porque o fuso da
     * aplicação também era UTC — a conversão não fazia nada. Com a aplicação em
     * hora de Angola passaria a tirar uma hora ao valor gravado, e a submissão
     * declararia à AGT um instante diferente daquele que foi assinado.
     *
     * Todo o resto da cadeia trata esta coluna como relógio de parede, sem
     * fuso: o SignatureService assina o format('Y-m-d H:i:s') em bruto, o
     * SAFTGenerator escreve-o em bruto, e o AGTClient:417 já fazia isto mesmo.
     * Este método era o único fora do passo. Passa a estar de acordo, e o que
     * sai daqui é byte a byte o que saía antes para todos os documentos que já
     * existem.
     *
     * O sufixo Z fica, e passa a ser um rótulo e não uma afirmação: o valor
     * assinado é o que a AGT confere, e é o mesmo dos dois lados.
     */
    private function resolveSystemEntryDate(Model $document): string
    {
        $dt = $document->system_entry_date ?? $document->created_at ?? now();
        return $dt instanceof \DateTimeInterface
            ? $dt->format('Y-m-d\TH:i:s\Z')
            : (string) $dt;
    }

    private function getSettings(Model $document)
    {
        try {
            return \App\Models\Invoicing\InvoicingSettings::forTenant($document->tenant_id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Mapear uma linha (item) para a estrutura AGT v1.2.
     *
     * @param  array{bruto: float, desconto: float, liquido: float}  $valor
     *         a linha com o desconto do documento já repartido (DescontoDoDocumento)
     */
    private function mapLine($item, int $lineNo, bool $isCreditNote, ?string $defaultEac, bool $isRectifying, array $valor): array
    {
        $unitPrice    = (float) ($item->unit_price ?? 0);
        $unitPriceBase = (float) ($item->unit_price_base ?? $item->unit_price ?? 0);
        $quantity     = (float) ($item->quantity ?? 1);
        $discount     = (float) ($item->discount_amount ?? 0);
        // Líquido de TODOS os descontos, o da linha e a parte do global: é o
        // que fecha com o netTotal do documento.
        $netLine      = $valor['liquido'];
        $comDesconto  = $valor['desconto'] > 0.005;

        // DS.120: unitPrice é o preço SEM descontos; unitPriceBase é o preço
        // JÁ deduzido dos descontos de linha e de cabeçalho, e a quantidade
        // vezes ele dá o creditAmount (E21). Sem desconto, os dois coincidem.
        if ($comDesconto) {
            $unitPriceBase = \App\Services\Invoicing\DescontoDoDocumento::precoLiquido($netLine, $quantity);
        }
        $taxAmount    = (float) ($item->tax_amount ?? 0);
        $taxRate      = (float) ($item->tax_rate ?? 0);

        $line = [
            'lineNumber'         => $lineNo,
            // Código do produto tal como o vendedor o identifica: SKU, senão o
            // código do artigo. O id interno da base de dados era o último
            // recurso e passava a ser o mais comum, porque a maioria dos
            // produtos não tem SKU — a AGT recebia um número sem significado.
            'productCode'        => (string) (
                $item->product?->sku
                    ?: ($item->product?->code
                    ?: ($item->product_id ?? "ITEM{$lineNo}"))
            ),
            'productDescription' => (string) ($item->description ?? $item->product_name ?? $item->product?->name ?? 'Item'),
            'quantity'           => round($quantity, 4),
            'unitOfMeasure'      => (string) ($item->unit ?? 'UN'),
            'unitPriceBase'      => $comDesconto ? $unitPriceBase : round($unitPriceBase, 2),
            'unitPrice'          => round($unitPrice, 2),
        ];

        // referenceInfo (obrigatório na NC e na ND)
        if (($isRectifying || $isCreditNote) && !empty($item->reference_invoice_no)) {
            $line['referenceInfo'] = [
                'reference'           => (string) $item->reference_invoice_no,
                'referenceItemLineNo' => (int) ($item->reference_item_line_no ?? $lineNo),
                'reason'              => (string) ($item->reference_reason ?? 'Rectificação'),
            ];
        }

        // debitAmount / creditAmount (mutuamente exclusivos; só um pode estar > 0).
        // As colunas são NOT NULL DEFAULT 0, logo o `??` nunca disparava e TODAS as
        // linhas seguiam para a AGT com 0 — tratar 0 como "não preenchido".
        $debitCol  = (float) ($item->debit_amount ?? 0);

        // O líquido já vem das colunas quando a linha as tem (as notas) e já
        // leva a parte do desconto do documento.
        if ($isCreditNote || $debitCol > 0) {
            $line['debitAmount']  = round($netLine, 2);
            $line['creditAmount'] = 0;
        } else {
            $line['debitAmount']  = 0;
            $line['creditAmount'] = round($netLine, 2);
        }

        // taxContribution: a AGT APURA o IVA por arredondamento ao cêntimo por
        // EXCESSO (CEIL, DS.120 §4.1) sobre base × taxa a precisão plena. O
        // tax_amount gravado já vem arredondado a 2 casas — aplicar-lhe o ceil
        // depois não tem fracção para subir e sai MENOS UM CÊNTIMO do que a AGT
        // apura (recusa E70: «taxContribution … não corresponde ao imposto
        // apurado»). Recalcula-se aqui a partir da MESMA base do
        // calculateTotals (líquido de desconto + IEC) e faz-se o ceil sobre o
        // produto a precisão plena, para bater ao cêntimo com a AGT.
        $iecLine = 0.0;
        if ($item instanceof Model && $item->getKey()) {
            $iecLine = (float) \App\Models\Invoicing\LineTax::where('line_type', get_class($item))
                ->where('line_id', $item->getKey())
                ->where('tax_type', \App\Models\Invoicing\LineTax::TIPO_IEC)
                ->sum('tax_amount');
        }
        // A base é o líquido tal como vai no payload (creditAmount/debitAmount =
        // round($netLine, 2)) mais o IEC da linha — a AGT apura sobre o que lê.
        $baseIva = round($netLine, 2) + $iecLine;
        $taxContribution = $taxRate > 0
            ? AGTPayloadBuilder::ceilCents(($baseIva * $taxRate) / 100)
            : 0.0;

        // taxes (array)
        $tax = [
            'taxType'          => 'IVA',
            'taxCountryRegion' => $item->tax_country_region ?? 'AO',
            'taxCode'          => $item->tax_code ?? ($taxRate > 0 ? 'NOR' : 'ISE'),
            'taxPercentage'    => round($taxRate, 2),
            'taxContribution'  => $taxContribution,
        ];
        if (!empty($item->tax_exemption_code)) {
            $tax['taxExemptionCode'] = $item->tax_exemption_code;
        }
        if (!empty($item->tax_exemption_reason)) {
            $tax['taxExemptionReason'] = $item->tax_exemption_reason;
        }

        // O IVA vem das colunas da linha; IEC e IS vêm de invoicing_line_taxes.
        // O IS pertence a taxes[]: a FAQ oficial do SAF-T (modelo que a AGT
        // segue) confirma IVA, IS e NS como valores de TaxType. As recusas
        // anteriores vinham do taxCode — a nossa tabela de verbas estava errada.
        $line['taxes'] = array_merge(
            [$tax],
            $item instanceof \Illuminate\Database\Eloquent\Model
                ? \App\Models\Invoicing\LineTax::agtTaxesForLine($item)
                : []
        );

        // DS.120: o total dos descontos da linha — o seu e a parte do global.
        $line['settlementAmount'] = $comDesconto
            ? round($valor['desconto'], 2)
            : round((float) ($item->settlement_amount ?? $discount ?? 0), 2);

        return $line;
    }

    /**
     * @param array $lines Linhas já mapeadas, para os totais derivarem delas.
     */
    private function mapTotals(Model $document, array $lines = []): array
    {
        if ($document instanceof Receipt) {
            $invoice = $document->invoice;
            return [
                'taxPayable' => (float) ($invoice?->tax_payable ?? $invoice?->tax_amount ?? 0),
                'netTotal' => (float) ($invoice?->net_total ?? $invoice?->subtotal ?? $document->amount_paid),
                'grossTotal' => (float) ($invoice?->gross_total ?? $invoice?->total ?? $document->amount_paid),
            ];
        }

        $netTotal = (float) ($document->net_total ?? $document->subtotal ?? 0);

        // taxPayable = SOMA DOS IMPOSTOS DAS LINHAS. É textualmente o que a AGT
        // valida; derivar daqui garante que nunca divergem. Antes usava-se o
        // tax_payable gravado MAIS os extras, e o IEC/IS era contado duas vezes.
        $taxPayable = 0.0;
        foreach ($lines as $linha) {
            foreach ($linha['taxes'] ?? [] as $imposto) {
                $taxPayable += (float) ($imposto['taxContribution'] ?? 0);
            }
        }

        // Sem linhas mapeadas (recibos, chamadas isoladas) usa-se o gravado.
        if (empty($lines)) {
            $taxPayable = (float) ($document->tax_payable ?? $document->tax_amount ?? 0);
        }

        return [
            'taxPayable' => round($taxPayable, 2),
            'netTotal'   => round($netTotal, 2),
            // grossTotal reconstruído: tem de fechar com os outros dois.
            'grossTotal' => round($netTotal + $taxPayable, 2),
        ];
    }

    /** Soma dos impostos extra (IEC/IS) de todas as linhas do documento. */
    private function extraTaxTotal(Model $document): float
    {
        $items = $document->items ?? null;
        if (!$items || $items->isEmpty()) {
            return 0.0;
        }

        $primeira = $items->first();
        if (!$primeira instanceof Model) {
            return 0.0;
        }

        return (float) \App\Models\Invoicing\LineTax::where('line_type', get_class($primeira))
            ->whereIn('line_id', $items->pluck('id'))
            ->sum('tax_amount');
    }

    private function mapPaymentMechanism(?string $method): string
    {
        return match (strtolower(trim((string) $method))) {
            'cash', 'dinheiro' => 'NU',
            'transfer', 'bank_transfer', 'transferencia' => 'TB',
            'multicaixa', 'tpa', 'card', 'cartao' => 'CD',
            'check', 'cheque' => 'CH',
            default => 'OU',
        };
    }

    /** Mapear retenções na fonte (polimórfico). */
    private function mapWithholdings(Model $document): array
    {
        $list = [];

        // A tabela invoicing_withholding_taxes é a fonte AUTORITÁVEL: guarda o
        // tipo escolhido (IRT, IPU ou IPC) e a taxa aplicada. O campo legado
        // irt_amount serve de recurso e só é usado quando a tabela nada tem —
        // ele guarda o valor de QUALQUER retenção, pelo que emiti-lo sempre
        // declarava uma retenção IRT a mais nos documentos com IPU ou IPC.
        try {
            $rows = \DB::table('invoicing_withholding_taxes')
                ->where('document_type', get_class($document))
                ->where('document_id', $document->id)
                ->get();
            foreach ($rows as $r) {
                $list[] = [
                    // A AGT só aceita [IRT, II, IS, IVA, IP, IAC, OU]. Dados
                    // antigos podem ter IPU/IPC — traduzem-se, em vez de fazer
                    // o payload inteiro ser recusado na validação.
                    'withholdingTaxType'        => self::TIPOS_RETENCAO_AGT[$r->withholding_tax_type]
                        ?? (in_array($r->withholding_tax_type, self::TIPOS_RETENCAO_AGT, true)
                            ? $r->withholding_tax_type
                            : 'OU'),
                    'withholdingTaxDescription' => $r->withholding_tax_description,
                    'withholdingTaxAmount'      => round((float) $r->withholding_tax_amount, 2),
                ];
            }
        } catch (\Throwable $e) {
            // ignora se a tabela não existir
        }

        // Recurso: IRT automático dos serviços, quando nada foi registado.
        if (empty($list) && isset($document->irt_amount) && (float) $document->irt_amount > 0) {
            $list[] = [
                'withholdingTaxType'        => 'IRT',
                'withholdingTaxDescription' => 'Imposto sobre Rendimento do Trabalho',
                'withholdingTaxAmount'      => round((float) $document->irt_amount, 2),
            ];
        }

        return $list;
    }
}
