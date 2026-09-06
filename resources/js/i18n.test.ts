import { describe, expect, it } from 'vitest';

import { t, tPartes } from './i18n';

/**
 * O `t()` SEM DICIONÁRIO DEVOLVE A FRASE.
 *
 * É a propriedade que permitiu embrulhar 37 ecrãs sem risco: em português —
 * que é a língua de quase todas as empresas — não há dicionário nenhum, e cada
 * `t('…')` devolve exactamente o que lá estava escrito. Se isto se partir, a
 * facturação inteira fica cheia de chaves em vez de texto.
 */
describe('t()', () => {
    it('devolve a própria frase quando não há tradução', () => {
        expect(t('Emitir factura')).toBe('Emitir factura');
    });

    it('substitui os :atributos como o Laravel', () => {
        expect(t('Faltam :quanto Kz', { quanto: '1.500,00' })).toBe('Faltam 1.500,00 Kz');
        expect(t('De :a para :b', { a: 'Loja', b: 'Armazém' })).toBe('De Loja para Armazém');
    });

    it('aceita números e repete o mesmo atributo', () => {
        expect(t(':n de :n', { n: 3 })).toBe('3 de 3');
    });

    it('deixa em paz um atributo que ninguém preencheu', () => {
        expect(t('Sobra :isto')).toBe('Sobra :isto');
    });

    /**
     * O MAIS COMPRIDO PRIMEIRO.
     *
     * Pela ordem de escrita, o `:pagina` comia o princípio do `:paginas` e
     * sobrava um «s» perdido: «Página 3 de 7s».
     */
    it('não deixa um atributo comer o princípio de outro', () => {
        expect(t('Página :pagina de :paginas', { pagina: 3, paginas: 7 })).toBe('Página 3 de 7');
        expect(t('Página :pagina de :paginas', { paginas: 7, pagina: 3 })).toBe('Página 3 de 7');
    });
});

describe('tPartes()', () => {
    it('parte a frase à volta do atributo, para o valor poder vir em negrito', () => {
        const pedacos = tPartes('Vai apagar o lote :lote. Não há volta.', { lote: 'L-2026-04' });

        expect(pedacos).toHaveLength(3);
        expect(pedacos[0]).toBe('Vai apagar o lote ');
        expect(pedacos[2]).toBe('. Não há volta.');
    });

    it('não deixa pedaços vazios quando o atributo está no fim', () => {
        expect(tPartes('Total: :valor', { valor: 'x' })).toHaveLength(2);
    });
});
