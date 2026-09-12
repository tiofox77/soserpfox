import { afterEach, describe, expect, it } from 'vitest';

import { etiquetaIntl } from '@/i18n';
import { data, kz } from './tokens';

/**
 * O NÚMERO E A DATA SEGUEM A LÍNGUA.
 *
 * O dicionário trata das frases; os números não passam por ele — saem do
 * `Intl` do navegador, e o `Intl` precisa de uma etiqueta BCP-47. Estava
 * 'pt-PT' escrito à mão nos dois formatadores, e o resultado era um ecrã
 * traduzido com «15 234,50» por baixo a quem esperava «15,234.50»: a
 * meia-tradução que passa despercebida a quem revê o texto e salta à vista a
 * quem usa.
 *
 * O QUE ESTES ENSAIOS GUARDAM ACIMA DE TUDO é a outra metade: **em português
 * nada mudou**. Quase todas as empresas trabalham em português, e os ensaios
 * de browser comparam textos com vírgula decimal — um desvio aqui partia-os
 * todos de uma vez.
 */

function trabalhaEm(lingua: string | undefined): void {
    window.__reactLingua = lingua;
}

afterEach(() => {
    delete window.__reactLingua;
});

/**
 * O SEPARADOR DE MILHARES NÃO SE ESCREVE À MÃO EM LADO NENHUM.
 *
 * Qual é o carácter que separa os milhares é decisão do CLDR e já mudou de
 * versão para versão — em pt-PT o Node de hoje dá um espaço sem quebra onde
 * antes dava um ponto. Fixá-lo aqui era pôr o ensaio a partir-se numa
 * actualização do Node sem nada de errado ter mudado. O que importa, e o que
 * se compara, é o DECIMAL: vírgula em português e em francês, ponto em inglês.
 */
function espacosNormais(texto: string): string {
    return texto.replace(/[  ]/g, ' ');
}

describe('etiquetaIntl()', () => {
    it('traduz a língua para a etiqueta que o Intl entende', () => {
        trabalhaEm('pt');
        expect(etiquetaIntl()).toBe('pt-PT');

        trabalhaEm('en');
        expect(etiquetaIntl()).toBe('en-GB');

        trabalhaEm('fr');
        expect(etiquetaIntl()).toBe('fr-FR');
    });

    it('sem escolha, e perante uma língua que não conhece, fica em português', () => {
        expect(etiquetaIntl()).toBe('pt-PT');

        trabalhaEm('de');
        expect(etiquetaIntl()).toBe('pt-PT');
    });
});

describe('kz()', () => {
    it('em português continua como estava: decimal por vírgula', () => {
        expect(espacosNormais(kz(15234.5))).toMatch(/^15[. ]234,50$/);
        expect(espacosNormais(kz('15000'))).toMatch(/^15[. ]000,00$/);
        expect(espacosNormais(kz(1234567.891, 0))).toMatch(/^1[. ]234[. ]568$/);
        expect(kz(0)).toBe('0,00');

        // Quatro algarismos não levam separador nenhum em pt-PT: o CLDR só
        // agrupa a partir de cinco. Fica escrito para ninguém o «corrigir».
        expect(kz(1234.5)).toBe('1234,50');
    });

    it('sem língua escolhida é português — é o caso de quase toda a gente', () => {
        trabalhaEm(undefined);
        expect(espacosNormais(kz(15234.5))).toMatch(/^15[. ]234,50$/);
    });

    it('em inglês o decimal é ponto e o milhar é vírgula', () => {
        trabalhaEm('en');
        expect(kz(15234.5)).toBe('15,234.50');

        // E quatro algarismos JÁ levam separador, ao contrário do português:
        // é exactamente esta diferença que o 'pt-PT' fixo escondia.
        expect(kz(1234.5)).toBe('1,234.50');
    });

    it('em francês separa os milhares por espaço', () => {
        trabalhaEm('fr');
        expect(espacosNormais(kz(15234.5))).toBe('15 234,50');
    });

    it('o que não é número nenhum sai a zero, e não «NaN»', () => {
        expect(kz('isto não é um número')).toBe('0,00');
        expect(kz(null)).toBe('0,00');
    });
});

describe('data()', () => {
    /*
     * A HORA VAI ESCRITA de propósito. `new Date('2026-09-04')` é meia-noite
     * em UTC, e a Ocidente do meridiano lê-se dia 3 — o ensaio passava ou
     * falhava conforme a máquina onde corresse.
     */
    it('em português lê-se 04/09/2026', () => {
        expect(data('2026-09-04T10:00:00')).toBe('04/09/2026');
    });

    it('nas três línguas o dia vem primeiro — mas quem o decide é o Intl', () => {
        for (const lingua of ['pt', 'en', 'fr']) {
            trabalhaEm(lingua);
            expect(data('2026-09-04T10:00:00')).toBe('04/09/2026');
        }
    });

    it('sem data, um travessão', () => {
        expect(data(null)).toBe('—');
        expect(data('não é data nenhuma')).toBe('—');
    });
});

describe('haQuanto', () => {
    const agora = new Date('2026-09-12T12:00:00Z');

    it('diz a distância na unidade maior que cabe', async () => {
        const { haQuanto } = await import('./tokens');

        expect(haQuanto('2026-09-09T12:00:00Z', agora)).toBe('há 3 dias');
        expect(haQuanto('2026-09-12T10:00:00Z', agora)).toBe('há 2 horas');
    });

    it('sem data não inventa uma', async () => {
        const { haQuanto } = await import('./tokens');

        expect(haQuanto(null, agora)).toBe('—');
        expect(haQuanto('isto não é data', agora)).toBe('—');
    });
});
