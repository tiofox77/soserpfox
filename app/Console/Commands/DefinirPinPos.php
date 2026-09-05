<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Repor o PIN de turno de um funcionário — o mesmo que o modal «PIN de turno»
 * da Gestão de Utilizadores faz, mas por linha de comando.
 *
 * Existe porque nem sempre há uma sessão de admin aberta no tenant certo: para
 * o primeiro provisionamento, ou para quem se esqueceu do PIN, isto resolve
 * sem login. Guarda-se sempre só o bcrypt (via User::definirPinPos); o PIN em
 * claro nunca fica em lado nenhum — e sobe ao tablet na próxima sincronização.
 *
 * SEGURO POR OMISSÃO:
 *   · o PIN é GERADO no servidor quando não se passa --pin, para o número não
 *     viajar num URL nem ficar no histórico de quem corre isto;
 *   · recusa os PIN óbvios (a mesma lista da UI), a menos que --forcar — porque
 *     este PIN autoriza vendas de medicamentos controlados;
 *   · aborta se o utilizador não pertencer ao tenant indicado.
 */
class DefinirPinPos extends Command
{
    protected $signature = 'pwa:definir-pin
        {--tenant= : id ou fragmento do nome do tenant (sem espacos)}
        {--email= : email do funcionario}
        {--pin= : PIN de 4 a 6 digitos; se omitido, e gerado no servidor}
        {--forcar : permite um PIN da lista dos obvios (desaconselhado)}
        {--aplicar : grava de facto (sem isto, so mostra)}';

    protected $description = 'Define/repoe o PIN de turno (login offline do POS) de um funcionario';

    // Os PIN óbvios vivem em App\Support\PinDeTurno — uma lista só, a mesma
    // dos ecrãs e a que o aparelho recebe. Esta tinha o 4321 e a dos ecrãs não.

    public function handle(): int
    {
        $alvoTenant = trim((string) $this->option('tenant'));
        $email = mb_strtolower(trim((string) $this->option('email')));
        $pinDado = trim((string) $this->option('pin'));

        if ($alvoTenant === '' || $email === '') {
            $this->error('Faltam --tenant ou --email.');

            return self::FAILURE;
        }

        // Resolver o tenant (id ou nome-like) — tem de ser ÚNICO.
        $q = ctype_digit($alvoTenant)
            ? Tenant::where('id', (int) $alvoTenant)
            : Tenant::where('name', 'like', '%'.$alvoTenant.'%');
        $tenants = $q->get(['id', 'name', 'is_active']);

        if ($tenants->count() !== 1) {
            $this->error("Tenant nao unico para '{$alvoTenant}' ({$tenants->count()} encontrados):");
            foreach ($tenants as $t) {
                $this->line("  #{$t->id}  {$t->name}");
            }

            return self::FAILURE;
        }
        $tenant = $tenants->first();

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("Funcionario {$email} nao encontrado.");

            return self::FAILURE;
        }

        $membro = $tenant->users()->where('users.id', $user->id)->exists();

        $this->line("Tenant:      #{$tenant->id}  {$tenant->name}".($tenant->is_active ? '' : '  (SUSPENSO)'));
        $this->line("Funcionario: #{$user->id}  {$user->name}  <{$email}>");
        $this->line('Membro do tenant: '.($membro ? 'sim' : 'NAO'));
        $this->line('Ja tinha PIN: '.($user->temPinPos() ? 'sim (vai ser substituido)' : 'nao'));

        if (! $membro) {
            $this->error('O funcionario nao pertence a este tenant — abortado por seguranca.');

            return self::FAILURE;
        }

        // Determinar o PIN: dado (validado) ou gerado.
        $gerado = false;
        if ($pinDado !== '') {
            if (! preg_match('/^\d{4,6}$/', $pinDado)) {
                $this->error('O PIN tem de ter 4 a 6 digitos.');

                return self::FAILURE;
            }
            if (\App\Support\PinDeTurno::ehObvio($pinDado) && ! $this->option('forcar')) {
                $this->error("O PIN '{$pinDado}' e demasiado obvio. Escolha outro, ou use --forcar se souber o que faz.");

                return self::FAILURE;
            }
            $pin = $pinDado;
        } else {
            $pin = $this->gerarPin();
            $gerado = true;
        }

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->line('Vai definir o PIN: '.($gerado ? '(gerado no servidor, mostrado ao aplicar)' : str_repeat('•', strlen($pin))));
            $this->comment('CONSULTA apenas. Corra com --aplicar para gravar.');

            return self::SUCCESS;
        }

        $user->definirPinPos($pin);

        $this->newLine();
        $this->info("PIN de {$user->name} @ {$tenant->name} definido.");
        $this->line('PIN: '.$pin.($gerado ? '   (gerado — anote e entregue ao funcionario)' : ''));
        $this->comment('Sobe ao tablet na PROXIMA sincronizacao com internet. Trate-o como credencial: aparece aqui uma vez.');

        return self::SUCCESS;
    }

    /** Um PIN de 4 dígitos que não é óbvio nem uma sequência trivial. */
    private function gerarPin(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (\App\Support\PinDeTurno::ehObvio($pin));

        return $pin;
    }
}
