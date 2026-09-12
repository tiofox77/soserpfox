<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use App\Models\Accounting\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * AS REGRAS DE UM LANÇAMENTO CONTABILÍSTICO.
 *
 * Estavam espalhadas pelo ecrã em Livewire, e metade não estava em lado nenhum.
 * O que aqui vive são as que um contabilista dá por certas e que o sistema
 * deixava passar:
 *
 *  · UM LANÇAMENTO EQUILIBRA. Débito igual a crédito — isto já se verificava.
 *  · MAS NÃO PODE SER TUDO ZERO. Zero é igual a zero, e um lançamento vazio
 *    passava a verificação do equilíbrio como se fosse bom.
 *  · UMA LINHA É DÉBITO OU CRÉDITO, nunca as duas. Uma linha com os dois é uma
 *    linha que ninguém sabe ler — e que na razão da conta aparece duas vezes.
 *  · NÃO SE LANÇA NUMA CONTA DE AGREGAÇÃO. Uma conta `is_view` existe para
 *    somar as filhas; pôr-lhe movimento próprio conta o valor duas vezes no
 *    balanço.
 *  · A DATA CAI DENTRO DO PERÍODO. Um lançamento datado fora do seu período é
 *    um lançamento que nenhum relatório encontra onde devia estar.
 *  · UM PERÍODO FECHADO NÃO RECEBE NADA — nem ao criar nem ao confirmar. Era
 *    verificado ao criar e não ao confirmar: um rascunho de Janeiro confirmado
 *    em Março entrava num período já fechado e reescrevia contas encerradas.
 *  · UM LANÇAMENTO CONFIRMADO NÃO SE APAGA. É o registo contabilístico; apagá-lo
 *    reescreve a história em silêncio. Rectifica-se por ESTORNO, que é um
 *    lançamento novo e simétrico — e fica o rasto dos dois.
 */
class Lancamentos
{
    /** As naturezas que crescem a débito. As outras crescem a crédito. */
    public const A_DEBITO = ['asset', 'expense'];

