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
    // Depois, o network-first ainda servia uma cópia guardada quando a rede
    // falhava — e um relatório de caixa lia números de ontem. Hoje os dados
    // vão SÓ à rede (soRede): sem rede, o motor recebe um 503 e decide.
    const usos = fonte.match(/API_ROUTES/g) || [];

    assert.ok(usos.length >= 2, 'API_ROUTES tem de ser declarada E usada');
    assert.match(
        fonte,
        /API_ROUTES\.some\([^)]*\)[\s\S]{0,120}soRede/,
        'as rotas de dados vão só à rede, nunca a uma cópia guardada'
    );
});

test('os dados são decididos ANTES das páginas HTML', () => {
    // Um pedido de dados também pode trazer text/html no Accept; se o ramo
    // das páginas vier primeiro, a regra dos dados nunca chega a correr.
    const posDados = fonte.indexOf('API_ROUTES.some(');
    // Ancora-se na CHAMADA dentro do encaminhamento, não na pergunta:
    // a função `ehPedidoDePagina` é declarada mais acima no ficheiro.
    const posHtml = fonte.indexOf('if (ehPedidoDePagina(request))');

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

test('o molde do documento vai à rede, não ao stale-while-revalidate', () => {
    const fonte = readFileSync(join(raiz, 'resources', 'pwa', 'sw.js'), 'utf8');
    const lista = fonte.slice(fonte.indexOf('const API_ROUTES'), fonte.indexOf('];', fonte.indexOf('const API_ROUTES')));

    // O aparelho guarda o molde no IndexedDB e revalida por ETag; se o service
    // worker lhe devolvesse a cópia velha, o papel sem rede ficava um ciclo
    // atrasado em relação à pré-visualização do servidor.
    assert.ok(lista.includes("'/invoicing/offline/molde/'"), 'o molde tem de estar nas rotas de dados');
});

test('a navegação do wire:navigate conta como página, e não como "tudo o resto"', () => {
    const fonte = readFileSync(join(raiz, 'resources', 'pwa', 'sw.js'), 'utf8');

    // O Livewire vai buscar a página com um fetch sem Accept. Sem isto, a
    // página caía no stale-while-revalidate e era servida VELHA — ao trocar de
    // empresa, apareciam os dados da anterior.
    assert.match(fonte, /function ehPedidoDePagina/, 'falta a pergunta que reconhece navegação');
    assert.match(fonte, /request\.mode === 'navigate'/, 'a navegação do browser conta');
    assert.match(fonte, /headers\.has\('X-Livewire-Navigate'\)/, 'a navegação do Livewire conta');
    assert.match(fonte, /if \(ehPedidoDePagina\(request\)\)/, 'o encaminhamento tem de usar a pergunta');
});

test('nenhuma página HTML entra na rede de segurança final', () => {
    const fonte = readFileSync(join(raiz, 'resources', 'pwa', 'sw.js'), 'utf8');
    const swr = fonte.slice(fonte.indexOf('async function staleWhileRevalidate'));

    // A regra é uma lista do que PODE entrar, não do que não entra.
    assert.match(swr, /ehConteudoImutavel\(response\)/, 'só entra conteúdo sem dono');
    assert.match(swr, /cached && ehConteudoImutavel\(cached\)/, 'nem se serve o que tem dono');
    assert.match(fonte, /function ehConteudoImutavel/, 'falta a lista do que pode ser guardado');
    assert.ok(!/text\/html/.test(fonte.slice(fonte.indexOf('function ehConteudoImutavel'), fonte.indexOf('function ehConteudoImutavel') + 600)),
        'HTML não pode constar da lista do que pode ser guardado');
});

test('ao activar, as páginas da retaguarda que já lá estão são deitadas fora', () => {
    const fonte = readFileSync(join(raiz, 'resources', 'pwa', 'sw.js'), 'utf8');

    // O cache das páginas sobrevive aos deploys de propósito. Sem esta limpeza,
    // o que entrou por engano ficava nos aparelhos para sempre.
    assert.match(fonte, /limparPaginasIntrusas/, 'falta a limpeza');
    assert.match(fonte, /\.then\(\(\) => limparPaginasIntrusas\(\)\)/, 'a limpeza tem de correr na activação');
    assert.match(fonte, /if \(!ehPaginaDoPwa\(caminho\)\) await cache\.delete\(pedido\)/, 'só ficam as páginas do PWA');
});
