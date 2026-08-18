<?php

namespace Tests\Unit\Invoicing;

use PHPUnit\Framework\TestCase;

/**
 * Cada tipo de documento tem de ter o SEU carrinho.
 *
 * A venda e a compra construiam a mesma chave de sessao,
 * 'invoice_{tenant}_{user}', e o pacote de carrinho trata chaves iguais
 * como um unico balde. Artigos lancados numa factura de compra apareciam
 * na factura de venda seguinte do mesmo utilizador — numerada, com hash
 * encadeado e comunicada a AGT com linhas que nunca foram vendidas.
 */
class CarrinhoPorTipoDeDocumentoTest extends TestCase
{
    private const ECRAS = [
        'app/Livewire/Invoicing/Sales/InvoiceCreate.php',
        'app/Livewire/Invoicing/Purchases/InvoiceCreate.php',
        'app/Livewire/Invoicing/Sales/ProformaCreate.php',
        'app/Livewire/Invoicing/Purchases/ProformaCreate.php',
    ];

    /** Extrai o prefixo literal da chave do carrinho de cada ecra. */
    private function prefixos(): array
    {
        $raiz = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR;
        $prefixos = [];

        foreach (self::ECRAS as $ecra) {
            $codigo = file_get_contents($raiz . $ecra);
            $this->assertNotFalse($codigo, "Nao consegui ler {$ecra}");

            $encontrou = preg_match(
                "/cartInstance\s*=\s*'([a-z_]+)'\s*\.\s*activeTenantId/",
                $codigo,
                $m
            );

            $this->assertSame(1, $encontrou,
                "{$ecra} deixou de construir a chave do carrinho como se esperava.");

            $prefixos[$ecra] = $m[1];
        }

        return $prefixos;
    }

    public function test_cada_ecra_tem_um_prefixo_de_carrinho_distinto(): void
    {
        $prefixos = $this->prefixos();

        $this->assertSame(
            count($prefixos),
            count(array_unique($prefixos)),
            'Dois ecras partilham o carrinho: ' . json_encode($prefixos)
        );
    }

    public function test_venda_e_compra_nao_partilham_o_carrinho(): void
    {
        $prefixos = $this->prefixos();

        $this->assertNotSame(
            $prefixos['app/Livewire/Invoicing/Sales/InvoiceCreate.php'],
            $prefixos['app/Livewire/Invoicing/Purchases/InvoiceCreate.php'],
            'A factura de venda e a de compra voltaram a partilhar o carrinho.'
        );

        $this->assertNotSame(
            $prefixos['app/Livewire/Invoicing/Sales/ProformaCreate.php'],
            $prefixos['app/Livewire/Invoicing/Purchases/ProformaCreate.php'],
            'As proformas de venda e de compra voltaram a partilhar o carrinho.'
        );
    }

    public function test_o_prefixo_distingue_o_sentido_do_documento(): void
    {
        $prefixos = $this->prefixos();

        foreach ($prefixos as $ecra => $prefixo) {
            $sentido = str_contains($ecra, '/Sales/') ? 'sales' : 'purchase';

            $this->assertStringStartsWith($sentido, $prefixo,
                "{$ecra} devia ter um prefixo que comeca por '{$sentido}'.");
        }
    }
}
