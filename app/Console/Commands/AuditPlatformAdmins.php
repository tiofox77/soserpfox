<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audita — e opcionalmente repara — quem tem acesso ao painel /superadmin.
 *
 * Só deve ter acesso quem tem a flag users.is_super_admin. Uma role chamada
 * "Super Admin" com roles.tenant_id NULL, atribuída dentro do contexto de uma
 * empresa (model_has_roles.tenant_id preenchido), dava acesso à plataforma a
 * donos de empresa — foi assim que a escalação de privilégios aconteceu.
 *
 *   php artisan superadmin:audit
 *   php artisan superadmin:audit --fix
 */
class AuditPlatformAdmins extends Command
{
    protected $signature = 'superadmin:audit {--fix : Remove atribuições indevidas e registos órfãos}';
    protected $description = 'Audita quem tem acesso ao painel de super admin da plataforma';

    public function handle(): int
    {
        $this->info('=== Acesso à plataforma (/superadmin) ===');
        $this->newLine();

        // 1) Quem tem a flag — o único acesso legítimo
        $porFlag = User::where('is_super_admin', true)->get(['id', 'email', 'is_active']);
        $this->line("  Por flag is_super_admin: {$porFlag->count()}");
        foreach ($porFlag as $u) {
            $this->line("     • #{$u->id} " . $this->mascarar($u->email)
                . ($u->is_active ? '' : ' <fg=yellow>(inactivo)</>'));
        }

        // 2) Roles globais atribuídas dentro do contexto de uma empresa
        $suspeitas = DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->join('users as u', 'u.id', '=', 'mhr.model_id')
            ->whereNull('r.tenant_id')
            ->whereNotNull('mhr.tenant_id')
            ->where('u.is_super_admin', false)
            ->get(['u.id', 'u.email', 'r.name', 'mhr.tenant_id']);

        $this->newLine();
        $this->line("  Roles globais atribuídas dentro de uma empresa: {$suspeitas->count()}");
        foreach ($suspeitas as $s) {
            $this->line("     • #{$s->id} " . $this->mascarar($s->email)
                . " — role <fg=yellow>{$s->name}</> no contexto do tenant {$s->tenant_id}");
        }

        // 3) Atribuições órfãs (utilizador já apagado)
        $orfaos = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->whereNotIn('model_id', DB::table('users')->pluck('id'))
            ->count();
        $this->line("  Atribuições órfãs (utilizador inexistente): {$orfaos}");

        // 4) Veredicto real: quem passa por isPlatformSuperAdmin()
        $this->newLine();
        $comAcesso = User::all()->filter(fn ($u) => $u->isPlatformSuperAdmin());
        $this->info("  Total com acesso efectivo à plataforma: {$comAcesso->count()}");
        foreach ($comAcesso as $u) {
            $viaFlag = $u->is_super_admin ? 'flag' : '<fg=red>role</>';
            $this->line("     • #{$u->id} " . $this->mascarar($u->email) . " (via {$viaFlag})");
        }

        if (!$this->option('fix')) {
            if ($suspeitas->count() || $orfaos) {
                $this->newLine();
                $this->warn('  Use --fix para remover as atribuições indevidas e os órfãos.');
            }
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('A reparar...');

        $removidas = 0;
        foreach ($suspeitas as $s) {
            $removidas += DB::table('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->where('mhr.model_id', $s->id)
                ->where('mhr.model_type', User::class)
                ->whereNull('r.tenant_id')
                ->whereNotNull('mhr.tenant_id')
                ->delete();
        }

        $orfRemovidos = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->whereNotIn('model_id', DB::table('users')->pluck('id'))
            ->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info("   Atribuições indevidas removidas: {$removidas}");
        $this->info("   Órfãos removidos: {$orfRemovidos}");
        $this->warn('   NOTA: estes utilizadores mantêm as roles da própria empresa. '
            . 'Só perderam o acesso ao painel da plataforma.');

        return self::SUCCESS;
    }

    /** Não despejar emails completos de produção nos registos. */
    private function mascarar(?string $email): string
    {
        if (!$email || !str_contains($email, '@')) {
            return '(sem email)';
        }
        [$user, $dominio] = explode('@', $email, 2);
        $visivel = mb_substr($user, 0, 2);

        return $visivel . str_repeat('*', max(1, mb_strlen($user) - 2)) . '@' . $dominio;
    }
}
