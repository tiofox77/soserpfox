<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Confirma que o servidor tem MESMO o que foi enviado.
 *
 * O envio por FTP reporta "OK" sem confirmar a escrita. Numa auditoria única
 * apareceram 260 ficheiros desalinhados — classes em falta que se manifestavam
 * como erro 500 aleatório, e correcções que se julgavam publicadas e não
 * estavam. Este comando fecha essa lacuna: corre-se DEPOIS de cada envio.
 *
 *   php artisan deploy:verify
 *   php artisan deploy:verify --lista        (imprime só os caminhos, para reenviar)
 *   php artisan deploy:verify --url=https://outro.dominio
 */
class DeployVerifyCommand extends Command
{
    protected $signature = 'deploy:verify
        {--url= : URL do servidor (por omissão APP_URL de produção)}
        {--token= : Token de manutenção (por omissão MAINTENANCE_TOKEN)}
        {--lista : Imprime apenas os caminhos divergentes, um por linha}
        {--ps : Imprime o comando PowerShell pronto a reenviar}';

    protected $description = 'Compara os ficheiros locais com os do servidor e mostra o que falta ou difere';

    /**
     * Pastas comparadas por inteiro.
     *
     * NÃO inclui .env (é legitimamente diferente), storage/ (dados em execução)
     * nem vendor/ (instalado pelo composer no servidor).
     */
    private const RAIZES = [
        'app'                  => ['php'],
        'bootstrap'            => ['php'],
        'resources/views'      => ['php'],
        'resources/js'         => ['js'],
        'resources/css'        => ['css'],
        'lang'                 => ['php', 'json'],
        'database/migrations'  => ['php'],
        'database/seeders'     => ['php'],
        'database/factories'   => ['php'],
        'routes'               => ['php'],
        'config'               => ['php'],
        'public/js'            => ['js'],
        'public/css'           => ['css'],
    ];

    /**
     * Ficheiros soltos que também têm de estar iguais.
     *
     * O composer.lock entra de propósito: se divergir, o servidor está a correr
     * dependências diferentes das testadas aqui.
     */
    private const FICHEIROS_SOLTOS = [
        'artisan',
        'composer.json',
        'composer.lock',
        'public/index.php',
        'public/.htaccess',
        'public/robots.txt',
    ];

    /**
     * Ignorados dentro das raízes: gerados no servidor, não enviados.
     */
    private const IGNORAR = [
        'bootstrap/cache/',
    ];

