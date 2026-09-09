/**
 * Corre os ensaios de BROWSER dos ecrãs em React contra o servidor que estiver
 * de pé.
 *
 * PORQUE É OUTRO SCRIPT E NÃO O DO PWA. O PWA precisa de HTTPS: um service
 * worker só arranca em contexto seguro, e sobre `http://soserp.test` o browser
 * recusa-se a registá-lo em silêncio. Os ecrãs em React NÃO TÊM service worker
 * nenhum — são páginas de secretária — e por isso HTTP serve-lhes tão bem
 * quanto HTTPS.
 *
 * Custou duas corridas inteiras a perceber: com o SSL do Laragon em baixo, o
 * `PWA_URL=https://soserp.test` dava `ERR_CONNECTION_REFUSED` em todos os
 * ensaios e o Playwright saía com «86 did not run» — uma mensagem que parece
 * um problema do produto e é um problema do vhost.
 */

import { spawn } from 'node:child_process';

/**
 * Por ordem de preferência: o que o utilizador mandar, depois HTTPS (que é o
 * que a casa usa), e por fim HTTP — que aqui basta.
 */
const CANDIDATOS = [
    process.env.PWA_URL,
    'https://soserp.test',
    'http://soserp.test',
].filter(Boolean);

async function responde(base) {
    try {
        const r = await fetch(base + '/up', { signal: AbortSignal.timeout(3000) });

        return r.ok;
    } catch (erro) {
        // Certificado auto-assinado dá um erro próprio: o servidor ESTÁ lá, e
        // o browser dos ensaios ignora-o com `ignoreHTTPSErrors`.
        if (/certificate|self.signed|UNABLE_TO_VERIFY/i.test(String(erro?.cause?.code || erro?.message))) {
            return true;
        }

        return false;
    }
}

let base = null;

for (const candidato of CANDIDATOS) {
    if (await responde(candidato)) {
        base = candidato;
        break;
    }
}

if (!base) {
    console.error('');
    console.error('Nenhum servidor web respondeu.');
    console.error('Tentei: ' + CANDIDATOS.join(', '));
    console.error('');
    console.error('Ligue o Laragon (Apache) e volte a tentar.');
    console.error('');
    process.exit(1);
}

console.log('Ecrãs em React contra ' + base + '\n');

const filho = spawn('npx', ['playwright', 'test', '--project=secretaria', ...process.argv.slice(2)], {
    stdio: 'inherit',
    shell: true,
    env: { ...process.env, PWA_URL: base },
});

filho.on('exit', (codigo) => process.exit(codigo ?? 1));
