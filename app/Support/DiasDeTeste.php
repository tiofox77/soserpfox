<?php

namespace App\Support;

use App\Models\Plan;
use Illuminate\Support\Str;

/**
 * OS DIAS DE TESTE, DITOS COMO SÃO (26/09/2026).
 *
 * As páginas comerciais escreviam «14 dias grátis» à mão, e o Pacote Hotel dá
 * 30: quem vinha do anúncio do hotel lia uma coisa na página e outra no
 * registo. A frase genérica sai agora dos planos pagos da montra: os dias
 * mais comuns, e as excepções pelo nome — «14 dias grátis (30 no Hotel)».
 * Muda sozinha quando os planos mudarem.
 */
final class DiasDeTeste
{
    private static ?string $frase = null;

    /** «14 dias grátis (30 no Hotel)», ou «Teste grátis» se nenhum plano tiver dias. */
    public static function frase(): string
    {
        if (self::$frase !== null) {
            return self::$frase;
        }

        try {
            // Os planos da montra, pagos e com teste (o gratuito não entra: não
            // é um teste, é o plano).
            $planos = Plan::publico()
                ->where('price_monthly', '>', 0)
                ->where('trial_days', '>', 0)
                ->get(['name', 'trial_days']);
        } catch (\Throwable) {
            return self::$frase = 'Teste grátis';
        }

        if ($planos->isEmpty()) {
            return self::$frase = 'Teste grátis';
        }

        $comum = (int) $planos->countBy('trial_days')->sortDesc()->keys()->first();

        $excepcoes = $planos
            ->filter(fn ($p) => (int) $p->trial_days !== $comum)
            ->sortBy('trial_days')
            ->map(fn ($p) => (int) $p->trial_days . ' no ' . self::nomeCurto((string) $p->name))
            ->unique()
            ->values();

        return self::$frase = $comum . ' dias grátis' . ($excepcoes->isEmpty() ? '' : ' (' . $excepcoes->implode(', ') . ')');
    }

    /** «🏨 Pacote Hotel» → «Hotel». */
    private static function nomeCurto(string $nome): string
    {
        $limpo = trim(preg_replace('/^[^\p{L}]+/u', '', $nome) ?? $nome);

        return Str::of($limpo)->replaceStart('Pacote ', '')->trim()->toString() ?: $nome;
    }

    /** Para os testes: esquecer a frase guardada neste pedido. */
    public static function esquecer(): void
    {
        self::$frase = null;
    }
}
