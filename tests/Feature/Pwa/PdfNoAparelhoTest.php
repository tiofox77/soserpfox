<?php

namespace Tests\Feature\Pwa;

use Tests\TenantTestCase;

/**
 * O PDF no próprio aparelho, para o WhatsApp — o que tem de estar no lugar
 * para funcionar SEM REDE.
 *
 * Duas bibliotecas (html2canvas desenha o HTML de sempre, jsPDF embrulha-o
 * num PDF) servidas pelo próprio domínio e pré-guardadas pelo service
 * worker; o motor com a função de partilha; e os botões nos três ecrãs.
 * Sem qualquer uma destas peças, o botão existe e não faz nada — que é o
 * pior tipo de avaria.
 */
class PdfNoAparelhoTest extends TenantTestCase
{
    private const LIBS = ['/vendor/js/html2canvas.min.js', '/vendor/js/jspdf.umd.min.js'];

    public function test_as_bibliotecas_existem_e_sao_servidas_pelo_proprio_dominio(): void
    {
        foreach (self::LIBS as $lib) {
            $ficheiro = public_path(ltrim($lib, '/'));
            $this->assertFileExists($ficheiro);
            $this->assertGreaterThan(100_000, filesize($ficheiro), "$lib está vazio ou truncado");
        }

        $layout = file_get_contents(resource_path('views/layouts/pwa.blade.php'));
        foreach (self::LIBS as $lib) {
            $this->assertStringContainsString($lib, $layout, "o layout do PWA não carrega $lib");
        }
    }

    /** Sem isto, o primeiro uso sem rede encontra o botão e não o gerador. */
    public function test_o_service_worker_pre_guarda_as_bibliotecas(): void
    {
        preg_match('/const PRECACHE_URLS = \[(.*?)\];/s', file_get_contents(resource_path('pwa/sw.js')), $m);

        foreach (self::LIBS as $lib) {
            $this->assertStringContainsString("'$lib'", $m[1] ?? '', "$lib não está pré-guardado");
        }
    }

    public function test_o_motor_sabe_fazer_e_partilhar_o_pdf(): void
    {
        $motor = file_get_contents(public_path('js/pwa-invoicing.js'));
        $this->assertStringContainsString('async partilharPdf(', $motor);
        $this->assertStringContainsString('async pdfDe(', $motor);
        $this->assertStringContainsString('navigator.canShare', $motor, 'a entrega é a folha de partilha do sistema');
        $this->assertStringContainsString("/pdf'", $motor, 'emitido e com rede, vai buscar o PDF do servidor');

        $talao = file_get_contents(public_path('js/pos-offline-ticket.js'));
        $this->assertStringContainsString('pdfDoTalao(', $talao);
        $this->assertStringContainsString('pdfDoDocumento(', $talao);
        $this->assertStringContainsString('window.html2canvas(', $talao, 'o PDF é o HTML de sempre, desenhado — não um segundo desenho');
    }

    public function test_os_tres_ecras_tem_o_botao(): void
    {
        foreach (['pos', 'drafts', 'draft-form'] as $vista) {
            $html = file_get_contents(resource_path("views/invoicing/offline/{$vista}.blade.php"));
            $this->assertStringContainsString('data-ensaio="partilhar-pdf"', $html, "falta o botão no ecrã {$vista}");
            $this->assertStringContainsString('partilharPdf(', $html);
        }
    }
}
