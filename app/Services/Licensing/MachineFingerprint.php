<?php

namespace App\Services\Licensing;

/**
 * Impressão digital da máquina — prende uma licença a UM computador.
 *
 * Junta identificadores estáveis de hardware/SO e resume-os num hash. Não é
 * infalível (nada em software o é), mas muda quando se copia a instalação para
 * outra máquina — que é o abuso comum. Nunca rebenta: o que não conseguir ler
 * fica em branco e entra no hash na mesma.
 */
class MachineFingerprint
{
    private static ?string $cache = null;

    /** O fingerprint desta máquina (cacheado no processo). */
    public static function atual(): string
    {
        return self::$cache ??= self::dasPartes(self::recolher());
    }

    /** Hash determinístico de um conjunto de partes — usado também nos testes. */
    public static function dasPartes(array $partes): string
    {
        $partes = array_values(array_filter(array_map(fn ($p) => trim((string) $p), $partes)));
        sort($partes); // ordem estável: a mesma máquina dá sempre o mesmo hash
        return substr(hash('sha256', implode('|', $partes)), 0, 32);
    }

    /** @return string[] */
    public static function recolher(): array
    {
        $partes = [
            gethostname() ?: '',
            php_uname('s'),   // sistema operativo
            php_uname('m'),   // arquitectura
        ];

        // Windows (o parque alvo): id estável de placa/CPU/produto.
        foreach (['wmic csproduct get UUID', 'wmic cpu get ProcessorId', 'wmic baseboard get SerialNumber'] as $cmd) {
            $partes[] = self::valorDoComando($cmd);
        }

        // Linux: machine-id, se existir.
        if (@is_readable('/etc/machine-id')) {
            $partes[] = trim((string) @file_get_contents('/etc/machine-id'));
        }

        return $partes;
    }

    /**
     * Corre um comando e devolve o valor útil (o wmic imprime o cabeçalho na
     * 1.ª linha e o valor a seguir). Blindado: qualquer falha devolve ''.
     */
    private static function valorDoComando(string $cmd): string
    {
        if (!function_exists('shell_exec')) {
            return '';
        }

        try {
            $out = @shell_exec($cmd);
        } catch (\Throwable $e) {
            return '';
        }

        if (!$out) {
            return '';
        }

        $linhas = array_values(array_filter(array_map('trim', explode("\n", $out))));

        // Última linha não-vazia = o valor (a 1.ª é o nome da propriedade).
        return end($linhas) ?: '';
    }
}
