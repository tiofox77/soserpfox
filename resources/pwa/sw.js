/**
 * SOS ERP - Service Worker
 * Cache imersivo + Offline parcial
 */

const CACHE_VERSION = 'soserp-v1.1.0';
const STATIC_CACHE = `static-${CACHE_VERSION}`;
const IMAGE_CACHE = `images-${CACHE_VERSION}`;
const API_CACHE = `api-${CACHE_VERSION}`;

// O cache das PÁGINAS não leva a versão, e isso é deliberado.
//
// O activate apaga todos os caches cujo nome não tenha a versão actual, e o
// servidor injecta uma versão nova a cada deploy. Com o nome versionado, cada
// deploy deitava fora as páginas guardadas — incluindo o POS offline — e o
// aparelho ficava sem rede de segurança até alguém voltar a abrir cada página
// com internet. Se o servidor caísse nesse intervalo, não havia nada que
// mostrar. Foi o que aconteceu.
//
// Sem versão, as páginas atravessam os deploys. Ficarem velhas não é problema:
// a estratégia é network-first, portanto com rede vem sempre a versão nova, e
// o que está guardado só serve quando não há mais nada.
const DYNAMIC_CACHE = 'dynamic-paginas';

// Recursos estáticos pré-cacheados (App Shell)
/**
 * O que tem de estar guardado ANTES de faltar a rede.
 *
 * ESTA LISTA TEM DE BATER LETRA A LETRA COM O QUE O layouts/pwa.blade.php
 * PEDE. Um URL diferente é uma entrada diferente no cache e não serve de nada
 * — foi o que aconteceu: o layout passou a carregar tudo de `/vendor/`, local,
 * e esta lista ficou a pré-guardar os CDN (unpkg, cdnjs, cdn.tailwindcss.com)
 * que a aplicação já não pede. Resultado num aparelho acabado de instalar e
 * sem rede: sem Tailwind não há desenho, sem Alpine o ecrã não responde, e sem
 * o Dexie o motor offline nem arranca — o pwa-invoicing.js começa com um
 * `if (typeof Dexie === 'undefined') return`. Parecia que a lógica offline não
 * existia; existia, mas nunca chegava a correr.
 *
 * As `?v=` fazem parte do URL: sem elas o cache falha por um caracter.
 */
const PRECACHE_URLS = [
    '/offline',
    '/pwa/icon-192x192.png',
    '/pwa/icon-512x512.png',

    // ── O motor. Sem estes quatro não há offline nenhum. ──────────────
    '/vendor/js/dexie.min.js',
    '/vendor/js/alpine.min.js',
    '/vendor/js/tailwind.js',
    '/vendor/css/fontawesome.min.css',

    // bcryptjs — verifica o PIN de turno offline.
    '/js/vendor/bcrypt.min.js?v=1',

    // ── O código da aplicação offline. É ISTO que faz o trabalho: sem
    //    ele o ecrã carrega e não sabe fazer nada. ──────────────────────
    '/js/pwa-invoicing.js?v=21',
    '/js/pos-offline-ticket.js?v=4',
];

/**
 * As páginas do PWA, guardadas na instalação.
 *
 * Sem isto só ficavam em cache depois de alguém as ter aberto COM rede. Quem
 * instalasse a aplicação e fosse vender a um sítio sem cobertura encontrava a
 * página de offline em vez do POS — e o POS é a razão de a aplicação existir.
 *
 * Vão à parte porque falham por outros motivos (sessão expirada, por exemplo)
 * e uma falha aqui não pode impedir o resto de ficar guardado.
 */
const PRECACHE_PAGINAS = [
    '/invoicing/offline',
    '/invoicing/offline/pos',
    // O POS de restaurante. Responde 403 a quem não tem o módulo, e o
    // guardarPagina() só guarda respostas OK — a empresa sem restaurante não
    // fica com um ecrã de restaurante escondido no aparelho.
    '/invoicing/offline/restaurant',
    '/invoicing/offline/catalog',
    '/invoicing/offline/clients',
    '/invoicing/offline/drafts',
];

