<?php

namespace App\Services\Projetos;

use App\Models\Projetos\HoraLancada;
use App\Models\Projetos\Projeto;
use App\Models\Projetos\Tarefa;

/**
 * Lançar, corrigir e apagar horas de projeto.
 *
 * Duas regras mandam aqui:
 *
 * 1. **O preço congela no lançamento.** Facturar em Outubro o trabalho de
 *    Agosto tem de pagar o preço de Agosto — senão uma actualização da tabela
 *    reescrevia o passado por trás de quem o trabalhou.
 * 2. **Hora facturada é história.** Depois de sustentar uma factura emitida,
 *    a linha não se edita nem se apaga. Corrige-se pelos documentos, não por
 *    baixo deles.
 */
class RegistoDeHoras
{
    /** Um dia de trabalho não tem 25 horas — e um engano de tecla custa dinheiro. */
    public const MAX_HORAS_POR_LINHA = 24.0;

    public function lancar(int $tenantId, int $userId, array $dados): HoraLancada
    {
        $projeto = $this->projetoDaCasa($dados['projeto_id'] ?? null, $tenantId);
        $horas = round((float) ($dados['horas'] ?? 0), 2);

        $this->validarHoras($horas);

        if (! $projeto->aceitaHoras()) {
            throw new \InvalidArgumentException(
                "Não se lançam horas num projeto {$projeto->estadoRotulo()}. Reactive-o primeiro."
            );
        }

        $tarefaId = $this->tarefaDoProjeto($dados['tarefa_id'] ?? null, $projeto);

        return HoraLancada::create([
            'tenant_id' => $tenantId,
            'projeto_id' => $projeto->id,
            'tarefa_id' => $tarefaId,
            'user_id' => $dados['user_id'] ?? $userId,
            'data' => $dados['data'] ?? now()->toDateString(),
            'horas' => $horas,
            'descricao' => trim((string) ($dados['descricao'] ?? '')) ?: null,
            'facturavel' => (bool) ($dados['facturavel'] ?? true),
            // Congela AQUI. Sem preço no projeto, a hora vale zero e não vai a
            // factura nenhuma — melhor do que inventar um preço.
            'valor_hora' => $dados['valor_hora'] ?? $projeto->valor_hora,
        ]);
    }

    public function actualizar(HoraLancada $linha, int $tenantId, array $dados): HoraLancada
    {
        $this->minha($linha, $tenantId);
        $this->naoFacturada($linha);

        $horas = round((float) ($dados['horas'] ?? $linha->horas), 2);
        $this->validarHoras($horas);

        $linha->update([
            'data' => $dados['data'] ?? $linha->data,
            'horas' => $horas,
            'descricao' => trim((string) ($dados['descricao'] ?? '')) ?: null,
            'facturavel' => (bool) ($dados['facturavel'] ?? $linha->facturavel),
            'tarefa_id' => array_key_exists('tarefa_id', $dados)
                ? $this->tarefaDoProjeto($dados['tarefa_id'], $linha->projeto)
                : $linha->tarefa_id,
        ]);

        return $linha->fresh();
    }

    /**
     * Apagar uma linha por facturar é legítimo — foi engano de quem a lançou,
     * e ainda não sustenta nada. Depois de facturada, já não.
     */
    public function apagar(HoraLancada $linha, int $tenantId): void
    {
        $this->minha($linha, $tenantId);
        $this->naoFacturada($linha);

        $linha->delete();
    }

    private function validarHoras(float $horas): void
    {
        if ($horas <= 0) {
            throw new \InvalidArgumentException('As horas têm de ser maiores do que zero.');
        }

        if ($horas > self::MAX_HORAS_POR_LINHA) {
            throw new \InvalidArgumentException(
                'Um lançamento não pode ter mais de '.self::MAX_HORAS_POR_LINHA.' horas. Divida por dias.'
            );
        }
    }

    private function naoFacturada(HoraLancada $linha): void
    {
        if ($linha->jaFacturada()) {
            throw new \InvalidArgumentException(
                'Esta hora já foi facturada — está a sustentar um documento emitido. Corrija pela factura.'
            );
        }
    }

    private function projetoDaCasa($projetoId, int $tenantId): Projeto
    {
        $projeto = Projeto::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->whereKey($projetoId)->first();

        if (! $projeto) {
            throw new \InvalidArgumentException('Projeto desconhecido nesta empresa.');
        }

        return $projeto;
    }

    /** Uma tarefa de OUTRO projeto não pode receber estas horas. */
    private function tarefaDoProjeto($tarefaId, Projeto $projeto): ?int
    {
        if (! $tarefaId) {
            return null;
        }

        $existe = Tarefa::withoutGlobalScopes()
            ->where('tenant_id', $projeto->tenant_id)
            ->where('projeto_id', $projeto->id)
            ->whereKey($tarefaId)
            ->exists();

        if (! $existe) {
            throw new \InvalidArgumentException('Essa tarefa não é deste projeto.');
        }

        return (int) $tarefaId;
    }

    private function minha(HoraLancada $linha, int $tenantId): void
    {
        if ((int) $linha->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Lançamento de outra empresa.');
        }
    }
}
