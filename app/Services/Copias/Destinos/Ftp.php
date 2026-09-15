<?php

namespace App\Services\Copias\Destinos;

use RuntimeException;

/**
 * FTP, FTPS E SFTP — pelo curl, que o PHP já traz.
 *
 * A extensão `ftp` não existe em todos os PHP (não existe no deste
 * desenvolvimento) e o SFTP pedia a extensão ssh2 ou uma biblioteca no vendor.
 * O curl fala os três protocolos; o ecrã só oferece os que o curl do servidor
 * sabe (`Ftp::protocolosDisponiveis()`).
 *
 * Segurança: `CURLOPT_PROTOCOLS` fica preso ao protocolo do destino (um
 * redireccionamento não salta para file:// nem para http), e nos destinos das
 * empresas o anfitrião tem de ser público (Rede).
 */
class Ftp extends Destino
{
    public static function protocolosDisponiveis(): array
    {
        return function_exists('curl_version') ? (curl_version()['protocols'] ?? []) : [];
    }

    private function sftp(): bool
    {
        return $this->destino->tipo === 'sftp';
    }

    private function base(): string
    {
        $anfitriao = trim((string) $this->destino->cfg('anfitriao'));
        if ($anfitriao === '') {
            throw new RuntimeException(__('Falta o anfitrião do servidor.'));
        }
        if ($this->destino->tenant_id) {
            Rede::exigirPublico($anfitriao);
        }

        $esquema = $this->sftp() ? 'sftp' : ($this->destino->cfg('seguranca') === 'implicito' ? 'ftps' : 'ftp');
        $porta = (int) ($this->destino->cfg('porta') ?: ($this->sftp() ? 22 : ($esquema === 'ftps' ? 990 : 21)));
        $pasta = implode('/', array_map('rawurlencode', array_filter(explode('/', $this->pasta()))));

        // No SFTP o caminho é absoluto a partir da raiz; «~» é a casa do utilizador.
        $prefixo = $this->sftp() && ! str_starts_with((string) $this->destino->pasta, '/') ? '/~' : '';

        return "{$esquema}://{$anfitriao}:{$porta}{$prefixo}/" . ($pasta !== '' ? $pasta . '/' : '');
    }

    private function curl(string $url, array $opcoes): string
    {
        $c = curl_init($url);
        $protocolo = $this->sftp() ? CURLPROTO_SFTP : (CURLPROTO_FTP | CURLPROTO_FTPS);

        curl_setopt_array($c, $opcoes + [
            CURLOPT_USERPWD => $this->destino->cfg('utilizador') . ':' . $this->destino->cfg('senha'),
            CURLOPT_PROTOCOLS => $protocolo,
            CURLOPT_REDIR_PROTOCOLS => $protocolo,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 900,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FTP_CREATE_MISSING_DIRS => CURLFTP_CREATE_DIR,
        ]);

        if (! $this->sftp()) {
            if (in_array($this->destino->cfg('seguranca'), ['explicito', 'implicito'], true)) {
                curl_setopt($c, CURLOPT_USE_SSL, CURLUSESSL_ALL);
                $verificar = (bool) $this->destino->cfg('verificar_certificado', true);
                curl_setopt($c, CURLOPT_SSL_VERIFYPEER, $verificar);
                curl_setopt($c, CURLOPT_SSL_VERIFYHOST, $verificar ? 2 : 0);
            }
            curl_setopt($c, CURLOPT_FTP_USE_EPSV, true);
            if (! $this->destino->cfg('passivo', true)) {
                curl_setopt($c, CURLOPT_FTPPORT, '-');
            }
        }

        $saida = curl_exec($c);
        $erro = curl_error($c);
        $codigo = curl_errno($c);
        curl_close($c);

        if ($codigo !== 0) {
            throw new RuntimeException(__('O servidor :p respondeu com erro: :e', ['p' => strtoupper($this->sftp() ? 'sftp' : 'ftp'), 'e' => $erro]));
        }

        return is_string($saida) ? $saida : '';
    }

    public function enviar(string $local, string $nome): string
    {
        $f = fopen($local, 'rb');

        try {
            $this->curl($this->base() . rawurlencode($nome), [
                CURLOPT_UPLOAD => true,
                CURLOPT_INFILE => $f,
                CURLOPT_INFILESIZE => filesize($local),
            ]);
        } finally {
            fclose($f);
        }

        return $nome;
    }

    public function descarregar(string $remoto, string $local): void
    {
        $f = fopen($local, 'wb');

        try {
            $this->curl($this->base() . rawurlencode($remoto), [CURLOPT_FILE => $f, CURLOPT_RETURNTRANSFER => false]);
        } catch (\Throwable $e) {
            fclose($f);
            @unlink($local);
            throw $e;
        }

        fclose($f);
    }

    public function apagar(string $remoto): void
    {
        $comando = $this->sftp()
            ? 'rm ' . $this->caminhoSftp($remoto)
            : 'DELE ' . $remoto;

        $this->curl($this->base(), [CURLOPT_QUOTE => [$comando], CURLOPT_NOBODY => true]);
    }

    public function listar(): array
    {
        $saida = $this->curl($this->base(), [CURLOPT_DIRLISTONLY => true]);

        return collect(preg_split('/\r?\n/', $saida))
            ->map(fn ($n) => basename(trim($n)))
            ->filter(fn ($n) => $n !== '' && self::ehCopia($n))
            ->map(fn ($n) => ['remoto' => $n, 'nome' => $n, 'tamanho' => null, 'data' => self::dataDoNome($n)])
            ->sortByDesc('nome')->values()->all();
    }

    private function caminhoSftp(string $nome): string
    {
        $pasta = trim((string) $this->destino->pasta, '/');
        $absoluta = str_starts_with((string) $this->destino->pasta, '/');

        return ($absoluta ? '/' : '') . ($pasta !== '' ? $pasta . '/' : '') . $nome;
    }

    /** O FTP não diz a data na listagem simples; o nome da cópia di-la. */
    public static function dataDoNome(string $nome): ?string
    {
        return preg_match('/(\d{8})-(\d{6})/', $nome, $m)
            ? \Carbon\Carbon::createFromFormat('Ymd His', $m[1] . ' ' . $m[2])->toIso8601String()
            : null;
    }
}
