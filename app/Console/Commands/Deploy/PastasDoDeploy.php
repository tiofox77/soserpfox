<?php

namespace App\Console\Commands\Deploy;

use RuntimeException;

/**
 * Onde vivem, no servidor, os pacotes e os instantâneos. Fora do docroot
 * (storage/app), porque um instantâneo leva a base de dados inteira.
 */
trait PastasDoDeploy
{
    protected function pastaDosPacotes(): string
    {
        return storage_path('app/deploy/pacotes');
    }

    protected function pastaDosInstantaneos(): string
    {
        return storage_path('app/deploy/instantaneos');
    }

    protected function caminhoDoPacote(string $nome): string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+\.zip$/', $nome)) {
            throw new RuntimeException("Nome de pacote inválido: {$nome}");
        }

        return $this->pastaDosPacotes() . '/' . $nome;
    }

    protected function pastaDoInstantaneo(string $nome): string
    {
        if (! preg_match('/^\d{8}-\d{6}$/', $nome)) {
            throw new RuntimeException("Nome de instantâneo inválido: {$nome} (é da forma 20260913-181500)");
        }

        return $this->pastaDosInstantaneos() . '/' . $nome;
    }

    /** @return array{host: string, port: string, username: string, password: string, database: string} */
    protected function ligacaoDaBase(): array
    {
        $cfg = config('database.connections.' . config('database.default'));

        return [
            'host' => (string) ($cfg['host'] ?? '127.0.0.1'),
            'port' => (string) ($cfg['port'] ?? 3306),
            'username' => (string) ($cfg['username'] ?? ''),
            'password' => (string) ($cfg['password'] ?? ''),
            'database' => (string) ($cfg['database'] ?? ''),
        ];
    }
}
