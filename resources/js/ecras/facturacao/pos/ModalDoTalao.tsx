import type { VendaFechada } from '@/api/pos';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';

/**
 * A VENDA FECHOU — e o que se faz a seguir.
 *
 * Ao balcão isto é meio segundo: o operador vê que passou, arranca o talão e
 * chama o próximo. Por isso a peça grande deste modal é o NÚMERO e o TOTAL, e
 * não os botões.
 *
 * A IMPRESSÃO NÃO SE REESCREVE AQUI. O talão de 80 mm e a factura A4 saem das
 * páginas que o servidor já gera — as mesmas que a lista de facturas usa, com
 * o cabeçalho da empresa, o QR da AGT e o rodapé legal. Um talão desenhado de
 * novo no browser seria um segundo documento a dizer o mesmo, e são
 * precisamente os documentos fiscais que não podem ter duas versões.
 */
export function ModalDoTalao({ venda, aoFechar }: { venda: VendaFechada | null; aoFechar: () => void }) {
    if (!venda) return null;

    function abrirEImprimir(url: string) {
        const janela = window.open(url, '_blank', 'noopener');

        if (!janela) {
            window.alert(t('O navegador bloqueou a janela de impressão. Permita pop-ups para este site.'));
        }
    }

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Venda registada')}
            icone="fa-circle-check"
            cor="bom"
            largura="md"
            rodape={
                <Botao cor="primaria" tom="solida" icone="fa-cart-plus" onClick={aoFechar}>
                    {t('Nova venda')}
                </Botao>
            }
        >
            <div className="space-y-4">
                <div className={cls('bg-gradient-to-br from-emerald-50 to-teal-50 p-6 text-center', RAIO)}>
                    <div className="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-full bg-emerald-100">
                        <i className="fas fa-check text-2xl text-emerald-600" aria-hidden="true" />
                    </div>
                    <p className="text-sm text-emerald-700">{t('Documento')}</p>
                    <p className="text-2xl font-bold text-emerald-900">{venda.numero_interno}</p>
                    {venda.numero_interno !== venda.numero && (
                        <p className="mt-0.5 font-mono text-xs text-emerald-600">
                            <i className="fas fa-landmark mr-1" aria-hidden="true" />
                            {venda.numero}
                        </p>
                    )}
                    <p className="mt-3 text-4xl font-bold tabular-nums text-emerald-700">{kz(venda.total)}</p>
                    <p className="mt-1 text-sm text-emerald-600">{venda.cliente}</p>
                </div>

                {/* O QR DA AGT vem do servidor. Montá-lo no browser era inventar
                    um selo fiscal a partir de dados que o browser não certifica. */}
                {venda.qr && (
                    <div className="flex justify-center">
                        <img
                            src={venda.qr}
                            alt={t('Código QR da AGT')}
                            className={cls('h-28 w-28 border border-slate-200 bg-white p-1', RAIO)}
                        />
                    </div>
                )}

                <div className="grid gap-2 sm:grid-cols-2">
                    <button
                        type="button"
                        onClick={() => abrirEImprimir(`${venda.preview}?formato=talao`)}
                        className={cls(
                            'flex items-center justify-center gap-2 border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700',
                            'transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md',
                            RAIO,
                            FOCO,
                        )}
                    >
                        <i className="fas fa-receipt text-indigo-500" aria-hidden="true" />
                        {t('Talão 80 mm')}
                    </button>
                    <button
                        type="button"
                        onClick={() => abrirEImprimir(venda.preview)}
                        className={cls(
                            'flex items-center justify-center gap-2 border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700',
                            'transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md',
                            RAIO,
                            FOCO,
                        )}
                    >
                        <i className="fas fa-file-lines text-red-500" aria-hidden="true" />
                        {t('Factura A4')}
                    </button>
                </div>
            </div>
        </Modal>
    );
}
