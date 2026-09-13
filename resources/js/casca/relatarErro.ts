import { ErroDaApi } from '@/api/cliente';

/**
 * UM ECRÃ QUE REBENTA NO BROWSER DE ALGUÉM CHEGA AO SERVIDOR.
 *
 * Os erros do servidor já iam para `erros_do_sistema`, agrupados. Os do React
 * ficavam na consola de quem os teve: um botão que rebentava num balcão em
 * Luanda só se sabia quando alguém telefonava — e a fotografia do ecrã raramente
 * trazia a pilha. Vão para `/erros-do-browser`, que os escreve no log do
 * servidor, e daí para o mesmo registo agrupado.
 *
 * O que NÃO se manda: os erros da API (o servidor já os registou do lado dele,
 * e um 422 é uma validação, não um defeito), os de extensões do browser e o
 * «Script error.» sem pilha dos scripts de outros domínios. E nunca mais de
 * dez por página, nem o mesmo duas vezes.
 */

const ENDERECO = '/erros-do-browser';
const MAXIMO_POR_PAGINA = 10;
const enviados = new Set<string>();

export type RelatoDeErro = {
    mensagem: string;
    pilha?: string | null;
    ecra?: string | null;
    pilhaDoComponente?: string | null;
    origem: 'ecra' | 'janela' | 'promessa';
};

export function relatarErro(relato: RelatoDeErro): void {
    try {
        const mensagem = (relato.mensagem || '').slice(0, 500);

        if (!mensagem || enviados.size >= MAXIMO_POR_PAGINA) return;

        const chave = `${relato.ecra ?? ''}|${mensagem}`;
        if (enviados.has(chave)) return;
        enviados.add(chave);

        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        void fetch(ENDERECO, {
            method: 'POST',
            keepalive: true,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                mensagem,
                pilha: relato.pilha?.slice(0, 4000) ?? null,
                pilha_do_componente: relato.pilhaDoComponente?.slice(0, 2000) ?? null,
                ecra: relato.ecra ?? null,
                origem: relato.origem,
                endereco: window.location.pathname + window.location.search,
            }),
        }).catch(() => undefined);
    } catch {
        // Relatar um erro nunca pode ser outro erro.
    }
}

/** Um erro que vem de fora do sistema (extensão, script de outro domínio). */
function eDeFora(pilha: string | undefined, ficheiro?: string): boolean {
    const texto = `${ficheiro ?? ''}\n${pilha ?? ''}`;

    return /chrome-extension:|moz-extension:|safari-extension:/.test(texto)
        || (!!ficheiro && !ficheiro.startsWith(window.location.origin));
}

let ligado = false;

/** Os erros que nenhum ecrã apanhou: os de um clique, de um temporizador, de uma promessa. */
export function ligarRelatoDeErros(): void {
    if (ligado || typeof window === 'undefined') return;
    ligado = true;

    window.addEventListener('error', (e) => {
        if (!e.error || e.message === 'Script error.' || eDeFora(e.error?.stack, e.filename)) return;

        relatarErro({ mensagem: e.message, pilha: e.error?.stack, origem: 'janela' });
    });

    window.addEventListener('unhandledrejection', (e) => {
        const razao = e.reason;

        // Pela classe e pela forma: um pedaço carregado à parte pode trazer a
        // sua cópia da classe, e aí o `instanceof` já não a reconhece.
        if (razao instanceof ErroDaApi || typeof razao?.estado === 'number' || razao?.name === 'AbortError') return;

        const erro = razao instanceof Error ? razao : new Error(String(razao));
        if (eDeFora(erro.stack)) return;

        relatarErro({ mensagem: erro.message, pilha: erro.stack, origem: 'promessa' });
    });
}