// Rotas que NUNCA devem ser cacheadas
const NEVER_CACHE = [
    '/livewire/message',
    '/livewire/upload-file',
    '/logout',
    '/login',
    '/register',
    '/broadcasting/auth',
    '/sanctum/csrf-cookie',
];

// Rotas de API/dados dinâmicos.
//
// ISTO ESTAVA DECLARADO E NUNCA ERA USADO, e os pedidos de dados caíam no
// "tudo o resto" lá em baixo, que é stale-while-revalidate: devolve o que
// está guardado e só depois vai buscar o novo. Dava exactamente o que se via
// no ecrã — recarregar uma vez não mudava nada, recarregar outra vez mostrava
// os dados. Numa lista de turnos ou num relatório de caixa, isso é ler
// números de ontem a pensar que são de hoje.
const API_ROUTES = [
    '/api/',
    // O Livewire 3 usa /livewire/update; o /message é do Livewire 2 e ficou
    // aqui de arrasto. Os dois vão a POST e já saltam pelo método, mas a
    // lista tem de dizer a verdade sobre o que existe hoje.
    '/livewire/update',
    '/livewire/message',
];

// Limite de itens no cache dinâmico (HTML páginas precisam de mais espaço para PWA offline)
const DYNAMIC_CACHE_LIMIT = 250;
const IMAGE_CACHE_LIMIT = 200;

// Rotas críticas do PWA Offline — usadas como fallback quando estamos sem rede
// e o utilizador navega para uma rota que ainda não foi cached.
const PWA_OFFLINE_FALLBACKS = [
    '/invoicing/offline/',
    '/invoicing/offline/index',
    // A entrada do PWA. Tem de estar guardada: quem sair sem rede ia parar a
    // um ecrã de erro, com a sessão do servidor ainda aberta e sem forma de
    // voltar a entrar no aparelho.
    '/invoicing/offline/login',
    '/invoicing/offline/pos',
    '/invoicing/offline/restaurant',
    '/invoicing/offline/catalog',
    '/invoicing/offline/clients',
    '/invoicing/offline/drafts',
];

// ========================
// INSTALL - Pré-cache
// ========================
/**
 * Guarda uma página do PWA — mas SÓ se for mesmo a página.
 *
 * `cache.add()` segue os redireccionamentos e guarda o que vier ao fim. Sem
 * sessão iniciada, `/invoicing/offline/pos` responde 302 para `/login`, e o
 * que ficaria guardado debaixo do URL do POS era o ECRÃ DE LOGIN. Depois,
 * offline, abrir o POS mostrava um formulário de entrada que não tem como
 * funcionar sem rede — pior do que a página de offline, porque parece que a
 * aplicação está a pedir credenciais e a recusá-las.
 *
 * Por isso: busca-se, verifica-se que não houve desvio, e só então se guarda.
 * Quando o service worker instala antes do login, não se guarda nada — e a
 * página fica em cache na primeira visita a sério, como sempre esteve.
 */
