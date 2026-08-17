/**
 * A fila de envio, em linguagem de quem está ao balcão.
 *
 * Contar quantos faltam não chega: quando um fica preso, quem está na caixa
 * precisa de ver O QUÊ ficou preso e porquê. "3 por enviar" não se distingue
 * de "3 perdidos".
 */

import { test } from 'node:test';
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const fonte = readFileSync(join(raiz, 'public', 'js', 'pwa-invoicing.js'), 'utf8');

const corpo = fonte.match(/async getQueue\(\) \{[\s\S]*?\n        \},/);
assert.ok(corpo, 'getQueue não foi encontrada no pwa-invoicing.js');

function montar(itens) {
    const fabrica = new Function('db', `return { ${corpo[0]} }.getQueue;`);

    return fabrica({
        sync_queue: { orderBy: () => ({ toArray: async () => itens }) },
    });
}

test('uma venda aparece com o que leva e quanto vale', async () => {
    const getQueue = montar([{
        id: 1, op: 'create_pos_sale', status: 'pending', retries: 0,
        created_at: '2026-08-17T10:00:00Z',
        payload: { items: [
            { quantity: 2, unit_price: 1500 },
            { quantity: 1, unit_price: 700 },
        ] },
    }]);

    const [j] = await getQueue.call(null);

    assert.strictEqual(j.tipo, 'Venda');
    assert.strictEqual(j.detalhe, '2 artigo(s) · 3700.00 Kz');
    assert.strictEqual(j.estado, 'pending');
});

test('cada tipo de trabalho tem nome de gente', async () => {
    const getQueue = montar([
        { id: 1, op: 'create_client', status: 'pending', payload: { name: 'Ana Paula' } },
        { id: 2, op: 'close_pos_shift', status: 'pending', payload: {} },
        { id: 3, op: 'logout', status: 'pending', payload: {} },
        { id: 4, op: 'create_draft', status: 'pending', payload: { doc_type: 'ft' } },
    ]);

    const j = await getQueue.call(null);

    assert.deepStrictEqual(j.map(x => x.tipo), ['Cliente', 'Fecho de turno', 'Saída de sessão', 'Documento']);
    assert.strictEqual(j[0].detalhe, 'Ana Paula');
    assert.strictEqual(j[3].detalhe, 'FT');
});

test('um trabalho preso mostra o erro e as tentativas', async () => {
    const getQueue = montar([{
        id: 9, op: 'create_pos_sale', status: 'failed', retries: 3,
        last_error: 'Cliente ainda não sincronizado',
        payload: { items: [] },
    }]);

    const [j] = await getQueue.call(null);

    assert.strictEqual(j.estado, 'failed');
    assert.strictEqual(j.tentativas, 3);
    assert.strictEqual(j.erro, 'Cliente ainda não sincronizado');
});

test('uma operação desconhecida não parte a lista', async () => {
    const getQueue = montar([{ id: 1, op: 'coisa_nova', status: 'pending', payload: null }]);

    const [j] = await getQueue.call(null);

    // Melhor mostrar o nome cru do que esconder um trabalho preso.
    assert.strictEqual(j.tipo, 'coisa_nova');
    assert.strictEqual(j.detalhe, '');
});

test('fila vazia devolve lista vazia', async () => {
    const getQueue = montar([]);

    assert.deepStrictEqual(await getQueue.call(null), []);
});
