<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UpdateAdminEmail extends Command
{
    protected $signature = 'admin:set-email {--email=} {--promote} {--demote-others} {--rename-conflict=}';
    protected $description = 'Atualiza o email do super admin para o valor especificado (--email=novo@dominio.com).';

    public function handle()
    {
        $newEmail = trim($this->option('email') ?? '');
        if (!$newEmail) {
            $this->error('Forneça --email=novo@dominio.com');
            return Command::FAILURE;
        }

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error("Email inválido: {$newEmail}");
            return Command::FAILURE;
        }

        $admins = User::where('is_super_admin', true)->get();

        if ($admins->isEmpty()) {
            $this->error('Nenhum super admin encontrado.');
            return Command::FAILURE;
        }

        $this->info("🔍 Super admins encontrados: {$admins->count()}");
        foreach ($admins as $a) {
            $this->line("  - #{$a->id} {$a->name} <{$a->email}>");
        }

        $existing = User::where('email', $newEmail)->first();

        // CASO 1: --promote → utilizador existente vira super-admin
        if ($existing && $this->option('promote')) {
            if ($existing->is_super_admin) {
                $this->info("ℹ️  Utilizador #{$existing->id} ({$existing->email}) já é super admin.");
            } else {
                $existing->is_super_admin = true;
                $existing->email_verified_at = $existing->email_verified_at ?? now();
                $existing->is_active = true;
                $existing->save();
                $this->info("✅ Utilizador #{$existing->id} {$existing->name} <{$existing->email}> PROMOVIDO a super-admin.");
            }

            if ($this->option('demote-others')) {
                $demoted = User::where('is_super_admin', true)
                    ->where('id', '!=', $existing->id)
                    ->update(['is_super_admin' => false]);
                $this->warn("⚠️  {$demoted} outros super-admins foram despromovidos.");
            }

            return Command::SUCCESS;
        }

        if ($existing && !$existing->is_super_admin) {
            $renameTo = trim($this->option('rename-conflict') ?? '');
            if (!$renameTo) {
                $this->error("Email {$newEmail} já está em uso pelo utilizador #{$existing->id} ({$existing->name}).");
                $this->line("   Usa --rename-conflict=novo@dominio.com para renomeá-lo primeiro.");
                $this->line("   Ou --promote para promover esse utilizador a super-admin.");
                return Command::FAILURE;
            }
            if (!filter_var($renameTo, FILTER_VALIDATE_EMAIL)) {
                $this->error("--rename-conflict inválido: {$renameTo}");
                return Command::FAILURE;
            }
            if (User::where('email', $renameTo)->exists()) {
                $this->error("--rename-conflict {$renameTo} também já está em uso.");
                return Command::FAILURE;
            }
            $oldConflictEmail = $existing->email;
            $existing->email = $renameTo;
            $existing->save();
            $this->info("🔄 Utilizador #{$existing->id} renomeado: {$oldConflictEmail} → {$renameTo}");
        }

        // CASO 2: atualizar email do super-admin existente
        $admin = $admins->first();
        $oldEmail = $admin->email;
        $admin->email = $newEmail;
        $admin->email_verified_at = now();
        $admin->save();

        $this->info("✅ Email atualizado com sucesso!");
        $this->info("   #{$admin->id} {$admin->name}");
        $this->info("   {$oldEmail} → {$newEmail}");

        return Command::SUCCESS;
    }
}
