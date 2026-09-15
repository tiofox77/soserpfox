<?php

namespace App\Services\Workshop;

use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;
use App\Models\Workshop\WorkOrderItem;
use Illuminate\Support\Facades\DB;

/**
 * A ORDEM DE SERVIÇO — as regras, fora do ecrã.
 *
 * O ecrã em Livewire tinha isto lá dentro, com um aviso escrito por cima do
 * método mais importante: «Ponto ÚNICO de mudança de estado de uma OS. Existe
 * porque havia dois caminhos (o botão de estado e o formulário de edição) com
 * regras diferentes: o formulário gravava o estado directamente e saltava a
 * baixa de stock.»
 *
 * Passar o ecrã para React sem tirar isto de lá era abrir o mesmo buraco: a
 * API teria uma rota de gravar e outra de mudar estado, e a primeira voltaria
 * a poder marcar «Concluída» sem descontar as peças. Por isso a regra sai para
 * aqui, e as duas rotas entram pela mesma porta.
 *
 * O QUE MUDA COM A MIGRAÇÃO:
 *
 *  · O HISTÓRICO PASSA A TER O QUE É PRECISO. O separador «Histórico» existia
 *    e só registava anexos — as sete constantes de acção estavam declaradas e
 *    seis nunca eram usadas. Criar, editar, mudar de estado, juntar e tirar
 *    linhas e facturar passam a ficar escritos, que é o que faz o separador
 *    responder a «quem mexeu nisto e quando».
 */
final class OrdensDeServico
{
    /** Os sete estados de uma ordem. */
    public const ESTADOS = [
        'pending' => 'Pendente',
        'scheduled' => 'Agendada',
        'in_progress' => 'Em curso',
        'waiting_parts' => 'À espera de peças',
        'completed' => 'Concluída',
        'delivered' => 'Entregue',
        'cancelled' => 'Cancelada',
    ];

    public const PRIORIDADES = [
        'low' => 'Baixa',
        'normal' => 'Normal',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ];

    /** As categorias de anexo — as do modal de sempre. */
    public const CATEGORIAS_DE_ANEXO = [
        'photo_before' => 'Foto antes',
        'photo_after' => 'Foto depois',
        'photo_damage' => 'Foto de dano',
        'document' => 'Documento',
        'invoice' => 'Factura',
        'other' => 'Outro',
    ];

    /**
     * PONTO ÚNICO DE MUDANÇA DE ESTADO.
     *
     * Passar a «Concluída» ou a «Entregue» DESCONTA as peças do stock; anular
     * DEVOLVE-AS. Um `update(['status' => ...])` cru deixava-as fora do stock
     * para sempre, ou dentro dele depois de montadas no carro.
     *
     * @return array{0: string, 1: list<string>} a mensagem e as peças que não saíram
     */
    public function aplicarEstado(WorkOrder $ordem, string $novo): array
    {
        if ($ordem->status === $novo) {
            return [__('O estado já era esse.'), []];
        }

        // OF-09: com um controlo de qualidade obrigatório, não se conclui sem ele feito e sem nada urgente.
        if ($novo === 'completed' || ($novo === 'delivered' && ! $ordem->completed_at)) {
            $this->exigirControloDeQualidade($ordem);
        }

        $falhas = [];

        if ($novo === 'in_progress') {
            $ordem->markAsInProgress();
        } elseif ($novo === 'completed') {
            $falhas = $ordem->markAsCompleted();
        } elseif ($novo === 'delivered') {
            // Entregue sem ter passado por Concluída também consome as peças —
            // o carro saiu da oficina com elas montadas.
            $falhas = $ordem->processStockMovement() ?: [];
            $ordem->markAsDelivered();
        } elseif ($novo === 'cancelled') {
            $ordem->returnStockMovement();
            $ordem->update(['status' => $novo]);
        } else {
            $ordem->update(['status' => $novo]);
        }

        /*
         * O HISTÓRICO DA MUDANÇA DE ESTADO NÃO SE ESCREVE AQUI.
         *
         * O `WorkOrderObserver` já o faz no `updated`, e escrevê-lo também
         * neste sítio dava DUAS linhas por cada mudança — foi o que apareceu na
         * primeira prova no browser. Um observador é a rede que apanha as
         * mudanças venham elas de onde vierem; este método é uma delas.
         */
        // OF-11: a revisão feita marca a próxima (por km e por data).
        if (in_array($novo, ['completed', 'delivered'], true)) {
            LembretesDaOficina::revisaoFeita($ordem);
        }

        if ($falhas) {
            AvisosDaOficina::estadoMudou($ordem, $novo);

            // Antes dizia sempre «Estoque baixado automaticamente», mesmo
            // quando a baixa rebentava por falta de stock e o erro só ia para
            // o log — quem estava no ecrã ficava a acreditar que tinha saído.
            return [__('Estado alterado, mas houve peças que NÃO saíram do stock: :quais', [
                'quais' => implode(' | ', $falhas),
            ]), $falhas];
        }

        // OF-10: o cliente fica a saber (email/SMS pelo módulo Notificações, depois da resposta).
        AvisosDaOficina::estadoMudou($ordem, $novo);

        $mensagem = match (true) {
            in_array($novo, ['completed', 'delivered'], true) => __('Estado alterado. Peças descontadas do stock.'),
            $novo === 'cancelled' => __('Estado alterado. Peças devolvidas ao stock.'),
            default => __('Estado alterado.'),
        };

        return [$mensagem, []];
    }

