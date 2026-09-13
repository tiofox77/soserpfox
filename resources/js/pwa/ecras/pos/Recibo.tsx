import { useEffect } from 'react';

import { t } from '@/i18n';

import { kz } from '../../ganchos';
import { Camada } from './Camada';
import type { ControloDoPos } from './usePos';

/**
 * O RECIBO DEPOIS DA VENDA — imprimir outra vez, PDF para o WhatsApp, ou a próxima venda.
 *
 * O documento é o registo VIVO da base: quando a venda sincroniza, o número
 * provisório (PEND-…) passa ao fiscal aqui mesmo, com o recibo aberto.
 */
export function ReciboDaVenda({ pos }: { pos: ControloDoPos }) {
    const recibo = pos.recibo;
    const registo = pos.registoDoRecibo;
    const fechar = pos.fecharRecibo;

    useEffect(() => {
        if (!recibo) return;
        const tecla = (e: KeyboardEvent) => { if (e.key === 'Escape') fechar(); };
        window.addEventListener('keydown', tecla);

        return () => window.removeEventListener('keydown', tecla);
    }, [recibo, fechar]);

    if (!recibo) return null;

    const sincronizada = !!registo?._synced;
    const numero = String((sincronizada ? registo?._server_number : registo?.provisional_number) || registo?.provisional_number || '');

    // NÃO dizer «sincronizada com a AGT»: o documento foi emitido e numerado, e a
    // comunicação à AGT vai a seguir, em fila. Prometer o que ainda não aconteceu
    // é pior do que não prometer nada — alguém confia e deixa de conferir.
    const mensagem = sincronizada
        ? t('Emitida — :numero', { numero })
        : recibo.online
            ? t('A sincronizar com o servidor…')
            : t('Guardada localmente — será sincronizada quando voltar online.');

    return (
        <Camada>
            <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div className="pwa-fundo absolute inset-0 bg-slate-900/60 backdrop-blur-[2px]" onClick={fechar} />
                <div role="dialog" aria-modal="true" aria-labelledby="pos-recibo-titulo"
                     className="pwa-cresce relative bg-white rounded-3xl max-w-sm w-full p-6 text-center shadow-2xl overflow-hidden">
                    {/* A faixa de cor em cima diz o estado antes de se ler: verde emitida, âmbar provisória. */}
                    <span className={`absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r transition-colors ${sincronizada ? 'from-emerald-500 to-green-600' : 'from-amber-400 to-orange-500'}`} aria-hidden="true" />

                    <div className={`w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3 transition-colors ${sincronizada ? 'bg-emerald-100' : 'bg-amber-100'}`}>
                        <i key={String(sincronizada)} className={`pwa-cresce text-3xl fas ${sincronizada ? 'fa-check text-emerald-600' : 'fa-clock text-amber-600'}`} aria-hidden="true" />
                    </div>
                    <h3 id="pos-recibo-titulo" className="text-xl font-bold text-gray-900 mb-1">
                        {sincronizada ? t('Venda Concluída!') : t('Venda Registada (Provisória)')}
                    </h3>
                    <p className="text-sm text-gray-600 mb-3" aria-live="polite">{mensagem}</p>

                    <div className="bg-gray-50 rounded-2xl p-3 mb-3 text-left text-sm space-y-0.5">
                        <p className="flex justify-between gap-2"><span className="text-gray-500">{t('Documento')}</span><strong className="font-mono text-right break-all">{numero}</strong></p>
                        <p className="flex justify-between gap-2"><span className="text-gray-500">{t('Cliente')}</span><strong className="text-right truncate">{recibo.cliente}</strong></p>
                        <p className="flex justify-between gap-2"><span className="text-gray-500">{t('Itens')}</span><strong>{recibo.itens}</strong></p>
                        <p className="flex justify-between gap-2"><span className="text-gray-500">{t('Pagamento')}</span><strong>{recibo.pagamento}</strong></p>
                        <p className="flex justify-between gap-2 text-lg mt-1 pt-1 border-t border-gray-200">
                            <span className="text-gray-500">{t('Total')}</span>
                            <strong className="text-emerald-700 tabular-nums">{kz(registo?.total ?? recibo.total)}</strong>
                        </p>
                    </div>

                    <div className="flex gap-2">
                        <button type="button" onClick={() => { if (registo) void pos.reimprimir(registo); }}
                                className="pwa-toque flex-1 bg-gradient-to-r from-emerald-600 to-green-700 text-white py-3 rounded-2xl font-bold shadow-lg text-sm">
                            <i className="fas fa-print mr-1" aria-hidden="true" />{t('Imprimir')}
                        </button>
                        {/* O talão em PDF, para o WhatsApp — feito no aparelho, com ou sem rede. Abre a folha de partilha do sistema. */}
                        <button type="button" onClick={() => void pos.partilhar(registo)} disabled={pos.aPartilhar} data-ensaio="partilhar-pdf"
                                className="pwa-toque flex-1 bg-gradient-to-r from-teal-600 to-emerald-700 text-white py-3 rounded-2xl font-bold shadow-lg text-sm disabled:opacity-60">
                            {pos.aPartilhar
                                ? <><i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A gerar o PDF…')}</>
                                : <><i className="fas fa-file-pdf mr-1" aria-hidden="true" />{t('PDF · WhatsApp')}</>}
                        </button>
                        <button type="button" onClick={fechar} autoFocus
                                className="pwa-toque flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-2xl font-bold text-sm">
                            <i className="fas fa-plus mr-1" aria-hidden="true" />{t('Nova Venda')}
                        </button>
                    </div>
                </div>
            </div>
        </Camada>
    );
}
