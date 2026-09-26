<?php

namespace App\Support\Deploy;

use RuntimeException;
use ZipArchive;

/**
 * O PACOTE DE UM DEPLOY, O INSTANTÂNEO DE ANTES E O RESTAURO.
 *
 * O deploy era ficheiro a ficheiro por FTP: centenas de pedidos, minutos com a
 * produção meio velha meio nova, e nenhuma forma de voltar atrás além de
 * lembrar o que se tinha enviado. Passa a ser assim:
 *
 *   1. PACOTE — um zip com os ficheiros novos e a lista dos que saem
 *      (`_deploy/apagar.txt`). Sobe inteiro, num envio só.
 *   2. INSTANTÂNEO — antes de tocar em nada, guarda-se a versão actual de
 *      CADA ficheiro que o pacote vai escrever ou apagar, e anota-se os que
 *      ainda não existem. É exactamente o que é preciso para desfazer.
 *   3. APLICAR — escreve e apaga, no servidor, em segundos.
 *   4. RESTAURAR — repõe o que o instantâneo guardou e apaga o que o pacote
 *      criou. A produção volta a ser byte a byte a de antes.
 *
 * SEM NADA DO LARAVEL, de propósito: o restauro de emergência
 * (bootstrap/restauro-de-emergencia.php) carrega este ficheiro à mão, quando
 * um deploy deixou o Laravel sem arrancar e as rotas de manutenção morreram
 * com ele.
 */
final class Pacote
{
    public const PASTA_DE_CONTROLO = '_deploy/';

    public const LISTA_DE_APAGAR = '_deploy/apagar.txt';

    /** Só se escreve dentro destas pastas… */
    public const RAIZES = ['app/', 'bootstrap/', 'config/', 'database/', 'lang/', 'public/', 'resources/', 'routes/'];

    /** …e nestes ficheiros soltos da raiz. */
    public const SOLTOS = ['artisan', 'composer.json', 'composer.lock'];

    /**
     * Nunca: as caches (reconstroem-se), os ficheiros das empresas e o que o
     * servidor tem de seu. O `.env` e o `vendor/` já ficam fora das raízes.
     */
    private const PROIBIDOS = ['bootstrap/cache/', 'public/storage/', 'public/.well-known/', 'public/uploads/'];

    public static function caminhoPermitido(string $relativo): bool
    {
        $r = str_replace('\\', '/', $relativo);

        if ($r === '' || str_contains($r, "\0") || str_starts_with($r, '/') || preg_match('#^[A-Za-z]:#', $r)) {
            return false;
        }

        foreach (explode('/', $r) as $parte) {
            if ($parte === '' || $parte === '.' || $parte === '..') {
                return false;
            }
        }

        if (preg_match('#(^|/)\.env#i', $r)) {
            return false;
        }

        foreach (self::PROIBIDOS as $proibido) {
            if (str_starts_with($r, $proibido)) {
                return false;
            }
        }

        if (in_array($r, self::SOLTOS, true)) {
            return true;
        }

        foreach (self::RAIZES as $raiz) {
            if (str_starts_with($r, $raiz)) {
                return true;
            }
        }

        return false;
    }

    /**
     * O que o pacote escreve e o que apaga. Recusa o pacote INTEIRO se um só
     * caminho sair das raízes — nunca se aplica metade.
     *
     * @return array{ficheiros: list<string>, apagar: list<string>}
     */
    public static function ler(string $pacote): array
    {
        $zip = self::abrir($pacote);
        $ficheiros = [];
        $apagar = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nome = (string) $zip->getNameIndex($i);

            if (str_ends_with($nome, '/')) {
                continue;
            }

            if ($nome === self::LISTA_DE_APAGAR) {
                foreach (preg_split('/\R/', (string) $zip->getFromIndex($i)) as $linha) {
                    $linha = trim($linha);
                    if ($linha !== '' && ! str_starts_with($linha, '#')) {
                        $apagar[] = str_replace('\\', '/', $linha);
                    }
                }

                continue;
            }

            if (str_starts_with($nome, self::PASTA_DE_CONTROLO)) {
                continue;
            }

            $ficheiros[] = $nome;
        }

