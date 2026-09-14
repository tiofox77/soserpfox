import { useEffect, useRef, useState, type CSSProperties, type KeyboardEvent } from 'react';

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
 *
 * A LISTA DE SUGESTÕES SÓ ABRE QUANDO SE PROCURA. Estava sempre aberta e por
 * cima da tabela: carregava-se num artigo, a lista voltava a abrir sem ele e
 * tapava a linha onde se acerta a quantidade — parecia que não tinha entrado.
 * Agora fecha ao juntar, a linha nova acende e fica à vista, e o cursor volta
 * à procura (o leitor de código de barras continua a funcionar: Enter junta
 * o artigo quando só há um, ou quando o código é exactamente esse).
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
    const [resultado, porResultado] = useState<ArtigoParaTransferir[]>([]);
    const [aberta, porAberta] = useState(false);
    const [aProcurar, porAProcurar] = useState(false);
    const [aviso, porAviso] = useState('');
    /* A linha que acabou de entrar: acende e rola para a vista. */
    const [nova, porNova] = useState<number | null>(null);
    const caixa = useRef<HTMLInputElement>(null);
    const zona = useRef<HTMLDivElement>(null);
    const tabela = useRef<HTMLTableSectionElement>(null);

    /* A procura só corre com a lista aberta — e já não depende dos itens:
       mudar uma quantidade ia ao servidor a cada tecla. */
    useEffect(() => {
        if (!armazem || !aberta) { porResultado([]); return; }
        let cancelado = false;
        porAProcurar(true);
        const h = setTimeout(() => {
            transferencias.artigos({ procura: procura.trim(), armazem, so_com_stock: soComStock })
                .then((r) => { if (!cancelado) porResultado(r.data); })
                .catch(() => { if (!cancelado) porResultado([]); })
                .finally(() => { if (!cancelado) porAProcurar(false); });
        }, 300);
        return () => { cancelado = true; clearTimeout(h); };
    }, [procura, armazem, soComStock, aberta]);

    /* Fora da caixa e da lista, a lista fecha. */
    useEffect(() => {
        if (!aberta) return;
        const fora = (e: MouseEvent) => { if (zona.current && !zona.current.contains(e.target as Node)) porAberta(false); };
        document.addEventListener('mousedown', fora);
        return () => document.removeEventListener('mousedown', fora);
    }, [aberta]);

    /* A linha nova à vista, e o acender apaga-se sozinho. */
    useEffect(() => {
        if (nova === null) return;
        tabela.current?.querySelector(`[data-artigo="${nova}"]`)?.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' });
        const h = setTimeout(() => porNova(null), 1600);
        return () => clearTimeout(h);
    }, [nova]);

    const sugestoes = resultado.filter((a) => !itens.some((i) => i.product_id === a.id));

    const juntar = (a: ArtigoParaTransferir) => {
        if (comTecto && a.disponivel <= 0) { porAviso(t('Não há :artigo neste armazém.', { artigo: a.name })); return; }
        porAviso('');
        aoMudar([...itens, { product_id: a.id, product_name: a.name, product_code: a.code, unit: a.unit, quantity: comTecto ? Math.min(1, a.disponivel) : 1, disponivel: a.disponivel, unit_cost: a.custo }]);
        porProcura('');
        porAberta(false);
        porNova(a.id);
        caixa.current?.focus();
    };

    /* Enter junta quando não há dúvida: um só resultado, ou o código exacto (leitor de barras). */
    const aoTeclar = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Escape' && aberta) { e.preventDefault(); e.stopPropagation(); porAberta(false); return; }
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const termo = procura.trim().toLowerCase();
        const exacto = sugestoes.find((a) => (a.code ?? '').toLowerCase() === termo);
        const escolhido = exacto ?? (sugestoes.length === 1 ? sugestoes[0] : undefined);
        if (escolhido) juntar(escolhido);
    };

    const quantos = itens.reduce((soma, i) => soma + (Number(i.quantity) || 0), 0);

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

    /*
     * O − E O +: um de cada vez, sem ter de apagar e escrever. O − pára no 1
     * (para tirar o artigo há o caixote); o + pára no que o armazém tem quando
     * há tecto. As casas decimais de quem escreveu 2,5 mantêm-se: 2,5 + 1 = 3,5.
     */
    const passo = (k: number, delta: 1 | -1) => {
        const actual = Number(itens[k]?.quantity) || 0;
        const seguinte = Math.round((actual + delta) * 100) / 100;
        mudarQuantidade(k, String(delta < 0 ? Math.max(1, seguinte) : seguinte));
    };

    return (
        <div className="space-y-3">
            <div ref={zona}>
                <Campo etiqueta={t('Juntar artigo')}>
                    <span className="relative block">
                        <i className={cls('fas pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400', aProcurar && aberta ? 'fa-spinner fa-spin' : 'fa-magnifying-glass')} aria-hidden="true" />
                        <input
                            ref={caixa}
                            value={procura}
                            onChange={(e) => { porProcura(e.target.value); porAberta(true); }}
                            onClick={() => porAberta(true)}
                            onKeyDown={aoTeclar}
                            disabled={!armazem}
                            role="combobox"
                            aria-expanded={aberta && sugestoes.length > 0}
                            placeholder={armazem ? t('Nome, código ou código de barras') : t('Escolha primeiro o armazém de origem')}
                            className={cls(entrada, 'pl-9')}
                        />
                    </span>
                </Campo>
                {/* Na página e não por cima: dentro da janela, uma lista flutuante
                    era cortada pelo fundo e tapava o carrinho. */}
                {aberta && armazem && (sugestoes.length > 0 || (!aProcurar && procura.trim() !== '')) && (
                    <ul className={cls('animate-scale-in mt-1 max-h-64 w-full overflow-auto border border-slate-200 bg-white shadow-lg', RAIO)} role="listbox">
                        {sugestoes.length === 0 && (
                            <li className="px-3 py-3 text-center text-sm text-slate-400"><i className="fas fa-box-open mr-2" aria-hidden="true" />{t('Nenhum artigo encontrado.')}</li>
                        )}
                        {sugestoes.map((a, i) => (
                            <li key={a.id} style={cascata(i)} className="entra border-b border-slate-100 last:border-0">
                                <button type="button" onClick={() => juntar(a)} className={cls('group flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm transition-all duration-200 hover:bg-indigo-50/60', FOCO)}>
                                    <span className="min-w-0"><span className="font-semibold text-slate-900">{a.name}</span>{a.code && <span className="ml-2 font-mono text-xs text-slate-400">{a.code}</span>}</span>
                                    <span className="flex flex-none items-center gap-2">
                                        <span className={cls('text-xs font-semibold', a.disponivel > 0 ? 'text-slate-500' : 'text-red-500')}>
                                            <i className={cls('fas mr-1', a.disponivel > 0 ? 'fa-cubes' : 'fa-ban')} aria-hidden="true" />
                                            {t('tem :quanto', { quanto: a.disponivel.toLocaleString('pt-PT') })}
                                        </span>
                                        <span className="grid h-6 w-6 place-items-center rounded-full bg-indigo-100 text-indigo-600 opacity-0 transition-all duration-200 group-hover:scale-110 group-hover:opacity-100 group-focus-visible:opacity-100" aria-hidden="true"><i className="fas fa-plus text-[10px]" /></span>
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

            <div className={cls('overflow-hidden border border-slate-200', RAIO)}>
                {/* As contas fora da parte que rola: numa janela estreita a
                    tabela rola para o lado e o total ficava cortado. */}
                {itens.length > 0 && (
                    <div className="flex items-center justify-between border-b border-slate-200 bg-gradient-to-r from-indigo-50 to-violet-50 px-3 py-2 text-xs font-semibold text-indigo-900">
                        <span><i className="fas fa-cart-flatbed mr-2 text-indigo-500" aria-hidden="true" />{t('Artigos a movimentar')}</span>
                        <span className="tabular-nums" data-contas-do-carrinho>{t(':artigos artigo(s) · :unidades unidade(s)', { artigos: itens.length, unidades: quantos.toLocaleString('pt-PT') })}</span>
                    </div>
                )}
                {itens.length === 0 ? (
                    /* A caixa a tracejado do ecrã de sempre: falta alguma coisa,
                       mas não é um erro — é um convite. */
                    <div className={cls('m-2 border-2 border-dashed border-slate-200 p-6 text-center text-slate-400', RAIO)}>
                        <i className="fas fa-inbox mb-2 text-3xl" aria-hidden="true" />
                        <p className="text-sm">{t('Sem artigos. Procure e junte.')}</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                    <table className="min-w-[560px] w-full text-sm">
                        <thead><tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600"><th className="px-3 py-2 font-bold">{t('Artigo')}</th><th className="px-3 py-2 text-right font-bold">{t('Tem')}</th><th className="px-3 py-2 text-right font-bold">{t('Qtd.')}</th><th className="w-10 px-3 py-2"></th></tr></thead>
                        <tbody ref={tabela} className="divide-y divide-slate-100">
                            {itens.map((i, k) => (
                                <tr key={i.product_id} data-artigo={i.product_id} style={cascata(k)} className={cls('entra transition-all duration-500 hover:bg-indigo-50/60', nova === i.product_id && 'bg-emerald-50 ring-2 ring-inset ring-emerald-300')}>
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
                                    <td className="w-44 px-3 py-2">
                                        <span className="flex items-center justify-end gap-1" data-quantidade>
                                            <button
                                                type="button"
                                                onClick={() => passo(k, -1)}
                                                disabled={(Number(i.quantity) || 0) <= 1}
                                                aria-label={t('Menos um de :artigo', { artigo: i.product_name })}
                                                title={t('Menos um')}
                                                className={cls('grid h-8 w-8 flex-none place-items-center rounded-lg bg-slate-100 text-slate-600 transition-all duration-200 hover:scale-110 hover:bg-red-100 hover:text-red-700 active:scale-95 disabled:pointer-events-none disabled:opacity-40', FOCO)}
                                            >
                                                <i className="fas fa-minus text-xs" aria-hidden="true" />
                                            </button>
                                            <input
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                inputMode="decimal"
                                                value={i.quantity}
                                                onChange={(e) => mudarQuantidade(k, e.target.value)}
                                                onFocus={(e) => e.target.select()}
                                                aria-label={t('Quantidade de :artigo', { artigo: i.product_name })}
                                                className={cls(entrada, 'h-8 w-16 px-1 py-0 text-center font-bold tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none')}
                                            />
                                            <button
                                                type="button"
                                                onClick={() => passo(k, 1)}
                                                disabled={comTecto && (Number(i.quantity) || 0) >= i.disponivel}
                                                aria-label={t('Mais um de :artigo', { artigo: i.product_name })}
                                                title={comTecto && (Number(i.quantity) || 0) >= i.disponivel ? t('É tudo o que há neste armazém') : t('Mais um')}
                                                className={cls('grid h-8 w-8 flex-none place-items-center rounded-lg bg-indigo-100 text-indigo-700 transition-all duration-200 hover:scale-110 hover:bg-indigo-600 hover:text-white active:scale-95 disabled:pointer-events-none disabled:opacity-40', FOCO)}
                                            >
                                                <i className="fas fa-plus text-xs" aria-hidden="true" />
                                            </button>
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 text-right"><button type="button" onClick={() => aoMudar(itens.filter((_, j) => j !== k))} aria-label={t('Tirar :artigo', { artigo: i.product_name })} className={cls('grid h-8 w-8 place-items-center rounded-lg transition-all duration-200 hover:scale-110', CORES.perigo.suave, FOCO)}><i className="fas fa-trash" aria-hidden="true" /></button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    </div>
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
