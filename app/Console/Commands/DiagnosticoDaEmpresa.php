<?php

namespace App\Console\Commands;

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
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * UMA EMPRESA ACABADA DE INSCREVER: FICOU TUDO PRONTO? — só lê.
 *
 * A inscrição (App\Services\Registo\RegistarEmpresa) e o `created` do Tenant
 * provisionam várias coisas, algumas dentro de try/catch que só escrevem no
 * log se falharem: papéis, dono, subscrição, módulos do plano, impostos,
 * armazém principal, formas de pagamento, série FR, categorias. Uma que falhe
 * em silêncio só se descobre quando o cliente tenta vender. Isto confere cada
 * uma e diz o que falta — sem criar nada (os `*:backfill` é que criam, e só
 * por ordem).
 */
class DiagnosticoDaEmpresa extends Command
{
    protected $signature = 'empresa:diagnostico {--tenant= : id da empresa}';

    protected $description = 'Confere se uma empresa ficou completa depois da inscrição: dono, papéis, subscrição, módulos, permissões, impostos, armazém, pagamentos, séries e erros (só lê)';

    private array $faltas = [];

    public function handle(): int
    {
        $t = Tenant::find((int) $this->option('tenant'));

        if (! $t) {
            $this->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        $this->info("EMPRESA #{$t->id} — {$t->name}");
        $this->line("  NIF: {$t->nif} · regime: {$t->regime} · activa: " . ($t->is_active ? 'sim' : 'NÃO') . " · criada: {$t->created_at}");
        if (! $t->is_active) {
            $this->falta('A empresa está desactivada.');
        }

        /* Utilizadores e dono */
        $this->newLine();
        $this->info('Utilizadores');
        $utilizadores = $t->users()->get();
        $superAdmin = Role::where('tenant_id', $t->id)->where('name', 'Super Admin')->first();
        foreach ($utilizadores as $u) {
            setPermissionsTeamId($t->id);
            $papeis = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $u->id)->where('model_has_roles.model_type', get_class($u))
                ->where('model_has_roles.tenant_id', $t->id)->pluck('roles.name')->all();
            $this->line(sprintf('  #%d %s <%s> · activo na empresa: %s · conta activa: %s · email verificado: %s · papéis: %s',
                $u->id, $u->name, $u->email, $u->pivot->is_active ? 'sim' : 'NÃO', $u->is_active === false ? 'NÃO' : 'sim',
                $u->email_verified_at ? 'sim' : 'não', $papeis ? implode(', ', $papeis) : '(nenhum)'));
        }
        if ($utilizadores->isEmpty()) {
            $this->falta('A empresa não tem utilizadores.');
        }
        $donos = $superAdmin ? DB::table('model_has_roles')->where('role_id', $superAdmin->id)->where('tenant_id', $t->id)->count() : 0;
        if (! $superAdmin) {
            $this->falta('Não existe o papel «Super Admin» da empresa (createDefaultRolesForTenant falhou?).');
        } elseif ($donos === 0) {
            $this->falta('Ninguém tem o papel «Super Admin» da empresa (o dono ficou sem acesso total).');
        }

        /* Papéis */
        $papeis = Role::where('tenant_id', $t->id)->withCount('permissions')->orderBy('name')->get();
        $this->line('  Papéis: ' . ($papeis->isEmpty() ? '(nenhum)' : $papeis->map(fn ($r) => "{$r->name} ({$r->permissions_count})")->implode(', ')));
        if ($papeis->isEmpty()) {
            $this->falta('A empresa não tem papéis.');
        }

        /* Subscrição e pedidos */
        $this->newLine();
        $this->info('Subscrição');
        $sub = $t->activeSubscription()->first();
        if ($sub) {
            $this->line(sprintf('  plano: %s · estado: %s · teste até: %s · termina: %s · valor: %s',
                $sub->plan?->name ?? '(sem plano)', $sub->status, $sub->trial_ends_at ?? '—', $sub->ends_at ?? '—', $sub->amount ?? '—'));
        } else {
            $this->falta('Sem subscrição activa (nem em teste).');
        }
        $pedidos = Order::where('tenant_id', $t->id)->latest('id')->limit(5)->get();
        foreach ($pedidos as $o) {
            $this->line(sprintf('  pedido #%d · %s · plano %s · %s', $o->id, $o->status, $o->plan?->name ?? '—', $o->created_at));
        }

        /* Módulos e permissões */
        $this->newLine();
        $this->info('Módulos');
        $doPlano = $sub?->plan ? $sub->plan->modules()->pluck('slug')->all() : [];
        $activos = $t->modules()->wherePivot('is_active', true)->pluck('slug')->all();
        $this->line('  activos: ' . (implode(', ', $activos) ?: '(nenhum)'));
        $this->line('  no plano: ' . (implode(', ', $doPlano) ?: '(nenhum)'));
        if ($emFalta = array_diff($doPlano, $activos)) {
            $this->falta('Módulos do plano por activar: ' . implode(', ', $emFalta));
        }
        if ($aMais = array_diff($activos, $doPlano)) {
            $this->line('  (activos fora do plano: ' . implode(', ', $aMais) . ')');
        }

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
                $this->line(sprintf('  permissões do Super Admin em %-14s %d', $slug . ':', $n));
                if ($n === 0) {
                    $semPermissoes[] = $slug;
                }
            }
            if ($semPermissoes) {
                $this->falta('O Super Admin não tem permissões nos módulos activos: ' . implode(', ', $semPermissoes) . ' (tenant:reconcile-modules resolve).');
            }
        }

        /* Facturação: o que o `created` provisiona */
        $this->newLine();
        $this->info('Facturação');
        $def = InvoicingSettings::where('tenant_id', $t->id)->first();
        $this->line('  definições: ' . ($def ? 'sim' : 'NÃO') . ($def ? " · ambiente AGT: {$def->agt_environment} · envio automático: " . ($def->agt_auto_submit ? 'sim' : 'não') . ' · CAE: ' . ($def->agt_eac_code ?: '—') : ''));
        $impostos = Tax::withoutGlobalScopes()->where('tenant_id', $t->id)->count();
        $armazem = Warehouse::withoutGlobalScopes()->where('tenant_id', $t->id)->where('is_default', true)->where('is_active', true)->first();
        $pagamentos = PaymentMethod::withoutGlobalScopes()->where('tenant_id', $t->id)->count();
        $categorias = Category::withoutGlobalScopes()->where('tenant_id', $t->id)->count();
        $series = InvoicingSeries::withoutGlobalScopes()->where('tenant_id', $t->id)->get();
        $this->line("  impostos: {$impostos} · armazém principal: " . ($armazem ? $armazem->name : 'NÃO') . " · formas de pagamento: {$pagamentos} · categorias: {$categorias}");
        $this->line('  séries: ' . ($series->isEmpty() ? '(nenhuma)' : $series->map(fn ($s) => "{$s->document_type}:{$s->prefix}" . ($s->agt_series_id ? " [AGT {$s->agt_environment}]" : '') . ($s->is_active ? '' : ' (inactiva)'))->implode(', ')));
        if ($impostos === 0) {
            $this->falta('Sem impostos (taxes:backfill --tenant resolve).');
        }
        if (! $armazem) {
            $this->falta('Sem armazém principal (warehouses:backfill --tenant resolve).');
        }
        if ($pagamentos === 0) {
            $this->falta('Sem formas de pagamento (payment-methods:backfill --tenant resolve).');
        }
        if (! $series->contains(fn ($s) => $s->document_type === 'pos')) {
            $this->falta('Sem a série FR do POS.');
        }
        if (Schema::hasTable('treasury_accounts')) {
            $contas = DB::table('treasury_accounts')->where('tenant_id', $t->id)->count();
            $this->line("  contas de tesouraria: {$contas}");
        }

        /* Erros desde que nasceu */
        $this->newLine();
        $this->info('Erros desta empresa desde a criação');
        $erros = ErroDoSistema::where('tenant_id', $t->id)->orderByDesc('ultima_vez')->limit(15)->get();
        if ($erros->isEmpty()) {
            $this->line('  nenhum');
        }
        foreach ($erros as $e) {
            $this->line(sprintf('  #%d %s x%d %s — %s', $e->id, $e->nivel, $e->ocorrencias, $e->ultima_vez, mb_strimwidth((string) $e->mensagem, 0, 120, '…')));
        }

        /* Veredicto */
        $this->newLine();
        if ($this->faltas === []) {
            $this->info('✓ Nada em falta: a empresa ficou completa.');
        } else {
            $this->warn('Em falta:');
            foreach ($this->faltas as $f) {
                $this->line("  ✗ {$f}");
            }
        }

        return self::SUCCESS;
    }

    private function falta(string $texto): void
    {
        $this->faltas[] = $texto;
    }
}
