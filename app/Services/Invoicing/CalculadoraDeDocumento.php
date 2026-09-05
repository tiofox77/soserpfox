<?php

namespace App\Services\Invoicing;

use App\Helpers\InvoiceCalculationHelper;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * OS TOTAIS DE UM DOCUMENTO, CONTADOS NO SERVIDOR. SEMPRE.
 *
 * Existe por uma razão só: o ecrã em React NÃO PODE ter uma segunda
 * implementação da matemática do imposto. Uma cópia em TypeScript da conta que
 * o `InvoiceCalculationHelper` faz divergiria ao primeiro ajuste, e a
 * divergência aparece como um cêntimo na factura — que a AGT recusa com E70.
 *
 * Por isso o editor pergunta ao servidor a cada alteração de linha, e o
 * servidor responde com os números. O ecrã mostra o que lhe dizem; a conta é
 * feita uma vez, no sítio onde já estava.
 *
 * E A TAXA DE CADA LINHA SAI DO `TaxResolver`, que é a fonte única — nunca uma
 * percentagem que o browser tenha mandado. Um cliente que mande `tax_rate: 0`
 * numa linha de um artigo a 14% não muda nada: a taxa é lida do artigo, do
 * regime fiscal e da região da operação, aqui.
 */
class CalculadoraDeDocumento
{
    /**
     * @param  array<int, array{product_id?: int|null, description?: string|null,
     *                          quantity?: float, price?: float, discount_percent?: float}>  $linhas
     * @return array{linhas: array, totais: array}
     */
    public function calcular(
        array $linhas,
        float $descontoComercial = 0,
        float $descontoValor = 0,
        float $descontoFinanceiro = 0,
        bool $eServico = false,
        ?int $tenantId = null
    ): array {
        $tenantId = $tenantId ?: activeTenantId();

        $itens = $this->paraCarrinho($linhas, $tenantId);

        $totais = InvoiceCalculationHelper::calculateTotals(
            $itens,
            $descontoComercial,
            $descontoValor,
            $descontoFinanceiro,
            $eServico
        );

        return [
            'linhas' => $itens->map(fn ($i) => $this->linhaCalculada($i))->values()->all(),
            'totais' => $this->totaisEnxutos($totais),
        ];
    }

    /**
     * As linhas do pedido, com a taxa RESOLVIDA e no formato que o helper
     * espera (objectos com `price`, `quantity` e `attributes`).
     */
    private function paraCarrinho(array $linhas, int $tenantId): Collection
    {
        return collect($linhas)->map(function (array $l) use ($tenantId) {
            $artigo = ! empty($l['product_id'])
                ? Product::where('tenant_id', $tenantId)->find($l['product_id'])
                : null;

            // A TAXA VEM DAQUI, NUNCA DO PEDIDO.
            $imposto = TaxResolver::forProduct($artigo, $tenantId);

            return (object) [
                'id' => $artigo?->id,
                'name' => $artigo?->name ?? ($l['description'] ?? ''),
                'price' => round((float) ($l['price'] ?? $artigo?->price ?? 0), 2),
                'quantity' => (float) ($l['quantity'] ?? 1),
                'attributes' => [
                    'description' => $l['description'] ?? null,
                    'unit' => $artigo?->unit ?? 'UN',
                    'discount_percent' => (float) ($l['discount_percent'] ?? 0),
                    'tax_rate' => (float) $imposto['rate'],
                    'tax_code' => $imposto['tax_code'],
                    'exemption_code' => $imposto['exemption_code'],
                    'exemption_reason' => $imposto['exemption_reason'],
                ],
            ];
        });
    }

    private function linhaCalculada(object $i): array
    {
        $a = $i->attributes;

        $bruto = round((float) $i->price * (float) $i->quantity, 2);
        $desconto = round($bruto * (((float) $a['discount_percent']) / 100), 2);
        $base = round($bruto - $desconto, 2);

        /*
         * O IMPOSTO É `CEIL` AO CÊNTIMO DA BASE VEZES A TAXA.
         *
         * É a regra do DS.120 §4.1 que a AGT verifica, e não um arredondamento
         * qualquer: uma linha calculada com `round` sai um cêntimo abaixo em
         * certos valores e o documento é recusado com E70. A mesma regra que
         * o emissor fiscal já aplica.
         */
        $imposto = $a['tax_rate'] > 0
            ? ceil($base * ((float) $a['tax_rate']) / 100 * 100) / 100
            : 0.0;

        return [
            'product_id' => $i->id,
            'nome' => $i->name,
            'description' => $a['description'],
            'unit' => $a['unit'],
            'quantity' => (float) $i->quantity,
            'price' => (float) $i->price,
            'discount_percent' => (float) $a['discount_percent'],
            'bruto' => $bruto,
            'desconto' => $desconto,
            'base' => $base,
            'tax_rate' => (float) $a['tax_rate'],
            'tax_code' => $a['tax_code'],
            'exemption_reason' => $a['exemption_reason'],
            'imposto' => $imposto,
            'total' => round($base + $imposto, 2),
        ];
    }

    /**
     * Só o que o ecrã mostra.
     *
     * O helper devolve uma dúzia de chaves internas; publicar todas era
     * prometer que nenhuma delas muda de nome.
     */
    private function totaisEnxutos(array $t): array
    {
        $n = fn (string $chave) => round((float) ($t[$chave] ?? 0), 2);

        return [
            'bruto' => $n('subtotal_original'),
            'desconto_comercial' => $n('desconto_comercial_total'),
            'desconto_por_linha' => $n('total_discount_items'),
            'liquido' => $n('subtotal'),
            'base' => $n('incidencia_iva'),
            'imposto' => $n('tax_amount'),
            'retencao' => $n('irt_amount'),
            'total' => $n('total'),
        ];
    }
}
