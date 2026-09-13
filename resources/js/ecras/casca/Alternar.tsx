import { useEffect, useState } from 'react';

import { t } from '@/i18n';
import { FOCO, TRANSICAO, cls } from '@/ui/tokens';

/**
 * OS BOTÕES DE ABRIR E FECHAR A BARRA LATERAL — na barra do topo.
 *
 * A barra lateral é outra peça (`casca`) e as duas falam por eventos na
 * janela: este pede `casca:alternar`, aquela responde `casca:estado`. O estado
 * inicial lê-se do `<html data-casca>`, que a barra carimba — se esta peça
 * montar depois dela, o primeiro aviso já passou.
 */
export default function Alternar() {
    const [aberta, porAberta] = useState(() => document.documentElement.dataset.casca !== 'fechada' && window.innerWidth >= 1024);

    useEffect(() => {
        const ouvir = (e: Event) => porAberta(!!(e as CustomEvent<{ aberta: boolean }>).detail?.aberta);
        window.addEventListener('casca:estado', ouvir);
        return () => window.removeEventListener('casca:estado', ouvir);
    }, []);

    const alternar = () => window.dispatchEvent(new CustomEvent('casca:alternar'));

    return (
        <>
            <button type="button" onClick={alternar} aria-label={t('Menu')} aria-expanded={aberta}
                className={cls('rounded-lg p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 lg:hidden', TRANSICAO, FOCO)}>
                <i className="fas fa-bars text-xl" aria-hidden="true" />
            </button>
            <button type="button" onClick={alternar} aria-label={aberta ? t('Encolher o menu') : t('Abrir o menu')} aria-expanded={aberta}
                className={cls('hidden rounded p-1 text-gray-400 hover:text-gray-600 lg:block', TRANSICAO, FOCO)}>
                <i className={cls('fas transition-transform duration-300', aberta ? 'fa-chevron-left' : 'fa-chevron-right')} aria-hidden="true" />
            </button>
        </>
    );
}
