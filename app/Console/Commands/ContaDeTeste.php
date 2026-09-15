<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\TenantModuleSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * UMA CONTA PARA ALGUÉM EXPERIMENTAR — a empresa, quem entra, e só os módulos pedidos.
 *
 * Pedido de 2026-09-15: criar em produção uma empresa de testes para uma pessoa,
 * com a oficina e a facturação, e dar-lhe o email e a senha. Nenhum comando
 * fazia isto inteiro: `empresas:criar` exige NIF e não cria quem entra, e
 * `utilizadores:criar` inventa o email a partir do nome.
 *
 * É a MESMA sequência do registo público (`RegistarEmpresa`) — empresa, conta
 * com o papel Super Admin da empresa, papéis, plano de contas, definições de RH
 * e modelos de notificação, subscrição e módulos pelo `TenantModuleSyncService`
 * (dependências e permissões) — com três diferenças de propósito:
 *
 *  · SEM EMAIL DE BOAS-VINDAS nem pedido de plano: a senha é entregue por quem
 *    pediu a conta, e um pedido «pendente» apareceria no painel para aprovar;
 *  · PERÍODO DE TESTE com prazo (`--dias`) e valor 0: não entra no ciclo de
 *    facturação, portanto ninguém recebe uma factura por uma conta de testes;
 *  · SÓ OS MÓDULOS PEDIDOS, ligados a um plano que os contenha — um plano sem
 *    eles e uma reconciliação futura (`tenant:reconcile-modules`) tirava-os.
 *
 * A SENHA É GERADA AQUI e aparece UMA vez, na saída de `--aplicar`. Nunca vai
 * nos argumentos: em produção isto corre por um endereço HTTP, e um argumento
 * fica nos registos do servidor web.
 *
 * Simulação por omissão. Os dados vão num JSON em base64 (`--dados`), porque a
 * rota de manutenção parte os argumentos nos espaços:
 *   {"empresa":"Momed Valla (Testes)","nome":"Momed Valla","email":"x@y.com","telefone":"929000000"}
 */
class ContaDeTeste extends Command
{
    protected $signature = 'empresas:conta-de-teste
                            {--dados= : JSON em base64 com empresa, nome, email e telefone}
                            {--modulos=invoicing,oficina : slugs dos módulos, separados por vírgula}
                            {--dias=30 : dias do período de teste}
                            {--plano= : id ou slug do plano (sem ele: o mais pequeno que tenha os módulos)}
                            {--aplicar : grava (sem isto é simulação)}';

    protected $description = 'Cria uma empresa de testes com quem entra e só os módulos pedidos (simulação por omissão)';

    public function handle(): int
    {
        $dados = json_decode((string) base64_decode((string) $this->option('dados'), true), true);

        if (! is_array($dados)) {
            $this->error('O --dados tem de ser um JSON em base64 com empresa, nome, email e telefone.');

            return self::FAILURE;
        }

        $nome = trim((string) ($dados['nome'] ?? ''));
        $empresaNome = trim((string) ($dados['empresa'] ?? '')) ?: $nome;
        $email = Str::lower(trim((string) ($dados['email'] ?? '')));
        $telefone = preg_replace('/[^0-9+ ]/', '', (string) ($dados['telefone'] ?? '')) ?: null;
        $dias = max(1, (int) $this->option('dias'));

        if ($nome === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Faltam o nome e um email válido.');

            return self::FAILURE;
        }

        // Uma conta com este email já existe: entra nela e não se cria outra.
        // Uma segunda conta com o mesmo email nem sequer é possível, e mexer na
        // senha de quem já cá está não é o que se pediu.
        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $this->error("Já existe uma conta com o email {$email}. Nada foi criado.");

            return self::FAILURE;
        }

