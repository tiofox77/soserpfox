import { test, expect } from '@playwright/test';
import {
    CREDENCIAIS, entrar, esperarServiceWorker, esperarMotor,
    esperarCatalogo, sincronizar, irPara, avaliar,
} from './apoio.js';

/**
 * Ciclo de um telemóvel verdadeiro: prepara com internet, termina a sessão,
 * corta TODA a rede, autentica pelo PIN local, vende, reinicia, volta a ter
 * rede e envia exactamente a mesma venda.
 */
test('login, venda e sincronização depois de ficar totalmente offline', async ({ page, context }) => {
    await entrar(page);
    await irPara(page, '/invoicing/offline');
    await esperarServiceWorker(page);
    await esperarMotor(page);
    await sincronizar(page);
    await esperarCatalogo(page, 5);

    const funcionario = await avaliar(page, (email) => window.SosPwa.db.employees.get(email), CREDENCIAIS.email);
    expect(funcionario?.pin_hash, 'o PIN do operador tem de estar sincronizado no aparelho').toBeTruthy();

    // Termina a sessão Laravel sem apagar IndexedDB, tal como o botão Sair.
    await avaliar(page, async () => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const r = await fetch('/invoicing/offline/sair?da_fila=1', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        });
        if (!r.ok) throw new Error('Falhou ao terminar a sessão: ' + r.status);
    });

    await irPara(page, '/invoicing/offline/login');
    await context.setOffline(true);
    await page.reload();
    await esperarMotor(page);

    // Mesmo que o sistema operativo diga "Wi-Fi ligado", o operador tem uma
    // saída explícita para o PIN local.
    const alternar = page.getByRole('button', { name: 'Entrar com PIN offline' });
    const emailOffline = page.locator('input[type="email"]').last();
    await expect.poll(async () => (await alternar.isVisible()) || (await emailOffline.isVisible()), { timeout: 15_000 }).toBe(true);
    if (await alternar.isVisible()) await alternar.click();

    await emailOffline.fill(CREDENCIAIS.email);
    await page.locator('input[inputmode="numeric"]').fill(CREDENCIAIS.pin);
    await page.getByRole('button', { name: 'Entrar sem rede' }).click();
    await page.waitForURL(/\/invoicing\/offline\/pos/, { timeout: 20_000 });
    await esperarMotor(page);

    const uuid = await avaliar(page, async () => {
        const artigo = await window.SosPwa.db.products.toCollection().first();
        const criada = await window.SosPwa.createPosSaleOffline({
            payment_method: 'cash',
            amount_received: Number(artigo.price),
            items: [{
                product_id: artigo.id, product_name: artigo.name,
                quantity: 1, unit_price: Number(artigo.price), tax_rate: Number(artigo.tax_rate || 0),
            }],
        });

        return criada.local_uuid;
    });

    let venda = await avaliar(page, (id) => window.SosPwa.db.pos_sales.get(id), uuid);
    expect(venda?._synced).toBe(0);

    // Reiniciar offline não pode perder autenticação, venda ou fila.
    await page.reload();
    await esperarMotor(page);
    venda = await avaliar(page, (id) => window.SosPwa.db.pos_sales.get(id), uuid);
    expect(venda?._synced).toBe(0);

    // A rede regressa: por segurança, o login local não fabrica sessão de
    // servidor. Faz login normal e então sincroniza o documento pendente.
    await context.setOffline(false);
    await irPara(page, '/login');
    await page.fill('input[name="email"]', CREDENCIAIS.email);
    await page.fill('input[name="password"]', CREDENCIAIS.password);
    await page.click('button[type="submit"]');
    await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 30_000 });
    await irPara(page, '/invoicing/offline/pos');
    await esperarMotor(page);
    await sincronizar(page);

    await expect.poll(
        () => avaliar(page, async (id) => (await window.SosPwa.db.pos_sales.get(id))?._synced || 0, uuid),
        { timeout: 60_000, message: 'a venda offline tem de receber confirmação do servidor' },
    ).toBe(1);

    const final = await avaliar(page, (id) => window.SosPwa.db.pos_sales.get(id), uuid);
    expect(final._server_id).toBeTruthy();
    expect(final._server_number).toBeTruthy();
});
