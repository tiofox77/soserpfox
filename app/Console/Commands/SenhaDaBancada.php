<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * A senha de quem trabalha NA BANCADA DE ENSAIO — e só dela.
 *
 * PORQUE RECEBE UM HASH E NÃO UMA SENHA. Em produção não há consola: os
 * comandos correm por um endereço HTTP e os argumentos viajam na query. Tudo o
 * que vai na query fica escrito nos registos de acesso do servidor, e uma
 * senha em texto num registo é uma senha perdida. Por isso o que viaja é o
 * hash bcrypt, que é exactamente o que a base já guarda: quem o ler nos
 * registos não fica a saber nada que a base não soubesse.
 *
 * PORQUE SÓ A BANCADA. Um comando que muda senhas em produção é uma porta.
 * Esta só abre para a empresa de ensaio — tentar apontá-la a um cliente real
 * é recusado, mesmo com o token de manutenção na mão. O `bancada:producao`
 * continua a ser o caminho normal, com senhas geradas que aparecem uma vez;
 * isto existe para quando se quer entrar com uma senha escolhida.
 *
 * SEM `--hash`, só relata quem lá está. É a forma de confirmar a bancada sem
 * lhe tocar.
 */
class SenhaDaBancada extends Command
{
    protected $signature = 'bancada:senha
                            {--email= : O utilizador da bancada}
                            {--hash= : O hash bcrypt da senha nova}';

    protected $description = 'Mostra ou define a senha de um utilizador da bancada de ensaio (recebe hash, nunca texto)';

    /** A mesma empresa que o `bancada:producao` monta. */
    private const SLUG = 'bancada-de-ensaio';

    public function handle(): int
    {
        $empresa = Tenant::where('slug', self::SLUG)->first();

        if (!$empresa) {
            $this->error('Não há bancada de ensaio nesta instalação. Monte-a com bancada:producao.');

            return self::FAILURE;
        }

        $daBancada = User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $empresa->id))
            ->orderBy('id')
            ->get();

        $email = $this->option('email');
        $hash = $this->option('hash');

        if (!$hash) {
            $this->line('<options=bold>BANCADA DE ENSAIO</> — ' . $empresa->name . ' (#' . $empresa->id . ')');
            $this->table(
                ['#', 'Nome', 'Email', 'Activo'],
                $daBancada->map(fn ($u) => [$u->id, $u->name, $u->email, $u->is_active ? 'sim' : 'não'])->all()
            );
            $this->line('Para definir: --email=<email> --hash=<bcrypt>');

            return self::SUCCESS;
        }

        if (!$email) {
            $this->error('Falta --email: o hash tem de ser para alguém.');

            return self::FAILURE;
        }

        // Um hash a sério, não uma senha em texto trocada às pressas.
        if (!preg_match('/^\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}$/', $hash)) {
            $this->error('O --hash não é um bcrypt válido. Gere-o com Hash::make e envie o hash, nunca a senha.');

            return self::FAILURE;
        }

        $utilizador = $daBancada->firstWhere('email', $email);

        if (!$utilizador) {
            // De propósito não se diz se a conta existe fora da bancada: este
            // comando não é um sítio para descobrir emails de clientes.
            $this->error("Não há ninguém com esse email NA BANCADA. Este comando não toca noutras empresas.");

            return self::FAILURE;
        }

        $utilizador->forceFill(['password' => $hash])->save();

        $this->info("Senha trocada para {$utilizador->name} <{$utilizador->email}> (#{$utilizador->id}).");
        $this->line('<fg=yellow>A senha em si nunca passou por aqui — só o seu hash.</>');

        return self::SUCCESS;
    }
}
