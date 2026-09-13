<?php

/**
 * O RESTAURO DE EMERGÊNCIA — para quando um deploy deixou o Laravel sem arrancar.
 *
 * Um ficheiro de arranque errado (aconteceu com o bootstrap/app.php em
 * 2026-08-27) dá 500 em TODOS os pedidos, e as rotas de manutenção morrem com
 * eles: o `deploy:restaurar` deixa de se poder chamar precisamente quando é
 * preciso. Isto corre ANTES do Laravel (public/index.php encaminha para aqui)
 * e não usa nada dele — nem o autoloader do Composer.
 *
 *   GET /__restauro/{token}                           → lista os instantâneos
 *   GET /__restauro/{token}/{instantaneo}             → diz o que faria
 *   GET /__restauro/{token}/{instantaneo}?confirmar=1 → repõe os ficheiros
 *
 * Só ficheiros. A base de dados repõe-se depois, com o Laravel de volta:
 * deploy:restaurar {instantaneo} --bd --confirmar.
 *
 * O token é o mesmo das rotas de manutenção (MAINTENANCE_TOKEN no .env, ou o
 * de config/maintenance.php). Sem token certo responde 404, como se não
 * existisse.
 */

$base = dirname(__DIR__);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$tokenEsperado = (static function (string $base): string {
    $env = @file_get_contents($base . '/.env');

    if (is_string($env) && preg_match('/^\s*MAINTENANCE_TOKEN\s*=\s*["\']?([^"\'\r\n#]+)/m', $env, $m)) {
        return trim($m[1]);
    }

    $config = @file_get_contents($base . '/config/maintenance.php');

    if (is_string($config) && preg_match("/env\(\s*'MAINTENANCE_TOKEN'\s*,\s*'([^']+)'\s*\)/", $config, $m)) {
        return $m[1];
    }

    return '';
})($base);

$caminho = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$partes = array_values(array_filter(explode('/', $caminho), 'strlen'));
$token = (string) ($partes[1] ?? '');
$nome = (string) ($partes[2] ?? '');

if (strlen($tokenEsperado) < 16 || ! hash_equals($tokenEsperado, $token)) {
    http_response_code(404);
    echo "Not Found\n";

    return;
}

require_once $base . '/app/Support/Deploy/Pacote.php';

$raiz = $base . '/storage/app/deploy/instantaneos';

try {
    if ($nome === '') {
        $pastas = glob($raiz . '/*', GLOB_ONLYDIR) ?: [];
        rsort($pastas);

        echo "INSTANTÂNEOS\n\n";

        foreach ($pastas as $pasta) {
            $m = \App\Support\Deploy\Pacote::manifesto($pasta);
            printf(
                "%s  pacote=%s  guardados=%d  criados=%d  bd=%s  aplicado=%s  restaurado=%s\n",
                basename($pasta),
                $m['pacote'] ?? '-',
                count($m['guardados']),
                count($m['criados']),
                is_file($pasta . '/bd.sql.gz') ? 'sim' : 'não',
                $m['aplicado_em'] ?? '-',
                $m['restaurado_em'] ?? '-',
            );
        }

        if (! $pastas) {
            echo "(nenhum)\n";
        }

        return;
    }

    if (! preg_match('/^\d{8}-\d{6}$/', $nome)) {
        http_response_code(422);
        echo "Nome de instantâneo inválido.\n";

        return;
    }

    $confirmar = ($_GET['confirmar'] ?? '') === '1';
    $resultado = \App\Support\Deploy\Pacote::restaurar($base, $raiz . '/' . $nome, aSeco: ! $confirmar);

    if (! $confirmar) {
        echo "A SECO — nada foi alterado.\n";
        echo "Repunha {$resultado['repostos']} ficheiros e apagava {$resultado['apagados']} criados pelo pacote.\n";
        echo "Para fazer mesmo: acrescente ?confirmar=1\n";

        return;
    }

    echo "RESTAURADO {$nome}\n";
    echo "Ficheiros repostos: {$resultado['repostos']}\n";
    echo "Criados pelo pacote e apagados: {$resultado['apagados']}\n";
    echo "Caches de rotas/configuração apagadas e OPcache reposto.\n";
    echo "A base de dados NÃO foi tocada. Se for preciso: deploy:restaurar {$nome} --bd --confirmar\n";
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'FALHOU: ' . $e->getMessage() . "\n";
}
