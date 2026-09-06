import { useEffect, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { stock, type ArtigoParaLote, type ItemDoLote, type LinhaDeStock, type OpcoesDoStock } from '@/api/stock';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

/**
 * A GESTÃO DE STOCK — o que há em cada armazém, e as três formas de lhe
 * mexer à mão: ajustar uma linha, transferir entre armazéns, e a
 * movimentação em lote (entradas e saídas com referência MOV/AAAA/NNNNNN).
 *
 * Os cartões e a lista saem da mesma consulta filtrada. As regras do stock
 * são do servidor (`MovimentacaoDeStock`, os ganchos do `StockMovement`):
 * este ecrã nunca soma nem subtrai — pede.
 */
export default function Stock() {
    const cache = useQueryClient();
    const [filtros, porFiltros] = useState<{ procura: string; armazem: string; baixo: boolean; conservacao: string; page: number }>({ procura: '', armazem: '', baixo: false, conservacao: '', page: 1 });
    const [aAjustar, porAAjustar] = useState<LinhaDeStock | null>(null);
    const [aTransferir, porATransferir] = useState<LinhaDeStock | null>(null);
    const [movimentosDe, porMovimentosDe] = useState<LinhaDeStock | null>(null);
    const [lote, porLote] = useState(false);
    const [recado, porRecado] = useState('');

    const opcoes = useQuery({ queryKey: ['stock', 'opcoes'], queryFn: stock.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({ queryKey: ['stock', filtros], queryFn: () => stock.lista(filtros), placeholderData: keepPreviousData });

    const feito = (m: string) => { porRecado(m); void cache.invalidateQueries({ queryKey: ['stock'] }); };

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) {
        const erro = opcoes.error ?? lista.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir o stock</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : 'Verifique a ligação.'}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const resumo = lista.data?.resumo;

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label="Fechar" className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            {resumo && (
                <div className="grid gap-3 sm:grid-cols-4">
                    <Numero rotulo="Artigos" valor={String(resumo.artigos)} icone="fa-boxes" />
                    <Numero rotulo="Unidades" valor={resumo.quantidade.toLocaleString('pt-PT')} icone="fa-cubes" />
                    <Numero rotulo="Valor ao custo" valor={`${kz(resumo.valor)} Kz`} icone="fa-coins" />
                    <Numero rotulo="Abaixo do mínimo" valor={String(resumo.baixo)} icone="fa-triangle-exclamation" alerta={resumo.baixo > 0} />
                </div>
            )}

            <Cartao
                titulo={<span className="flex items-center gap-2"><i className="fas fa-boxes text-slate-400" aria-hidden="true" />Gestão de Stock</span>}
                accoes={o.permissoes.pode_editar && <Botao cor="primaria" tom="solida" icone="fa-truck-ramp-box" onClick={() => porLote(true)}>Movimentação em lote</Botao>}
            >
                <div className="flex flex-wrap items-end gap-3">
                    <label className="min-w-[16rem] flex-1 text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Procurar</span>
                        <input value={filtros.procura} onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} placeholder="Nome ou código do artigo" className={entrada} />
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Armazém</span>
                        <select value={filtros.armazem} onChange={(e) => porFiltros((f) => ({ ...f, armazem: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">Todos</option>
                            {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </label>
                    {o.mostra_conservacao && (
                        <label className="text-sm">
                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Conservação</span>
                            <select value={filtros.conservacao} onChange={(e) => porFiltros((f) => ({ ...f, conservacao: e.target.value, page: 1 }))} className={entrada}>
                                <option value="">Todas</option>
                                {o.conservacao.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                            </select>
                        </label>
                    )}
                    <label className="flex items-center gap-2 pb-2 text-sm text-slate-700">
                        <input type="checkbox" checked={filtros.baixo} onChange={(e) => porFiltros((f) => ({ ...f, baixo: e.target.checked, page: 1 }))} className="h-4 w-4 rounded border-slate-300" />
                        Só abaixo do mínimo
                    </label>
                </div>
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                <th className="px-4 py-3 font-semibold">Artigo</th>
                                <th className="px-4 py-3 font-semibold">Armazém</th>
                                {o.mostra_conservacao && <th className="px-4 py-3 font-semibold">Conservação</th>}
                                <th className="px-4 py-3 text-right font-semibold">Quantidade</th>
                                <th className="px-4 py-3 text-right font-semibold">Mínimo</th>
                                <th className="px-4 py-3 text-right font-semibold">Custo</th>
                                <th className="px-4 py-3 text-right font-semibold">Valor</th>
                                <th className="w-36 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className={cls('divide-y divide-slate-100', lista.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && <tr><td colSpan={8} className="px-4 py-10 text-center text-slate-400">{lista.isPending ? 'A carregar…' : 'Sem stock com estes filtros.'}</td></tr>}
                            {linhas.map((l) => (
                                <tr key={l.id} className={cls(l.baixo && 'bg-amber-50/40')}>
                                    <td className="px-4 py-2">
                                        <div className="font-medium text-slate-900">{l.artigo}{l.conteudo && <span className="ml-1 text-xs text-slate-400">{l.conteudo}</span>}</div>
                                        {l.codigo && <div className="font-mono text-xs text-slate-400">{l.codigo}</div>}
                                    </td>
                                    <td className="px-4 py-2">{l.armazem}</td>
                                    {o.mostra_conservacao && <td className="px-4 py-2">{l.conservacao_rotulo ?? <span className="text-slate-300">—</span>}</td>}
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        <span className={cls('font-semibold', l.baixo ? 'text-amber-700' : 'text-slate-900')}>{l.quantidade.toLocaleString('pt-PT')}</span>
                                        {l.unidade && <span className="ml-1 text-xs text-slate-400">{l.unidade}</span>}
                                        {l.baixo && <span className="ml-2"><Etiqueta cor="aviso">baixo</Etiqueta></span>}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums text-slate-500">{l.minimo.toLocaleString('pt-PT')}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{kz(l.custo)}</td>
                                    <td className="px-4 py-2 text-right tabular-nums font-semibold">{kz(l.valor)}</td>
                                    <td className="px-4 py-2 text-right">
                                        <span className="flex justify-end gap-1">
                                            <button type="button" onClick={() => porMovimentosDe(l)} title="Movimentos" aria-label={`Movimentos de ${l.artigo ?? ''}`} className={cls('p-2 text-slate-400 hover:text-indigo-600', RAIO, FOCO)}><i className="fas fa-clock-rotate-left" aria-hidden="true" /></button>
                                            {o.permissoes.pode_editar && <button type="button" onClick={() => porAAjustar(l)} title="Ajustar" aria-label={`Ajustar ${l.artigo ?? ''}`} className={cls('p-2 text-slate-400 hover:text-amber-600', RAIO, FOCO)}><i className="fas fa-sliders" aria-hidden="true" /></button>}
                                            {o.permissoes.pode_transferir && o.armazens.length > 1 && <button type="button" onClick={() => porATransferir(l)} title="Transferir" aria-label={`Transferir ${l.artigo ?? ''}`} className={cls('p-2 text-slate-400 hover:text-emerald-600', RAIO, FOCO)}><i className="fas fa-right-left" aria-hidden="true" /></button>}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {contas && contas.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
                        <span>Página {contas.current_page} de {contas.last_page} · {contas.total} linhas</span>
                        <span className="flex gap-1">
                            <Botao icone="fa-chevron-left" disabled={contas.current_page <= 1} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}>Anterior</Botao>
                            <Botao icone="fa-chevron-right" disabled={contas.current_page >= contas.last_page} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}>Seguinte</Botao>
                        </span>
                    </div>
                )}
            </Cartao>

            {aAjustar && <Ajustar l={aAjustar} aoFechar={() => porAAjustar(null)} aoFeito={(m) => { porAAjustar(null); feito(m); }} />}
            {aTransferir && <Transferir l={aTransferir} o={o} aoFechar={() => porATransferir(null)} aoFeito={(m) => { porATransferir(null); feito(m); }} />}
            {movimentosDe && <Movimentos l={movimentosDe} aoFechar={() => porMovimentosDe(null)} />}
            {lote && <Lote o={o} armazemInicial={filtros.armazem || (o.armazem_padrao ? String(o.armazem_padrao) : '')} aoFechar={() => porLote(false)} aoFeito={feito} />}
        </div>
    );
}

function Numero({ rotulo, valor, icone, alerta = false }: { rotulo: string; valor: string; icone: string; alerta?: boolean }) {
    return (
        <div className={cls('flex items-center gap-3 border bg-white px-4 py-3', RAIO, alerta ? 'border-amber-300' : 'border-slate-200')}>
            <i className={cls('fas', icone, alerta ? 'text-amber-500' : 'text-slate-300')} aria-hidden="true" />
            <div>
                <p className="text-xs uppercase tracking-wider text-slate-500">{rotulo}</p>
                <p className="text-lg font-bold tabular-nums text-slate-900">{valor}</p>
            </div>
        </div>
    );
}

/* ─── Ajustar uma linha ─────────────────────────────────────────────── */

function Ajustar({ l, aoFechar, aoFeito }: { l: LinhaDeStock; aoFechar: () => void; aoFeito: (m: string) => void }) {
    const [nova, porNova] = useState(String(l.quantidade));
    const [notas, porNotas] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});

    const gravar = useMutation({
        mutationFn: () => stock.ajustar({ stock_id: l.id, nova_quantidade: Number(nova) || 0, notas: notas || null }),
        onSuccess: (r) => aoFeito(r.message),
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={`Ajustar ${l.artigo ?? ''}`} rodape={<><Botao onClick={aoFechar}>Cancelar</Botao><Botao cor="primaria" tom="solida" icone="fa-sliders" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>Ajustar</Botao></>}>
            <AvisoDeErro erro={gravar.error} />
            <p className="mb-4 text-sm text-slate-600">No armazém <strong>{l.armazem}</strong> há <strong className="tabular-nums">{l.quantidade.toLocaleString('pt-PT')}</strong>. O ajuste fica registado como movimento.</p>
            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta="Quantidade certa" erro={erros.nova_quantidade} obrigatorio>
                    <input type="number" min="0" step="0.01" value={nova} onChange={(e) => porNova(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                </Campo>
                <Campo etiqueta="Motivo" erro={erros.notas}>
                    <input value={notas} onChange={(e) => porNotas(e.target.value)} placeholder="Contagem física, avaria…" className={entrada} />
                </Campo>
            </div>
        </Modal>
    );
}

/* ─── Transferir entre armazéns ─────────────────────────────────────── */

function Transferir({ l, o, aoFechar, aoFeito }: { l: LinhaDeStock; o: OpcoesDoStock; aoFechar: () => void; aoFeito: (m: string) => void }) {
    const [para, porPara] = useState('');
    const [qtd, porQtd] = useState('');
    const [notas, porNotas] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});

    const gravar = useMutation({
        mutationFn: () => stock.transferir({ stock_id: l.id, para_armazem_id: Number(para) || 0, quantidade: Number(qtd) || 0, notas: notas || null }),
        onSuccess: (r) => aoFeito(r.message),
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={`Transferir ${l.artigo ?? ''}`} rodape={<><Botao onClick={aoFechar}>Cancelar</Botao><Botao cor="primaria" tom="solida" icone="fa-right-left" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>Transferir</Botao></>}>
            <AvisoDeErro erro={gravar.error} />
            <p className="mb-4 text-sm text-slate-600">De <strong>{l.armazem}</strong>, onde há <strong className="tabular-nums">{l.disponivel.toLocaleString('pt-PT')}</strong> disponível.</p>
            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta="Para o armazém" erro={erros.para_armazem_id} obrigatorio>
                    <select value={para} onChange={(e) => porPara(e.target.value)} className={entrada}>
                        <option value="">Escolher…</option>
                        {o.armazens.filter((a) => a.id !== l.warehouse_id).map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta="Quantidade" erro={erros.quantidade} obrigatorio>
                    <input type="number" min="0.01" step="0.01" max={l.disponivel} value={qtd} onChange={(e) => porQtd(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                </Campo>
                <Campo etiqueta="Observações" erro={erros.notas} className="sm:col-span-2">
                    <input value={notas} onChange={(e) => porNotas(e.target.value)} className={entrada} />
                </Campo>
            </div>
        </Modal>
    );
}

/* ─── Os movimentos de um artigo ────────────────────────────────────── */

function Movimentos({ l, aoFechar }: { l: LinhaDeStock; aoFechar: () => void }) {
    const q = useQuery({ queryKey: ['stock', 'movimentos', l.product_id], queryFn: () => stock.movimentos(l.product_id) });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={`Movimentos de ${l.artigo ?? ''}`} largura="lg" rodape={<Botao onClick={aoFechar}>Fechar</Botao>}>
            {q.isPending ? <Carregando linhas={5} /> : q.isError ? <AvisoDeErro erro={q.error} /> : (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                            <th className="px-3 py-2 font-semibold">Quando</th>
                            <th className="px-3 py-2 font-semibold">Tipo</th>
                            <th className="px-3 py-2 font-semibold">Armazém</th>
                            <th className="px-3 py-2 text-right font-semibold">Qtd.</th>
                            <th className="px-3 py-2 text-right font-semibold">Saldo</th>
                            <th className="px-3 py-2 font-semibold">Notas</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {q.data.data.length === 0 && <tr><td colSpan={6} className="px-3 py-6 text-center text-slate-400">Sem movimentos.</td></tr>}
                        {q.data.data.map((m) => (
                            <tr key={m.id}>
                                <td className="px-3 py-2 tabular-nums text-slate-600">{m.quando}</td>
                                <td className="px-3 py-2"><Etiqueta cor={m.tipo === 'in' ? 'bom' : m.tipo === 'out' ? 'perigo' : 'neutra'}>{m.tipo_rotulo}</Etiqueta></td>
                                <td className="px-3 py-2">{m.armazem}</td>
                                <td className="px-3 py-2 text-right tabular-nums">{m.quantidade.toLocaleString('pt-PT')}</td>
                                <td className="px-3 py-2 text-right tabular-nums text-slate-500">{m.saldo_depois?.toLocaleString('pt-PT') ?? ''}</td>
                                <td className="px-3 py-2 text-xs text-slate-500">{m.lote && <span className="mr-1 font-mono">{m.lote}</span>}{m.notas}{m.quem && <span className="ml-1 text-slate-400">· {m.quem}</span>}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </Modal>
    );
}

/* ─── A movimentação em lote ────────────────────────────────────────── */

function Lote({ o, armazemInicial, aoFechar, aoFeito }: { o: OpcoesDoStock; armazemInicial: string; aoFechar: () => void; aoFeito: (m: string) => void }) {
    const [armazem, porArmazem] = useState(armazemInicial);
    const [procura, porProcura] = useState('');
    const [sugestoes, porSugestoes] = useState<ArtigoParaLote[]>([]);
    const [itens, porItens] = useState<ItemDoLote[]>([]);
    const [notas, porNotas] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [resultado, porResultado] = useState<{ referencia: string; ok: number; erros: string[]; pdf: string } | null>(null);

    /* A procura pede ao servidor, com pausa. */
    useEffect(() => {
        const t = procura.trim();
        if (t === '') { porSugestoes([]); return; }
        let cancelado = false;
        const h = setTimeout(() => {
            stock.artigos(t, armazem || undefined)
                .then((r) => { if (!cancelado) porSugestoes(r.data.filter((a) => !itens.some((i) => i.product_id === a.id))); })
                .catch(() => { if (!cancelado) porSugestoes([]); });
        }, 300);
        return () => { cancelado = true; clearTimeout(h); };
    }, [procura, armazem, itens]);

    const juntar = (a: ArtigoParaLote) => {
        porItens((ls) => [...ls, { product_id: a.id, product_name: a.name, code: a.code, unit: a.unit, op: 'add', quantity: 1, unit_cost: a.cost || '', actual: a.actual }]);
        porProcura('');
        porSugestoes([]);
    };

    const gravar = useMutation({
        mutationFn: () => stock.entrada({
            armazem_id: Number(armazem) || 0,
            itens: itens.map((i) => ({ product_id: i.product_id, product_name: i.product_name, op: i.op, quantity: Number(i.quantity) || 0, unit_cost: Number(i.unit_cost) || 0 })),
            notas: notas || null,
        }),
        onSuccess: (r) => { porResultado(r); porErros({}); aoFeito(r.message); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (resultado) {
        return (
            <Modal aberto aoFechar={aoFechar} titulo="Movimentação registada" rodape={<><Botao onClick={aoFechar}>Fechar</Botao><Botao icone="fa-plus" onClick={() => { porResultado(null); porItens([]); porNotas(''); }}>Outra</Botao><Botao cor="primaria" tom="solida" icone="fa-file-pdf" onClick={() => window.open(resultado.pdf, '_blank')}>PDF do lote</Botao></>}>
                <div className={cls(CARTAO, 'p-6 text-center')}>
                    <i className="fas fa-circle-check mb-2 text-3xl text-emerald-500" aria-hidden="true" />
                    <p className="font-mono text-lg font-bold text-slate-900" data-referencia>{resultado.referencia}</p>
                    <p className="mt-1 text-sm text-slate-500">{resultado.ok} produto(s) actualizado(s).</p>
                    {resultado.erros.length > 0 && <ul className="mt-3 text-left text-sm text-red-700">{resultado.erros.map((e, i) => <li key={i}>{e}</li>)}</ul>}
                </div>
            </Modal>
        );
    }

    return (
        <Modal aberto aoFechar={aoFechar} titulo="Movimentação em lote" largura="lg" rodape={<><Botao onClick={aoFechar}>Cancelar</Botao><Botao cor="primaria" tom="solida" icone="fa-truck-ramp-box" aTrabalhar={gravar.isPending} disabled={itens.length === 0} onClick={() => gravar.mutate()}>Registar</Botao></>}>
            <AvisoDeErro erro={gravar.error} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta="Armazém" erro={erros.armazem_id} obrigatorio>
                    <select value={armazem} onChange={(e) => porArmazem(e.target.value)} className={entrada}>
                        <option value="">Escolher…</option>
                        {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta="Observações" erro={erros.notas}>
                    <input value={notas} onChange={(e) => porNotas(e.target.value)} placeholder="Contentor, guia do fornecedor…" className={entrada} />
                </Campo>
                <div className="relative sm:col-span-2">
                    <Campo etiqueta="Juntar artigo">
                        <input value={procura} onChange={(e) => porProcura(e.target.value)} placeholder="Nome, código ou código de barras" className={entrada} />
                    </Campo>
                    {sugestoes.length > 0 && (
                        <ul className={cls('absolute z-10 mt-1 max-h-60 w-full overflow-auto border border-slate-200 bg-white shadow-lg', RAIO)} role="listbox">
                            {sugestoes.map((a) => (
                                <li key={a.id}>
                                    <button type="button" onClick={() => juntar(a)} className={cls('flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-50', FOCO)}>
                                        <span>{a.name}{a.net_content && <span className="ml-1 text-xs text-slate-400">{a.net_content}</span>}{a.code && <span className="ml-2 font-mono text-xs text-slate-400">{a.code}</span>}</span>
                                        <span className="text-xs text-slate-500">tem {a.actual.toLocaleString('pt-PT')}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>

            <div className={cls('mt-4 border border-slate-200', RAIO)}>
                {itens.length === 0 ? <p className="px-3 py-4 text-center text-sm text-slate-400">Sem artigos. Procure e junte.</p> : (
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-3 py-2">Artigo</th><th className="px-3 py-2">Tem</th><th className="px-3 py-2">Op.</th><th className="px-3 py-2 text-right">Qtd.</th><th className="px-3 py-2 text-right">Custo</th><th className="w-10 px-3 py-2"></th></tr></thead>
                        <tbody className="divide-y divide-slate-100">
                            {itens.map((i, k) => (
                                <tr key={i.product_id}>
                                    <td className="px-3 py-2">{i.product_name}{i.code && <span className="ml-2 font-mono text-xs text-slate-400">{i.code}</span>}</td>
                                    <td className="px-3 py-2 tabular-nums text-slate-500">{i.actual.toLocaleString('pt-PT')}</td>
                                    <td className="px-3 py-2">
                                        <select value={i.op} onChange={(e) => porItens((ls) => ls.map((x, j) => (j === k ? { ...x, op: e.target.value as 'add' | 'sub' } : x)))} aria-label={`Operação de ${i.product_name}`} className={cls(entrada, 'h-8 py-0 text-xs')}>
                                            <option value="add">+ entrada</option>
                                            <option value="sub">− saída</option>
                                        </select>
                                    </td>
                                    <td className="px-3 py-2"><input type="number" min="0.01" step="0.01" value={i.quantity} onChange={(e) => porItens((ls) => ls.map((x, j) => (j === k ? { ...x, quantity: e.target.value } : x)))} aria-label={`Quantidade de ${i.product_name}`} className={cls(entrada, 'h-8 py-0 text-right tabular-nums')} /></td>
                                    <td className="px-3 py-2"><input type="number" min="0" step="0.01" value={i.unit_cost} onChange={(e) => porItens((ls) => ls.map((x, j) => (j === k ? { ...x, unit_cost: e.target.value } : x)))} aria-label={`Custo de ${i.product_name}`} className={cls(entrada, 'h-8 py-0 text-right tabular-nums')} /></td>
                                    <td className="px-3 py-2 text-right"><button type="button" onClick={() => porItens((ls) => ls.filter((_, j) => j !== k))} aria-label={`Tirar ${i.product_name}`} className={cls('p-1 text-red-500 hover:bg-red-50', RAIO, FOCO)}><i className="fas fa-trash" aria-hidden="true" /></button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
                {erros.itens?.[0] && <p role="alert" className="border-t border-red-100 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{erros.itens[0]}</p>}
            </div>
        </Modal>
    );
}
