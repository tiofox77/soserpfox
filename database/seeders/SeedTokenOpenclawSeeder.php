<?php

namespace Database\Seeders;

use App\Models\AgentToken;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Insere a credencial do agente openclaw em producao.
 *
 * O segredo foi gerado FORA do servidor: aqui so entra o sha256. O
 * servidor nunca ve, nunca regista e nunca transmite o valor em claro.
 *
 * Idempotente pelo prefixo: correr outra vez nao cria uma segunda linha.
 * Depois de usado, este ficheiro pode ser apagado sem qualquer efeito.
 */
class SeedTokenOpenclawSeeder extends Seeder
{
    public function run(): void
    {
        $prefixo = 'e85dd5d4e5bb';
        $hash    = 'c8bcccae7c564c7d0d6b9a7e641e8348548b38344db2302e6660296e746c0cea';

        if (AgentToken::where('prefix', $prefixo)->exists()) {
            $this->command?->warn("Token {$prefixo} ja existe — nada a fazer.");
            return;
        }

        // O responsavel resolve-se por EMAIL: o id do utilizador em producao
        // nao tem de coincidir com o do ambiente onde o token foi gerado.
        $responsavel = User::where('email', 'foxgamer77s@gmail.com')->first();

        if (!$responsavel) {
            $this->command?->error('Responsavel foxgamer77s@gmail.com nao encontrado. Token NAO criado.');
            return;
        }

        AgentToken::create([
            'name'          => 'openclaw',
            'prefix'        => $prefixo,
            'token_hash'    => $hash,
            'owner_user_id' => $responsavel->id,
            'scopes'        => ['tenants:read', 'health:read', 'orders:read', 'orders:note', 'logs:read', 'contacts:read'],
            'allowed_ips'   => ['154.71.160.227'],
            'expires_at'    => now()->addDays(30),
            'created_by'    => $responsavel->id,
        ]);

        $this->command?->info("Token openclaw ({$prefixo}) criado. Responsavel: {$responsavel->email}. "
            . 'Escopos: leitura + nota. IP: 154.71.160.227. Validade: 30 dias.');
    }
}
