import { createContext, useContext } from 'react';

import type { PropsDoPwa } from './tipos';

/** As props da página, ao alcance de qualquer peça sem as passar à mão por dez níveis. */
export const ContextoDoPwa = createContext<PropsDoPwa | null>(null);

export function usePwa(): PropsDoPwa {
    const p = useContext(ContextoDoPwa);

    if (!p) throw new Error('usePwa fora do <ContextoDoPwa.Provider>');

    return p;
}

/** A que ecrã leva cada entrada do menu — é assim que o ecrã servido de recurso sabe se é deste utilizador. */
export const ENTRADA_DO_ECRA: Record<string, string> = {
    inicio: 'inicio',
    catalogo: 'catalogo',
    clientes: 'clientes',
    'novo-cliente': 'clientes',
    documentos: 'documentos',
    'novo-documento': 'documentos',
    pos: 'pos',
    restaurante: 'restaurante',
};
