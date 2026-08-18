/**
 * Sair funciona sempre, com ou sem internet.
 *
 * Só se tomava o caminho local quando o navegador dizia estar sem rede — e
 * esse valor diz apenas que há placa ligada, não que haja internet. Com wifi
 * sem saída, quem carregava em Sair não saía, e a caixa seguinte não
 * conseguia entrar. Numa loja a internet pode faltar dias e as pessoas
 * rendem-se de turno na mesma.
 */

import { test } from 'node:test';
import assert from 'node:assert';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const layout = readFileSync(join(raiz, 'resources', 'views', 'layouts', 'pwa.blade.php'), 'utf8');
const motor = readFileSync(join(raiz, 'public', 'js', 'pwa-invoicing.js'), 'utf8');

const handler = layout.match(/getElementById\('pwa-sair'\)[\s\S]*?\n                \}\);/);

/** Só o código: os comentários explicam o defeito antigo e falam dele. */
const codigo = (handler?.[0] ?? '')
    .split('\n')
    .filter((l) => !l.trim().startsWith('//'))
    .join('\n');

test('a saída nunca consulta o estado da rede', () => {
    assert.ok(handler, 'o handler do Sair não foi encontrado');
    assert.doesNotMatch(codigo, /navigator\.onLine/);
});

test('o formulário nunca chega a ser submetido ao servidor', () => {
    // O preventDefault é incondicional: quem decide é o aparelho.
    assert.match(codigo, /e\.preventDefault\(\)/);
});

test('uma falha a comunicar não impede a saída', () => {
    assert.match(codigo, /catch/);
    assert.match(codigo, /window\.location\.href/);
});

test('a saída tranca o aparelho mas não apaga o acesso offline', () => {
    const sair = motor.match(/async sair\(\) \{[\s\S]*?\n        \},/);

    assert.ok(sair);
    assert.match(sair[0], /removeItem\('pwa_unlocked'\)/);

    // Apagar o auth_cache obrigava a uma ida à internet só para voltar a
    // trabalhar — que é o que este modo existe para evitar.
    assert.doesNotMatch(sair[0], /auth_cache/);
});

test('a saída entra na fila para o servidor saber', () => {
    const sair = motor.match(/async sair\(\) \{[\s\S]*?\n        \},/);

    assert.match(sair[0], /enqueue\('logout'/);
});
