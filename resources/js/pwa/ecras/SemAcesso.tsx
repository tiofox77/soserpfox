import { t } from '@/i18n';

import { usePwa } from '../contexto';

/**
 * O ECRÃ A QUE ESTE UTILIZADOR NÃO TEM DIREITO — para não ser um 403 em branco.
 *
 * Quem está ao balcão não distingue «não tem permissão» de «a aplicação
 * partiu-se», e a chamada ao suporte é a mesma — com a diferença de que uma
 * delas se resolve em dez segundos, se alguém souber o que pedir.
 *
 * Aparece de duas maneiras: servida pelo servidor (403, com a permissão em
 * falta) ou decidida no aparelho, quando sem rede a página guardada de outro
 * endereço serviria um ecrã que o menu deste utilizador não tem.
 */
export function SemAcesso() {
    const { semAcesso, rotas } = usePwa();

    return (
        <div className="py-10 px-4">
            <div className="pwa-entra max-w-md mx-auto bg-white rounded-2xl shadow-sm p-8 text-center">
                <div className="w-16 h-16 mx-auto rounded-2xl bg-amber-100 flex items-center justify-center mb-4 pwa-flutua">
                    <i className="fas fa-lock text-2xl text-amber-600" aria-hidden="true" />
                </div>

                <h1 className="text-lg font-bold text-slate-800">
                    {semAcesso ? t('Não tem acesso a :area', { area: semAcesso.etiqueta }) : t('Não tem acesso a esta área')}
                </h1>

                <p className="text-sm text-slate-500 mt-2">
                    {semAcesso?.modulo
                        ? t('Esta área pertence a um módulo que a sua empresa não tem activo.')
                        : t('A sua conta não tem permissão para esta área, ou a empresa desligou-a nas definições da aplicação móvel.')}
                </p>

                {semAcesso?.permissao && (
                    // O nome exacto da permissão: é o que o administrador procura na lista de papéis.
                    <div className="mt-4 rounded-xl bg-slate-50 border border-slate-200 p-3">
                        <p className="text-[11px] font-bold uppercase tracking-wide text-slate-400">{t('Permissão em falta')}</p>
                        <code className="text-xs font-mono text-slate-700 break-all">{semAcesso.permissao}</code>
                    </div>
                )}

                <p className="text-xs text-slate-400 mt-4">{t('Peça ao administrador da empresa.')}</p>

                <a href={rotas.inicio}
                   className="pwa-toque inline-block mt-6 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-blue-600/20">
                    <i className="fas fa-arrow-left mr-1.5" aria-hidden="true" />{t('Voltar ao início')}
                </a>
            </div>
        </div>
    );
}
