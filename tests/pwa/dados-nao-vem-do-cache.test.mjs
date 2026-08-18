/**
 * Pedidos de dados não podem vir do cache primeiro.
 *
 * O stale-while-revalidate devolve o que está guardado e só depois vai buscar
 * o novo. Para dados, isso é o utilizador a recarregar uma vez e não ver nada
 * mudar, recarregar outra vez e aí sim — e, entretanto, a ler números de
 * ontem a pensar que são de hoje.
 */

import { test } from 'node:test';
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const fonte = readFileSync(join(raiz, 'resources', 'pwa', 'sw.js'), 'utf8');

test('a lista de rotas de dados é mesmo usada no encaminhamento', () => {
    // Estava declarada e nunca lida: os dados caíam no "tudo o resto".
    const usos = fonte.match(/API_ROUTES/g) || [];

    assert.ok(usos.length >= 2, 'API_ROUTES tem de ser declarada E usada');
    assert.match(
        fonte,
        /API_ROUTES\.some\([^)]*\)[\s\S]{0,120}networkFirst/,
        'as rotas de dados têm de ir a network-first'
    );
});

test('os dados são decididos ANTES das páginas HTML', () => {
    // Um pedido de dados também pode trazer text/html no Accept; se o ramo
    // das páginas vier primeiro, a regra dos dados nunca chega a correr.
    const posDados = fonte.indexOf('API_ROUTES.some(');
    const posHtml = fonte.indexOf("request.headers.get('Accept')");

    assert.ok(posDados > 0 && posHtml > 0);
    assert.ok(posDados < posHtml, 'a regra dos dados tem de vir antes da das páginas');
});

test('o endpoint do Livewire 3 está na lista, não só o do Livewire 2', () => {
    // O projecto corre Livewire 3, que usa /livewire/update.
    assert.match(fonte, /'\/livewire\/update'/);
});

test('o stale-while-revalidate deixa de apanhar os dados', () => {
    const cauda = fonte.slice(fonte.indexOf('API_ROUTES.some('));

    // A rede de segurança final continua lá, mas já não é ela a servir /api/.
    assert.match(cauda, /staleWhileRevalidate\(request, DYNAMIC_CACHE/);
});
