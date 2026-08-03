<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Endpoint ONE-SHOT para provisionar o tenant da
 * FARMACIA MEDICAL CONNECT SERVICE, LDA em produção.
 *
 * Acesso protegido por token. Idempotente: pode ser
 * executado várias vezes sem duplicar dados (firstOrNew
 * em todos os pontos de criação). Recomenda-se REMOVER
 * o ficheiro e a rota assim que o seed tiver sido feito.
 */
class SeedMedicalConnectController extends Controller
{
    /**
     * Token de protecção. Sem isto qualquer pessoa que descobrisse
     * a rota poderia invocar o seed.
     */
    private const TOKEN = 'mc-srv-2026-jUn5x9Kqz7TpL4mNvWeR8sYf3D2bH6a';

    public function __invoke(string $token)
    {
        abort_unless(hash_equals(self::TOKEN, $token), 404);

        $report = [];
        $generatedPasswords = [];

        try {
            DB::beginTransaction();

            // ───────────────────────────────────────────────────────────
            // 1) Plano "Starter"
            // ───────────────────────────────────────────────────────────
            $plan = Plan::where('slug', 'starter')
                ->orWhere('name', 'Starter')
                ->first();

            if (!$plan) {
                throw new \Exception('Plano "Starter" não encontrado em `plans`. Garanta que o seed de planos foi executado.');
            }
            $report[] = "[OK] Plano usado: #{$plan->id} {$plan->name} (slug={$plan->slug})";

            // ───────────────────────────────────────────────────────────
            // 2) Tenant — idempotente por NIF
            // ───────────────────────────────────────────────────────────
            $tenant = Tenant::firstOrNew(['nif' => '5002637081']);
            $isNewTenant = !$tenant->exists;

            $tenant->fill([
                'name'         => 'FARMACIA MEDICAL CONNECT SERVICE, LDA',
                'company_name' => 'FARMACIA MEDICAL CONNECT SERVICE, LDA',
                'nif'          => '5002637081',
                'address'      => 'RUA OLIMPIO MACUEIRA - BAIRRO PALANCA, (JUNTO A ENTRADA DA RUA I1, ESTRADA NOVA), CASA S/N',
                'city'         => 'KILAMBA KIAXI',
                'country'      => 'AO',
                'email'        => 'bleasekiama2000@gmail.com',
                'is_active'    => true,
                'max_users'    => max((int) ($plan->max_users ?? 5), 4),
            ]);

            $settings = $tenant->settings ?? [];
            $settings['provincia'] = 'LUANDA';
            $settings['municipio'] = 'KILAMBA KIAXI';
            $tenant->settings = $settings;
            $tenant->save();

            $report[] = ($isNewTenant ? '[+] Tenant criado' : '[=] Tenant existente atualizado')
                . ": #{$tenant->id} {$tenant->name}";

            // ───────────────────────────────────────────────────────────
            // 3) Roles padrão (Spatie team = tenant_id)
            // ───────────────────────────────────────────────────────────
            setPermissionsTeamId($tenant->id);
            createDefaultRolesForTenant($tenant->id);
            $report[] = '[OK] Roles padrão criadas/sincronizadas';

            $superAdminRole = Role::where('name', 'Super Admin')->where('tenant_id', $tenant->id)->first();
            $caixaRole      = Role::where('name', 'Caixa')->where('tenant_id', $tenant->id)->first();

            // ───────────────────────────────────────────────────────────
            // 4) Super Admin do tenant
            // ───────────────────────────────────────────────────────────
            $admin = $this->upsertUser(
                tenant: $tenant,
                email: 'bleasekiama2000@gmail.com',
                name: 'Admin Medical Connect',
                role: $superAdminRole,
                report: $report,
                generated: $generatedPasswords,
                label: 'Admin'
            );

            // ───────────────────────────────────────────────────────────
            // 5) 3 utilizadores tipo Caixa
            // ───────────────────────────────────────────────────────────
            $caixas = [
                ['name' => 'Caixa 1 - Medical Connect', 'email' => 'caixa1@farmaciamedicalconnect.ao'],
                ['name' => 'Caixa 2 - Medical Connect', 'email' => 'caixa2@farmaciamedicalconnect.ao'],
                ['name' => 'Caixa 3 - Medical Connect', 'email' => 'caixa3@farmaciamedicalconnect.ao'],
            ];
            foreach ($caixas as $c) {
                $this->upsertUser(
                    tenant: $tenant,
                    email: $c['email'],
                    name: $c['name'],
                    role: $caixaRole,
                    report: $report,
                    generated: $generatedPasswords,
                    label: 'Caixa'
                );
            }

            // ───────────────────────────────────────────────────────────
            // 6) Subscription do plano Starter
            // ───────────────────────────────────────────────────────────
            $existingSub = $tenant->subscriptions()->whereIn('status', ['active', 'trial'])->first();
            if (!$existingSub) {
                $now = now();
                $trialDays = (int) $plan->trial_days;

                if ($trialDays > 0) {
                    $status = 'trial';
                    $end    = $now->copy()->addDays($trialDays);
                } else {
                    $status = 'active';
                    $end    = $now->copy()->addMonth();
                }

                $sub = $tenant->subscriptions()->create([
                    'plan_id'              => $plan->id,
                    'status'               => $status,
                    'trial_ends_at'        => $status === 'trial' ? $end : null,
                    'current_period_start' => $now,
                    'current_period_end'   => $end,
                    'ends_at'              => $end,
                    'amount'               => $plan->price_monthly,
                    'billing_cycle'        => 'monthly',
                ]);
                $report[] = "[+] Subscription criada (id #{$sub->id}, status={$status}, até {$end->format('Y-m-d')})";
            } else {
                $report[] = "[=] Subscription já existente (id #{$existingSub->id}, status={$existingSub->status})";
            }

            // ───────────────────────────────────────────────────────────
            // 7) Ativar módulos incluídos no plano
            // ───────────────────────────────────────────────────────────
            if ($plan->included_modules && is_array($plan->included_modules)) {
                $attached = 0;
                foreach ($plan->included_modules as $slug) {
                    $module = Module::where('slug', $slug)->first();
                    if (!$module) {
                        $report[] = "[!] Módulo não encontrado: {$slug}";
                        continue;
                    }
                    if (!$tenant->modules()->where('modules.id', $module->id)->exists()) {
                        $tenant->modules()->attach($module->id, [
                            'is_active'    => true,
                            'activated_at' => now(),
                        ]);
                        $attached++;
                    }
                }
                $report[] = "[OK] Módulos do plano sincronizados (+{$attached} novos)";
            }

            // ───────────────────────────────────────────────────────────
            // 8) Dados contabilísticos padrão
            // ───────────────────────────────────────────────────────────
            initializeAccountingDataForTenant($tenant->id);
            $report[] = '[OK] Dados contabilísticos (contas, diários, impostos, centros de custo) inicializados';

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->make(
                "<pre style='color:#a00;font-family:monospace'>ERRO: "
                . htmlspecialchars($e->getMessage())
                . "\n\n"
                . htmlspecialchars($e->getTraceAsString())
                . "</pre>",
                500
            );
        }

        return $this->renderReport($report, $generatedPasswords);
    }

