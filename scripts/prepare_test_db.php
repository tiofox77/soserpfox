<?php

/**
 * Constrói o esquema da base de testes (soserp_test) a partir da de
 * desenvolvimento (soserp). SÓ ESTRUTURA — nenhum dado é copiado.
 *
 * Porque não `php artisan migrate`: as 272 migrações não correm de raiz. A
 * ordem está partida (p.ex. 2025_01_11_create_hr_departments_table referencia
 * `tenants`, que só é criada em 2025_10_02), pelo que uma instalação limpa
 * falha com "Failed to open the referenced table 'tenants'". Enquanto isso não
 * for corrigido, esta é a forma de ter uma base de testes fiel ao esquema real.
 *
 * Uso:  php scripts/prepare_test_db.php [destino]
 *
 * O DESTINO é um argumento porque a suite corre em PARALELO: cada processo
 * precisa da sua própria base (soserp_test_1, soserp_test_2, …) — duas
 * corridas a partilhar tabelas viam os dados uma da outra e falhavam por
 * razões que não têm nada a ver com o produto. Sem argumento, é a de sempre.
 */
$host = getenv('DB_HOST') ?: '127.0.0.1';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

// ── MODO PARALELO: --workers=N ───────────────────────────────────────────
//
// Depois de uma migração, TODOS os workers da suite ficam desactualizados e
// cada clone custa ~25s (é o InnoDB a criar 235 ficheiros de tabela; nem o
// binlog nem o flush eram o gargalo — mediu-se). Em série eram 12 minutos de
// suite pendurada no arranque.
//
// A saída é sobrepor: o custo de cada CREATE é espera de disco, e N sessões
// em paralelo sobrepõem essas esperas quase de graça. Este modo clona os N
// workers em simultâneo (10 de cada vez) e salta os que já batem certo, por
// isso custa ~0s quando não há migração nova.
//
// Uso: php scripts/prepare_test_db.php --workers=29 [origem]
if (str_starts_with($argv[1] ?? '', '--workers=')) {
    $quantos = max(1, (int) substr($argv[1], strlen('--workers=')));
    $origem = $argv[2] ?? (getenv('DB_DATABASE') ?: 'soserp');

    // OS NOMES SÃO OS DO LARAVEL, LETRA A LETRA. O ParallelTesting acrescenta
    // `_test_{token}` à base de TESTE (soserp_test) — os workers chamam-se
    // `soserp_test_test_1..N`, com o `test` DUAS vezes. A primeira versão
    // disto clonou `soserp_test_1..29`: nomes bonitos, bases que ninguém usa
    // — e a suite continuou a reconstruir as verdadeiras no arranque, agora
    // com o disco ocupado por 29 clones fantasmas.
    $baseTeste = "{$origem}_test";

    $pdo = new PDO("mysql:host={$host}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // «Bate certo» é a IMPRESSÃO DIGITAL do esquema, não o número de tabelas.
    // Uma migração que só acrescenta colunas, ou só muda um DEFAULT, deixava
    // as contagens iguais e os workers com o esquema velho — a mesma regra
    // que o AppServiceProvider::prepararSuiteEmParalelo usa no arranque.
    $impressao = function (string $db) use ($pdo): string {
        $tabelas = (int) $pdo->query(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '.$pdo->quote($db)
        )->fetchColumn();

        if ($tabelas === 0) {
            return '-';
        }

        $definicoes = $pdo->query(
            "SELECT CONCAT_WS('|', TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, IFNULL(COLUMN_DEFAULT, 'NULL'))"
            .' FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '.$pdo->quote($db)
            .' ORDER BY TABLE_NAME, ORDINAL_POSITION'
        )->fetchAll(PDO::FETCH_COLUMN);

        return $tabelas.':'.count($definicoes).':'.md5(implode(';', $definicoes));
    };

    // Primeiro a base de série (é também o molde dos workers): fresca sempre.
    $molde = $impressao($origem);

    if ($impressao($baseTeste) !== $molde) {
        echo "a refrescar {$baseTeste}...\n";
        passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__)." {$baseTeste} {$origem}");
    }

    $porFazer = [];
    for ($i = 1; $i <= $quantos; $i++) {
        if ($impressao("{$baseTeste}_test_{$i}") !== $molde) {
            $porFazer[] = "{$baseTeste}_test_{$i}";
        }
    }

    if (! $porFazer) {
        echo "workers em dia ({$quantos}/{$quantos})\n";
        exit(0);
    }

    echo 'a clonar '.count($porFazer)." worker(s) em paralelo...\n";
    $inicio = microtime(true);
    $vivos = [];
    $falhas = 0;

    while ($porFazer || $vivos) {
        while ($porFazer && count($vivos) < 10) {
            $db = array_shift($porFazer);
            $vivos[$db] = proc_open(
                [PHP_BINARY, __FILE__, $db, $origem],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
        }
        foreach ($vivos as $db => $p) {
            $estado = proc_get_status($p);
            if (! $estado['running']) {
                if (proc_close($p) !== 0) {
                    $falhas++;
                    fwrite(STDERR, "FALHOU: {$db}\n");
                }
                unset($vivos[$db]);
            }
        }
        usleep(100_000);
    }

    printf("clonados em %.1fs%s\n", microtime(true) - $inicio, $falhas ? " ({$falhas} FALHA(S))" : '');
    exit($falhas ? 1 : 0);
}

