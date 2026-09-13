<?php

namespace Tests\Feature\Seguranca;

use App\Models\Client;
use App\Models\Invoicing\QuoteTemplate;
use App\Models\Invoicing\SalesQuote;
use App\Services\Invoicing\Propostas\RenderizadorDeProposta;
use App\Support\HtmlSeguro;
use Tests\TenantTestCase;

/**
 * O MODELO DE PROPOSTA NÃO CORRE SCRIPTS NEM VAI BUSCAR ENDEREÇOS DE FORA.
 *
 * O bloco de texto saía cru e `{{cliente.nome}}` entrava sem escapar: um nome
 * registado na marcação pública do salão corria código no ecrã de quem
 * pré-visualizasse; e uma imagem externa punha o PDF a visitar a rede interna
 * (auditoria de segurança de 2026-09-13).
 */
class PropostaNaoCorreScriptsTest extends TenantTestCase
{
    public function test_o_bloco_de_texto_e_o_nome_do_cliente_sao_limpos(): void
    {
        $modelo = new QuoteTemplate(['tenant_id' => $this->tenant->id, 'nome' => 'Ensaio']);
        $modelo->blocos = [
            ['tipo' => 'texto', 'html' => '<p>Caro {{cliente.nome}}, <b>obrigado</b>.</p><img src=x onerror=alert(1)><script>alert(2)</script>'],
            ['tipo' => 'imagem', 'url' => 'http://127.0.0.1:8080/interno.png', 'largura' => 50],
        ];

        $cliente = new Client(['name' => '<img src=y onerror=alert(3)>']);
        $orcamento = new SalesQuote(['tenant_id' => $this->tenant->id]);
        $orcamento->setRelation('client', $cliente);

        $html = app(RenderizadorDeProposta::class)->render($modelo, $orcamento, $this->tenant);

        $this->assertDoesNotMatchRegularExpression('/<[^>]*onerror/i', $html, 'nenhuma etiqueta com onerror (o nome sai como texto escapado)');
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('127.0.0.1', $html, 'a imagem externa não entra');
        $this->assertStringContainsString('<b>obrigado</b>', $html, 'a formatação fica');
    }

    public function test_o_limpador_tira_o_que_corre_e_deixa_a_formatacao(): void
    {
        $limpo = HtmlSeguro::limpar('<h2 style="color:#123">T</h2><a href="javascript:x()" onclick="y">a</a><a href="https://ok.ao">b</a><iframe src="//x"></iframe><ul><li>1</li></ul>');

        $this->assertSame('<h2 style="color:#123">T</h2><a>a</a><a href="https://ok.ao">b</a><ul><li>1</li></ul>', $limpo);
    }
}
