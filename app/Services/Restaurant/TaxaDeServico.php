<?php

namespace App\Services\Restaurant;

use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\RestaurantSettings;

/**
 * A taxa de serviço da casa.
 *
 * É RECEITA, ao contrário da gorjeta: a casa cobra-a, está na conta, e por
 * isso entra na factura como linha e é tributada como qualquer outra venda.
 * Uma taxa de serviço que não fosse à factura era receita não declarada.
 *
 * PORQUE PRECISA DE UM ARTIGO. O `ModuleInvoiceService` exige que cada linha
 * tenha artigo do catálogo — sem isso a factura é recusada na emissão. Este
 * serviço trata de ter um, criado uma vez por empresa e reutilizado sempre.
 * É um SERVIÇO e não um produto: não tem stock, e um artigo com stock que
 * nunca se compra ficava eternamente negativo.
 */
class TaxaDeServico
{
    public const NOME = 'Taxa de serviço';

    public const CODIGO = 'SERV-TAXA';

    public const NOME_ENTREGA = 'Taxa de entrega';

    public const CODIGO_ENTREGA = 'SERV-ENTREGA';

    /** Quanto é a taxa desta comanda, ao cêntimo. */
    public function valorPara(Order $order, RestaurantSettings $definicoes): float
    {
        $percentagem = (float) $definicoes->service_charge_percent;

        if ($percentagem <= 0) {
            return 0.0;
        }

        // SOBRE OS PRATOS, NÃO SOBRE O TOTAL.
        //
        // O total já leva a taxa de entrega — cobrar serviço sobre o
        // transporte é cobrar serviço sobre uma coisa que ninguém serviu à
        // mesa. E se a taxa fosse calculada sobre um total que já a inclui,
        // crescia a cada recálculo.
        $base = (float) $order->subtotal - (float) $order->discount_total;

        return round(max(0, $base) * $percentagem / 100, 2);
    }

    /**
     * O artigo do catálogo que representa a taxa na factura.
     *
     * Criado à medida e guardado nas definições: procurá-lo pelo nome a cada
     * fecho de conta dava um artigo diferente assim que alguém lhe mudasse o
     * nome, e as facturas antigas passavam a apontar para outro sítio.
     */
    public function artigo(int $tenantId, ?RestaurantSettings $definicoes = null): Product
    {
        $definicoes ??= RestaurantSettings::forTenant($tenantId);

        if ($definicoes->service_charge_product_id) {
            $existente = Product::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($definicoes->service_charge_product_id);

            if ($existente) {
                return $existente;
            }
        }

        $artigo = $this->artigoPorCodigo($tenantId, self::CODIGO, self::NOME);

        $definicoes->update(['service_charge_product_id' => $artigo->id]);

        return $artigo;
    }

    /**
     * O artigo do transporte — SEPARADO do da taxa de serviço.
     *
     * São duas receitas diferentes: uma paga quem serve à mesa, a outra paga
     * quem leva a comida a casa. Usar o mesmo artigo nas duas fazia o
     * relatório por artigo somá-las e ninguém conseguia dizer quanto rendeu
     * cada uma.
     */
    public function artigoDeEntrega(int $tenantId): Product
    {
        return $this->artigoPorCodigo($tenantId, self::CODIGO_ENTREGA, self::NOME_ENTREGA);
    }

    /**
     * Encontra ou cria o artigo, PELO CÓDIGO.
     *
     * Pelo código e não pelo nome: o nome é para se ler e alguém há-de lhe
     * mexer, e nesse dia passava a criar-se um artigo novo a cada conta.
     */
    private function artigoPorCodigo(int $tenantId, string $codigo, string $nome): Product
    {
        $existente = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $codigo)
            ->first();

        if ($existente) {
            return $existente;
        }

        $imposto = Tax::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('rate')
            ->first();

        return Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'type' => 'servico',
            'name' => $nome,
            'code' => $codigo,
            'price' => 0,
            'cost' => 0,
            'unit' => 'UN',
            'tax_type' => 'iva',
            'tax_rate_id' => $imposto?->id,
            'manage_stock' => false,
            'is_active' => true,
        ]);
    }

    /**
     * A linha da taxa, pronta a juntar às da factura.
     *
     * Devolve null quando não há taxa a cobrar — e o chamador não precisa de
     * saber porquê (percentagem a zero, conta a zero, ou já tudo facturado).
     */
    public function linhaPara(Order $order, RestaurantSettings $definicoes, int $tenantId, float $baseFacturada): ?array
    {
        $percentagem = (float) $definicoes->service_charge_percent;

        if ($percentagem <= 0 || $baseFacturada <= 0) {
            return null;
        }

        $valor = round($baseFacturada * $percentagem / 100, 2);

        if ($valor <= 0) {
            return null;
        }

        return [
            'product_id' => $this->artigo($tenantId, $definicoes)->id,
            'name' => self::NOME.' ('.rtrim(rtrim(number_format($percentagem, 2, ',', '.'), '0'), ',').'%)',
            'quantity' => 1,
            'unit_price' => $valor,
            'discount_percent' => 0,
            'unit' => 'UN',
        ];
    }
}
