import { t } from '@/i18n';

/**
 * DOCUMENTO GRAVADO — e o que se faz a seguir.
 *
 * Imprimir É a razão de muitos o emitirem: o papel para o cliente. Antes disto
 * o ecrã redireccionava sozinho e não havia impressão em lado nenhum; agora
 * pergunta, como o POS faz com o talão. Fica no fundo, por cima do menu, onde
 * o polegar já está.
 */
export function AvisoDeGuardado({
    mensagem,
    aImprimir,
    aPartilhar,
    aoImprimir,
    aoPartilhar,
    rotaDocumentos,
    rotaNovoDocumento,
}: {
    mensagem: string;
    aImprimir: boolean;
    aPartilhar: boolean;
    aoImprimir: () => void;
    aoPartilhar: () => void;
    rotaDocumentos: string;
    rotaNovoDocumento: string;
}) {
    return (
        <div role="status" aria-live="polite" data-ensaio="documento-guardado"
             className="pwa-sobe fixed bottom-24 inset-x-3 z-50 mx-auto max-w-lg rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700 px-4 py-3 text-sm text-white shadow-2xl shadow-emerald-900/30">
            <div className="flex items-start gap-3">
                <span className="pwa-cresce w-9 h-9 shrink-0 rounded-xl bg-white/20 flex items-center justify-center">
                    <i className="fas fa-check-circle text-lg" aria-hidden="true" />
                </span>
                <p className="flex-1 min-w-0 pt-1.5 font-semibold leading-snug">{mensagem}</p>
                <a href={rotaNovoDocumento} title={t('Novo Documento')} aria-label={t('Novo Documento')}
                   className="pwa-toque w-9 h-9 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center transition">
                    <i className="fas fa-plus" aria-hidden="true" />
                </a>
            </div>

            <div className="mt-3 grid grid-cols-3 gap-2">
                <button type="button" onClick={aoImprimir} disabled={aImprimir} data-ensaio="imprimir-documento"
                        className="pwa-toque rounded-xl bg-white/95 hover:bg-white py-2.5 text-xs font-black text-emerald-800 disabled:opacity-60">
                    {aImprimir
                        ? <><i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A obter o número…')}</>
                        : <><i className="fas fa-print mr-1" aria-hidden="true" />{t('Imprimir')}</>}
                </button>
                <button type="button" onClick={aoPartilhar} disabled={aPartilhar} data-ensaio="partilhar-pdf"
                        className="pwa-toque rounded-xl bg-teal-900/70 hover:bg-teal-900/90 py-2.5 text-xs font-black text-white disabled:opacity-60">
                    {aPartilhar
                        ? <><i className="fas fa-spinner fa-spin mr-1" aria-hidden="true" />{t('A gerar o PDF…')}</>
                        : <><i className="fas fa-file-pdf mr-1" aria-hidden="true" />{t('PDF · WhatsApp')}</>}
                </button>
                <a href={rotaDocumentos}
                   className="pwa-toque rounded-xl bg-emerald-800/60 hover:bg-emerald-800/80 py-2.5 text-center text-xs font-black text-white">
                    <i className="fas fa-list mr-1" aria-hidden="true" />{t('Ver documentos')}
                </a>
            </div>
        </div>
    );
}
