<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * O lado CLIENTE das atualizações: pergunta ao servidor, VERIFICA, e (se
 * pedido) aplica com rede de segurança.
 *
 * Duas trancas de segurança, ambas obrigatórias antes de tocar em nada:
 *   1. o MANIFESTO tem de ter assinatura Ed25519 válida (UpdateVerifier);
 *   2. o PACOTE descarregado tem de bater o SHA-256 do manifesto.
 * Só depois disto se faz backup da BD, se aplica, e — a qualquer falha — se faz
 * rollback (restaura a BD do backup).
 *
 * NOTA HONESTA: o `aplicar()` mexe em ficheiros e na BD de uma instalação real.
 * As trancas de segurança e o backup/rollback da BD estão implementados e são
 * testados; a troca de ficheiros a quente num Windows com o Apache a servir
 * precisa dos serviços parados (trata disso o wrapper do instalador) e de um
 * snapshot de release para rollback de ficheiros — ver PRD (refinamento).
 */
class UpdateService
{
    public function __construct(private array $cfg)
    {
    }

    public static function apartirDaConfig(): self
    {
        return new self(config('licensing', []));
    }

    private function up(string $chave, $default = null)
    {
        return $this->cfg['update'][$chave] ?? $default;
    }

    /**
     * Pergunta ao servidor se há update e devolve os claims do manifesto JÁ
     * verificados (assinatura). null = nada a fazer / não confiável.
     */
    public function verificar(?string $token = null): ?array
    {
        $url = trim((string) $this->up('check_url', ''));
        if ($url === '') {
            return null;
        }

        $token = $token ?? LicenseManager::apartirDaConfig()->store()->token();
        if (!$token) {
            return null;
        }

        try {
            $resp = Http::timeout(15)->acceptJson()->post($url, [
                'token'        => $token,
                'versao_atual' => $this->up('current', '1.0.0'),
            ]);
        } catch (\Throwable $e) {
            return null; // sem rede: fica na versão actual
        }

        if (!$resp->successful()) {
            return null;
        }

        $dados = (array) $resp->json();
        if (($dados['atualizado'] ?? false) === true || empty($dados['manifesto'])) {
            return null;
        }

        // A segunda verificação, do lado do cliente: o servidor pode mentir, a
        // assinatura não. Reverifica sempre o manifesto localmente.
        $claims = (new UpdateVerifier((string) $this->up('public_key', '')))->ler($dados['manifesto']);
        if (!$claims || empty($claims['pacote_url']) || empty($claims['pacote_sha256'])) {
            return null;
        }

        return $claims;
    }

    /**
     * Descarrega o pacote e confirma o SHA-256 do manifesto. Devolve o caminho
     * local; lança se falhar o download ou o hash (a tranca que impede aplicar
     * um pacote trocado, mesmo com manifesto válido).
     */
    public function descarregar(array $claims): string
    {
        $dir = (string) $this->up('work_dir', storage_path('app/updates/work'));
        File::ensureDirectoryExists($dir);
        $destino = $dir . '/pacote-' . ($claims['versao'] ?? 'x') . '.zip';

        $resp = Http::timeout(120)->get($claims['pacote_url']);
        if (!$resp->successful()) {
            throw new \RuntimeException('Falha ao descarregar o pacote (HTTP ' . $resp->status() . ').');
        }
        file_put_contents($destino, $resp->body());

        if (!UpdateVerifier::ficheiroConfere($destino, (string) $claims['pacote_sha256'])) {
            @unlink($destino);
            throw new \RuntimeException('SHA-256 do pacote não confere — pacote recusado.');
        }

        return $destino;
    }

