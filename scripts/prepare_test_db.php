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
 * Uso:  php scripts/prepare_test_db.php
 */

$host   = getenv('DB_HOST') ?: '127.0.0.1';
$user   = getenv('DB_USERNAME') ?: 'root';
$pass   = getenv('DB_PASSWORD') ?: '';
$origem = getenv('DB_DATABASE') ?: 'soserp';
$destino = 'soserp_test';

$pdo = new PDO("mysql:host={$host}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$destino}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

// Limpar o que lá estiver, para o esquema ficar igual ao de origem.
$pdo->exec("USE `{$destino}`");
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
}

$pdo->exec("USE `{$origem}`");
$tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$copiadas = 0;
$falhas   = [];

foreach ($tabelas as $tabela) {
    $ddl = $pdo->query("SHOW CREATE TABLE `{$tabela}`")->fetch(PDO::FETCH_NUM)[1] ?? null;
    if (!$ddl) {
        $falhas[] = $tabela;
        continue;
    }

    try {
        $pdo->exec("USE `{$destino}`");
        $pdo->exec($ddl);
        $copiadas++;
    } catch (\Throwable $e) {
        $falhas[] = $tabela . ' (' . mb_substr($e->getMessage(), 0, 60) . ')';
    } finally {
        $pdo->exec("USE `{$origem}`");
    }
}

// A tabela de migrações vai com os registos, para o artisan não tentar
// remigrar tudo em cima de um esquema que já está completo.
$pdo->exec("USE `{$destino}`");
$pdo->exec("TRUNCATE TABLE `migrations`");
$pdo->exec("INSERT INTO `migrations` SELECT * FROM `{$origem}`.`migrations`");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

printf("esquema copiado: %d/%d tabela(s)\n", $copiadas, count($tabelas));
foreach ($falhas as $f) {
    echo "  falhou: {$f}\n";
}