async function guardarPagina(cache, url) {
    try {
        const resposta = await fetch(url, { credentials: 'same-origin' });

        if (!resposta.ok || resposta.redirected || new URL(resposta.url).pathname !== url) {
            console.info('[SW] Página não pré-guardada (sem sessão ou desviada):', url);

            return;
        }

        await cache.put(url, resposta);
    } catch (err) {
        console.warn('[SW] Não foi possível pré-guardar a página:', url, err);
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => {
                // Um a um, e não com addAll.
                //
                // O addAll é tudo-ou-nada: basta UM dos URLs falhar — um CDN
                // com soluços, um bloqueio na rede da loja — e nada fica
                // guardado, nem sequer a página /offline e os ícones. O catch
                // que estava aqui salvava a instalação e perdia o cache
                // inteiro, silenciosamente. Assim, o que falha é só o que
                // falhou, e o resto fica.
                return Promise.all(PRECACHE_URLS.map((url) =>
                    cache.add(url).catch((err) => {
                        console.warn('[SW] Não foi possível pré-guardar:', url, err);
                    })
                ));
            })
            .then(() => caches.open(DYNAMIC_CACHE).then((cache) =>
                // As páginas vão para o cache das PÁGINAS, que é o mesmo de
                // onde o networkFirst as vai buscar quando não há rede. Guardá-
                // las no cache estático não servia de nada: o fallback nunca
                // olharia para lá.
                Promise.all(PRECACHE_PAGINAS.map((url) => guardarPagina(cache, url)))
            ))
        // NOTA: NÃO chamamos skipWaiting() aqui. O cliente decide quando
        // ativar a nova versão (ou o user clica "Atualizar agora").
    );
});

// Permitir que o cliente force a ativação imediata via postMessage
// e registar Background Sync quando solicitado pela app.
self.addEventListener('message', (event) => {
    if (!event.data) return;
    if (event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    if (event.data.type === 'REGISTER_SYNC') {
        try {
            self.registration.sync?.register('pwa-sync').catch(() => {});
        } catch (_) {}
    }
});

// ========================
// BACKGROUND SYNC API
// ========================
// Quando o browser recupera conectividade (mesmo com a página fechada),
// dispara 'sync' com a tag 'pwa-sync' e notificamos os clients abertos
// para executar window.SosPwa.sync(false). Se não houver client aberto,
// o navegador volta a tentar mais tarde automaticamente.
self.addEventListener('sync', (event) => {
    if (event.tag === 'pwa-sync') {
        event.waitUntil(notifyClientsToSync());
    }
});

/**
 * Sincronização periódica com a aplicação fechada (Chrome/Android, instalada).
 *
 * O que se faz aqui é refrescar as PÁGINAS guardadas, e não os dados.
 *
 * Escrever produtos e clientes na IndexedDB a partir daqui obrigava a repetir
 * toda a lógica de fusão que vive no pwa-invoicing.js — o bulkPut, os
 * removidos, o marcador last_sync — sem o Dexie e sem partilhar uma linha de
 * código com ela. Duas implementações da mesma fusão acabam sempre por
 * divergir, e a divergência aqui é stock e preços errados no balcão. Fica um
 * único sítio a escrever dados: a aplicação.
 *
 * O que isto resolve, e não é pouco: garante que o POS e as outras páginas
 * abrem mesmo que o servidor esteja em baixo há dias. Assim que abrem, a
 * sincronização de dentro trata dos dados.
 */
self.addEventListener('periodicsync', (event) => {
    if (event.tag === 'manter-catalogo') {
        event.waitUntil(refrescarPaginasGuardadas());
    }
});

async function refrescarPaginasGuardadas() {
    const cache = await caches.open(DYNAMIC_CACHE);

    // Uma a uma e com o erro engolido: sem rede, ou com o servidor em baixo,
    // isto não tem nada que fazer — e ficar tudo como está é o comportamento
    // certo. O que não pode é uma falha impedir as outras de serem tentadas.
    await Promise.all(PWA_OFFLINE_FALLBACKS.map(async (url) => {
        try {
            const resposta = await fetch(url, { credentials: 'same-origin' });

            if (podeSerGuardada(resposta)) {
                await cache.put(url, resposta.clone());
            }
        } catch (err) {
            // Sem rede. Fica o que já lá estava.
        }
    }));

    // Avisa quem estiver aberto, para os dados também virem.
    await notifyClientsToSync();
}

async function notifyClientsToSync() {
    const allClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: false });
    for (const client of allClients) {
        client.postMessage({ type: 'BG_SYNC' });
    }
}

