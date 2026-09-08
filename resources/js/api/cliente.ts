/**
 * A ponte para o Laravel.
 *
 * SESSÃO, NÃO TOKEN. O `ResolveApiToken` já aceita a sessão web quando não há
 * `Bearer` — por isso o React não precisa de guardar credencial nenhuma. Um
 * token no `localStorage` é um token que uma extensão do browser consegue ler;
 * o cookie de sessão é `httpOnly` e nenhum JavaScript lhe toca. O que o cookie
 * exige em troca é o CSRF, que vai daqui em cada escrita.
 */

/** O erro que o ecrã sabe apanhar, com o que o servidor disse mesmo. */
export class ErroDaApi extends Error {
    constructor(
        readonly estado: number,
        mensagem: string,
        readonly erros: Record<string, string[]> = {},
        /**
         * O CORPO INTEIRO da resposta de erro.
         *
         * Nem tudo o que o servidor recusa se explica com uma frase: um 409
         * pode vir com a morada do sítio onde a operação SE FAZ (estornar uma
         * venda é emitir uma nota de crédito, noutro ecrã). Sem isto, essa
         * indicação chegava e era deitada fora.
         */
        readonly corpo: Record<string, unknown> = {},
    ) {
        super(mensagem);
        this.name = 'ErroDaApi';
    }

    get eDePermissao(): boolean {
        return this.estado === 403;
    }

    /** A sessão morreu. Não é falta de rede — é preciso voltar a entrar. */
    get eSessaoMorta(): boolean {
        return this.estado === 401 || this.estado === 419;
    }
}

function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

const RAIZ = '/api/v1/invoicing/react';

type Parametros = Record<string, string | number | boolean | null | undefined>;

function comParametros(caminho: string, parametros?: Parametros): string {
    if (!parametros) {
        return RAIZ + caminho;
    }

    const q = new URLSearchParams();

    for (const [chave, valor] of Object.entries(parametros)) {
        // Um filtro vazio não viaja: `?estado=` faria o servidor validar uma
        // cadeia vazia em vez de entender «sem filtro».
        if (valor !== null && valor !== undefined && valor !== '') {
            q.set(chave, String(valor));
        }
    }

    const cauda = q.toString();

    return RAIZ + caminho + (cauda ? `?${cauda}` : '');
}

async function pedir<T>(caminho: string, opcoes: RequestInit = {}, parametros?: Parametros): Promise<T> {
    const resposta = await fetch(comParametros(caminho, parametros), {
        ...opcoes,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
            // Um FormData leva o seu próprio Content-Type, com a fronteira; só o JSON o declara aqui.
            ...(opcoes.body && !(opcoes.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
            ...opcoes.headers,
        },
    });

    if (!resposta.ok) {
        // Uma resposta de erro nem sempre é JSON: um 500 do PHP vem em HTML, e
        // tentar lê-lo como JSON esconderia o erro verdadeiro atrás de um
        // «Unexpected token <».
        let mensagem = `O servidor respondeu ${resposta.status}.`;
        let erros: Record<string, string[]> = {};
        let inteiro: Record<string, unknown> = {};

        try {
            const corpo = (await resposta.json()) as { message?: string; errors?: Record<string, string[]> };
            mensagem = corpo.message ?? mensagem;
            erros = corpo.errors ?? {};
            inteiro = corpo as Record<string, unknown>;
        } catch {
            /* não era JSON; fica a mensagem genérica */
        }

        throw new ErroDaApi(resposta.status, mensagem, erros, inteiro);
    }

    return (await resposta.json()) as T;
}

export const api = {
    ler: <T>(caminho: string, parametros?: Parametros) => pedir<T>(caminho, { method: 'GET' }, parametros),

    criar: <T>(caminho: string, corpo: unknown) =>
        pedir<T>(caminho, { method: 'POST', body: JSON.stringify(corpo) }),

    guardar: <T>(caminho: string, corpo: unknown) =>
        pedir<T>(caminho, { method: 'PUT', body: JSON.stringify(corpo) }),

    apagar: <T>(caminho: string) => pedir<T>(caminho, { method: 'DELETE' }),

    /** Um ficheiro: vai em multipart, e o browser é que põe o Content-Type. */
    enviar: <T>(caminho: string, corpo: FormData) =>
        pedir<T>(caminho, { method: 'POST', body: corpo }),
};

/** A forma de uma lista paginada, tal como o Laravel a devolve. */
export type Pagina<T> = {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
};
