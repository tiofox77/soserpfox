import { useEffect, useRef, type ReactNode } from 'react';
import { cls } from './tokens';

/**
 * Uma janela sobreposta, construída sobre o `<dialog>` do browser.
 *
 * PORQUÊ O `<dialog>` E NÃO UMA `<div>` COM `position: fixed`, que é o que as
 * vistas Blade fazem hoje: o elemento nativo traz de graça o que essas não
 * têm — o foco fica preso dentro da janela (com Tab não se sai para o menu
 * atrás), o Escape fecha, o resto da página fica inerte para um leitor de
 * ecrã, e o empilhamento não depende de acertar um `z-index`.
 *
 * O que se escreve à mão é só o aspecto.
 */
export function Modal({
    aberto,
    aoFechar,
    titulo,
    children,
    rodape,
    largura = 'md',
}: {
    aberto: boolean;
    aoFechar: () => void;
    titulo: string;
    children: ReactNode;
    rodape?: ReactNode;
    largura?: 'sm' | 'md' | 'lg';
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

    const larguras = { sm: 'max-w-md', md: 'max-w-2xl', lg: 'max-w-4xl' } as const;

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
                'w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 p-0 shadow-2xl',
                'backdrop:bg-slate-900/50',
                larguras[largura],
            )}
        >
            <header className="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <h2 className="text-base font-bold tracking-tight text-slate-900">{titulo}</h2>
                <button
                    type="button"
                    onClick={aoFechar}
                    aria-label="Fechar"
                    className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                >
                    <i className="fas fa-times" aria-hidden="true" />
                </button>
            </header>

            <div className="max-h-[70vh] overflow-y-auto px-5 py-5">{children}</div>

            {rodape && (
                <footer className="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3.5">
                    {rodape}
                </footer>
            )}
        </dialog>
    );
}
