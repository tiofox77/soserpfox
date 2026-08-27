import { test, expect } from '@playwright/test';
import { entrar, esperarServiceWorker, esperarMotor, esperarCatalogo, garantirTurno, lerBase, sincronizar, irPara, avaliar } from './apoio.js';

/**
 * O POS de restaurante, sem rede.
 *
 * A diferença para o balcão é a MESA. Uma venda de balcão nasce e morre em
 * segundos; uma comanda fica aberta enquanto as pessoas comem, recebe mais
 * pratos, e só no fim vira documento. É esse tempo todo que o aparelho tem de
 * aguentar sem rede — e é nesse tempo que se perdem contas.
 *
 * Os ensaios seguem o serviço de uma sala: sentar, pedir, mandar à cozinha,
 * fechar a conta. Cada um começa com o aparelho JÁ sincronizado, que é o que
 * um empregado tem quando sai da cozinha com o tablet na mão.
 */

async function aparelhoPreparado(page) {
    await entrar(page);
    await irPara(page, '/invoicing/offline');
    await esperarServiceWorker(page);
    await esperarMotor(page);
    await sincronizar(page);
    await esperarCatalogo(page, 5);
    // Sem turno aberto no SERVIDOR, o restaurante recusa qualquer comanda.
    await garantirTurno(page);
}

/** A sala, tal como o aparelho a conhece. */
async function sala(page) {
    return avaliar(page, async () => ({
        salas: await window.SosPwa.db.rest_venues.count(),
        zonas: await window.SosPwa.db.rest_areas.count(),
        mesas: await window.SosPwa.db.rest_tables.count(),
        modulo: await window.SosPwa.temModulo('restaurant'),
    }));
}

