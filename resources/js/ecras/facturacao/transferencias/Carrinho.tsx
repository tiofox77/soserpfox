import { useEffect, useState, type CSSProperties } from 'react';

import { transferencias, type ArtigoParaTransferir, type ItemDaTransferencia } from '@/api/transferencias';
import { Campo, entrada } from '@/ui/Campo';
import { CORES, FOCO, RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * O CARRINHO DE UMA TRANSFERÊNCIA OU DE UM AJUSTE: procurar artigos no
 * servidor (com o que há no armazém de origem) e juntá-los com quantidade.
 *
 * A quantidade limita-se ao disponível quando há tecto — quem escreveu 50
 * quando há 9 quer transferir o que houver; dizer-lhe quanto é mais útil do
 * que recusar. Sem tecto (um ajuste de entrada) aceita-se o que for.
 *
 * O ASPECTO É O DE SEMPRE: o quadrado de gradiente ao lado de cada artigo, a
 * caixa a tracejado enquanto o carrinho está vazio, e o comprovativo com o
 * visto num círculo verde.
 */

/** O atraso da linha `i` na entrada em cascata (ver `.entra` no layout). */
const cascata = (i: number) => ({ '--i': i }) as CSSProperties;

export function Carrinho({ armazem, itens, aoMudar, comTecto, soComStock = true }: {
    armazem: string;
    itens: ItemDaTransferencia[];
    aoMudar: (itens: ItemDaTransferencia[]) => void;
    comTecto: boolean;
    soComStock?: boolean;
}) {
    const [procura, porProcura] = useState('');
    const [sugestoes, porSugestoes] = useState<ArtigoParaTransferir[]>([]);
    const [aviso, porAviso] = useState('');

    useEffect(() => {
        if (!armazem) { porSugestoes([]); return; }
        let cancelado = false;
        const h = setTimeout(() => {
            transferencias.artigos({ procura: procura.trim(), armazem, so_com_stock: soComStock })
                .then((r) => { if (!cancelado) porSugestoes(r.data.filter((a) => !itens.some((i) => i.product_id === a.id))); })
                .catch(() => { if (!cancelado) porSugestoes([]); });
        }, 300);
        return () => { cancelado = true; clearTimeout(h); };
    }, [procura, armazem, itens, soComStock]);

    const juntar = (a: ArtigoParaTransferir) => {
        if (comTecto && a.disponivel <= 0) { porAviso(t('Não há :artigo neste armazém.', { artigo: a.name })); return; }
        porAviso('');
        aoMudar([...itens, { product_id: a.id, product_name: a.name, product_code: a.code, unit: a.unit, quantity: comTecto ? Math.min(1, a.disponivel) : 1, disponivel: a.disponivel, unit_cost: a.custo }]);
        porProcura('');
    };

    const mudarQuantidade = (k: number, valor: string) => {
        const n = Number(valor);
        aoMudar(itens.map((i, j) => {
            if (j !== k) return i;
            if (valor === '') return { ...i, quantity: '' };
            if (!Number.isFinite(n) || n <= 0) { porAviso(t('A quantidade deve ser um número maior que zero.')); return i; }
            if (comTecto && n > i.disponivel) { porAviso(t('Só há :quanto de :artigo neste armazém. Ajustado para o disponível.', { quanto: i.disponivel, artigo: i.product_name })); return { ...i, quantity: i.disponivel }; }
            porAviso('');
            return { ...i, quantity: n };
        }));
    };

    return (
        <div className="space-y-3">
            <div className="relative">
                <Campo etiqueta={t('Juntar artigo')}>
                    <input value={procura} onChange={(e) => porProcura(e.target.value)} disabled={!armazem} placeholder={armazem ? t('Nome, código ou código de barras') : t('Escolha primeiro o armazém de origem')} className={entrada} />
                </Campo>
                {sugestoes.length > 0 && (
                    <ul className={cls('absolute z-10 mt-1 max-h-64 w-full overflow-auto border border-slate-200 bg-white shadow-xl', RAIO)} role="listbox">
                        {sugestoes.map((a, i) => (
                            <li key={a.id} style={cascata(i)} className="entra border-b border-slate-100 last:border-0">
                                <button type="button" onClick={() => juntar(a)} className={cls('flex w-full items-center justify-between px-3 py-2 text-left text-sm transition-all duration-200 hover:bg-indigo-50/60', FOCO)}>
                                    <span><span className="font-semibold text-slate-900">{a.name}</span>{a.code && <span className="ml-2 font-mono text-xs text-slate-400">{a.code}</span>}</span>
                                    <span className={cls('text-xs font-semibold', a.disponivel > 0 ? 'text-slate-500' : 'text-red-500')}>
                                        <i className={cls('fas mr-1', a.disponivel > 0 ? 'fa-cubes' : 'fa-ban')} aria-hidden="true" />
                                        {t('tem :quanto', { quanto: a.disponivel.toLocaleString('pt-PT') })}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {aviso && (
                <p role="alert" className={cls('flex items-center gap-2 border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800', RAIO)}>
                    <i className="fas fa-triangle-exclamation" aria-hidden="true" />{aviso}
                </p>
            )}

            <div className={cls('overflow-x-auto border border-slate-200', RAIO)}>
                {itens.length === 0 ? (
                    /* A caixa a tracejado do ecrã de sempre: falta alguma coisa,
                       mas não é um erro — é um convite. */
                    <div className={cls('m-2 border-2 border-dashed border-slate-200 p-6 text-center text-slate-400', RAIO)}>
                        <i className="fas fa-inbox mb-2 text-3xl" aria-hidden="true" />
                        <p className="text-sm">{t('Sem artigos. Procure e junte.')}</p>
                    </div>
                ) : (
                    <table className="min-w-[580px] w-full text-sm">
                        <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-3 py-2 font-bold">{t('Artigo')}</th><th className="px-3 py-2 text-right font-bold">{t('Tem')}</th><th className="px-3 py-2 text-right font-bold">{t('Qtd.')}</th><th className="w-10 px-3 py-2"></th></tr></thead>
                        <tbody className="divide-y divide-slate-100">
                            {itens.map((i, k) => (
                                <tr key={i.product_id} style={cascata(k)} className="entra transition-all duration-200 hover:bg-indigo-50/60">
                                    <td className="px-3 py-2">
                                        <span className="flex items-center gap-2.5">
                                            <span className="grid h-9 w-9 flex-none place-items-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md">
                                                <i className="fas fa-box text-xs" aria-hidden="true" />
                                            </span>
                                            <span className="min-w-0">
                                                <span className="block font-semibold text-slate-900">{i.product_name}</span>
                                                {i.product_code && <span className="block font-mono text-xs text-slate-400">{i.product_code}</span>}
                                            </span>
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 text-right tabular-nums text-slate-500">{i.disponivel.toLocaleString('pt-PT')} {i.unit}</td>
                                    <td className="w-32 px-3 py-2"><input type="number" min="0.01" step="0.01" value={i.quantity} onChange={(e) => mudarQuantidade(k, e.target.value)} aria-label={t('Quantidade de :artigo', { artigo: i.product_name })} className={cls(entrada, 'h-8 py-0 text-right font-bold tabular-nums')} /></td>
                                    <td className="px-3 py-2 text-right"><button type="button" onClick={() => aoMudar(itens.filter((_, j) => j !== k))} aria-label={t('Tirar :artigo', { artigo: i.product_name })} className={cls('grid h-8 w-8 place-items-center rounded-lg transition-all duration-200 hover:scale-110', CORES.perigo.suave, FOCO)}><i className="fas fa-trash" aria-hidden="true" /></button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}

/** O comprovativo: a referência e o resumo por artigo. */
export function Comprovativo({ titulo, referencias, resumo, pdf, colunas }: {
    titulo: string;
    referencias: Array<{ rotulo: string; valor: string }>;
    resumo: Array<Record<string, string | number | null>>;
    pdf: string | null;
    colunas: Array<{ chave: string; rotulo: string; numero?: boolean }>;
}) {
    return (
        <div className="space-y-4">
            <div className={cls('animate-scale-in border border-emerald-200 bg-emerald-50 p-5 text-center', RAIO)}>
                {/* O visto num círculo, como no ecrã de sempre: o sinal de que
                    acabou lê-se antes de qualquer texto. */}
                <div className="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-full bg-emerald-100">
                    <i className="fas fa-check text-2xl text-emerald-600" aria-hidden="true" />
                </div>
                <p className="text-lg font-bold text-emerald-900">{titulo}</p>
                {referencias.map((r) => (
                    <p key={r.rotulo} className="mt-1.5 text-sm text-emerald-800">
                        {r.rotulo}:{' '}
                        <span className="ml-1 inline-flex items-center gap-1.5 rounded-lg bg-white/70 px-2.5 py-1 font-mono font-bold" data-referencia>
                            <i className="fas fa-hashtag text-xs text-emerald-500" aria-hidden="true" />{r.valor}
                        </span>
                    </p>
                ))}
                {pdf && <a href={pdf} target="_blank" rel="noreferrer" className={cls('mt-4 inline-flex items-center gap-2 bg-gradient-to-r from-red-600 to-rose-600 px-5 py-2.5 text-sm font-bold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg', RAIO, FOCO)}><i className="fas fa-file-pdf text-base" aria-hidden="true" />{t('Comprovativo em PDF')}</a>}
            </div>
            <table className="w-full text-sm">
                <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600">{colunas.map((c) => <th key={c.chave} className={cls('px-3 py-2 font-bold', c.numero && 'text-right')}>{c.rotulo}</th>)}</tr></thead>
                <tbody className="divide-y divide-slate-100">
                    {resumo.map((l, i) => (
                        <tr key={i} style={cascata(i)} className="entra transition-all duration-200 hover:bg-indigo-50/60">{colunas.map((c) => <td key={c.chave} className={cls('px-3 py-2', c.numero && 'text-right tabular-nums')}>{typeof l[c.chave] === 'number' ? (l[c.chave] as number).toLocaleString('pt-PT') : (l[c.chave] ?? '')}</td>)}</tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