// ========================
// ACTIVATE - Limpar caches antigos
// ========================
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => {
                            // O cache das páginas fica, venha a versão que vier:
                            // é a única coisa que sustenta o sistema quando o
                            // servidor não responde, e apagá-lo num deploy
                            // deixava os aparelhos sem nada.
                            if (name === DYNAMIC_CACHE) return false;

                            return !name.includes(CACHE_VERSION);
                        })
                        .map((name) => {
                            return caches.delete(name);
                        })
                );
            })
            .then(() => self.clients.claim())
    );
});

// ========================
// FETCH - Estratégias de cache
// ========================
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Ignorar requests não-GET
    if (request.method !== 'GET') return;

    // Ignorar chrome-extension, etc.
    if (!url.protocol.startsWith('http')) return;

    // Ignorar rotas que nunca devem ser cacheadas
    if (NEVER_CACHE.some((route) => url.pathname.startsWith(route))) return;

    // Ignorar Livewire message bus (tempo real)
    if (url.pathname.includes('/livewire/message')) return;

    // ---------- Estratégia por tipo ----------

    // 1) Imagens → Cache First
    if (isImageRequest(request)) {
        event.respondWith(cacheFirst(request, IMAGE_CACHE, IMAGE_CACHE_LIMIT));
        return;
    }

    // 2) CDN / Estáticos → Cache First
    if (isCDNRequest(url)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
        return;
    }

    // 3) Assets estáticos (CSS, JS, fonts) → Cache First
    if (isStaticAsset(url)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
        return;
    }

    // 4) Dados → Network First.
    //
    // Tem de vir ANTES das páginas: um pedido de dados também pode trazer
    // text/html no Accept, e caía no ramo errado. Com rede vem sempre o
    // valor de agora; o que está guardado só serve quando não há rede.
    if (API_ROUTES.some((rota) => url.pathname.startsWith(rota))) {
        event.respondWith(networkFirst(request));
        return;
    }

    // 5) Páginas HTML → Network First (com fallback offline)
    if (request.headers.get('Accept')?.includes('text/html')) {
        event.respondWith(networkFirst(request));
        return;
    }

    // 6) Tudo o resto → Stale While Revalidate
    event.respondWith(staleWhileRevalidate(request, DYNAMIC_CACHE, DYNAMIC_CACHE_LIMIT));
});

// ========================
// ESTRATÉGIAS DE CACHE
// ========================

/**
 * Cache First - Busca no cache, se não tiver vai à rede
 * Ideal para: imagens, CDN, assets estáticos
 */
async function cacheFirst(request, cacheName, limit) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
            if (limit) trimCache(cacheName, limit);
        }
        return response;
    } catch (err) {
        // Se for imagem, retornar placeholder
        if (isImageRequest(request)) {
            return new Response(getPlaceholderSVG(), {
                headers: { 'Content-Type': 'image/svg+xml' }
            });
        }
        return new Response('Offline', { status: 503 });
    }
}

/**
 * Network First - Tenta rede, fallback para cache
 * Ideal para: páginas HTML (conteúdo dinâmico)
 */
