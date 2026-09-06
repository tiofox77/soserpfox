<?php

namespace App\Livewire\Invoicing\Reports\Concerns;

use App\Services\Invoicing\Relatorios\Catalogo;

/**
 * O ecrã Livewire de um relatório: as propriedades públicas são os filtros,
 * e os números vêm do `Catalogo` — o mesmo serviço que o ecrã em React usa.
 * O componente só precisa de dizer qual é o seu mapa (`RELATORIO`).
 */
trait UsaRelatorio
{
    /** Os filtros do ecrã: as propriedades públicas declaradas por este componente. */
    protected function filtrosDoRelatorio(): array
    {
        $f = [];

        foreach ((new \ReflectionClass($this))->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
            if (!$p->isStatic() && $p->getDeclaringClass()->getName() === static::class && $p->isInitialized($this)) {
                $f[$p->getName()] = $p->getValue($this);
            }
        }

        return $f;
    }

    /** Os dados do mapa, com as mesmas chaves que a vista espera. */
    protected function dadosDoRelatorio(array $extra = []): array
    {
        return Catalogo::abrir(static::RELATORIO)->dados((int) activeTenantId(), array_merge($this->filtrosDoRelatorio(), $extra));
    }
}
