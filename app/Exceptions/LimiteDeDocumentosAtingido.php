<?php

namespace App\Exceptions;

/**
 * A empresa gastou os documentos que o plano lhe dá.
 *
 * Tem tipo próprio para que quem apanha o erro saiba que não é uma avaria:
 * é uma regra comercial, e a resposta certa é mudar de plano, não tentar
 * outra vez.
 */
class LimiteDeDocumentosAtingido extends \RuntimeException
{
    public function __construct(
        public readonly int $limite,
        public readonly int $emitidos,
    ) {
        parent::__construct(__(
            'Este plano permite emitir :limite documentos e já foram emitidos :emitidos. Mude de plano para continuar a facturar.',
            ['limite' => $limite, 'emitidos' => $emitidos]
        ));
    }
}
