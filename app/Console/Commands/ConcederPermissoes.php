<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

/**
 * Concede permissões a um utilizador DENTRO de um tenant (team-scoped).
 *
 * Sem --aplicar é só uma consulta: mostra o utilizador, o tenant e o que muda.
 * Com --aplicar, atribui as permissões (directas ao utilizador) no contexto do
 * tenant certo. Escrito para correr pela rota de manutenção — por isso o
 * --tenant aceita um fragmento do nome SEM espaços (ex.: "Vital").
 */
class ConcederPermissoes extends Command
{
    protected $signature = 'permissoes:conceder
        {--tenant= : id ou fragmento do nome do tenant (sem espacos)}
        {--email= : email do utilizador}
        {--permissoes= : nomes das permissoes separados por virgula}
        {--aplicar : aplica de facto (sem isto, so mostra)}';

    protected $description = 'Concede permissoes a um utilizador dentro de um tenant';

    public function handle(): int
    {
        $alvoTenant = trim((string) $this->option('tenant'));
        $email = trim((string) $this->option('email'));
        $nomes = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('permissoes')))));

        if ($alvoTenant === '' || $email === '' || !$nomes) {
            $this->error('Faltam --tenant, --email ou --permissoes.');

            return self::FAILURE;
        }

        // Resolver o tenant (id ou nome-like) — tem de ser ÚNICO.
        $q = ctype_digit($alvoTenant)
            ? Tenant::where('id', (int) $alvoTenant)
            : Tenant::where('name', 'like', '%' . $alvoTenant . '%');
        $tenants = $q->get(['id', 'name', 'is_active']);

        if ($tenants->count() !== 1) {
            $this->error("Tenant nao unico para '{$alvoTenant}' ({$tenants->count()} encontrados):");
            foreach ($tenants as $t) {
                $this->line("  #{$t->id}  {$t->name}");
            }

            return self::FAILURE;
        }
        $tenant = $tenants->first();

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("Utilizador {$email} nao encontrado.");

            return self::FAILURE;
        }

        $membro = $tenant->users()->where('users.id', $user->id)->exists();

        $this->line("Tenant:      #{$tenant->id}  {$tenant->name}" . ($tenant->is_active ? '' : '  (SUSPENSO)'));
        $this->line("Utilizador:  #{$user->id}  {$user->name}  <{$email}>");
        $this->line('Membro do tenant: ' . ($membro ? 'sim' : 'NAO'));

        if (!$membro) {
            $this->error('O utilizador nao pertence a este tenant — abortado por seguranca.');

            return self::FAILURE;
        }

        // Contexto de team = este tenant.
        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $jaTem = $user->getAllPermissions()->pluck('name')->all();
        $novas = array_values(array_diff($nomes, $jaTem));

        $this->newLine();
        $this->line('Permissoes pedidas: ' . implode(', ', $nomes));
        $this->line('Ja tem:             ' . (implode(', ', array_intersect($nomes, $jaTem)) ?: '—'));
        $this->line('Vai acrescentar:    ' . (implode(', ', $novas) ?: '— (nada a fazer)'));

        if (!$this->option('aplicar')) {
            $this->newLine();
            $this->comment('CONSULTA apenas. Corra com --aplicar para conceder.');

            return self::SUCCESS;
        }

        // Garante que as permissoes existem (globais, guard web) e concede.
        foreach ($nomes as $nome) {
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }
        $user->givePermissionTo($nomes);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info("Concedidas {$user->name} @ {$tenant->name}: " . implode(', ', $nomes));

        return self::SUCCESS;
    }
}
