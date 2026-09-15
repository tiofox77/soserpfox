<?php

namespace App\Services\Copias\Destinos;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * WEBDAV — Nextcloud, ownCloud, pCloud, Koofr, Box, um NAS Synology ou QNAP.
 *
 * PUT para enviar, PROPFIND para listar, MKCOL para criar a pasta (um 405 quer
 * dizer que já existe), DELETE para apagar. Autenticação básica sobre HTTPS —
 * em Nextcloud use uma «senha de aplicação», não a da conta.
 */
class WebDav extends Destino
{
    private function base(): string
    {
        $url = rtrim((string) $this->destino->cfg('url'), '/');
        $p = parse_url($url);
        if (empty($p['host']) || ! in_array($p['scheme'] ?? '', ['https', 'http'], true)) {
            throw new RuntimeException(__('O endereço WebDAV tem de começar por https://.'));
        }
        if ($this->destino->tenant_id) {
            if (($p['scheme'] ?? '') !== 'https') {
                throw new RuntimeException(__('O endereço WebDAV tem de começar por https://.'));
            }
            Rede::exigirPublico($p['host']);
        }

        return $url;
    }

    private function http()
    {
        return Http::withBasicAuth((string) $this->destino->cfg('utilizador'), (string) $this->destino->cfg('senha'))
            ->withOptions(['allow_redirects' => false]);
    }

    private function urlDaPasta(): string
    {
        $pasta = implode('/', array_map('rawurlencode', array_filter(explode('/', $this->pasta()))));

        return $this->base() . '/' . ($pasta !== '' ? $pasta . '/' : '');
    }

    private function garantirPasta(): void
    {
        $url = $this->base();
        foreach (array_filter(explode('/', $this->pasta())) as $parte) {
            $url .= '/' . rawurlencode($parte);
            $r = $this->http()->timeout(30)->send('MKCOL', $url . '/');
            if (! in_array($r->status(), [201, 405, 301, 302], true) && ! $r->successful()) {
                $this->falhar(__('O servidor WebDAV não deixou criar a pasta.'), null, $r->body());
            }
        }
    }

    public function enviar(string $local, string $nome): string
    {
        $this->garantirPasta();
        $f = fopen($local, 'rb');

        try {
            $r = $this->http()->timeout(900)
                ->withHeaders(['Content-Length' => (string) filesize($local)])
                ->withBody(Utils::streamFor($f), 'application/octet-stream')
                ->put($this->urlDaPasta() . rawurlencode($nome));
        } finally {
            if (is_resource($f)) {
                fclose($f);
            }
        }

        if (! $r->successful()) {
            $this->falhar(__('O servidor WebDAV recusou o envio.'), null, $r->body());
        }

        return $nome;
    }

    public function descarregar(string $remoto, string $local): void
    {
        $r = $this->http()->timeout(900)->withOptions(['sink' => $local])->get($this->urlDaPasta() . rawurlencode($remoto));

        if (! $r->successful()) {
            @unlink($local);
            $this->falhar(__('Não foi possível descarregar do servidor WebDAV.'));
        }
    }

    public function apagar(string $remoto): void
    {
        $r = $this->http()->timeout(60)->delete($this->urlDaPasta() . rawurlencode($remoto));

        if (! $r->successful() && $r->status() !== 404) {
            $this->falhar(__('O servidor WebDAV não deixou apagar a cópia.'), null, $r->body());
        }
    }

    public function listar(): array
    {
        $r = $this->http()->timeout(60)
            ->withHeaders(['Depth' => '1'])
            ->withBody('<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:getcontentlength/><d:getlastmodified/><d:resourcetype/></d:prop></d:propfind>', 'application/xml')
            ->send('PROPFIND', $this->urlDaPasta());

        if ($r->status() === 404) {
            return [];
        }
        if ($r->status() !== 207) {
            $this->falhar(__('Não foi possível listar a pasta WebDAV.'), null, $r->body());
        }

        $xml = @simplexml_load_string($r->body());
        if (! $xml) {
            return [];
        }
        $xml->registerXPathNamespace('d', 'DAV:');

        $itens = [];
        foreach ($xml->xpath('//d:response') ?: [] as $resposta) {
            $resposta->registerXPathNamespace('d', 'DAV:');
            $nome = rawurldecode(basename((string) ($resposta->xpath('d:href')[0] ?? '')));
            if (! self::ehCopia($nome)) {
                continue;
            }
            $tamanho = $resposta->xpath('.//d:getcontentlength')[0] ?? null;
            $data = $resposta->xpath('.//d:getlastmodified')[0] ?? null;
            $itens[] = [
                'remoto' => $nome,
                'nome' => $nome,
                'tamanho' => $tamanho !== null ? (int) $tamanho : null,
                'data' => $data !== null ? \Carbon\Carbon::parse((string) $data)->toIso8601String() : null,
            ];
        }

        return collect($itens)->sortByDesc('data')->values()->all();
    }
}
