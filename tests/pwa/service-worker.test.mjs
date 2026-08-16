/**
 * Testes do service worker, contra o ficheiro REAL.
 *
 * Corre em Node com um ambiente de service worker fingido — caches, fetch e os
 * listeners — porque o que se quer provar aqui não é o browser: é a decisão que
 * o sw.js toma quando o servidor responde mal.
 *
 * O que motivou isto: num sábado o servidor ficou em baixo e o PWA não abriu,
 * apesar de ter tudo guardado. A causa era o fetch RESOLVER com um 502 em vez
 * de rejeitar, e o recurso ao cache viver só no catch.
 *
 *   node --test tests/pwa/service-worker.test.mjs
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

// ---------------------------------------------------------------------------
// Um ambiente de service worker suficiente para o sw.js correr.
// ---------------------------------------------------------------------------

class RespostaFalsa {
    constructor(corpo, { status = 200, redirected = false, url = 'https://exemplo.ao/', type = 'basic' } = {}) {
        this.corpo = corpo;
        this.status = status;
        this.ok = status >= 200 && status < 300;
        this.redirected = redirected;
        this.url = url;
        this.type = type;
        this.headers = { get: () => null };
    }

    clone() {
        return new RespostaFalsa(this.corpo, {
            status: this.status, redirected: this.redirected, url: this.url, type: this.type,
        });
    }
}

class CacheFalso {
    constructor(buscar) { this.itens = new Map(); this.buscar = buscar; }
    async put(pedido, resposta) { this.itens.set(chave(pedido), resposta); }
    async match(pedido) { return this.itens.get(chave(pedido)) ?? undefined; }
    async keys() { return [...this.itens.keys()].map((u) => ({ url: u })); }
    async delete(pedido) { return this.itens.delete(chave(pedido)); }

    async add(url) {
        const resposta = await this.buscar(url);
        if (!resposta || !resposta.ok) throw new TypeError('falhou a pré-guardar ' + url);
        this.itens.set(url, resposta);
    }

    async addAll(urls) {
        // Tudo-ou-nada, como o real: é justamente o que não se quer no install.
        const respostas = await Promise.all(urls.map((u) => this.buscar(u)));
        respostas.forEach((r, i) => {
            if (!r || !r.ok) throw new TypeError('falhou a pré-guardar ' + urls[i]);
        });
        respostas.forEach((r, i) => this.itens.set(urls[i], r));
    }
}

function chave(pedido) {
    return typeof pedido === 'string' ? pedido : pedido.url;
}

function montarAmbiente({ respostaDaRede, erroDeRede, urlQueFalha } = {}) {
    const caches = new Map();

    const buscar = async (url) => {
        if (urlQueFalha && String(url).includes(urlQueFalha)) {
            throw new TypeError('Failed to fetch');
        }

        return new RespostaFalsa('conteudo de ' + url);
    };

    const ambiente = {
        CACHE_VERSION_INJECTADA: 'soserp-teste',
        listeners: {},
        caches: {
            async open(nome) {
                if (!caches.has(nome)) caches.set(nome, new CacheFalso(buscar));
                return caches.get(nome);
            },
            async match(pedido) {
                for (const c of caches.values()) {
                    const achado = await c.match(pedido);
                    if (achado) return achado;
                }
                return undefined;
            },
            async keys() { return [...caches.keys()]; },
            async delete(nome) { return caches.delete(nome); },
        },
        async fetch() {
            if (erroDeRede) throw new TypeError('Failed to fetch');
            return respostaDaRede;
        },
        caches_internos: caches,
    };

    return ambiente;
}

/** Carrega o sw.js real dentro do ambiente fingido e devolve o que interessa. */
async function carregarServiceWorker(ambiente) {
    const fonte = readFileSync(join(raiz, 'resources', 'pwa', 'sw.js'), 'utf8');

    const self = {
        addEventListener: (nome, fn) => { ambiente.listeners[nome] = fn; },
        clients: { claim: async () => {}, matchAll: async () => [] },
        skipWaiting: async () => {},
        registration: { showNotification: async () => {} },
    };

    // O sw.js não exporta nada: acrescenta-se um retorno no fim para alcançar as
    // funções que se querem exercitar, em vez de as copiar para o teste — copiá-las
    // seria testar a cópia, e a cópia não é a que corre no telemóvel de ninguém.
    const corpo = fonte + '\n;return { networkFirst, podeSerGuardada, DYNAMIC_CACHE, CACHE_VERSION };';

    const fabrica = new Function('self', 'caches', 'fetch', 'console', 'URL', 'Response', corpo);

    return fabrica(
        self,
        ambiente.caches,
        ambiente.fetch,
        { log() {}, warn() {}, error() {} },
        URL,
        RespostaFalsa
    );
}

