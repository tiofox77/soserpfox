<?php

namespace App\Services\Plataforma;

use App\Models\Category;
use App\Models\ErroDoSistema;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\Warehouse;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Treasury\PaymentMethod;
use App\Support\CatalogoDePermissoes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * UMA EMPRESA FICOU COMPLETA? — só lê.
 *
 * A inscrição (App\Services\Registo\RegistarEmpresa) e o `created` do Tenant
 * provisionam várias coisas, algumas dentro de try/catch que só escrevem no
 * log se falharem: papéis, dono, subscrição, módulos do plano, impostos,
 * armazém principal, formas de pagamento, série FR, categorias. Uma que falhe
 * em silêncio só se descobre quando o cliente tenta vender.
 *
 * Vivia dentro do comando `empresa:diagnostico`; saiu para aqui para o agente
 * externo poder fazer a mesma pergunta pela API sem ler texto de consola. O
 * comando e a API mostram exactamente as mesmas verificações.
 */
class DiagnosticoDaEmpresa
{
    /**
     * @return array{empresa: array, utilizadores: array, papeis: array, subscricao: ?array, pedidos: array,
     *               modulos: array, facturacao: array, erros: array, faltas: list<string>, completa: bool}
     */
    public function para(Tenant $t): array
    {
        $faltas = [];

        if (! $t->is_active) {
            $faltas[] = 'A empresa está desactivada.';
        }

        /* Utilizadores e dono */
        $superAdmin = Role::where('tenant_id', $t->id)->where('name', 'Super Admin')->first();
        $utilizadores = $t->users()->get()->map(function ($u) use ($t) {
            $papeis = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $u->id)->where('model_has_roles.model_type', get_class($u))
                ->where('model_has_roles.tenant_id', $t->id)->pluck('roles.name')->all();

            return [
                'id' => $u->id,
                'nome' => $u->name,
                'email' => $u->email,
                'activo_na_empresa' => (bool) $u->pivot->is_active,
                'conta_activa' => $u->is_active !== false,
                'email_verificado' => $u->email_verified_at !== null,
                'papeis' => $papeis,
            ];
        })->values()->all();

        if ($utilizadores === []) {
            $faltas[] = 'A empresa não tem utilizadores.';
        }

        $donos = $superAdmin ? DB::table('model_has_roles')->where('role_id', $superAdmin->id)->where('tenant_id', $t->id)->count() : 0;
        if (! $superAdmin) {
            $faltas[] = 'Não existe o papel «Super Admin» da empresa (createDefaultRolesForTenant falhou?).';
        } elseif ($donos === 0) {
            $faltas[] = 'Ninguém tem o papel «Super Admin» da empresa (o dono ficou sem acesso total).';
        }

        /* Papéis */
        $papeis = Role::where('tenant_id', $t->id)->withCount('permissions')->orderBy('name')->get()
            ->map(fn ($r) => ['nome' => $r->name, 'permissoes' => (int) $r->permissions_count])->values()->all();
        if ($papeis === []) {
            $faltas[] = 'A empresa não tem papéis.';
        }

        /* Subscrição e pedidos */
        $sub = $t->activeSubscription()->first();
        if (! $sub) {
            $faltas[] = 'Sem subscrição activa (nem em teste).';
        }
        // O DESTINO de cada pedido: sem isto, uma empresa sem plano não dizia
        // se o pedido foi recusado, por quem e porquê (empresas 108 e 110).
        // Da referência e do comprovativo diz-se só se existem.
        $pedidos = Order::with(['plan', 'rejectedBy:id,name', 'approvedBy:id,name'])->where('tenant_id', $t->id)->latest('id')->limit(5)->get()->map(fn ($o) => [
            'id' => $o->id, 'estado' => $o->status, 'plano' => $o->plan?->name, 'valor' => (float) $o->amount,
            'criado_em' => $o->created_at?->toIso8601String(),
            'forma_de_pagamento' => $o->payment_method,
            'com_referencia' => filled($o->payment_reference),
            'com_comprovativo' => filled($o->payment_proof),
            'aprovado_em' => $o->approved_at?->toIso8601String(),
            'aprovado_por' => $o->approvedBy ? "#{$o->approvedBy->id} {$o->approvedBy->name}" : null,
            'recusado_em' => $o->rejected_at?->toIso8601String(),
            'recusado_por' => $o->rejectedBy ? "#{$o->rejectedBy->id} {$o->rejectedBy->name}" : ($o->rejected_at ? '(sem utilizador: agente ou sistema)' : null),
            'motivo_da_recusa' => $o->rejection_reason,
        ])->values()->all();

        // Todas as subscrições, e não só a viva: é o histórico que explica
        // uma empresa sem plano.
        $subscricoes = $t->subscriptions()->with('plan:id,name')->latest('id')->limit(5)->get()->map(fn ($x) => [
            'id' => $x->id, 'plano' => $x->plan?->name, 'estado' => $x->status,
            'criada_em' => $x->created_at?->toIso8601String(), 'termina' => $x->ends_at?->toIso8601String(),
        ])->values()->all();