    /**
     * Cria (ou atualiza) um utilizador associado ao tenant.
     */
    private function upsertUser(
        Tenant $tenant,
        string $email,
        string $name,
        ?Role $role,
        array &$report,
        array &$generated,
        string $label
    ): User {
        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $isNew = !$user->exists;

        if ($isNew) {
            $pwd = $this->generatePassword();
            $user->name           = $name;
            $user->password       = Hash::make($pwd);
            $user->is_active      = true;
            $user->is_super_admin = false;
            $user->tenant_id      = $tenant->id;
            $user->save();
            $generated[$email] = $pwd;
        } else {
            // Garantir activo + tenant default
            if (!$user->tenant_id) {
                $user->tenant_id = $tenant->id;
            }
            $user->is_active = true;
            if (method_exists($user, 'restore') && $user->trashed()) {
                $user->restore();
            }
            $user->save();
        }

        // Pivot tenant_user
        if (!$user->tenants()->where('tenants.id', $tenant->id)->exists()) {
            $user->tenants()->attach($tenant->id, [
                'is_active' => true,
                'joined_at' => now(),
            ]);
        }

        // Role
        if ($role) {
            setPermissionsTeamId($tenant->id);
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }
        }

        $report[] = ($isNew ? "[+] {$label} criado" : "[=] {$label} existente sincronizado")
            . ": {$email}"
            . ($role ? " (role: {$role->name})" : '');

        return $user;
    }

    private function generatePassword(): string
    {
        // 12 chars URL-safe
        return Str::password(12, letters: true, numbers: true, symbols: false, spaces: false);
    }

    private function renderReport(array $report, array $generated): \Illuminate\Http\Response
    {
        $rows = implode("\n", array_map('htmlspecialchars', $report));

        $creds = '';
        if (!empty($generated)) {
            $creds = '<h3 style="margin-top:24px">🔑 Credenciais GERADAS — anote agora (não voltam a aparecer)</h3>'
                . '<table border="1" cellpadding="8" style="border-collapse:collapse">'
                . '<tr style="background:#fafafa"><th>Email</th><th>Password</th></tr>';
            foreach ($generated as $email => $pwd) {
                $creds .= '<tr><td><b>' . htmlspecialchars($email) . '</b></td>'
                    . '<td><code style="font-size:14px">' . htmlspecialchars($pwd) . '</code></td></tr>';
            }
            $creds .= '</table>';
        } else {
            $creds = '<p><i>Todos os utilizadores já existiam — passwords não foram alteradas.</i></p>';
        }

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Seed Medical Connect</title></head>'
            . '<body style="font-family:system-ui,sans-serif;max-width:900px;margin:30px auto;padding:0 16px">'
            . '<h2 style="color:#059669">✅ Seed: FARMACIA MEDICAL CONNECT SERVICE, LDA</h2>'
            . '<pre style="background:#f4f4f4;padding:14px;border-radius:8px;white-space:pre-wrap;font-size:13px">'
            . $rows
            . '</pre>'
            . $creds
            . '<p style="color:#a00;margin-top:24px"><b>⚠️ Por segurança, remova este endpoint após confirmar acesso:</b><br>'
            . '<code>app/Http/Controllers/Setup/SeedMedicalConnectController.php</code> + rota em <code>routes/web.php</code>.</p>'
            . '</body></html>';

        return response()->make($html, 200);
    }
}
