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
    // O caixa sem direitos de gestão (bancada:pwa). Nunca entra com rede.
    caixa: { email: 'caixa@pwa.local', pin: '7391' },
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
 * ESCOLHE O CLIENTE OU O FORNECEDOR num editor de documentos.
 *
 * A escolha da outra parte é UM CONTROLO SÓ — uma caixa com procura que abre
 * uma lista por baixo (`EscolhaDaParte`). Não é um `<select>`: escreve-se,
 * aparecem os resultados, carrega-se num.
 *
 * A lista procura-se por `#lista-de-partes` e não por `getByRole('option')`:
 * esse apanha as opções de TODOS os `<select>` da página — armazém, série,
 * IEC, selo — e um editor tem quase duzentas.
 *
 * Devolve o nome de quem ficou escolhido.
 */
export async function escolherParte(page, rotulo = /^Cliente/) {
    const caixa = page.getByRole('combobox', { name: rotulo });

    await caixa.click();

    const lista = page.locator('#lista-de-partes');
    const primeiro = lista.getByRole('option').first();

    await primeiro.waitFor({ state: 'visible', timeout: 20_000 });

    const nome = (await primeiro.innerText()).split('\n')[0].trim();

    await primeiro.click();

    return nome;
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

/**
 * Navega, aguentando as navegações que o PWA provoca sozinho.
 *
 * O service worker assume o comando e a aplicação recarrega-se a seguir ao
 * primeiro sync. Um `goto` apanhado nesse instante morre com `ERR_ABORTED` —
 * falha do ensaio, não do produto. Tenta-se outra vez; se voltar a acontecer,
 * é porque a página está mesmo inacessível.
 */
export async function irPara(page, rota, tentativas = 3) {
    let ultimo;

    for (let i = 0; i < tentativas; i++) {
        try {
            const r = await page.goto(rota, { waitUntil: 'domcontentloaded' });
            await assentar(page);

            return r;
        } catch (erro) {
            ultimo = erro;
            const abortada = /ERR_ABORTED|Execution context was destroyed|frame was detached/i.test(erro.message);

            if (!abortada) {
                throw erro;
            }

            await page.waitForTimeout(600);
        }
    }

    throw ultimo;
}

/**
 * Avalia código na página, sobrevivendo a uma navegação a meio.
 *
 * Mesmo motivo do `irPara`: o contexto pode ser destruído debaixo dos pés.
 */
export async function avaliar(page, fn, arg = undefined, tentativas = 3) {
    let ultimo;

    for (let i = 0; i < tentativas; i++) {
        try {
            return await page.evaluate(fn, arg);
        } catch (erro) {
            ultimo = erro;

            if (!/Execution context was destroyed|frame was detached|Target closed/i.test(erro.message)) {
                throw erro;
            }

            await assentar(page);
        }
    }

    throw ultimo;
}

/** Espera que a página pare de se mexer sozinha. */
async function assentar(page) {
    try {
        await page.waitForLoadState('domcontentloaded', { timeout: 15_000 });
        // Meio segundo depois do DOM: é a janela em que a aplicação decide
        // recarregar-se. Esperar aqui poupa uma navegação a meio do ensaio.
        await page.waitForTimeout(500);
    } catch (_) {
        // Se o estado não assenta, quem chamou trata do erro seguinte.
    }
}

/** Lê uma tabela do IndexedDB do PWA. */
export async function lerBase(page, tabela) {
    return avaliar(page, (t) => window.SosPwa.db.table(t).toArray(), tabela);
}

/** Quantas linhas tem uma tabela. */
export async function contar(page, tabela) {
    return avaliar(page, (t) => window.SosPwa.db.table(t).count(), tabela);
}

/** A empresa que o aparelho julga ser a sua. */
export async function empresaLocal(page) {
    return avaliar(page, async () => (await window.SosPwa.db.meta.get('tenant_id'))?.value ?? null);
}

/**
 * Garante que há turno aberto — e aberto NO SERVIDOR, não só no aparelho.
 *
 * O restaurante recusa abrir comandas sem turno, e recusa-o do lado do
 * servidor: uma comanda que suba com o turno ainda por sincronizar leva 422 e
 * o ensaio falha por uma razão que não tem nada a ver com o que mede.
 */
export async function garantirTurno(page) {
    const jaAberto = await avaliar(page, async () => {
        const t = (await window.SosPwa.db.meta.get('shift'))?.value;

        return !!(t?.open && !t._local);
    });

    if (jaAberto) {
        return;
    }

    await avaliar(page, () => window.SosPwa.openShiftOffline({ opening_balance: 0 }));
    await sincronizar(page);

    await page.waitForFunction(
        async () => {
            const t = (await window.SosPwa.db.meta.get('shift'))?.value;

            return !!(t?.open && !t._local);
        },
        null,
        { timeout: 45_000 }
    );
}

/** Força uma sincronização e espera que termine. */
export async function sincronizar(page) {
    await avaliar(page, () => window.SosPwa.sync());
    await page.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 60_000 });
}
