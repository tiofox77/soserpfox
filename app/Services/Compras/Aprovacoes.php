<?php

namespace App\Services\Compras;

use App\Models\Compras\Aprovacao;
use App\Models\Compras\DefinicoesDasCompras;
use App\Models\Compras\Encomenda;
use App\Models\Compras\PedidoDePagamento;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * OS VOTOS DE UMA ENCOMENDA OU DE UM PEDIDO DE PAGAMENTO.
 *
 * A empresa diz quantos «sim» cada passo precisa (`compras_definicoes`). Aqui
 * vivem as regras que fazem disso separação de funções e não um carimbo:
 *
 *  · QUEM FEZ NÃO APROVA. O autor da encomenda (ou quem pediu o pagamento) não
 *    vota no que é seu — sem isto, uma pessoa com as duas permissões fazia e
 *    aprovava sozinha, e o circuito era só burocracia.
 *  · UM VOTO POR PESSOA E POR RONDA. Recusado e corrigido, o documento volta
 *    a uma ronda nova e precisa outra vez de todos os «sim».
 *  · UM «NÃO» CHEGA para parar — com motivo, porque quem fez tem de saber o
 *    que corrigir.
 */
class Aprovacoes
{
    public function necessarias(Model $documento): int
    {
        $regras = DefinicoesDasCompras::da((int) $documento->tenant_id);

        return $documento instanceof PedidoDePagamento
            ? (int) $regras->aprovacoes_pagamento
            : (int) $regras->aprovacoes_encomenda;
    }

    /**
     * Regista um voto e diz se a ronda ficou decidida.
     *
     * @return array{aprovada: bool, recusada: bool, sins: int, necessarias: int}
     */
    public function votar(Model $documento, User $user, bool $aprova, ?string $comentario, int $ronda): array
    {
        $autor = $documento instanceof PedidoDePagamento ? $documento->pedido_por : $documento->created_by;

        if ((int) $autor === (int) $user->id) {
            throw new \InvalidArgumentException($documento instanceof PedidoDePagamento
                ? 'Quem pediu o pagamento não o aprova. Tem de ser outra pessoa.'
                : 'Quem fez a encomenda não a aprova. Tem de ser outra pessoa.');
        }

        $comentario = trim((string) $comentario);

        if (! $aprova && $comentario === '') {
            throw new \InvalidArgumentException('Diga porque está a recusar — quem fez tem de saber o que corrigir.');
        }

        $jaVotou = Aprovacao::withoutGlobalScopes()
            ->where('aprovavel_type', $documento::class)
            ->where('aprovavel_id', $documento->getKey())
            ->where('ronda', $ronda)
            ->where('user_id', $user->id)
            ->exists();

        if ($jaVotou) {
            throw new \InvalidArgumentException('Já deu a sua decisão nesta ronda.');
        }

        Aprovacao::create([
            'tenant_id' => $documento->tenant_id,
            'aprovavel_type' => $documento::class,
            'aprovavel_id' => $documento->getKey(),
            'ronda' => $ronda,
            'user_id' => $user->id,
            'decisao' => $aprova ? 'aprovado' : 'recusado',
            'comentario' => $comentario !== '' ? $comentario : null,
        ]);

        $sins = $this->sins($documento, $ronda);
        $necessarias = max(1, $this->necessarias($documento));

        return [
            'aprovada' => $aprova && $sins >= $necessarias,
            'recusada' => ! $aprova,
            'sins' => $sins,
            'necessarias' => $necessarias,
        ];
    }

    public function sins(Model $documento, int $ronda): int
    {
        return Aprovacao::withoutGlobalScopes()
            ->where('aprovavel_type', $documento::class)
            ->where('aprovavel_id', $documento->getKey())
            ->where('ronda', $ronda)
            ->where('decisao', 'aprovado')
            ->count();
    }

    /** Esta pessoa ainda pode votar nesta ronda? (o botão só aparece a quem pode). */
    public function podeVotar(Model $documento, ?User $user, int $ronda): bool
    {
        if (! $user) {
            return false;
        }

        $autor = $documento instanceof PedidoDePagamento ? $documento->pedido_por : $documento->created_by;

        if ((int) $autor === (int) $user->id) {
            return false;
        }

        return ! Aprovacao::withoutGlobalScopes()
            ->where('aprovavel_type', $documento::class)
            ->where('aprovavel_id', $documento->getKey())
            ->where('ronda', $ronda)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * O histórico dos votos, para a ficha: quem, o quê, quando e em que ronda.
     *
     * @return Collection<int, array{quem: string|null, decisao: string, comentario: string|null, quando: string|null, ronda: int}>
     */
    public function historico(Model $documento): Collection
    {
        return Aprovacao::withoutGlobalScopes()
            ->where('aprovavel_type', $documento::class)
            ->where('aprovavel_id', $documento->getKey())
            ->with('autor:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Aprovacao $a) => [
                'quem' => $a->autor?->name,
                'decisao' => $a->decisao,
                'comentario' => $a->comentario,
                'quando' => $a->created_at?->format('Y-m-d H:i'),
                'ronda' => (int) $a->ronda,
            ]);
    }

    /** Para o sino: as encomendas à espera do voto DESTA pessoa. */
    public function encomendasPorVotar(User $user, int $tenantId): int
    {
        return Encomenda::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('estado', 'em_aprovacao')
            ->where('created_by', '!=', $user->id)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('compras_aprovacoes as a')
                ->whereColumn('a.aprovavel_id', 'compras_encomendas.id')
                ->where('a.aprovavel_type', Encomenda::class)
                ->whereColumn('a.ronda', 'compras_encomendas.ronda_aprovacao')
                ->where('a.user_id', $user->id))
            ->count();
    }

    public function pagamentosPorVotar(User $user, int $tenantId): int
    {
        return PedidoDePagamento::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('estado', 'em_aprovacao')
            ->where('pedido_por', '!=', $user->id)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('compras_aprovacoes as a')
                ->whereColumn('a.aprovavel_id', 'compras_pagamentos.id')
                ->where('a.aprovavel_type', PedidoDePagamento::class)
                ->whereColumn('a.ronda', 'compras_pagamentos.ronda')
                ->where('a.user_id', $user->id))
            ->count();
    }
}
