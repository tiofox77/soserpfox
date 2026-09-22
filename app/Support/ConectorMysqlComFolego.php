<?php

namespace App\Support;

use Illuminate\Database\Connectors\MySqlConnector;
use PDOException;

/**
 * LIGAR AO MYSQL COM FÔLEGO (22/09/2026).
 *
 * O alojamento dá ao utilizador da base um tecto de 30 ligações ao mesmo tempo
 * (`max_user_connections`) e o servidor partilhado tem o seu próprio tecto
 * (`Too many connections`). Quando o MySQL abranda, os pedidos demoram, as
 * ligações ficam presas e o 31.º pedido levava um erro 500 logo à primeira —
 * até na página de entrada, que só queria ler a sessão.
 *
 * Um tecto cheio esvazia-se em fracções de segundo, à medida que os pedidos
 * presos acabam. Por isso: três novas tentativas com espera crescente (cerca
 * de 2 s ao todo) antes de desistir. Só para estes dois erros — uma senha
 * errada ou um servidor em baixo não melhoram com esperas.
 */
class ConectorMysqlComFolego extends MySqlConnector
{
    /** Espera antes de cada nova tentativa, em milissegundos. */
    public const ESPERAS_MS = [250, 600, 1200];

    public function createConnection($dsn, array $config, array $options)
    {
        foreach (self::ESPERAS_MS as $espera) {
            try {
                return $this->ligar($dsn, $config, $options);
            } catch (PDOException $e) {
                if (! self::tectoCheio($e)) {
                    throw $e;
                }

                $this->esperar($espera);
            }
        }

        return $this->ligar($dsn, $config, $options);
    }

    /** Uma tentativa — a de sempre do Laravel. */
    protected function ligar($dsn, array $config, array $options)
    {
        return parent::createConnection($dsn, $config, $options);
    }

    protected function esperar(int $milissegundos): void
    {
        usleep($milissegundos * 1000);
    }

    /** 1226 = o tecto do utilizador; 1040 = o tecto do servidor. */
    public static function tectoCheio(\Throwable $e): bool
    {
        return in_array((int) $e->getCode(), [1226, 1040], true)
            || (bool) preg_match('/\[(1226|1040)\]|max_user_connections|Too many connections/i', $e->getMessage());
    }
}
