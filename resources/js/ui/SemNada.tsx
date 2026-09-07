import type { CSSProperties, ReactNode } from 'react';

/**
 * QUANDO NÃO HÁ NADA PARA MOSTRAR.
 *
 * O círculo cinzento com o ícone do documento, o título e a frase que diz o
 * passo seguinte — é o estado vazio que os ecrãs em Blade tinham, e que se
 * perdeu na migração (ficou uma linha de texto cinzento a meio de uma tabela).
 * Uma lista vazia é o momento em que mais falta faz dizer o que fazer a
 * seguir: quem chega ali pela primeira vez não sabe.
 *
 * NASCEU DUAS VEZES, no mesmo dia, em dois ficheiros diferentes — uma versão
 * com título e acção, outra só com uma frase. São a mesma coisa, e é por isso
 * que vive aqui: as duas formas cabem numa peça só, e a próxima não volta a
 * divergir.
 */
export function SemNada({
    icone,
    titulo,
    frase,
    accao,
    children,
}: {
    icone: string;
    /** Sem título, fica só a frase — a forma curta, para dentro de um cartão. */
    titulo?: string;
    frase?: string;
    accao?: ReactNode;
    children?: ReactNode;
}) {
    const curto = !titulo && !accao;

    return (
        <div className={curto ? 'px-5 py-10 text-center' : 'animate-fade-in px-6 py-14 text-center'}>
            <span
                className={`mx-auto mb-3 grid h-20 w-20 place-items-center rounded-full bg-slate-100 text-slate-400 ${
                    curto ? 'text-2xl' : 'mb-4 text-3xl'
                }`}
            >
                <i className={`fas ${icone}`} aria-hidden="true" />
            </span>

            {titulo && <h3 className="text-lg font-bold text-slate-900">{titulo}</h3>}

            {(frase || children) && (
                <p className={`mx-auto max-w-sm text-sm text-slate-500 ${titulo ? 'mt-1' : ''}`}>{frase ?? children}</p>
            )}

            {accao && <div className="mt-5 flex justify-center gap-2">{accao}</div>}
        </div>
    );
}

/**
 * O ATRASO DE CADA LINHA, para a lista entrar em cascata e não de repente.
 *
 * O tecto de 12 não é um número bonito: com 100 linhas a 22 ms, a última
 * entrava dois segundos depois da primeira e a tabela parecia lenta.
 */
export function cascata(i: number): CSSProperties {
    return { '--i': Math.min(i, 12) } as CSSProperties;
}
