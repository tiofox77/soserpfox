import { useId } from 'react';

import { t } from '@/i18n';

import { dinheiro } from '../../ganchos';
import type { Registo } from '../../motor/base';
import { valorDaTaxa, type LinhaDoFormulario } from './formulario';
import { CAMPO_PEQUENO, ROTULO_PEQUENO } from './Seccao';

/**
 * UMA LINHA DO DOCUMENTO: quantidade, preço, desconto %, IVA, e — havendo as
 * tabelas da AGT — o IEC e o Imposto de Selo.
 */
export function LinhaDoDocumento({
    linha,
    liquido,
    iva,
    taxas,
    pautais,
    verbas,
    aoMudar,
    aoRemover,
}: {
    linha: LinhaDoFormulario;
    /** O líquido da linha, já com o desconto dela — da mesma conta do total. */
    liquido: number;
    iva: number;
    taxas: Registo[];
    pautais: Registo[];
    verbas: Registo[];
    aoMudar: (parcial: Partial<LinhaDoFormulario>) => void;
    aoRemover: () => void;
}) {
    const id = useId();

    /*
     * AS TAXAS SÃO AS DA EMPRESA, sincronizadas.
     *
     * Estavam fixas (0/5/7/14) e o servidor mandava a mesma lista a toda a
     * gente: numa empresa em não sujeição, os 14% ficavam a um toque de
     * distância.
     *
     * Se a taxa do ARTIGO não está na lista da empresa, aparece como opção à
     * parte. O `<select>` do Blade mostrava a primeira opção (Isento) e o total
     * continuava a contar a taxa do artigo: o que se via não era o que se
     * emitia. Aqui vê-se a verdade e pode trocar-se por uma da empresa.
     */
    const temATaxa = taxas.some((x) => valorDaTaxa(x.rate) === linha.tax_rate);

    return (
        <div className="pwa-entra border-2 border-slate-100 rounded-xl p-3 bg-gradient-to-br from-white to-slate-50/60 transition hover:border-blue-100" data-ensaio="linha">
            <div className="flex items-start justify-between gap-2 mb-2">
                <p className="font-semibold text-sm text-slate-800 flex-1 min-w-0 break-words">
                    <i className="fas fa-cube text-slate-300 mr-1.5" aria-hidden="true" />{linha.product_name}
                </p>
                <button type="button" onClick={aoRemover} aria-label={t('Remover')} title={t('Remover')}
                        className="pwa-toque w-8 h-8 shrink-0 rounded-lg text-red-500 hover:text-white hover:bg-red-500 flex items-center justify-center text-xs transition">
                    <i className="fas fa-trash" aria-hidden="true" />
                </button>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <div>
                    <label htmlFor={`${id}-qtd`} className={ROTULO_PEQUENO}>{t('Qtd')}</label>
                    <input id={`${id}-qtd`} name="quantity" type="number" min="0.01" step="0.01" inputMode="decimal"
                           value={linha.quantity} onChange={(e) => aoMudar({ quantity: e.target.value })} className={CAMPO_PEQUENO} />
                </div>
                <div>
                    <label htmlFor={`${id}-preco`} className={ROTULO_PEQUENO}>{t('Preço')}</label>
                    <input id={`${id}-preco`} name="unit_price" type="number" min="0" step="0.01" inputMode="decimal"
                           value={linha.unit_price} onChange={(e) => aoMudar({ unit_price: e.target.value })} className={CAMPO_PEQUENO} />
                </div>
                <div>
                    <label htmlFor={`${id}-desc`} className={ROTULO_PEQUENO}>{t('Desc %')}</label>
                    <input id={`${id}-desc`} name="discount_percent" type="number" min="0" max="100" step="0.01" inputMode="decimal"
                           value={linha.discount_percent} onChange={(e) => aoMudar({ discount_percent: e.target.value })} className={CAMPO_PEQUENO} />
                </div>
                <div>
                    <label htmlFor={`${id}-iva`} className={ROTULO_PEQUENO}>{t('IVA %')}</label>
                    <select id={`${id}-iva`} name="tax_rate" value={linha.tax_rate} onChange={(e) => aoMudar({ tax_rate: e.target.value })}
                            className={`${CAMPO_PEQUENO} px-1`}>
                        {taxas.map((x, i) => (
                            <option key={`${String(x.id ?? 'x')}-${i}`} value={valorDaTaxa(x.rate)}>{x.label}</option>
                        ))}
                        {!temATaxa && (
                            <option value={linha.tax_rate}>{t(':taxa% (do artigo)', { taxa: linha.tax_rate.replace('.', ',') })}</option>
                        )}
                    </select>
                </div>
            </div>

            {/* IEC e Imposto de Selo.

                O que se escolhe aqui é o CÓDIGO, não o valor: quem apura é o
                servidor. Um valor calculado no aparelho daria dois apuramentos
                do mesmo imposto no documento, e a AGT recusa-o. */}
            {(pautais.length > 0 || verbas.length > 0) && (
                <div className="grid grid-cols-2 gap-2 mt-2">
                    <div>
                        <label htmlFor={`${id}-iec`} className={ROTULO_PEQUENO}>{t('+ IEC')}</label>
                        {/* «Sem IEC» vale '' e grava null. O Blade dava `:value="null"`
                            à opção — e o valor de uma opção no DOM é sempre texto:
                            voltar a «Sem IEC» depois de escolher um código gravava
                            a palavra «null», não nada. O mesmo no Selo. */}
                        <select id={`${id}-iec`} name="iec_pautal" value={linha.iec_pautal ?? ''}
                                onChange={(e) => aoMudar({ iec_pautal: e.target.value || null })}
                                className={`${CAMPO_PEQUENO} px-1 text-xs`}>
                            <option value="">{t('Sem IEC')}</option>
                            {pautais.map((p) => (
                                <option key={String(p.pautal_code)} value={String(p.pautal_code)}>
                                    {`${p.pautal_code} · ${p.description} (${p.rate_percentage}%)`}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label htmlFor={`${id}-selo`} className={ROTULO_PEQUENO}>{t('+ Selo')}</label>
                        <select id={`${id}-selo`} name="is_verba" value={linha.is_verba ?? ''}
                                onChange={(e) => aoMudar({ is_verba: e.target.value || null })}
                                className={`${CAMPO_PEQUENO} px-1 text-xs`}>
                            <option value="">{t('Sem selo')}</option>
                            {verbas.map((v) => (
                                <option key={String(v.verba_no)} value={String(v.verba_no)}>
                                    {`${v.verba_no} · ${v.description}`}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            )}

            <p className="text-right text-xs text-slate-600 mt-2">
                {t('Subtotal')}: <strong className="text-slate-800 tabular-nums">{dinheiro(liquido)}</strong>
                <span className="text-slate-300 mx-1">·</span>
                {t('IVA')}: <span className="tabular-nums">{dinheiro(iva)}</span>
            </p>
        </div>
    );
}
