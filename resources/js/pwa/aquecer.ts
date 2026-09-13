import type { PropsDoPwa } from './tipos';

/**
 * AQUECER O CACHE — pôr as páginas do PWA guardadas logo na primeira visita.
 *
 * O service worker pré-guarda as páginas na instalação, mas só as que
 * respondem com sessão: um aparelho que instalou o modo offline a partir da
 * ENTRADA (sem sessão) fica só com a entrada, e as outras respondem 302. Quem
 * depois entrasse e fosse vender para longe da cobertura encontrava a página
 * de «sem ligação» em vez do POS.
 *
 * Com a casca única, basta UMA página da aplicação no cache para desenhar
 * qualquer ecrã. Mesmo assim guardam-se todas: é o que faz cada endereço
 * servir-se a si próprio sem depender do recurso.
 *
 * DAR POR FEITO SÓ QUANDO ESTÁ MESMO FEITO. O antigo marcava-se como concluído
 * aconteça o que acontecer — o `allSettled` resolve com os 302 — e o aparelho
 * nunca mais tentava. Aqui confirma-se no cache antes de marcar. A marca leva
 * a ASSINATURA da versão do PWA: um deploy que mude o PWA aquece outra vez; um
 * que não lhe toque, não (era o `changelog.current`, que sobe com coisas que
 * nada têm a ver com o aparelho).
 */
export function aquecerCache(props: PropsDoPwa): void {
    if (!('serviceWorker' in navigator) || !('caches' in window) || !navigator.onLine) return;

    const chave = `soserp-pwa-aquecido-${props.versao.assinatura}`;
    try { if (localStorage.getItem(chave) === '1') return; } catch { /* sem armazenamento: tenta na mesma */ }

    const r = props.rotas;
    const paginas = [r.inicio, r.pos, r.restaurante, r.catalogo, r.clientes, r.novoCliente, r.documentos, r.novoDocumento, r.entrada, r.pinEsquecido];

    void navigator.serviceWorker.ready.then(() => {
        // Depois do arranque, para não competir com a primeira sincronização.
        setTimeout(async () => {
            // Com `Accept: text/html`: é por aí que o service worker reconhece um pedido
            // de PÁGINA e o guarda. Sem ele caía no «tudo o resto», que não guarda
            // HTML — o aquecimento corria e não ficava nada.
            await Promise.allSettled(paginas.map((u) => fetch(u, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'text/html' } })));

            try {
                const cache = await caches.open('dynamic-paginas');
                if (await cache.match(r.pos) || await cache.match(r.inicio)) {
                    localStorage.setItem(chave, '1');
                }
            } catch { /* fica por marcar, e tenta-se da próxima vez */ }
        }, 1500);
    });
}

/**
 * Esquece que o cache foi aquecido — para «Actualizar aplicação» e «Apagar
 * tudo» voltarem a guardar as páginas. Leva também as marcas do aquecimento
 * antigo (`soserp-pwa-warmed-*`), que ficaram nos aparelhos.
 */
export function esquecerAquecimento(): void {
    try {
        Object.keys(localStorage)
            .filter((k) => k.startsWith('soserp-pwa-aquecido-') || k.startsWith('soserp-pwa-warmed-'))
            .forEach((k) => localStorage.removeItem(k));
    } catch {
        // Sem armazenamento: não há marca para limpar.
    }
}
