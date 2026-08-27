<?php

namespace App\Services\HR;

use App\Models\HR\Payroll;
use App\Models\HR\PayrollItem;
use Illuminate\Support\Collection;

/**
 * O mapa de IRT retido num mês: quem, sobre quanto, e quanto se entrega à AGT.
 *
 * O IRT já é calculado e guardado linha a linha no processamento (irt_base,
 * irt_rate, irt_amount). Isto NÃO recalcula nada — junta o que foi retido e
 * apresenta-o na forma em que se declara e se paga. Recalcular aqui abriria a
 * porta a o mapa dizer um valor e os recibos dos trabalhadores dizerem outro.
 *
 * QUE FOLHAS CONTAM: as aprovadas e as pagas. Um rascunho ainda vai mudar, e
 * uma folha anulada não gerou retenção nenhuma — declarar qualquer uma delas
 * seria declarar imposto que não foi retido.
 */
class MapaDeIRT
{
    /** Estados de folha que representam retenção real. */
    public const ESTADOS_VALIDOS = ['approved', 'paid'];

    /**
     * @return array{
     *   linhas: Collection, totais: array, folhas: Collection,
     *   ignoradas: Collection, periodo: string
     * }
     */
    public function paraMes(int $tenantId, int $ano, int $mes, array $filtros = []): array
    {
        $folhas = Payroll::where('tenant_id', $tenantId)
            ->where('year', $ano)
            ->where('month', $mes)
            ->orderBy('payroll_number')
            ->get();

        $validas = $folhas->whereIn('status', self::ESTADOS_VALIDOS);

        // As que ficam de fora são MOSTRADAS, não escondidas: um rascunho por
        // aprovar é a razão nº1 de o mapa não bater com o que a contabilidade
        // espera, e descobrir isso depois de entregar é tarde.
        $ignoradas = $folhas->whereNotIn('status', self::ESTADOS_VALIDOS);

        $linhas = $validas->isEmpty()
            ? collect()
            : $this->linhasDasFolhas($validas->pluck('id')->all(), $filtros);

        return [
            'linhas'    => $linhas,
            'totais'    => $this->totais($linhas),
            'folhas'    => $validas->values(),
            'ignoradas' => $ignoradas->values(),
            'periodo'   => str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '/' . $ano,
        ];
    }

    /**
     * Uma linha por trabalhador, com o mês inteiro somado.
     *
     * Hoje o esquema já garante uma linha só por pessoa e por mês: `hr_payrolls`
     * tem índice único (empresa, ano, mês) e `hr_payroll_items` tem único
     * (folha, trabalhador). O 13.º e o subsídio de férias vêm em colunas da
     * mesma linha, não numa folha à parte.
     *
     * A soma fica na mesma porque é o que o mapa precisa de dizer — o total
     * retido À PESSOA no período — e continua certa no dia em que algum desses
     * limites cair (uma folha extraordinária, por exemplo). O que não pode
     * acontecer nunca é o mesmo trabalhador sair duas vezes com dois IRT.
     */
    private function linhasDasFolhas(array $idsDasFolhas, array $filtros): Collection
    {
        $itens = PayrollItem::with(['employee.department', 'employee.position'])
            ->whereIn('payroll_id', $idsDasFolhas)
            ->when(!empty($filtros['departamento']), fn ($q) => $q->whereHas(
                'employee',
                fn ($e) => $e->where('department_id', $filtros['departamento'])
            ))
            ->get();

        return $itens
            ->groupBy('employee_id')
            ->map(function (Collection $doTrabalhador) {
                $primeiro = $doTrabalhador->first();
                $funcionario = $primeiro->employee;

                $bruto = (float) $doTrabalhador->sum('gross_salary');
                $inss  = (float) $doTrabalhador->sum('inss_employee');
                $base  = (float) $doTrabalhador->sum('irt_base');
                $irt   = (float) $doTrabalhador->sum('irt_amount');

                return [
                    'employee_id'  => $primeiro->employee_id,
                    'numero'       => $funcionario->employee_number ?? '',
                    'nome'         => $funcionario->full_name
                        ?? trim(($funcionario->first_name ?? '') . ' ' . ($funcionario->last_name ?? '')),
                    'nif'          => $funcionario->nif ?? '',
                    'seguranca'    => $funcionario->social_security_number ?? '',
                    'departamento' => $funcionario->department->name ?? '',
                    'bruto'        => $bruto,
                    'inss'         => $inss,
                    'base'         => $base,
                    'irt'          => $irt,
                    // A taxa EFECTIVA sobre a matéria colectável. A coluna
                    // irt_rate é a do escalão, e com duas folhas no mesmo mês
                    // as duas taxas não se somam nem se podem tirar à média.
                    'taxa'         => $base > 0 ? round($irt / $base * 100, 2) : 0.0,
                    'isento'       => $irt <= 0,
                    'folhas'       => $doTrabalhador->count(),
                ];
            })
            ->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function totais(Collection $linhas): array
    {
        return [
            'trabalhadores' => $linhas->count(),
            'tributados'    => $linhas->where('isento', false)->count(),
            'isentos'       => $linhas->where('isento', true)->count(),
            'bruto'         => (float) $linhas->sum('bruto'),
            'inss'          => (float) $linhas->sum('inss'),
            'base'          => (float) $linhas->sum('base'),
            'irt'           => (float) $linhas->sum('irt'),
        ];
    }

    /**
     * O mesmo mapa em CSV, para quem tem de o carregar noutro sítio.
     *
     * Vai com BOM: sem ele o Excel em Windows lê os acentos como lixo e um
     * mapa com "Joo Antnio" não serve para entregar a lado nenhum.
     */
    public function csv(array $mapa, string $empresa, string $nif): string
    {
        $linhas = [];
        $linhas[] = ['Mapa de IRT - ' . $empresa, 'NIF: ' . $nif, 'Periodo: ' . $mapa['periodo']];
        $linhas[] = [];
        $linhas[] = ['N.', 'Nome', 'NIF', 'Seguranca Social', 'Departamento',
                     'Remuneracao bruta', 'INSS 3%', 'Materia colectavel', 'Taxa efectiva %', 'IRT retido'];

        foreach ($mapa['linhas'] as $l) {
            $linhas[] = [
                $l['numero'], $l['nome'], $l['nif'], $l['seguranca'], $l['departamento'],
                number_format($l['bruto'], 2, ',', ''),
                number_format($l['inss'], 2, ',', ''),
                number_format($l['base'], 2, ',', ''),
                number_format($l['taxa'], 2, ',', ''),
                number_format($l['irt'], 2, ',', ''),
            ];
        }

        $t = $mapa['totais'];
        $linhas[] = [];
        $linhas[] = ['TOTAL', $t['trabalhadores'] . ' trabalhador(es)', '', '', '',
                     number_format($t['bruto'], 2, ',', ''),
                     number_format($t['inss'], 2, ',', ''),
                     number_format($t['base'], 2, ',', ''),
                     '',
                     number_format($t['irt'], 2, ',', '')];

        $saida = "\xEF\xBB\xBF";   // BOM UTF-8
        foreach ($linhas as $linha) {
            $saida .= implode(';', array_map(
                fn ($c) => '"' . str_replace('"', '""', (string) $c) . '"',
                $linha
            )) . "\r\n";
        }

        return $saida;
    }
}
