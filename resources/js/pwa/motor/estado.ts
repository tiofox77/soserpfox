/**
 * O ESTADO DO MOTOR — e quem o está a ver.
 *
 * O motor antigo escrevia directamente no DOM: escondia e mostrava as barras
 * de «sem ligação», «a sincronizar», «sessão expirada». Aqui o motor só mexe
 * neste objecto e avisa; quem desenha é o ecrã (a barra de estado em React).
 *
 * O `state` continua a ser UM objecto mutável exposto em `window.SosPwa.state`
 * — é o que os ensaios e quem depura no telemóvel lêem.
 */
export interface EstadoDoMotor {
    online: boolean;
    /** Verificado pelo /ping — o `navigator.onLine` só diz que há placa de rede. */
    realOnline: boolean;
    syncing: boolean;
    lastSync: string | null;
    pendingCount: number;
    sessionExpired: boolean;
    subscriptionExpired: boolean;
    /** A última falha de sincronização, para a barra vermelha (sai sozinha). */
    erroDeSync: string | null;
    /** Trabalhos de outra empresa retidos neste aparelho. */
    retidos: number;
    /** A faixa verde «Sincronizado», que só fica dois segundos. */
    acabouDeSincronizar: boolean;
    /** Há convite de instalação à espera (Chrome) ou é um iPhone. */
    instalavel: boolean;
}

const ligado = typeof navigator !== 'undefined' ? navigator.onLine : true;

export const state: EstadoDoMotor = {
    online: ligado,
    realOnline: ligado,
    syncing: false,
    lastSync: null,
    pendingCount: 0,
    sessionExpired: false,
    subscriptionExpired: false,
    erroDeSync: null,
    retidos: 0,
    acabouDeSincronizar: false,
    instalavel: false,
};

type Ouvinte = () => void;

const ouvintes = new Set<Ouvinte>();
let fotografia: EstadoDoMotor = { ...state };

/** Avisa quem está a ver. Chama-se depois de mexer no `state`. */
export function notificar(): void {
    fotografia = { ...state };
    ouvintes.forEach((o) => {
        try { o(); } catch { /* um ecrã partido não pode parar o motor */ }
    });
}

export function subscrever(ouvinte: Ouvinte): () => void {
    ouvintes.add(ouvinte);

    return () => { ouvintes.delete(ouvinte); };
}

/** Uma cópia estável do estado (para o `useSyncExternalStore`). */
export function fotografiaDoEstado(): EstadoDoMotor {
    return fotografia;
}

/** Dispara um evento `pwa:*` na janela — a forma como os ecrãs sempre ouviram o motor. */
export function anunciar(nome: string, detalhe?: unknown): void {
    window.dispatchEvent(new CustomEvent(nome, { detail: detalhe }));
}

/**
 * A MIGRAÇÃO DO ARRANQUE, antes de qualquer sincronização.
 *
 * O arranque confere a versão do formato do catálogo e, se mudou, APAGA os
 * artigos para os voltar a descer inteiros. Isso tem de acontecer ANTES de
 * qualquer sincronização: o motor fica em `window.SosPwa` logo que o pacote
 * corre, e uma sincronização pedida nesse instante (o ecrã, o service worker,
 * um ensaio) descia o catálogo — e a migração, que ainda estava à espera da
 * base, apagava-o a seguir. O balcão ficava sem artigos e, sem rede, sem forma
 * de os ir buscar.
 *
 * Fora do arranque (ensaios do motor) não há migração e a espera é nula.
 */
let migracaoDoArranque: Promise<void> = Promise.resolve();

export function marcarMigracaoDoArranque(p: Promise<void>): void {
    migracaoDoArranque = p.catch(() => undefined);
}

export function esperarMigracaoDoArranque(): Promise<void> {
    return migracaoDoArranque;
}
