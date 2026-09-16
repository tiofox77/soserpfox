<?php

namespace App\Console\Commands\Revenda;

use App\Models\Reseller;
use App\Services\Revenda\AvisosDaRevenda;
use Illuminate\Console\Command;

/**
 * ENVIA A UM REVENDEDOR O EMAIL DOS DADOS DE ACESSO, a pedido do dono da
 * plataforma — o mesmo do botão «Enviar dados de acesso» na lista.
 *
 * Procura-o pelo código (não é dado pessoal, pode ir no endereço da rota de
 * manutenção). A senha nunca vai no email. A seco por omissão; --enviar envia.
 */
class DadosDeAcessoDoRevendedor extends Command
{
    protected $signature = 'revendedor:dados-de-acesso
        {codigo : o código do revendedor (ex.: MARIA4821)}
        {--enviar : envia de facto o email}';

    protected $description = 'Envia a um revendedor aprovado o email com os dados de acesso ao portal (a seco por omissão)';

    public function handle(AvisosDaRevenda $avisos): int
    {
        $codigo = mb_strtoupper(trim((string) $this->argument('codigo')));
        $r = Reseller::where('code', $codigo)->first();

        if (! $r) {
            $this->error("Nenhum revendedor com o código {$codigo}.");

            return self::FAILURE;
        }

        if (! $r->aprovado()) {
            $this->error("O revendedor #{$r->id} está {$r->status}: só se enviam os dados a um aprovado.");

            return self::FAILURE;
        }

        $this->line("Revendedor #{$r->id} — {$r->nomeVisivel()} <{$r->email}>");
        $this->line('Portal: ' . route('revendedor.login'));
        $this->line('Link: ' . $r->link());
        $this->line('Comissão: ' . $r->regra()->resumo());

        if (! $this->option('enviar')) {
            $this->comment('A SECO. Corra com --enviar para enviar o email.');

            return self::SUCCESS;
        }

        if (! $avisos->dadosDeAcesso($r)) {
            $this->error('O email não saiu (ver o registo). Confirme o SMTP da plataforma.');

            return self::FAILURE;
        }

        $this->info("Email dos dados de acesso enviado para {$r->email}.");

        return self::SUCCESS;
    }
}
