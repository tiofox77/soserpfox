import { expect, test } from '@playwright/test';
import { entrar } from './apoio.js';

/**
 * O MÓDULO DOS EVENTOS — os oito ecrãs, no browser.
 *
 * As regras vivem nos ensaios de PHP (`tests/Feature/Eventos`): as transições
 * de estado, a capacidade do local, as permissões porta a porta, o número de
 * série único por empresa. O que aqui se prova é o que só se vê no browser:
 *
 *  · que as moradas abrem, e SEM UM ERRO NA CONSOLA — que neste módulo era o
 *    defeito de sempre: o calendário e os dois gráficos vinham de um CDN, e
 *    sem internet a página ficava um quadrado branco sem aviso nenhum;
 *  · que o CALENDÁRIO se desenha em casa, com as sete colunas da semana;
 *  · que o parque, os conjuntos e as categorias são TRÊS SEPARADORES do mesmo
 *    ecrã — eram três páginas com a mesma barra de navegação copiada em cima;
 *  · que o empréstimo distingue um cliente de um técnico da casa;
 *  · e que o tipo de evento mostra a cor, que é o que se lê no calendário.
 *
 * A bancada monta-se com `php artisan bancada:pwa`: um tipo, um local, um
 * técnico, um equipamento e um evento a meio do mês.
 */

/**
 * As moradas, e o título que o ECRÃ desenha (não o do layout).
 *
 * O cabeçalho da página vem do Blade e aparece mesmo que o React nunca monte.
 * Procurar o título DENTRO da ilha é o que distingue um ecrã que abriu de uma
 * página que ficou no esqueleto cinzento.
 */
const MORADAS = [
    ['/events/dashboard', 'Painel dos Eventos'],
    ['/events/calendar', 'Agenda de Eventos'],
    ['/events/reports', 'Relatórios de Eventos'],
    ['/events/equipment', 'Equipamentos'],
    ['/events/equipment/dashboard', 'Painel dos Equipamentos'],
    ['/events/equipment/sets', 'Equipamentos'],
    ['/events/equipment/categories', 'Equipamentos'],
    ['/events/venues', 'Locais'],
    ['/events/types', 'Tipos de Eventos'],
    ['/events/technicians', 'Técnicos'],
];

test.beforeEach(async ({ page }) => {
    await entrar(page);
});

/* ─── Todas abrem ───────────────────────────────────────────────────── */

test.describe('as moradas dos eventos', () => {
    for (const [morada, titulo] of MORADAS) {
        test(`abre ${morada}`, async ({ page }) => {
            const erros = [];

            page.on('console', (m) => { if (m.type() === 'error') erros.push(m.text()); });
            page.on('pageerror', (e) => erros.push(String(e)));

            const resposta = await page.goto(morada);

            expect(resposta?.status(), `${morada} respondeu ${resposta?.status()}`).toBeLessThan(400);

            await expect(
                page.locator('.ecra-react').getByRole('heading', { name: titulo }).first(),
            ).toBeVisible({ timeout: 20_000 });

            expect(erros, `consola de ${morada}`).toEqual([]);
        });
    }
});

/* ─── O painel ──────────────────────────────────────────────────────── */

test.describe('o painel', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/events/dashboard');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Painel dos Eventos' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * O PROGRESSO DO CHECKLIST é a novidade do painel.
     *
     * Um evento a três dias com o checklist a 20% é um problema; o mesmo a 90%
     * não é. O número estava na base de dados e não aparecia em ecrã nenhum.
     */
    test('a lista dos proximos mostra o progresso de cada um', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'Os próximos eventos' })).toBeVisible();
        await expect(page.getByText('Com o progresso do checklist à vista')).toBeVisible();
    });

    /** E o equipamento por devolver tem cartão próprio. */
    test('o equipamento por devolver tem cartao proprio', async ({ page }) => {
        await expect(page.getByText('Por devolver', { exact: true })).toBeVisible();
        await expect(page.getByText('O que não está cá para a próxima montagem')).toBeVisible();
    });
});

