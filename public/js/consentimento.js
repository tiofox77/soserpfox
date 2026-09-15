/**
 * O AVISO DE COOKIES — RGPD, Directiva ePrivacy, LGPD e Lei 22/11.
 *
 * Nada que não seja estritamente necessário corre antes de a pessoa escolher:
 * o Google Analytics, o Tag Manager e o Meta Pixel ficam nas páginas como
 * <script type="text/plain" data-consentimento="estatisticas|marketing"> e só
 * são activados aqui, depois de aceites. Recusar é tão fácil como aceitar
 * (um botão, ao lado do outro) e a escolha muda-se a qualquer momento por
 * qualquer ligação com [data-abrir-consentimento].
 *
 * A escolha vive num cookie curto e legível — «v2026-09-15.e1.m0» — que o
 * servidor também lê (App\Services\Privacidade\Consentimentos), e fica gravada
 * como prova em POST /api/analytics/consentimento. Mudar a versão no servidor
 * volta a perguntar a toda a gente.
 */
(function () {
    'use strict';

    var noEl = document.getElementById('sos-consentimento-config');
    if (!noEl || window.SosConsentimento) return;

    var CFG;
    try { CFG = JSON.parse(noEl.textContent || '{}'); } catch (e) { return; }

    var COOKIE = CFG.cookie || 'sos_consentimento';
    var VERSAO = CFG.versao;
    var DIAS = CFG.dias || 180;

    /* ── O cookie ─────────────────────────────────────────────────────── */

    function lerCookie(nome) {
        var partes = document.cookie ? document.cookie.split('; ') : [];
        for (var i = 0; i < partes.length; i++) {
            var p = partes[i].indexOf('=');
            if (partes[i].slice(0, p) === nome) return decodeURIComponent(partes[i].slice(p + 1));
        }
        return null;
    }

    function estado() {
        var m = /^v([0-9-]{10})\.e([01])\.m([01])$/.exec(lerCookie(COOKIE) || '');
        if (!m || m[1] !== VERSAO) return null;
        return { estatisticas: m[2] === '1', marketing: m[3] === '1' };
    }

    function gravar(escolha) {
        var valor = 'v' + VERSAO + '.e' + (escolha.estatisticas ? 1 : 0) + '.m' + (escolha.marketing ? 1 : 0);
        var expira = new Date(Date.now() + DIAS * 864e5).toUTCString();
        document.cookie = COOKIE + '=' + valor + '; expires=' + expira + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    }

    /** Recusar apaga o que já lá estava — senão recusar só valia para o futuro. */
    function apagarCookiesDe(categoria) {
        var nomes = categoria === 'estatisticas'
            ? [/^sos_vid$/, /^sos_utm_/, /^_ga($|_)/, /^_gid$/, /^_gat/]
            : [/^_fbp$/, /^fr$/, /^_gcl_/];
        var host = location.hostname;
        var dominios = ['', host, '.' + host, '.' + host.split('.').slice(-2).join('.')];

        (document.cookie ? document.cookie.split('; ') : []).forEach(function (c) {
            var nome = c.split('=')[0];
            if (!nomes.some(function (r) { return r.test(nome); })) return;
            dominios.forEach(function (d) {
                document.cookie = nome + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + (d ? '; domain=' + d : '');
            });
        });

        if (categoria === 'estatisticas') {
            try { sessionStorage.removeItem('sos_sid'); } catch (e) { /* sem acesso */ }
        }
    }

    /* ── Os scripts que esperam ─────────────────────────────────────────── */

    var activados = {};

    function activar(escolha) {
        ['estatisticas', 'marketing'].forEach(function (cat) {
            if (!escolha[cat] || activados[cat]) return;
            activados[cat] = true;
            document.querySelectorAll('script[type="text/plain"][data-consentimento="' + cat + '"]').forEach(function (velho) {
                var novo = document.createElement('script');
                if (velho.dataset.src) { novo.src = velho.dataset.src; novo.async = true; }
                else { novo.text = velho.text; }
                velho.parentNode.insertBefore(novo, velho.nextSibling);
            });
        });
    }

    function escolher(escolha, visitante) {
        var anterior = estado();
        gravar(escolha);
        if (!escolha.estatisticas) apagarCookiesDe('estatisticas');
        if (!escolha.marketing) apagarCookiesDe('marketing');
        activar(escolha);

        try {
            fetch(CFG.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    estatisticas: !!escolha.estatisticas,
                    marketing: !!escolha.marketing,
                    visitor_id: lerCookie('sos_vid') || visitante || null,
                }),
            }).catch(function () { /* a escolha já ficou no cookie */ });
        } catch (e) { /* idem */ }

        window.dispatchEvent(new CustomEvent('sos:consentimento', { detail: { escolha: escolha, anterior: anterior } }));
        fechar();

        // Retirar um consentimento com os scripts já carregados só se cumpre
        // recarregando: um script activo não se desliga.
        if (anterior && ((anterior.estatisticas && !escolha.estatisticas) || (anterior.marketing && !escolha.marketing))) {
            setTimeout(function () { location.reload(); }, 350);
        }
    }

    /* ── O desenho ────────────────────────────────────────────────────── */

    var ICONES = {
        cookie: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01M16 15.5v.01M12 12v.01M11 17v.01M7 14v.01"/></svg>',
        escudo: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
        grafico: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>',
        megafone: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>',
        fechar: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>',
    };
    var ICONE_DA_CATEGORIA = { necessarios: ICONES.escudo, estatisticas: ICONES.grafico, marketing: ICONES.megafone };

    var CSS = ''
        + '.sosc,.sosc *{box-sizing:border-box;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
        // A cor explícita: a página por trás pode ter texto branco (o site tem
        // faixas escuras), e os títulos herdavam-no — ficavam invisíveis.
        + '.sosc{color:#0f172a;text-align:left;line-height:1.4;letter-spacing:normal}.sosc h2,.sosc h3{color:#0f172a}'
        + '.sosc-aviso{position:fixed;z-index:2147483000;left:16px;right:16px;bottom:16px;max-width:560px;background:#fff;color:#0f172a;border-radius:18px;box-shadow:0 24px 60px -12px rgba(15,23,42,.45),0 0 0 1px rgba(15,23,42,.06);padding:20px;transform:translateY(24px);opacity:0;transition:transform .45s cubic-bezier(.2,.9,.3,1.2),opacity .35s ease}'
        + '.sosc-aviso.sosc-entra{transform:none;opacity:1}'
        + '.sosc-cabeca{display:flex;gap:12px;align-items:flex-start}'
        + '.sosc-bola{flex:none;display:grid;place-items:center;width:44px;height:44px;border-radius:14px;color:#fff;background:linear-gradient(135deg,#ea580c,#f59e0b);box-shadow:0 8px 18px -6px rgba(234,88,12,.7);animation:sosc-flutua 3s ease-in-out infinite}'
        + '@keyframes sosc-flutua{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-4px) rotate(-8deg)}}'
        + '.sosc h2{margin:0 0 4px;font-size:16px;font-weight:800;line-height:1.3}'
        + '.sosc p{margin:0;font-size:13.5px;line-height:1.55;color:#475569}'
        + '.sosc a{color:#1d4ed8;font-weight:600;text-decoration:underline;text-underline-offset:2px}'
        + '.sosc-botoes{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}'
        + '.sosc-b{flex:1 1 auto;min-width:140px;cursor:pointer;border:0;border-radius:12px;padding:11px 14px;font-size:13.5px;font-weight:700;transition:transform .15s ease,box-shadow .2s ease,background .2s ease}'
        + '.sosc-b:hover{transform:translateY(-1px)}.sosc-b:active{transform:none}'
        + '.sosc-b:focus-visible,.sosc-interruptor input:focus-visible+span{outline:3px solid #93c5fd;outline-offset:2px}'
        + '.sosc-sim{background:#1e3a8a;color:#fff;box-shadow:0 8px 18px -8px rgba(30,58,138,.8)}.sosc-sim:hover{background:#1e40af}'
        + '.sosc-nao{background:#e2e8f0;color:#0f172a}.sosc-nao:hover{background:#cbd5e1}'
        + '.sosc-mais{background:transparent;color:#1d4ed8;box-shadow:inset 0 0 0 2px #bfdbfe}.sosc-mais:hover{background:#eff6ff}'
        + '.sosc-fundo{position:fixed;inset:0;z-index:2147483001;background:rgba(15,23,42,.55);backdrop-filter:blur(3px);display:grid;place-items:center;padding:16px;opacity:0;transition:opacity .25s ease}'
        + '.sosc-fundo.sosc-entra{opacity:1}'
        + '.sosc-janela{width:100%;max-width:620px;max-height:calc(100vh - 32px);overflow:auto;background:#fff;border-radius:20px;padding:22px;box-shadow:0 30px 80px -20px rgba(15,23,42,.6);transform:scale(.96) translateY(10px);transition:transform .3s cubic-bezier(.2,.9,.3,1.2)}'
        + '.sosc-fundo.sosc-entra .sosc-janela{transform:none}'
        + '.sosc-topo{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:6px}'
        + '.sosc-x{cursor:pointer;border:0;background:#f1f5f9;color:#334155;width:36px;height:36px;border-radius:10px;display:grid;place-items:center;transition:background .2s}.sosc-x:hover{background:#e2e8f0}'
        + '.sosc-cat{margin-top:12px;border:1px solid #e2e8f0;border-radius:14px;padding:14px;transition:border-color .2s,box-shadow .2s}'
        + '.sosc-cat:hover{border-color:#bfdbfe;box-shadow:0 6px 16px -10px rgba(30,58,138,.35)}'
        + '.sosc-cat-cabeca{display:flex;align-items:center;gap:10px}'
        + '.sosc-cat-icone{flex:none;display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#eff6ff;color:#1d4ed8}'
        + '.sosc-cat[data-cat="necessarios"] .sosc-cat-icone{background:#ecfdf5;color:#047857}'
        + '.sosc-cat[data-cat="marketing"] .sosc-cat-icone{background:#fff7ed;color:#c2410c}'
        + '.sosc-cat h3{margin:0;font-size:14px;font-weight:800;flex:1}'
        + '.sosc-cat p{margin-top:6px}'
        + '.sosc-sempre{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#047857;background:#d1fae5;border-radius:999px;padding:3px 9px}'
        + '.sosc-interruptor{position:relative;display:inline-block;width:46px;height:26px;flex:none}'
        + '.sosc-interruptor input{position:absolute;opacity:0;width:100%;height:100%;margin:0;cursor:pointer}'
        + '.sosc-interruptor span{position:absolute;inset:0;border-radius:999px;background:#cbd5e1;transition:background .2s;pointer-events:none}'
        + '.sosc-interruptor span:after{content:"";position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 2px 4px rgba(0,0,0,.25);transition:transform .25s cubic-bezier(.2,.9,.3,1.3)}'
        + '.sosc-interruptor input:checked+span{background:#16a34a}.sosc-interruptor input:checked+span:after{transform:translateX(20px)}'
        + '.sosc details{margin-top:8px}.sosc summary{cursor:pointer;font-size:12.5px;font-weight:700;color:#1d4ed8}'
        + '.sosc table{width:100%;border-collapse:collapse;margin-top:6px;font-size:12px}'
        + '.sosc th,.sosc td{text-align:left;padding:5px 6px;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:top}.sosc th{color:#64748b;font-weight:700}'
        + '.sosc-tabela{overflow-x:auto}'
        + '@media (max-width:480px){.sosc-aviso{left:10px;right:10px;bottom:10px;padding:16px}.sosc-b{min-width:100%}}'
        + '@media (prefers-reduced-motion:reduce){.sosc-aviso,.sosc-fundo,.sosc-janela,.sosc-b,.sosc-interruptor span:after{transition:none}.sosc-bola{animation:none}}';

    function el(html) {
        var d = document.createElement('div');
        d.innerHTML = html.trim();
        return d.firstChild;
    }

    function texto(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    var aviso = null;
    var fundo = null;
    var focoAntes = null;

    function garantirEstilo() {
        if (document.getElementById('sosc-estilo')) return;
        var s = document.createElement('style');
        s.id = 'sosc-estilo';
        s.textContent = CSS;
        document.head.appendChild(s);
    }

    function mostrarAviso() {
        garantirEstilo();
        if (aviso) return;
        aviso = el(
            '<section class="sosc sosc-aviso" role="dialog" aria-live="polite" aria-labelledby="sosc-titulo">'
            + '<div class="sosc-cabeca"><span class="sosc-bola">' + ICONES.cookie + '</span><div>'
            + '<h2 id="sosc-titulo">' + texto(CFG.textos.titulo) + '</h2>'
            + '<p>' + texto(CFG.textos.frase) + ' <a href="' + texto(CFG.politica) + '">' + texto(CFG.textos.politica) + '</a> · <a href="' + texto(CFG.paginaCookies) + '">' + texto(CFG.textos.cookies) + '</a></p>'
            + '</div></div>'
            + '<div class="sosc-botoes">'
            + '<button type="button" class="sosc-b sosc-sim" data-accao="tudo">' + texto(CFG.textos.aceitar) + '</button>'
            + '<button type="button" class="sosc-b sosc-nao" data-accao="nada">' + texto(CFG.textos.recusar) + '</button>'
            + '<button type="button" class="sosc-b sosc-mais" data-accao="escolher">' + texto(CFG.textos.escolher) + '</button>'
            + '</div></section>'
        );
        aviso.addEventListener('click', function (e) {
            var b = e.target.closest('[data-accao]');
            if (!b) return;
            if (b.dataset.accao === 'tudo') escolher({ estatisticas: true, marketing: true });
            if (b.dataset.accao === 'nada') escolher({ estatisticas: false, marketing: false });
            if (b.dataset.accao === 'escolher') abrir();
        });
        document.body.appendChild(aviso);
        requestAnimationFrame(function () { requestAnimationFrame(function () { aviso && aviso.classList.add('sosc-entra'); }); });
    }

    function abrir() {
        garantirEstilo();
        if (fundo) return;
        focoAntes = document.activeElement;
        var actual = estado() || { estatisticas: false, marketing: false };

        var categorias = Object.keys(CFG.categorias).map(function (chave) {
            var c = CFG.categorias[chave];
            var linhas = (c.cookies || []).map(function (k) {
                return '<tr><td><code>' + texto(k.nome) + '</code></td><td>' + texto(k.finalidade) + '</td><td>' + texto(k.duracao) + '</td></tr>';
            }).join('');
            var controlo = chave === 'necessarios'
                ? '<span class="sosc-sempre">' + texto(CFG.textos.sempre) + '</span>'
                : '<label class="sosc-interruptor"><input type="checkbox" data-cat="' + chave + '"' + (actual[chave] ? ' checked' : '') + ' aria-label="' + texto(c.nome) + '"><span></span></label>';
            return '<div class="sosc-cat" data-cat="' + chave + '"><div class="sosc-cat-cabeca"><span class="sosc-cat-icone">' + (ICONE_DA_CATEGORIA[chave] || ICONES.escudo) + '</span><h3>' + texto(c.nome) + '</h3>' + controlo + '</div>'
                + '<p>' + texto(c.descricao) + '</p>'
                + (linhas ? '<details><summary>' + texto(CFG.textos.verCookies) + '</summary><div class="sosc-tabela"><table><thead><tr><th>' + texto(CFG.textos.nome) + '</th><th>' + texto(CFG.textos.finalidade) + '</th><th>' + texto(CFG.textos.duracao) + '</th></tr></thead><tbody>' + linhas + '</tbody></table></div></details>' : '')
                + '</div>';
        }).join('');

        fundo = el(
            '<div class="sosc sosc-fundo"><div class="sosc-janela" role="dialog" aria-modal="true" aria-labelledby="sosc-janela-titulo">'
            + '<div class="sosc-topo"><h2 id="sosc-janela-titulo">' + texto(CFG.textos.preferencias) + '</h2><button type="button" class="sosc-x" data-accao="fechar" aria-label="' + texto(CFG.textos.fechar) + '">' + ICONES.fechar + '</button></div>'
            + '<p>' + texto(CFG.textos.explica) + ' <a href="' + texto(CFG.politica) + '">' + texto(CFG.textos.politica) + '</a></p>'
            + categorias
            + '<div class="sosc-botoes">'
            + '<button type="button" class="sosc-b sosc-sim" data-accao="guardar">' + texto(CFG.textos.guardar) + '</button>'
            + '<button type="button" class="sosc-b sosc-nao" data-accao="nada">' + texto(CFG.textos.recusar) + '</button>'
            + '<button type="button" class="sosc-b sosc-mais" data-accao="tudo">' + texto(CFG.textos.aceitar) + '</button>'
            + '</div></div></div>'
        );

        fundo.addEventListener('click', function (e) {
            if (e.target === fundo) { fecharJanela(); return; }
            var b = e.target.closest('[data-accao]');
            if (!b) return;
            var accao = b.dataset.accao;
            if (accao === 'fechar') fecharJanela();
            if (accao === 'tudo') escolher({ estatisticas: true, marketing: true });
            if (accao === 'nada') escolher({ estatisticas: false, marketing: false });
            if (accao === 'guardar') {
                var v = {};
                fundo.querySelectorAll('input[data-cat]').forEach(function (i) { v[i.dataset.cat] = i.checked; });
                escolher({ estatisticas: !!v.estatisticas, marketing: !!v.marketing });
            }
        });
        fundo.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') fecharJanela();
        });

        document.body.appendChild(fundo);
        requestAnimationFrame(function () { requestAnimationFrame(function () { if (fundo) { fundo.classList.add('sosc-entra'); var f = fundo.querySelector('input,button'); f && f.focus(); } }); });
    }

    function fecharJanela() {
        if (!fundo) return;
        var f = fundo;
        fundo = null;
        f.classList.remove('sosc-entra');
        setTimeout(function () { f.remove(); }, 260);
        if (focoAntes && focoAntes.focus) focoAntes.focus();
    }

    function fechar() {
        fecharJanela();
        if (aviso) {
            var a = aviso;
            aviso = null;
            a.classList.remove('sosc-entra');
            setTimeout(function () { a.remove(); }, 400);
        }
    }

    /* ── Arranque ─────────────────────────────────────────────────────── */

    window.SosConsentimento = {
        estado: estado,
        abrir: abrir,
        permite: function (cat) { var e = estado(); return !!(e && e[cat]); },
    };

    document.addEventListener('click', function (e) {
        var a = e.target.closest('[data-abrir-consentimento]');
        if (!a) return;
        e.preventDefault();
        abrir();
    });

    function arrancar() {
        var e = estado();
        if (e) { activar(e); return; }
        if (!CFG.silencioso) mostrarAviso();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', arrancar);
    else arrancar();
})();
