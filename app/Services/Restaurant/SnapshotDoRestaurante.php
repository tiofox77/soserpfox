<?php

namespace App\Services\Restaurant;

use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
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
                'status'   => $m->status,
            ])->values()->all(),

            'settings' => [
                'use_kitchen_workflow'        => (bool) $definicoes->use_kitchen_workflow,
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
}