        /* Módulos e permissões */
        $doPlano = $sub?->plan ? $sub->plan->modules()->pluck('slug')->all() : [];
        $activos = $t->modules()->wherePivot('is_active', true)->pluck('slug')->all();
        if ($emFalta = array_diff($doPlano, $activos)) {
            $faltas[] = 'Módulos do plano por activar: ' . implode(', ', $emFalta);
        }

        $permissoesPorModulo = [];
        if ($superAdmin) {
            $nomes = DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_has_permissions.role_id', $superAdmin->id)->pluck('permissions.name');
            $semPermissoes = [];
            foreach ($activos as $slug) {
                $prefixos = CatalogoDePermissoes::MODULOS[$slug]['prefixos'] ?? null;
                if ($prefixos === null) {
                    continue;
                }
                $n = $nomes->filter(fn ($p) => collect($prefixos)->contains(fn ($px) => str_starts_with($p, $px)))->count();
                $permissoesPorModulo[$slug] = $n;
                if ($n === 0) {
                    $semPermissoes[] = $slug;
                }
            }
            if ($semPermissoes) {
                $faltas[] = 'O Super Admin não tem permissões nos módulos activos: ' . implode(', ', $semPermissoes) . ' (tenant:reconcile-modules resolve).';
            }
        }

        /* Facturação: o que o `created` provisiona */
        $def = InvoicingSettings::where('tenant_id', $t->id)->first();
        $impostos = Tax::withoutGlobalScopes()->where('tenant_id', $t->id)->count();
        $armazem = Warehouse::withoutGlobalScopes()->where('tenant_id', $t->id)->where('is_default', true)->where('is_active', true)->first();
        $pagamentos = PaymentMethod::withoutGlobalScopes()->where('tenant_id', $t->id)->count();
        $categorias = Category::withoutGlobalScopes()->where('tenant_id', $t->id)->count();
        $series = InvoicingSeries::withoutGlobalScopes()->where('tenant_id', $t->id)->get();
        $contas = Schema::hasTable('treasury_accounts') ? DB::table('treasury_accounts')->where('tenant_id', $t->id)->count() : null;

        if ($impostos === 0) {
            $faltas[] = 'Sem impostos (taxes:backfill --tenant resolve).';
        }
        if (! $armazem) {
            $faltas[] = 'Sem armazém principal (warehouses:backfill --tenant resolve).';
        }
        if ($pagamentos === 0) {
            $faltas[] = 'Sem formas de pagamento (payment-methods:backfill --tenant resolve).';
        }
        if (! $series->contains(fn ($s) => $s->document_type === 'pos')) {
            $faltas[] = 'Sem a série FR do POS.';
        }

        /* Erros desde que nasceu */
        $erros = ErroDoSistema::where('tenant_id', $t->id)->orderByDesc('ultima_vez')->limit(15)->get()->map(fn ($e) => [
            'id' => $e->id, 'nivel' => $e->nivel, 'ocorrencias' => (int) $e->ocorrencias,
            'ultima_vez' => optional($e->ultima_vez)->toIso8601String(), 'mensagem' => mb_strimwidth((string) $e->mensagem, 0, 300, '…'),
            'resolvido' => $e->resolvido_em !== null,
        ])->values()->all();

        return [
            'empresa' => [
                'id' => $t->id, 'nome' => $t->name, 'nif' => $t->nif, 'regime' => $t->regime,
                'activa' => (bool) $t->is_active, 'criada_em' => $t->created_at?->toIso8601String(),
            ],
            'utilizadores' => $utilizadores,
            'papeis' => $papeis,
            'subscricao' => $sub ? [
                'plano' => $sub->plan?->name, 'estado' => $sub->status,
                'teste_ate' => $sub->trial_ends_at?->toIso8601String(), 'termina' => $sub->ends_at?->toIso8601String(),
                'valor' => $sub->amount === null ? null : (float) $sub->amount,
            ] : null,
            'pedidos' => $pedidos,
            'subscricoes' => $subscricoes,
            'modulos' => [
                'activos' => array_values($activos),
                'no_plano' => array_values($doPlano),
                'activos_fora_do_plano' => array_values(array_diff($activos, $doPlano)),
                'permissoes_do_super_admin' => $permissoesPorModulo,
            ],
            'facturacao' => [
                'definicoes' => (bool) $def,
                'ambiente_agt' => $def?->agt_environment,
                'envio_automatico_agt' => $def ? (bool) $def->agt_auto_submit : null,
                'cae' => $def?->agt_eac_code,
                'impostos' => $impostos,
                'armazem_principal' => $armazem?->name,
                'formas_de_pagamento' => $pagamentos,
                'categorias' => $categorias,
                'contas_de_tesouraria' => $contas,
                'series' => $series->map(fn ($s) => [
                    'tipo' => $s->document_type, 'prefixo' => $s->prefix, 'activa' => (bool) $s->is_active,
                    'agt_series_id' => $s->agt_series_id, 'agt_ambiente' => $s->agt_environment,
                ])->values()->all(),
            ],
            'erros' => $erros,
            'faltas' => $faltas,
            'completa' => $faltas === [],
        ];
    }
}