    /**
     * O CONTROLO DE QUALIDADE OBRIGATÓRIO (OF-09).
     *
     * Só vale quando a oficina tem um modelo de controlo de qualidade activo e
     * marcado «obrigatório». Uma inspecção desse tipo concluída sem pontos
     * urgentes deixa passar; qualquer outra coisa pára com uma frase que diz o
     * que falta fazer.
     *
     * @throws \InvalidArgumentException
     */
    public function exigirControloDeQualidade(WorkOrder $ordem): void
    {
        $obrigatorio = \App\Models\Workshop\InspectionTemplate::withoutGlobalScopes()->where('tenant_id', $ordem->tenant_id)
            ->where('kind', 'qualidade')->where('is_required', true)->where('is_active', true)->exists();

        if (! $obrigatorio) {
            return;
        }

        $feitas = \App\Models\Workshop\WorkOrderInspection::withoutGlobalScopes()->where('work_order_id', $ordem->id)
            ->where('kind', 'qualidade')->whereNotNull('completed_at')->get();

        if ($feitas->isEmpty()) {
            throw new \InvalidArgumentException(__('Falta o controlo de qualidade: faça-o no separador Inspecção antes de concluir a ordem.'));
        }

        if ($feitas->every(fn ($i) => $i->contas()['urgente'] > 0)) {
            throw new \InvalidArgumentException(__('O controlo de qualidade tem pontos urgentes: resolva-os e volte a fazê-lo antes de concluir.'));
        }
    }

    /**
     * GRAVAR UMA ORDEM — nova ou existente.
     *
     * O estado NUNCA se grava aqui: sai dos dados e vai pelo ponto único. Era
     * este o furo do ecrã em Livewire antes de o corrigirem, e é o que esta
     * separação impede de voltar.
     *
     * @return array{0: WorkOrder, 1: string} a ordem e a mensagem
     */
    public function guardar(array $dados, ?WorkOrder $ordem, int $tenantId): array
    {
        $estado = $dados['status'] ?? null;
        unset($dados['status']);

        // Um `<select>` ou um `<input>` vazio chega como '' e não como nulo.
        // Gravar '' numa coluna datetime dá SQL 1292 e numa chave estrangeira
        // um erro de integridade: na prática, uma OS sem data agendada ou sem
        // mecânico atribuído nunca gravava.
        foreach (['mechanic_id', 'received_at', 'scheduled_for'] as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dados[$campo] = $dados[$campo] ?: null;
            }
        }

        $dados['mileage_in'] = (int) ($dados['mileage_in'] ?? 0);

