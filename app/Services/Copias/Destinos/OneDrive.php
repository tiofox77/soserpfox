<?php

namespace App\Services\Copias\Destinos;

use Illuminate\Support\Facades\Http;

/**
 * ONEDRIVE — Microsoft Graph, na pasta da aplicação (Apps/<nome da aplicação>).
 *
 * Envio por «upload session»: pedaços com tamanho múltiplo de 320 KiB (regra
 * da Microsoft), e o URL da sessão NÃO leva o token — mandá-lo faz o pedido
 * falhar.
 */
class OneDrive extends Destino
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0/me/drive';

    /** 25 × 320 KiB ≈ 7,8 MB. */
    private const PEDACO = 25 * 327680;

    private function http()
    {
        return Http::withToken(OAuth::acesso($this->destino))->timeout(120);
    }

    private function caminho(string $nome = ''): string
    {
        $partes = array_map('rawurlencode', array_filter([...explode('/', $this->pasta()), $nome]));

        return self::GRAPH . '/special/approot:/' . implode('/', $partes);
    }

    public function enviar(string $local, string $nome): string
    {
        $tamanho = filesize($local);

        $sessao = $this->http()->post($this->caminho($nome) . ':/createUploadSession', [
            'item' => ['@microsoft.graph.conflictBehavior' => 'replace'],
        ]);
        $url = $sessao->json('uploadUrl');

        if (! $sessao->successful() || ! $url) {
            $this->falhar(__('O OneDrive recusou o envio.'), null, $sessao->body());
        }

        $f = fopen($local, 'rb');
        $posicao = 0;
        $id = null;

        try {
            do {
                $dados = fread($f, self::PEDACO) ?: '';
                $fim = $posicao + strlen($dados) - 1;

                $r = Http::timeout(300)
                    ->withHeaders(['Content-Range' => "bytes {$posicao}-{$fim}/{$tamanho}"])
                    ->withBody($dados, 'application/octet-stream')
                    ->put($url);

                if ($r->status() === 202) {
                    $posicao = $fim + 1;

                    continue;
                }
                if (! $r->successful()) {
                    $this->falhar(__('O envio para o OneDrive falhou a meio.'), null, $r->body());
                }
                $id = $r->json('id');
                break;
            } while ($posicao < $tamanho);
        } finally {
            fclose($f);
        }

        return $id ?? $this->falhar(__('O OneDrive não devolveu o ficheiro enviado.'));
    }

    public function descarregar(string $remoto, string $local): void
    {
        $r = Http::withToken(OAuth::acesso($this->destino))->timeout(600)
            ->withOptions(['sink' => $local])
            ->get(self::GRAPH . '/items/' . rawurlencode($remoto) . '/content');

        if (! $r->successful()) {
            @unlink($local);
            $this->falhar(__('Não foi possível descarregar do OneDrive.'));
        }
    }

    public function apagar(string $remoto): void
    {
        $r = $this->http()->delete(self::GRAPH . '/items/' . rawurlencode($remoto));

        if (! $r->successful() && $r->status() !== 404) {
            $this->falhar(__('O OneDrive não deixou apagar a cópia.'), null, $r->body());
        }
    }

    public function listar(): array
    {
        $r = $this->http()->get($this->caminho() . ':/children', ['$select' => 'id,name,size,createdDateTime', '$top' => 200]);

        if ($r->status() === 404) {
            return [];
        }
        if (! $r->successful()) {
            $this->falhar(__('Não foi possível listar a pasta do OneDrive.'), null, $r->body());
        }

        return collect($r->json('value', []))
            ->filter(fn ($f) => self::ehCopia($f['name']))
            ->map(fn ($f) => ['remoto' => $f['id'], 'nome' => $f['name'], 'tamanho' => (int) ($f['size'] ?? 0), 'data' => $f['createdDateTime'] ?? null])
            ->sortByDesc('data')->values()->all();
    }
}
