<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Corrige nome, email e palavra-passe de um utilizador de uma empresa.
 *
 * A PALAVRA-PASSE VEM DE UM FICHEIRO, nunca da linha de comandos. Estes
 * comandos também se correm por HTTP, e um argumento de URL fica escrito nos
 * registos do servidor — a palavra-passe ficava lá, legível, para sempre.
 *
 * SIMULAÇÃO POR OMISSÃO.
 *
 *   php artisan utilizadores:actualizar --tenant=57 --de=antigo@x --email=novo@x --aplicar
 */
class ActualizarUtilizador extends Command
{
    protected $signature = 'utilizadores:actualizar
                            {--tenant= : id da empresa}
                            {--de= : email actual do utilizador}
                            {--email= : email novo}
                            {--nome= : nome novo}
                            {--nome-ficheiro= : ficheiro com o nome novo (1ª linha) — nomes têm espaços}
                            {--senha-ficheiro= : ficheiro com a palavra-passe nova (1ª linha)}
                            {--aplicar : grava (sem isto é simulação)}';

    protected $description = 'Corrige nome, email e palavra-passe de um utilizador (simulação por omissão)';

    public function handle(): int
    {
        $empresa = Tenant::find($this->option('tenant'));

        if (!$empresa) {
            $this->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        $user = User::where('tenant_id', $empresa->id)
            ->where('email', trim((string) $this->option('de')))
            ->first();

        if (!$user) {
            $this->error('Utilizador não encontrado nesta empresa: ' . $this->option('de'));

            return self::FAILURE;
        }

        $email = trim((string) $this->option('email')) ?: $user->email;
        // Por ficheiro quando o nome tem espaços: a rota de manutenção parte
        // os argumentos nos espaços e "Carlos Borges" chegava aqui como
        // "Carlos".
        $nome = trim((string) $this->option('nome')) ?: $user->name;

        if ($nf = $this->option('nome-ficheiro')) {
            if (!is_file($nf) && is_file(base_path($nf))) {
                $nf = base_path($nf);
            }

            if (is_file($nf)) {
                $nome = trim(strtok((string) file_get_contents($nf), "
")) ?: $nome;
            }
        }
        $senha = null;

        if ($f = $this->option('senha-ficheiro')) {
            if (!is_file($f) && is_file(base_path($f))) {
                $f = base_path($f);
            }

            if (!is_file($f)) {
                $this->error('Ficheiro da palavra-passe não encontrado.');

                return self::FAILURE;
            }

            $senha = trim(strtok((string) file_get_contents($f), "\r\n"));

            if (mb_strlen($senha) < 8) {
                $this->error('A palavra-passe tem de ter pelo menos 8 caracteres.');

                return self::FAILURE;
            }
        }

        if ($email !== $user->email && User::where('email', $email)->exists()) {
            $this->error('Já existe outro utilizador com o email ' . $email);

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line('  nome:  ' . $user->name . ($nome !== $user->name ? "  →  {$nome}" : '  (sem mudança)'));
        $this->line('  email: ' . $user->email . ($email !== $user->email ? "  →  {$email}" : '  (sem mudança)'));
        $this->line('  palavra-passe: ' . ($senha ? 'vai ser mudada' : 'sem mudança'));

        // Os papéis são por empresa no Spatie: sem dizer qual, vinha vazio e
        // parecia que a pessoa não tinha acesso a nada.
        setPermissionsTeamId($empresa->id);
        $papeis = $user->roles()->pluck('name')->implode(', ');
        $this->line('  papéis: ' . ($papeis ?: '(nenhum)'));

        if (!$this->option('aplicar')) {
            $this->newLine();
            $this->warn('  Nada foi gravado. Repita com --aplicar.');

            return self::SUCCESS;
        }

        $user->name = $nome;
        $user->email = $email;

        if ($senha) {
            $user->password = Hash::make($senha);
        }

        $user->save();

        $this->newLine();
        $this->info('  ✓ Actualizado. Entra com: ' . $user->email);

        return self::SUCCESS;
    }
}
