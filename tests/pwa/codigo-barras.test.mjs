/**
 * As formas equivalentes de um código de barras, no lado offline.
 *
 * Tem de dar exactamente o mesmo que App\Support\CodigoDeBarras no servidor:
 * se as duas listas divergirem, o mesmo artigo é encontrado online e não é
 * encontrado offline, que é o pior dos dois mundos porque só aparece quando
 * a rede falha.
 */

import { test } from 'node:test';
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const fonte = readFileSync(join(raiz, 'public', 'js', 'pwa-invoicing.js'), 'utf8');

const corpo = fonte.match(/function formasDeCodigo\(lido\) \{[\s\S]*?\n    \}/);
assert.ok(corpo, 'formasDeCodigo não foi encontrada no pwa-invoicing.js');

const formasDeCodigo = new Function(`${corpo[0]}; return formasDeCodigo;`)();

test('o lido vem sempre primeiro', () => {
    assert.strictEqual(formasDeCodigo('8902292003269')[0], '8902292003269');
    assert.strictEqual(formasDeCodigo('0108902292003269')[0], '0108902292003269');
});

test('do envelope GS1 chega-se ao EAN-13', () => {
    const f = formasDeCodigo('0108902292003269');
    assert.ok(f.includes('08902292003269'));
    assert.ok(f.includes('8902292003269'));
});

test('do EAN-13 chega-se ao envelope', () => {
    const f = formasDeCodigo('8902292003269');
    assert.ok(f.includes('0108902292003269'));
    assert.ok(f.includes('08902292003269'));
});

test('os glifos do leitor não contam', () => {
    assert.ok(formasDeCodigo(')01=08902292003269').includes('8902292003269'));
});

test('GS1 com validade atrás ainda dá o produto', () => {
    assert.ok(formasDeCodigo('0103400935955838172').includes('3400935955838'));
});

test('um código interno fica como está', () => {
    assert.deepStrictEqual(formasDeCodigo('0000548'), ['0000548']);
    assert.deepStrictEqual(formasDeCodigo('000456'), ['000456']);
});

test('nunca devolve repetidos nem vazios', () => {
    for (const lido of ['8902292003269', '0108902292003269', '', '   ', 'ABC']) {
        const f = formasDeCodigo(lido);
        assert.deepStrictEqual(f, [...new Set(f)]);
        assert.ok(!f.includes(''));
    }
});
