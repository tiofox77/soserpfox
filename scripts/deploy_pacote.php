<?php

/**
 * MONTA O PACOTE DE DEPLOY — só com o que difere da produção.
 *
 *   php scripts/deploy_pacote.php --versao=2026.09.13.1 [--base=main] [--sem-servidor]
 *
 * 1. Candidatos: os ficheiros do git dentro das raízes que um deploy pode tocar
 *    (App\Support\Deploy\Pacote::caminhoPermitido) e os pacotes construídos
 *    (public/build, public/react, public/pwa-app — sem os .map, que expõem a
 *    fonte). Construa antes: npm run build && npm run react:build.
 * 2. Pergunta à produção (rota verify-files, só leitura) quais já estão iguais
 *    e deixa-os de fora.
 * 3. A lista de apagar: o que saiu do git desde --base e ainda existe no
 *    servidor.
 * 4. Avisa dos ficheiros em que a produção difere de --base sem este ramo lhes
 *    ter tocado — são mãos no servidor ou deploys que nunca chegaram, e o
 *    pacote vai escrevê-los por cima.
 *
 * Escreve storage/app/deploy/pacotes/soserp-<versao>.zip e um relatório ao lado.
 * O zip sobe por FTP e aplica-se no servidor: deploy:instantaneo → deploy:aplicar.
 */

require __DIR__ . '/../app/Support/Deploy/Pacote.php';

use App\Support\Deploy\Pacote;

$raizProjecto = realpath(__DIR__ . '/..');
chdir($raizProjecto);

$opcoes = getopt('', ['versao:', 'base::', 'sem-servidor']);
$versao = $opcoes['versao'] ?? null;
$ramoBase = $opcoes['base'] ?? 'main';
$semServidor = array_key_exists('sem-servidor', $opcoes);

if (! $versao || ! preg_match('/^[0-9A-Za-z.-]+$/', $versao)) {
    fwrite(STDERR, "Uso: php scripts/deploy_pacote.php --versao=2026.09.13.1 [--base=main] [--sem-servidor]\n");
    exit(1);
}

$git = fn (string $args) => array_values(array_filter(array_map('trim', explode("\n", (string) shell_exec("git {$args}")))));

if ($git('status --porcelain --untracked-files=no')) {
    fwrite(STDERR, "AVISO: há alterações por commitar — o pacote leva o que está no disco.\n");
}

$commit = trim((string) shell_exec('git rev-parse --short HEAD'));

// ── 1. candidatos ─────────────────────────────────────────────────────────
// As fontes TypeScript não correm no servidor: vai o que o Vite construiu.
$candidatos = array_values(array_filter(
    $git('ls-files'),
    fn ($f) => Pacote::caminhoPermitido($f) && is_file($f) && ! str_starts_with($f, 'resources/js/'),
));

foreach (['public/build', 'public/react', 'public/pwa-app'] as $construido) {
    if (! is_dir($construido)) {
        fwrite(STDERR, "FALTA {$construido} — construa antes (npm run build && npm run react:build).\n");
        exit(1);
    }

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($construido, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $f) {
        $relativo = str_replace('\\', '/', $f->getPathname());

        if (! str_ends_with($relativo, '.map')) {
            $candidatos[] = $relativo;
        }
    }
}

$candidatos = array_values(array_unique($candidatos));
sort($candidatos);

$apagarCandidatos = array_values(array_filter(
    $git("diff --name-only --diff-filter=D {$ramoBase}..HEAD"),
    fn ($f) => Pacote::caminhoPermitido($f),
));

$mudadosNoRamo = array_flip($git("diff --name-only {$ramoBase}..HEAD"));

// ── 2. o que a produção já tem ────────────────────────────────────────────
// A verify-files só compara estas raízes; o resto de public/ vai se mudou no
// ramo, e os pacotes construídos vão sempre (nomes com hash).
$verificaveis = ['app/', 'bootstrap/', 'resources/', 'lang/', 'database/', 'routes/', 'config/', 'public/js/', 'public/css/'];
$soltosVerificaveis = ['artisan', 'composer.json', 'composer.lock', 'public/index.php', 'public/.htaccess', 'public/robots.txt'];
$eVerificavel = function (string $f) use ($verificaveis, $soltosVerificaveis): bool {
    if (in_array($f, $soltosVerificaveis, true)) {
        return true;
    }
    foreach ($verificaveis as $raiz) {
        if (str_starts_with($f, $raiz)) {
            return true;
        }
    }

    return false;
};

$ficheiros = [];
$iguais = 0;
$deriva = [];
$apagar = [];

