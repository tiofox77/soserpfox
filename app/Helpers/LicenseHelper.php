<?php

use App\Services\Licensing\LicenseManager;
use App\Services\Licensing\LicenseState;

/*
 * Helpers globais de licença — para usar em Blade e nos componentes sem ter de
 * resolver o LicenseManager à mão. Todos respeitam o interruptor `enforce`:
 * com ele desligado (cloud), nada restringe.
 */

if (!function_exists('licenca_enforce')) {
    /** O bloqueio por licença está ligado? (só na build offline) */
    function licenca_enforce(): bool
    {
        return (bool) config('licensing.enforce', false);
    }
}

if (!function_exists('licenca_estado')) {
    /** O estado actual da licença (cacheado no processo). */
    function licenca_estado(): LicenseState
    {
        return app(LicenseManager::class)->estado();
    }
}

if (!function_exists('licenca_bloqueia_tudo')) {
    /** O sistema deve estar totalmente trancado? (false se enforce desligado) */
    function licenca_bloqueia_tudo(): bool
    {
        return licenca_enforce() && licenca_estado()->bloqueiaTudo();
    }
}

if (!function_exists('licenca_so_leitura')) {
    /** Estamos em modo só-leitura? (false se enforce desligado) */
    function licenca_so_leitura(): bool
    {
        if (!licenca_enforce()) {
            return false;
        }
        $e = licenca_estado();

        return $e->bloqueiaEscrita() && !$e->bloqueiaTudo();
    }
}

if (!function_exists('licenca_max_utilizadores')) {
    /**
     * Tecto de utilizadores imposto pela licença. 0 = sem tecto (é o que
     * devolve na cloud, com o enforce desligado).
     */
    function licenca_max_utilizadores(): int
    {
        if (!licenca_enforce()) {
            return 0;
        }

        return licenca_estado()->payload?->maxUtilizadores() ?? 0;
    }
}

if (!function_exists('licenca_tem_modulo')) {
    /**
     * Este módulo está licenciado? Com enforce desligado devolve sempre true
     * (na cloud quem manda nos módulos é o plano/tenant, não a licença).
     */
    function licenca_tem_modulo(string $modulo): bool
    {
        if (!licenca_enforce()) {
            return true;
        }
        $payload = licenca_estado()->payload;

        return $payload ? $payload->temModulo($modulo) : false;
    }
}