    private function recusa(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => [$mensagem]]);
    }

    /**
     * A REFERÊNCIA SEGUINTE DO DIÁRIO, sem duas iguais.
     *
     * O ecrã lia `last_number + 1` ao escolher o diário e só o incrementava ao
     * gravar: dois utilizadores a lançar ao mesmo tempo levavam a MESMA
     * referência, e uma referência que se repete é um documento que ninguém
     * distingue do outro. Aqui o número sai sob tranca, no momento de gravar.
     */
    public function proximaReferencia(Journal $diario): string
    {
        return DB::transaction(function () use ($diario) {
            $bloqueado = Journal::whereKey($diario->id)->lockForUpdate()->firstOrFail();

            $prefixo = $bloqueado->sequence_prefix ?: 'DG-';
            $numero = (int) $bloqueado->last_number;

            /*
             * A REFERÊNCIA É ÚNICA POR EMPRESA, e o contador é POR DIÁRIO.
             *
             * O prefixo é texto livre: dois diários da mesma empresa podem
             * nascer com o mesmo («DG-»), cada um com o seu contador — e o
             * segundo a chegar ao número 1 batia no índice único
             * `(tenant_id, ref)` com um 500 e um lançamento perdido. Aqui
             * salta-se o que já está tomado em vez de estoirar.
             */
            do {
                $numero++;
                $ref = $prefixo.str_pad((string) $numero, 5, '0', STR_PAD_LEFT);

                $tomada = Move::withoutGlobalScopes()
                    ->where('tenant_id', $bloqueado->tenant_id)
                    ->where('ref', $ref)
                    ->exists();
            } while ($tomada);

            $bloqueado->update(['last_number' => $numero]);

            return $ref;
        });
    }

    /**
     * Cria um lançamento com as suas linhas.
     *
     * @param  array<int,array{account_id:int, debit:float, credit:float, narration?:string}>  $linhas
     */
    public function criar(array $dados, array $linhas, int $tenantId, int $autorId): Move
    {
        $diario = Journal::findOrFail($dados['journal_id']);
        $periodo = Period::findOrFail($dados['period_id']);

        $this->exigirPeriodoAberto($periodo);
        $this->exigirDataNoPeriodo($dados['date'], $periodo);

        $linhas = $this->validarLinhas($linhas);

        $debito = round(collect($linhas)->sum('debit'), 2);
        $credito = round(collect($linhas)->sum('credit'), 2);

        return DB::transaction(function () use ($dados, $linhas, $tenantId, $autorId, $diario, $debito, $credito) {
            $move = Move::create([
                'tenant_id' => $tenantId,
                'journal_id' => $diario->id,
                'period_id' => $dados['period_id'],
                'document_type_id' => $dados['document_type_id'] ?? null,
                'date' => $dados['date'],
                // A referência sai do diário AGORA — não ao abrir o formulário.
                // Vazia OU ausente quer dizer «dá-me a seguinte»: um campo
                // opcional que não vem não chega ao array validado.
                'ref' => ($dados['ref'] ?? null) ?: $this->proximaReferencia($diario),
                'narration' => $dados['narration'] ?? null,
                'state' => 'draft',
                'total_debit' => $debito,
                'total_credit' => $credito,
                'created_by' => $autorId,
            ]);

            foreach ($linhas as $linha) {
                MoveLine::create([
                    'tenant_id' => $tenantId,
                    'move_id' => $move->id,
                    'account_id' => $linha['account_id'],
                    'debit' => $linha['debit'],
                    'credit' => $linha['credit'],
                    'balance' => round($linha['debit'] - $linha['credit'], 2),
                    'narration' => $linha['narration'] ?? null,
                ]);
            }

            return $move;
        });
    }

    /**
     * CONFIRMAR.
     *
     * Verifica-se OUTRA VEZ o período e o equilíbrio: entre criar o rascunho e
     * confirmá-lo pode ter passado um mês e o período pode ter fechado.
     */
    public function confirmar(Move $move, int $utilizadorId): Move
    {
        if ($move->state === 'posted') {
            $this->recusa('geral', __('Este lançamento já está confirmado.'));
        }

        $periodo = $move->period;

        if ($periodo) {
            $this->exigirPeriodoAberto($periodo);
            $this->exigirDataNoPeriodo($move->date->format('Y-m-d'), $periodo);
        }

        $linhas = $move->lines->map(fn ($l) => [
            'account_id' => $l->account_id,
            'debit' => (float) $l->debit,
            'credit' => (float) $l->credit,
        ])->all();

        // Se alguém mexeu nas linhas na base, ou se a conta passou a ser de
        // agregação, o erro aparece aqui — antes de entrar na contabilidade.
        $this->validarLinhas($linhas);

        $move->update([
            'state' => 'posted',
            'posted_by' => $utilizadorId,
            'posted_at' => now(),
        ]);

        return $move->fresh();
    }

    /**
     * APAGAR — só um rascunho.
     *
     * Um lançamento confirmado é o registo contabilístico. Apagá-lo reescreve a
     * história sem deixar rasto, e um balanço que muda sozinho é um balanço em
     * que ninguém pode confiar. Rectifica-se por estorno.
     */
    public function apagar(Move $move): void
    {
        if ($move->state === 'posted') {
            $this->recusa('geral', __('Um lançamento confirmado não se apaga — rectifica-se por estorno.'));
        }

        DB::transaction(function () use ($move) {
            $move->lines()->delete();
            $move->delete();
        });
    }

    /**
     * O ESTORNO: um lançamento novo e simétrico.
     *
     * É como se rectifica um lançamento confirmado. O original fica intacto — é
     * essa a diferença entre corrigir e apagar: ficam os DOIS, e a razão da
     * conta mostra o que se lançou e o que se desfez.
     */
    public function estornar(Move $move, int $autorId, ?string $data = null, ?int $periodoId = null): Move
    {
        if ($move->state !== 'posted') {
            $this->recusa('geral', __('Só se estorna um lançamento confirmado. Um rascunho apaga-se.'));
        }

        $periodo = $periodoId ? Period::findOrFail($periodoId) : $move->period;

        if (! $periodo) {
            $this->recusa('period_id', __('Escolha o período onde o estorno entra.'));
        }

        $this->exigirPeriodoAberto($periodo);

        $dia = $data ?: max(
            $move->date->format('Y-m-d'),
            $periodo->date_start instanceof \DateTimeInterface
                ? $periodo->date_start->format('Y-m-d')
                : (string) $periodo->date_start,
        );

        $this->exigirDataNoPeriodo($dia, $periodo);

        // DÉBITO E CRÉDITO TROCADOS: é isso, e só isso, um estorno.
        $linhas = $move->lines->map(fn ($l) => [
            'account_id' => $l->account_id,
            'debit' => (float) $l->credit,
            'credit' => (float) $l->debit,
            'narration' => $l->narration,
        ])->all();

        $estorno = $this->criar([
            'journal_id' => $move->journal_id,
            'period_id' => $periodo->id,
            'document_type_id' => $move->document_type_id,
            'date' => $dia,
            'ref' => '',
            'narration' => __('Estorno de :ref. :nota', [
                'ref' => $move->ref, 'nota' => (string) $move->narration,
            ]),
        ], $linhas, (int) $move->tenant_id, $autorId);

        // Um estorno nasce confirmado: rectificar e deixar a rectificação em
        // rascunho era ficar com o erro de pé e a correcção guardada na gaveta.
        return $this->confirmar($estorno, $autorId);
    }

    /* ─── As guardas ──────────────────────────────────────────────────── */

    private function exigirPeriodoAberto(Period $periodo): void
    {
        if ($periodo->state !== 'open') {
            $this->recusa('period_id', __('O período :nome está fechado e não recebe lançamentos.', [
                'nome' => $periodo->name ?: $periodo->id,
            ]));
        }
    }

    private function exigirDataNoPeriodo(string $data, Period $periodo): void
    {
        $inicio = $periodo->date_start instanceof \DateTimeInterface
            ? $periodo->date_start->format('Y-m-d') : (string) $periodo->date_start;
        $fim = $periodo->date_end instanceof \DateTimeInterface
            ? $periodo->date_end->format('Y-m-d') : (string) $periodo->date_end;

        if ($inicio && $data < $inicio || $fim && $data > $fim) {
            $this->recusa('date', __('A data tem de cair dentro do período (:inicio a :fim).', [
                'inicio' => $inicio, 'fim' => $fim,
            ]));
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $linhas
     * @return array<int,array<string,mixed>>
     */
    private function validarLinhas(array $linhas): array
    {
        $limpas = [];

        foreach ($linhas as $i => $linha) {
            $debito = round((float) ($linha['debit'] ?? 0), 2);
            $credito = round((float) ($linha['credit'] ?? 0), 2);

            // UMA LINHA VAZIA não é uma linha: deita-se fora em silêncio, que é
            // o que o formulário faz com a última linha por preencher.
            if (empty($linha['account_id']) && $debito === 0.0 && $credito === 0.0) {
                continue;
            }

            if (empty($linha['account_id'])) {
                $this->recusa("lines.{$i}.account_id", __('Escolha a conta desta linha.'));
            }

            if ($debito < 0 || $credito < 0) {
                $this->recusa("lines.{$i}.debit", __('Um valor negativo não é um lançamento — troque de coluna.'));
            }

            /*
             * DÉBITO OU CRÉDITO, NUNCA OS DOIS.
             *
             * Uma linha com os dois aparece duas vezes na razão da conta e
             * ninguém sabe qual delas é a verdadeira.
             */
            if ($debito > 0 && $credito > 0) {
                $this->recusa("lines.{$i}.debit", __('Uma linha é a débito OU a crédito, nunca as duas.'));
            }

            if ($debito === 0.0 && $credito === 0.0) {
                $this->recusa("lines.{$i}.debit", __('Uma linha sem valor não lança nada.'));
            }

            $conta = Account::find($linha['account_id']);

            if (! $conta) {
                $this->recusa("lines.{$i}.account_id", __('Conta não encontrada nesta empresa.'));
            }

            if ($conta->blocked) {
                $this->recusa("lines.{$i}.account_id", __('A conta :conta está bloqueada.', [
                    'conta' => $conta->code.' · '.$conta->name,
                ]));
            }

            /*
             * NÃO SE LANÇA NUMA CONTA DE AGREGAÇÃO.
             *
             * Uma conta `is_view` existe para somar as filhas. Dar-lhe movimento
             * próprio conta o valor duas vezes no balanço — uma na conta e outra
             * no total que ela devia representar.
             */
            if ($conta->is_view) {
                $this->recusa("lines.{$i}.account_id", __('A conta :conta é de agregação: some as filhas e não recebe movimento.', [
                    'conta' => $conta->code.' · '.$conta->name,
                ]));
            }

            $limpas[] = [
                'account_id' => (int) $conta->id,
                'debit' => $debito,
                'credit' => $credito,
                'narration' => $linha['narration'] ?? null,
            ];
        }

        if (count($limpas) < 2) {
            $this->recusa('lines', __('Um lançamento tem pelo menos duas linhas: de onde sai e para onde vai.'));
        }

        $debito = round(collect($limpas)->sum('debit'), 2);
        $credito = round(collect($limpas)->sum('credit'), 2);

        /*
         * E NÃO PODE SER TUDO ZERO.
         *
         * Zero é igual a zero: um lançamento vazio passava a verificação do
         * equilíbrio como se fosse bom, e ficava na contabilidade a dizer nada.
         */
        if ($debito === 0.0 && $credito === 0.0) {
            $this->recusa('lines', __('Um lançamento sem valor nenhum não equilibra — não lança.'));
        }

        if (abs($debito - $credito) > 0.01) {
            $this->recusa('lines', __('O débito (:debito) e o crédito (:credito) não batem certo.', [
                'debito' => number_format($debito, 2, ',', '.'),
                'credito' => number_format($credito, 2, ',', '.'),
            ]));
        }

        return $limpas;
    }
}
