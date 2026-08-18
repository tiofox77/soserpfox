/**
 * O IEC e o Selo viajam como ESCOLHA, nunca como valor.
 *
 * Se o aparelho calculasse, o documento ficava com dois apuramentos do mesmo
 * imposto — o dele e o do servidor — e a AGT recusa com "taxContribution não
 * corresponde ao imposto apurado". Já aconteceu uma vez.
 */

import { test } from 'node:test';
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const form = readFileSync(join(raiz, 'resources', 'views', 'invoicing', 'offline', 'draft-form.blade.php'), 'utf8');
const motor = readFileSync(join(raiz, 'public', 'js', 'pwa-invoicing.js'), 'utf8');

test('a linha nasce com os dois campos, vazios', () => {
    const add = form.match(/addItem\(p\) \{[\s\S]*?\n        \},/);

    assert.ok(add);
    assert.match(add[0], /iec_pautal: null/);
    assert.match(add[0], /is_verba: null/);
});

test('o ecrã oferece o código, e não uma caixa de valor', () => {
    // Um input numérico aqui seria o aparelho a decidir o imposto.
    assert.match(form, /x-model="itm\.iec_pautal"/);
    assert.match(form, /x-model="itm\.is_verba"/);
    assert.doesNotMatch(form, /x-model[.\w]*="itm\.iec_amount"/);
    assert.doesNotMatch(form, /x-model[.\w]*="itm\.is_amount"/);
});

test('as tabelas da AGT são substituídas por inteiro, não juntadas', () => {
    // São listas fechadas: um pautal que saia da tabela tem de sair também
    // do aparelho, senão continua a ser oferecido.
    assert.match(motor, /db\.iec_pautais\.clear\(\)/);
    assert.match(motor, /db\.is_verbas\.clear\(\)/);
});

test('o esquema local tem as duas tabelas', () => {
    assert.match(motor, /iec_pautais: 'pautal_code'/);
    assert.match(motor, /is_verbas: 'verba_no'/);
});
