<?php

namespace App\Services\Copias\Destinos;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * S3 COMPATÍVEL — AWS S3, Backblaze B2, Wasabi, Cloudflare R2, MinIO, DigitalOcean Spaces.
 *
 * A assinatura AWS Signature Version 4 escrita aqui (sem o SDK da AWS, que não
 * vai no pacote do deploy). O corpo dos envios vai como UNSIGNED-PAYLOAD —
 * aceite por todos estes — para não ter de ler o ficheiro duas vezes; a
 * integridade confere-se pelo sha256 guardado com a cópia.
 */
class S3 extends Destino
{
    private function endpoint(): array
    {
        $url = rtrim((string) ($this->destino->cfg('endpoint') ?: 'https://s3.amazonaws.com'), '/');
        $p = parse_url($url);
        if (empty($p['host']) || ($p['scheme'] ?? '') !== 'https') {
            throw new RuntimeException(__('O endpoint S3 tem de ser um endereço https://.'));
        }
        if ($this->destino->tenant_id) {
            Rede::exigirPublico($p['host']);
        }

        return [$p['host'] . (isset($p['port']) ? ':' . $p['port'] : '')];
    }

    private function bucket(): string
    {
        return (string) ($this->destino->cfg('bucket') ?: throw new RuntimeException(__('Falta o nome do bucket.')));
    }

    private function chave(string $nome): string
    {
        return ltrim($this->pasta() . '/' . $nome, '/');
    }

    /** Monta host, caminho e cabeçalhos assinados de um pedido. */
    private function pedido(string $metodo, string $chave, array $query = [], bool $comCorpo = false): array
    {
        [$anfitriao] = $this->endpoint();
        $caminhoEstilo = (bool) $this->destino->cfg('estilo_caminho', true);
        $host = $caminhoEstilo ? $anfitriao : $this->bucket() . '.' . $anfitriao;
        $caminho = '/' . ($caminhoEstilo ? rawurlencode($this->bucket()) . '/' : '')
            . implode('/', array_map('rawurlencode', explode('/', $chave)));
        if ($chave === '') {
            $caminho = '/' . ($caminhoEstilo ? rawurlencode($this->bucket()) . '/' : '');
        }

        $regiao = (string) ($this->destino->cfg('regiao') ?: 'us-east-1');
        $agora = gmdate('Ymd\THis\Z');
        $dia = substr($agora, 0, 8);
        $payload = $comCorpo || $metodo === 'GET' ? 'UNSIGNED-PAYLOAD' : hash('sha256', '');

        ksort($query);
        $queryCanonica = implode('&', array_map(fn ($k, $v) => rawurlencode($k) . '=' . rawurlencode((string) $v), array_keys($query), $query));

        $cabecalhos = ['host' => $host, 'x-amz-content-sha256' => $payload, 'x-amz-date' => $agora];
        ksort($cabecalhos);
        $canonicos = implode('', array_map(fn ($k, $v) => "{$k}:{$v}\n", array_keys($cabecalhos), $cabecalhos));
        $assinados = implode(';', array_keys($cabecalhos));

        $pedidoCanonico = implode("\n", [$metodo, $caminho, $queryCanonica, $canonicos, $assinados, $payload]);
        $ambito = "{$dia}/{$regiao}/s3/aws4_request";
        $aAssinar = implode("\n", ['AWS4-HMAC-SHA256', $agora, $ambito, hash('sha256', $pedidoCanonico)]);

        $k = hash_hmac('sha256', $dia, 'AWS4' . $this->destino->cfg('chave_secreta'), true);
        $k = hash_hmac('sha256', $regiao, $k, true);
        $k = hash_hmac('sha256', 's3', $k, true);
        $k = hash_hmac('sha256', 'aws4_request', $k, true);
        $assinatura = hash_hmac('sha256', $aAssinar, $k);

        $url = "https://{$host}{$caminho}" . ($queryCanonica !== '' ? '?' . $queryCanonica : '');

        return [$url, [
            'x-amz-content-sha256' => $payload,
            'x-amz-date' => $agora,
            'Authorization' => sprintf('AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s', $this->destino->cfg('chave_acesso'), $ambito, $assinados, $assinatura),
        ]];
    }

    public function enviar(string $local, string $nome): string
    {
        $chave = $this->chave($nome);
        [$url, $cabecalhos] = $this->pedido('PUT', $chave, [], true);
        $f = fopen($local, 'rb');

        try {
            $r = Http::withHeaders($cabecalhos + ['Content-Length' => (string) filesize($local)])->timeout(900)
                ->withBody(Utils::streamFor($f), 'application/octet-stream')
                ->put($url);
        } finally {
            if (is_resource($f)) {
                fclose($f);
            }
        }

        if (! $r->successful()) {
            $this->falhar(__('O armazenamento S3 recusou o envio.'), null, $r->body());
        }

        return $chave;
    }

    public function descarregar(string $remoto, string $local): void
    {
        [$url, $cabecalhos] = $this->pedido('GET', $remoto);
        $r = Http::withHeaders($cabecalhos)->timeout(900)->withOptions(['sink' => $local])->get($url);

        if (! $r->successful()) {
            @unlink($local);
            $this->falhar(__('Não foi possível descarregar do armazenamento S3.'));
        }
    }

    public function apagar(string $remoto): void
    {
        [$url, $cabecalhos] = $this->pedido('DELETE', $remoto);
        $r = Http::withHeaders($cabecalhos)->timeout(60)->delete($url);

        if (! $r->successful() && $r->status() !== 404) {
            $this->falhar(__('O armazenamento S3 não deixou apagar a cópia.'), null, $r->body());
        }
    }

    public function listar(): array
    {
        $prefixo = $this->pasta() !== '' ? $this->pasta() . '/' : '';
        [$url, $cabecalhos] = $this->pedido('GET', '', ['list-type' => '2', 'prefix' => $prefixo, 'max-keys' => '1000']);
        $r = Http::withHeaders($cabecalhos)->timeout(60)->get($url);

        if (! $r->successful()) {
            $this->falhar(__('Não foi possível listar o bucket.'), null, $r->body());
        }

        $xml = @simplexml_load_string($r->body());
        $itens = [];
        foreach ($xml?->Contents ?? [] as $c) {
            $chave = (string) $c->Key;
            if (self::ehCopia(basename($chave))) {
                $itens[] = ['remoto' => $chave, 'nome' => basename($chave), 'tamanho' => (int) $c->Size, 'data' => (string) $c->LastModified];
            }
        }

        return collect($itens)->sortByDesc('data')->values()->all();
    }
}
