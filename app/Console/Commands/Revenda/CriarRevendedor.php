<?php

namespace App\Console\Commands\Revenda;

use App\Models\Reseller;
use App\Services\Revenda\RegraDeComissao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * CRIAR (OU APROVAR) UM REVENDEDOR À MÃO, a pedido do dono da plataforma.
 *
 * Os dados vêm num ficheiro JSON em `storage/app/revenda/` — nunca nos
 * argumentos: as rotas de manutenção levam-nos no endereço, e nem a senha nem o
 * email de uma pessoa podem ficar nos registos do servidor. O ficheiro traz só
 * a impressão bcrypt da senha e é apagado assim que se aplica.
 *
 *   {"nome": "…", "email": "…", "senha_hash": "$2y$…", "telefone": "…"}
 *
 * Um revendedor que já exista com esse email fica com a senha nova e, se
 * ainda não estava aprovado, aprovado (com código e a comissão por omissão).
 * A seco por omissão; --aplicar grava. Não envia email nenhum.
 */
class CriarRevendedor extends Command
{
    protected $signature = 'revendedor:criar
        {--ficheiro= : nome do ficheiro JSON em storage/app/revenda}
        {--aplicar : grava de facto e apaga o ficheiro}';

    protected $description = 'Cria ou aprova um revendedor a partir de um ficheiro (a seco por omissão)';

    public function handle(): int
    {
        $caminho = storage_path('app/revenda/' . basename((string) $this->option('ficheiro')));

        if (! $this->option('ficheiro') || ! is_file($caminho)) {
            $this->error('Ficheiro não encontrado em storage/app/revenda.');

            return self::FAILURE;
        }

        $d = json_decode((string) file_get_contents($caminho), true);
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $nome = trim((string) ($d['nome'] ?? ''));
        $hash = (string) ($d['senha_hash'] ?? '');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($nome) < 3 || ! str_starts_with($hash, '$2y$') || strlen($hash) !== 60) {
            $this->error('O ficheiro tem de trazer nome, email e senha_hash (bcrypt).');

            return self::FAILURE;
        }

        $existente = Reseller::where('email', $email)->first();

        $this->line($existente
            ? "Já existe o revendedor #{$existente->id} ({$existente->status}): fica com a senha nova" . ($existente->aprovado() ? '.' : ' e aprovado.')
            : "Novo revendedor {$nome} <{$email}>, aprovado, com a comissão por omissão.");

        if (! $this->option('aplicar')) {
            $this->comment('A SECO. Corra com --aplicar para gravar.');

            return self::SUCCESS;
        }

        $r = DB::transaction(function () use ($existente, $email, $nome, $hash, $d) {
            $r = $existente ?? new Reseller();

            if (! $existente) {
                $r->fill(['name' => $nome, 'email' => $email, 'phone' => $d['telefone'] ?? null]);
                // Uma senha qualquer só para a criação: a verdadeira entra logo a seguir, já cifrada.
                $r->password = bin2hex(random_bytes(16));
            }

            if (! $r->aprovado()) {
                $r->forceFill([
                    'status' => 'aprovado',
                    'code' => $r->code ?: Reseller::novoCodigo($r->company_name ?: $r->name),
                    'commission' => $r->commission ?: RegraDeComissao::PADRAO,
                    'approved_at' => now(),
                    'rejection_reason' => null,
                    'suspended_at' => null,
                ]);
            }

            $r->save();

            // A impressão entra tal como veio: o cast `hashed` não a volta a cifrar.
            DB::table('resellers')->where('id', $r->id)->update(['password' => $hash, 'updated_at' => now()]);

            return $r->fresh();
        });

        @unlink($caminho);

        $this->info("Revendedor #{$r->id} pronto — código {$r->code}.");
        $this->line('Link: ' . $r->link());
        $this->line('Entrada: ' . route('revendedor.login'));
        $this->line('Comissão: ' . $r->regra()->resumo());
        $this->line('Ficheiro apagado.');

        return self::SUCCESS;
    }
}
