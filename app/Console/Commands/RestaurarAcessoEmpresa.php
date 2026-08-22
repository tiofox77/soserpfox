<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/** Recria ou religa o login principal de uma empresa sem duplicar utilizadores. */
class RestaurarAcessoEmpresa extends Command
{
    protected $signature = 'utilizadores:restaurar-acesso
        {--tenant= : id da empresa}
        {--email= : email de login}
        {--nome-ficheiro= : ficheiro com o nome na primeira linha}
        {--senha-ficheiro= : ficheiro com a palavra-passe na primeira linha}
        {--aplicar : grava; sem isto apenas simula}';

    protected $description = 'Recria ou religa o proprietário de uma empresa (simulação por omissão)';

    public function handle(): int
    {
        $tenant = Tenant::find($this->option('tenant'));
        $email = mb_strtolower(trim((string) $this->option('email')));
        if (!$tenant || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Empresa ou email inválido.');
            return self::FAILURE;
        }

        $nome = $this->ler($this->option('nome-ficheiro')) ?: $tenant->name . ' — Administrador';
        $senha = $this->ler($this->option('senha-ficheiro'));
        if ($senha === '' && $this->option('aplicar')) {
            $senha = $this->gerarSenha();
        }
        if ($senha !== '' && mb_strlen($senha) < 10) {
            $this->error('A palavra-passe tem de ter pelo menos 10 caracteres.');
            return self::FAILURE;
        }

        $user = User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
        $this->line("Empresa: #{$tenant->id} {$tenant->name}");
        $this->line('Utilizador: ' . ($user ? "existente #{$user->id}" : 'será criado'));
        $this->line("Login: {$email}");
        if (!$this->option('aplicar')) {
            $this->warn('Simulação: nada foi alterado.');
            return self::SUCCESS;
        }

        DB::transaction(function () use (&$user, $tenant, $email, $nome, $senha) {
            if (!$user) {
                $user = new User();
                $user->email = $email;
            } elseif ($user->trashed()) {
                $user->restore();
            }

            $user->forceFill([
                'name' => $nome, 'password' => Hash::make($senha),
                'tenant_id' => $tenant->id, 'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $role = Role::where('tenant_id', $tenant->id)->where('name', 'Super Admin')->first()
                ?? Role::where('tenant_id', $tenant->id)->where('name', 'Admin')->firstOrFail();

            $user->tenants()->syncWithoutDetaching([$tenant->id => [
                'role_id' => $role->id, 'is_active' => true, 'joined_at' => now(),
            ]]);
            setPermissionsTeamId($tenant->id);
            if (!$user->hasRole($role->name)) $user->assignRole($role);
        });

        $this->info("Acesso restaurado. Utilizador #{$user->id}; entra com {$email}.");
        $this->warn("PALAVRA-PASSE (mostrada uma vez): {$senha}");
        return self::SUCCESS;
    }

    private function ler(?string $ficheiro): string
    {
        $f = trim((string) $ficheiro);
        if ($f !== '' && !is_file($f) && is_file(base_path($f))) $f = base_path($f);
        return is_file($f) ? trim((string) strtok((string) file_get_contents($f), "\r\n")) : '';
    }

    private function gerarSenha(): string
    {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $senha = 'S!';
        for ($i = 0; $i < 14; $i++) $senha .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        return $senha;
    }
}
