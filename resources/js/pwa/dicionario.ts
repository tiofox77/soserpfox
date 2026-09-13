import { definirDicionario } from '@/i18n';

/**
 * O DICIONÁRIO DO PWA — lido da própria página, ANTES de tudo o resto.
 *
 * Tem de ser o primeiro `import` do `pwa.tsx`: os módulos ES correm pela ordem
 * em que são importados, e um `t()` à cabeça de um módulo de ecrã que corresse
 * antes disto ficava em português para sempre.
 *
 * Vem dentro da página (e não por um pedido) porque sem rede esse pedido não
 * se faz, e a aplicação mudava de língua a meio de um turno.
 */
const no = typeof document !== 'undefined' ? document.getElementById('pwa-dicionario') : null;

if (no?.textContent) {
    try {
        definirDicionario(JSON.parse(no.textContent) as Record<string, string>);
    } catch (e) {
        console.warn('[PWA] dicionário ilegível — fica em português', e);
    }
}

export {};
