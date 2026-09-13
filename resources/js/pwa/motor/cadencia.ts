/**
 * A CADÊNCIA DA SINCRONIZAÇÃO AUTOMÁTICA.
 *
 * Os gatilhos automáticos só corriam quando havia coisas POR ENVIAR. Um posto
 * aberto o dia inteiro sem uma venda ficava com o catálogo do dia anterior — e
 * quando o servidor caísse, faltava exactamente o que tinha mudado.
 *
 * Duas cadências: a dos pendentes, rápida (uma venda por enviar é dinheiro
 * parado); a do catálogo, mais lenta (puxar produtos de dezenas de postos a
 * cada 45 s é carga sem retorno). A descarga é incremental — quase não custa.
 */
export const PENDENTES_A_CADA = 45 * 1000;
export const CATALOGO_A_CADA = 5 * 60 * 1000;
export const CATALOGO_AO_VOLTAR = 2 * 60 * 1000;

/** Há quanto tempo foi a última sincronização. Nunca, ou ilegível, conta como infinito. */
export function desdeAUltimaSync(lastSync: string | null, agora = Date.now()): number {
    if (!lastSync) return Infinity;

    const quando = new Date(lastSync).getTime();

    // Com NaN a comparação dava sempre falso e nunca mais se sincronizava.
    return Number.isNaN(quando) ? Infinity : agora - quando;
}

export function deveSincronizarNoTemporizador(estado: { syncing: boolean; pendingCount: number; lastSync: string | null }, agora = Date.now()): boolean {
    if (estado.syncing) return false;

    return estado.pendingCount > 0 || desdeAUltimaSync(estado.lastSync, agora) >= CATALOGO_A_CADA;
}

/** Ao voltar à aplicação: pendentes, ou um catálogo com mais de dois minutos. */
export function deveSincronizarAoVoltar(pendentes: number, lastSync: string | null, agora = Date.now()): boolean {
    return pendentes > 0 || desdeAUltimaSync(lastSync, agora) >= CATALOGO_AO_VOLTAR;
}
