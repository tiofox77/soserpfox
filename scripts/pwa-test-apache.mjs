/**
 * Corre a bancada do PWA contra o Apache do Laragon.
 *
 * Existe porque o `artisan serve` é single-thread no Windows e o PWA dispara
 * pedidos em paralelo — a página, o ping, o sync e o service worker a
 * pré-guardar cinco páginas. Um servidor de uma linha só serializa tudo e,
 * a cortar a rede a meio, chega a morrer; o ensaio seguinte apanha um
 * ERR_CONNECTION_REFUSED que não tem nada a ver com o produto.
 *
 * A detecção está AQUI e não na configuração do Playwright: um `await` no topo
 * do ficheiro de configuração deixa o carregador pendurado, sem uma linha a
 * dizer porquê.
 */

import { spawn } from 'node:child_process';

/**
 * HTTPS primeiro, e não por gosto: um service worker só arranca em CONTEXTO
 * SEGURO — HTTPS, ou `localhost`/`127.0.0.1`. Um `http://soserp.test` responde
 * 200 a tudo e o browser recusa-se a registar o service worker em silêncio; os
 * ensaios ficam 45 segundos à espera de um controlador que nunca chega e
 * falham todos, sem uma linha a explicar. Custou uma corrida inteira a
 * perceber.
 */
const CANDIDATOS = [
    process.env.PWA_URL,
    'https://soserp.test',
    'https://localhost/soserp/public',
    // Sem o 443 ligado: o playwright.config trata este endereço como seguro.
    'http://soserp.test',
].filter(Boolean);

async function responde(base) {
    try {
        // O certificado do Laragon é auto-assinado: sem isto o fetch recusa-o
        // e a detecção diz que o servidor não existe. O browser dos ensaios
        // ignora-o com `ignoreHTTPSErrors`.
        const r = await fetch(base + '/up', {
            signal: AbortSignal.timeout(3000),
            // eslint-disable-next-line no-undef
            dispatcher: undefined,
        });

        return r.ok;
    } catch (erro) {
        // Certificado auto-assinado dá um erro próprio: o servidor ESTÁ lá.
        if (/certificate|self.signed|UNABLE_TO_VERIFY/i.test(String(erro?.cause?.code || erro?.message))) {
            return true;
        }

        return false;
    }
}

const escolhido = [];
for (const base of CANDIDATOS) {
    if (await responde(base)) {
        escolhido.push(base);
        break;
    }
}

if (!escolhido.length) {
    console.error('');
    console.error('Nenhum servidor web respondeu.');
    console.error('Tentei: ' + CANDIDATOS.join(', '));
    console.error('');
    console.error('Ou liga o Laragon (Apache), ou corre `npm run pwa:test`,');
    console.error('que sobe um servidor próprio — mais lento e mais frágil no Windows.');
    console.error('');
    process.exit(1);
}

const base = escolhido[0];
console.log('Bancada do PWA contra ' + base + '\n');

const argumentos = ['playwright', 'test', '--project=pwa', ...process.argv.slice(2)];

const filho = spawn('npx', argumentos, {
    stdio: 'inherit',
    shell: true,
    env: { ...process.env, PWA_URL: base },
});

filho.on('exit', (codigo) => process.exit(codigo ?? 1));
