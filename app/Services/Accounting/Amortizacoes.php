<?php

namespace App\Services\Accounting;

use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FixedAssetDepreciation;
use App\Models\Accounting\Period;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * AS AMORTIZAÇÕES DO IMOBILIZADO — o cálculo que nunca existiu.
 *
 * O ecrã em Livewire tinha um botão «Calcular Depreciações» cujo corpo era uma
 * mensagem: «Funcionalidade de cálculo de depreciações será implementada em
 * breve!». As tabelas estavam feitas desde 2025 — bens, categorias e a linha de
 * amortização por período, com sítio para o lançamento — e nada as usava.
 *
 * AS REGRAS QUE AQUI VIVEM:
 *
 *  · UMA LINHA POR BEM E POR MÊS. O mês é a unidade porque é a do período
 *    contabilístico: amortizar «por ano» obriga a decidir em que mês entra o
 *    gasto, e a resposta certa é «um doze avos em cada».
 *  · O RESIDUAL NÃO SE AMORTIZA. A base é o valor de aquisição menos o que se
 *    espera valer no fim; amortizar o valor todo punha no gasto dinheiro que o
 *    bem ainda vale.
 *  · NUNCA ABAIXO DO RESIDUAL. A última prestação é o que falta, não a
 *    prestação inteira — senão o valor líquido passa por baixo e o bem passa a
 *    valer menos do que a sucata.
 *  · NÃO SE AMORTIZA ANTES DE COMPRAR. O primeiro mês é o da aquisição.
 *  · CALCULAR NÃO É LANÇAR. As linhas nascem em rascunho; uma já LANÇADA nunca
 *    é tocada por um novo cálculo — o lançamento conta nos saldos e rectifica-se
 *    por estorno como qualquer outro.
 */
class Amortizacoes
{
    public function __construct(private Lancamentos $lancamentos) {}

    /** Os métodos que a coluna aceita. */
    public const METODOS = ['linear', 'declining_balance', 'units_of_production'];