$destino = $argv[1] ?? 'soserp_test';
$origem = $argv[2] ?? (getenv('DB_DATABASE') ?: 'soserp');

$pdo = new PDO("mysql:host={$host}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$destino}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

// SEM BINLOG NESTA SESSÃO. Com log_bin=ON e sync_binlog=1, cada um dos ~235
// CREATE TABLE levava um fsync ao log binário — um clone custava 30-50s, e
// 29 workers a reconstruir depois de uma migração eram 6+ minutos de suite
// pendurada no arranque. Bases de teste não se replicam: o binlog delas é
// puro custo. (Por-sessão, de propósito: não mexe no binlog de mais ninguém.)
try {
    $pdo->exec('SET sql_log_bin = 0');
} catch (Throwable $e) {
    // Sem privilégio SUPER fica como estava — mais lento, nunca partido.
}

// Limpar o que lá estiver, para o esquema ficar igual ao de origem.
// DROP DATABASE inteiro e não tabela a tabela: 235 drops individuais custavam
// vários segundos; o drop da base é uma operação só.
$pdo->exec("DROP DATABASE `{$destino}`");
$pdo->exec("CREATE DATABASE `{$destino}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$destino}`");

$pdo->exec("USE `{$origem}`");
$tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$copiadas = 0;
$falhas = [];

foreach ($tabelas as $tabela) {
    $ddl = $pdo->query("SHOW CREATE TABLE `{$tabela}`")->fetch(PDO::FETCH_NUM)[1] ?? null;
    if (! $ddl) {
        $falhas[] = $tabela;

        continue;
    }

    try {
        $pdo->exec("USE `{$destino}`");
        $pdo->exec($ddl);
        $copiadas++;
    } catch (\Throwable $e) {
        $falhas[] = $tabela.' ('.mb_substr($e->getMessage(), 0, 60).')';
    } finally {
        $pdo->exec("USE `{$origem}`");
    }
}

// A tabela de migrações vai com os registos, para o artisan não tentar
// remigrar tudo em cima de um esquema que já está completo.
$pdo->exec("USE `{$destino}`");
$pdo->exec('TRUNCATE TABLE `migrations`');
$pdo->exec("INSERT INTO `migrations` SELECT * FROM `{$origem}`.`migrations`");
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

printf("esquema copiado: %d/%d tabela(s)\n", $copiadas, count($tabelas));
foreach ($falhas as $f) {
    echo "  falhou: {$f}\n";
}