async function networkFirst(request) {
    let respostaDaRede = null;

    try {
        const response = await fetch(request);

        // Guardar SÓ o que é mesmo a aplicação. Uma sessão expirada devolve um
        // 302 para /login que acaba em 200: sem este teste, a página de login
        // ficava gravada no lugar do POS e passava a ser ela a aparecer offline.
        if (podeSerGuardada(response)) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
            trimCache(DYNAMIC_CACHE, DYNAMIC_CACHE_LIMIT);
            return response;
        }

        // Aqui estava a avaria que deixou toda a gente sem sistema no sábado.
        //
        // O `fetch` só REJEITA quando a rede falha por completo — DNS, ligação
        // recusada, sem rota. Um servidor de pé mas avariado (502 e 503 do
        // alojamento quando o PHP morre, 500 do Laravel) devolve uma resposta
        // com sucesso, e o antigo `return response` entregava a página de erro
        // ao utilizador. O recurso ao cache vivia só no catch, e o catch nunca
        // corria: o PWA tinha tudo guardado e não chegava a usá-lo.
        //
        // Agora uma resposta má vale o mesmo que não haver rede: procura-se no
        // cache primeiro, e a resposta má só sai daqui se não houver nada
        // guardado — porque aí é melhor mostrar o erro do servidor do que uma
        // página em branco.
        // SEM SESSÃO E COM REDE, MOSTRA-SE O LOGIN.
        //
        // Este é o único caso em que uma resposta não guardável ganha ao
        // cache. "O servidor está em baixo" e "tu não tens sessão" são coisas
        // diferentes: na primeira o cache salva o dia, na segunda o cache
        // esconde o problema — o POS aparece, parece funcionar, e a primeira
        // venda falha por não haver quem a assine.
        //
        // Sem rede a resposta nem chega aqui, e o offline continua a servir-se
        // do cache como sempre.
        if (ehIdaAoLogin(response)) {
            return response;
        }

        respostaDaRede = response;
    } catch (err) {
        // Sem rede nenhuma. Segue para o cache, como sempre seguiu.
    }

    try {
        // 1) Cache exacto da URL pedida
        const cached = await caches.match(request);
        if (cached) return cached;

        // 2) Se a URL pertence ao PWA Offline (ou às rotas legadas /dashboard, /pos que
        //    redirecionam para lá), tentar qualquer fallback PWA cached — serve o POS
        //    offline em vez de mostrar "Sem conexão".
        const url = new URL(request.url);
        const isPwaRoute = url.pathname.startsWith('/invoicing/offline')
            || url.pathname === '/dashboard'
            || url.pathname === '/pos';
        if (isPwaRoute) {
            // Preferir sempre o POS offline (página inicial do PWA)
            const pos = await caches.match('/invoicing/offline/pos');
            if (pos) return pos;
            for (const fallback of PWA_OFFLINE_FALLBACKS) {
                const hit = await caches.match(fallback);
                if (hit) return hit;
            }
        }

        // 3) Página /offline genérica (pré-cached em STATIC_CACHE)
        const offlinePage = await caches.match('/offline');
        if (offlinePage) return offlinePage;
    } catch (err) {
        // Uma falha a ler o cache não pode ser o fim: segue para o que resta.
    }

    // 4) Nada no cache. Se o servidor chegou a responder — mesmo que mal — é
    //    melhor mostrar o erro dele do que uma página em branco: quem está a
    //    ver percebe que o problema é do lado de lá.
    if (respostaDaRede) return respostaDaRede;

    // 5) Último recurso: HTML inline
    return new Response(getOfflineHTML(), {
        headers: { 'Content-Type': 'text/html' }
    });
}

/**
 * Isto é mesmo a aplicação, ou é uma página de erro ou de login?
 *
 * Só o que passa aqui vai para o cache — é o cache que sustenta o sistema
 * quando o servidor não está, e uma página de erro guardada lá dentro é pior
 * do que cache nenhum: fica a aparecer offline, e ninguém percebe porquê.
 */
/**
 * O servidor mandou-nos ao login?
 *
 * É o que acontece quando a sessão expira ou o aparelho nunca entrou. Com
 * rede, isto tem de chegar ao ecrã: é a única forma de a pessoa voltar a
 * entrar. Sem rede nunca se chega aqui.
 */
function ehIdaAoLogin(response) {
    if (!response) return false;

    if (response.type === 'opaqueredirect') return true;

    if (!response.redirected) return false;

    try {
        const destino = new URL(response.url).pathname;

        return destino.startsWith('/login') || destino.startsWith('/register');
    } catch (e) {
        return false;
    }
}

