import { useState } from 'react';

import { t } from '@/i18n';

import { useEstadoDoMotor } from '../ganchos';
import { conviteDispensado, dispensarConvite, promptInstall } from '../motor/instalar';

/**
 * O CONVITE A INSTALAR — FICA POR CIMA DA NAVEGAÇÃO, NUNCA POR CIMA DE VENDER.
 *
 * Estava em `bottom-20 z-50` e a barra «Ver carrinho» do POS está em
 * `bottom-[72px] z-40`: sobrepunham-se, e o banner ganhava. O operador enchia o
 * carrinho, tocava em «Ver carrinho» e não acontecia nada — o toque ia para o
 * convite. Sobe acima da barra de vendas (`bottom-36`) e cede-lhe a camada. Um
 * convite a instalar pode esperar; uma venda não.
 */
export function ConviteInstalar() {
    const { instalavel } = useEstadoDoMotor();
    const [dispensado, setDispensado] = useState(conviteDispensado);

    if (!instalavel || dispensado) return null;

    return (
        <div id="pwa-install-banner" className="pwa-sobe fixed bottom-36 inset-x-3 z-30 bg-gradient-to-r from-indigo-600 to-blue-700 text-white rounded-2xl shadow-2xl p-4">
            <div className="flex items-center gap-3">
                <div className="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0 pwa-flutua">
                    <i className="fas fa-download text-xl" aria-hidden="true" />
                </div>
                <div className="flex-1 min-w-0">
                    <p className="font-bold text-sm">{t('Instalar aplicação')}</p>
                    <p className="text-xs opacity-90">{t('Acesso rápido + funciona offline')}</p>
                </div>
                <button type="button" id="pwa-install-btn" onClick={() => void promptInstall()}
                        className="pwa-toque bg-white text-blue-700 px-3 py-2 rounded-lg text-xs font-bold whitespace-nowrap">{t('Instalar')}</button>
                <button type="button" id="pwa-install-dismiss" title={t('Mais tarde')} aria-label={t('Mais tarde')}
                        onClick={() => { dispensarConvite(); setDispensado(true); }}
                        className="text-white/70 hover:text-white text-lg px-1">&times;</button>
            </div>
        </div>
    );
}
