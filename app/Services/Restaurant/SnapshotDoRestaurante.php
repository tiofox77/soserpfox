<?php

namespace App\Services\Restaurant;

use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\Recipe;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Models\Tenant;

/**
 * A sala inteira, guardada no aparelho antes de faltar a rede.
 *
 * O POS de restaurante não é um balcão: antes de vender é preciso saber que
 * mesas existem, em que sala estão e quais é que já têm gente sentada. Nada
 * disso vem do catálogo de artigos, e sem isto o ecrã abre vazio.
 *
 * Vai à boleia da sincronização de faturação — o aparelho já a faz, e uma
 * segunda viagem seria outra oportunidade para a rede falhar a meio.
 *
 * SÓ VAI SE A EMPRESA TIVER O MÓDULO. É esta a activação: sem módulo não há
 * salas, sem salas o PWA não mostra a entrada do restaurante, e o endereço,
 * mesmo escrito à mão, leva 403. Um aparelho que perca o módulo deixa de
 * receber a sala na sincronização seguinte.
 */
class SnapshotDoRestaurante
{
    public function paraTenant(?Tenant $tenant): ?array
    {
        if (!$tenant || !$tenant->hasModule('restaurant')) {
            return null;
        }

        $tenantId = $tenant->id;
        $definicoes = RestaurantSettings::forTenant($tenantId);

        $salas = Venue::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'warehouse_id']);

        $zonas = Area::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'venue_id', 'name', 'sort_order']);

        $mesas = DiningTable::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'venue_id', 'area_id', 'code', 'name', 'capacity', 'status']);

        // As mesas com comanda aberta AGORA — é por elas que se lê o estado.
        $comComanda = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', Order::OPEN_STATUSES)
            ->whereNotNull('table_id')
            ->pluck('table_id')
            ->flip();

        return [
            'venues' => $salas->map(fn ($v) => [
                'id'           => $v->id,
                'code'         => $v->code,
                'name'         => $v->name,
                'warehouse_id' => $v->warehouse_id,
            ])->values()->all(),

            'areas' => $zonas->map(fn ($a) => [
                'id'         => $a->id,
                'venue_id'   => $a->venue_id,
                'name'       => $a->name,
                'sort_order' => (int) $a->sort_order,
            ])->values()->all(),

            // O estado da mesa é do SERVIDOR e serve de ponto de partida: o
            // aparelho sobrepõe-lhe o que ele próprio abriu offline. Uma mesa
            // que aqui venha livre pode já ter gente noutro tablet — é por isso
            // que a reposição da comanda sabe abrir ao balcão em vez de recusar.
            'tables' => $mesas->map(fn ($m) => [
                'id'       => $m->id,
                'venue_id' => $m->venue_id,
                'area_id'  => $m->area_id,
                'code'     => $m->code,
                'name'     => $m->name,
                'capacity' => (int) $m->capacity,
                'status'   => self::estadoParaOAparelho($m->status, isset($comComanda[$m->id])),
            ])->values()->all(),

            'settings' => [
                // NO PWA A VENDA É SEMPRE RÁPIDA — não passa pela cozinha.
                //
                // O circuito de cozinha é uma conversa entre dois aparelhos: o
                // empregado manda o pedido, o ecrã da cozinha aceita, prepara e
                // devolve "pronto". Sem rede não há essa conversa — o outro
                // aparelho não existe. O que o PWA fazia era pedir ao empregado
                // que carregasse em "Enviar à cozinha" para um sítio que não o
                // ouvia, e o pedido ficava por confirmar até haver rede.
                //
                // Aqui a comanda confirma-se e cobra-se num passo só. O
                // servidor já sabe receber assim: ao repor a comanda marca os
                // artigos como servidos e consome o stock na mesma (ver
                // ComandaOffline::darComoServida). O circuito completo continua
                // a existir no restaurante ONLINE, que é onde funciona.
                'use_kitchen_workflow'        => false,
                'use_kitchen_workflow_online' => (bool) $definicoes->use_kitchen_workflow,
                'require_recipe_for_products' => (bool) $definicoes->require_recipe_for_products,
                'consume_stock_on_kitchen'    => (bool) $definicoes->consume_stock_on_kitchen,
                'default_client_id'           => $definicoes->default_client_id,
            ],

            // Os pratos que PODEM ser vendidos quando a empresa exige ficha
            // técnica. Sem esta lista, o aparelho oferecia offline um prato que
            // o servidor recusa na sincronização — e uma comanda recusada
            // depois de a comida sair é dinheiro parado numa fila. Só se envia
            // quando a regra está ligada; nas outras empresas seria a lista de
            // artigos toda, outra vez, por nada.
            'recipe_product_ids' => $definicoes->require_recipe_for_products
                ? Recipe::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->pluck('product_id')
                    ->unique()
                    ->values()
                    ->all()
                : null,
        ];
    }

    /** Os estados que querem dizer «tem gente sentada». */
    private const OCUPADA = ['occupied', 'waiting_kitchen', 'served', 'billing'];

    /**
     * O ESTADO DA MESA LÊ-SE PELAS COMANDAS, NÃO SÓ PELA COLUNA.
     *
     * Vários postos trabalham a mesma sala, uns com rede e outros sem. A
     * coluna `status` é escrita por quem abre, fecha e limpa — e fica para trás
     * quando alguma coisa corre fora do caminho (uma comanda feita sem rede
     * que chegou a uma mesa já ocupada abre ao balcão; uma comanda cancelada).
     * Uma mesa «ocupada» sem comanda nenhuma ficava ocupada em todos os
     * tablets para sempre, e uma mesa com comanda aberta podia vir «livre».
     *
     * O aparelho recebe o estado corrigido: com comanda aberta está ocupada;
     * «ocupada» sem comanda está livre. Limpeza, reserva e bloqueio passam
     * como estão — são decisões de alguém, não restos.
     */
    public static function estadoParaOAparelho(string $estado, bool $temComandaAberta): string
    {
        $ocupada = in_array($estado, self::OCUPADA, true);

        if ($temComandaAberta) {
            return $ocupada ? $estado : 'occupied';
        }

        return $ocupada ? 'available' : $estado;
    }
}
