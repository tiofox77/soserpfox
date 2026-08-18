/**
 * Quantas coisas faltam MESMO enviar.
 *
 * A barra do topo somava as vendas por sincronizar E o comprimento da fila —
 * mas cada venda TEM um trabalho na fila. Uma venda offline dava 2 no topo e
 * 1 no selo do POS, e o operador via dois números para a mesma coisa.
 */

import { test } from 'node:test';
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const fonte = readFileSync(join(raiz, 'public', 'js', 'pwa-invoicing.js'), 'utf8');

const corpo = fonte.match(/async function refreshPendingCount\(\) \{[\s\S]*?\n    \}/);

test('a contagem sai só da fila', () => {
    assert.ok(corpo);
    assert.match(corpo[0], /db\.sync_queue\.where\('status'\)\.equals\('pending'\)\.count\(\)/);
});

test('não se somam os registos por cima da fila', () => {
    // Somá-los conta a mesma venda duas vezes: uma como registo, outra como
    // trabalho na fila.
    assert.doesNotMatch(corpo[0], /pos_sales/);
    assert.doesNotMatch(corpo[0], /draft_documents/);
    assert.doesNotMatch(corpo[0], /db\.clients/);
});

test('uma venda offline conta UMA vez', () => {
    // O contador é o comprimento da fila; uma venda enfileira um trabalho.
    const contador = new Function('db', 'state', 'updateStatusBar', `
        ${corpo[0]}
        return refreshPendingCount;
    `);

    const estado = { pendingCount: null };
    const fila = { where: () => ({ equals: () => ({ count: async () => 1 }) }) };

    return contador({ sync_queue: fila }, estado, () => {})().then((n) => {
        assert.strictEqual(n, 1);
        assert.strictEqual(estado.pendingCount, 1);
    });
});
