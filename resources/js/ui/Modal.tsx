import { useEffect, useRef, type ReactNode } from 'react';

import { FOCO, GRADIENTES, RAIO_GRANDE, TRANSICAO, cls, type Cor } from './tokens';

/**
 * O MODAL DA CASA.
 *
 * Continua a ser um `<dialog>` de verdade — é ele que trata do foco preso lá
 * dentro, do Escape e do fundo inerte, coisas que uma `<div>` com `position:
 * fixed` só imita mal. O que muda é o aspecto e o movimento, que estavam
 * abaixo do resto do sistema: cabeçalho com gradiente e ícone, cantos largos,
 * sombra funda, e a entrada com as animações que a aplicação já tinha
 * (`animate-fade-in` no fundo, `animate-scale-in` no painel, definidas no
 * `layouts/app.blade.php`).
 *
 * O ÍCONE NO CABEÇALHO não é enfeite: num sistema com dezenas de modais
 * parecidos, é o que diz num relance se isto é criar, editar ou apagar — e a
 * COR diz o mesmo a quem não distingue ícones.
 */
export function Modal({
    aberto,
    aoFechar,
    titulo,
    subtitulo,
    icone,
    cor = 'primaria',
    children,
    rodape,
    largura = 'md',
}: {
    aberto: boolean;
    aoFechar: () => void;
    titulo: string;
    /** Uma linha por baixo do título, para dizer o que a janela faz. */
    subtitulo?: string;
    /** Um ícone FontAwesome (`fa-user`, `fa-trash`, …). */
    icone?: string;
    cor?: Cor;
    children: ReactNode;
    rodape?: ReactNode;
    largura?: 'sm' | 'md' | 'lg' | 'xl';
}) {
    const janela = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const el = janela.current;

        if (!el) return;

        if (aberto && !el.open) {
            el.showModal();
        } else if (!aberto && el.open) {
            el.close();
        }
    }, [aberto]);

    const larguras = { sm: 'max-w-md', md: 'max-w-2xl', lg: 'max-w-4xl', xl: 'max-w-6xl' } as const;

    return (
        <dialog
            ref={janela}
            // O Escape dispara `cancel`; sem isto o `<dialog>` fechava-se
            // sozinho e o React continuava a achar que estava aberto.
            onCancel={(e) => {
                e.preventDefault();
                aoFechar();
            }}
            onClose={aoFechar}
            // Clicar FORA fecha. O clique no `<dialog>` só chega ao próprio
            // elemento quando cai no fundo escurecido, nunca no conteúdo.
            onClick={(e) => {
                if (e.target === janela.current) aoFechar();
            }}
            className={cls(
                'w-[calc(100vw-2rem)] overflow-hidden border-0 p-0 shadow-2xl',
                RAIO_GRANDE,
                'animate-scale-in',
                // O fundo escurece e desfoca — é o que separa a janela da
                // página sem a esconder.
                'backdrop:bg-slate-900/60 backdrop:backdrop-blur-sm',
                larguras[largura],
            )}
        >
            <header className={cls('sticky top-0 z-10 px-5 py-4 text-white', GRADIENTES[cor])}>
                <div className="flex items-center justify-between gap-4">
                    <div className="flex min-w-0 items-center gap-3">
                        {icone && (
                            <span className="grid h-10 w-10 flex-none place-items-center rounded-xl bg-white/20 text-lg">
                                <i className={`fas ${icone}`} aria-hidden="true" />
                            </span>
                        )}
                        <span className="min-w-0">
                            <h2 className="truncate text-lg font-bold tracking-tight">{titulo}</h2>
                            {subtitulo && <p className="truncate text-xs text-white/80">{subtitulo}</p>}
                        </span>
                    </div>

                    <button
                        type="button"
                        onClick={aoFechar}
                        aria-label="Fechar"
                        className={cls(
                            'flex-none rounded-lg p-2 text-white/80 hover:bg-white/20 hover:text-white',
                            // Roda ao passar: o mesmo gesto do ecrã de sempre.
                            'transition-all duration-200 hover:rotate-90',
                            FOCO,
                        )}
                    >
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            </header>

            <div className="max-h-[70vh] overflow-y-auto bg-white px-5 py-5">{children}</div>

            {rodape && (
                <footer className={cls('flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3.5', TRANSICAO)}>
                    {rodape}
                </footer>
            )}
        </dialog>
    );
}