/* ─── A agenda ──────────────────────────────────────────────────────── */

test.describe('a agenda', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/events/calendar');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Agenda de Eventos' }))
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * O CALENDÁRIO É DESENHADO EM CASA.
     *
     * O ecrã antigo puxava o FullCalendar de um CDN: sem internet ficava um
     * quadrado branco, e com rede só aparecia depois de a biblioteca descer.
     * As sete colunas da semana são a prova de que a grelha é nossa.
     */
    test('o calendario desenha as sete colunas da semana', async ({ page }) => {
        for (const dia of ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']) {
            await expect(page.getByText(dia, { exact: true }).first()).toBeVisible({ timeout: 15_000 });
        }

        // E o evento da bancada está lá, pintado com a cor do tipo.
        await expect(page.getByRole('button', { name: /Conferência da Bancada/ }).first())
            .toBeVisible({ timeout: 15_000 });
    });

    /** As duas vistas do mesmo mês, no mesmo ecrã. */
    test('a lista e o calendario sao o mesmo ecra', async ({ page }) => {
        await page.getByRole('button', { name: 'Lista' }).click();

        await expect(page.getByPlaceholder('Número ou nome do evento…')).toBeVisible({ timeout: 15_000 });
    });

    /**
     * A JANELA DO EVENTO tem os campos todos — e os atalhos de criar o
     * cliente, o local e o tipo sem sair da marcação.
     */
    test('o formulario tem a montagem, a desmontagem e os atalhos', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo evento' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('O local tem capacidade, e ela conta')).toBeVisible();

        // Os campos que a montagem precisa e que a lista não mostra.
        await expect(modal.getByText('Montagem começa')).toBeVisible();
        await expect(modal.getByText('Desmontagem acaba')).toBeVisible();
        await expect(modal.getByText('Pessoas esperadas')).toBeVisible();

        // E a capacidade do local está à vista na própria ajuda do campo.
        await expect(modal.getByText('A capacidade do local recusa um evento que não caiba.')).toBeVisible();
    });
});

/* ─── Os equipamentos ───────────────────────────────────────────────── */

test.describe('os equipamentos', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/events/equipment');
        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Equipamentos' }).first())
            .toBeVisible({ timeout: 20_000 });
    });

    /**
     * TRÊS PÁGINAS PASSARAM A TRÊS SEPARADORES.
     *
     * O parque, os conjuntos e as categorias eram três moradas com a mesma
     * barra de navegação copiada no topo de cada uma — e a barra era a prova
     * de que pertenciam ao mesmo ecrã.
     */
    test('o parque os conjuntos e as categorias sao separadores', async ({ page }) => {
        await page.getByRole('tab', { name: 'Conjuntos' }).click();
        await expect(page.getByPlaceholder('Nome do conjunto…')).toBeVisible({ timeout: 15_000 });

        await page.getByRole('tab', { name: 'Categorias' }).click();
        await expect(page.getByRole('button', { name: 'Nova categoria' })).toBeVisible({ timeout: 15_000 });
    });

    /**
     * O NÚMERO DE SÉRIE é o campo que rebentava.
     *
     * A regra apontava para uma tabela que não existe, e gravar com número de
     * série dava erro de SQL. O campo está lá, e a ajuda diz a regra certa.
     */
    test('o formulario pede o numero de serie e diz a regra', async ({ page }) => {
        await page.getByRole('button', { name: 'Novo equipamento' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('O número de série é único dentro da empresa')).toBeVisible();
        await expect(modal.getByText('Único dentro da empresa — duas casas podem ter o mesmo aparelho.')).toBeVisible();
    });

    /**
     * EMPRESTAR É A DOIS, e o técnico da casa não paga aluguer do material da
     * casa: o preço por dia só aparece quando o destinatário é um cliente.
     */
    test('o emprestimo distingue o cliente do tecnico', async ({ page }) => {
        await page.getByRole('button', { name: 'Emprestar' }).first().click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('Preço por dia')).toBeVisible();

        await modal.getByRole('button', { name: 'A um técnico' }).click();

        await expect(modal.getByText('Preço por dia')).toHaveCount(0);

        // O destinatário passou a ser a lista dos técnicos. Procura-se pelo
        // CONTROLO e não pelo texto do rótulo: um campo obrigatório leva o
        // asterisco e o «(obrigatório)» para leitores de ecrã dentro do mesmo
        // elemento, e o texto exacto nunca bate certo.
        await expect(modal.getByRole('combobox', { name: /Técnico/ })).toBeVisible();
    });
});

