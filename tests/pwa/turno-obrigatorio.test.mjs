/**
 * Sem turno aberto não se vende.
 *
 * O POS perguntava se queria continuar, e continuar era o caminho fácil: a
 * venda saía mas ficava fora do fecho de caixa. Ao fim do dia o dinheiro na
 * gaveta não batia certo e ninguém sabia de que venda vinha a diferença — e
 * uma caixa que não fecha não serve para conferir ninguém.
 */

import { test } from 'node:test';
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const pos = readFileSync(join(raiz, 'resources', 'views', 'invoicing', 'offline', 'pos.blade.php'), 'utf8');

const checkout = pos.match(/async checkout\(\) \{[\s\S]*?\n            this\.saving = true;/);

test('o checkout sai logo quando não há turno', () => {
    assert.ok(checkout, 'checkout não foi encontrado');
    assert.match(checkout[0], /if \(!this\.shift\.open\) \{[\s\S]*?return;/);
});

test('não se pergunta se quer vender à mesma', () => {
    // Um confirm aqui é um convite: quem tem fila à espera carrega em OK.
    assert.doesNotMatch(checkout[0], /confirm\(/);
});

test('o botão de finalizar está desactivado sem turno', () => {
    assert.match(pos, /:disabled="!cart\.length \|\| saving \|\| !shift\.open"/);
});

test('o aviso diz que é bloqueio e não um risco', () => {
    assert.match(pos, /abra um turno para poder vender/);
    assert.doesNotMatch(pos, /as vendas podem não entrar no fecho de caixa/);
});

test('o aviso continua a oferecer abrir o turno ali mesmo', () => {
    // Bloquear sem dar a saída ao lado é deixar a caixa parada.
    assert.match(pos, /@click="openOpenShiftModal\(\)"/);
});
