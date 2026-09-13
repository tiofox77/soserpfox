<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Dump da BD para um .sql.gz numa pasta NÃO-pública (storage/app/db-dump/),
 * para replicar produção -> local. Descarregado depois por FTP autenticado.
 *
 * NÃO expõe nenhum endpoint público. Corrido via rota de manutenção (token) ou CLI.
 * O ficheiro deve ser apagado após download (o pull_prod_db.ps1 fá-lo).
 */
class DumpDatabase extends Command
{
    protected $signature = 'db:dump {--destino= : caminho absoluto do .sql.gz, dentro de storage/ (o instantâneo do deploy usa-o)}';
    protected $description = 'Gera storage/app/db-dump/soserp_prod.sql.gz (exclui tabelas transitórias)';

    public function handle(): int
    {
        $cfg = config('database.connections.mysql');

        if (!function_exists('exec')) {
            $this->error('exec() está desativado no servidor — impossível usar mysqldump.');
            return 1;
        }

        $file = storage_path('app/db-dump/soserp_prod.sql.gz');

        // O instantâneo do deploy guarda a cópia na sua pasta. Só dentro de
        // storage/: é o que fica fora do docroot.
        if ($destino = $this->option('destino')) {
            $destino = str_replace('\\', '/', (string) $destino);
            if (!str_starts_with($destino, str_replace('\\', '/', storage_path()) . '/') || str_contains($destino, '..') || !str_ends_with($destino, '.sql.gz')) {
                $this->error('--destino tem de ser um .sql.gz dentro de storage/.');
                return 1;
            }
            $file = $destino;
        }

        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $errFile = $dir . '/dump.err';
        @unlink($file);
        @unlink($errFile);

        $mysqldump = trim((string) @shell_exec('command -v mysqldump 2>/dev/null'));
        if ($mysqldump === '') {
            $mysqldump = 'mysqldump';
        }

        putenv('MYSQL_PWD=' . (string) $cfg['password']);

        // Dump COMPLETO (todas as tabelas) para o local ficar idêntico à produção.
        // Não excluir sessions/cache/jobs — senão a réplica fica sem tabelas essenciais.
        $cmd = sprintf(
            '%s --single-transaction --no-tablespaces --skip-lock-tables --default-character-set=utf8mb4 -h %s -P %s -u %s %s 2>%s | gzip -c > %s',
            $mysqldump,
            escapeshellarg($cfg['host']),
            escapeshellarg((string) ($cfg['port'] ?? 3306)),
            escapeshellarg($cfg['username']),
            escapeshellarg($cfg['database']),
            escapeshellarg($errFile),
            escapeshellarg($file)
        );

        @exec($cmd, $out, $rc);

        $size = file_exists($file) ? filesize($file) : 0;
        if ($size < 200) {
            $err = @file_get_contents($errFile) ?: '(sem stderr)';
            $this->error('Dump falhou (rc=' . $rc . ', size=' . $size . '): ' . substr($err, 0, 500));
            return 1;
        }

        @unlink($errFile);
        $this->info('OK: ' . $file . ' (' . round($size / 1048576, 2) . ' MB)');
        return 0;
    }
}
