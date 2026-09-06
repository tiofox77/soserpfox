<?php

namespace App\Services\Invoicing\Relatorios;

use Carbon\Carbon;

/**
 * O PERÍODO DE UM RELATÓRIO: um atalho (hoje, semana, mês, trimestre, ano)
 * ou duas datas escritas à mão. É o que o trait HasReportFilters fazia
 * dentro de cada ecrã Livewire, agora num sítio só, para os dois ecrãs.
 */
final class Periodo
{
    public const ATALHOS = [
        'today' => 'Hoje',
        'week' => 'Esta semana',
        'month' => 'Este mês',
        'quarter' => 'Este trimestre',
        'year' => 'Este ano',
        'ytd' => 'Este ano até hoje',
        'custom' => 'Personalizado',
    ];

    /** As datas de um atalho. */
    public static function datas(string $atalho): array
    {
        $agora = Carbon::now();

        return match ($atalho) {
            'today' => [$agora->copy()->startOfDay(), $agora->copy()->endOfDay()],
            'week' => [$agora->copy()->startOfWeek(), $agora->copy()->endOfWeek()],
            'quarter' => [$agora->copy()->startOfQuarter(), $agora->copy()->endOfQuarter()],
            'year' => [$agora->copy()->startOfYear(), $agora->copy()->endOfYear()],
            // Um extracto de conta corrente lê-se para trás, não para o mês.
            'ytd' => [$agora->copy()->startOfYear(), $agora->copy()->endOfDay()],
            default => [$agora->copy()->startOfMonth(), $agora->copy()->endOfMonth()],
        };
    }

    /**
     * O intervalo efectivo: as datas que vieram, ou o atalho pedido, ou o
     * atalho por omissão do relatório. Sempre [de, ate] em Y-m-d.
     */
    public static function intervalo(?string $atalho, ?string $de, ?string $ate, string $omissao = 'month'): array
    {
        if ($de && $ate) {
            $de = Carbon::parse($de)->format('Y-m-d');
            $ate = Carbon::parse($ate)->format('Y-m-d');

            // Fim antes do início devolve mapas vazios e parece avaria:
            // troca-se em silêncio, que é o que a pessoa queria dizer.
            return $de <= $ate ? [$de, $ate] : [$ate, $de];
        }

        $atalho = $atalho && $atalho !== 'custom' && isset(self::ATALHOS[$atalho]) ? $atalho : $omissao;
        [$inicio, $fim] = self::datas($atalho);

        return [$inicio->format('Y-m-d'), $fim->format('Y-m-d')];
    }
}