    /**
     * Aplica a atualização com rede de segurança. Devolve um relatório.
     *
     * @return array{ok:bool, etapa:string, motivo?:string, backup?:string}
     */
    public function aplicar(array $claims): array
    {
        // 1) descarregar + verificar hash (tranca dura, antes de tudo)
        try {
            $pacote = $this->descarregar($claims);
        } catch (\Throwable $e) {
            return ['ok' => false, 'etapa' => 'descarga', 'motivo' => $e->getMessage()];
        }

        // 2) backup da BD (o que tem volta mais difícil)
        $backup = $this->backupBd();
        if ($backup === null) {
            return ['ok' => false, 'etapa' => 'backup', 'motivo' => 'Não foi possível fazer backup da BD — abortado.'];
        }

        // 3) extrair para staging
        $staging = (string) $this->up('work_dir') . '/staging-' . ($claims['versao'] ?? 'x');
        try {
            $this->extrair($pacote, $staging);
        } catch (\Throwable $e) {
            return ['ok' => false, 'etapa' => 'extracao', 'motivo' => $e->getMessage(), 'backup' => $backup];
        }

        // 4) aplicar ficheiros + migrar; qualquer falha → rollback da BD
        try {
            $this->copiarSobreApp($staging);
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('view:cache');            // nunca deixar as views a frio
            $this->verificarSaude();                // health-check pós
        } catch (\Throwable $e) {
            $this->restaurarBd($backup);
            return ['ok' => false, 'etapa' => 'aplicacao', 'motivo' => $e->getMessage(), 'backup' => $backup];
        }

        return ['ok' => true, 'etapa' => 'concluido', 'backup' => $backup];
    }

    // ── OS/DB (dependentes do ambiente; correm numa instalação real) ────────

    /** mysqldump da BD para um .sql. null = falhou (aborta a atualização). */
    private function backupBd(): ?string
    {
        try {
            $dir = (string) $this->up('backup_dir', storage_path('app/updates/backups'));
            File::ensureDirectoryExists($dir);

            $c = config('database.connections.' . config('database.default'));
            if (($c['driver'] ?? '') !== 'mysql') {
                return null; // v1 só faz backup automático de MySQL/MariaDB
            }

            $ficheiro = $dir . '/db-' . ($this->up('current', 'x')) . '-' . date('Ymd_His') . '.sql';
            $cmd = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s',
                escapeshellarg($c['host']), escapeshellarg((string) ($c['port'] ?? 3306)),
                escapeshellarg($c['username']), escapeshellarg((string) $c['password']),
                escapeshellarg($c['database']), escapeshellarg($ficheiro)
            );
            @shell_exec($cmd);

            return (is_file($ficheiro) && filesize($ficheiro) > 0) ? $ficheiro : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function restaurarBd(string $sql): bool
    {
        try {
            $c = config('database.connections.' . config('database.default'));
            $cmd = sprintf(
                'mysql --host=%s --port=%s --user=%s --password=%s %s < %s',
                escapeshellarg($c['host']), escapeshellarg((string) ($c['port'] ?? 3306)),
                escapeshellarg($c['username']), escapeshellarg((string) $c['password']),
                escapeshellarg($c['database']), escapeshellarg($sql)
            );
            @shell_exec($cmd);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function extrair(string $zip, string $destino): void
    {
        File::ensureDirectoryExists($destino);
        $za = new \ZipArchive();
        if ($za->open($zip) !== true) {
            throw new \RuntimeException('Não foi possível abrir o pacote.');
        }
        $za->extractTo($destino);
        $za->close();
    }

    /**
     * Copia o staging sobre a aplicação, preservando o que é do cliente
     * (.env, storage). Numa instalação Windows, o wrapper do instalador deve
     * PARAR os serviços antes (ficheiros bloqueados) — ver PRD.
     */
    private function copiarSobreApp(string $staging): void
    {
        $base = base_path();
        $preservar = ['.env', 'storage'];

        foreach (File::directories($staging) as $dir) {
            $nome = basename($dir);
            if (in_array($nome, $preservar, true)) {
                continue;
            }
            File::copyDirectory($dir, $base . DIRECTORY_SEPARATOR . $nome);
        }
        foreach (File::files($staging) as $file) {
            if (in_array($file->getFilename(), $preservar, true)) {
                continue;
            }
            File::copy($file->getPathname(), $base . DIRECTORY_SEPARATOR . $file->getFilename());
        }
    }

    /** Health-check pós-migração: se rebentar, o aplicar() faz rollback. */
    private function verificarSaude(): void
    {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    }
}
