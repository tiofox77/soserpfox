<?php

namespace App\Services\Copias\Destinos;

use Illuminate\Support\Facades\Http;

/**
 * DROPBOX — API v2, «upload session» (start → append → finish).
 *
 * Os parâmetros dos pedidos de conteúdo vão no cabeçalho Dropbox-API-Arg, em
 * JSON só com ASCII: um nome com acentos tem de ir escapado (\uXXXX), ou o
 * Dropbox recusa o cabeçalho.
 */
class Dropbox extends Destino
{
    private const API = 'https://api.dropboxapi.com/2';

    private const CONTEUDO = 'https://content.dropboxapi.com/2';

    private function token(): string
    {
        return OAuth::acesso($this->destino);
    }

    private function caminho(string $nome = ''): string
    {
        return '/' . trim($this->pasta() . ($nome !== '' ? '/' . $nome : ''), '/');
    }

    private static function arg(array $a): string
    {
        return json_encode($a, JSON_UNESCAPED_SLASHES);
    }

    public function enviar(string $local, string $nome): string
    {
        $tamanho = filesize($local);
        $pedaco = (int) config('copias.pedaco_bytes');
        $f = fopen($local, 'rb');

        try {
            $dados = fread($f, $pedaco) ?: '';
            $r = Http::withToken($this->token())->timeout(300)
                ->withHeaders(['Dropbox-API-Arg' => self::arg(['close' => false])])
                ->withBody($dados, 'application/octet-stream')
                ->post(self::CONTEUDO . '/files/upload_session/start');
            $sessao = $r->json('session_id');
            if (! $r->successful() || ! $sessao) {
                $this->falhar(__('O Dropbox recusou o envio.'), null, $r->body());
            }
            $posicao = strlen($dados);

            while ($posicao < $tamanho) {
                $dados = fread($f, $pedaco) ?: '';
                $r = Http::withToken($this->token())->timeout(300)
                    ->withHeaders(['Dropbox-API-Arg' => self::arg(['cursor' => ['session_id' => $sessao, 'offset' => $posicao], 'close' => false])])
                    ->withBody($dados, 'application/octet-stream')
                    ->post(self::CONTEUDO . '/files/upload_session/append_v2');
                if (! $r->successful()) {
                    $this->falhar(__('O envio para o Dropbox falhou a meio.'), null, $r->body());
                }
                $posicao += strlen($dados);
            }

            $r = Http::withToken($this->token())->timeout(120)
                ->withHeaders(['Dropbox-API-Arg' => self::arg([
                    'cursor' => ['session_id' => $sessao, 'offset' => $posicao],
                    'commit' => ['path' => $this->caminho($nome), 'mode' => 'add', 'autorename' => true, 'mute' => true],
                ])])
                ->withBody('', 'application/octet-stream')
                ->post(self::CONTEUDO . '/files/upload_session/finish');

            if (! $r->successful()) {
                $this->falhar(__('O Dropbox não concluiu o envio.'), null, $r->body());
            }

            return (string) ($r->json('path_display') ?? $this->caminho($nome));
        } finally {
            fclose($f);
        }
    }

    public function descarregar(string $remoto, string $local): void
    {
        $r = Http::withToken($this->token())->timeout(600)
            ->withHeaders(['Dropbox-API-Arg' => self::arg(['path' => $remoto])])
            ->withOptions(['sink' => $local])
            ->withBody('', 'text/plain')
            ->post(self::CONTEUDO . '/files/download');

        if (! $r->successful()) {
            @unlink($local);
            $this->falhar(__('Não foi possível descarregar do Dropbox.'));
        }
    }

    public function apagar(string $remoto): void
    {
        $r = Http::withToken($this->token())->timeout(60)->post(self::API . '/files/delete_v2', ['path' => $remoto]);

        if (! $r->successful() && ! str_contains((string) $r->body(), 'not_found')) {
            $this->falhar(__('O Dropbox não deixou apagar a cópia.'), null, $r->body());
        }
    }

    public function listar(): array
    {
        $r = Http::withToken($this->token())->timeout(60)->post(self::API . '/files/list_folder', ['path' => $this->caminho(), 'limit' => 500]);

        if (! $r->successful()) {
            if (str_contains((string) $r->body(), 'not_found')) {
                return [];
            }
            $this->falhar(__('Não foi possível listar a pasta do Dropbox.'), null, $r->body());
        }

        return collect($r->json('entries', []))
            ->filter(fn ($f) => ($f['.tag'] ?? '') === 'file' && self::ehCopia($f['name']))
            ->map(fn ($f) => ['remoto' => $f['path_display'], 'nome' => $f['name'], 'tamanho' => (int) ($f['size'] ?? 0), 'data' => $f['server_modified'] ?? null])
            ->sortByDesc('data')->values()->all();
    }
}