    /**
     * Calcula as amortizações em falta de um bem até uma data.
     *
     * @return int quantas linhas ficaram criadas
     */
    public function calcular(FixedAsset $bem, string $ate): int
    {
        if ($bem->status !== 'active') {
            return 0;
        }

        $base = $bem->baseAmortizavel();

        if ($base <= 0 || (int) $bem->useful_life_years <= 0) {
            return 0;
        }

        $inicio = $bem->acquisition_date instanceof \DateTimeInterface
            ? Carbon::parse($bem->acquisition_date)->startOfMonth()
            : Carbon::parse((string) $bem->acquisition_date)->startOfMonth();

        $limite = Carbon::parse($ate)->endOfMonth();

        if ($limite->lt($inicio)) {
            return 0;
        }

        // Os meses que JÁ TÊM linha: um recálculo não pode duplicar nem repor
        // uma que já foi lançada.
        $existentes = FixedAssetDepreciation::where('fixed_asset_id', $bem->id)
            ->get(['id', 'depreciation_date', 'status'])
            ->keyBy(fn ($d) => Carbon::parse($d->depreciation_date)->format('Y-m'));

        $criadas = 0;

        // OS MESES QUE O BEM DURA. Passado o último, não há mais amortização —
        // nem no degressivo, onde a fórmula sozinha nunca chegaria a zero.
        $totalDeMeses = max(1, (int) $bem->useful_life_years * 12);

        DB::transaction(function () use ($bem, $inicio, $limite, $base, $existentes, $totalDeMeses, &$criadas) {
            // O acumulado parte do que já está LANÇADO OU EM RASCUNHO até ao
            // primeiro mês por calcular — a cascata tem de continuar de onde
            // ficou, senão cada linha recomeçava a contar.
            $acumulado = 0.0;
            $mes = $inicio->copy();
            $indice = 0;

            while ($mes->lte($limite) && $indice < $totalDeMeses) {
                $chave = $mes->format('Y-m');
                $jaExiste = $existentes[$chave] ?? null;

                $porAmortizar = round($base - $acumulado, 2);

                if ($porAmortizar <= 0) {
                    break;
                }

                /*
                 * O ÚLTIMO MÊS LEVA O QUE FALTA.
                 *
                 * Doze prestações de 83,33 dão 999,96 e não 1.000: os quatro
                 * cêntimos do arredondamento ficariam por amortizar para sempre
                 * e o bem nunca chegava a «totalmente amortizado». No degressivo
                 * é mais do que cêntimos — a fórmula nunca chega a zero — e a
                 * convenção é a mesma: o fim da vida útil fecha a conta.
                 */
                $prestacao = $indice === $totalDeMeses - 1
                    ? $porAmortizar
                    : min($porAmortizar, $this->prestacaoDoMes($bem, $base, $acumulado));

                if ($jaExiste) {
                    /*
                     * UMA LINHA LANÇADA É INTOCÁVEL — e o acumulado tem de
                     * seguir o que ELA diz, não o que o cálculo diria: se
                     * alguém lançou 1.000 onde a fórmula dá 900, o mês seguinte
                     * parte de 1.000.
                     */
                    $linha = FixedAssetDepreciation::find($jaExiste->id);

                    if ($linha->status === 'posted') {
                        $acumulado = round($acumulado + (float) $linha->depreciation_amount, 2);
                        $mes->addMonth();
                        $indice++;

                        continue;
                    }

                    // Em rascunho, actualiza-se: a vida útil ou o método podem
                    // ter sido corrigidos desde o último cálculo.
                    $acumulado = round($acumulado + $prestacao, 2);

                    $linha->update([
                        'period_id' => $this->periodoDoMes($bem, $mes) ?? $linha->period_id,
                        'depreciation_amount' => $prestacao,
                        'accumulated_depreciation' => $acumulado,
                        'book_value' => round((float) $bem->acquisition_value - $acumulado, 2),
                    ]);

                    $mes->addMonth();
                    $indice++;

                    continue;
                }

                $periodoId = $this->periodoDoMes($bem, $mes);

                /*
                 * SEM PERÍODO NÃO HÁ LINHA.
                 *
                 * A coluna `period_id` é obrigatória na base, e com razão: uma
                 * amortização pertence a um período contabilístico. Quando o mês
                 * não tem período montado, salta-se — e o ecrã diz quantos
                 * meses ficaram de fora e porquê.
                 */
                if (! $periodoId) {
                    $mes->addMonth();
                    $indice++;

                    continue;
                }

                $acumulado = round($acumulado + $prestacao, 2);

                FixedAssetDepreciation::create([
                    'fixed_asset_id' => $bem->id,
                    'period_id' => $periodoId,
                    'depreciation_date' => $mes->copy()->endOfMonth()->format('Y-m-d'),
                    'depreciation_amount' => $prestacao,
                    'accumulated_depreciation' => $acumulado,
                    'book_value' => round((float) $bem->acquisition_value - $acumulado, 2),
                    'status' => 'draft',
                ]);

                $criadas++;
                $mes->addMonth();
                $indice++;
            }

            $this->actualizarOBem($bem);
        });

        return $criadas;
    }

    /**
     * A prestação de um mês, pelo método do bem.
     *
     * LINEAR: a base a dividir pelos meses de vida útil — a mesma prestação
     * todos os meses.
     *
     * DEGRESSIVO (`declining_balance`): uma taxa ANUAL sobre o valor que ainda
     * falta amortizar, dividida por doze. Amortiza mais no princípio, que é o
     * que se usa em bens que perdem valor depressa.
     *
     * POR UNIDADES (`units_of_production`): depende da produção do mês, que o
     * sistema não regista em lado nenhum. Trata-se como linear e diz-se — a
     * alternativa era inventar um número.
     */
    private function prestacaoDoMes(FixedAsset $bem, float $base, float $acumulado): float
    {
        $meses = max(1, (int) $bem->useful_life_years * 12);

        if ($bem->depreciation_method === 'declining_balance') {
            // Sem taxa escrita, usa-se o dobro da linear — a convenção do
            // «duplo degressivo».
            $taxa = (float) ($bem->depreciation_rate ?: (200 / max(1, (int) $bem->useful_life_years)));

            return round(max(0.0, ($base - $acumulado) * ($taxa / 100) / 12), 2);
        }

        return round($base / $meses, 2);
    }

