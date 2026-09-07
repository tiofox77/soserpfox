import { useEffect, useState } from 'react';

import type { ArtigoDoPos } from '@/api/pos';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Campo } from '@/ui/Campo';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls, kz } from '@/ui/tokens';

/**
 * O PREÇO PERGUNTADO AO BALCÃO.
 *
 * Alguns artigos não têm preço fixo: vendem-se a peso, ao corte, ou por
 * acordo. Esses trazem o interruptor `preco_no_pos` ligado e, em vez de
 * entrarem direitos no carrinho, param aqui — com o preço de catálogo já
 * escrito como proposta, que é quase sempre o que se cobra.
 *
 * O campo abre já seleccionado: quem vai escrever outro preço escreve-o sem
 * ter de apagar o que lá está.
 */
export function ModalDePreco({
    artigo,
    aoFechar,
    aoConfirmar,
}: {
    artigo: ArtigoDoPos | null;
    aoFechar: () => void;
    aoConfirmar: (preco: number) => void;
}) {
    const [valor, porValor] = useState('');

    useEffect(() => {
        if (artigo) porValor(String(artigo.preco));
    }, [artigo]);

    const numero = Number(String(valor).replace(/\s/g, '').replace(',', '.'));
    const valido = Number.isFinite(numero) && numero > 0;

    function confirmar() {
        if (valido) aoConfirmar(Math.round(numero * 100) / 100);
    }

    return (
        <Modal
            aberto={artigo !== null}
            aoFechar={aoFechar}
            titulo={t('Preço desta venda')}
            subtitulo={artigo?.nome}
            icone="fa-tag"
            cor="aviso"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-cart-plus" disabled={!valido} onClick={confirmar}>
                        {t('Juntar ao carrinho')}
                    </Botao>
                </>
            }
        >
            <div className="space-y-3">
                <p className="text-sm text-slate-600">
                    {t('Este artigo tem o preço perguntado ao balcão. O que está escrito é o de catálogo — mude-o se for outro.')}
                </p>

                <Campo etiqueta={t('Preço unitário')}>
                    <div className="relative">
                        <input
                            type="text"
                            inputMode="decimal"
                            value={valor}
                            onChange={(e) => porValor(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    confirmar();
                                }
                            }}
                            autoFocus
                            onFocus={(e) => e.target.select()}
                            aria-label={t('Preço unitário')}
                            className={cls(
                                'w-full border border-slate-300 bg-white py-4 pl-4 pr-16 text-right text-3xl font-bold tabular-nums',
                                'focus:border-amber-500 focus:ring-2 focus:ring-amber-200',
                                RAIO,
                                FOCO,
                            )}
                        />
                        <span className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-slate-400">
                            Kz
                        </span>
                    </div>
                </Campo>

                {artigo && (
                    <p className="text-xs text-slate-500">
                        {t('Preço de catálogo: :preco', { preco: kz(artigo.preco) })}
                    </p>
                )}
            </div>
        </Modal>
    );
}
