/**
 * O PWA num Android a sério, conduzido pelo Chrome do emulador.
 *
 * O `adb input` não serve para isto: um toque que caia dois pixels ao lado
 * escreve a palavra-passe no campo do email, e passa-se a depurar o teclado em
 * vez do produto. Ligamo-nos ao Chrome do aparelho por depuração remota
 * (`adb forward` + CDP) e conduzimo-lo como se fosse um browser local — mas é
 * mesmo o Chrome do Android, com o seu service worker e a sua cache.
 *
 * O servidor local chega ao aparelho por `adb reverse` como `localhost:8321`,
 * e `localhost` É contexto seguro — sem isso o Android nunca regista um
 * service worker e não há PWA nenhum para testar.
 *
 * Uso:  node scripts/pwa_android.mjs <passo>
 */
import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8321';
const EMAIL = 'bancada@pwa.local';
const PASSWORD = 'bancada-pwa-2026';
const PIN = '4321';

const passo = process.argv[2] || 'estado';

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222');
const contexto = browser.contexts()[0];
const pagina = contexto.pages().find((p) => p.url().includes('localhost:8321')) || contexto.pages()[0];

const diz = (rotulo, valor) => console.log(rotulo.padEnd(30), typeof valor === 'object' ? JSON.stringify(valor) : valor);

async function estado() {
    diz('url', pagina.url());
    diz('titulo', await pagina.title());

    const info = await pagina.evaluate(async () => {
        const r = {
            seguro: window.isSecureContext,
            sw: typeof navigator.serviceWorker !== 'undefined',
            controlador: !!navigator.serviceWorker?.controller,
            motor: typeof window.SosPwa,
        };

        if (window.SosPwa) {
            r.estadoDaLigacao = await window.SosPwa.estadoDaLigacao();
            r.destrancado = window.SosPwa.isPwaUnlocked();
            r.produtos = await window.SosPwa.db.products.count();
            r.funcionarios = await window.SosPwa.db.employees.count();
            r.fila = await window.SosPwa.db.sync_queue.where('status').equals('pending').count();
        }

        try {
            const c = await caches.open('dynamic-paginas');
            r.paginasGuardadas = (await c.keys()).map((k) => new URL(k.url).pathname);
        } catch (e) { r.paginasGuardadas = 'erro: ' + e.message; }

        return r;
    });

    for (const [k, v] of Object.entries(info)) diz(k, v);
}

async function entrar() {
    await pagina.goto(`${BASE}/invoicing/offline/login`, { waitUntil: 'domcontentloaded' });
    await pagina.waitForTimeout(2000);

    await pagina.fill('input[name="email"]', EMAIL);
    await pagina.fill('input[name="password"]', PASSWORD);
    await pagina.click('button[type="submit"]');
    await pagina.waitForTimeout(6000);

    diz('depois de entrar', pagina.url());
}

async function preparar() {
    // Sincronizar e passar pelo POS: e o que enche a cache e traz os PINs.
    await pagina.goto(`${BASE}/invoicing/offline`, { waitUntil: 'domcontentloaded' });
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 60000 });
    await pagina.evaluate(() => window.SosPwa.sync(true));
    await pagina.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 });

    await pagina.goto(`${BASE}/invoicing/offline/pos`, { waitUntil: 'domcontentloaded' });
    await pagina.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 60000 });
    await pagina.waitForTimeout(3000);

    await estado();
}

async function pin() {
    const r = await pagina.evaluate(async ([email, pin]) => {
        const res = await window.SosPwa.verifyOfflineAuth(email, pin);

        return { ok: res.ok, motivo: res.reason || null, destrancado: window.SosPwa.isPwaUnlocked() };
    }, [EMAIL, PIN]);

    diz('PIN', r);
}

/**
 * A QUEIXA, PASSO A PASSO: fechar a aplicação, cortar a rede, voltar a abrir,
 * pôr o PIN — e ter de aterrar no POS.
 *
 * Fechar o separador apaga o `sessionStorage`, que é onde vive o desbloqueio.
 * É por isso que volta a pedir o PIN, e isso está certo. O que se mede aqui é
 * o que acontece A SEGUIR.
 */
async function reabrir() {
    diz('1. antes de fechar', pagina.url());

    await pagina.close();

    const nova = await contexto.newPage();
    diz('2. aplicação fechada e reaberta', 'separador novo (sessionStorage vazio)');

    await nova.goto(`${BASE}/invoicing/offline/pos`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await nova.waitForTimeout(3000);

    const aoAbrir = await nova.evaluate(async () => ({
        titulo: document.title,
        caminho: location.pathname,
        semLigacao: document.body.innerText.includes('Sem Conexão'),
        pedePin: !!document.querySelector('#pwa-offline-login-password')?.offsetParent,
        destrancado: window.SosPwa?.isPwaUnlocked?.() ?? null,
        estado: window.SosPwa ? await window.SosPwa.estadoDaLigacao() : null,
    }));

    diz('3. ao reabrir', aoAbrir);

    const doPin = await nova.evaluate(async ([email, pin]) => {
        const r = await window.SosPwa.verifyOfflineAuth(email, pin);

        return { ok: r.ok, motivo: r.reason || null, destrancado: window.SosPwa.isPwaUnlocked() };
    }, [EMAIL, PIN]);

    diz('4. PIN', doPin);

    // O que faltava: chegar a algum lado depois do PIN.
    await nova.goto(`${BASE}/invoicing/offline/pos`, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await nova.waitForTimeout(3000);

    const depois = await nova.evaluate(async () => ({
        titulo: document.title,
        semLigacao: document.body.innerText.includes('Sem Conexão'),
        aindaPedePin: !!document.querySelector('#pwa-offline-login-password')?.offsetParent,
        artigosNaGrelha: document.querySelectorAll('.grid button').length,
        produtos: window.SosPwa ? await window.SosPwa.db.products.count() : null,
    }));

    diz('5. depois do PIN', depois);
    diz('VEREDICTO', depois.artigosNaGrelha > 0 && !depois.aindaPedePin ? 'ENTROU NO POS' : 'NAO ENTROU');
}

const passos = { estado, entrar, preparar, pin, reabrir };

if (!passos[passo]) {
    console.log('passos:', Object.keys(passos).join(', '));
} else {
    await passos[passo]();
}

await browser.close();