    public function handle(): int
    {
        $url = rtrim((string) ($this->option('url') ?: config('app.url_producao') ?: 'https://soserp.vip'), '/');
        $token = (string) ($this->option('token') ?: config('maintenance.token') ?: env('MAINTENANCE_TOKEN'));

        if (strlen($token) < 16) {
            $this->error('Token de manutenção em falta ou demasiado curto (MAINTENANCE_TOKEN).');
            return self::FAILURE;
        }

        $manifesto = $this->construirManifesto();

        if (empty($manifesto)) {
            $this->error('Nenhum ficheiro encontrado para comparar.');
            return self::FAILURE;
        }

        $silencioso = $this->option('lista') || $this->option('ps');

        if (!$silencioso) {
            $this->info("=== Verificação de deploy · {$url} ===");
            $this->line('  ' . count($manifesto) . ' ficheiros locais a comparar');
        }

        try {
            // O DNS deste servidor falha de vez em quando; sem as tentativas o
            // comando dava erro por causa da rede e não por divergências, o que
            // ensina a ignorá-lo — exactamente o oposto do que se pretende.
            $resposta = Http::timeout(240)
                ->connectTimeout(30)
                ->retry(3, 2000, throw: false)
                ->asJson()
                ->post("{$url}/maintenance/{$token}/verify-files", [
                    'manifest' => $manifesto,
                ]);
        } catch (\Throwable $e) {
            $this->error('Falha ao contactar o servidor: ' . $e->getMessage());
            $this->line('  (rede ou DNS — a verificação não chegou a correr; repita)');
            return self::FAILURE;
        }

        if (!$resposta->successful()) {
            $this->error('O servidor respondeu ' . $resposta->status() . ': ' . mb_substr($resposta->body(), 0, 200));
            return self::FAILURE;
        }

        $r = $resposta->json();

        $divergentes = array_merge(
            $r['ausentes'] ?? [],
            array_column($r['diferem'] ?? [], 'ficheiro')
        );
        sort($divergentes);

        // Modos para encadear com o envio
        if ($this->option('lista')) {
            foreach ($divergentes as $f) {
                $this->line($f);
            }
            return empty($divergentes) ? self::SUCCESS : self::FAILURE;
        }

        if ($this->option('ps')) {
            if (empty($divergentes)) {
                $this->line('# nada a reenviar');
                return self::SUCCESS;
            }
            $lista = implode(",\n  ", array_map(fn ($f) => '"' . $f . '"', $divergentes));
            $this->line(".\\scripts\\ftp_deploy.ps1 -Files @(\n  {$lista}\n)");
            return self::FAILURE;
        }

        $this->line('  ' . ($r['verificados'] ?? 0) . ' verificados no servidor'
            . (($r['ignorados'] ?? 0) > 0 ? ', ' . $r['ignorados'] . ' ignorados' : ''));
        $this->newLine();

        if ($r['ok'] ?? false) {
            $this->info('  TUDO ALINHADO — o servidor tem exactamente o que está aqui.');
            return self::SUCCESS;
        }

        if (!empty($r['ausentes'])) {
            $this->line('<fg=red;options=bold>AUSENTES no servidor (' . count($r['ausentes']) . ')</>');
            foreach ($r['ausentes'] as $f) {
                $this->line("  <fg=red>{$f}</>");
            }
            $this->newLine();
        }

        if (!empty($r['diferem'])) {
            $this->line('<fg=yellow;options=bold>DIFEREM (' . count($r['diferem']) . ')</>');
            foreach ($r['diferem'] as $d) {
                $this->line(sprintf('  <fg=yellow>%-58s</> %s',
                    $d['ficheiro'],
                    ($d['motivo'] ?? 'tamanho') === 'tamanho'
                        ? "servidor={$d['servidor']} local={$d['origem']}"
                        : $d['motivo']));
            }
            $this->newLine();
        }

        $this->warn('Para reenviar tudo o que falta:');
        $this->line('  php artisan deploy:verify --ps');

        return self::FAILURE;
    }

    /** [caminho relativo => tamanho] de tudo o que deve estar igual no servidor. */
    private function construirManifesto(): array
    {
        $manifesto = [];

        foreach (self::RAIZES as $raiz => $extensoes) {
            $caminho = base_path($raiz);
            if (!is_dir($caminho)) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($caminho, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($it as $ficheiro) {
                if (!$ficheiro->isFile() || !in_array($ficheiro->getExtension(), $extensoes, true)) {
                    continue;
                }

                $relativo = str_replace('\\', '/', substr($ficheiro->getPathname(), strlen(base_path()) + 1));

                foreach (self::IGNORAR as $prefixo) {
                    if (str_starts_with($relativo, $prefixo)) {
                        continue 2;
                    }
                }

                // "tamanho:md5" — o hash é o que garante que o conteúdo é
                // mesmo o mesmo; o tamanho serve para o servidor descartar
                // depressa a maioria dos casos sem ler o ficheiro todo.
                $manifesto[$relativo] = $ficheiro->getSize() . ':' . md5_file($ficheiro->getPathname());
            }
        }

        foreach (self::FICHEIROS_SOLTOS as $relativo) {
            $caminho = base_path($relativo);
            if (is_file($caminho)) {
                $manifesto[$relativo] = filesize($caminho) . ':' . md5_file($caminho);
            }
        }

        ksort($manifesto);

        return $manifesto;
    }
}
