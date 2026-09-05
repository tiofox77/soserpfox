<?php

namespace App\Support;

/**
 * Onde está hoje o pacote dos ecrãs em React.
 *
 * O nome do ficheiro leva hash e muda a cada construção — e TEM de levar. Os
 * pedaços importam a entrada por caminho relativo; se o Blade a carregasse com
 * um `?v=` por cima de um nome fixo, o browser via dois módulos diferentes e
 * carregava o React duas vezes. O ecrã morria com «Cannot read properties of
 * null (reading 'useState')», que não diz nada a quem o apanha.
 *
 * O manifesto do Vite é a única fonte do nome. Lê-se uma vez por pedido.
 */
class PacoteReact
{
    private const MANIFESTO = 'react/.vite/manifest.json';
    private const ENTRADA = 'resources/js/react.tsx';

    private static ?string $memoria = null;

    /**
     * O caminho público do pacote, ou null se ainda não foi construído.
     *
     * Null é um estado normal: uma instalação acabada de clonar não tem
     * `public/react`, e nenhuma página pode rebentar por causa disso — os
     * ecrãs em React simplesmente não montam.
     */
    public static function caminho(): ?string
    {
        if (self::$memoria !== null) {
            return self::$memoria ?: null;
        }

        $manifesto = public_path(self::MANIFESTO);

        if (! is_file($manifesto)) {
            self::$memoria = '';

            return null;
        }

        $mapa = json_decode((string) file_get_contents($manifesto), true);
        $ficheiro = $mapa[self::ENTRADA]['file'] ?? null;

        self::$memoria = $ficheiro ? '/react/' . $ficheiro : '';

        return self::$memoria ?: null;
    }

    /** Esquece o que leu — para os ensaios, que constroem a meio. */
    public static function esquecer(): void
    {
        self::$memoria = null;
    }
}
