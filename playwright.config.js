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

/**
 * Onde correr.
 *
 * Com `PWA_URL` definido, usa-se esse endereço e não se sobe servidor nenhum —
 * é o modo recomendado, apontado ao Apache do Laragon (`npm run pwa:test:apache`).
 *
 * PORQUÊ. O `artisan serve` é o servidor embutido do PHP e, no Windows, é
 * ESTRITAMENTE single-thread: o `PHP_CLI_SERVER_WORKERS` só existe em Unix. O
 * PWA dispara pedidos em paralelo (a página, o ping, o sync e o service worker
 * a pré-guardar cinco páginas), e um servidor de uma linha só serializa tudo —
 * a cortar a rede a meio, chega a morrer, e o ensaio seguinte apanha um
 * ERR_CONNECTION_REFUSED que não tem nada a ver com o produto. Foi o que
 * aconteceu. O Apache é multi-processo e aguenta.
 *
 * SEM `await` AQUI. Detectar o servidor sozinho exigia um `await` no topo do
 * ficheiro, e o carregador de configuração do Playwright fica pendurado com
 * isso: a corrida ficava a zero bytes de saída até ao tempo esgotar, sem uma
 * linha a dizer porquê. Um interruptor explícito vale mais do que magia que
 * não arranca.
 */
const servidor = process.env.PWA_URL
    ? { url: process.env.PWA_URL, proprio: false }
    : { url: 'http://127.0.0.1:8123', proprio: true };

export default defineConfig({
    testDir: './tests/browser',
    // Um de cada vez: os ensaios partilham a mesma empresa e o mesmo turno de
    // POS na base local. A correr em paralelo, um fecha o turno que o outro
    // ainda está a usar.
    workers: 1,
    fullyParallel: false,
    timeout: 90_000,
    expect: { timeout: 15_000 },
    // Uma repetição. Um PWA tem navegações que ele próprio provoca (recarrega-
    // se depois do primeiro sync) e há corridas que nenhuma espera apanha
    // sempre. Repetir uma vez distingue o que é frágil do que está partido —
    // mas uma só: duas escondiam um defeito a sério.
    retries: 1,
    reporter: [['list']],

    use: {
        baseURL: servidor.url,
        headless: true,
        // O PWA vive num telemóvel. Testá-lo numa janela de 1920 esconderia
        // exactamente os problemas que só aparecem no ecrã pequeno.
        viewport: { width: 412, height: 915 },
        ignoreHTTPSErrors: true,
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },

    projects: [
        {
            name: 'pwa',
            // Os ecrãs em React são de secretaria e correm no projecto abaixo.
            // Ficam de fora daqui para o `npm run pwa:test` continuar a correr
            // exactamente o que sempre correu.
            testIgnore: /react\..*\.spec\.js/,
            use: {
                ...devices['Pixel 5'],
                // Os service workers TÊM de correr: sem eles não há offline
                // nenhum e o ensaio não mede nada.
                serviceWorkers: 'allow',
            },
        },
        {
            /*
             * OS ECRÃS EM REACT, NUM ECRÃ DE SECRETÁRIA.
             *
             * A facturação faz-se sentado, num monitor — testá-la a 412 px
             * mediria um problema que ninguém tem. O PWA é que vive no
             * telemóvel, e esse continua no projecto de cima.
             */
            name: 'secretaria',
            testMatch: /react\..*\.spec\.js/,
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1440, height: 900 },
            },
        },
    ],

    // Só quando não há Apache. Porta própria, para não se enganar com o que
    // quer que esteja no 8000.
    ...(servidor.proprio
        ? {
            webServer: {
                command: 'php artisan serve --host=127.0.0.1 --port=8123',
                url: 'http://127.0.0.1:8123/up',
                reuseExistingServer: true,
                timeout: 60_000,
                stdout: 'ignore',
                stderr: 'pipe',
            },
        }
        : {}),
});
