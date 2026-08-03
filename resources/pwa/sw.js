/**
 * SOS ERP - Service Worker
 * Cache imersivo + Offline parcial
 */

const CACHE_VERSION = 'soserp-v1.0.0';
const STATIC_CACHE = `static-${CACHE_VERSION}`;
const DYNAMIC_CACHE = `dynamic-${CACHE_VERSION}`;
const IMAGE_CACHE = `images-${CACHE_VERSION}`;
const API_CACHE = `api-${CACHE_VERSION}`;

// Recursos estáticos pré-cacheados (App Shell)
const PRECACHE_URLS = [
    '/offline',
    '/pwa/icon-192x192.png',
    '/pwa/icon-512x512.png',
    'https://cdn.tailwindcss.com',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-solid-900.woff2',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-regular-400.woff2',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
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

// Rotas de API/dados dinâmicos (cache curto)
const API_ROUTES = [
    '/api/',
    '/livewire/message/',
];

// Limite de itens no cache dinâmico (HTML páginas precisam de mais espaço para PWA offline)
const DYNAMIC_CACHE_LIMIT = 250;
const IMAGE_CACHE_LIMIT = 200;

// Rotas críticas do PWA Offline — usadas como fallback quando estamos sem rede
// e o utilizador navega para uma rota que ainda não foi cached.
const PWA_OFFLINE_FALLBACKS = [
    '/invoicing/offline/',
    '/invoicing/offline/index',
    '/invoicing/offline/pos',
    '/invoicing/offline/catalog',
    '/invoicing/offline/clients',
    '/invoicing/offline/drafts',
];

// ========================
// INSTALL - Pré-cache
// ========================
self.addEventListener('install', (event) => {
    console.log('[SW] Installing Service Worker v' + CACHE_VERSION);
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => {
                console.log('[SW] Pre-caching App Shell');
                return cache.addAll(PRECACHE_URLS).catch((err) => {
                    console.warn('[SW] Some precache items failed:', err);
                    // Não falhar se algum recurso CDN não carregar
                    return Promise.resolve();
                });
            })
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
    console.log('[SW] Activating Service Worker v' + CACHE_VERSION);
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => {
                            return !name.includes(CACHE_VERSION);
                        })
                        .map((name) => {
                            console.log('[SW] Deleting old cache:', name);
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

    // 4) Páginas HTML → Network First (com fallback offline)
    if (request.headers.get('Accept')?.includes('text/html')) {
        event.respondWith(networkFirst(request));
        return;
    }

    // 5) Tudo o resto → Stale While Revalidate
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
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
            trimCache(DYNAMIC_CACHE, DYNAMIC_CACHE_LIMIT);
        }
        return response;
    } catch (err) {
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

        // 4) Último recurso: HTML inline
        return new Response(getOfflineHTML(), {
            headers: { 'Content-Type': 'text/html' }
        });
    }
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
