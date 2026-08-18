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

    // O duplo imita a consulta real: so pendentes e falhados.
    return fabrica({
        sync_queue: {
            where: () => ({
                anyOf: (...estados) => ({
                    sortBy: async () => itens.filter((j) => estados.includes(j.status)),
                }),
            }),
        },
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

test('um trabalho JÁ ENTREGUE não aparece na lista do que falta', () => {
    // Os entregues ficam marcados 'done' na tabela e nunca são removidos.
    // Listá-los punha vendas já recebidas pelo servidor a aparecer como
    // "à espera" — o operador via três vendas paradas que afinal tinham
    // subido todas.
    const fonteAtual = readFileSync(join(raiz, 'public', 'js', 'pwa-invoicing.js'), 'utf8');
    const corpoAtual = fonteAtual.match(/async getQueue\(\) \{[\s\S]*?\n        \},/);

    assert.ok(corpoAtual);
    assert.match(
        corpoAtual[0],
        /anyOf\(\s*'pending',\s*'failed'\s*\)/,
        'a lista tem de pedir só o que ainda falta, e não a tabela toda'
    );
    assert.doesNotMatch(corpoAtual[0], /orderBy\('created_at'\)\.toArray\(\)/);
});

test('os entregues antigos são limpos, para a tabela não crescer sem fim', () => {
    const fonteAtual = readFileSync(join(raiz, 'public', 'js', 'pwa-invoicing.js'), 'utf8');

    assert.match(fonteAtual, /async function limparEntreguesAntigos/);
    assert.match(fonteAtual, /await limparEntreguesAntigos\(\);/);
});

test('o entregue fica de fora mesmo estando na tabela', async () => {
    const getQueue = montar([
        { id: 1, op: 'create_pos_sale', status: 'done', payload: { items: [] } },
        { id: 2, op: 'create_pos_sale', status: 'pending', payload: { items: [{ quantity: 1, unit_price: 500 }] } },
        { id: 3, op: 'create_pos_sale', status: 'failed', last_error: 'x', payload: { items: [] } },
    ]);

    const j = await getQueue.call(null);

    // O 'done' é uma venda que o servidor JÁ recebeu. Mostrá-la como
    // "à espera" é dizer ao operador que o dinheiro dele não chegou.
    assert.deepStrictEqual(j.map(x => x.id), [2, 3]);
});
