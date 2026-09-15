/**
 * SOS Tracker — analytics frontend
 * Regista pageviews + cliques em CTAs (elementos com [data-track])
 */
(function () {
    'use strict';

    const ENDPOINT = '/api/analytics/track';
    const COOKIE_NAME = 'sos_vid';
    const SESSION_KEY = 'sos_sid';

    // A versão das regras de consentimento em vigor (vem do servidor).
    const VERSAO = (document.currentScript && document.currentScript.dataset.versao) || '';

    // ----- UUID v4 -----
    function uuid() {
        if (crypto?.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    // ----- Cookies -----
    function getCookie(name) {
        return document.cookie.split('; ').reduce((acc, c) => {
            const [k, v] = c.split('=');
            return k === name ? decodeURIComponent(v) : acc;
        }, null);
    }
    function setCookie(name, value, days = 365) {
        const exp = new Date(Date.now() + days * 86400000).toUTCString();
        document.cookie = `${name}=${value}; expires=${exp}; path=/; SameSite=Lax`;
    }

    // ----- Consentimento (RGPD / ePrivacy) -----
    //
    // Sem o «sim» às estatísticas não se guarda NADA no aparelho: nem o cookie
    // do visitante, nem as campanhas, nem o sessionStorage. A visita conta-se
    // com identificadores que vivem só nesta página e vai marcada `anonimo` —
    // o servidor não lhe guarda IP, cidade nem browser. Aceitar a meio da
    // página passa a contar a partir daí (evento do consentimento.js).
    function consentiu() {
        const m = /^v([0-9-]{10})\.e([01])\.m([01])$/.exec(getCookie('sos_consentimento') || '');
        return !!(m && (!VERSAO || m[1] === VERSAO) && m[2] === '1');
    }
    let comConsentimento = consentiu();

    // ----- IDs -----
    let visitorId;
    let sessionId;
    function prepararIds() {
        if (comConsentimento) {
            visitorId = getCookie(COOKIE_NAME) || visitorId || uuid();
            setCookie(COOKIE_NAME, visitorId);
            try {
                sessionId = sessionStorage.getItem(SESSION_KEY) || sessionId || uuid();
                sessionStorage.setItem(SESSION_KEY, sessionId);
            } catch (e) { sessionId = sessionId || uuid(); }
        } else {
            visitorId = visitorId || uuid();
            sessionId = sessionId || uuid();
        }
    }
    prepararIds();

    // ----- UTMs (da morada; guardadas 30d só com consentimento) -----
    const url = new URL(window.location.href);
    const utms = {};
    function lerUtms() {
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(k => {
            const v = url.searchParams.get(k);
            if (v) {
                utms[k] = v;
                if (comConsentimento) setCookie('sos_' + k, v, 30);
            } else if (comConsentimento) {
                const stored = getCookie('sos_' + k);
                if (stored) utms[k] = stored;
            }
        });
    }
    lerUtms();

    window.addEventListener('sos:consentimento', function (e) {
        comConsentimento = !!(e.detail && e.detail.escolha && e.detail.escolha.estatisticas);
        prepararIds();
        lerUtms();
    });

    // ----- CSRF token (se existir) -----
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ----- POST -----
    function send(payload) {
        try {
            const body = JSON.stringify({
                visitor_id: visitorId,
                session_id: sessionId,
                url: location.href.substring(0, 500),
                path: location.pathname,
                referrer: document.referrer.substring(0, 500),
                language: navigator.language,
                anonimo: !comConsentimento,
                ...utms,
                ...payload,
            });
            if (navigator.sendBeacon) {
                const blob = new Blob([body], { type: 'application/json' });
                navigator.sendBeacon(ENDPOINT, blob);
            } else {
                fetch(ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body,
                    keepalive: true,
                });
            }
        } catch (e) { /* silent */ }
    }

    // ----- Pageview automático -----
    function trackPageview() {
        send({ type: 'pageview', event_name: 'pageview' });
    }

    // ----- Quanto tempo a página esteve aberta -----
    //
    // Sem isto não se distingue quem leu a página de quem fechou o separador
    // ao segundo — que é a diferença entre uma visita e um engano. Enviado à
    // saída, por sendBeacon, que é o único envio que o browser garante
    // entregar quando a página está a fechar.
    //
    // O relógio pára quando o separador vai para trás: contar o tempo de uma
    // página esquecida numa aba aberta durante horas daria médias que não
    // querem dizer nada.
    let inicio = Date.now();
    let acumulado = 0;
    let visivel = !document.hidden;
    let jaEnviouSaida = false;

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            if (visivel) { acumulado += Date.now() - inicio; visivel = false; }
        } else {
            inicio = Date.now();
            visivel = true;
        }
    });

    function segundosNaPagina() {
        const total = acumulado + (visivel ? Date.now() - inicio : 0);
        return Math.min(Math.round(total / 1000), 86400);
    }

    function enviarSaida() {
        if (jaEnviouSaida) return;
        const s = segundosNaPagina();
        if (s < 1) return;
        jaEnviouSaida = true;
        send({ type: 'page_exit', event_name: 'page_exit', duration_seconds: s });
    }

    // `pagehide` e não `unload`: o `unload` não dispara em iOS nem quando a
    // página vai para a bfcache, e era aí que se perdia metade das saídas.
    window.addEventListener('pagehide', enviarSaida);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) enviarSaida();
    });

    // ----- Pesquisas -----
    //
    // Qualquer caixa marcada com [data-track-search] passa a alimentar os
    // "termos mais pesquisados". Regista-se quando a pessoa PÁRA de escrever
    // (ou carrega Enter), nunca por tecla: senão gravava-se "ami", "amid" e
    // "amido" como três pesquisas, e o painel mostrava pedaços de palavras.
    const PAUSA_MS = 1200;
    const MINIMO_LETRAS = 3;
    const temporizadores = new WeakMap();
    const ultimoEnviado = new WeakMap();

    function registarPesquisa(campo) {
        const termo = (campo.value || '').trim();
        if (termo.length < MINIMO_LETRAS) return;
        if (ultimoEnviado.get(campo) === termo) return;   // não repetir o mesmo
        ultimoEnviado.set(campo, termo);
        send({
            type: 'search',
            event_name: 'search_' + (campo.getAttribute('data-track-search') || 'site'),
            search_term: termo.substring(0, 150),
        });
    }

    document.addEventListener('input', function (e) {
        const campo = e.target.closest('[data-track-search]');
        if (!campo) return;
        clearTimeout(temporizadores.get(campo));
        temporizadores.set(campo, setTimeout(() => registarPesquisa(campo), PAUSA_MS));
    }, { capture: true, passive: true });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const campo = e.target.closest('[data-track-search]');
        if (!campo) return;
        clearTimeout(temporizadores.get(campo));
        registarPesquisa(campo);
    }, { capture: true });

    // ----- Listeners para cliques marcados -----
    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-track]');
        if (!el) return;
        const eventName = el.getAttribute('data-track');
        const meta = {};
        for (const attr of el.attributes) {
            if (attr.name.startsWith('data-track-')) {
                meta[attr.name.replace('data-track-', '')] = attr.value;
            }
        }
        send({
            type: 'cta_click',
            event_name: eventName,
            meta: {
                ...meta,
                text: (el.innerText || '').substring(0, 100),
                href: el.href || null,
            },
        });
    }, { capture: true, passive: true });

    // ----- Auto-track cliques em links importantes (registo, contacto) -----
    document.addEventListener('click', function (e) {
        const a = e.target.closest('a');
        if (!a) return;
        const href = a.getAttribute('href') || '';
        if (href.includes('/register')) {
            send({ type: 'cta_click', event_name: 'click_register', meta: { text: (a.innerText || '').substring(0, 80) } });
        } else if (href.startsWith('https://wa.me/')) {
            send({ type: 'cta_click', event_name: 'click_whatsapp' });
        } else if (href.startsWith('mailto:')) {
            send({ type: 'cta_click', event_name: 'click_email' });
        } else if (href.startsWith('tel:')) {
            send({ type: 'cta_click', event_name: 'click_phone' });
        } else if (href.startsWith('/modulos/')) {
            send({ type: 'cta_click', event_name: 'click_module', meta: { module: href.replace('/modulos/', '') } });
        } else if (href === '#planos' || href.includes('#planos')) {
            send({ type: 'cta_click', event_name: 'click_planos' });
        }
    }, { capture: true, passive: true });

    // ----- Fire ASAP -----
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', trackPageview);
    } else {
        trackPageview();
    }

    // Expose for manual tracking
    window.SosTracker = { send, get visitorId() { return visitorId; }, get sessionId() { return sessionId; } };
})();
