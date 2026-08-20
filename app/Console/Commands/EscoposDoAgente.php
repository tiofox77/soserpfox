<?php

namespace App\Console\Commands;

use App\Models\AgentToken;
use Illuminate\Console\Command;

/**
 * Muda os escopos de uma credencial já emitida, sem lhe tocar no segredo.
 *
 * PORQUE É QUE ISTO EXISTE SEPARADO DO `agente:token`
 * ---------------------------------------------------
 * Para dar poderes novos a um agente havia só um caminho: emitir uma
 * credencial nova. Isso obriga a fazer chegar um segredo ao outro lado — e o
 * único canal remoto que esta casa tem para correr comandos é a rota de
 * manutenção, cujo token está em git e cuja resposta é texto simples. Um
 * segredo emitido por ali ficava no URL, na resposta, e provavelmente nos
 * registos de acesso do servidor.
 *
 * Alargar os escopos não mexe no segredo: nada de secreto atravessa a rede, e
 * é por isso que este comando pode estar na whitelist de manutenção e o
 * `agente:token` não pode.
 *
 * TIRAR poderes é igualmente importante e igualmente instantâneo: o middleware
 * lê os escopos da base a cada pedido, portanto uma retirada tem efeito na
 * chamada seguinte, sem deploy e sem revogar nada.
 */
class EscoposDoAgente extends Command
{
    protected $signature = 'agente:escopos
        {--prefixo= : prefixo da credencial (as 12 letras a seguir a oclaw_)}
        {--dar= : escopos a acrescentar, separados por vírgulas}
        {--tirar= : escopos a retirar, separados por vírgulas}
        {--todos : dá TODOS os escopos declarados em config/agent.php}';

    protected $description = 'Vê e muda os escopos de uma credencial do agente, sem tocar no segredo';

    public function handle(): int
    {
        $declarados = array_keys(config('agent.escopos', []));

        $tokens = AgentToken::query()
            ->when($this->option('prefixo'), fn ($q, $p) => $q->where('prefix', $p))
            ->orderBy('id')
            ->get();

        if ($tokens->isEmpty()) {
            $this->error('Nenhuma credencial encontrada.');

            return self::FAILURE;
        }

        // Sem --dar, --tirar nem --todos, isto é só uma consulta.
        if (!$this->option('dar') && !$this->option('tirar') && !$this->option('todos')) {
            $this->mostrar($tokens, $declarados);

            return self::SUCCESS;
        }

        if ($tokens->count() > 1) {
            $this->error('Há mais do que uma credencial. Indique --prefixo para dizer qual.');
            $this->mostrar($tokens, $declarados);

            return self::FAILURE;
        }

        $token = $tokens->first();
        $antes = $token->scopes ?? [];

        $novos = $this->option('todos')
            ? $declarados
            : array_values(array_unique(array_merge($antes, $this->lista('dar'))));

        $novos = array_values(array_diff($novos, $this->lista('tirar')));

        // Um escopo que não exista em config/agent.php nunca autoriza nada — o
        // middleware compara com a lista declarada. Gravá-lo dava a ilusão de
        // um poder que o agente não tem.
        $inventados = array_diff($novos, $declarados);

        if ($inventados) {
            $this->error('Escopos que não existem: ' . implode(', ', $inventados));

            return self::FAILURE;
        }

        sort($novos);
        $token->forceFill(['scopes' => $novos])->save();

        $this->info("Credencial '{$token->name}' ({$token->prefix}) actualizada.");

        foreach (array_diff($novos, $antes) as $s) {
            $this->line("  + {$s}");
        }

        foreach (array_diff($antes, $novos) as $s) {
            $this->line("  - {$s}");
        }

        $this->newLine();
        $this->line('Escopos agora: ' . implode(', ', $novos));

        // O segredo não muda: quem já o tem continua a usá-lo, com os poderes
        // novos já na chamada seguinte.
        $this->comment('O segredo NÃO mudou — não é preciso reconfigurar o agente.');

        return self::SUCCESS;
    }

    /** @return string[] */
    private function lista(string $opcao): array
    {
        $v = trim((string) $this->option($opcao));

        return $v === '' ? [] : array_map('trim', explode(',', $v));
    }

    private function mostrar($tokens, array $declarados): void
    {
        $this->table(
            ['prefixo', 'nome', 'escopos', 'expira', 'estado'],
            $tokens->map(fn (AgentToken $t) => [
                $t->prefix,
                $t->name,
                count($t->scopes ?? []) . '/' . count($declarados),
                $t->expires_at?->toDateString() ?? '—',
                $t->estaRevogado() ? 'revogada' : ($t->estaExpirado() ? 'expirada' : 'boa'),
            ])->all()
        );

        if ($tokens->count() === 1) {
            $t = $tokens->first();
            $tem = $t->scopes ?? [];

            $this->newLine();
            $this->line('Tem:      ' . (implode(', ', $tem) ?: '—'));
            $this->line('Não tem:  ' . (implode(', ', array_diff($declarados, $tem)) ?: '—'));
        }
    }
}
