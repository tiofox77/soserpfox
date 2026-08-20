<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Pendura o CapturarErros no canal de log.
 *
 * Usa-se como `'tap' => [\App\Logging\LigarCapturaDeErros::class]` na
 * configuração do canal (config/logging.php). O canal continua a escrever no
 * ficheiro como sempre — isto é um ouvinte a mais, não um substituto.
 */
class LigarCapturaDeErros
{
    public function __invoke(Logger $logger): void
    {
        // Sem base de dados não há onde gravar, e a captura durante as
        // migrações ou uma instalação nova rebentaria em cada erro.
        if (!config('agent.erros.capturar', true)) {
            return;
        }

        $logger->getLogger()->pushHandler(new CapturarErros());
    }
}
