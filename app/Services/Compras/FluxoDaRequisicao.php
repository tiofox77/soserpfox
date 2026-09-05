<?php

namespace App\Services\Compras;

use App\Models\Compras\Requisicao;
use App\Models\Compras\RequisicaoItem;
use Illuminate\Support\Facades\DB;

/**
 * O caminho de uma requisição de compra: rascunho → submetida → decidida.
 *
 * As regras de quem pode passar de onde para onde vivem aqui e não no
 * componente, para valerem também quando o pedido vier de outro lado (um
 * comando, um ensaio, mais tarde uma API). O `tenantId` é sempre parâmetro
 * explícito — nunca `activeTenantId()` — para o serviço funcionar fora de uma
 * sessão de utilizador.
 */
class FluxoDaRequisicao
{
    /**
     * @param  array<int, array{descricao?: string, product_id?: int|null, quantidade?: mixed, custo_estimado?: mixed, unidade?: string|null, notas?: string|null}>  $linhas
     */
    public function criar(int $tenantId, ?int $userId, array $dados, array $linhas): Requisicao
    {
        $limpas = $this->linhasValidas($linhas);

        if ($limpas === []) {
            throw new \InvalidArgumentException('Uma requisição precisa de pelo menos um artigo com quantidade.');
        }

        return DB::transaction(function () use ($tenantId, $userId, $dados, $limpas) {
            $req = Requisicao::create([
                'tenant_id' => $tenantId,
                'warehouse_id' => $dados['warehouse_id'] ?? null,
                'necessaria_em' => $dados['necessaria_em'] ?? null,
                'justificacao' => $dados['justificacao'] ?? null,
                'estado' => 'rascunho',
                'created_by' => $userId,
            ]);

            $this->gravarLinhas($req, $limpas);

            return $req->fresh('itens');
        });
    }

    public function actualizar(Requisicao $req, int $tenantId, array $dados, array $linhas): Requisicao
    {
        $this->minha($req, $tenantId);

        if (! $req->podeEditar()) {
            throw new \InvalidArgumentException('Só um rascunho se edita. Esta requisição já foi submetida.');
        }

        $limpas = $this->linhasValidas($linhas);

        if ($limpas === []) {
            throw new \InvalidArgumentException('Uma requisição precisa de pelo menos um artigo com quantidade.');
        }

        return DB::transaction(function () use ($req, $dados, $limpas) {
            $req->update([
                'warehouse_id' => $dados['warehouse_id'] ?? null,
                'necessaria_em' => $dados['necessaria_em'] ?? null,
                'justificacao' => $dados['justificacao'] ?? null,
            ]);

            // Substituição limpa: um rascunho ainda não tem história para
            // proteger, e reconciliar linha a linha só traria bugs.
            $req->itens()->delete();
            $this->gravarLinhas($req, $limpas);

            return $req->fresh('itens');
        });
    }

    public function submeter(Requisicao $req, int $tenantId): Requisicao
    {
        $this->minha($req, $tenantId);

        if ($req->estado !== 'rascunho') {
            throw new \InvalidArgumentException('Só se submete um rascunho.');
        }

        if ($req->itens()->count() === 0) {
            throw new \InvalidArgumentException('Não se submete uma requisição sem artigos.');
        }

        $req->update(['estado' => 'submetida']);

        return $req;
    }

    public function aprovar(Requisicao $req, int $tenantId, ?int $userId): Requisicao
    {
        $this->minha($req, $tenantId);

        if ($req->estado !== 'submetida') {
            throw new \InvalidArgumentException('Só se aprova uma requisição submetida.');
        }

        $req->update([
            'estado' => 'aprovada',
            'decidida_por' => $userId,
            'decidida_em' => now(),
            'motivo_recusa' => null,
        ]);

        return $req;
    }

    /**
     * Recusar EXIGE motivo — a mesma regra do CRM para perder um negócio.
     * Uma recusa sem explicação volta sempre como pergunta.
     */
    public function rejeitar(Requisicao $req, int $tenantId, ?int $userId, string $motivo): Requisicao
    {
        $this->minha($req, $tenantId);

        if ($req->estado !== 'submetida') {
            throw new \InvalidArgumentException('Só se rejeita uma requisição submetida.');
        }

        if (trim($motivo) === '') {
            throw new \InvalidArgumentException('Diga porque está a recusar — quem pediu tem de saber.');
        }

        $req->update([
            'estado' => 'rejeitada',
            'decidida_por' => $userId,
            'decidida_em' => now(),
            'motivo_recusa' => trim($motivo),
        ]);

        return $req;
    }

    /**
     * Cancelar não apaga nada — muda o estado. Uma requisição já encomendada
     * não se cancela: o compromisso com o fornecedor está feito, e é lá que
     * tem de ser desfeito.
     */
    public function cancelar(Requisicao $req, int $tenantId): Requisicao
    {
        $this->minha($req, $tenantId);

        if ($req->estado === 'encomendada') {
            throw new \InvalidArgumentException('Esta requisição já deu origem a encomendas. Cancele as encomendas primeiro.');
        }

        if ($req->estado === 'cancelada') {
            throw new \InvalidArgumentException('Esta requisição já está cancelada.');
        }

        $req->update(['estado' => 'cancelada']);

        return $req;
    }

    /** Descarta linhas vazias e normaliza números. */
    private function linhasValidas(array $linhas): array
    {
        $limpas = [];

        foreach ($linhas as $linha) {
            $descricao = trim((string) ($linha['descricao'] ?? ''));
            $quantidade = (float) ($linha['quantidade'] ?? 0);

            if ($descricao === '' || $quantidade <= 0) {
                continue;
            }

            $custo = $linha['custo_estimado'] ?? null;

            $limpas[] = [
                'product_id' => $linha['product_id'] ?? null,
                'descricao' => $descricao,
                'quantidade' => $quantidade,
                'custo_estimado' => ($custo === null || $custo === '') ? null : (float) $custo,
                'unidade' => $linha['unidade'] ?? null,
                'notas' => $linha['notas'] ?? null,
            ];
        }

        return $limpas;
    }

    private function gravarLinhas(Requisicao $req, array $linhas): void
    {
        foreach ($linhas as $i => $linha) {
            RequisicaoItem::create($linha + [
                'requisicao_id' => $req->id,
                'ordem' => $i,
            ]);
        }
    }

    /** Nunca confiar no id que vem do browser. */
    private function minha(Requisicao $req, int $tenantId): void
    {
        if ((int) $req->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('Requisição de outra empresa.');
        }
    }
}
