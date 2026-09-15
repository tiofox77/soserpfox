<?php

namespace App\Services\Copias;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * A BASE INTEIRA — despejar (mysqldump) e repor (mysql).
 *
 * É o mesmo caminho que o instantâneo do deploy usa há semanas em produção
 * (`db:dump` e `deploy:restaurar --bd`): o mysqldump numa transacção só, sem
 * trancar tabelas, e o mysql com a senha no ambiente (MYSQL_PWD) e não na linha
 * de comando, onde apareceria na lista de processos.
 *
 * O QUE SOBREVIVE A UM RESTAURO: as tabelas das próprias cópias. Repor uma base
 * de ontem não pode apagar os destinos ligados hoje, nem o registo do restauro
 * que está a acontecer — guardam-se antes e voltam a pôr-se depois.
 */
class BaseInteira
{
    public const PRESERVAR = ['agendas_de_copia', 'destinos_de_copia', 'copias_de_seguranca', 'envios_de_copia', 'restauros_de_copia'];

    public function despejar(string $destino): void
    {
        $this->exigirExec();
        $cfg = config('database.connections.' . config('database.default'));
        $erros = $destino . '.err';

        putenv('MYSQL_PWD=' . (string) $cfg['password']);

        try {
            @exec(sprintf(
                '%s --single-transaction --quick --no-tablespaces --skip-lock-tables --default-character-set=utf8mb4 -h %s -P %s -u %s %s 2>%s | gzip -c > %s',
                $this->binario('mysqldump'),
                escapeshellarg((string) $cfg['host']),
                escapeshellarg((string) ($cfg['port'] ?? 3306)),
                escapeshellarg((string) $cfg['username']),
                escapeshellarg((string) $cfg['database']),
                escapeshellarg($erros),
                escapeshellarg($destino)
            ), $saida, $rc);
        } finally {
            putenv('MYSQL_PWD');
        }

        $tamanho = is_file($destino) ? filesize($destino) : 0;
        $stderr = trim((string) @file_get_contents($erros));
        @unlink($erros);

        // O mysqldump escreve avisos no stderr mesmo quando corre bem: o que
        // decide é o ficheiro ter conteúdo e acabar com «Dump completed».
        if ($tamanho < 200 || ! $this->completo($destino)) {
            @unlink($destino);
            throw new RuntimeException('O mysqldump falhou (rc=' . $rc . '): ' . mb_strimwidth($stderr ?: 'sem mensagem', 0, 400, '…'));
        }
    }

    /** Repõe a base a partir de um .sql.gz já decifrado. */
    public function repor(string $origem): array
    {
        $this->exigirExec();
        $cfg = config('database.connections.' . config('database.default'));
        $preservadas = $this->guardarPreservadas();

        $sql = $origem . '.sql';
        $erros = $origem . '.err';

        @exec(sprintf('gzip -dc %s > %s 2>%s', escapeshellarg($origem), escapeshellarg($sql), escapeshellarg($erros)), $s, $rc);
        if ($rc !== 0 || ! is_file($sql) || filesize($sql) < 100) {
            @unlink($sql);
            throw new RuntimeException('Não foi possível descomprimir a cópia: ' . trim((string) @file_get_contents($erros)));
        }

        putenv('MYSQL_PWD=' . (string) $cfg['password']);

        try {
            @exec(sprintf(
                '%s --default-character-set=utf8mb4 -h %s -P %s -u %s %s < %s 2>%s',
                $this->binario('mysql'),
                escapeshellarg((string) $cfg['host']),
                escapeshellarg((string) ($cfg['port'] ?? 3306)),
                escapeshellarg((string) $cfg['username']),
                escapeshellarg((string) $cfg['database']),
                escapeshellarg($sql),
                escapeshellarg($erros)
            ), $s, $rc);
        } finally {
            putenv('MYSQL_PWD');
            @unlink($sql);
        }

        $stderr = trim((string) @file_get_contents($erros));
        @unlink($erros);

        DB::purge();
        DB::reconnect();

        $this->reporPreservadas($preservadas);

        if ($rc !== 0) {
            throw new RuntimeException('O mysql parou com erro (rc=' . $rc . '): ' . mb_strimwidth($stderr, 0, 400, '…'));
        }

        // Uma cópia de antes de uma migração: o esquema volta atrás e o código
        // não. As migrações por correr correm já.
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            report($e);
        }

        return ['migracoes' => trim(Artisan::output())];
    }

    private function guardarPreservadas(): array
    {
        $dados = [];
        foreach (self::PRESERVAR as $t) {
            if (Schema::hasTable($t)) {
                $dados[$t] = DB::table($t)->get()->map(fn ($l) => (array) $l)->all();
            }
        }

        return $dados;
    }

    private function reporPreservadas(array $dados): void
    {
        foreach ($dados as $t => $linhas) {
            if (! Schema::hasTable($t)) {
                continue;
            }
            DB::table($t)->delete();
            foreach (array_chunk($linhas, 200) as $bloco) {
                DB::table($t)->insert($bloco);
            }
        }
    }

    private function completo(string $gz): bool
    {
        // O mysqldump fecha com «-- Dump completed on ...». Lê-se só o fim.
        $f = @gzopen($gz, 'rb');
        if (! $f) {
            return false;
        }
        $fim = '';
        while (! gzeof($f)) {
            $fim = substr($fim . gzread($f, 65536), -512);
        }
        gzclose($f);

        return str_contains($fim, 'Dump completed');
    }

    private function binario(string $nome): string
    {
        $caminho = trim((string) @shell_exec('command -v ' . escapeshellarg($nome) . ' 2>/dev/null'));

        return $caminho !== '' ? $caminho : $nome;
    }

    private function exigirExec(): void
    {
        if (! function_exists('exec')) {
            throw new RuntimeException('exec() está desligado neste servidor: a base inteira não se copia por aqui.');
        }
    }
}
