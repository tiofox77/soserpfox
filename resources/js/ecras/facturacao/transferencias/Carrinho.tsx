import { useEffect, useState } from 'react';

import { transferencias, type ArtigoParaTransferir, type ItemDaTransferencia } from '@/api/transferencias';
import { Campo, entrada } from '@/ui/Campo';
import { FOCO, RAIO, cls } from '@/ui/tokens';

/**
 * O CARRINHO DE UMA TRANSFERÊNCIA OU DE UM AJUSTE: procurar artigos no
 * servidor (com o que há no armazém de origem) e juntá-los com quantidade.
 *
 * A quantidade limita-se ao disponível quando há tecto — quem escreveu 50
 * quando há 9 quer transferir o que houver; dizer-lhe quanto é mais útil do
 * que recusar. Sem tecto (um ajuste de entrada) aceita-se o que for.
 */
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
        if (comTecto && a.disponivel <= 0) { porAviso(`Não há ${a.name} neste armazém.`); return; }
        porAviso('');
        aoMudar([...itens, { product_id: a.id, product_name: a.name, product_code: a.code, unit: a.unit, quantity: comTecto ? Math.min(1, a.disponivel) : 1, disponivel: a.disponivel, unit_cost: a.custo }]);
        porProcura('');
    };

    const mudarQuantidade = (k: number, valor: string) => {
        const n = Number(valor);
        aoMudar(itens.map((i, j) => {
            if (j !== k) return i;
            if (valor === '') return { ...i, quantity: '' };
            if (!Number.isFinite(n) || n <= 0) { porAviso('A quantidade deve ser um número maior que zero.'); return i; }
            if (comTecto && n > i.disponivel) { porAviso(`Só há ${i.disponivel} de ${i.product_name} neste armazém. Ajustado para o disponível.`); return { ...i, quantity: i.disponivel }; }
            porAviso('');
            return { ...i, quantity: n };
        }));
    };

    return (
        <div className="space-y-3">
            <div className="relative">
                <Campo etiqueta="Juntar artigo">
                    <input value={procura} onChange={(e) => porProcura(e.target.value)} disabled={!armazem} placeholder={armazem ? 'Nome, código ou código de barras' : 'Escolha primeiro o armazém de origem'} className={entrada} />
                </Campo>
                {sugestoes.length > 0 && (
                    <ul className={cls('absolute z-10 mt-1 max-h-64 w-full overflow-auto border border-slate-200 bg-white shadow-lg', RAIO)} role="listbox">
                        {sugestoes.map((a) => (
                            <li key={a.id}>
                                <button type="button" onClick={() => juntar(a)} className={cls('flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-50', FOCO)}>
                                    <span>{a.name}{a.code && <span className="ml-2 font-mono text-xs text-slate-400">{a.code}</span>}</span>
                                    <span className={cls('text-xs', a.disponivel > 0 ? 'text-slate-500' : 'text-red-500')}>tem {a.disponivel.toLocaleString('pt-PT')}</span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {aviso && <p role="alert" className="text-sm text-amber-700">{aviso}</p>}

            <div className={cls('border border-slate-200', RAIO)}>
                {itens.length === 0 ? <p className="px-3 py-4 text-center text-sm text-slate-400">Sem artigos. Procure e junte.</p> : (
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-3 py-2">Artigo</th><th className="px-3 py-2 text-right">Tem</th><th className="px-3 py-2 text-right">Qtd.</th><th className="w-10 px-3 py-2"></th></tr></thead>
                        <tbody className="divide-y divide-slate-100">
                            {itens.map((i, k) => (
                                <tr key={i.product_id}>
                                    <td className="px-3 py-2">{i.product_name}{i.product_code && <span className="ml-2 font-mono text-xs text-slate-400">{i.product_code}</span>}</td>
                                    <td className="px-3 py-2 text-right tabular-nums text-slate-500">{i.disponivel.toLocaleString('pt-PT')} {i.unit}</td>
                                    <td className="w-32 px-3 py-2"><input type="number" min="0.01" step="0.01" value={i.quantity} onChange={(e) => mudarQuantidade(k, e.target.value)} aria-label={`Quantidade de ${i.product_name}`} className={cls(entrada, 'h-8 py-0 text-right tabular-nums')} /></td>
                                    <td className="px-3 py-2 text-right"><button type="button" onClick={() => aoMudar(itens.filter((_, j) => j !== k))} aria-label={`Tirar ${i.product_name}`} className={cls('p-1 text-red-500 hover:bg-red-50', RAIO, FOCO)}><i className="fas fa-trash" aria-hidden="true" /></button></td>
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
            <div className={cls('border border-emerald-200 bg-emerald-50 p-4 text-center', RAIO)}>
                <i className="fas fa-circle-check mb-2 text-3xl text-emerald-500" aria-hidden="true" />
                <p className="font-semibold text-emerald-900">{titulo}</p>
                {referencias.map((r) => (
                    <p key={r.rotulo} className="mt-1 text-sm text-emerald-800">{r.rotulo}: <span className="font-mono font-bold" data-referencia>{r.valor}</span></p>
                ))}
                {pdf && <a href={pdf} target="_blank" rel="noreferrer" className={cls('mt-3 inline-flex items-center gap-2 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700', RAIO, FOCO)}><i className="fas fa-file-pdf" aria-hidden="true" />Comprovativo em PDF</a>}
            </div>
            <table className="w-full text-sm">
                <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">{colunas.map((c) => <th key={c.chave} className={cls('px-3 py-2', c.numero && 'text-right')}>{c.rotulo}</th>)}</tr></thead>
                <tbody className="divide-y divide-slate-100">
                    {resumo.map((l, i) => (
                        <tr key={i}>{colunas.map((c) => <td key={c.chave} className={cls('px-3 py-2', c.numero && 'text-right tabular-nums')}>{typeof l[c.chave] === 'number' ? (l[c.chave] as number).toLocaleString('pt-PT') : (l[c.chave] ?? '')}</td>)}</tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
