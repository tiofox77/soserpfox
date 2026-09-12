<?php

namespace App\Services\Plataforma;

/**
 * ESCREVER NO `.env` — o único sítio onde as credenciais do produtor AGT vivem.
 *
 * Estava dentro do ecrã das definições do software. Saiu para aqui por uma
 * razão só: um ensaio que exercitasse aquele ecrã escrevia no `.env` VERDADEIRO
 * da máquina. O caminho vem de `app()->environmentFilePath()`, que um ensaio
 * aponta para um ficheiro temporário com `useEnvironmentPath()`.
 */
class FicheiroDeAmbiente
{
    /** @param array<string, string> $valores */
    public function escrever(array $valores): void
    {
        $caminho = app()->environmentFilePath();
        $conteudo = @file_get_contents($caminho);

        if ($conteudo === false) {
            throw new \RuntimeException(__('Não foi possível ler o ficheiro de configuração do sistema.'));
        }

        foreach ($valores as $chave => $valor) {
            // UMA QUEBRA DE LINHA NUM VALOR escrevia uma variável nova à escolha
            // de quem preenchesse o campo.
            if (preg_match('/[\r\n]/', (string) $valor) || ! preg_match('/^[A-Z][A-Z0-9_]*$/', $chave)) {
                throw new \InvalidArgumentException(__('Valor inválido para :chave.', ['chave' => $chave]));
            }

            $linha = $chave.'='.($valor === '' ? '' : '"'.addcslashes((string) $valor, '\\"').'"');
            $padrao = '/^'.preg_quote($chave, '/').'=.*/m';

            $conteudo = preg_match($padrao, $conteudo)
                ? preg_replace_callback($padrao, static fn () => $linha, $conteudo, 1)
                : rtrim($conteudo).PHP_EOL.$linha.PHP_EOL;
        }

        if (file_put_contents($caminho, $conteudo, LOCK_EX) === false) {
            throw new \RuntimeException(__('Não foi possível guardar o ficheiro de configuração do sistema.'));
        }
    }
}
