import type { CSSProperties, ReactNode } from 'react';

import { CORES_DE_ECRA, RAIO, RAIO_GRANDE, cls } from '@/ui/tokens';

/**
 * O CABEÇALHO DE ECRÃ, DE VOLTA.
 *
 * Quase todos os ecrãs em Blade abriam com a mesma peça: uma faixa com
 * gradiente, o ícone num quadrado translúcido à esquerda, o título e uma
 * linha a dizer o que a página faz — e, à direita, o que ali se pode fazer.
 * Ao passar para React ficou uma página que começa numa caixa branca, e é
 * disso que se queixa quem usa: «perdeu-se tudo».
 *
 * Não é uma peça NOVA de vocabulário: é a que já estava em `agt-settings`,
 * `saftgenerator`, `audit-trail-viewer`, `pos-shift-manager`, `reports-hub` e
 * companhia, escrita uma vez em vez de quinze.
 *
 * AS CORES SÃO AS DA CASA e mais nenhumas, e vivem em `ui/tokens`
 * (`CORES_DE_ECRA`) — não aqui. O MODAL que cada ecrã abre repete a cor da sua
 * faixa, e duas listas da mesma paleta em ficheiros diferentes divergem à
 * primeira cor nova.
 *
 * São as mesmas famílias dos papéis (`GRADIENTES`), sem a parte do `hover:`:
 * um cabeçalho não é um botão, e escurecer ao passar o rato por cima seria
 * prometer um clique que não existe.
 */
const TONS = CORES_DE_ECRA;

export type TomDaFaixa = keyof typeof TONS;

export function Faixa({
    titulo,
    subtitulo,
    icone,
    cor = 'primaria',
    accoes,
    children,
}: {
    titulo: string;
    /** Uma linha por baixo do título: o que esta página faz. */
    subtitulo?: string;
    icone: string;
    cor?: TomDaFaixa;
    /** O que se pode fazer aqui — botões translúcidos, ver `ACCAO_DA_FAIXA`. */
    accoes?: ReactNode;
    /** Estado que se lê de relance (o ambiente que emite, o turno aberto). */
    children?: ReactNode;
}) {
    return (
        <header className={cls('animate-fade-in p-5 text-white shadow-lg', RAIO_GRANDE, TONS[cor])}>
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex min-w-0 items-center gap-4">
                    <span className="grid h-12 w-12 flex-none place-items-center rounded-xl bg-white/20 text-xl backdrop-blur-sm">
                        <i className={`fas ${icone}`} aria-hidden="true" />
                    </span>
                    {/* `h2` e não `h1`: o `h1` da página já é o título que o
                        layout desenha na barra de cima, e duas primeiras
                        cabeças na mesma página baralham quem navega por
                        títulos com o leitor de ecrã. */}
                    <div className="min-w-0">
                        <h2 className="truncate text-xl font-bold tracking-tight sm:text-2xl">{titulo}</h2>
                        {subtitulo && <p className="truncate text-sm text-white/80">{subtitulo}</p>}
                    </div>
                </div>

                {accoes && <div className="flex flex-wrap items-center gap-2">{accoes}</div>}
            </div>

            {children && <div className="mt-4">{children}</div>}
        </header>
    );
}

/**
 * Um botão ou uma ligação DENTRO da faixa.
 *
 * Sobre um fundo com gradiente não entra um botão branco: o que a casa usa
 * desde sempre é vidro fosco — `bg-white/20`, que clareia ao passar.
 */
export const ACCAO_DA_FAIXA = cls(
    'inline-flex items-center gap-2 bg-white/20 px-4 py-2 text-sm font-semibold text-white',
    'transition-all duration-200 hover:bg-white/30 hover:-translate-y-0.5',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70',
    RAIO,
);

/** Uma etiqueta de estado para dentro da faixa: o mesmo vidro, sem clique. */
export function EstadoNaFaixa({ icone, children }: { icone: string; children: ReactNode }) {
    return (
        <span className={cls('inline-flex items-center gap-2 bg-white/20 px-3 py-1.5 text-sm font-semibold text-white backdrop-blur-sm', RAIO)}>
            <i className={`fas ${icone}`} aria-hidden="true" />
            {children}
        </span>
    );
}

/* A peça e o atraso vivem em `@/ui/SemNada` — nasceram aqui duas vezes,
   no mesmo dia, e uma delas tinha de sair. Reexportam-se para os ecrãs que
   as importam daqui não terem de mudar de porta. */
export { SemNada, cascata } from '@/ui/SemNada';