    /** O período contabilístico onde o fim daquele mês cai. */
    private function periodoDoMes(FixedAsset $bem, Carbon $mes): ?int
    {
        $dia = $mes->copy()->endOfMonth()->format('Y-m-d');

        return Period::where('tenant_id', $bem->tenant_id)
            ->where('date_start', '<=', $dia)
            ->where('date_end', '>=', $dia)
            ->value('id');
    }

    /**
     * O acumulado, o valor líquido e o estado do bem saem SEMPRE das linhas.
     *
     * Guardá-los no bem é conveniência de leitura; a verdade são as linhas. Se
     * divergirem, é a soma que manda — foi por isso que a tesouraria ficou com
     * uma porta só para o saldo.
     */
    public function actualizarOBem(FixedAsset $bem): void
    {
        $acumulado = round((float) FixedAssetDepreciation::where('fixed_asset_id', $bem->id)
            ->sum('depreciation_amount'), 2);

        $base = $bem->baseAmortizavel();

        $bem->update([
            'accumulated_depreciation' => $acumulado,
            'book_value' => round((float) $bem->acquisition_value - $acumulado, 2),
            // TOTALMENTE AMORTIZADO quando já não falta nada — e só se o bem
            // ainda estava activo: um bem vendido não volta a este estado.
            'status' => $bem->status === 'active' && $base > 0 && $acumulado >= $base - 0.01
                ? 'fully_depreciated'
                : $bem->status,
        ]);
    }

    /**
     * LANÇAR A AMORTIZAÇÃO na contabilidade.
     *
     * Débito na conta de GASTO, crédito na de AMORTIZAÇÕES ACUMULADAS — que é a
     * que desconta o activo no balanço. Passa pela porta única dos lançamentos,
     * pelo que herda todas as guardas: período aberto, data dentro do período,
     * contas que recebem movimento.
     */
    public function lancar(FixedAssetDepreciation $linha, int $autorId): FixedAssetDepreciation
    {
        if ($linha->status === 'posted') {
            throw ValidationException::withMessages([
                'geral' => [__('Esta amortização já está lançada.')],
            ]);
        }

        $bem = $linha->asset;

        if (! $bem) {
            throw ValidationException::withMessages(['geral' => [__('Bem não encontrado.')]]);
        }

        if ((float) $linha->depreciation_amount <= 0) {
            throw ValidationException::withMessages([
                'geral' => [__('Uma amortização de zero não se lança.')],
            ]);
        }

        $diario = \App\Models\Accounting\Journal::where('tenant_id', $bem->tenant_id)
            ->where('active', true)
            ->orderByRaw("FIELD(type, 'adjustment', 'general') DESC")
            ->first();

        if (! $diario) {
            throw ValidationException::withMessages([
                'geral' => [__('Não há nenhum diário activo onde lançar a amortização.')],
            ]);
        }

        $dia = Carbon::parse($linha->depreciation_date)->format('Y-m-d');

        $move = $this->lancamentos->criar([
            'journal_id' => $diario->id,
            'period_id' => $linha->period_id,
            'date' => $dia,
            'ref' => '',
            'narration' => __('Amortização de :bem — :mes', [
                'bem' => $bem->code.' · '.$bem->name,
                'mes' => Carbon::parse($dia)->translatedFormat('F Y'),
            ]),
        ], [
            [
                'account_id' => $bem->depreciation_account_id,
                'debit' => (float) $linha->depreciation_amount,
                'credit' => 0,
                'narration' => __('Amortização do período'),
            ],
            [
                'account_id' => $bem->accumulated_depreciation_account_id,
                'debit' => 0,
                'credit' => (float) $linha->depreciation_amount,
                'narration' => __('Amortizações acumuladas'),
            ],
        ], (int) $bem->tenant_id, $autorId);

        $this->lancamentos->confirmar($move->load(['lines', 'period']), $autorId);

        $linha->update(['move_id' => $move->id, 'status' => 'posted']);

        return $linha->fresh();
    }
}
