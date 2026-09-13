import { describe, expect, it } from 'vitest';

import { CATALOGO_A_CADA, CATALOGO_AO_VOLTAR, deveSincronizarAoVoltar, deveSincronizarNoTemporizador, desdeAUltimaSync, PENDENTES_A_CADA } from './cadencia';
import { dataDeHoje, formasDeCodigo, motivoDoServidor, uuidV4 } from './util';
import { contasDoDocumento, fmt2, valorPorExtenso } from '../papel/molde';

/**
 * As regras do motor que não precisam de base nem de rede — as mesmas que os
 * ensaios node (`tests/pwa/*.test.mjs`) recortavam do JavaScript antigo por
 * expressão regular. Agora ensaiam-se as funções verdadeiras.
 */

const AGORA = 1770000000000;
const haMinutos = (m: number | null) => (m === null ? null : new Date(AGORA - m * 60000).toISOString());

describe('a cadência da sincronização automática', () => {
    it('sem nada por enviar, o catálogo é refrescado na mesma', () => {
        // Era isto que faltava: quem não vende nada nunca puxava nada.
        expect(deveSincronizarNoTemporizador({ syncing: false, pendingCount: 0, lastSync: haMinutos(10) }, AGORA)).toBe(true);
    });

    it('sem nada por enviar e com o catálogo fresco, não se incomoda o servidor', () => {
        expect(deveSincronizarNoTemporizador({ syncing: false, pendingCount: 0, lastSync: haMinutos(1) }, AGORA)).toBe(false);
    });

    it('com vendas por enviar sincroniza mesmo com o catálogo fresco', () => {
        expect(deveSincronizarNoTemporizador({ syncing: false, pendingCount: 3, lastSync: haMinutos(0) }, AGORA)).toBe(true);
    });

    it('a sincronizar, não se lança outra por cima', () => {
        expect(deveSincronizarNoTemporizador({ syncing: true, pendingCount: 5, lastSync: haMinutos(999) }, AGORA)).toBe(false);
    });

    it('nunca sincronizado, ou data ilegível, conta como catálogo velho', () => {
        expect(desdeAUltimaSync(null, AGORA)).toBe(Infinity);
        // Com NaN a comparação dava sempre falso e nunca mais se sincronizava.
        expect(desdeAUltimaSync('isto não é uma data', AGORA)).toBe(Infinity);
    });

    it('ao voltar à aplicação refresca o catálogo, e não só os pendentes', () => {
        expect(deveSincronizarAoVoltar(0, haMinutos(3), AGORA)).toBe(true);
        expect(deveSincronizarAoVoltar(0, haMinutos(1), AGORA)).toBe(false);
        expect(deveSincronizarAoVoltar(2, haMinutos(0), AGORA)).toBe(true);
    });

    it('as cadências são as pretendidas', () => {
        expect(PENDENTES_A_CADA).toBe(45 * 1000);
        expect(CATALOGO_A_CADA).toBe(5 * 60 * 1000);
        expect(CATALOGO_AO_VOLTAR).toBe(2 * 60 * 1000);
    });
});

describe('o código de barras', () => {
    it('o lido vem sempre primeiro', () => {
        expect(formasDeCodigo('8902292003269')[0]).toBe('8902292003269');
        expect(formasDeCodigo('0108902292003269')[0]).toBe('0108902292003269');
    });

    it('do envelope GS1 chega-se ao EAN-13, e do EAN-13 ao envelope', () => {
        expect(formasDeCodigo('0108902292003269')).toEqual(expect.arrayContaining(['08902292003269', '8902292003269']));
        expect(formasDeCodigo('8902292003269')).toEqual(expect.arrayContaining(['0108902292003269', '08902292003269']));
    });

    it('os glifos do leitor não contam, e a validade atrás não estraga', () => {
        expect(formasDeCodigo(')01=08902292003269')).toContain('8902292003269');
        expect(formasDeCodigo('0103400935955838172')).toContain('3400935955838');
    });

    it('um código interno fica como está, e nunca há repetidos nem vazios', () => {
        expect(formasDeCodigo('0000548')).toEqual(['0000548']);
        for (const lido of ['8902292003269', '0108902292003269', '', '   ', 'ABC']) {
            const f = formasDeCodigo(lido);
            expect(f).toEqual([...new Set(f)]);
            expect(f).not.toContain('');
        }
    });
});

describe('as utilidades', () => {
    it('a data de hoje é a do relógio de quem vende, não a UTC', () => {
        // 00:30 de dia 15 em Angola (UTC+1) ainda é dia 14 em UTC.
        const meiaNoiteEMeia = new Date(2026, 8, 15, 0, 30);
        expect(dataDeHoje(meiaNoiteEMeia)).toBe('2026-09-15');
    });

    it('o UUID tem a forma que a coluna char(36) exige', () => {
        expect(uuidV4()).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    });

    it('o motivo de uma recusa sai legível', () => {
        expect(motivoDoServidor('HTTP 422: {"error":"PIN óbvio"}')).toBe('PIN óbvio');
        expect(motivoDoServidor('HTTP 500 sem JSON')).toBeNull();
    });
});

describe('as contas do documento (ecrã, papel e lista batem com o servidor)', () => {
    const itens = [{ quantity: 3, unit_price: 1000, tax_rate: 14, discount_percent: 10 }];

    it('o desconto da linha entra no líquido', () => {
        const c = contasDoDocumento({ items: itens });
        expect(c.subtotal).toBeCloseTo(2700, 2);
        expect(c.total).toBeCloseTo(3078, 2);
    });

    it('o comercial sai antes do imposto e o financeiro depois', () => {
        const c = contasDoDocumento({ items: itens, discount_commercial: 700, discount_financial: 100 });
        // 2700 − 700 = 2000; IVA 14% de 2000 = 280; 2280 − 100 = 2180.
        expect(c.iva).toBeCloseTo(280, 2);
        expect(c.total).toBeCloseTo(2180, 2);
    });

    it('só há retenção em serviços, e sai do total e não do imposto', () => {
        const semServico = contasDoDocumento({ items: itens, withholding_percentage: 6.5 });
        expect(semServico.retencao).toBe(0);

        const servico = contasDoDocumento({ items: itens, is_service: true });
        expect(servico.retencao).toBeCloseTo(175.5, 2); // 6,5% de 2700
        expect(servico.iva).toBeCloseTo(378, 2);
        expect(servico.total).toBeCloseTo(2700 + 378 - 175.5, 2);
    });

    it('a retenção sai do total UMA vez: a pagar é o total, e o total do documento é antes de reter', () => {
        const c = contasDoDocumento({ items: itens, is_service: true });

        expect(c.aPagar).toBeCloseTo(c.total, 2);
        expect(c.totalDoDocumento).toBeCloseTo(c.total + c.retencao, 2);
        expect(c.aPagar).toBeCloseTo(2700 + 378 - 175.5, 2);
    });

    it('um total nunca fica negativo', () => {
        expect(contasDoDocumento({ items: itens, discount_financial: 999999 }).total).toBe(0);
    });

    it('o papel escreve como o servidor', () => {
        expect(fmt2(1234567.5)).toBe('1.234.567,50');
        expect(valorPorExtenso(1001.5)).toBe('Mil e um kwanzas e cinquenta cêntimos');
    });
});
