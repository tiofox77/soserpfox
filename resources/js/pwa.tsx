/**
 * A ENTRADA DO PWA — o motor offline, o papel e os onze ecrãs, num pacote só.
 *
 * Era o `layouts/pwa.blade.php` com Alpine e três ficheiros de `public/js`
 * (`pwa-invoicing.js`, `pos-offline-ticket.js`, `pwa-turno.js`). Ver
 * `vite.pwa.config.js` para o porquê de ser um ficheiro só, e
 * `App\Support\PaginaDoPwa` para a casca do servidor.
 */

// PRIMEIRO, sempre: o dicionário tem de estar posto antes de qualquer `t()`.
import './pwa/dicionario';

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import { ligarServicoOffline } from './casca/servicoOffline';
import { Aplicacao } from './pwa/aplicacao';
import { aquecerCache } from './pwa/aquecer';
import { instalarMotor } from './pwa/motor';
import type { PropsDoPwa } from './pwa/tipos';

declare global {
    interface Window {
        __pwaMontado?: boolean;
    }
}

// O service worker regista-se antes de tudo: mesmo que um ecrã rebente, a
// instalação do modo offline não pode ficar para trás.
ligarServicoOffline({ avisos: false });

const raiz = document.getElementById('pwa-raiz');

if (raiz) {
    let props: PropsDoPwa | null = null;

    try {
        props = JSON.parse(raiz.dataset.props || '{}') as PropsDoPwa;
    } catch (e) {
        console.error('[PWA] data-props ilegível', e);
    }

    // O motor arranca em todas as páginas, incluindo a entrada: é ele que diz
    // se há rede, se há acesso offline e quantas vendas faltam enviar.
    instalarMotor();

    if (props) {
        // As páginas da aplicação ficam guardadas logo na primeira visita com sessão.
        if (props.ecra !== 'entrada' && props.ecra !== 'pin-esquecido' && props.ecra !== 'sem-acesso') aquecerCache(props);

        createRoot(raiz).render(
            <StrictMode>
                <Aplicacao props={props} />
            </StrictMode>,
        );
    }

    window.__pwaMontado = true;

    const aCarregar = document.getElementById('pwa-a-carregar');
    if (aCarregar) {
        aCarregar.style.transition = 'opacity .25s';
        aCarregar.style.opacity = '0';
        setTimeout(() => aCarregar.remove(), 260);
    }
}