const PEDIDO_POS = { url: 'https://soserp.vip/invoicing/offline/pos', method: 'GET' };

// ---------------------------------------------------------------------------

test('um 502 do servidor devolve a pagina guardada, e nao o erro', async () => {
    const ambiente = montarAmbiente({
        respostaDaRede: new RespostaFalsa('<h1>502 Bad Gateway</h1>', { status: 502 }),
    });
    const sw = await carregarServiceWorker(ambiente);

    // A página do POS já tinha sido guardada numa visita anterior com rede.
    const cache = await ambiente.caches.open(sw.DYNAMIC_CACHE);
    await cache.put(PEDIDO_POS, new RespostaFalsa('<h1>POS offline</h1>'));

    const resposta = await sw.networkFirst(PEDIDO_POS);

    assert.equal(resposta.corpo, '<h1>POS offline</h1>',
        'com o servidor avariado tinha de vir a pagina guardada');
});

test('um 500 do Laravel tambem devolve a pagina guardada', async () => {
    const ambiente = montarAmbiente({
        respostaDaRede: new RespostaFalsa('<h1>Server Error</h1>', { status: 500 }),
    });
    const sw = await carregarServiceWorker(ambiente);

    const cache = await ambiente.caches.open(sw.DYNAMIC_CACHE);
    await cache.put(PEDIDO_POS, new RespostaFalsa('<h1>POS offline</h1>'));

    const resposta = await sw.networkFirst(PEDIDO_POS);

    assert.equal(resposta.corpo, '<h1>POS offline</h1>');
});

test('sem rede nenhuma continua a devolver a pagina guardada', async () => {
    const ambiente = montarAmbiente({ erroDeRede: true });
    const sw = await carregarServiceWorker(ambiente);

    const cache = await ambiente.caches.open(sw.DYNAMIC_CACHE);
    await cache.put(PEDIDO_POS, new RespostaFalsa('<h1>POS offline</h1>'));

    const resposta = await sw.networkFirst(PEDIDO_POS);

    assert.equal(resposta.corpo, '<h1>POS offline</h1>');
});

test('sem nada guardado, um 502 mostra o erro do servidor e nao uma pagina em branco', async () => {
    const ambiente = montarAmbiente({
        respostaDaRede: new RespostaFalsa('<h1>502 Bad Gateway</h1>', { status: 502 }),
    });
    const sw = await carregarServiceWorker(ambiente);

    const resposta = await sw.networkFirst(PEDIDO_POS);

    assert.equal(resposta.status, 502, 'quem esta a ver tem de perceber que o problema e do servidor');
});

test('com o servidor bom, a pagina vem da rede e fica guardada', async () => {
    const ambiente = montarAmbiente({
        respostaDaRede: new RespostaFalsa('<h1>POS fresco</h1>', { status: 200 }),
    });
    const sw = await carregarServiceWorker(ambiente);

    const resposta = await sw.networkFirst(PEDIDO_POS);
    assert.equal(resposta.corpo, '<h1>POS fresco</h1>');

    const cache = await ambiente.caches.open(sw.DYNAMIC_CACHE);
    const guardada = await cache.match(PEDIDO_POS);
    assert.ok(guardada, 'a pagina boa tinha de ficar guardada para a proxima');
});