        $zip->close();

        $apagar = array_values(array_unique($apagar));
        $recusados = array_values(array_filter(array_merge($ficheiros, $apagar), fn ($c) => ! self::caminhoPermitido($c)));

        if ($recusados) {
            throw new RuntimeException('O pacote traz caminhos que um deploy não pode tocar: ' . implode(', ', array_slice($recusados, 0, 10)));
        }

        $emAmbas = array_intersect($ficheiros, $apagar);

        if ($emAmbas) {
            throw new RuntimeException('O pacote escreve e apaga o mesmo ficheiro: ' . implode(', ', array_slice($emAmbas, 0, 10)));
        }

        return ['ficheiros' => $ficheiros, 'apagar' => $apagar];
    }

    /**
     * Guarda a versão actual de tudo o que o pacote vai tocar.
     *
     * @return array<string, mixed> o manifesto
     */
    public static function instantaneo(string $base, string $pacote, string $pasta): array
    {
        $conteudo = self::ler($pacote);

        if (is_file($pasta . '/manifesto.json')) {
            throw new RuntimeException('Já existe um instantâneo em ' . $pasta);
        }

        if (! is_dir($pasta) && ! @mkdir($pasta, 0750, true) && ! is_dir($pasta)) {
            throw new RuntimeException('Não foi possível criar a pasta do instantâneo: ' . $pasta);
        }

        $escreve = array_flip($conteudo['ficheiros']);
        $guardados = [];
        $criados = [];

        $zip = new ZipArchive();

        if ($zip->open($pasta . '/codigo.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar ' . $pasta . '/codigo.zip');
        }

        foreach (array_merge($conteudo['ficheiros'], $conteudo['apagar']) as $relativo) {
            $absoluto = $base . '/' . $relativo;

            if (is_file($absoluto)) {
                $dados = file_get_contents($absoluto);

                if ($dados === false) {
                    $zip->close();
                    throw new RuntimeException('Não foi possível ler ' . $relativo);
                }

                $zip->addFromString($relativo, $dados);
                $guardados[$relativo] = hash('sha256', $dados);
            } elseif (isset($escreve[$relativo])) {
                $criados[] = $relativo;
            }
        }

        // Um zip sem entradas não chega a ser escrito no disco.
        $zip->addFromString(self::PASTA_DE_CONTROLO . 'LEIA-ME.txt', "Instantâneo tirado antes do pacote " . basename($pacote) . ".\n");

        if (! $zip->close()) {
            throw new RuntimeException('O zip do instantâneo não ficou gravado.');
        }

        $manifesto = [
            'pacote' => basename($pacote),
            'pacote_sha256' => hash_file('sha256', $pacote),
            'criado_em' => date('c'),
            'guardados' => $guardados,
            'criados' => $criados,
            'apagar' => $conteudo['apagar'],
            'aplicado_em' => null,
            'restaurado_em' => null,
        ];

        self::verificarCopia($pasta, $manifesto);
        self::gravarManifesto($pasta, $manifesto);

        return $manifesto;
    }

    /**
     * Aplica o pacote — só sobre o instantâneo que foi tirado PARA ELE, e só
     * se nada mudou desde então (senão o restauro já não devolvia o estado de
     * agora).
     *
     * @return array{escritos: int, apagados: int}
     */
    public static function aplicar(string $base, string $pacote, string $pasta): array
    {
        $manifesto = self::manifesto($pasta);

        if (($manifesto['pacote_sha256'] ?? '') !== hash_file('sha256', $pacote)) {
            throw new RuntimeException('Este instantâneo não foi tirado para este pacote.');
        }

        if (! empty($manifesto['aplicado_em'])) {
            throw new RuntimeException('Este pacote já foi aplicado sobre este instantâneo. Tire um instantâneo novo.');
        }

        foreach ($manifesto['guardados'] as $relativo => $sha) {
            $absoluto = $base . '/' . $relativo;

            if (! is_file($absoluto) || hash_file('sha256', $absoluto) !== $sha) {
                throw new RuntimeException("O ficheiro {$relativo} mudou depois do instantâneo. Tire um instantâneo novo.");
            }
        }

        foreach ($manifesto['criados'] as $relativo) {
            if (is_file($base . '/' . $relativo)) {
                throw new RuntimeException("O ficheiro {$relativo} apareceu depois do instantâneo. Tire um instantâneo novo.");
            }
        }

        $conteudo = self::ler($pacote);

        // Marca-se ANTES: um aplicar que rebente a meio fica registado, e o
        // restauro sabe que há o que desfazer.
        $manifesto['aplicado_em'] = date('c');
        self::gravarManifesto($pasta, $manifesto);

        $zip = self::abrir($pacote);
        $escritos = 0;

        try {
            // OS MANIFESTOS DOS PACOTES REACT/PWA VÃO NO FIM (26/09/2026). Por
            // ordem alfabética, `public/react/.vite/manifest.json` escrevia-se
            // antes dos pedaços: nesse intervalo a página apontava para
            // ficheiros que ainda não existiam. Os pedaços primeiro, e o
            // manifesto — que é o que os torna visíveis — por último.
            foreach (self::manifestosNoFim($conteudo['ficheiros']) as $relativo) {
                $dados = $zip->getFromName($relativo);

                if ($dados === false) {
                    throw new RuntimeException('Não foi possível ler ' . $relativo . ' do pacote.');
                }

                self::escrever($base . '/' . $relativo, $dados);
                $escritos++;
            }
        } finally {
            $zip->close();
        }

        $apagados = 0;

        foreach ($conteudo['apagar'] as $relativo) {
            $absoluto = $base . '/' . $relativo;

            if (is_file($absoluto)) {
                if (! @unlink($absoluto)) {
                    throw new RuntimeException('Não foi possível apagar ' . $relativo);
                }
                $apagados++;
            }
        }

        self::limparCaches($base);

        return ['escritos' => $escritos, 'apagados' => $apagados];
    }

    /**
     * Volta ao estado do instantâneo: repõe o que guardou, apaga o que o
     * pacote criou. Confere a cópia inteira ANTES de escrever o primeiro
     * ficheiro — uma cópia estragada não chega a meio da produção.
     *
     * @return array{repostos: int, apagados: int, a_seco: bool}
     */
    public static function restaurar(string $base, string $pasta, bool $aSeco = false): array
    {
        $manifesto = self::manifesto($pasta);
        self::verificarCopia($pasta, $manifesto);

        $criadosPresentes = array_values(array_filter(
            $manifesto['criados'],
            fn ($r) => self::caminhoPermitido($r) && is_file($base . '/' . $r),
        ));

        if ($aSeco) {
            return ['repostos' => count($manifesto['guardados']), 'apagados' => count($criadosPresentes), 'a_seco' => true];
        }

        $zip = self::abrir($pasta . '/codigo.zip');
        $repostos = 0;

        try {
            foreach (array_keys($manifesto['guardados']) as $relativo) {
                self::escrever($base . '/' . $relativo, (string) $zip->getFromName($relativo));
                $repostos++;
            }
        } finally {
            $zip->close();
        }

        $apagados = 0;

        foreach ($criadosPresentes as $relativo) {
            if (@unlink($base . '/' . $relativo)) {
                $apagados++;
            }
        }

        self::limparCaches($base);

        $manifesto['restaurado_em'] = date('c');
        self::gravarManifesto($pasta, $manifesto);

        return ['repostos' => $repostos, 'apagados' => $apagados, 'a_seco' => false];
    }

    /** @return array<string, mixed> */
    public static function manifesto(string $pasta): array
    {
        $caminho = $pasta . '/manifesto.json';

        if (! is_file($caminho)) {
            throw new RuntimeException('Não há instantâneo em ' . $pasta);
        }

        $manifesto = json_decode((string) file_get_contents($caminho), true);

        if (! is_array($manifesto) || ! isset($manifesto['guardados'], $manifesto['criados'])) {
            throw new RuntimeException('O manifesto do instantâneo está ilegível.');
        }

        foreach (array_merge(array_keys($manifesto['guardados']), $manifesto['criados']) as $relativo) {
            if (! self::caminhoPermitido((string) $relativo)) {
                throw new RuntimeException('O manifesto traz um caminho que o restauro não pode tocar: ' . $relativo);
            }
        }

        return $manifesto;
    }

    /**
     * As caches de rotas, de configuração e de eventos são fotografias do
     * código: depois de o trocar, apontavam para classes que já não existem
     * (ou deixavam de fora as novas). Apagá-las é seguro — o Laravel lê os
     * ficheiros até se voltarem a construir.
     */
    public static function limparCaches(string $base): void
    {
        foreach (['config', 'routes-v7', 'routes', 'events'] as $cache) {
            @unlink($base . '/bootstrap/cache/' . $cache . '.php');
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /** @param array<string, mixed> $manifesto */
    private static function verificarCopia(string $pasta, array $manifesto): void
    {
        $zip = self::abrir($pasta . '/codigo.zip');

        try {
            foreach ($manifesto['guardados'] as $relativo => $sha) {
                $dados = $zip->getFromName($relativo);

                if ($dados === false || hash('sha256', $dados) !== $sha) {
                    throw new RuntimeException("A cópia de {$relativo} no instantâneo está estragada — não se restaura nada.");
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * A ordem de escrita: tudo o resto primeiro, os manifestos dos pacotes do
     * browser (`.vite/manifest.json`) por último — são eles que tornam os
     * pedaços novos visíveis.
     *
     * @param  list<string>  $ficheiros
     * @return list<string>
     */
    public static function manifestosNoFim(array $ficheiros): array
    {
        $eManifesto = fn (string $f) => str_ends_with(str_replace('\\', '/', $f), '/.vite/manifest.json');

        return array_values(array_merge(
            array_filter($ficheiros, fn ($f) => ! $eManifesto($f)),
            array_filter($ficheiros, $eManifesto),
        ));
    }

    private static function escrever(string $absoluto, string $dados): void
    {
        $pasta = dirname($absoluto);

        if (! is_dir($pasta) && ! @mkdir($pasta, 0755, true) && ! is_dir($pasta)) {
            throw new RuntimeException('Não foi possível criar a pasta ' . $pasta);
        }

        // Escreve ao lado e troca: um pedido que chegue a meio nunca lê um
        // ficheiro PHP cortado ao meio.
        $temporario = $absoluto . '.deploy-' . bin2hex(random_bytes(4));

        if (file_put_contents($temporario, $dados) !== strlen($dados)) {
            @unlink($temporario);
            throw new RuntimeException('Não foi possível escrever ' . $absoluto);
        }

        @chmod($temporario, 0644);

        if (! @rename($temporario, $absoluto)) {
            @unlink($temporario);
            throw new RuntimeException('Não foi possível substituir ' . $absoluto);
        }
    }

    /** @param array<string, mixed> $manifesto */
    private static function gravarManifesto(string $pasta, array $manifesto): void
    {
        $json = json_encode($manifesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false || file_put_contents($pasta . '/manifesto.json', $json) === false) {
            throw new RuntimeException('Não foi possível gravar o manifesto do instantâneo.');
        }
    }

    private static function abrir(string $caminho): ZipArchive
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensão zip do PHP não está disponível.');
        }

        $zip = new ZipArchive();

        if (! is_file($caminho) || $zip->open($caminho) !== true) {
            throw new RuntimeException('Não foi possível abrir ' . $caminho);
        }

        return $zip;
    }
}
