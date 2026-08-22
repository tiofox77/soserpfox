<?php

namespace App\Console\Commands;

use App\Models\AgentToken;
use Illuminate\Console\Command;

class TelefoneResponsavelDoAgente extends Command
{
    protected $signature = 'agente:responsavel-telefone {--prefixo=} {--telefone=} {--aplicar}';
    protected $description = 'Configura o telefone do humano responsável pela credencial do agente';

    public function handle(): int
    {
        $token = AgentToken::with('owner')->where('prefix', $this->option('prefixo'))->first();
        $telefone = preg_replace('/\D+/', '', (string) $this->option('telefone'));
        if (!$token?->owner || !preg_match('/^(?:244)?9\d{8}$/', $telefone)) {
            $this->error('Credencial ou telefone angolano inválido.');
            return self::FAILURE;
        }
        if (!str_starts_with($telefone, '244')) $telefone = '244' . $telefone;
        $telefone = '+' . $telefone;
        $this->line("Credencial: {$token->name}; responsável: {$token->owner->email}; telefone: {$telefone}");
        if (!$this->option('aplicar')) { $this->warn('Simulação: nada alterado.'); return self::SUCCESS; }
        $token->owner->forceFill(['phone' => $telefone])->save();
        $this->info('Telefone configurado.');
        return self::SUCCESS;
    }
}
