<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SossaudeCredential extends Command
{
    protected $signature = 'sossaude:credential {tenant} {--name=SOSSaude} {--days=90} {--revoke=}';
    protected $description = 'Emite ou revoga credencial M2M da ponte comercial SOSSaúde';

    public function handle(): int
    {
        $tenant = filter_var($this->argument('tenant'), FILTER_VALIDATE_INT);
        if (!$tenant || !DB::table('tenants')->where('id', $tenant)->whereNull('deleted_at')->where('is_active', true)->exists()) {
            $this->error('Empresa activa não encontrada.');
            return self::FAILURE;
        }
        if ($id = $this->option('revoke')) {
            $n = DB::table('sossaude_credentials')->where('tenant_id', $tenant)->where('id', $id)
                ->update(['revoked_at' => now(), 'updated_at' => now()]);
            $this->info($n ? 'Credencial revogada.' : 'Credencial não encontrada.');
            return $n ? self::SUCCESS : self::FAILURE;
        }
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        if (!$days || $days < 1 || $days > 365 || mb_strlen($this->option('name')) > 255) {
            $this->error('Validade: 1–365 dias; nome: até 255 caracteres.');
            return self::FAILURE;
        }
        $token = 'sossaude_' . bin2hex(random_bytes(32));
        $id = DB::table('sossaude_credentials')->insertGetId([
            'tenant_id' => $tenant, 'name' => $this->option('name'),
            'token_hash' => hash('sha256', $token), 'scopes' => json_encode(['billing-drafts.write']),
            'expires_at' => now()->addDays($days), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->info("Credencial {$id}. Guarde o segredo em cofre; não será mostrado novamente.");
        $this->line($token);
        return self::SUCCESS;
    }
}
