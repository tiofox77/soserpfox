<?php

namespace App\Console\Commands\Deploy;

use App\Support\Deploy\Pacote;
use Illuminate\Console\Command;
use Throwable;

/**
 * VOLTA AO ESTADO DE ANTES DO DEPLOY.
 *
 *   deploy:restaurar 20260913-181500              → só diz o que faria
 *   deploy:restaurar 20260913-181500 --confirmar  → repõe os ficheiros
 *   deploy:restaurar 20260913-181500 --bd --confirmar
 *                                                  → e a base de dados
 *
 * A BASE DE DADOS É À PARTE e pede-se de propósito: repô-la apaga tudo o que
 * as empresas gravaram depois do instantâneo (facturas emitidas, vendas do
 * POS). Antes de a repor tira-se uma cópia da de agora, na mesma pasta — nem
 * esse passo se perde.
 *
 * Se o deploy deixou o Laravel sem arrancar, este comando não corre: há o
 * restauro de emergência em /__restauro/{token}/{instantaneo} (só ficheiros).
 */
class RestaurarInstantaneo extends Command
{
    use PastasDoDeploy;

    protected $signature = 'deploy:restaurar
        {instantaneo : o nome do instantâneo (deploy:instantaneos lista-os)}
        {--bd : repor também a base de dados (apaga o que foi gravado depois)}
        {--confirmar : sem isto só diz o que faria}';

    protected $description = 'Repõe os ficheiros (e, com --bd, a base de dados) de um instantâneo de deploy';

    public function handle(): int
    {
        @set_time_limit(0);

        try {
            $pasta = $this->pastaDoInstantaneo((string) $this->argument('instantaneo'));
            $confirmar = (bool) $this->option('confirmar');

            $resultado = Pacote::restaurar(base_path(), $pasta, aSeco: ! $confirmar);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $confirmar) {
            $this->info("A seco: repunha {$resultado['repostos']} ficheiros e apagava {$resultado['apagados']} criados pelo pacote.");

            if ($this->option('bd')) {
                $this->warn('E repunha a base de dados de ' . $this->dataDaCopia($pasta) . ' — perde-se o que foi gravado depois.');
            }

            $this->line('Para fazer mesmo: acrescente --confirmar');

            return self::SUCCESS;
        }

        $this->info("Ficheiros repostos: {$resultado['repostos']}; apagados os {$resultado['apagados']} que o pacote criou.");

        if ($this->option('bd')) {
            return $this->reporBase($pasta);
        }

        $this->line('A seguir: optimize:clear, config:cache, route:cache, view:cache, deploy:opcache-reset.');

        return self::SUCCESS;
    }

    private function reporBase(string $pasta): int
    {
        $copia = $pasta . '/bd.sql.gz';

        if (! is_file($copia) || filesize($copia) < 200) {
            $this->error('Este instantâneo não tem cópia da base de dados.');

            return self::FAILURE;
        }

        if (! function_exists('exec')) {
            $this->error('exec() está desligado no servidor — a base de dados não se repõe por aqui.');

            return self::FAILURE;
        }

        $antes = $pasta . '/bd-antes-do-restauro-' . date('Ymd-His') . '.sql.gz';

        if ($this->call('db:dump', ['--destino' => $antes]) !== self::SUCCESS) {
            $this->error('Não foi possível copiar a base de dados de agora — por segurança, não se repõe.');

            return self::FAILURE;
        }

        $bd = $this->ligacaoDaBase();
        $sql = $pasta . '/bd-a-repor.sql';
        $erros = $pasta . '/bd-a-repor.err';

        @exec(sprintf('gzip -dc %s > %s 2>%s', escapeshellarg($copia), escapeshellarg($sql), escapeshellarg($erros)), $saida, $rc);

        if ($rc !== 0 || ! is_file($sql) || filesize($sql) < 200) {
            $this->error('Não foi possível descomprimir a cópia: ' . substr((string) @file_get_contents($erros), 0, 500));
            @unlink($sql);

            return self::FAILURE;
        }

        $mysql = trim((string) @shell_exec('command -v mysql 2>/dev/null')) ?: 'mysql';
        putenv('MYSQL_PWD=' . $bd['password']);

        @exec(sprintf(
            '%s --default-character-set=utf8mb4 -h %s -P %s -u %s %s < %s 2>%s',
            $mysql,
            escapeshellarg($bd['host']),
            escapeshellarg($bd['port']),
            escapeshellarg($bd['username']),
            escapeshellarg($bd['database']),
            escapeshellarg($sql),
            escapeshellarg($erros),
        ), $saida, $rc);

        putenv('MYSQL_PWD');
        @unlink($sql);

        if ($rc !== 0) {
            $this->error('A reposição da base de dados falhou (rc=' . $rc . '): ' . substr((string) @file_get_contents($erros), 0, 500));
            $this->warn('A base de antes desta tentativa está em ' . basename($antes));

            return self::FAILURE;
        }

        @unlink($erros);
        $this->info('Base de dados reposta a partir de ' . $this->dataDaCopia($pasta) . '. A de antes do restauro ficou em ' . basename($antes) . '.');

        return self::SUCCESS;
    }

    private function dataDaCopia(string $pasta): string
    {
        $copia = $pasta . '/bd.sql.gz';

        return is_file($copia) ? date('Y-m-d H:i:s', (int) filemtime($copia)) : '(sem cópia)';
    }
}
