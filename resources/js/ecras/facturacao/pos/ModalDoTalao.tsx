import { useEffect, useRef, useState } from 'react';

import type { VendaFechada } from '@/api/pos';
import { t } from '@/i18n';
import { abrirOPapel } from '../imprimirAoGravar';
import { Botao } from '@/ui/Botao';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';

/**
 * A VENDA FECHOU — e o papel já está à vista.
 *
 * O ecrã de sempre dava dois botões que abriam um separador novo. Ao balcão
 * isso são dois gestos e uma janela a mais entre a venda e o talão na mão do
 * cliente. Aqui o modal abre JÁ com a pré-visualização do papel que a empresa
 * tem configurado — talão de 80 mm ou factura A4 — e o botão de imprimir só
 * manda imprimir o que está à frente.
 *
 * A IMPRESSÃO NÃO SE REESCREVE. O talão e a factura saem das páginas que o
 * servidor gera, com o cabeçalho da empresa, o QR da AGT e o rodapé legal.
 * O talão de 80 mm até é a MESMA parcial que o modal do POS em Livewire
 * inclui: um documento fiscal desenha-se num sítio só.
 */
export function ModalDoTalao({ venda, aoFechar }: { venda: VendaFechada | null; aoFechar: () => void }) {
    const [papel, porPapel] = useState<'talao' | 'a4'>('talao');
    const [avisoDeImpressao, porAvisoDeImpressao] = useState(false);
    const folha = useRef<HTMLIFrameElement>(null);

    // Abre no papel configurado. Muda-se aqui se esta venda pedir o outro.
    useEffect(() => {
        if (venda) {
            porPapel(venda.formato);
            porAvisoDeImpressao(false);
        }
    }, [venda]);

    if (!venda) return null;

    const morada = venda.papeis[papel];

    /*
     * IMPRIMIR O QUE ESTÁ À FRENTE.
     *
     * `iframe.contentWindow.print()` imprime só o conteúdo da folha, sem o
     * ecrã à volta. Quando o browser o recusa — acontece em alguns, por o
     * documento vir de outra rota — abre-se a página com `?imprimir=1`, que
     * manda imprimir sozinha depois de as imagens carregarem.
     */
    function imprimir() {
        try {
            const janela = folha.current?.contentWindow;

            if (janela) {
                janela.focus();
                janela.print();

                return;
            }
        } catch {
            /* segue para o plano B */
        }

        // Sem `noopener` nas características: com ele o `window.open` devolve SEMPRE
        // null, e o aviso «impressão bloqueada» aparecia mesmo com a janela aberta.
        // A ligação de volta corta-se à mão (ver `abrirOPapel`).
        if (!abrirOPapel(`${morada}?imprimir=1`)) {
            porAvisoDeImpressao(true);
        }
    }

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Venda registada')}
            subtitulo={`${venda.numero_interno} · ${kz(venda.total)}`}
            icone="fa-circle-check"
            cor="bom"
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-cart-plus">{t('Nova venda')}</Botao>
                    <button
                        type="button"
                        onClick={imprimir}
                        className={cls(
                            'inline-flex items-center gap-2 bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-3',
                            'text-base font-bold text-white shadow-lg transition-all duration-200',
                            'hover:-translate-y-0.5 hover:shadow-xl active:translate-y-0',
                            RAIO,
                            FOCO,
                        )}
                    >
                        <i className="fas fa-print text-lg" aria-hidden="true" />
                        {t('Imprimir')}
                    </button>
                </>
            }
        >
            <div className="space-y-3">
                {avisoDeImpressao && (
                    <div
                        role="alert"
                        className={cls(
                            'flex items-start gap-3 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900',
                            RAIO,
                        )}
                    >
                        <i className="fas fa-triangle-exclamation mt-0.5 text-amber-600" aria-hidden="true" />
                        <span className="min-w-0 flex-1">
                            <strong className="block">{t('A impressão foi bloqueada')}</strong>
                            {t('Permita pop-ups para este site e clique novamente em Imprimir.')}
                        </span>
                        <button
                            type="button"
                            onClick={() => porAvisoDeImpressao(false)}
                            aria-label={t('Fechar aviso')}
                            title={t('Fechar aviso')}
                            className={cls('grid h-8 w-8 shrink-0 place-items-center rounded-lg text-amber-700 hover:bg-amber-100', FOCO)}
                        >
                            <i className="fas fa-xmark" aria-hidden="true" />
                        </button>
                    </div>
                )}

                {/* O QUE SE VENDEU, numa linha. O papel logo a seguir diz o
                    resto — repeti-lo aqui era ocupar o ecrã duas vezes. */}
                <div
                    className={cls(
                        'flex flex-wrap items-center justify-between gap-3 bg-gradient-to-r from-emerald-50 to-teal-50 px-4 py-3',
                        RAIO,
                    )}
                >
                    <span className="flex items-center gap-3">
                        <span className="grid h-10 w-10 place-items-center rounded-full bg-emerald-100">
                            <i className="fas fa-check text-emerald-600" aria-hidden="true" />
                        </span>
                        <span>
                            <span className="block text-lg font-bold text-emerald-900">{venda.numero_interno}</span>
                            <span className="block text-xs text-emerald-600">
                                {venda.cliente}
                                {venda.atcud && ` · ATCUD: ${venda.atcud}`}
                            </span>
                        </span>
                    </span>
                    <span className="text-2xl font-bold tabular-nums text-emerald-700">{kz(venda.total)}</span>
                </div>

                {/* O PAPEL. Abre no que a empresa configurou; o outro está aqui
                    ao lado para quem, nesta venda, precisa do outro. */}
                <div className={cls('flex overflow-hidden border border-slate-200 bg-white', RAIO)}>
                    {(
                        [
                            ['talao', t('Talão 80 mm'), 'fa-receipt'],
                            ['a4', t('Factura A4'), 'fa-file-lines'],
                        ] as const
                    ).map(([qual, rotulo, icone]) => (
                        <button
                            key={qual}
                            type="button"
                            onClick={() => porPapel(qual)}
                            aria-pressed={papel === qual}
                            className={cls(
                                'flex flex-1 items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold transition-colors',
                                papel === qual
                                    ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white'
                                    : 'text-slate-600 hover:bg-slate-50',
                                FOCO,
                            )}
                        >
                            <i className={cls('fas', icone)} aria-hidden="true" />
                            {rotulo}
                            {venda.formato === qual && (
                                <span className="ml-1 text-[10px] opacity-70">({t('configurado')})</span>
                            )}
                        </button>
                    ))}
                </div>

                {/* A PRÉ-VISUALIZAÇÃO. É a página verdadeira do servidor dentro
                    de um `iframe` — o que se vê é exactamente o que sai na
                    impressora, e não um desenho parecido feito no browser. */}
                <iframe
                    ref={folha}
                    key={morada}
                    src={morada}
                    title={t('Pré-visualização do documento')}
                    className={cls('h-[26rem] w-full border border-slate-200 bg-white', RAIO)}
                />
            </div>
        </Modal>
    );
}
