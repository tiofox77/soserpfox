<?php

namespace App\Http\Middleware;

use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use SessionHandlerInterface;

/**
 * O manipulador de um só pedido: lê pelo verdadeiro, e na gravação renova a
 * actividade (na base) e devolve o verdadeiro à sessão.
 *
 * Ver SessaoSoDeLeitura.
 */
final class SoRenovaUmaVez implements SessionHandlerInterface
{
    public function __construct(private SessionHandlerInterface $original, private Store $sessao) {}

    public function open(string $path, string $name): bool
    {
        return $this->original->open($path, $name);
    }

    public function close(): bool
    {
        return $this->original->close();
    }

    public function read(string $id): string|false
    {
        return $this->original->read($id);
    }

    public function write(string $id, string $data): bool
    {
        $this->sessao->setHandler($this->original);

        if ($this->original instanceof DatabaseSessionHandler) {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('id', $id)
                ->update(['last_activity' => time()]);
        }

        return true;
    }

    public function destroy(string $id): bool
    {
        return $this->original->destroy($id);
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->original->gc($max_lifetime);
    }
}
