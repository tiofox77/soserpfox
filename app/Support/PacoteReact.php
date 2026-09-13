<?php

namespace App\Support;

/**
 * Onde está hoje o pacote dos ecrãs em React — o da aplicação web e o do PWA.
 *
 * O nome do ficheiro leva hash e muda a cada construção — e TEM de levar. Os
 * pedaços importam a entrada por caminho relativo; se o Blade a carregasse com
 * um `?v=` por cima de um nome fixo, o browser via dois módulos diferentes e
 * carregava o React duas vezes. O ecrã morria com «Cannot read properties of
 * null (reading 'useState')», que não diz nada a quem o apanha.
 *
 * O manifesto do Vite é a única fonte do nome. Lê-se uma vez por pedido.
 *
 * O DO PWA É OUTRO PACOTE (`vite.pwa.config.js`), num ficheiro só: é esse
 * ficheiro que o service worker pré-guarda e que entra na versão do PWA.
 */
class PacoteReact
{
    private const PACOTES = [
        'web' => ['manifesto' => 'react/.vite/manifest.json', 'entrada' => 'resources/js/react.tsx', 'pasta' => '/react/'],
        'pwa' => ['manifesto' => 'pwa-app/.vite/manifest.json', 'entrada' => 'resources/js/pwa.tsx', 'pasta' => '/pwa-app/'],
    ];

    /** @var array<string, string> */
    private static array $memoria = [];

    /**
     * O caminho público do pacote, ou null se ainda não foi construído.
     *
     * Null é um estado normal: uma instalação acabada de clonar não tem
     * `public/react`, e nenhuma página pode rebentar por causa disso — os
     * ecrãs em React simplesmente não montam.
     */
    public static function caminho(): ?string
    {
        return self::ler('web');
    }

    /** O pacote do PWA (`/pwa-app/pwa-<hash>.js`), ou null se não foi construído. */
    public static function doPwa(): ?string
    {
        return self::ler('pwa');
    }

    /** O ficheiro do pacote do PWA no disco — para a versão do PWA contar com ele. */
    public static function ficheiroDoPwa(): ?string
    {
        $caminho = self::doPwa();

        return $caminho ? public_path(ltrim($caminho, '/')) : null;
    }

    private static function ler(string $qual): ?string
    {
        if (array_key_exists($qual, self::$memoria)) {
            return self::$memoria[$qual] ?: null;
        }

        $def = self::PACOTES[$qual];
        $manifesto = public_path($def['manifesto']);

        if (! is_file($manifesto)) {
            self::$memoria[$qual] = '';

            return null;
        }

        $mapa = json_decode((string) file_get_contents($manifesto), true);
        $ficheiro = $mapa[$def['entrada']]['file'] ?? null;

        self::$memoria[$qual] = $ficheiro ? $def['pasta'] . $ficheiro : '';

        return self::$memoria[$qual] ?: null;
    }

    /** Esquece o que leu — para os ensaios, que constroem a meio. */
    public static function esquecer(): void
    {
        self::$memoria = [];
    }
}