function podeSerGuardada(response) {
    if (!response || !response.ok) return false;

    // O `type: 'opaqueredirect'` e o `redirected` denunciam a ida ao /login.
    if (response.type === 'opaqueredirect') return false;

    if (response.redirected) {
        try {
            const destino = new URL(response.url).pathname;
            if (NEVER_CACHE.some((rota) => destino.startsWith(rota))) return false;
        } catch (e) {
            return false;
        }
    }

    return true;
}

/**
 * Stale While Revalidate - Retorna cache imediatamente e actualiza em background
 * Ideal para: dados que mudam mas não são críticos
 */
async function staleWhileRevalidate(request, cacheName, limit) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const fetchPromise = fetch(request)
        .then((response) => {
            if (response.ok) {
                cache.put(request, response.clone());
                if (limit) trimCache(cacheName, limit);
            }
            return response;
        })
        .catch(() => cached);

    return cached || fetchPromise;
}

// ========================
// HELPERS
// ========================

function isImageRequest(request) {
    const url = new URL(request.url);
    return (
        request.destination === 'image' ||
        /\.(png|jpg|jpeg|gif|svg|webp|ico|bmp)(\?.*)?$/i.test(url.pathname)
    );
}

function isCDNRequest(url) {
    return (
        url.hostname.includes('cdn.') ||
        url.hostname.includes('cdnjs.') ||
        url.hostname.includes('googleapis.com') ||
        url.hostname.includes('gstatic.com') ||
        url.hostname.includes('unpkg.com')
    );
}

function isStaticAsset(url) {
    return /\.(css|js|woff|woff2|ttf|eot)(\?.*)?$/i.test(url.pathname);
}

/**
 * Limitar tamanho do cache (FIFO)
 */
async function trimCache(cacheName, maxItems) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    if (keys.length > maxItems) {
        await cache.delete(keys[0]);
        trimCache(cacheName, maxItems);
    }
}

/**
 * Placeholder SVG para imagens offline
 */
function getPlaceholderSVG() {
    return `<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
        <rect fill="#e5e7eb" width="200" height="200" rx="8"/>
        <text fill="#9ca3af" font-family="Arial" font-size="14" text-anchor="middle" x="100" y="95">Sem conexão</text>
        <text fill="#d1d5db" font-family="Arial" font-size="28" text-anchor="middle" x="100" y="130">📷</text>
    </svg>`;
}

/**
 * HTML de emergência quando offline e sem cache
 */
function getOfflineHTML() {
    return `<!DOCTYPE html>
    <html lang="pt"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>SOS ERP - Offline</title>
    <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Arial,sans-serif;background:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;color:#1e293b}
    .container{text-align:center;padding:2rem}.icon{font-size:4rem;margin-bottom:1rem}.title{font-size:1.5rem;font-weight:bold;margin-bottom:.5rem}
    .msg{color:#64748b;margin-bottom:1.5rem}.btn{display:inline-block;padding:.75rem 1.5rem;background:#1e40af;color:#fff;border-radius:.5rem;text-decoration:none;font-weight:600}
    .btn:hover{background:#1e3a8a}</style></head>
    <body><div class="container"><div class="icon">📡</div><h1 class="title">Sem Conexão</h1>
    <p class="msg">Verifique a sua ligação à internet e tente novamente.</p>
    <a class="btn" href="javascript:location.reload()">Tentar Novamente</a></div></body></html>`;
}

// ========================
// PUSH NOTIFICATIONS (preparado)
// ========================
self.addEventListener('push', (event) => {
    const data = event.data?.json() || {};
    const title = data.title || 'SOS ERP';
    const options = {
        body: data.body || 'Nova notificação',
        icon: '/pwa/icon-192x192.png',
        badge: '/pwa/icon-72x72.png',
        vibrate: [100, 50, 100],
        data: { url: data.url || '/dashboard' },
        actions: data.actions || [],
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/dashboard';
    event.waitUntil(clients.openWindow(url));
});