test.describe('PWA — Restaurante', () => {
    /**
     * A ACTIVAÇÃO. O módulo tem de chegar ao aparelho, senão a entrada das
     * mesas não aparece e ninguém sabe porquê — nem se é falta de módulo, se
     * é defeito.
     */
    test('o módulo e a sala descem na sincronização', async ({ page }) => {
        await aparelhoPreparado(page);

        const s = await sala(page);

        expect(s.modulo, 'o aparelho tem de saber que a empresa tem restaurante').toBe(true);
        expect(s.salas, 'sem estabelecimento não há onde abrir comandas').toBeGreaterThan(0);
        expect(s.mesas, 'sem mesas a planta abre vazia').toBeGreaterThanOrEqual(6);
    });

    /**
     * O ENSAIO QUE IMPORTA: sem rede, o empregado abre a aplicação e tem sala.
     * Se isto falhar, o resto não interessa.
     */
    test('a sala abre sem rede', async ({ page, context }) => {
        await aparelhoPreparado(page);

        await context.setOffline(true);

        const resposta = await irPara(page, '/invoicing/offline/restaurant');

        expect(resposta, 'a navegação tem de devolver alguma coisa').not.toBeNull();
        await expect(page.locator('body')).not.toContainText('Sem Conexão à Internet');

        await esperarMotor(page);

        // E as mesas têm de estar desenhadas, não só o HTML da página.
        await page.waitForFunction(
            () => [...document.querySelectorAll('button')]
                .some((b) => /Mesa \d/.test(b.textContent || '')),
            null,
            { timeout: 30_000 }
        );
    });

    /**
     * Abrir uma comanda sem rede: a mesa ocupa-se NO APARELHO, de imediato.
     * Sem isto, dois empregados sentavam gente na mesma mesa e só descobriam
     * quando a rede voltasse.
     */
    test('sentar uma mesa sem rede ocupa-a já no aparelho', async ({ page, context }) => {
        await aparelhoPreparado(page);
        await context.setOffline(true);
        await irPara(page, '/invoicing/offline/restaurant');
        await esperarMotor(page);

        const resultado = await avaliar(page, async () => {
            const r = window.SosPwa.restaurante;
            const salaId = (await r.salas())[0].id;
            const livre = (await r.mesas(salaId)).find((m) => m.status === 'available');

            const comanda = await r.abrir({ venue_id: salaId, table_id: livre.id, guest_count: 2 });
            const depois = (await r.mesas(salaId)).find((m) => m.id === livre.id);

            return { mesa: livre.id, uuid: comanda.local_uuid, estado: depois.status, temComanda: !!depois.comanda };
        });

        expect(resultado.uuid, 'a comanda nasce com identificador próprio').toBeTruthy();
        expect(resultado.estado, 'a mesa tem de ficar ocupada de imediato').toBe('occupied');
        expect(resultado.temComanda, 'a mesa tem de mostrar a comanda que lhe pertence').toBe(true);
    });

    /** Os pratos entram na conta e os totais fazem-se no aparelho. */
    test('a conta soma sem rede, com imposto', async ({ page, context }) => {
        await aparelhoPreparado(page);
        await context.setOffline(true);
        await irPara(page, '/invoicing/offline/restaurant');
        await esperarMotor(page);

        const conta = await avaliar(page, async () => {
            const r = window.SosPwa.restaurante;
            const salaId = (await r.salas())[0].id;
            const comanda = await r.abrir({ venue_id: salaId, channel: 'counter' });

            await r.juntar(comanda.local_uuid, {
                product_id: -1, product_name: 'Ensaio', quantity: 3, unit_price: 1000, tax_rate: 14,
            });

            const c = await r.comanda(comanda.local_uuid);

            return { subtotal: c.subtotal, tax: c.tax, total: c.total };
        });

        // 3 × 1000 = 3000; IVA 14% = 420; total 3420.
        expect(conta.subtotal).toBeCloseTo(3000, 2);
        expect(conta.tax).toBeCloseTo(420, 2);
        expect(conta.total).toBeCloseTo(3420, 2);
    });

    /**
     * O mesmo prato pedido outra vez SOMA-SE. Uma comanda com "Cerveja 1"
     * repetida oito vezes não se lê — e é assim que se erra a conta ao dizê-la
     * em voz alta.
     */
    test('o mesmo prato pedido outra vez soma na mesma linha', async ({ page, context }) => {
        await aparelhoPreparado(page);
        await context.setOffline(true);
        await irPara(page, '/invoicing/offline/restaurant');
        await esperarMotor(page);

        const linhas = await avaliar(page, async () => {
            const r = window.SosPwa.restaurante;
            const salaId = (await r.salas())[0].id;
            const comanda = await r.abrir({ venue_id: salaId, channel: 'counter' });

            for (let i = 0; i < 3; i++) {
                await r.juntar(comanda.local_uuid, {
                    product_id: -2, product_name: 'Cerveja', quantity: 1, unit_price: 800, tax_rate: 14,
                });
            }

            const c = await r.comanda(comanda.local_uuid);

            return { linhas: c.items.length, quantidade: c.items[0].quantity };
        });

        expect(linhas.linhas).toBe(1);
        expect(linhas.quantidade).toBe(3);
    });

    /**
     * Um artigo já mandado à cozinha não se altera. Foi cozinhado: mexer aqui
     * seria mentir ao stock e à cozinha, e o servidor recusa na mesma.
     */
    test('o que já foi para a cozinha não se altera', async ({ page, context }) => {
        await aparelhoPreparado(page);
        await context.setOffline(true);
        await irPara(page, '/invoicing/offline/restaurant');
        await esperarMotor(page);

        const recusou = await avaliar(page, async () => {
            const r = window.SosPwa.restaurante;
            const salaId = (await r.salas())[0].id;
            const comanda = await r.abrir({ venue_id: salaId, channel: 'counter' });

            await r.juntar(comanda.local_uuid, {
                product_id: -3, product_name: 'Prato', quantity: 1, unit_price: 100, tax_rate: 0,
            });
            await r.mandarParaCozinha(comanda.local_uuid);

            const c = await r.comanda(comanda.local_uuid);

            try {
                await r.alterarQuantidade(comanda.local_uuid, c.items[0].local_uuid, 1);

                return false;
            } catch (_) {
                return true;
            }
        });

        expect(recusou, 'alterar um artigo já produzido tem de ser recusado').toBe(true);
    });

    /**
     * Mandar à cozinha sem rede põe a comanda na fila, carimbada com a
     * empresa — como qualquer outra operação fiscal do aparelho.
     */
    test('mandar à cozinha sem rede põe a comanda na fila', async ({ page, context }) => {
        await aparelhoPreparado(page);

        const empresa = await avaliar(page, async () => (await window.SosPwa.db.meta.get('tenant_id'))?.value);

        await context.setOffline(true);
        await irPara(page, '/invoicing/offline/restaurant');
        await esperarMotor(page);

        const uuid = await avaliar(page, async () => {
            const r = window.SosPwa.restaurante;
            const salaId = (await r.salas())[0].id;
            const comanda = await r.abrir({ venue_id: salaId, channel: 'counter' });
            const prato = await window.SosPwa.db.products.toCollection().first();

            await r.juntar(comanda.local_uuid, {
                product_id: prato.id, product_name: prato.name,
                quantity: 1, unit_price: prato.price, tax_rate: prato.tax_rate,
            });
            await r.mandarParaCozinha(comanda.local_uuid);

            return comanda.local_uuid;
        });

        const trabalho = (await lerBase(page, 'sync_queue'))
            .find((j) => j.op === 'sync_restaurant_order' && j.payload?.local_uuid === uuid);

        expect(trabalho, 'a comanda tem de entrar na fila').toBeTruthy();
        expect(trabalho.status).toBe('pending');
        expect(trabalho.tenant_id, 'sem carimbo pode ir para os livros de outra empresa').toBe(empresa);
    });

    /** Recarregar sem rede não pode perder a comanda que está aberta na mesa. */
    test('a comanda sobrevive a recarregar sem rede', async ({ page, context }) => {
        await aparelhoPreparado(page);
        await context.setOffline(true);
        await irPara(page, '/invoicing/offline/restaurant');
        await esperarMotor(page);

        const uuid = await avaliar(page, async () => {
            const r = window.SosPwa.restaurante;
            const salaId = (await r.salas())[0].id;
            const livre = (await r.mesas(salaId)).find((m) => m.status === 'available');
            const comanda = await r.abrir({ venue_id: salaId, table_id: livre.id });

            await r.juntar(comanda.local_uuid, {
                product_id: -4, product_name: 'Sobrevive', quantity: 2, unit_price: 500, tax_rate: 14,
            });

            return comanda.local_uuid;
        });

        await page.reload();
        await esperarMotor(page);

        const depois = await avaliar(page, (u) => window.SosPwa.restaurante.comanda(u), uuid);

        expect(depois, 'a comanda não pode desaparecer num recarregar').toBeTruthy();
        expect(depois.items.length).toBe(1);
        expect(depois.total).toBeCloseTo(1140, 2);
    });

    /**
     * O CICLO COMPLETO, que é o que interessa a quem tem o restaurante: fechar
     * a conta sem rede, a rede voltar, e o documento fiscal existir no servidor
     * com número — uma vez só.
     */
    test('a conta fechada sem rede sobe e recebe número fiscal', async ({ page, context }) => {
        await aparelhoPreparado(page);

        await context.setOffline(true);
        await irPara(page, '/invoicing/offline/restaurant');
        await esperarMotor(page);

        const uuid = await avaliar(page, async () => {
            const r = window.SosPwa.restaurante;
            const salaId = (await r.salas())[0].id;
            const livre = (await r.mesas(salaId)).find((m) => m.status === 'available');
            const comanda = await r.abrir({ venue_id: salaId, table_id: livre.id, guest_count: 2 });
            const prato = await window.SosPwa.db.products.toCollection().first();
            const metodo = ((await window.SosPwa.db.meta.get('payment_methods'))?.value || [])[0];

            await r.juntar(comanda.local_uuid, {
                product_id: prato.id, product_name: prato.name,
                quantity: 2, unit_price: prato.price, tax_rate: prato.tax_rate,
            });

            await r.receber(comanda.local_uuid, {
                document_type: 'FR',
                payment_method_id: metodo?.id || null,
            });

            return comanda.local_uuid;
        });

        await context.setOffline(false);

        // A espera volta a pedir sincronização a cada volta: uma sincronização
        // apanhada a meio devolve sem levar este trabalho, e o ensaio ficava a
        // olhar para uma fila que já ninguém ia buscar.
        await expect
            .poll(
                async () => {
                    const c = await avaliar(page, (u) => window.SosPwa.restaurante.comanda(u), uuid);

                    if (c?._invoice_number) {
                        return c._invoice_number;
                    }

                    await sincronizar(page).catch(() => {});

                    return null;
                },
                { message: 'a conta fechada sem rede tem de receber número fiscal', timeout: 60_000, intervals: [1000] }
            )
            .not.toBeNull();

        const comanda = await avaliar(page, (u) => window.SosPwa.restaurante.comanda(u), uuid);

        expect(comanda._server_number, 'a comanda tem de receber o número CMD- do servidor').toMatch(/^CMD-/);
    });

    /**
     * REENVIAR NÃO PODE CRIAR UMA SEGUNDA CONTA.
     *
     * O ensaio do servidor já o prova em PHP; este prova o caminho todo, com o
     * cliente a mandar duas vezes como manda uma rede instável.
     */
    test('reenviar a mesma comanda não cria uma segunda', async ({ page }) => {
        await aparelhoPreparado(page);
        await irPara(page, '/invoicing/offline/restaurant');
        await esperarMotor(page);

        const resultado = await avaliar(page, async () => {
            const prato = await window.SosPwa.db.products.toCollection().first();
            const salaId = (await window.SosPwa.restaurante.salas())[0].id;

            const corpo = {
                local_uuid: crypto.randomUUID(),
                venue_id: salaId,
                table_id: null,
                channel: 'counter',
                guest_count: 1,
                confirmar: true,
                items: [{
                    local_uuid: crypto.randomUUID(),
                    product_id: prato.id,
                    quantity: 1,
                    unit_price: prato.price,
                }],
            };

            const enviar = async () => {
                const r = await fetch('/api/v1/restaurant/offline/comanda', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(corpo),
                });

                return r.json();
            };

            return { primeiro: await enviar(), segundo: await enviar() };
        });

        expect(resultado.primeiro.id, 'o primeiro envio tem de criar a comanda').toBeTruthy();
        expect(resultado.segundo.id, 'o reenvio tem de devolver a MESMA comanda').toBe(resultado.primeiro.id);
        expect(resultado.segundo.order_number).toBe(resultado.primeiro.order_number);
    });
});

