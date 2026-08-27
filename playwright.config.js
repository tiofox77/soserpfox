import { defineConfig, devices } from '@playwright/test';

/**
 * Bancada de ensaio do PWA — online e offline a sério.
 *
 * O `context.setOffline(true)` do Playwright corta a rede ao nível do
 * browser: os pedidos falham como falham no telemóvel de um vendedor sem
 * cobertura. É por isso que isto existe em vez de se mexer no
 * `navigator.onLine`, que só engana o código que o consulta e deixa os
 * `fetch` a passar.
 */
export default defineConfig({
    testDir: './tests/browser',
    // Um de cada vez: os ensaios partilham a mesma empresa e o mesmo turno de
    // POS na base local. A correr em paralelo, um fecha o turno que o outro
    // ainda está a usar.
    workers: 1,
    fullyParallel: false,
    timeout: 90_000,
    expect: { timeout: 15_000 },
    reporter: [['list']],

    use: {
        baseURL: process.env.PWA_URL || 'http://127.0.0.1:8123',
        // Sem headless não há como correr isto sem alguém a olhar.
        headless: true,
        // O PWA vive num telemóvel. Testá-lo numa janela de 1920 esconderia
        // exactamente os problemas que só aparecem no ecrã pequeno.
        viewport: { width: 412, height: 915 },
        ignoreHTTPSErrors: true,
        actionTimeout: 15_000,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },

    projects: [
        {
            name: 'pwa',
            use: {
                ...devices['Pixel 5'],
                // Os service workers TÊM de correr: sem eles não há offline
                // nenhum e o ensaio não mede nada.
                serviceWorkers: 'allow',
            },
        },
    ],

    // O servidor sobe com os ensaios e desce no fim. Porta própria, para não
    // se enganar com o que quer que esteja no 8000.
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8123',
        url: 'http://127.0.0.1:8123/up',
        reuseExistingServer: true,
        timeout: 60_000,
        stdout: 'pipe',
        stderr: 'pipe',
    },
});
