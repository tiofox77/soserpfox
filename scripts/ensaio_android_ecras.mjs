/**
 * TODOS OS ECRÃS DO PWA NUM ANDROID, COM REDE E EM MODO AVIÃO.
 *
 * Conduz o Chrome do emulador (adb forward tcp:9222 localabstract:chrome_devtools_remote)
 * já com sessão aberta no PWA (ver ensaio_producao.mjs › entrar). Para cada
 * ecrã do menu de baixo, e para os dois formulários, abre a morada, espera a
 * casca montar e regista: o que se desenhou, se o limite de erro apanhou algum
 * ecrã, e os erros da consola. A fotografia é do ECRÃ DO APARELHO (adb
 * screencap), não do documento: é o que o empregado vê.
 *
 * O modo avião é o do sistema (adb), não o do browser: corta como corta a
 * quem está a vender, e o que se abre é o que o service worker guardou.
 *
 * Uso: ADB=<caminho do adb> FOTOS=<pasta> node scripts/ensaio_android_ecras.mjs [rede|aviao|ambos]
 */
import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';

const ADB = process.env.ADB || 'adb';
const PASTA = process.env.FOTOS || '.';
const modo = process.argv[2] || 'ambos';

const adb = (...args) => execFileSync(ADB, args, { maxBuffer: 64 * 1024 * 1024 });
const diz = (r, v) => console.log(String(r).padEnd(30), typeof v === 'object' ? JSON.stringify(v) : v);
const esperar = (ms) => new Promise((ok) => setTimeout(ok, ms));

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20000 });
const contexto = browser.contexts()[0];
const pagina = contexto.pages().find((p) => p.url().includes('soserp.vip'));

if (!pagina) {
    console.log('Não há separador do soserp.vip aberto no Chrome do aparelho.');
    process.exit(1);
}

await pagina.bringToFront();

let erros = [];
pagina.on('console', (m) => { if (m.type() === 'error') erros.push(m.text().slice(0, 200)); });
pagina.on('pageerror', (e) => erros.push('pageerror: ' + e.message.slice(0, 200)));

const props = await pagina.evaluate(() => JSON.parse(document.getElementById('pwa-raiz').dataset.props));
const ecras = [
    ...props.menu.map((m) => ({ chave: m.chave, url: m.url })),
    { chave: 'novo-cliente', url: props.rotas.novoCliente },
    { chave: 'novo-documento', url: props.rotas.novoDocumento },
];

diz('empresa', props.empresa?.nome ?? props.empresa);
diz('ecrãs a percorrer', ecras.map((e) => e.chave));

async function percorrer(rotulo) {
    for (const ecra of ecras) {
        erros = [];
        await pagina.goto(new URL(ecra.url, 'https://soserp.vip').href, { waitUntil: 'domcontentloaded', timeout: 45000 })
            .catch((e) => erros.push('goto: ' + e.message.slice(0, 120)));
        await pagina.waitForFunction(() => document.querySelector('#pwa-raiz')?.children.length > 0, null, { timeout: 30000 }).catch(() => {});
        await esperar(3000);

        const r = await pagina.evaluate(() => {
            const texto = document.body.innerText;
            const main = document.querySelector('main') ?? document.body;

            return {
                titulo: document.title,
                cabeca: (main.querySelector('h1, h2')?.textContent ?? '').trim().slice(0, 60),
                botoes: main.querySelectorAll('button').length,
                campos: main.querySelectorAll('input, select, textarea').length,
                limiteDeErro: /não conseguiu abrir|rebentou|Something went wrong/i.test(texto),
                semAcesso: /sem acesso/i.test(texto) && /não tem acesso|sem acesso/i.test(main.innerText),
                faixa: document.getElementById('pwa-status-bar')?.innerText.trim().slice(0, 80) ?? null,
                online: navigator.onLine,
            };
        }).catch((e) => ({ erro: e.message.slice(0, 120) }));

        const foto = `${PASTA}/android-${rotulo}-${ecra.chave}.png`;
        writeFileSync(foto, adb('exec-out', 'screencap', '-p'));

        diz(`${rotulo} · ${ecra.chave}`, { ...r, errosDaConsola: erros });
    }
}

if (modo === 'rede' || modo === 'ambos') {
    await percorrer('rede');
}

if (modo === 'aviao' || modo === 'ambos') {
    adb('shell', 'cmd', 'connectivity', 'airplane-mode', 'enable');
    await esperar(6000);

    try {
        await percorrer('aviao');
    } finally {
        adb('shell', 'cmd', 'connectivity', 'airplane-mode', 'disable');
    }
}

await browser.close().catch(() => {});
