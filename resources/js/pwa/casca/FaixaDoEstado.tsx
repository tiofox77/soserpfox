import { useEffect, useState } from 'react';

import { t, tn } from '@/i18n';

import { useEstadoDoMotor } from '../ganchos';
import { sync } from '../motor/sincronizar';
import { usePwa } from '../contexto';

/**
 * A FAIXA DO ESTADO — o que o aparelho está a fazer com a rede, no topo.
 *
 * Uma de cada vez, pela ordem do que é mais grave: a subscrição (vermelho, e
 * leva a quem pode renovar), a sessão (laranja, e leva à entrada), a falha da
 * última sincronização (sai sozinha), sem rede com o que falta enviar, e por
 * enviar com rede (toca-se para enviar).
 *
 * «A sincronizar» e «Sincronizado» JÁ NÃO SÃO FAIXA: a faixa empurra o
 * cabeçalho (ver Casca › Topo), e estes dois aparecem a cada venda — o ecrã
 * saltava debaixo do dedo de quem estava a tocar no artigo seguinte. Vivem no
 * botão de sincronizar do cabeçalho (roda, e depois um visto). Pela mesma
 * razão o «por enviar» com rede só aparece se ficar pendurado: uma venda
 * acabada de fazer sobe em segundos e não merece faixa.
 *
 * Os trabalhos retidos de outra empresa ficam por baixo e NÃO saem sozinhos:
 * uma venda retida que ninguém vê é uma venda perdida, só mais devagar.
 *
 * Os ids são os da barra antiga (`pwa-status-*`): os ensaios e quem depura no
 * telemóvel procuram-nos.
 */
export function FaixaDoEstado() {
    const e = useEstadoDoMotor();
    const { rotas } = usePwa();
    const [erroFechado, setErroFechado] = useState(false);
    const [retidosFechados, setRetidosFechados] = useState(false);

    useEffect(() => { setErroFechado(false); }, [e.erroDeSync]);
    useEffect(() => { setRetidosFechados(false); }, [e.retidos]);

    const porEnviarPendurado = useDepoisDe(e.online && e.pendingCount > 0 && !e.syncing, 8000);

    const base = 'pwa-desce w-full text-white text-center py-1.5 px-3 text-xs font-semibold shadow-lg';
    let faixa = null;

    if (e.subscriptionExpired) {
        faixa = (
            <button type="button" id="pwa-status-subscricao" onClick={() => { window.location.href = rotas.subscricaoExpirada; }} className={`${base} bg-red-700 py-2 font-bold`}>
                <i className="fas fa-triangle-exclamation mr-1" aria-hidden="true" />
                {t('Subscrição expirada — as vendas ficam guardadas até renovar')}
            </button>
        );
    } else if (e.sessionExpired) {
        faixa = (
            <button type="button" id="pwa-status-session" onClick={() => { window.location.href = rotas.entrada; }} className={`${base} bg-orange-600 py-2 font-bold`}>
                <i className="fas fa-lock mr-1" aria-hidden="true" />
                {t('Sessão expirada — toque para autenticar novamente')}
            </button>
        );
    } else if (e.erroDeSync && !erroFechado) {
        faixa = (
            <button type="button" id="pwa-status-error" onClick={() => setErroFechado(true)} className={`${base} bg-red-600`}>
                <i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />
                {t('Erro de sincronização: :erro (toca para fechar)', { erro: e.erroDeSync || t('desconhecido') })}
            </button>
        );
    } else if (!e.online) {
        faixa = (
            <div role="status" id="pwa-status-offline" className={`${base} bg-amber-500`}>
                <i className="fas fa-wifi mr-1 opacity-70" aria-hidden="true" />{t('Sem conexão — A trabalhar offline.')}
                {e.pendingCount > 0 && <span className="ml-2">{t(':n por sincronizar', { n: e.pendingCount })}</span>}
            </div>
        );
    } else if (porEnviarPendurado) {
        faixa = (
            <button type="button" id="pwa-status-waiting" onClick={() => void sync(true)} className={`${base} bg-amber-500 hover:bg-amber-600`}>
                <i className="fas fa-rotate mr-1" aria-hidden="true" />
                {tn(':n documento por sincronizar — toque para sincronizar|:n documentos por sincronizar — toque para sincronizar', e.pendingCount, { n: e.pendingCount })}
            </button>
        );
    }

    const retidos = e.retidos > 0 && !retidosFechados;

    if (!faixa && !retidos) return null;

    return (
        <div id="pwa-status-bar" className="relative z-50">
            {faixa}
            {retidos && (
                <button type="button" id="pwa-retidos" onClick={() => setRetidosFechados(true)} className="pwa-desce w-full bg-amber-500 text-white text-xs px-3 py-2 text-left">
                    <i className="fas fa-triangle-exclamation mr-1" aria-hidden="true" />
                    {t(':n operação(ões) da empresa anterior ficaram por enviar. Volte a entrar nessa empresa para as sincronizar.', { n: e.retidos })}
                </button>
            )}
        </div>
    );
}

/** Verdadeiro só depois de a condição se manter por `ms` seguidos. */
function useDepoisDe(condicao: boolean, ms: number): boolean {
    const [passou, porPassou] = useState(false);

    useEffect(() => {
        if (!condicao) { porPassou(false); return; }
        const relogio = window.setTimeout(() => porPassou(true), ms);
        return () => window.clearTimeout(relogio);
    }, [condicao, ms]);

    return condicao && passou;
}
