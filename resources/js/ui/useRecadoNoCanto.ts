import { useCallback, useState, type SetStateAction } from 'react';

import { avisar } from '@/casca/avisos';
import { t } from '@/i18n';

/**
 * O RECADO DE UM ECRÃ SAI NO CANTO SUPERIOR DIREITO.
 *
 * Os ecrãs guardavam o recado de sucesso («Cliente criado.», «Artigo
 * guardado») num estado seu e desenhavam-no numa caixa verde no topo da
 * página — fora da vista de quem estava no fundo de um formulário comprido, e
 * cada ecrã com a sua caixa. Todo o CRUD avisa agora no canto (ver
 * casca/avisosDasMutacoes.ts): este gancho substitui o `useState` do recado,
 * com a mesma forma — `const [recado, porRecado] = useRecadoNoCanto('')` —, e
 * manda o texto para o canto em vez de o guardar. O `recado` fica sempre
 * vazio, por isso a caixa antiga não chega a desenhar-se e o aviso não sai
 * duas vezes.
 *
 * Um recado comprido fica mais tempo: ninguém lê trinta palavras em três
 * segundos.
 */
export function useRecadoNoCanto(inicial: string): [string, (valor: SetStateAction<string>) => void];
export function useRecadoNoCanto(inicial: string | null): [string | null, (valor: SetStateAction<string | null>) => void];
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function useRecadoNoCanto(inicial: string | null): [string | null, (valor: any) => void] {
    const [vazio] = useState<string | null>(inicial);

    const porRecado = useCallback((valor: SetStateAction<string | null>) => {
        const texto = typeof valor === 'function' ? valor(vazio) : valor;

        if (typeof texto === 'string' && texto.trim() !== '') {
            avisar(t(texto), 'ok', { duracao: duracaoPara(texto) });
        }
    }, [vazio]);

    return [vazio, porRecado];
}

export function duracaoPara(texto: string): number {
    return Math.min(10_000, Math.max(3_500, 1_500 + texto.length * 45));
}

/** O recado com tom (`{ texto, mau }` ou `{ tipo: 'bom' | 'mau', texto }`): o mau sai a vermelho. */
export type RecadoComTom = { texto: string; mau?: boolean; tipo?: string; aviso?: boolean };

export function useRecadoComTomNoCanto<T extends RecadoComTom>(): [T | null, (valor: SetStateAction<T | null>) => void] {
    const porRecado = useCallback((valor: SetStateAction<T | null>) => {
        const r = typeof valor === 'function' ? valor(null) : valor;

        if (!r || !r.texto || r.texto.trim() === '') return;

        const tom = r.mau || r.tipo === 'mau' || r.tipo === 'erro' ? 'erro' : r.aviso ? 'aviso' : 'ok';
        avisar(t(r.texto), tom, { duracao: duracaoPara(r.texto) });
    }, []);

    return [null, porRecado];
}
