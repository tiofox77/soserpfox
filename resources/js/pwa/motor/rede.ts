import { db, lerMeta } from './base';
import { anunciar, notificar, state } from './estado';
import { csrf, uuidV4 } from './util';

/**
 * A REDE: o ping, a sessão, a subscrição e os pedidos ao servidor.
 */

export type EstadoDaLigacao = 'online' | 'sessao_expirada' | 'subscricao_expirada' | 'offline';

function ping(): Promise<Response> {
    return fetch('/api/v1/invoicing/ping', {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        signal: AbortSignal.timeout(5000),
    });
}

/**
 * Três estados, e não dois: `online`, `sessao_expirada`, `offline` (e a
 * subscrição, que é outra conversa).
 *
 * O `checkRealOnline()` responde sim ou não, e para sincronizar isso chega.
 * Mas o ECRÃ precisa da diferença, e misturá-las produziu esta avaria: a
 * sessão expira, o ping devolve 401, contava como «sem rede», a entrada
 * anunciava «entrada local» com o telemóvel cheio de sinal, o PIN conferia
 * (é um desbloqueio LOCAL), a aplicação saltava para o POS — e o pedido, que
 * vai pela rede que existe, apanhava o desvio do `auth` para /login. Com rede,
 * quem resolve uma sessão morta é a palavra-passe.
 */
export async function estadoDaLigacao(): Promise<EstadoDaLigacao> {
    if (!navigator.onLine) return 'offline';

    try {
        const r = await ping();

        if (r.status === 401 || r.status === 419) return 'sessao_expirada';

        // A EMPRESA DEIXOU DE PAGAR. Dizer «offline» aqui seria a mentira mais
        // cara: o aparelho continuava a vender convencido de que depois
        // sincronizava, e nada disso ia chegar a existir.
        if (r.status === 402) {
            handleSubscriptionExpired();

            return 'subscricao_expirada';
        }

        return r.ok ? 'online' : 'offline';
    } catch {
        return 'offline';
    }
}

export async function checkRealOnline(): Promise<boolean> {
    if (!navigator.onLine) {
        state.realOnline = false;

        return false;
    }

    try {
        const r = await ping();

        if (r.status === 401 || r.status === 419) {
            handleSessionExpired();
            state.realOnline = false;

            return false;
        }

        if (r.status === 402) {
            handleSubscriptionExpired();
            state.realOnline = false;

            return false;
        }

        state.realOnline = r.ok;

        return r.ok;
    } catch {
        state.realOnline = false;

        return false;
    } finally {
        notificar();
    }
}

/**
 * A sessão do Laravel expirou: tranca este separador para obrigar a entrar de
 * novo (com rede pela palavra-passe, sem rede pelo PIN). A barra laranja é o
 * ecrã que a desenha a partir do `state`.
 */
export function handleSessionExpired(): void {
    if (state.sessionExpired) return;

    state.sessionExpired = true;
    try { sessionStorage.removeItem('pwa_unlocked'); } catch { /* sem armazenamento */ }
    notificar();
    anunciar('pwa:session-expired');
}

/**
 * A empresa deixou de ter direito a emitir.
 *
 * Primo da sessão expirada, mas NÃO se resolve da mesma maneira: uma
 * palavra-passe não repõe um plano. E o que NÃO se faz aqui: apagar seja o que
 * for. As vendas na fila foram feitas e cobradas; ficam até a empresa renovar.
 */
export function handleSubscriptionExpired(): void {
    if (state.subscriptionExpired) return;

    state.subscriptionExpired = true;
    notificar();
    anunciar('pwa:subscricao-expirada');
}

/**
 * O identificador deste aparelho, nesta empresa. Nasce aqui e vive no
 * IndexedDB: limpar os dados do browser dá um aparelho novo — e é honesto.
 */
let _idDoAparelho: string | null = null;

export async function idDoAparelho(): Promise<string | null> {
    if (_idDoAparelho) return _idDoAparelho;

    try {
        const guardado = await lerMeta<string>('device_uuid');
        if (guardado) return (_idDoAparelho = guardado);

        const novo = uuidV4();
        await db.meta.put({ key: 'device_uuid', value: novo });

        return (_idDoAparelho = novo);
    } catch {
        return null;
    }
}

/**
 * A versão que este aparelho está MESMO a correr — a da página que o trouxe
 * (meta `pwa-versao`), e não uma constante escrita à mão. O servidor pode
 * servir uma versão nova e o aparelho continuar com a antiga em cache; foi
 * isso que aconteceu, e é esta diferença que o inventário de aparelhos mostra.
 */
function versaoEmUso(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="pwa-versao"]')?.content ?? '';
}

export async function cabecalhosDoAparelho(): Promise<Record<string, string>> {
    try {
        // Instalado no ecrã principal, ou só um separador? Um separador fecha-se
        // e volta actualizado; uma aplicação instalada pode ficar semanas igual.
        const instalado = window.matchMedia?.('(display-mode: standalone)')?.matches
            || (window.navigator as Navigator & { standalone?: boolean }).standalone === true;

        const plataforma = (navigator as Navigator & { userAgentData?: { platform?: string } }).userAgentData?.platform
            || navigator.platform || '';

        return {
            'X-Sos-Device': (await idDoAparelho()) || '',
            'X-Sos-Version': versaoEmUso(),
            'X-Sos-Standalone': instalado ? '1' : '0',
            'X-Sos-Platform': plataforma.slice(0, 60),
        };
    } catch {
        return {};
    }
}

/** Um erro do servidor, com o que o motor precisa para decidir se repete. */
export class ErroDoServidor extends Error {
    definitivo = false;
    status = 0;
}

export async function fetchJson<T = any>(url: string, opcoes: RequestInit = {}): Promise<T> {
    // Só a sincronização leva a identificação: é a chamada que todo o aparelho
    // faz, e pendurá-la em cada pedido só engordava cabeçalhos.
    const daIdentidade = url.includes('/invoicing/sync') ? await cabecalhosDoAparelho() : {};

    const resposta = await fetch(url, {
        credentials: 'same-origin',
        ...opcoes,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
            ...daIdentidade,
            ...((opcoes.headers as Record<string, string>) || {}),
        },
    });

    // 401 não autenticado, 419 CSRF expirado.
    if (resposta.status === 401 || resposta.status === 419) {
        handleSessionExpired();
        throw new Error(`SESSION_EXPIRED:${resposta.status}`);
    }

    // 402 — sem subscrição válida. Tem de sair ANTES do tratamento dos 4xx:
    // lá um 4xx é recusa DEFINITIVA e o trabalho é descartado. Aqui a venda é
    // boa; o que caducou foi o plano.
    if (resposta.status === 402) {
        handleSubscriptionExpired();
        throw new Error(`SUBSCRIPTION_EXPIRED:${resposta.status}`);
    }

    if (!resposta.ok) {
        const texto = await resposta.text();

        // Um 4xx (tirando 408, 409 e 429) é um pedido recusado pelo que ele É:
        // repetir cinco vezes não o compõe, só atrasa a fila que tem coisas
        // boas atrás. O 408 e o 429 dizem «agora não»; o 409 diz «o cliente a
        // que isto aponta ainda não existe cá» — a próxima sincronização
        // resolve.
        const erro = new ErroDoServidor(`HTTP ${resposta.status}: ${texto.substring(0, 200)}`);
        erro.status = resposta.status;
        erro.definitivo = resposta.status >= 400 && resposta.status < 500
            && ![408, 409, 429].includes(resposta.status);

        throw erro;
    }

    return resposta.json() as Promise<T>;
}
