<?php

namespace Tests\Feature\Pwa;

use Tests\TenantTestCase;

/**
 * As regras da fila offline que não podem ser quebradas.
 *
 * Os testes olham para o CÓDIGO do motor (`resources/js/pwa/motor/*.ts`) porque
 * é JavaScript que corre no telemóvel. O comportamento prova-se a correr, nos
 * ensaios do motor (`resources/js/pwa/motor/sincronizacao.test.ts`, contra uma
 * base IndexedDB verdadeira); isto é a rede de segurança do lado do PHP, para
 * uma regressão não passar calada por quem só corre a suite do servidor.
 */
class FilaOfflineDoPwaTest extends TenantTestCase
{
    private function motor(string $ficheiro = null): string
    {
        if ($ficheiro) {
            return file_get_contents(resource_path("js/pwa/motor/{$ficheiro}.ts"));
        }

        return collect(glob(resource_path('js/pwa/motor/*.ts')))
            ->reject(fn ($f) => str_ends_with($f, '.test.ts'))
            ->map(fn ($f) => file_get_contents($f))
            ->implode("\n");
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
        $this->assertMatchesRegularExpression(
            '/db\.sync_queue\.add\(\{.*?tenant_id/s',
            $this->motor('fila'),
            'Cada trabalho tem de nascer carimbado com a empresa.'
        );
    }

    public function test_a_fila_recusa_enviar_trabalho_de_outra_empresa(): void
    {
        $fila = $this->motor('fila');

        $this->assertStringContainsString('job.tenant_id !== empresaActual', $fila,
            'O envio tem de comparar a empresa do trabalho com a da sessão.');
        $this->assertStringContainsString("status: 'outra_empresa'", $fila,
            'Um trabalho de outra empresa fica retido, com estado próprio.');
    }

    /** Retido não é apagado: apagar seria perder uma venda. */
    public function test_o_trabalho_retido_nao_e_apagado(): void
    {
        preg_match('/if \(job\.tenant_id && empresaActual.*?continue;/s', $this->motor('fila'), $m);

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
        preg_match('/Empresa mudou.*?pwa:tenant-changed/s', $this->motor('sincronizar'), $m);

        $this->assertNotEmpty($m, 'Bloco de troca de empresa não encontrado.');
        $this->assertStringNotContainsString('sync_queue.clear', $m[0],
            'A fila de sincronização nunca pode ser limpa ao trocar de empresa.');
    }

    /** E avisa quem ficou para trás — uma venda parada que ninguém vê perde-se na mesma. */
    public function test_a_troca_de_empresa_avisa_do_que_ficou_retido(): void
    {
        $this->assertStringContainsString('state.retidos = retidos', $this->motor('sincronizar'),
            'A troca de empresa tem de dizer ao ecrã quantas operações ficaram retidas.');
        $this->assertStringContainsString('e.retidos', file_get_contents(resource_path('js/pwa/casca/FaixaDoEstado.tsx')),
            'E a faixa do estado tem de o mostrar.');
    }

    /**
     * Recusa definitiva (4xx) não se repete; transitória fica na fila.
     *
     * Sem isto, uma venda que o servidor recusa por aquilo que ela É seria
     * reenviada para sempre, pondo-se à frente das boas que estão atrás.
     */
    public function test_distingue_recusa_definitiva_de_falha_transitoria(): void
    {
        $rede = $this->motor('rede');

        $this->assertStringContainsString('erro.definitivo = resposta.status >= 400 && resposta.status < 500', $rede);
        $this->assertStringContainsString('![408, 409, 429].includes(resposta.status)', $rede,
            '408 e 429 são «agora não», e o 409 é «o cliente ainda não chegou» — nenhum é «nunca».');
        $this->assertStringContainsString('if (e.definitivo)', $this->motor('fila'), 'A fila tem de agir sobre a recusa definitiva.');
    }

    /** Guarda de reentrância: o sync é disparado por quatro caminhos diferentes. */
    public function test_o_sync_tem_guarda_de_reentrancia(): void
    {
        $this->assertStringContainsString('if (state.syncing || !navigator.onLine) return;', $this->motor('sincronizar'),
            'Sem guarda, duas execuções lêem a mesma fila antes de qualquer uma a esvaziar.');
    }

    /** Idempotência: cada venda leva um identificador local, imutável. */
    public function test_as_vendas_levam_identificador_local(): void
    {
        $this->assertMatchesRegularExpression(
            '/enqueue\(\s*[\'"]create_pos_sale[\'"],\s*\{\s*\n\s*local_uuid/s',
            $this->motor('vendas'),
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
        $this->assertStringContainsString("where('_synced').equals(0).count()", $this->motor('fila'),
            'O fecho de turno tem de esperar pelas vendas por sincronizar.');
    }

    /** E os ensaios que o provam a correr existem — sem eles isto era só ler texto. */
    public function test_os_ensaios_do_motor_existem(): void
    {
        $ensaios = file_get_contents(resource_path('js/pwa/motor/sincronizacao.test.ts'));

        foreach (['OUTRA empresa fica retido', 'não gasta tentativas', 'recusa definitiva', 'uma venda offline conta UMA vez'] as $caso) {
            $this->assertStringContainsStringIgnoringCase($caso, $ensaios, "falta o ensaio «{$caso}»");
        }
    }
}
