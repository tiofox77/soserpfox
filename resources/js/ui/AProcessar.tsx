import { useEffect, useId, useRef, useState } from 'react';

import { t } from '@/i18n';

/**
 * A VENDA A GRAVAR — tudo parado até a resposta chegar.
 *
 * Com a rede lenta, o «Confirmar» ficava a pensar e o balcão continuava vivo
 * por baixo: fechar a janela, mexer no carrinho, carregar outra vez. Foi assim
 * que nasceram vendas em dobro (Luk Simões, 23/09/2026). Enquanto isto está
 * aberto, nada mais se toca.
 *
 * É um `<dialog>` próprio, aberto com `showModal()`: entra na camada de cima,
 * por cima da janela do pagamento, e o resto da página fica inerte. Não tem
 * botão de fechar, o Escape não o fecha, e sair da página a meio pede
 * confirmação ao browser. Passados uns segundos, diz que a ligação está lenta
 * e que a venda não se repete — é essa a pergunta de quem está ao balcão.
 *
 * Serve ao POS online e ao PWA (os dois carregam o mesmo Tailwind).
 */
export function AProcessar({
    activo,
    titulo = t('A registar a venda…'),
    texto = t('Aguarde. Não feche nem recarregue esta página.'),
}: {
    activo: boolean;
    titulo?: string;
    texto?: string;
}) {
    const janela = useRef<HTMLDialogElement>(null);
    const activoAgora = useRef(activo);
    const [lento, porLento] = useState(false);
    const idTitulo = useId();

    activoAgora.current = activo;

    useEffect(() => {
        const el = janela.current;
        if (!el) return;

        if (activo && !el.open) {
            if (typeof el.showModal === 'function') el.showModal();
            else el.setAttribute('open', '');
        } else if (!activo && el.open) {
            if (typeof el.close === 'function') el.close();
            else el.removeAttribute('open');
        }
    }, [activo]);

    // A ligação está lenta: diz-se ao fim de uns segundos, sem assustar antes.
    useEffect(() => {
        porLento(false);
        if (!activo) return;

        const id = window.setTimeout(() => porLento(true), 6000);

        return () => window.clearTimeout(id);
    }, [activo]);

    // Sair da página a meio da gravação: o browser pergunta primeiro.
    useEffect(() => {
        if (!activo) return;

        const perguntar = (e: BeforeUnloadEvent) => {
            e.preventDefault();
            e.returnValue = '';
        };

        window.addEventListener('beforeunload', perguntar);

        return () => window.removeEventListener('beforeunload', perguntar);
    }, [activo]);

    return (
        <dialog
            ref={janela}
            role="alertdialog"
            aria-modal="true"
            aria-busy={activo}
            aria-labelledby={idTitulo}
            data-a-processar={activo ? '' : undefined}
            // O Escape não fecha. E se o browser a fechar à força (o Chrome
            // fá-lo ao segundo Escape), volta a abrir enquanto a venda grava.
            onCancel={(e) => e.preventDefault()}
            onClose={() => {
                if (activoAgora.current && typeof janela.current?.showModal === 'function') {
                    janela.current.showModal();
                }
            }}
            className="m-auto border-0 bg-transparent p-0 backdrop:bg-slate-900/70 backdrop:backdrop-blur-sm"
        >
            <div className="flex w-[min(22rem,calc(100vw-2rem))] flex-col items-center gap-3 rounded-2xl bg-white px-6 py-7 text-center shadow-2xl">
                <span className="relative grid h-16 w-16 place-items-center" aria-hidden="true">
                    <span className="absolute inset-0 animate-spin rounded-full border-4 border-emerald-100 border-t-emerald-600" />
                    <i className="fas fa-cash-register text-xl text-emerald-600" />
                </span>
                <h2 id={idTitulo} className="text-lg font-bold text-slate-900">{titulo}</h2>
                <p className="text-sm text-slate-600">{texto}</p>
                {lento && (
                    <p role="status" className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
                        <i className="fas fa-wifi mr-1.5" aria-hidden="true" />
                        {t('A ligação está lenta. A venda está a ser gravada e não se repete — espere pela resposta.')}
                    </p>
                )}
            </div>
        </dialog>
    );
}
