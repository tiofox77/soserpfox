<?php

namespace App\Services\Copias\Destinos;

use App\Models\Copias\DestinoDeCopia;
use RuntimeException;

/**
 * O QUE UM DESTINO SABE FAZER: enviar, listar, descarregar e apagar.
 *
 * Cada fornecedor (Google Drive, OneDrive, Dropbox, FTP, SFTP, S3, WebDAV)
 * implementa estas quatro coisas pela API dele — sem bibliotecas novas no
 * vendor, que não vai no pacote do deploy: HTTP pelo cliente do Laravel, e
 * FTP/SFTP pelo curl que o PHP já traz.
 *
 * TESTAR é igual para todos, e não é «a ligação abre»: envia um ficheiro
 * pequeno e apaga-o. Um FTP que aceita o login mas não deixa escrever passava
 * num teste de ligação e falhava na primeira cópia a sério.
 */
abstract class Destino
{
    public function __construct(protected DestinoDeCopia $destino)
    {
    }

    /** Envia o ficheiro e devolve como o encontrar do lado de lá (id ou caminho). */
    abstract public function enviar(string $local, string $nome): string;

    abstract public function descarregar(string $remoto, string $local): void;

    abstract public function apagar(string $remoto): void;

    /** @return list<array{remoto: string, nome: string, tamanho: ?int, data: ?string}> */
    abstract public function listar(): array;

    public function testar(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sosteste');
        file_put_contents($tmp, 'SOSERP — teste de escrita ' . now()->toIso8601String());

        try {
            $remoto = $this->enviar($tmp, '.soserp-teste-' . now()->format('YmdHis') . '.txt');
            $this->apagar($remoto);
        } finally {
            @unlink($tmp);
        }
    }

    /** A pasta configurada, sem barras nas pontas. */
    protected function pasta(): string
    {
        return trim((string) ($this->destino->pasta ?: 'SOSERP Copias'), "/ \t");
    }

    protected function falhar(string $o, ?\Throwable $e = null, ?string $resposta = null): never
    {
        $pormenor = $resposta ? ' — ' . mb_strimwidth(strip_tags($resposta), 0, 300, '…') : '';

        throw new RuntimeException($o . $pormenor, 0, $e);
    }

    /** Só os ficheiros que parecem cópias do SOSERP — o resto da pasta não é connosco. */
    protected static function ehCopia(string $nome): bool
    {
        return (bool) preg_match('/^soserp-.+\.(sql\.gz|jsonl\.gz)(\.soscopia)?$/', $nome);
    }
}
