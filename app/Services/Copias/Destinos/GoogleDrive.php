<?php

namespace App\Services\Copias\Destinos;

use Illuminate\Support\Facades\Http;

/**
 * GOOGLE DRIVE — API v3, envio «resumable» às partes.
 *
 * A pasta cria-se na primeira vez e o id fica no destino. Com a permissão
 * drive.file a aplicação só vê o que ela própria criou: a pasta e as cópias.
 */
class GoogleDrive extends Destino
{
    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3/files';

    private function http()
    {
        return Http::withToken(OAuth::acesso($this->destino))->timeout(120);
    }

    private function pastaId(): string
    {
        if ($id = $this->destino->cfg('pasta_id')) {
            return $id;
        }

        $nome = $this->pasta();
        $q = sprintf("name = '%s' and mimeType = 'application/vnd.google-apps.folder' and trashed = false", str_replace("'", "\\'", $nome));
        $r = $this->http()->get(self::API . '/files', ['q' => $q, 'fields' => 'files(id)', 'pageSize' => 1]);
        $id = $r->successful() ? $r->json('files.0.id') : null;

        if (! $id) {
            $r = $this->http()->post(self::API . '/files?fields=id', ['name' => $nome, 'mimeType' => 'application/vnd.google-apps.folder']);
            if (! $r->successful()) {
                $this->falhar(__('O Google Drive não deixou criar a pasta.'), null, $r->body());
            }
            $id = $r->json('id');
        }

        $this->destino->juntarCfg(['pasta_id' => $id]);

        return $id;
    }

    public function enviar(string $local, string $nome): string
    {
        $tamanho = filesize($local);

        $inicio = $this->http()
            ->withHeaders(['X-Upload-Content-Type' => 'application/octet-stream', 'X-Upload-Content-Length' => (string) $tamanho])
            ->post(self::UPLOAD . '?uploadType=resumable&fields=id', ['name' => $nome, 'parents' => [$this->pastaId()]]);

        $sessao = $inicio->header('Location');
        if (! $inicio->successful() || ! $sessao) {
            $this->falhar(__('O Google Drive recusou o envio.'), null, $inicio->body());
        }

        $f = fopen($local, 'rb');
        $pedaco = (int) config('copias.pedaco_bytes');
        $posicao = 0;
        $id = null;

        try {
            // Um ficheiro vazio vai num só pedido.
            do {
                $dados = $tamanho > 0 ? fread($f, $pedaco) : '';
                $fim = $posicao + strlen($dados) - 1;
                $intervalo = $tamanho > 0 ? "bytes {$posicao}-{$fim}/{$tamanho}" : 'bytes */0';

                $r = Http::withToken(OAuth::acesso($this->destino))->timeout(300)
                    ->withHeaders(['Content-Range' => $intervalo])
                    ->withBody($dados, 'application/octet-stream')
                    ->put($sessao);

                if ($r->status() === 308) {
                    $posicao = $fim + 1;

                    continue;
                }
                if (! $r->successful()) {
                    $this->falhar(__('O envio para o Google Drive falhou a meio.'), null, $r->body());
                }
                $id = $r->json('id');
                break;
            } while ($posicao < $tamanho);
        } finally {
            fclose($f);
        }

        return $id ?? $this->falhar(__('O Google Drive não devolveu o ficheiro enviado.'));
    }

    public function descarregar(string $remoto, string $local): void
    {
        $r = Http::withToken(OAuth::acesso($this->destino))->timeout(600)
            ->withOptions(['sink' => $local])
            ->get(self::API . '/files/' . rawurlencode($remoto), ['alt' => 'media']);

        if (! $r->successful()) {
            @unlink($local);
            $this->falhar(__('Não foi possível descarregar do Google Drive.'));
        }
    }

    public function apagar(string $remoto): void
    {
        $r = $this->http()->delete(self::API . '/files/' . rawurlencode($remoto));

        if (! $r->successful() && $r->status() !== 404) {
            $this->falhar(__('O Google Drive não deixou apagar a cópia.'), null, $r->body());
        }
    }

    public function listar(): array
    {
        $r = $this->http()->get(self::API . '/files', [
            'q' => sprintf("'%s' in parents and trashed = false", $this->pastaId()),
            'fields' => 'files(id,name,size,createdTime)',
            'orderBy' => 'createdTime desc',
            'pageSize' => 200,
        ]);

        if (! $r->successful()) {
            $this->falhar(__('Não foi possível listar a pasta do Google Drive.'), null, $r->body());
        }

        return collect($r->json('files', []))
            ->filter(fn ($f) => self::ehCopia($f['name']))
            ->map(fn ($f) => ['remoto' => $f['id'], 'nome' => $f['name'], 'tamanho' => isset($f['size']) ? (int) $f['size'] : null, 'data' => $f['createdTime'] ?? null])
            ->values()->all();
    }
}