/* ─── Os tipos ──────────────────────────────────────────────────────── */

test.describe('os tipos de evento', () => {
    /**
     * A COR VÊ-SE, e é a que pinta o calendário. A ORDEM sobe e desce em vez
     * de se escrever um número numa caixa — uma lista criada de enfiada fica
     * toda a zero, e trocar dois zeros não troca nada.
     */
    test('mostra a cor e a ordem sobe e desce', async ({ page }) => {
        await page.goto('/events/types');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Tipos de Eventos' }))
            .toBeVisible({ timeout: 20_000 });

        await expect(page.getByText('Sobe-se e desce-se — não se escrevem números')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Subir' }).first()).toBeVisible();
        await expect(page.getByRole('button', { name: 'Descer' }).first()).toBeVisible();
    });
});

/* ─── Os técnicos ───────────────────────────────────────────────────── */

test.describe('os técnicos', () => {
    /**
     * AS ESPECIALIDADES SÃO O QUE INTERESSA: um evento com transmissão precisa
     * de quem faça transmissão, e o ecrã filtra por elas.
     */
    test('filtram-se por especialidade e por nivel', async ({ page }) => {
        await page.goto('/events/technicians');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Técnicos' }))
            .toBeVisible({ timeout: 20_000 });

        await expect(page.getByRole('combobox', { name: 'Especialidade' })).toBeVisible();
        await expect(page.getByRole('combobox', { name: 'Nível' })).toBeVisible();
    });

    /** E a importação do RH não obriga a escrever a mesma pessoa duas vezes. */
    test('a importacao do rh esta a mao', async ({ page }) => {
        await page.goto('/events/technicians');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Técnicos' }))
            .toBeVisible({ timeout: 20_000 });

        await page.getByRole('button', { name: 'Importar do RH' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('Quem já está no pessoal e ainda não é técnico')).toBeVisible();
    });
});

/* ─── Os locais ─────────────────────────────────────────────────────── */

test.describe('os locais', () => {
    /** A capacidade é o campo que passou a valer alguma coisa. */
    test('a ficha pede a capacidade e diz para que serve', async ({ page }) => {
        await page.goto('/events/venues');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Locais' }))
            .toBeVisible({ timeout: 20_000 });

        await page.getByRole('button', { name: 'Novo local' }).click();

        const modal = page.getByRole('dialog');

        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.getByText('A capacidade é o que impede um evento que não cabe')).toBeVisible();
        await expect(modal.getByText('Quantas pessoas leva. O evento não passa daqui.')).toBeVisible();
    });
});

/* ─── Os relatórios ─────────────────────────────────────────────────── */

test.describe('os relatórios', () => {
    /**
     * OS DOIS BOTÕES QUE NÃO FAZIAM NADA DESAPARECERAM.
     *
     * «PDF» e «Excel» chamavam métodos que só diziam «ainda não existe» — um
     * botão que avisa que não faz nada continua a ser um botão a mais. Ficou o
     * CSV, que funciona.
     */
    test('so oferecem o csv, que existe mesmo', async ({ page }) => {
        await page.goto('/events/reports');

        await expect(page.locator('.ecra-react').getByRole('heading', { name: 'Relatórios de Eventos' }))
            .toBeVisible({ timeout: 20_000 });

        await expect(page.getByRole('link', { name: 'Descarregar CSV' })).toBeVisible();

        for (const morto of ['Exportar PDF', 'Exportar Excel']) {
            await expect(page.getByRole('button', { name: morto })).toHaveCount(0);
        }
    });
});
