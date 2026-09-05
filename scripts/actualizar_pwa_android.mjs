/**
 * Obriga o Android a ir buscar a versão nova do PWA — e prova que ele foi.
 *
 * PORQUE EXISTE. Descobriu-se, num deploy, que a correcção estava no servidor
 * e o telemóvel continuava a correr o motor de antes. A versão passou a sair
 * dos BYTES dos ficheiros e o `pwa:aparelhos` passou a dizer quem está
 * atrasado — mas faltava o outro lado da prova: que um aparelho atrasado
 * APANHA mesmo a versão nova quando volta a abrir.
 *
 * É o que isto faz. Diz a versão antes, força o service worker a actualizar-se,
 * recarrega, e diz a versão depois. Se as duas forem iguais, o mecanismo de
 * actualização não está a funcionar — e é melhor sabê-lo aqui do que quando
 * uma correcção urgente não chegar a quem vende.
 *
 * Uso: node scripts/actualizar_pwa_android.mjs
 */
import { chromium } from '@playwright/test';

const BASE = 'https://soserp.vip';

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20000 });
const contexto = browser.contexts()[0];
const pagina =
    contexto.pages().find((p) => p.url().includes('soserp.vip')) || contexto.pages()[0];

/** A versão que este aparelho está mesmo a correr: sai do `?v=` do próprio motor. */
const versaoDoAparelho = () =>
    pagina.evaluate(() => {
        const script = [...document.querySelectorAll('script[src]')].find((s) =>
            s.src.includes('pwa-invoicing.js')
        );

        return script ? new URL(script.src).searchParams.get('v') : null;
    });

/** A versão que o servidor está a servir agora. */
const versaoDoServidor = async () => {
    const sw = await pagina.evaluate(async (base) => {
        const r = await fetch(base + '/sw.js', { cache: 'no-store' });
        return r.text();
    }, BASE);

    return (sw.match(/CACHE_VERSION = 'soserp-([^']+)'/) || [])[1] ?? null;
};

console.log('URL actual:', pagina.url());

const antes = await versaoDoAparelho();
const servidor = await versaoDoServidor();

console.log('no aparelho:', antes);
console.log('no servidor:', servidor);

if (antes === servidor) {
    console.log('\nJá estava actualizado — nada a provar aqui.');
    process.exit(0);
}

// O service worker só troca de versão quando lhe pedem que se vá rever. Numa
// utilização normal isto acontece sozinho ao abrir a aplicação; aqui força-se
// para não estar à espera.
console.log('\nA pedir ao service worker que se actualize...');

const estado = await pagina.evaluate(async () => {
    const registos = await navigator.serviceWorker.getRegistrations();

    if (!registos.length) {
        return 'sem service worker registado';
    }

    for (const registo of registos) {
        await registo.update();
    }

    // Dar-lhe tempo para instalar a versão nova antes de recarregar.
    await new Promise((r) => setTimeout(r, 4000));

    const activo = await navigator.serviceWorker.getRegistrations();

    return activo
        .map((r) => `instalando=${!!r.installing} esperando=${!!r.waiting} activo=${!!r.active}`)
        .join(' | ');
});

console.log('service worker:', estado);

// Duas recargas: a primeira activa o novo service worker, a segunda já é
// servida por ele. É o que acontece a quem fecha e abre a aplicação.
for (let i = 1; i <= 2; i++) {
    await pagina.reload({ waitUntil: 'domcontentloaded', timeout: 60000 });
    await pagina.waitForTimeout(3000);
    console.log(`recarga ${i}: ${await versaoDoAparelho()}`);
}

const depois = await versaoDoAparelho();

console.log('\n─────────────────────────────────────────');
console.log('antes: ', antes);
console.log('depois:', depois);
console.log('servidor:', servidor);

if (depois === servidor) {
    console.log('\nAPANHOU. O aparelho está na versão do servidor.');
    process.exit(0);
}

console.log('\nNÃO APANHOU. Uma correcção deployada não chega a quem vende.');
process.exit(1);
