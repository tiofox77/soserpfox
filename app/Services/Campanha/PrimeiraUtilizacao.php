<?php

namespace App\Services\Campanha;

use App\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A PRIMEIRA UTILIZAÇÃO DE UMA EMPRESA — o primeiro trabalho a sério.
 *
 * Contava-se pelo `users.last_login_at`, mas o registo inicia sessão sozinho e
 * o redireccionamento para o `/home` grava-o logo: a primeira utilização dava
 * 14 em 14 (26/09/2026). Entrar não é usar.
 *
 * Usar é criar alguma coisa do negócio: um artigo, um cliente, um documento,
 * um colaborador, um quarto, uma mesa, um prato, uma viatura. O que o próprio
 * registo provisiona (o «Consumidor Final», armazém, impostos, séries) nasce
 * no mesmo segundo da empresa e fica de fora pela janela de 60 segundos.
 */
final class PrimeiraUtilizacao
{
    /** O que conta como trabalho, por tabela: [tabela, filtro extra ou null]. */
    private const TRABALHO = [
        ['invoicing_products', null],
        ['invoicing_clients', "COALESCE(nif, '') <> '999999999'"],
        ['invoicing_sales_invoices', null],
        ['invoicing_sales_proformas', null],
        ['hr_employees', null],
        ['hotel_room_types', null],
        ['hotel_rooms', null],
        ['hotel_reservations', null],
        ['restaurant_tables', null],
        ['restaurant_orders', null],
        ['salon_services', null],
        ['salon_appointments', null],
        ['workshop_vehicles', null],
        ['workshop_work_orders', null],
    ];

    /** Os segundos depois de a empresa nascer que ainda são do próprio registo. */
    private const DO_REGISTO = 60;

    /** Quando a empresa começou a trabalhar, ou null se ainda não começou. */
    public static function de(Tenant|int $empresa): ?CarbonInterface
    {
        $tenant = $empresa instanceof Tenant ? $empresa : Tenant::withTrashed()->find($empresa);

        if (! $tenant || ! $tenant->created_at) {
            return null;
        }

        $depois = $tenant->created_at->copy()->addSeconds(self::DO_REGISTO);
        $primeira = null;

        foreach (self::TRABALHO as [$tabela, $filtro]) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'tenant_id')) {
                continue;
            }

            $q = DB::table($tabela)->where('tenant_id', $tenant->id)->where('created_at', '>', $depois);

            if ($filtro !== null) {
                $q->whereRaw($filtro);
            }

            $quando = $q->min('created_at');

            if ($quando !== null && ($primeira === null || $quando < $primeira)) {
                $primeira = $quando;
            }
        }

        return $primeira !== null ? Carbon::parse($primeira) : null;
    }
}