/**
 * O ecrã não pode cortar nada, em tamanho nenhum — a mesma regra do POS de
 * balcão, e pela mesma razão: o defeito não se vê num ecrã só.
 *
 * Os elementos procuram-se pelo estilo CALCULADO e não pelo nome da classe:
 * uma classe do Tailwind com dois pontos precisa de escape no selector, e um
 * ensaio que se parte por causa disso mede o escape, não o produto.
 */
const TAMANHOS = [
    { nome: 'telemóvel', w: 390, h: 844 },
    { nome: 'tablet', w: 820, h: 1180 },
    { nome: 'portátil', w: 1366, h: 768 },
];

for (const t of TAMANHOS) {
    test(`restaurante em ${t.nome} (${t.w}x${t.h}): nada cortado`, async ({ page }) => {
        await page.setViewportSize({ width: t.w, height: t.h });

        await aparelhoPreparado(page);
        await irPara(page, '/invoicing/offline/restaurant');
        await esperarMotor(page);

        // Entrar numa comanda: é aí que vive a grelha de pratos, que é onde o
        // POS de balcão cortava os cartões.
        await avaliar(page, async () => {
            const r = window.SosPwa.restaurante;
            const salaId = (await r.salas())[0].id;

            await r.abrir({ venue_id: salaId, channel: 'counter' });
        });

        await page.reload();
        await esperarMotor(page);

        await page.waitForFunction(
            () => [...document.querySelectorAll('button')]
                .some((b) => /Mesa \d/.test(b.textContent || '')),
            null,
            { timeout: 30_000 }
        );

        const m = await avaliar(page, () => {
            const visivel = (e) => !!(e && e.offsetParent !== null);

            const porPosicao = (pos, extra = () => true) =>
                [...document.querySelectorAll('body *')]
                    .filter(visivel)
                    .filter((e) => getComputedStyle(e).position === pos && extra(e));

            const nav = porPosicao('fixed', (e) => {
                const b = e.getBoundingClientRect();

                return b.height > 40 && b.bottom >= window.innerHeight - 2;
            })[0];

            const mesas = [...document.querySelectorAll('button')]
                .filter(visivel)
                .filter((b) => /Mesa \d/.test(b.textContent || ''));

            const primeira = mesas[0]?.getBoundingClientRect();

            return {
                mesas: mesas.length,
                larguraDaMesa: primeira ? Math.round(primeira.width) : 0,
                tapadaPelaNav: (primeira && nav)
                    ? Math.max(0, Math.round(primeira.bottom - nav.getBoundingClientRect().top))
                    : 0,
                scrollHorizontal: document.documentElement.scrollWidth > window.innerWidth,
            };
        });

        expect(m.mesas, 'as mesas têm de aparecer').toBeGreaterThan(0);
        expect(m.scrollHorizontal, 'nunca pode haver scroll horizontal').toBe(false);
        expect(m.larguraDaMesa, 'a mesa tem de continuar tocável com o dedo').toBeGreaterThanOrEqual(120);
        expect(m.tapadaPelaNav, 'a barra de navegação não pode tapar a primeira mesa').toBe(0);
    });
}
