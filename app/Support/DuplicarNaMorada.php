<?php

namespace App\Support;

/**
 * O DOCUMENTO A DUPLICAR, QUE VEM NO ENDEREÇO.
 *
 * Da lista carrega-se em «Duplicar» e chega-se ao ecrã de emissão com
 * `?duplicar=123` — a mesma morada que o ecrã em Livewire usava, de propósito:
 * a rota de criação já existe e é a mesma; o que muda é de onde vêm os valores
 * iniciais, não o ecrã.
 *
 * Aqui só se lê o número. QUEM DECIDE SE ESSE DOCUMENTO EXISTE, se é desta
 * empresa e se esta pessoa o pode ver é a API (`/duplicar`), com o escopo da
 * empresa e o do autor aplicados — um id de outra empresa no endereço abre um
 * formulário vazio e mais nada.
 */
final class DuplicarNaMorada
{
    /**
     * @return array{duplicarDe?: int}
     */
    public static function props(): array
    {
        $id = request()->query('duplicar');

        if ((! is_string($id) && ! is_int($id)) || ! ctype_digit((string) $id)) {
            return [];
        }

        return ['duplicarDe' => (int) $id];
    }
}
