<?php

namespace App\Logging;

use App\Services\Agent\RegistoDeErros;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Liga o log ao registo de problemas.
 *
 * Tudo o que entra no log a partir de ERROR passa por aqui e é agrupado na
 * tabela `erros_do_sistema`, para o agente externo poder avisar uma vez por
 * problema em vez de mil vezes por ocorrência.
 *
 * NÃO SUBSTITUI O FICHEIRO DE LOG. É um segundo ouvinte, pendurado por `tap`
 * no canal — o ficheiro continua a receber tudo, com o detalhe todo. Isto
 * fica com o resumo consultável.
 *
 * Nada aqui pode rebentar nem bloquear: uma escrita no log acontece muitas
 * vezes já dentro de um `catch`, e uma excepção neste ponto substituiria o
 * erro original por outro, apagando a pista.
 */
class CapturarErros extends AbstractProcessingHandler
{
    public function __construct()
    {
        // Warning fica de fora: é ruído a mais para acordar alguém, e a lista
        // que se ignora por ser longa demais é a lista onde o erro a sério
        // passa despercebido.
        parent::__construct(Level::Error, true);
    }

    protected function write(LogRecord $record): void
    {
        try {
            if (!config('agent.erros.capturar', true)) {
                return;
            }

            $contexto = $record->context;
            $excepcao = $contexto['exception'] ?? null;

            $ficheiro = null;
            $linha = null;
            $classe = null;

            if ($excepcao instanceof \Throwable) {
                $ficheiro = $this->relativo($excepcao->getFile());
                $linha = $excepcao->getLine();
                $classe = get_class($excepcao);

                // O objecto não é serializável para JSON e traz argumentos de
                // chamadas — onde as passwords viajam. Fica só o essencial.
                $contexto['exception'] = $classe . ': ' . $excepcao->getMessage();
                $contexto['trace'] = $this->rastoCurto($excepcao);
            }

            app(RegistoDeErros::class)->registar(
                nivel: strtolower($record->level->getName()),
                mensagem: $record->message,
                contexto: $contexto,
                ficheiro: $ficheiro,
                linha: $linha,
                excepcao: $classe,
            );
        } catch (\Throwable) {
            // Em silêncio de propósito — ver RegistoDeErros. Uma excepção aqui
            // rebentaria dentro do tratamento de outro erro.
        }
    }

    /** Caminhos absolutos mudam entre máquinas e partem o agrupamento. */
    private function relativo(string $caminho): string
    {
        $base = base_path();

        return str_starts_with($caminho, $base)
            ? ltrim(str_replace('\\', '/', substr($caminho, strlen($base))), '/')
            : $caminho;
    }

    /** As primeiras linhas do rasto chegam para se saber de onde veio. */
    private function rastoCurto(\Throwable $e): string
    {
        $linhas = array_slice(explode("\n", $e->getTraceAsString()), 0, 8);

        return mb_substr(implode("\n", $linhas), 0, 1500);
    }
}
