<?php

namespace App\Helpers;

/**
 * Máscara de dinheiro: separadores, formatação e leitura tolerante.
 *
 * A escolha de separadores vem de `invoicing_settings.number_format` (angola,
 * international, portugal, …) e as casas de `decimal_places`. Este helper é a
 * FONTE ÚNICA da correspondência formato→separadores, usada tanto no PHP (ao
 * formatar o valor inicial e ao ler o que vem do input) como no JS (que recebe
 * os separadores por data-atributos no input).
 *
 * O parse é DELIBERADAMENTE tolerante: o utilizador pode escrever "10.000,23",
 * "10000,23", "10000.23" ou "10000", e a factura tem de receber sempre o
 * número certo. Um preço mal lido é dinheiro a mais ou a menos num documento
 * fiscal — não se pode depender de o JS ter corrido.
 */
class MoneyHelper
{
    /**
     * Separadores por formato: [milhar, decimal].
     */
    public static function separadores(?string $numberFormat): array
    {
        return match ($numberFormat) {
            'international', 'india' => [',', '.'],
            'switzerland'            => ["'", '.'],
            'france'                 => [' ', ','],
            // angola, portugal, brazil e omissão
            default                  => ['.', ','],
        };
    }

    /**
     * Configuração da máscara para o tenant activo (ou a partir de umas
     * definições dadas). Request-cache para não repetir a query por linha.
     *
     * @return array{on:bool,milhar:string,decimal:string,casas:int}
     */
    public static function config($settings = null): array
    {
        static $cache = null;

        if ($settings === null && $cache !== null) {
            return $cache;
        }

        try {
            $settings = $settings ?: \App\Models\Invoicing\InvoicingSettings::forTenant(activeTenantId());
        } catch (\Throwable $e) {
            // Sem empresa/definições: máscara Angola por omissão, ligada.
            return ['on' => true, 'milhar' => '.', 'decimal' => ',', 'casas' => 2];
        }

        [$milhar, $decimal] = self::separadores($settings->number_format ?? 'angola');

        $cfg = [
            'on'      => (bool) ($settings->price_mask_enabled ?? true),
            'milhar'  => $milhar,
            'decimal' => $decimal,
            'casas'   => (int) ($settings->decimal_places ?? 2),
        ];

        if (func_num_args() === 0) {
            $cache = $cfg;
        }

        return $cfg;
    }

    /**
     * Formata um número para exibição, ex.: 10000.23 → "10.000,23".
     */
    public static function format($valor, ?array $cfg = null): string
    {
        $cfg = $cfg ?: self::config();

        return number_format(
            (float) $valor,
            $cfg['casas'],
            $cfg['decimal'],
            $cfg['milhar']
        );
    }

    /**
     * Lê um valor escrito pelo utilizador e devolve um float, CIENTE do formato
     * da empresa.
     *
     * O separador decimal é o configurado (a vírgula, no formato Angola); tudo o
     * resto — o separador de milhares (o ponto), espaços, "Kz", apóstrofo — é
     * ruído e desaparece. Assim "100.000" lê-se 100000 (não 100), "10.000,23" lê
     * 10000.23, e "100,50" lê 100,5. É esta a regra que casa com a máscara: o
     * ponto que o utilizador escreve são milhares, não decimais.
     *
     * @param array{milhar:string,decimal:string,casas:int}|null $cfg
     */
    public static function parse($valor, ?array $cfg = null): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $cfg = $cfg ?: self::config();
        $dec = $cfg['decimal'];

        $s = (string) $valor;
        $out = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if ($ch >= '0' && $ch <= '9') {
                $out .= $ch;
            } elseif ($ch === $dec) {
                $out .= '.'; // decimal -> ponto
            } elseif ($ch === '-') {
                $out .= '-';
            }
            // separador de milhares e tudo o resto: ignorado
        }

        // Garantir um só ponto decimal.
        $p = strpos($out, '.');
        if ($p !== false) {
            $out = substr($out, 0, $p + 1) . str_replace('.', '', substr($out, $p + 1));
        }

        return is_numeric($out) ? (float) $out : 0.0;
    }
}
