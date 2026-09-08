import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * TODAS AS PÁGINAS DA FACTURAÇÃO E DA TESOURARIA, UMA A UMA, NUM BROWSER A SÉRIO.
 *
 * Os outros specs provam o que cada ecrã FAZ. Este prova uma coisa mais
 * simples e que nenhum deles cobre: que **todas** as moradas do módulo abrem
 * — as 87, incluindo a tesouraria e as de editar um documento — que o React monta em cada
 * uma, e que nenhuma deixa erro na consola nem uma chamada à API falhada.
 *
 * É o varrimento que apanha o ecrã que ninguém abriu desde que mudou: um
 * `import` errado, uma prop que o servidor deixou de mandar, um 500 numa
 * rota da API que só aquele ecrã chama.
 *
 * A lista sai do servidor (`scratchpad/moradas-reais.php` escreve o JSON ao
 * lado deste ficheiro): assim uma rota nova entra no varrimento sem ninguém
 * se lembrar de a acrescentar aqui.
 */

const AQUI = dirname(fileURLToPath(import.meta.url));
const MORADAS = JSON.parse(readFileSync(join(AQUI, 'moradas-da-facturacao.json'), 'utf8'));

/**
 * Ruído que não é defeito do ecrã.
 *
 * O 404 do favicon e os avisos do service worker aparecem em qualquer página
 * e não dizem nada sobre a facturação.
 */
const RUIDO = [/favicon/i, /service worker/i, /manifest/i, /Download the React DevTools/i];

const ehRuido = (texto) => RUIDO.some((r) => r.test(texto));

test.describe('todas as páginas da facturação', () => {
    /*
     * NÃO É `serial` DE PROPÓSITO.
     *
     * Em série, a primeira morada que falhasse levava as outras 73 atrás dela
     * sem sequer as abrir — e um varrimento que pára na primeira pedra não é
     * um varrimento. Partilham a mesma página (entra-se uma vez) mas cada uma
     * responde por si.
     */
    let pagina;

    test.beforeAll(async ({ browser }) => {
        pagina = await browser.newPage();
        await entrar(pagina);
    });

    test.afterAll(async () => {
        await pagina?.close();
    });

    for (const morada of MORADAS) {
        test(`abre ${morada}`, async () => {
            const erros = [];
            const falhas = [];

            const naConsola = (m) => {
                if (m.type() === 'error' && !ehRuido(m.text())) erros.push(m.text());
            };
            const noErro = (e) => erros.push(String(e));
            const naResposta = (r) => {
                if (r.status() >= 400 && !ehRuido(r.url())) falhas.push(`${r.status()} ${r.url()}`);
            };

            pagina.on('console', naConsola);
            pagina.on('pageerror', noErro);
            pagina.on('response', naResposta);

            try {
                const resposta = await pagina.goto(morada, { waitUntil: 'domcontentloaded' });

                expect(resposta?.status(), `${morada}: o servidor respondeu ${resposta?.status()}`).toBeLessThan(400);

                // O ecrã tem de MONTAR: o ponto de montagem aparece logo no
                // HTML, por isso espera-se pelo que o React lá põe dentro.
                const ilha = pagina.locator('[data-ecra]').first();

                await expect(ilha, `${morada}: não montou ecrã React nenhum`).toBeVisible({ timeout: 30_000 });

                await expect
                    .poll(async () => (await ilha.innerText()).trim().length, {
                        timeout: 30_000,
                        message: `${morada}: a ilha ficou vazia — o ecrã não desenhou nada`,
                    })
                    .toBeGreaterThan(0);

                // E não ficou preso no esqueleto de carregamento.
                await expect
                    .poll(async () => await pagina.locator('[aria-busy="true"]').count(), { timeout: 30_000 })
                    .toBe(0);

                /*
                 * A TABELA TEM DE FECHAR: tantas células na linha quantas
                 * colunas no cabeçalho.
                 *
                 * Uma coluna acrescentada ao cabeçalho e esquecida na linha
                 * (ou o contrário) desalinha a tabela toda a partir dali — os
                 * valores passam a aparecer debaixo do título errado, que é
                 * pior do que faltarem. Media-se antes contando `<th>` no
                 * ficheiro; agora que o cabeçalho é desenhado de uma lista,
                 * mede-se onde importa: no que chegou ao ecrã.
                 */
                const tabelas = pagina.locator('[data-ecra] table');

                for (let i = 0; i < (await tabelas.count()); i++) {
                    // UMA TABELA DE CADA VEZ. Há mapas com duas (o do IVA tem
                    // as taxas e o resumo): somar os cabeçalhos das duas e
                    // compará-los com a linha de uma só dava um desalinho que
                    // não existe.
                    const tabela = tabelas.nth(i);
                    const cabecalhos = await tabela.locator('thead th').count();
                    const primeiraLinha = tabela.locator('tbody tr').first();

                    if (cabecalhos === 0 || !(await primeiraLinha.count())) continue;

                    // Uma linha de «não há nada» é uma célula só com `colspan`:
                    // não conta como desalinho.
                    const celulas = await primeiraLinha.locator('td').count();

                    if (celulas > 1) {
                        expect(celulas, `${morada}: tabela ${i + 1} com ${cabecalhos} colunas no cabeçalho e ${celulas} células na linha`).toBe(cabecalhos);
                    }
                }
            } finally {
                pagina.off('console', naConsola);
                pagina.off('pageerror', noErro);
                pagina.off('response', naResposta);
            }

            expect(erros, `${morada}: erros na consola`).toEqual([]);
            expect(falhas, `${morada}: chamadas falhadas`).toEqual([]);
        });
    }
});
