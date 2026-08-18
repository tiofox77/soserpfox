<?php

namespace App\Console\Commands;

use App\Models\AgentToken;
use App\Models\User;
use App\Services\Agent\EmissaoDeTokens;
use Illuminate\Console\Command;

/**
 * Emitir, listar e revogar credenciais do agente, a partir da consola.
 *
 * O valor em claro aparece UMA vez. Não fica em ficheiro nenhum do
 * repositório e não há maneira de o recuperar depois.
 */
class AgenteToken extends Command
{
    protected $signature = 'agente:token
        {accao : emitir | listar | revogar}
        {--nome= : nome da credencial}
        {--responsavel= : email do humano que responde pelo agente}
        {--escopos= : lista separada por virgulas}
        {--ips= : lista de IPs separada por virgulas}
        {--dias=30 : validade}
        {--prefixo= : prefixo a revogar}
        {--motivo=revogado pela consola}';

    protected $description = 'Gerir credenciais da API do agente externo';

    public function handle(EmissaoDeTokens $emissao): int
    {
        return match ($this->argument('accao')) {
            'emitir'  => $this->emitir($emissao),
            'listar'  => $this->listar(),
            'revogar' => $this->revogar($emissao),
            default   => $this->erro('Acção desconhecida. Use emitir, listar ou revogar.'),
        };
    }

    private function emitir(EmissaoDeTokens $emissao): int
    {
        $responsavel = User::where('email', $this->option('responsavel'))->first();

        if (!$responsavel) {
            return $this->erro('Indique --responsavel com o email de um utilizador existente.');
        }

        $escopos = array_filter(array_map('trim', explode(',', (string) $this->option('escopos'))));
        $ips     = array_filter(array_map('trim', explode(',', (string) $this->option('ips'))));

        try {
            $r = $emissao->emitir(
                (string) $this->option('nome') ?: 'openclaw',
                $responsavel,
                $escopos,
                $ips,
                (int) $this->option('dias')
            );
        } catch (\InvalidArgumentException $e) {
            return $this->erro($e->getMessage());
        }

        $this->newLine();
        $this->info('Credencial emitida. Copie agora — não volta a ser mostrada:');
        $this->newLine();
        $this->line('  ' . $r['em_claro']);
        $this->newLine();
        $this->table(['campo', 'valor'], [
            ['nome', $r['token']->name],
            ['responsável', $responsavel->name],
            ['escopos', implode(', ', $r['token']->scopes)],
            ['IPs', implode(', ', $r['token']->allowed_ips ?: ['(sem restrição)'])],
            ['expira', $r['token']->expires_at->toDateTimeString()],
        ]);

        return self::SUCCESS;
    }

    private function listar(): int
    {
        $linhas = AgentToken::with('owner')->get()->map(fn ($t) => [
            $t->prefix,
            $t->name,
            $t->owner?->name,
            implode(',', $t->scopes ?? []),
            $t->expires_at?->toDateString(),
            $t->revoked_at ? 'REVOGADO' : ($t->estaExpirado() ? 'expirado' : 'activo'),
            $t->last_used_at?->diffForHumans() ?? 'nunca',
        ]);

        $this->table(
            ['prefixo', 'nome', 'responsável', 'escopos', 'expira', 'estado', 'último uso'],
            $linhas
        );

        return self::SUCCESS;
    }

    private function revogar(EmissaoDeTokens $emissao): int
    {
        $token = AgentToken::where('prefix', $this->option('prefixo'))->first();

        if (!$token) {
            return $this->erro('Prefixo não encontrado.');
        }

        $emissao->revogar($token, null, (string) $this->option('motivo'));
        $this->info("Credencial {$token->prefix} revogada. Efeito imediato.");

        return self::SUCCESS;
    }

    private function erro(string $mensagem): int
    {
        $this->error($mensagem);

        return self::FAILURE;
    }
}
