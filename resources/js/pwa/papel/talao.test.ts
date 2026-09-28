import { afterEach, describe, expect, it } from 'vitest';

import { comLargura, comLarguraDoAparelho, guardarLarguraDoAparelho, larguraEfectiva } from '@/ui/larguraDoTalao';

import { buildTicketHtml, cssDoTalao, PRINT_CSS } from './talao';

/**
 * O TALÃO DE 58 mm (28/09/2026) — as máquinas portáteis com impressora
 * embutida (Sunmi V2s…). O de 80 mm saía lá encolhido: aqui a folha tem 58 mm
 * e cada artigo vai em duas linhas, com o mesmo conteúdo fiscal.
 */
const venda = {
    provisional_number: 'PEND-1',
    created_at: '2026-09-28T10:00:00Z',
    total: 1140,
    items: [{ product_name: 'Água Mineral 1,5L', quantity: 2, unit_price: 500, tax_rate: 14 }],
};

afterEach(() => window.localStorage.clear());

describe('o talão do PWA na largura do rolo', () => {
    it('80 mm continua a ser o de sempre', () => {
        expect(PRINT_CSS).toBe(cssDoTalao(80));
        expect(PRINT_CSS).toContain('size: 80mm auto');

        const html = buildTicketHtml(venda, {});
        expect(html).toContain('>QTD<');
        expect(html).not.toContain('row-qty');
    });

    it('58 mm: folha de 58, sem sobras de 80, artigo em duas linhas', () => {
        const css = cssDoTalao(58);
        expect(css).toContain('size: 58mm auto');
        expect(css).not.toContain('80mm');

        const html = buildTicketHtml(venda, {}, 58);
        expect(html).not.toContain('>QTD<');
        expect(html).toContain('row-qty');
        expect(html).toContain('Água Mineral 1,5L');
        // O imposto da linha não se perde por o papel ser estreito.
        expect(html).toContain('IVA 14%');
    });
});

describe('a largura escolhida no aparelho', () => {
    it('sem escolha vale a da empresa, e sem empresa 80', () => {
        expect(larguraEfectiva()).toBe(80);
        expect(larguraEfectiva('58')).toBe(58);
        expect(larguraEfectiva(58)).toBe(58);
        expect(comLarguraDoAparelho('/t/1')).toBe('/t/1');
    });

    it('a do aparelho ganha à da empresa e vai no endereço', () => {
        guardarLarguraDoAparelho(58);

        expect(larguraEfectiva(80)).toBe(58);
        expect(comLarguraDoAparelho('/t/1')).toBe('/t/1?largura=58');
        expect(comLargura('/t/1?x=1', 80)).toBe('/t/1?x=1&largura=80');
    });
});