if ($semServidor) {
    $ficheiros = $candidatos;
    $apagar = $apagarCandidatos;
} else {
    $config = (string) file_get_contents('config/maintenance.php');
    preg_match("/env\(\s*'MAINTENANCE_TOKEN'\s*,\s*'([^']+)'\s*\)/", $config, $m);
    $token = $m[1] ?? '';

    $perguntar = function (array $manifesto) use ($token): array {
        $respostas = ['ausentes' => [], 'diferem' => []];

        foreach (array_chunk($manifesto, 4000, true) as $lote) {
            $corpo = json_encode(['manifest' => $lote]);
            $ch = curl_init("https://soserp.vip/maintenance/{$token}/verify-files");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $corpo,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 300,
            ]);
            $resposta = curl_exec($ch);
            $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $erro = curl_error($ch);
            curl_close($ch);

            $json = is_string($resposta) ? json_decode($resposta, true) : null;

            if ($codigo !== 200 || ! is_array($json)) {
                fwrite(STDERR, "A produção não respondeu à verify-files (HTTP {$codigo}) {$erro}\n");
                exit(1);
            }

            $respostas['ausentes'] = array_merge($respostas['ausentes'], $json['ausentes']);
            $respostas['diferem'] = array_merge($respostas['diferem'], array_column($json['diferem'], 'ficheiro'));
        }

        return $respostas;
    };

    $manifesto = [];

    foreach ($candidatos as $f) {
        if ($eVerificavel($f)) {
            $manifesto[$f] = filesize($f) . ':' . md5_file($f);
        } elseif (str_starts_with($f, 'public/build/') || str_starts_with($f, 'public/react/') || str_starts_with($f, 'public/pwa-app/') || isset($mudadosNoRamo[$f])) {
            $ficheiros[] = $f;
        }
    }

    $r = $perguntar($manifesto);
    $precisam = array_flip(array_merge($r['ausentes'], $r['diferem']));

    foreach (array_keys($manifesto) as $f) {
        if (isset($precisam[$f])) {
            $ficheiros[] = $f;

            if (in_array($f, $r['diferem'], true) && ! isset($mudadosNoRamo[$f])) {
                $deriva[] = $f;
            }
        } else {
            $iguais++;
        }
    }

    // Os que saíram do git: só vão para a lista se ainda existem lá.
    $manifestoApagar = [];
    foreach ($apagarCandidatos as $f) {
        if ($eVerificavel($f)) {
            $manifestoApagar[$f] = '0:0';
        } else {
            $apagar[] = $f;
        }
    }

    if ($manifestoApagar) {
        $ausentesLa = array_flip($perguntar($manifestoApagar)['ausentes']);
        foreach (array_keys($manifestoApagar) as $f) {
            if (! isset($ausentesLa[$f])) {
                $apagar[] = $f;
            }
        }
    }
}

sort($ficheiros);
sort($apagar);

// ── 3. o zip ──────────────────────────────────────────────────────────────
$pasta = 'storage/app/deploy/pacotes';
@mkdir($pasta, 0777, true);
$nome = "soserp-{$versao}.zip";
$caminho = "{$pasta}/{$nome}";

$zip = new ZipArchive();

if ($zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Não foi possível criar {$caminho}\n");
    exit(1);
}

foreach ($ficheiros as $f) {
    $zip->addFile($f, $f);
}

$zip->addFromString(Pacote::LISTA_DE_APAGAR, implode("\n", $apagar) . "\n");
$zip->addFromString('_deploy/versao.json', json_encode([
    'versao' => $versao,
    'commit' => $commit,
    'base' => $ramoBase,
    'gerado_em' => date('c'),
    'ficheiros' => count($ficheiros),
    'apagar' => count($apagar),
], JSON_PRETTY_PRINT));
$zip->close();

// Confirma o que se escreveu antes de o dar por bom.
$lido = Pacote::ler($caminho);

$relatorio = [
    'pacote' => $nome,
    'sha256' => hash_file('sha256', $caminho),
    'tamanho_mb' => round(filesize($caminho) / 1048576, 2),
    'commit' => $commit,
    'ficheiros' => count($lido['ficheiros']),
    'iguais_na_producao' => $iguais,
    'apagar' => $apagar,
    'producao_difere_de_' . $ramoBase => $deriva,
];

file_put_contents("{$pasta}/soserp-{$versao}.json", json_encode($relatorio, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo "Pacote {$caminho} ({$relatorio['tamanho_mb']} MB)\n";
echo "  a escrever: {$relatorio['ficheiros']}   já iguais na produção: {$iguais}   a apagar: " . count($apagar) . "\n";
echo "  sha256: {$relatorio['sha256']}\n";

if ($deriva) {
    echo "\nATENÇÃO — a produção difere de {$ramoBase} em " . count($deriva) . " ficheiros que este ramo não mudou (o pacote escreve-os por cima):\n";
    foreach (array_slice($deriva, 0, 60) as $f) {
        echo "  {$f}\n";
    }
}
