/**
 * Ferramentas partilhadas pelos ensaios do PWA.
 *
 * Evitam repetir, em cada ficheiro, as três coisas que mais falham num ensaio
 * de PWA: entrar, esperar que o service worker esteja MESMO no comando, e
 * espreitar o IndexedDB.
 *
 * A base lê-se por `window.SosPwa.db`, o Dexie que o próprio motor abriu.
 * Abrir uma segunda ligação pelo nome ('SosErpInvoicing') funcionava até ao
 * dia em que houvesse uma migração de versão à espera: aí as duas ligações
 * bloqueiam-se uma à outra e o ensaio fica pendurado sem dizer porquê.
 */

export const CREDENCIAIS = {
    email: 'bancada@pwa.local',
    password: 'bancada-pwa-2026',
    pin: '4321',
};

/** Entra no sistema. */
export async function entrar(page) {
    await page.goto('/login');
    await page.fill('input[name="email"]', CREDENCIAIS.email);
    await page.fill('input[name="password"]', CREDENCIAIS.password);
    await page.click('button[type="submit"]');
    await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 30_000 });
}

/**
 * Espera que o service worker esteja a CONTROLAR a página.
 *
 * Registado não chega: na primeira visita instala mas só assume o controlo no
 * carregamento seguinte. Um ensaio que corte a rede antes disso mede uma
 * página sem service worker nenhum e falha por razões que não têm nada a ver
 * com o que se quer provar.
 */
export async function esperarServiceWorker(page) {
    await page.waitForFunction(
        () => navigator.serviceWorker && navigator.serviceWorker.controller !== null,
        null,
        { timeout: 45_000 }
    );
}

/** Espera que o motor offline tenha arrancado e aberto a base. */
export async function esperarMotor(page) {
    await page.waitForFunction(
        () => window.SosPwa && window.SosPwa.db && window.SosPwa.db.isOpen(),
        null,
        { timeout: 45_000 }
    );
}

/** Espera que o catálogo tenha descido para o IndexedDB. */
export async function esperarCatalogo(page, minimo = 1) {
    await page.waitForFunction(
        (min) => window.SosPwa?.db?.products.count().then((n) => n >= min),
        minimo,
        { timeout: 60_000 }
    );
}

/** Lê uma tabela do IndexedDB do PWA. */
export async function lerBase(page, tabela) {
    return page.evaluate((t) => window.SosPwa.db.table(t).toArray(), tabela);
}

/** Quantas linhas tem uma tabela. */
export async function contar(page, tabela) {
    return page.evaluate((t) => window.SosPwa.db.table(t).count(), tabela);
}

/** A empresa que o aparelho julga ser a sua. */
export async function empresaLocal(page) {
    return page.evaluate(async () => (await window.SosPwa.db.meta.get('tenant_id'))?.value ?? null);
}

/** Força uma sincronização e espera que termine. */
export async function sincronizar(page) {
    await page.evaluate(() => window.SosPwa.sync());
    await page.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 60_000 });
}
