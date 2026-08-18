/**
 * Os totais do ecrã têm de bater com os do documento emitido.
 *
 * A ordem dos descontos não é detalhe: o comercial incide ANTES do IVA e
 * baixa o imposto; o financeiro incide DEPOIS e não lhe toca. Se o ecrã
 * contasse de outra maneira, o operador dizia um valor ao cliente e saía
 * outro na factura.
 */

import { test } from 'node:test';
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const form = readFileSync(join(raiz, 'resources', 'views', 'invoicing', 'offline', 'draft-form.blade.php'), 'utf8');
const motor = readFileSync(join(raiz, 'public', 'js', 'pwa-invoicing.js'), 'utf8');

test('o ecrã aplica o comercial antes do imposto', () => {
    const totais = form.match(/get totals\(\) \{[\s\S]*?\n        \},/);

    assert.ok(totais, 'o getter dos totais não foi encontrado');

    // O imposto recalcula-se sobre o líquido depois do comercial.
    assert.match(totais[0], /const liquido = subtotal - comercial;/);
    assert.match(totais[0], /tax \* \(liquido \/ subtotal\)/);

    // E o financeiro sai do total, já com imposto.
    assert.match(totais[0], /liquido \+ imposto - financeiro/);
});

test('o desconto de cada linha entra no líquido', () => {
    assert.match(form, /liquidoDaLinha\(itm\)/);
    assert.match(form, /bruto \* desc \/ 100/);
});

test('os campos novos viajam para o servidor', () => {
    const carga = motor.match(/await enqueue\('create_draft', \{[\s\S]*?\n            \}\);/);

    assert.ok(carga);

    for (const campo of ['discount_commercial', 'discount_financial', 'delivery_date', 'delivery_location']) {
        assert.match(carga[0], new RegExp(campo), `${campo} não vai no envio`);
    }
});

test('um total nunca fica negativo', () => {
    const totais = form.match(/get totals\(\) \{[\s\S]*?\n        \},/);

    // Um desconto maior que o documento nao pode dar um valor a pagar negativo.
    assert.match(totais[0], /Math\.max\(0,/);
});
