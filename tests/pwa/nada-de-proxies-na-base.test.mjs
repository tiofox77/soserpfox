/**
 * O que entra na base tem de ser dados simples.
 *
 * O ecrã manda um objecto reactivo do Alpine, que é um Proxy, e o IndexedDB
 * não sabe clonar Proxies: rebentava com "could not be cloned" e o documento
 * não chegava a ser gravado — o operador via um erro e perdia o que escreveu.
 */

import { test } from 'node:test';
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const fonte = readFileSync(join(raiz, 'public', 'js', 'pwa-invoicing.js'), 'utf8');

test('o documento é reduzido a dados antes de ir para a base', () => {
    const corpo = fonte.match(/async createDraftOffline\(draft\) \{[\s\S]{0,900}/);

    assert.ok(corpo);
    assert.match(corpo[0], /JSON\.parse\(JSON\.stringify\(draft/);
});

test('um Proxy do Alpine sobrevive à travessia', () => {
    // O que o structuredClone recusa, o JSON aceita — e é isso que se guarda.
    const reactivo = new Proxy(
        { doc_type: 'FT', items: [{ quantity: 2, unit_price: 1500 }] },
        { get: (a, p) => a[p] }
    );

    assert.throws(() => structuredClone(reactivo), 'o Proxy tinha mesmo de recusar clone');

    const simples = JSON.parse(JSON.stringify(reactivo));

    assert.doesNotThrow(() => structuredClone(simples));
    assert.strictEqual(simples.items[0].unit_price, 1500);
});
