<?php

namespace Tests\Unit\Deploy;

use App\Support\Deploy\Pacote;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

/**
 * O DEPLOY POR PACOTE E O CAMINHO DE VOLTA.
 *
 * Tudo numa pasta temporária que faz de produção: nada disto toca no projecto.
 * A prova que importa é a última — depois de aplicar e restaurar, a «produção»
 * é byte a byte a de antes.
 */
class PacoteDeDeployTest extends TestCase
{
    private string $raiz;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raiz = sys_get_temp_dir() . '/pacote-deploy-' . bin2hex(random_bytes(6));
        $this->base = $this->raiz . '/producao';

        $this->escrever('app/Antiga.php', "<?php // versão velha\n");
        $this->escrever('app/Livewire/Morto.php', "<?php // vai sair\n");
        $this->escrever('resources/views/fica.blade.php', "igual\n");
        $this->escrever('routes/web.php', "<?php // rotas velhas\n");
        $this->escrever('bootstrap/cache/routes-v7.php', "<?php return []; // cache velha\n");
        $this->escrever('.env', "APP_KEY=segredo\n");
    }

    protected function tearDown(): void
    {
        $this->apagarPasta($this->raiz);

        parent::tearDown();
    }

    public function test_aplicar_e_restaurar_devolve_a_producao_byte_a_byte(): void
    {
        $antes = $this->fotografia();

        $pacote = $this->pacote([
            'app/Antiga.php' => "<?php // versão nova\n",
            'app/Support/Nova.php' => "<?php // acabada de chegar\n",
            'routes/web.php' => "<?php // rotas novas\n",
            'public/react/app-abc123.js' => 'console.log(1)',
        ], apagar: ['app/Livewire/Morto.php', 'app/NuncaExistiu.php']);

        $pasta = $this->raiz . '/instantaneos/20260913-180000';
        $manifesto = Pacote::instantaneo($this->base, $pacote, $pasta);

        $this->assertSame(['app/Antiga.php', 'routes/web.php', 'app/Livewire/Morto.php'], array_keys($manifesto['guardados']));
        $this->assertSame(['app/Support/Nova.php', 'public/react/app-abc123.js'], $manifesto['criados']);
        $this->assertSame($antes, $this->fotografia(), 'o instantâneo não muda nada');

        $r = Pacote::aplicar($this->base, $pacote, $pasta);

        $this->assertSame(['escritos' => 4, 'apagados' => 1], $r);
        $this->assertStringContainsString('versão nova', file_get_contents($this->base . '/app/Antiga.php'));
        $this->assertFileExists($this->base . '/public/react/app-abc123.js');
        $this->assertFileDoesNotExist($this->base . '/app/Livewire/Morto.php');
        $this->assertFileDoesNotExist($this->base . '/bootstrap/cache/routes-v7.php', 'a cache de rotas apontava para o código velho');

        $aSeco = Pacote::restaurar($this->base, $pasta, aSeco: true);
        $this->assertSame(['repostos' => 3, 'apagados' => 2, 'a_seco' => true], $aSeco);
        $this->assertFileExists($this->base . '/app/Support/Nova.php', 'a seco não mexe');

        Pacote::restaurar($this->base, $pasta);

        $depois = $this->fotografia();
        unset($antes['bootstrap/cache/routes-v7.php']);
        $this->assertSame($antes, $depois, 'restaurar devolve exactamente o que havia');
        $this->assertNotNull(Pacote::manifesto($pasta)['restaurado_em']);
    }

    public function test_aplicar_recusa_sem_o_instantaneo_do_proprio_pacote(): void
    {
        $pacote = $this->pacote(['app/Antiga.php' => 'nova']);
        $outro = $this->pacote(['app/Antiga.php' => 'outra'], nome: 'outro.zip');
        $pasta = $this->raiz . '/instantaneos/20260913-180000';

        Pacote::instantaneo($this->base, $outro, $pasta);

        $this->expectExceptionMessage('não foi tirado para este pacote');
        Pacote::aplicar($this->base, $pacote, $pasta);
    }

    public function test_aplicar_recusa_se_a_producao_mudou_depois_do_instantaneo(): void
    {
        $pacote = $this->pacote(['app/Antiga.php' => 'nova']);
        $pasta = $this->raiz . '/instantaneos/20260913-180000';
        Pacote::instantaneo($this->base, $pacote, $pasta);

        // Alguém enviou uma correcção por FTP entretanto: o instantâneo já
        // não a guardava, e o restauro apagá-la-ia.
        $this->escrever('app/Antiga.php', '<?php // correcção à mão');

        try {
            Pacote::aplicar($this->base, $pacote, $pasta);
            $this->fail('aplicou sobre um instantâneo desactualizado');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('mudou depois do instantâneo', $e->getMessage());
        }

        $this->assertSame('<?php // correcção à mão', file_get_contents($this->base . '/app/Antiga.php'));
    }

    public function test_nao_se_aplica_duas_vezes_sobre_o_mesmo_instantaneo(): void
    {
        $pacote = $this->pacote(['app/Antiga.php' => 'nova']);
        $pasta = $this->raiz . '/instantaneos/20260913-180000';
        Pacote::instantaneo($this->base, $pacote, $pasta);
        Pacote::aplicar($this->base, $pacote, $pasta);

        $this->expectExceptionMessage('já foi aplicado');
        Pacote::aplicar($this->base, $pacote, $pasta);
    }

    /** @dataProvider caminhosProibidos */
    public function test_um_caminho_fora_das_raizes_recusa_o_pacote_inteiro(string $caminho): void
    {
        $pacote = $this->pacote(['app/Antiga.php' => 'nova', $caminho => 'mau']);

        try {
            Pacote::instantaneo($this->base, $pacote, $this->raiz . '/instantaneos/20260913-180000');
            $this->fail("aceitou {$caminho}");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('não pode tocar', $e->getMessage());
        }

        $this->assertSame("<?php // versão velha\n", file_get_contents($this->base . '/app/Antiga.php'));
    }

    public static function caminhosProibidos(): array
    {
        return [
            'o .env' => ['.env'],
            'um .env escondido numa raiz' => ['config/.env.production'],
            'subir na árvore' => ['app/../../fora.php'],
            'o vendor' => ['vendor/autoload.php'],
            'o storage' => ['storage/app/private/x.txt'],
            'as caches' => ['bootstrap/cache/packages.php'],
            'os ficheiros das empresas' => ['public/storage/logo.png'],
            'um caminho absoluto' => ['/etc/passwd'],
        ];
    }

    public function test_a_lista_de_apagar_tambem_so_vale_dentro_das_raizes(): void
    {
        $pacote = $this->pacote(['app/Antiga.php' => 'nova'], apagar: ['.env']);

        $this->expectExceptionMessage('não pode tocar');
        Pacote::ler($pacote);
    }

    public function test_uma_copia_estragada_nao_restaura_nada(): void
    {
        $pacote = $this->pacote(['app/Antiga.php' => 'nova', 'routes/web.php' => 'novas']);
        $pasta = $this->raiz . '/instantaneos/20260913-180000';
        Pacote::instantaneo($this->base, $pacote, $pasta);
        Pacote::aplicar($this->base, $pacote, $pasta);

        $zip = new ZipArchive();
        $zip->open($pasta . '/codigo.zip');
        $zip->addFromString('routes/web.php', 'estragado');
        $zip->close();

        try {
            Pacote::restaurar($this->base, $pasta);
            $this->fail('restaurou de uma cópia estragada');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('estragada', $e->getMessage());
        }

        $this->assertSame('nova', file_get_contents($this->base . '/app/Antiga.php'), 'não repôs nem o primeiro ficheiro');
    }

    public function test_o_restauro_de_emergencia_corre_sem_laravel(): void
    {
        // Uma produção de mentira com o script e o Pacote nos seus sítios.
        $this->escrever('bootstrap/restauro-de-emergencia.php', file_get_contents(dirname(__DIR__, 3) . '/bootstrap/restauro-de-emergencia.php'));
        $this->escrever('app/Support/Deploy/Pacote.php', file_get_contents(dirname(__DIR__, 3) . '/app/Support/Deploy/Pacote.php'));
        $token = str_repeat('ab', 12);
        $this->escrever('.env', "APP_KEY=segredo\nMAINTENANCE_TOKEN={$token}\n");

        $pacote = $this->pacote(['app/Antiga.php' => 'nova', 'app/Support/Nova.php' => 'nova']);
        $pasta = $this->base . '/storage/app/deploy/instantaneos/20260913-180000';
        Pacote::instantaneo($this->base, $pacote, $pasta);
        Pacote::aplicar($this->base, $pacote, $pasta);

        [$codigo, $saida] = $this->pedido("/__restauro/errado{$token}/20260913-180000", ['confirmar' => '1']);
        $this->assertSame(404, $codigo);
        $this->assertSame('nova', file_get_contents($this->base . '/app/Antiga.php'));

        [$codigo, $saida] = $this->pedido("/__restauro/{$token}");
        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('20260913-180000', $saida);

        [, $saida] = $this->pedido("/__restauro/{$token}/20260913-180000");
        $this->assertStringContainsString('A SECO', $saida);
        $this->assertSame('nova', file_get_contents($this->base . '/app/Antiga.php'));

        [$codigo, $saida] = $this->pedido("/__restauro/{$token}/20260913-180000", ['confirmar' => '1']);
        $this->assertSame(200, $codigo, $saida);
        $this->assertStringContainsString('RESTAURADO', $saida);
        $this->assertSame("<?php // versão velha\n", file_get_contents($this->base . '/app/Antiga.php'));
        $this->assertFileDoesNotExist($this->base . '/app/Support/Nova.php');
    }

    /** @return array{0: int, 1: string} */
    private function pedido(string $uri, array $get = []): array
    {
        $script = $this->base . '/bootstrap/restauro-de-emergencia.php';
        $invocador = $this->raiz . '/pedido.php';
        file_put_contents($invocador, '<?php $_SERVER["REQUEST_URI"] = ' . var_export($uri, true) . '; $_GET = ' . var_export($get, true) . ';'
            . ' ob_start(); require ' . var_export($script, true) . '; $s = ob_get_clean(); echo (http_response_code() ?: 200), "\n", $s;');

        $saida = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($invocador) . ' 2>&1');
        [$codigo, $corpo] = array_pad(explode("\n", $saida, 2), 2, '');

        return [(int) $codigo, $corpo];
    }

    /** @param array<string, string> $ficheiros */
    private function pacote(array $ficheiros, array $apagar = [], string $nome = 'pacote.zip'): string
    {
        @mkdir($this->raiz . '/pacotes', 0777, true);
        $caminho = $this->raiz . '/pacotes/' . $nome;

        $zip = new ZipArchive();
        $zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($ficheiros as $relativo => $conteudo) {
            $zip->addFromString($relativo, $conteudo);
        }

        if ($apagar) {
            $zip->addFromString(Pacote::LISTA_DE_APAGAR, implode("\n", $apagar) . "\n");
        }

        $zip->addFromString('_deploy/versao.json', '{"versao":"teste"}');
        $zip->close();

        return $caminho;
    }

    private function escrever(string $relativo, string $conteudo): void
    {
        $caminho = $this->base . '/' . $relativo;
        @mkdir(dirname($caminho), 0777, true);
        file_put_contents($caminho, $conteudo);
    }

    /** @return array<string, string> caminho => sha1, sem o que o próprio deploy guarda */
    private function fotografia(): array
    {
        $mapa = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $f) {
            $relativo = str_replace('\\', '/', substr($f->getPathname(), strlen($this->base) + 1));

            if (str_starts_with($relativo, 'storage/')) {
                continue;
            }

            $mapa[$relativo] = sha1_file($f->getPathname());
        }

        ksort($mapa);

        return $mapa;
    }

    private function apagarPasta(string $pasta): void
    {
        if (! is_dir($pasta)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pasta, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }

        @rmdir($pasta);
    }

    /** O manifesto do pacote React escreve-se depois dos pedaços que ele aponta (26/09/2026). */
    public function test_os_manifestos_do_browser_escrevem_se_no_fim(): void
    {
        $ordem = \App\Support\Deploy\Pacote::manifestosNoFim([
            'app/Models/User.php',
            'public/react/.vite/manifest.json',
            'public/react/app-AAA.js',
            'public/pwa-app/.vite/manifest.json',
            'public/react/pedacos/Assistente-BBB.js',
        ]);

        $this->assertSame([
            'app/Models/User.php',
            'public/react/app-AAA.js',
            'public/react/pedacos/Assistente-BBB.js',
            'public/react/.vite/manifest.json',
            'public/pwa-app/.vite/manifest.json',
        ], $ordem);
    }
}
