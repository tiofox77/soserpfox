/**
 * A cadência da sincronização automática, contra o pwa-invoicing.js REAL.
 *
 * O que motivou isto: os dois gatilhos automáticos só corriam quando havia
 * coisas POR ENVIAR. Um posto aberto o dia inteiro sem uma única venda ficava
 * com o catálogo do dia anterior — e quando o servidor caísse, faltava
 * exactamente o que tinha mudado entretanto.
 *
 *   node --test tests/pwa/auto-sync.test.mjs
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const fonte = readFileSync(join(raiz, 'public', 'js', 'pwa-invoicing.js'), 'utf8');

/**
 * A decisão do temporizador, recortada do ficheiro real.
 *
 * Não é uma cópia escrita à mão: lê-se o bloco do próprio ficheiro e avalia-se.
 * Se alguém mudar a regra lá, muda aqui — que é o ponto.
 */
function decisor() {
    const bloco = fonte.match(/const PENDENTES_A_CADA[\s\S]*?function desdeAUltimaSync\(\) \{[\s\S]*?\n        \}/);
    assert.ok(bloco, 'não encontrei o bloco da cadência no pwa-invoicing.js');

    const corpo = bloco[0]
        .replace(/^\s*const /gm, 'var ')
        .replace(/^\s*function /gm, 'function ');

    return new Function('state', 'agora', `
        ${corpo}
        Date.now = () => agora;
        return {
            PENDENTES_A_CADA: PENDENTES_A_CADA,
            CATALOGO_A_CADA: CATALOGO_A_CADA,
            CATALOGO_AO_VOLTAR: CATALOGO_AO_VOLTAR,
            desdeAUltimaSync: desdeAUltimaSync,
            deveSincronizarNoTemporizador: function () {
                if (state.syncing) return false;
                var temPendentes = state.pendingCount > 0;
                var catalogoVelho = desdeAUltimaSync() >= CATALOGO_A_CADA;
                return temPendentes || catalogoVelho;
            },
        };
    `);
}

const AGORA = 1770000000000;

function comEstado(estado, minutosDesdeASync) {
    const lastSync = minutosDesdeASync === null
        ? null
        : new Date(AGORA - minutosDesdeASync * 60000).toISOString();

    return decisor()({ syncing: false, pendingCount: 0, ...estado, lastSync }, AGORA);
}

// ---------------------------------------------------------------------------

test('sem nada por enviar, o catalogo e refrescado na mesma', async () => {
    const d = comEstado({ pendingCount: 0 }, 10);

    assert.equal(d.deveSincronizarNoTemporizador(), true,
        'era isto que faltava: quem nao vende nada nunca puxava nada');
});

test('sem nada por enviar e com o catalogo fresco, nao se incomoda o servidor', async () => {
    const d = comEstado({ pendingCount: 0 }, 1);

    assert.equal(d.deveSincronizarNoTemporizador(), false,
        'puxar o catalogo a cada 45s em dezenas de postos e carga sem retorno');
});

test('com vendas por enviar, sincroniza mesmo com o catalogo fresco', async () => {
    const d = comEstado({ pendingCount: 3 }, 0);

    assert.equal(d.deveSincronizarNoTemporizador(), true,
        'uma venda por enviar e dinheiro parado');
});

test('a sincronizar, nao se lanca outra por cima', async () => {
    const d = comEstado({ syncing: true, pendingCount: 5 }, 999);

    assert.equal(d.deveSincronizarNoTemporizador(), false);
});

test('nunca sincronizado conta como catalogo velho', async () => {
    const d = comEstado({ pendingCount: 0 }, null);

    assert.equal(d.desdeAUltimaSync(), Infinity);
    assert.equal(d.deveSincronizarNoTemporizador(), true);
});

test('uma data ilegivel nao trava a sincronizacao para sempre', async () => {
    const d = decisor()({ syncing: false, pendingCount: 0, lastSync: 'isto nao e uma data' }, AGORA);

    assert.equal(d.desdeAUltimaSync(), Infinity,
        'com NaN a comparacao dava sempre falso e nunca mais se sincronizava');
    assert.equal(d.deveSincronizarNoTemporizador(), true);
});

test('as cadencias sao as pretendidas', async () => {
    const d = comEstado({}, 0);

    assert.equal(d.PENDENTES_A_CADA, 45 * 1000);
    assert.equal(d.CATALOGO_A_CADA, 5 * 60 * 1000);
    assert.equal(d.CATALOGO_AO_VOLTAR, 2 * 60 * 1000);
});

test('o gatilho de voltar a aplicacao ja nao exige pendentes', async () => {
    const bloco = fonte.match(/visibilitychange[\s\S]*?\n        \}\);/);
    assert.ok(bloco, 'não encontrei o gatilho do visibilitychange');

    assert.ok(
        /desdeAUltimaSync\(\) >= CATALOGO_AO_VOLTAR/.test(bloco[0]),
        'ao voltar a aplicacao tem de refrescar o catalogo, e nao so enviar pendentes'
    );
});