        if (! $ordem) {
            // O número é gerado por empresa e de forma atómica, com repetição
            // no 1062: dois pedidos ao mesmo segundo pediam o mesmo número.
            $nova = WorkOrder::createWithTenantNumber(
                $dados + ['tenant_id' => $tenantId, 'status' => $estado ?: 'pending'],
                'order_number', 'OS-'
            );

            // A linha de «criada» no histórico é do observador, que a escreve
            // venha a ordem de onde vier.
            return [$nova, __('Ordem de serviço criada.')];
        }

        $ordem->update($dados);

        WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_UPDATED,
            __('Ficha da ordem alterada.'));

        $mensagem = __('Ordem de serviço guardada.');

        if ($estado && $estado !== $ordem->status) {
            [$mensagem] = $this->aplicarEstado($ordem, $estado);
        }

        return [$ordem->fresh(), $mensagem];
    }

    /**
     * JUNTAR UMA LINHA — um serviço ou uma peça.
     *
     * O SUBTOTAL NÃO SE CALCULA AQUI, de propósito: o modelo fá-lo no `saving`
     * e recalcula os totais da ordem no `saved`. Repetir a conta neste sítio
     * era ter duas implementações da mesma regra fiscal — que é exactamente o
     * que esta migração existe para acabar.
     *
     * O que aqui se garante é que o pedido não escolhe o subtotal: as chaves
     * que se gravam são só estas, e `subtotal` não é uma delas.
     */
    public function juntarLinha(WorkOrder $ordem, array $dados): WorkOrderItem
    {
        return DB::transaction(function () use ($ordem, $dados) {
            $linha = WorkOrderItem::create([
                'work_order_id' => $ordem->id,
                'type' => $dados['type'],
                'service_id' => ($dados['service_id'] ?? null) ?: null,
                'product_id' => ($dados['product_id'] ?? null) ?: null,
                'code' => $dados['code'] ?? null,
                'name' => $dados['name'],
                'description' => $dados['description'] ?? null,
                'quantity' => (float) $dados['quantity'],
                'unit_price' => (float) $dados['unit_price'],
                'discount_percent' => (float) ($dados['discount_percent'] ?? 0),
                'hours' => (float) ($dados['hours'] ?? 0),
                'mechanic_id' => ($dados['mechanic_id'] ?? null) ?: null,
                'part_number' => $dados['part_number'] ?? null,
                'brand' => $dados['brand'] ?? null,
                'is_original' => (bool) ($dados['is_original'] ?? false),
                // OF-03: uma linha proposta fica à espera do cliente e não conta para os totais.
                'approval' => ! empty($dados['precisa_aprovacao']) ? 'pending' : 'approved',
            ]);

            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_ITEM_ADDED,
                $linha->approval === 'pending'
                    ? __('Linha proposta ao cliente: :nome', ['nome' => $linha->name])
                    : __('Linha adicionada: :nome', ['nome' => $linha->name]),
                ['tipo' => $linha->type, 'subtotal' => (float) $linha->subtotal]);

            return $linha;
        });
    }

    /** Tirar uma linha — os totais voltam a fazer-se no `deleted` do modelo. */
    public function tirarLinha(WorkOrder $ordem, WorkOrderItem $linha): void
    {
        DB::transaction(function () use ($ordem, $linha) {
            $nome = $linha->name;
            $linha->delete();

            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_ITEM_REMOVED,
                __('Linha removida: :nome', ['nome' => $nome]));
        });
    }

    /**
     * O DESCONTO COMERCIAL DA ORDEM — e os totais a seguir.
     *
     * Gravar o desconto sem recalcular deixava o total a dizer o valor antigo
     * e a factura a sair com outro. As linhas não mudam, e por isso é preciso
     * chamar o recálculo à mão: os eventos do modelo da linha não disparam.
     */
    public function desconto(WorkOrder $ordem, float $desconto): void
    {
        $antes = (float) $ordem->discount;

        $ordem->update(['discount' => $desconto]);
        $ordem->calculateTotals();

        WorkOrderHistory::logFieldChange($ordem->id, 'discount', (string) $antes, (string) $desconto,
            __('Desconto da ordem alterado.'));
    }
}