        $pedidos = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('modulos')))));
        $existentes = Module::whereIn('slug', $pedidos)->pluck('slug')->all();

        if ($falta = array_diff($pedidos, $existentes)) {
            $this->error('Módulos que não existem: ' . implode(', ', $falta) . '. Os que há: ' . Module::orderBy('id')->pluck('slug')->implode(', '));

            return self::FAILURE;
        }

        // Os módulos a ligar, com as dependências (a facturação traz a tesouraria).
        $aLigar = array_values(array_unique(array_merge(...array_map(
            fn ($slug) => TenantModuleSyncService::withDependencies($slug),
            $pedidos
        ))));

        $plano = $this->plano($pedidos);

        if (! $plano) {
            $this->error('Nenhum plano tem todos estes módulos (' . implode(', ', $pedidos) . '). Indique um com --plano.');
            $this->planos();

            return self::FAILURE;
        }

        $fim = now()->addDays($dias)->endOfDay();

        $this->line('<options=bold>CONTA DE TESTE A CRIAR</>');
        $this->line("  empresa    {$empresaNome}");
        $this->line("  pessoa     {$nome}");
        $this->line("  email      {$email}");
        $this->line('  telefone   ' . ($telefone ?? '—'));
        $this->line("  plano      {$plano->name} (#{$plano->id}) — em teste, valor 0");
        $this->line('  módulos    ' . implode(', ', $aLigar));
        $this->line('  até        ' . $fim->format('d/m/Y') . " ({$dias} dias)");

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->planos();
            $this->warn('SIMULAÇÃO. Nada foi gravado. Repita com --aplicar.');

            return self::SUCCESS;
        }

        $senha = self::senha();

        [$empresa, $user] = DB::transaction(function () use ($empresaNome, $nome, $email, $telefone, $plano, $fim, $aLigar, $senha) {
            $empresa = Tenant::create([
                'name' => $empresaNome,
                'company_name' => $empresaNome,
                'email' => $email,
                'phone' => $telefone,
                'country' => 'AO',
                'max_users' => (int) ($plano->max_users ?: 3),
                'max_storage_mb' => (int) ($plano->max_storage_mb ?: 1024),
                'is_active' => true,
            ]);

            $user = User::create([
                'name' => $nome,
                'email' => $email,
                'phone' => $telefone,
                'password' => Hash::make($senha),
                'tenant_id' => $empresa->id,
                'is_active' => true,
                'is_super_admin' => false,
            ]);
            // Criada pelo dono da plataforma para uma pessoa concreta: o email
            // é o que essa pessoa deu, não há link de confirmação a seguir.
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->tenants()->attach($empresa->id, ['is_active' => true, 'joined_at' => now()]);

            setPermissionsTeamId($empresa->id);
            createDefaultRolesForTenant($empresa->id);
            if ($papel = Role::where('name', 'Super Admin')->where('tenant_id', $empresa->id)->first()) {
                $user->assignRole($papel);
            }

            initializeAccountingDataForTenant($empresa->id);

            $empresa->subscriptions()->create([
                'plan_id' => $plano->id,
                'status' => 'trial',
                'billing_cycle' => 'monthly',
                'amount' => 0,
                'trial_ends_at' => $fim,
                'current_period_start' => now(),
                'current_period_end' => $fim,
                'ends_at' => $fim,
            ]);

            $sync = new TenantModuleSyncService();
            foreach ($aLigar as $slug) {
                $sync->activateModule($empresa, $slug, false);
            }

            return [$empresa, $user];
        });

        foreach ([
            'definições de RH' => fn () => \App\Services\HR\DefinicoesRH::garantirPara($empresa->id),
            'modelos de notificação' => fn () => \App\Services\Notifications\ModelosPadrao::garantirPara($empresa->id),
        ] as $passo => $fazer) {
            try {
                $fazer();
            } catch (\Throwable $e) {
                $this->line("  <fg=yellow>falhou</> {$passo}: " . $e->getMessage());
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $ligados = $empresa->modules()->wherePivot('is_active', true)->pluck('slug')->all();

        $this->newLine();
        $this->info("Empresa #{$empresa->id} criada, com a conta #{$user->id}.");
        $this->line('  módulos ligados: ' . implode(', ', $ligados));
        $this->newLine();
        $this->line('  <options=bold>ENTRADA</> (só aparece agora)');
        $this->line('  endereço  ' . route('login'));
        $this->line("  email     {$email}");
        $this->line("  senha     {$senha}");

        return self::SUCCESS;
    }

    /** O plano indicado, ou o mais pequeno (menos módulos, depois mais barato) que tenha todos os pedidos. */
    private function plano(array $pedidos): ?Plan
    {
        if ($chave = $this->option('plano')) {
            $plano = is_numeric($chave) ? Plan::find((int) $chave) : Plan::where('slug', $chave)->first();

            return $plano && ! array_diff($pedidos, $plano->modules()->pluck('modules.slug')->all()) ? $plano : null;
        }

        return Plan::with('modules')->get()
            ->filter(fn (Plan $p) => ! array_diff($pedidos, $p->modules->pluck('slug')->all()))
            ->sortBy(fn (Plan $p) => [$p->modules->count(), (float) $p->price_monthly])
            ->first();
    }

    private function planos(): void
    {
        $this->line('<options=bold>PLANOS</>');
        foreach (Plan::with('modules')->orderBy('id')->get() as $p) {
            $this->line(sprintf('  #%-3d %-22s %s', $p->id, $p->slug, $p->modules->pluck('slug')->implode(', ')));
        }
    }

    /**
     * 12 caracteres sem os que se confundem ao ditar (0/O, 1/l/I), com
     * maiúscula, minúscula e número — passa a `RegraDaSenha`.
     */
    public static function senha(): string
    {
        $maiusculas = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        $minusculas = 'abcdefghjkmnpqrstuvwxyz';
        $numeros = '23456789';
        $todos = $maiusculas . $minusculas . $numeros;

        $letras = [
            $maiusculas[random_int(0, strlen($maiusculas) - 1)],
            $minusculas[random_int(0, strlen($minusculas) - 1)],
            $numeros[random_int(0, strlen($numeros) - 1)],
            $numeros[random_int(0, strlen($numeros) - 1)],
        ];
        while (count($letras) < 12) {
            $letras[] = $todos[random_int(0, strlen($todos) - 1)];
        }

        // Baralhar com random_int (o shuffle() não é criptográfico).
        for ($i = count($letras) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$letras[$i], $letras[$j]] = [$letras[$j], $letras[$i]];
        }

        return implode('', $letras);
    }
}
