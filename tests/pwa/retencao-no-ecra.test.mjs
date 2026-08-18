/**
 * A retenção na fonte no ecrã do PWA.
 *
 * Não é imposto do documento: é dinheiro que o cliente entrega ao Estado em
 * vez de o entregar a quem factura. Baixa o total a receber e não mexe no
 * IVA — e o ecrã tem de contar como o servidor, senão o operador diz um
 * valor ao cliente e sai outro na factura.
 */

import { test } from 'node:test';
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const form = readFileSync(join(raiz, 'resources', 'views', 'invoicing', 'offline', 'draft-form.blade.php'), 'utf8');
const motor = readFileSync(join(raiz, 'public', 'js', 'pwa-invoicing.js'), 'utf8');

const totais = form.match(/get totals\(\) \{[\s\S]*?\n        \},/);

test('só há retenção em prestação de serviço', () => {
    assert.ok(totais);
    assert.match(totais[0], /this\.form\.is_service\s*\n?\s*\?/);
});

test('a retenção sai do total e não do imposto', () => {
    // Se entrasse no imposto, ia parar à AGT como IVA que ninguém liquidou.
    assert.match(totais[0], /liquido \+ imposto - financeiro - retencao/);
    assert.doesNotMatch(totais[0], /imposto\s*[-+]\s*retencao/);
});

test('a taxa por omissão é 6,5%', () => {
    assert.match(totais[0], /6\.5/);
});

test('a escolha viaja para o servidor', () => {
    const carga = motor.match(/await enqueue\('create_draft', \{[\s\S]*?\n            \}\);/);

    assert.ok(carga);
    assert.match(carga[0], /is_service/);
    assert.match(carga[0], /withholding_percentage/);
});

test('sem prestação de serviço não se manda percentagem nenhuma', () => {
    const carga = motor.match(/await enqueue\('create_draft', \{[\s\S]*?\n            \}\);/);

    // Mandar 6,5 com is_service falso convidava o servidor a aplicá-la.
    assert.match(carga[0], /draft\.is_service\s*\n?\s*\?[\s\S]{0,80}: null/);
});
