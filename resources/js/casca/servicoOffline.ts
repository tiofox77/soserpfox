import { avisar } from './avisos';

/**
 * O SERVICE WORKER — registo e actualização automática.
 *
 * Era o `partials/pwa-register` em linha no layout (e em cada página do PWA).
 * Agora é um só, para a aplicação web e para o PWA (`resources/js/pwa.tsx`).
 *
 * As regras que já tinham custado caro, e que se mantêm:
 *  • Sem contexto seguro não há service worker, e o browser recusa EM
 *    SILÊNCIO — diz-se alto no console.
 *  • Na PRIMEIRA instalação não se recarrega: o `clients.claim()` dispara o
 *    `controllerchange` também quando não havia controlador, e a página
 *    recarregava a meio do que se estava a escrever.
 *  • Versão nova aplica-se sozinha (SKIP_WAITING) e recarrega.
 */
let ligado = false;

/**
 * `avisos: false` é o PWA: lá a faixa do estado já diz «sem conexão» e
 * «sincronizado», e um aviso por cima dela repetia o mesmo duas vezes.
 */
export function ligarServicoOffline({ avisos = true }: { avisos?: boolean } = {}): void {
    if (ligado || !('serviceWorker' in navigator)) return;
    ligado = true;

    window.addEventListener('offline', () => {
        document.body.classList.add('app-offline');
        if (avisos) avisar('Sem conexão. A trabalhar em modo offline.', 'aviso', { titulo: 'Offline', duracao: 5000 });
    });
    window.addEventListener('online', () => {
        document.body.classList.remove('app-offline');
        try { localStorage.setItem('soserp-last-online', Date.now().toString()); } catch { /* sem armazenamento */ }
        if (avisos) avisar('Conexão restaurada!', 'ok', { titulo: 'Online', duracao: 3000 });
    });

    if (!window.isSecureContext) {
        console.warn('[PWA] Sem contexto seguro (%s). O browser NÃO regista o service worker aqui, e o modo offline não vai funcionar. Use https com certificado de confiança, ou http://localhost.', location.origin);
        return;
    }

    try { localStorage.setItem('soserp-last-online', Date.now().toString()); } catch { /* sem armazenamento */ }

    let controlada = !!navigator.serviceWorker.controller;
    let aRecarregar = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (!controlada) { controlada = true; return; }
        if (aRecarregar) return;
        aRecarregar = true;
        window.location.reload();
    });

    const aplicar = (reg: ServiceWorkerRegistration) => reg.waiting?.postMessage({ type: 'SKIP_WAITING' });
    const seguir = (worker: ServiceWorker, reg: ServiceWorkerRegistration) => worker.addEventListener('statechange', () => {
        if (worker.state === 'installed' && navigator.serviceWorker.controller) aplicar(reg);
    });

    const registar = () => navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then((reg) => {
            if (reg.waiting && navigator.serviceWorker.controller) aplicar(reg);
            if (reg.installing) seguir(reg.installing, reg);
            reg.addEventListener('updatefound', () => { if (reg.installing) seguir(reg.installing, reg); });
            setInterval(() => reg.update().catch(() => {}), 30 * 60 * 1000);

            // Sincronização periódica com a aplicação fechada — só Chrome/Android; falha em silêncio.
            const periodica = (reg as ServiceWorkerRegistration & { periodicSync?: { register: (tag: string, o: { minInterval: number }) => Promise<void> } }).periodicSync;
            if (periodica) {
                navigator.permissions?.query({ name: 'periodic-background-sync' as PermissionName })
                    .then((estado) => (estado.state === 'granted' ? periodica.register('manter-catalogo', { minInterval: 6 * 60 * 60 * 1000 }) : undefined))
                    .catch(() => {});
            }

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') reg.update().catch(() => {});
            });
        })
        .catch((erro) => console.warn('[PWA] Falha ao registar SW:', erro));

    if (document.readyState === 'complete') void registar();
    else window.addEventListener('load', () => void registar(), { once: true });
}
