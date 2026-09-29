<?php

namespace Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * AS CLASSES TÊM DE CARREGAR NO LINUX, NÃO SÓ NO WINDOWS.
 *
 * O `PedidosDeRh` vivia em app/Services/HR/ mas dizia `namespace App\Services\Hr`.
 * No Windows (desenvolvimento) as maiúsculas não contam e tudo carregava; no
 * servidor o autoloader procurava app/Services/Hr/PedidosDeRh.php, que não
 * existe, e o ecrã de pedidos de RH rebentava com «Class not found» — 28 vezes
 * numa farmácia entre 26 e 29/09/2026, sem nenhum ensaio dar por isso.
 *
 * Aqui compara-se letra a letra, com os nomes das pastas tal como estão no
 * disco, que é o que o Linux vê.
 */
class ClassesCarregamNoLinuxTest extends TestCase
{
    /** @var array<string,true> caminhos relativos exactos de tudo o que está em app/ */
    private static array $ficheiros = [];

    private function raiz(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 3));
    }

    /** @return array<string,true> */
    private function ficheirosDeApp(): array
    {
        if (self::$ficheiros) {
            return self::$ficheiros;
        }

        $raiz = $this->raiz();
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/app', RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                self::$ficheiros[substr(str_replace('\\', '/', $f->getPathname()), strlen($raiz) + 1)] = true;
            }
        }

        return self::$ficheiros;
    }

    public function test_cada_classe_esta_na_pasta_com_a_grafia_do_namespace(): void
    {
        $desalinhadas = [];

        foreach (array_keys($this->ficheirosDeApp()) as $rel) {
            $src = file_get_contents($this->raiz() . '/' . $rel);
            if (! preg_match('/^namespace\s+([^;]+);/m', $src, $ns)
                || ! preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $src, $classe)) {
                continue;
            }

            $esperado = 'app/' . str_replace('\\', '/', substr($ns[1], strlen('App\\'))) . '/' . $classe[1] . '.php';
            if ($esperado !== $rel) {
                $desalinhadas[] = "$rel declara {$ns[1]}\\{$classe[1]}";
            }
        }

        $this->assertSame([], $desalinhadas, 'no Linux estas classes não carregam');
    }

    public function test_as_referencias_a_app_usam_a_grafia_das_pastas(): void
    {
        $exactos = $this->ficheirosDeApp();
        $porMinusculas = [];
        foreach (array_keys($exactos) as $rel) {
            $porMinusculas[strtolower($rel)] = $rel;
        }

        $raiz = $this->raiz();
        $trocadas = [];
        foreach (['app', 'routes', 'config', 'database', 'bootstrap'] as $pasta) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/' . $pasta, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.php')) {
                    continue;
                }

                preg_match_all('/\bApp((?:\\\\[A-Za-z_]\w*)+)/', file_get_contents($f->getPathname()), $refs);
                foreach (array_unique($refs[1]) as $cauda) {
                    $caminho = 'app/' . str_replace('\\', '/', ltrim($cauda, '\\')) . '.php';
                    // Só interessa o que existe com OUTRA grafia: o resto é
                    // App\X::metodo, funções, ou texto que não é classe.
                    if (! isset($exactos[$caminho]) && isset($porMinusculas[strtolower($caminho)])) {
                        $onde = substr(str_replace('\\', '/', $f->getPathname()), strlen($raiz) + 1);
                        $trocadas[] = "$onde: App$cauda (a pasta é {$porMinusculas[strtolower($caminho)]})";
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($trocadas)), 'no Linux estas referências não carregam');
    }
}
