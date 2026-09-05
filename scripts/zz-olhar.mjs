import { chromium } from '@playwright/test';
const b = await chromium.connectOverCDP('http://127.0.0.1:9222', { timeout: 20000 });
const ctx = b.contexts()[0];
const p = ctx.pages().find(x => x.url().includes('soserp.vip')) || ctx.pages()[0];
p.on('dialog', d => d.accept().catch(()=>{}));
await ctx.setOffline(false);

// Motor novo: forcar o SW a assumir
await p.evaluate(async () => {
    for (const r of await navigator.serviceWorker.getRegistrations()) {
        await r.update();
        if (r.waiting) r.waiting.postMessage({ type: 'SKIP_WAITING' });
    }
}).catch(()=>{});
await p.waitForTimeout(9000);
await p.goto('https://soserp.vip/invoicing/offline/pos', { waitUntil: 'domcontentloaded' });
await p.waitForFunction(() => window.SosPwa?.db?.isOpen(), null, { timeout: 45000 });
await p.waitForTimeout(2000);

await p.evaluate(() => window.SosPwa.sync(true));
await p.waitForFunction(() => window.SosPwa.state.syncing === false, null, { timeout: 90000 });

console.log(JSON.stringify(await p.evaluate(async () => ({
    aparelho: (await window.SosPwa.db.meta.get('device_uuid'))?.value?.slice(0, 12),
    versaoDoScript: [...document.querySelectorAll('script[src*=pwa-invoicing]')].map(s => new URL(s.src).searchParams.get('v'))[0],
    instalado: window.matchMedia?.('(display-mode: standalone)')?.matches ?? false,
}))));
await b.close();
