/**
 * Com rede, a venda sai já com o número fiscal.
 *
 * O documento provisório existe para o caso de NÃO haver rede. Havendo, o
 * cliente não pode sair da loja com um talão "PEND-" e o número fiscal
 * aparecer meia hora depois.
 *
 * A função é lida do próprio ficheiro, para o teste não poder divergir dela.
 */

import { test } from 'node:test';
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const fonte = readFileSync(join(raiz, 'public', 'js', 'pwa-invoicing.js'), 'utf8');

const corpo = fonte.match(/async function emitirJa\(local_uuid, msLimite = \d+\) \{[\s\S]*?\n    \}/);
assert.ok(corpo, 'emitirJa não foi encontrada no pwa-invoicing.js');

/** Monta a função com as dependências trocadas por duplos. */
function montar({ online, redeReal, vendaDepois, syncDemora = 0 }) {
    const chamadas = { sync: 0 };

    const fabrica = new Function(
        'navigator', 'checkRealOnline', 'sync', 'db', 'chamadas',
        `${corpo[0]}; return emitirJa;`
    );

    return {
        chamadas,
        emitirJa: fabrica(
            { onLine: online },
            async () => redeReal,
            async () => {
                chamadas.sync++;
                if (syncDemora) await new Promise(r => setTimeout(r, syncDemora));
            },
            { pos_sales: { get: async () => vendaDepois } },
            chamadas
        ),
    };
}

test('sem rede não espera nem sincroniza', async () => {
    const { emitirJa, chamadas } = montar({ online: false, redeReal: false, vendaDepois: null });

    assert.strictEqual(await emitirJa('pos_1'), null);
    assert.strictEqual(chamadas.sync, 0, 'offline não se tenta sincronizar');
});

test('rede aparente mas morta não devolve venda emitida', async () => {
    // navigator.onLine mente: diz que há rede quando só há wifi sem saída.
    const { emitirJa, chamadas } = montar({ online: true, redeReal: false, vendaDepois: null });

    assert.strictEqual(await emitirJa('pos_1'), null);
    assert.strictEqual(chamadas.sync, 0);
});

test('com rede devolve a venda já com número fiscal', async () => {
    const venda = { local_uuid: 'pos_1', _synced: 1, _server_number: 'FR SOS/2026/41' };
    const { emitirJa, chamadas } = montar({ online: true, redeReal: true, vendaDepois: venda });

    const r = await emitirJa('pos_1');

    assert.strictEqual(r, venda);
    assert.strictEqual(r._server_number, 'FR SOS/2026/41');
    assert.strictEqual(chamadas.sync, 1);
});

test('se a venda não chegou a sincronizar devolve null e segue provisória', async () => {
    const { emitirJa } = montar({
        online: true, redeReal: true,
        vendaDepois: { local_uuid: 'pos_1', _synced: 0, provisional_number: 'PEND-20260817-ABC123' },
    });

    assert.strictEqual(await emitirJa('pos_1'), null);
});

test('rede lenta não prende o balcão: passa o limite e segue', async () => {
    const { emitirJa } = montar({
        online: true, redeReal: true,
        vendaDepois: { _synced: 0 },
        syncDemora: 300,
    });

    const t0 = Date.now();
    const r = await emitirJa('pos_1', 50);

    assert.strictEqual(r, null);
    assert.ok(Date.now() - t0 < 250, 'devia ter desistido ao fim do limite, não esperado pela sync');
});

test('um erro a meio não rebenta a venda', async () => {
    const fabrica = new Function(
        'navigator', 'checkRealOnline', 'sync', 'db',
        `${corpo[0]}; return emitirJa;`
    );

    const emitirJa = fabrica(
        { onLine: true },
        async () => { throw new Error('boom'); },
        async () => {},
        { pos_sales: { get: async () => null } }
    );

    assert.strictEqual(await emitirJa('pos_1'), null);
});
