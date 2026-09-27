<?php

namespace App\Services\Compras;

use App\Models\AuditTrail;
use App\Models\Compras\Aprovacao;
use App\Models\Compras\Encomenda;
use App\Models\Compras\PedidoDePagamento;
use App\Models\Invoicing\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * O RASTO DE UMA COMPRA: cada passo, quem o deu e quando (27/09/2026).
 *
 * «A ideia é melhorar a rastreabilidade e permitir que cada etapa fique
 * associada ao respetivo utilizador.» Nada disto é gravado de propósito para
 * o ecrã — lê-se do que o sistema já guarda: a requisição (autor e decisor),
 * a trilha de auditoria (as mudanças de estado da encomenda), os votos, os
 * pedidos de pagamento, os movimentos de stock da recepção e a factura.
 */
class RastoDaCompra
{
    /** O que cada estado quer dizer, dito como passo. */
    private const PASSOS_DO_ESTADO = [
        'em_aprovacao' => 'Enviou para aprovação',
        'aprovada' => 'Encomenda aprovada',
        'enviada' => 'Enviou ao fornecedor',
        'confirmada' => 'Registou a confirmação do fornecedor',
        'cancelada' => 'Cancelou a encomenda',
    ];

    /**
     * @return list<array{quando: string|null, quem: string|null, passo: string, detalhe: string|null, tipo: string}>
     */
    public function daEncomenda(Encomenda $enc): array
    {
        $passos = collect();
        $nomes = [];
        $nome = function (?int $id) use (&$nomes): ?string {
            if (! $id) {
                return null;
            }

            return $nomes[$id] ??= User::whereKey($id)->value('name');
        };

        // ── A requisição que lhe deu origem ──
        if ($req = $enc->requisicao()->with(['autor:id,name', 'decisor:id,name'])->first()) {
            $passos->push($this->passo($req->created_at, $req->autor?->name, __('Pediu a compra (requisição :n)', ['n' => $req->numero]), $req->justificacao, 'requisicao'));

            if ($req->decidida_em) {
                $passos->push($this->passo($req->decidida_em, $req->decisor?->name,
                    $req->estado === 'rejeitada' ? __('Recusou a requisição') : __('Aprovou a requisição'),
                    $req->motivo_recusa, 'requisicao'));
            }
        }

        // ── A encomenda ──
        $passos->push($this->passo($enc->created_at, $enc->autor?->name ?? $nome($enc->created_by),
            __('Criou a encomenda :n', ['n' => $enc->numero]), null, 'encomenda'));

        AuditTrail::where('auditable_type', Encomenda::class)
            ->where('auditable_id', $enc->id)
            ->orderBy('id')
            ->get(['user_id', 'actor_name', 'new_values', 'created_at'])
            ->each(function (AuditTrail $a) use ($passos, $nome) {
                $estado = $a->new_values['estado'] ?? null;

                if ($estado && isset(self::PASSOS_DO_ESTADO[$estado])) {
                    $passos->push($this->passo($a->created_at, $a->actor_name ?: $nome($a->user_id),
                        __(self::PASSOS_DO_ESTADO[$estado]), null, 'encomenda'));
                }
            });

        $this->votos(Encomenda::class, $enc->id, $passos, __('Aprovou a encomenda'), __('Recusou a encomenda'));

        // ── O dinheiro ──
        foreach ($enc->pagamentos()->with(['pedidoPor:id,name', 'pagoPor:id,name', 'decididoPor:id,name', 'recibo:id,receipt_number'])->get() as $p) {
            $valor = number_format((float) $p->valor, 2, ',', '.').' Kz';

            $passos->push($this->passo($p->created_at, $p->pedidoPor?->name,
                __('Pediu o pagamento :n (:v)', ['n' => $p->numero, 'v' => $valor]), $p->notas, 'pagamento'));

            $this->votos(PedidoDePagamento::class, $p->id, $passos, __('Aprovou o pagamento :n', ['n' => $p->numero]), __('Recusou o pagamento :n', ['n' => $p->numero]));

            if ($p->estado === 'pago') {
                $passos->push($this->passo($p->pago_em, $p->pagoPor?->name,
                    __('Pagou :v ao fornecedor (:n)', ['v' => $valor, 'n' => $p->numero]),
                    $p->recibo ? __('Recibo :r', ['r' => $p->recibo->receipt_number]) : null, 'pagamento'));
            } elseif ($p->estado === 'recusado' && $p->decidido_em) {
                $passos->push($this->passo($p->decidido_em, $p->decididoPor?->name,
                    __('Devolveu o pedido de pagamento :n', ['n' => $p->numero]), $p->motivo_recusa, 'pagamento'));
            }
        }

        // ── A recepção: os movimentos de entrada, por pessoa e por minuto ──
        StockMovement::withoutGlobalScopes()
            ->where('tenant_id', $enc->tenant_id)
            ->where('reference_type', Encomenda::class)
            ->where('reference_id', $enc->id)
            ->orderBy('id')
            ->get(['user_id', 'quantity', 'created_at'])
            ->groupBy(fn ($m) => $m->user_id.'|'.$m->created_at?->format('Y-m-d H:i'))
            ->each(function (Collection $grupo) use ($passos, $nome) {
                $primeiro = $grupo->first();
                $passos->push($this->passo($primeiro->created_at, $nome($primeiro->user_id),
                    __('Recebeu a mercadoria no armazém'),
                    trans_choice('{1} :n linha|[2,*] :n linhas', $grupo->count(), ['n' => $grupo->count()]), 'recepcao'));
            });

        // ── A factura ──
        if ($f = $enc->factura()->first()) {
            $passos->push($this->passo($f->created_at, $nome($f->created_by),
                __('Gerou a factura de compra :n', ['n' => $f->invoice_number]), null, 'factura'));
        }

        return $passos
            ->sortBy(fn ($p) => $p['quando'] ?? '')
            ->values()
            ->all();
    }

    private function votos(string $tipo, int $id, Collection $passos, string $sim, string $nao): void
    {
        Aprovacao::withoutGlobalScopes()
            ->where('aprovavel_type', $tipo)
            ->where('aprovavel_id', $id)
            ->with('autor:id,name')
            ->orderBy('id')
            ->get()
            ->each(fn (Aprovacao $a) => $passos->push($this->passo(
                $a->created_at, $a->autor?->name,
                $a->decisao === 'aprovado' ? $sim : $nao,
                $a->comentario, 'aprovacao',
            )));
    }

    private function passo($quando, ?string $quem, string $passo, ?string $detalhe, string $tipo): array
    {
        return [
            'quando' => $quando ? \Carbon\Carbon::parse($quando)->format('Y-m-d H:i') : null,
            'quem' => $quem,
            'passo' => $passo,
            'detalhe' => $detalhe,
            'tipo' => $tipo,
        ];
    }
}
