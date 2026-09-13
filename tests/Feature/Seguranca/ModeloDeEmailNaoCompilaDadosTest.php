<?php

namespace Tests\Feature\Seguranca;

use App\Models\EmailTemplate;
use Tests\TenantTestCase;

/**
 * O CORPO DE UM EMAIL NUNCA É COMPILADO COMO BLADE.
 *
 * `wrapInLayout` escrevia o corpo, já com os valores do pedido, num ficheiro
 * `.blade.php` e compilava-o: registar uma empresa com o nome
 * «Loja {{ system('id') }}» corria código no servidor, antes de qualquer
 * pagamento (auditoria de segurança de 2026-09-13).
 */
class ModeloDeEmailNaoCompilaDadosTest extends TenantTestCase
{
    public function test_um_nome_com_codigo_blade_sai_como_texto(): void
    {
        $modelo = new EmailTemplate([
            'slug' => 'ensaio-' . uniqid(),
            'name' => 'Ensaio',
            'subject' => 'Nova empresa: {empresa_nome}',
            // Sem <!DOCTYPE: é o caminho que embrulhava no layout.
            'body_html' => '<p>Empresa: {empresa_nome}</p><p>Telefone: {empresa_telefone}</p>',
        ]);

        $antes = glob(resource_path('views/emails/temp_*')) ?: [];

        $r = $modelo->render([
            'empresa_nome' => "Loja {{ 7 * 191 }} @php echo 'CORREU'; @endphp",
            'empresa_telefone' => '<script>alert(1)</script>',
        ]);

        $this->assertStringNotContainsString('1337', $r['body_html'], 'a expressão Blade não pode ser avaliada');
        $this->assertStringNotContainsString('CORREU', str_replace("echo 'CORREU'", '', html_entity_decode($r['body_html'])), '@php não pode correr');
        $this->assertStringContainsString('{{ 7 * 191 }}', html_entity_decode($r['body_html']), 'o nome chega tal e qual');
        $this->assertStringNotContainsString('<script>', $r['body_html'], 'um valor não vira marcação no HTML');
        $this->assertStringContainsString('&lt;script&gt;', $r['body_html']);

        $this->assertSame($antes, glob(resource_path('views/emails/temp_*')) ?: [], 'nenhum ficheiro Blade temporário');
    }
}
