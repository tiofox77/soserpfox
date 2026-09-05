<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Auto-cura do OPcache: quando um método "desaparece" depois de um deploy.
 *
 * O PROBLEMA. O deploy é ficheiro-a-ficheiro por FTP. Um ficheiro pode chegar
 * com o conteúdo CERTO (MD5 a bater com o local) e mesmo assim o php-fpm correr
 * uma compilação ANTIGA: o OPcache guardou a versão anterior e, se o mtime não
 * subiu ou o `revalidate_freq` é longo, não repara na troca. O sintoma é cruel
 * — um método novo (ex.: `create()` num componente Livewire) aparece como
 * «Public method [create] not found on component», o `view:cache` não resolve
 * (é só Blade), e o ficheiro parece igual em todo o lado. Aconteceu no ecrã de
 * Funcionários: o modal não abria e nada no MD5 ou nos logs o denunciava.
 *
 * A CURA. Repõe-se o OPcache — no MÁXIMO uma vez por janela curta, para um
 * método genuinamente inexistente não desatar a repor a cada pedido — e devolve
 * QUEM chama se realmente repôs desta vez. Se era staleness, o próximo pedido
 * recompila do disco e o método aparece; se era um bug a sério, a janela impede
 * a tempestade de resets e o erro volta a ser visível (e bem).
 *
 * Isto é a rede de segurança. A prevenção é correr `deploy:opcache-reset` no
 * fecho de cada deploy de classes PHP (ver [[deployment]]).
 */
class AutoCuraOpcache
{
    /** Só se repõe uma vez a cada tantos segundos (evita tempestade de resets). */
    private const JANELA_SEGUNDOS = 60;

    /**
     * Repõe o OPcache se ainda não o repôs há pouco.
     *
     * @return bool true se REALMENTE repôs agora (o chamador só deve mandar
     *              recarregar nesse caso — senão é bug genuíno e o 500 fica).
     */
    public static function talvezRepor(): bool
    {
        try {
            if (! function_exists('opcache_reset')) {
                return false;
            }

            // Tranca ATÓMICA na cache: `add` só grava (e devolve true) se a
            // chave ainda não existir. Assim, dentro da janela, apenas o
            // PRIMEIRO pedido repõe — os outros ficam quietos e o erro volta a
            // ser visível (é o que impede o ciclo de recargas num bug genuíno).
            // A cache guarda DADOS, não código: não é afectada pelo OPcache.
            if (! Cache::add('opcache-auto-reset', 1, self::JANELA_SEGUNDOS)) {
                return false; // reposto há menos de uma janela
            }

            return (bool) @opcache_reset();
        } catch (\Throwable $e) {
            // A auto-cura NUNCA pode piorar o pedido que já está a falhar.
            return false;
        }
    }
}