test('a pagina de login NAO fica guardada no lugar do POS', async () => {
    const ambiente = montarAmbiente({
        respostaDaRede: new RespostaFalsa('<h1>Entrar</h1>', {
            status: 200, redirected: true, url: 'https://soserp.vip/login',
        }),
    });
    const sw = await carregarServiceWorker(ambiente);

    await sw.networkFirst(PEDIDO_POS);

    const cache = await ambiente.caches.open(sw.DYNAMIC_CACHE);
    assert.equal(await cache.match(PEDIDO_POS), undefined,
        'a sessao expirada ficava gravada por cima do POS e passava a aparecer offline');
});

test('uma sessao expirada nao apaga o que ja estava guardado', async () => {
    const ambiente = montarAmbiente({
        respostaDaRede: new RespostaFalsa('<h1>Entrar</h1>', {
            status: 200, redirected: true, url: 'https://soserp.vip/login',
        }),
    });
    const sw = await carregarServiceWorker(ambiente);

    const cache = await ambiente.caches.open(sw.DYNAMIC_CACHE);
    await cache.put(PEDIDO_POS, new RespostaFalsa('<h1>POS offline</h1>'));

    const resposta = await sw.networkFirst(PEDIDO_POS);

    assert.equal(resposta.corpo, '<h1>POS offline</h1>');
});

test('o cache das paginas nao leva a versao, para sobreviver aos deploys', async () => {
    const ambiente = montarAmbiente({ respostaDaRede: new RespostaFalsa('x') });
    const sw = await carregarServiceWorker(ambiente);

    assert.ok(
        !sw.DYNAMIC_CACHE.includes(sw.CACHE_VERSION),
        'com a versao no nome, cada deploy apagava as paginas guardadas'
    );
});

test('um CDN em baixo nao leva o resto do pre-cache atras', async () => {
    const ambiente = montarAmbiente({ urlQueFalha: 'unpkg.com/alpinejs' });
    const sw = await carregarServiceWorker(ambiente);

    // Correr o install a serio, com um dos URLs a falhar.
    let porEsperar = null;
    await ambiente.listeners.install({ waitUntil: (p) => { porEsperar = p; } });
    await porEsperar;

    const estatico = await ambiente.caches.open(`static-${sw.CACHE_VERSION}`);

    assert.ok(await estatico.match('/offline'),
        'a pagina /offline tinha de ficar guardada mesmo com um CDN em baixo');
    assert.ok(await estatico.match('https://unpkg.com/dexie@4.0.10/dist/dexie.min.js'),
        'o Dexie tinha de ficar guardado — sem ele o motor offline nem arranca');
    assert.equal(await estatico.match('https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js'), undefined,
        'so o que falhou e que devia faltar');
});

test('o Dexie e o Alpine estao na lista do pre-cache', async () => {
    const fonte = readFileSync(join(raiz, 'resources', 'pwa', 'sw.js'), 'utf8');
    const layout = readFileSync(join(raiz, 'resources', 'views', 'layouts', 'pwa.blade.php'), 'utf8');

    // Os URLs tem de ser os MESMOS do layout: um URL diferente e outra entrada
    // no cache, e o pre-cache passava a guardar uma coisa que ninguem pede.
    for (const biblioteca of ['dexie', 'alpinejs']) {
        const pedido = layout.match(new RegExp('https://unpkg\.com/' + biblioteca + '[^"]*'));
        assert.ok(pedido, 'o layout devia pedir o ' + biblioteca);
        assert.ok(fonte.includes(pedido[0]),
            'o sw.js tem de pre-guardar exactamente ' + pedido[0]);
    }
});
