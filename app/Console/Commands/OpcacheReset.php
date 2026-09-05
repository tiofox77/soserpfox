<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Repõe o OPcache do PHP (o do lado web) para os ficheiros acabados de enviar
 * serem recompilados a partir do disco.
 *
 * PORQUE EXISTE. Um ficheiro pode chegar por FTP com o conteúdo CERTO (MD5 a
 * bater com o local) e mesmo assim o servidor correr uma versão ANTIGA: o
 * OPcache guardou a compilação anterior e, se o mtime não subiu ou o
 * `revalidate_freq` ainda não passou, não repara na troca. O sintoma é cruel —
 * um método novo (ex.: `create()` num componente Livewire) aparece como
 * «Public method [create] not found», o `view:cache` não resolve (é só Blade),
 * e o ficheiro parece igual em todo o lado. Aconteceu no ecrã de Funcionários.
 *
 * Corre-se por HTTP (rota de manutenção) DE PROPÓSITO: o OPcache do php-fpm
 * (web) e o do CLI são segmentos separados. Um `opcache_reset()` chamado por
 * `php artisan` na consola repõe o do CLI e deixa o da web intacto — inútil.
 * A rota de manutenção corre dentro do php-fpm, por isso repõe o que serve o
 * site. Faz parte do fecho de qualquer deploy que mude classes PHP.
 */
class OpcacheReset extends Command
{
    protected $signature = 'deploy:opcache-reset';

    protected $description = 'Repõe o OPcache do PHP (web) para o código acabado de enviar entrar em vigor';

    public function handle(): int
    {
        if (! function_exists('opcache_reset')) {
            $this->error('opcache_reset() indisponível — o OPcache está desligado neste servidor.');

            return self::FAILURE;
        }

        $estadoAntes = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
        $scriptsAntes = $estadoAntes['opcache_statistics']['num_cached_scripts'] ?? null;

        $ok = opcache_reset();

        $this->info($ok
            ? 'OPcache reposto — o próximo pedido recompila tudo do disco.'
            : 'opcache_reset() devolveu false (já estava vazio, ou sem permissão para o repor).');

        if ($scriptsAntes !== null) {
            $this->line("Scripts em cache antes de repor: {$scriptsAntes}");
        }

        return self::SUCCESS;
    }
}
