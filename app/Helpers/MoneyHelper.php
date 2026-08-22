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
     * Lê um valor escrito pelo utilizador e devolve um float. Tolerante a
     * qualquer combinação de separadores.
     *
     * Regra: o ÚLTIMO separador que aparece (vírgula ou ponto) é o decimal; o
     * outro é de milhares. Assim "10.000,23", "10,000.23", "1.234.567,89" e
     * "10000.23" leem-se todos correctamente. Um valor sem separadores fica
     * inteiro.
     */
    public static function parse($valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $s = trim((string) $valor);
        // Fora dígitos, ponto, vírgula e sinal, nada interessa (Kz, espaços,
        // apóstrofo da Suíça, etc.).
        $s = preg_replace('/[^\d.,\-]/', '', $s);

        if ($s === '' || $s === '-') {
            return 0.0;
        }

        $ultimoPonto   = strrpos($s, '.');
        $ultimaVirgula = strrpos($s, ',');

        if ($ultimoPonto === false && $ultimaVirgula === false) {
            return (float) $s;
        }

        // O separador decimal é o que aparece mais à direita.
        $decimalEhVirgula = ($ultimaVirgula !== false)
            && ($ultimoPonto === false || $ultimaVirgula > $ultimoPonto);

        if ($decimalEhVirgula) {
            $s = str_replace('.', '', $s);   // tira milhares
            $s = str_replace(',', '.', $s);  // vírgula → ponto decimal
        } else {
            $s = str_replace(',', '', $s);   // tira milhares
        }

        return is_numeric($s) ? (float) $s : 0.0;
    }
}
