import { test, expect } from '@playwright/test';
import { entrar, esperarServiceWorker, esperarMotor, esperarCatalogo, contar, lerBase, sincronizar } from './apoio.js';

/**
 * O ciclo completo: vender sem rede, voltar a ter rede, e o que foi vendido
 * chegar ao servidor uma vez só.
 *
 * É aqui que vivem os defeitos caros de um PWA. As duas perguntas:
 *   · o que ficou por enviar chega mesmo?
 *   · chega UMA vez, ou cria um documento por cada tentativa?
 */

async function aparelhoPreparado(page) {
    await entrar(page);
    await page.goto('/invoicing/offline');
    await esperarServiceWorker(page);
    await esperarMotor(page);
    await sincronizar(page);
    await esperarCatalogo(page, 5);
}

test.describe('PWA — a rede volta', () => {
    test('o que ficou por enviar sobe quando a rede volta', async ({ page, context }) => {
        await aparelhoPreparado(page);

        await context.setOffline(true);
        await page.goto('/invoicing/offline/clients');
        await esperarMotor(page);

        const nome = 'Cliente Offline ' + Date.now();
        await page.evaluate((n) => window.SosPwa.enqueue('create_client', {
            local_uuid: 'cli-' + Date.now(),
            name: n,
            nif: String(500000000 + Math.floor(Math.random() * 99999999)),
        }, false), nome);

        expect(await contar(page, 'sync_queue'), 'devia estar à espera').toBeGreaterThan(0);

        // A rede volta.
        await context.setOffline(false);
        await page.evaluate(() => window.dispatchEvent(new Event('online')));

        await expect
            .poll(
                async () => {
                    const fila = await lerBase(page, 'sync_queue');

                    return fila.filter((j) => j.status === 'pending').length;
                },
                { message: 'a fila tem de esvaziar quando a rede volta', timeout: 60_000 }
            )
            .toBe(0);
    });

    /**
     * A PERGUNTA QUE MAIS CUSTA: reenviar não pode criar um documento novo.
     *
     * Sem identificador local honrado pelo servidor, cada tentativa cria outro
     * cliente/venda — e uma rede instável tenta muitas vezes.
     */
    test('reenviar a mesma operação não cria dois registos', async ({ page, context }) => {
        await aparelhoPreparado(page);

        const uuid = 'idem-' + Date.now();
        const nif = String(500000000 + Math.floor(Math.random() * 99999999));

        // Envia duas vezes o MESMO identificador, como faria um reenvio.
        const primeiro = await page.evaluate(async ({ uuid, nif }) => {
            const r = await fetch('/api/v1/invoicing/clients', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ local_uuid: uuid, name: 'Idempotente', nif }),
            });

            return r.json();
        }, { uuid, nif });

        const segundo = await page.evaluate(async ({ uuid, nif }) => {
            const r = await fetch('/api/v1/invoicing/clients', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ local_uuid: uuid, name: 'Idempotente', nif }),
            });

            return r.json();
        }, { uuid, nif });

        expect(primeiro.id, 'o primeiro envio tem de criar').toBeTruthy();
        expect(segundo.id, 'o reenvio tem de devolver o MESMO registo').toBe(primeiro.id);
    });

    /**
     * Um trabalho carimbado com OUTRA empresa fica retido, não é enviado.
     *
     * Sem isto, uma venda feita offline para a empresa A ia parar aos livros
     * de B depois de o aparelho mudar de mãos.
     */
    test('um trabalho de outra empresa fica retido e não sobe', async ({ page }) => {
        await aparelhoPreparado(page);

        await page.evaluate(async () => {
            const empresa = (await window.SosPwa.db.meta.get('tenant_id'))?.value;

            await window.SosPwa.db.sync_queue.add({
                op: 'create_client',
                payload: { local_uuid: 'alheio-' + Date.now(), name: 'Da Outra Empresa', nif: '5111111111' },
                tenant_id: (Number(empresa) || 0) + 9999,   // uma empresa que não é esta
                created_at: new Date().toISOString(),
                retries: 0,
                status: 'pending',
            });
        });

        await sincronizar(page);

        const fila = await lerBase(page, 'sync_queue');
        const alheio = fila.find((j) => j.payload?.name === 'Da Outra Empresa');

        expect(alheio, 'o trabalho não pode desaparecer').toBeTruthy();
        expect(alheio.status, 'tem de ficar retido, não enviado').toBe('outra_empresa');
    });

    /** Uma recusa definitiva (4xx) não fica a ser tentada para sempre. */
    test('uma operação recusada não bloqueia a fila para sempre', async ({ page }) => {
        await aparelhoPreparado(page);

        await page.evaluate(async () => {
            const empresa = (await window.SosPwa.db.meta.get('tenant_id'))?.value;

            await window.SosPwa.db.sync_queue.add({
                op: 'create_client',
                // Sem nome: o servidor recusa por validação (422).
                payload: { local_uuid: 'invalido-' + Date.now() },
                tenant_id: empresa,
                created_at: new Date().toISOString(),
                retries: 0,
                status: 'pending',
            });
        });

        await sincronizar(page);

        const fila = await lerBase(page, 'sync_queue');
        const recusado = fila.find((j) => String(j.payload?.local_uuid || '').startsWith('invalido-'));

        expect(recusado, 'o trabalho recusado tem de continuar visível').toBeTruthy();
        expect(recusado.status, 'não pode ficar em "pending" a ser tentado para sempre').toBe('failed');
    });
});
