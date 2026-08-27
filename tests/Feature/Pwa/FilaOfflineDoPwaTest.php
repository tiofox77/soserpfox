<?php

namespace Tests\Feature\Pwa;

use Tests\TenantTestCase;

/**
 * As regras da fila offline que não podem ser quebradas.
 *
 * Os testes olham para o CÓDIGO do motor (public/js/pwa-invoicing.js) porque é
 * JavaScript que corre no telemóvel e não há aqui como o executar. É pouco,
 * mas é o que impede uma regressão silenciosa em regras que só se descobrem
 * partidas depois de um vendedor perder um dia de vendas.
 */
class FilaOfflineDoPwaTest extends TenantTestCase
{
    private function motor(): string
    {
        return file_get_contents(public_path('js/pwa-invoicing.js'));
    }

    /**
     * A VENDA DE UMA EMPRESA NUNCA ENTRA NOS LIVROS DE OUTRA.
     *
     * O aparelho pode mudar de empresa e a fila é preservada de propósito. Sem
     * carimbo, uma venda feita offline para a empresa A era enviada com a
     * sessão da empresa B e ficava gravada nos livros de B — com número fiscal
     * de B e comunicada à AGT em nome de B.
     */
    public function test_os_trabalhos_da_fila_levam_carimbo_da_empresa(): void
    {
        $motor = $this->motor();

        $this->assertMatchesRegularExpression(
            '/db\.sync_queue\.add\(\{.*?tenant_id/s',
            $motor,
            'Cada trabalho tem de nascer carimbado com a empresa.'
        );
    }

    public function test_a_fila_recusa_enviar_trabalho_de_outra_empresa(): void
    {
        $motor = $this->motor();

        $this->assertStringContainsString(
            "job.tenant_id !== empresaActual",
            $motor,
            'O envio tem de comparar a empresa do trabalho com a da sessão.'
        );

        $this->assertStringContainsString(
            "'outra_empresa'",
            $motor,
            'Um trabalho de outra empresa fica retido, com estado próprio.'
        );
    }

    /** Retido não é apagado: apagar seria perder uma venda. */
    public function test_o_trabalho_retido_nao_e_apagado(): void
    {
        preg_match('/if \(job\.tenant_id && empresaActual.*?continue;/s', $this->motor(), $m);

        $this->assertNotEmpty($m, 'Guarda de empresa não encontrada.');
        $this->assertStringNotContainsString('delete', $m[0], 'Um trabalho retido nunca pode ser apagado.');
    }

    /**
     * A troca de empresa limpa o catálogo, mas NUNCA a fila.
     *
     * É a armadilha clássica: limpar o IndexedDB inteiro leva com ele as
     * vendas que ainda não subiram.
     */
    public function test_a_troca_de_empresa_nao_limpa_a_fila(): void
    {
        preg_match('/Empresa mudou.*?pwa:tenant-changed/s', $this->motor(), $m);

        $this->assertNotEmpty($m, 'Bloco de troca de empresa não encontrado.');
        $this->assertStringNotContainsString(
            'sync_queue.clear',
            $m[0],
            'A fila de sincronização nunca pode ser limpa ao trocar de empresa.'
        );
    }

    /** E avisa quem ficou para trás — uma venda parada que ninguém vê perde-se na mesma. */
    public function test_a_troca_de_empresa_avisa_do_que_ficou_retido(): void
    {
        $this->assertStringContainsString(
            'mostrarAvisoRetidos',
            $this->motor(),
            'A troca de empresa tem de avisar das operações retidas.'
        );
    }

    /**
     * Recusa definitiva (4xx) não se repete; transitória fica na fila.
     *
     * Sem isto, uma venda que o servidor recusa por aquilo que ela É seria
     * reenviada para sempre, pondo-se à frente das boas que estão atrás.
     */
    public function test_distingue_recusa_definitiva_de_falha_transitoria(): void
    {
        $motor = $this->motor();

        $this->assertStringContainsString('erro.definitivo = definitivo', $motor);
        $this->assertStringContainsString('response.status !== 408', $motor, '408 é "agora não", não "nunca".');
        $this->assertStringContainsString('response.status !== 429', $motor, '429 é "agora não", não "nunca".');
        $this->assertStringContainsString('if (err.definitivo)', $motor, 'A fila tem de agir sobre a recusa definitiva.');
    }

    /** Guarda de reentrância: o sync é disparado por quatro caminhos diferentes. */
    public function test_o_sync_tem_guarda_de_reentrancia(): void
    {
        $this->assertStringContainsString(
            'if (state.syncing) return;',
            $this->motor(),
            'Sem guarda, duas execuções lêem a mesma fila antes de qualquer uma a esvaziar.'
        );
    }

    /** Idempotência: cada venda leva um identificador local, imutável. */
    public function test_as_vendas_levam_identificador_local(): void
    {
        $motor = $this->motor();

        $this->assertStringContainsString('local_uuid', $motor);
        $this->assertMatchesRegularExpression(
            '/enqueue\(\s*[\'"]create_pos_sale[\'"],\s*\{\s*\n\s*local_uuid/s',
            $motor,
            'A venda POS tem de ir com o local_uuid — é o que impede duplicados no reenvio.'
        );
    }

    /** E o servidor honra-o: o mesmo uuid devolve o mesmo documento. */
    public function test_o_servidor_e_idempotente_pelo_identificador_local(): void
    {
        $controlador = file_get_contents(app_path('Http/Controllers/Api/Invoicing/PosSaleController.php'));

        $this->assertStringContainsString("'local_uuid'          => 'required", $controlador,
            'O local_uuid tem de ser obrigatório: sem ele não há idempotência.');
        $this->assertStringContainsString('local_uuid', $controlador);
    }

    /** O fecho de turno só depois de tudo sincronizado — senão fica de fora do fecho. */
    public function test_o_fecho_de_turno_espera_pelas_vendas(): void
    {
        $this->assertStringContainsString(
            "where('_synced').equals(0).count()",
            $this->motor(),
            'O fecho de turno tem de esperar pelas vendas por sincronizar.'
        );
    }
}
