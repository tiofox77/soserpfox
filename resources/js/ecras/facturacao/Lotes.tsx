import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { lotes, type Lote, type OpcoesDosLotes } from '@/api/lotes';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls, data, kz } from '@/ui/tokens';

/**
 * OS LOTES E AS VALIDADES.
 *
 * O que está a expirar sobe ao topo. Corrigir a quantidade de um lote não
 * altera o que já saiu dele — é o `GestorDeLotes`, no servidor, que faz
 * essa conta, o mesmo que o ecrã Livewire chama.
 */

type Forma = { product_id: string; warehouse_id: string; batch_number: string; manufacturing_date: string; expiry_date: string; quantity: string; cost_price: string; alert_days: string; notes: string };
const VAZIA: Forma = { product_id: '', warehouse_id: '', batch_number: '', manufacturing_date: '', expiry_date: '', quantity: '', cost_price: '', alert_days: '30', notes: '' };

export default function Lotes() {
    const cache = useQueryClient();
    const [filtros, porFiltros] = useState({ procura: '', produto: '', armazem: '', estado: '', page: 1 });
    const [aEditar, porAEditar] = useState<Lote | null>(null);
    const [forma, porForma] = useState<Forma | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aApagar, porAApagar] = useState<Lote | null>(null);
    const [recado, porRecado] = useState('');

    const opcoes = useQuery({ queryKey: ['lotes', 'opcoes'], queryFn: lotes.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({ queryKey: ['lotes', filtros], queryFn: () => lotes.lista(filtros), placeholderData: keepPreviousData });

    const feito = (m: string) => { porRecado(m); void cache.invalidateQueries({ queryKey: ['lotes'] }); };

    const gravar = useMutation({
        mutationFn: (f: Forma) => {
            const corpo = { ...f, product_id: Number(f.product_id) || null, warehouse_id: Number(f.warehouse_id) || null, quantity: Number(f.quantity) || 0, cost_price: Number(f.cost_price) || 0, alert_days: Number(f.alert_days) || 30, manufacturing_date: f.manufacturing_date || null, expiry_date: f.expiry_date || null };
            return aEditar ? lotes.actualizar(aEditar.id, corpo) : lotes.guardar(corpo);
        },
        onSuccess: (r) => { feito(r.message); porForma(null); porAEditar(null); porErros({}); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const apagar = useMutation({ mutationFn: (l: Lote) => lotes.apagar(l.id), onSuccess: (r) => { feito(r.message); porAApagar(null); } });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) {
        const erro = opcoes.error ?? lista.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir os lotes</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : 'Verifique a ligação.'}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const resumo = lista.data?.resumo;

    const abrirNovo = () => { porAEditar(null); porErros({}); porForma({ ...VAZIA }); };
    const abrirEdicao = (l: Lote) => {
        porAEditar(l); porErros({});
        porForma({ product_id: String(l.product_id), warehouse_id: l.warehouse_id ? String(l.warehouse_id) : '', batch_number: l.batch_number ?? '', manufacturing_date: l.manufacturing_date ?? '', expiry_date: l.expiry_date ?? '', quantity: String(l.quantity), cost_price: String(l.cost_price), alert_days: String(l.alert_days), notes: l.notes ?? '' });
    };

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label="Fechar" className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}
            <AvisoDeErro erro={apagar.error} />

            {resumo && (
                <div className="grid gap-3 sm:grid-cols-3">
                    <Numero rotulo="Lotes activos" valor={resumo.activos} icone="fa-boxes-stacked" />
                    <Numero rotulo="A expirar em breve" valor={resumo.a_expirar} icone="fa-hourglass-half" alerta={resumo.a_expirar > 0} />
                    <Numero rotulo="Expirados" valor={resumo.expirados} icone="fa-calendar-xmark" alerta={resumo.expirados > 0} />
                </div>
            )}

            <Cartao
                titulo={<span className="flex items-center gap-2"><i className="fas fa-calendar-check text-slate-400" aria-hidden="true" />Lotes e Validades</span>}
                accoes={o.permissoes.pode_criar && <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>Novo lote</Botao>}
            >
                <div className="flex flex-wrap items-end gap-3">
                    <label className="min-w-[14rem] flex-1 text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Procurar</span>
                        <input value={filtros.procura} onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} placeholder="Lote ou artigo" className={entrada} />
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Armazém</span>
                        <select value={filtros.armazem} onChange={(e) => porFiltros((f) => ({ ...f, armazem: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">Todos</option>
                            {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Estado</span>
                        <select value={filtros.estado} onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">Todos</option>
                            {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                        </select>
                    </label>
                </div>
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                <th className="px-4 py-3 font-semibold">Lote</th>
                                <th className="px-4 py-3 font-semibold">Artigo</th>
                                <th className="px-4 py-3 font-semibold">Armazém</th>
                                <th className="px-4 py-3 font-semibold">Validade</th>
                                <th className="px-4 py-3 text-right font-semibold">Disponível</th>
                                <th className="px-4 py-3 text-right font-semibold">Custo</th>
                                <th className="px-4 py-3 font-semibold">Estado</th>
                                <th className="w-24 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className={cls('divide-y divide-slate-100', lista.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && <tr><td colSpan={8} className="px-4 py-10 text-center text-slate-400">{lista.isPending ? 'A carregar…' : 'Nenhum lote.'}</td></tr>}
                            {linhas.map((l) => (
                                <tr key={l.id}>
                                    <td className="px-4 py-2 font-mono font-semibold text-slate-900">{l.batch_number ?? <span className="text-slate-300">—</span>}</td>
                                    <td className="px-4 py-2">{l.artigo}</td>
                                    <td className="px-4 py-2">{l.armazem}</td>
                                    <td className="px-4 py-2 tabular-nums">
                                        {l.expiry_date ? data(l.expiry_date) : <span className="text-slate-300">—</span>}
                                        {l.dias !== null && <span className={cls('ml-2 text-xs', l.dias < 0 ? 'text-red-600' : l.dias <= l.alert_days ? 'text-amber-600' : 'text-slate-400')}>{l.dias < 0 ? `há ${-l.dias} dias` : `${l.dias} dias`}</span>}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">{l.quantity_available.toLocaleString('pt-PT')} <span className="text-xs text-slate-400">/ {l.quantity.toLocaleString('pt-PT')} {l.unidade}</span></td>
                                    <td className="px-4 py-2 text-right tabular-nums">{kz(l.cost_price)}</td>
                                    <td className="px-4 py-2"><Etiqueta cor={l.status === 'expired' ? 'perigo' : l.status === 'active' ? 'bom' : 'neutra'}>{l.status === 'expired' ? 'Expirado' : l.status === 'active' ? 'Activo' : l.status}</Etiqueta></td>
                                    <td className="px-4 py-2 text-right">
                                        <span className="flex justify-end gap-1">
                                            {o.permissoes.pode_editar && <button type="button" onClick={() => abrirEdicao(l)} aria-label={`Editar lote ${l.batch_number ?? l.id}`} className={cls('p-2 text-slate-400 hover:text-indigo-600', RAIO, FOCO)}><i className="fas fa-pen" aria-hidden="true" /></button>}
                                            {o.permissoes.pode_apagar && <button type="button" disabled={!l.pode_apagar} onClick={() => porAApagar(l)} title={l.pode_apagar ? 'Apagar' : 'Já usado — não se apaga'} aria-label={`Apagar lote ${l.batch_number ?? l.id}`} className={cls('p-2 text-slate-400 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40', RAIO, FOCO)}><i className="fas fa-trash" aria-hidden="true" /></button>}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {contas && contas.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
                        <span>Página {contas.current_page} de {contas.last_page}</span>
                        <span className="flex gap-1">
                            <Botao icone="fa-chevron-left" disabled={contas.current_page <= 1} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}>Anterior</Botao>
                            <Botao icone="fa-chevron-right" disabled={contas.current_page >= contas.last_page} onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}>Seguinte</Botao>
                        </span>
                    </div>
                )}
            </Cartao>

            {forma && <Formulario o={o} forma={forma} erros={erros} titulo={aEditar ? `Editar lote ${aEditar.batch_number ?? ''}` : 'Novo lote'} aGravar={gravar.isPending} erroGeral={gravar.error} aoMudar={porForma} aoFechar={() => { porForma(null); porAEditar(null); }} aoGravar={() => gravar.mutate(forma)} />}

            <Modal aberto={aApagar !== null} aoFechar={() => porAApagar(null)} titulo="Apagar o lote?" rodape={<><Botao onClick={() => porAApagar(null)}>Cancelar</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar)}>Apagar</Botao></>}>
                <p className="text-sm text-slate-700">Vai apagar o lote <strong>{aApagar?.batch_number ?? aApagar?.id}</strong>. Não há volta.</p>
            </Modal>
        </div>
    );
}

function Numero({ rotulo, valor, icone, alerta = false }: { rotulo: string; valor: number; icone: string; alerta?: boolean }) {
    return (
        <div className={cls('flex items-center gap-3 border bg-white px-4 py-3', RAIO, alerta ? 'border-amber-300' : 'border-slate-200')}>
            <i className={cls('fas', icone, alerta ? 'text-amber-500' : 'text-slate-300')} aria-hidden="true" />
            <div><p className="text-xs uppercase tracking-wider text-slate-500">{rotulo}</p><p className="text-lg font-bold tabular-nums text-slate-900">{valor}</p></div>
        </div>
    );
}

function Formulario({ o, forma, erros, titulo, aGravar, erroGeral, aoMudar, aoFechar, aoGravar }: {
    o: OpcoesDosLotes; forma: Forma; erros: Record<string, string[]>; titulo: string; aGravar: boolean; erroGeral: unknown;
    aoMudar: (f: Forma) => void; aoFechar: () => void; aoGravar: () => void;
}) {
    const m = (chave: keyof Forma) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => aoMudar({ ...forma, [chave]: e.target.value });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={titulo} largura="lg" rodape={<><Botao onClick={aoFechar}>Cancelar</Botao><Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={aGravar} onClick={aoGravar}>Guardar</Botao></>}>
            <AvisoDeErro erro={erroGeral} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta="Artigo" erro={erros.product_id} obrigatorio>
                    <select value={forma.product_id} onChange={m('product_id')} className={entrada}>
                        <option value="">Escolher…</option>
                        {o.artigos.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta="Armazém" erro={erros.warehouse_id}>
                    <select value={forma.warehouse_id} onChange={m('warehouse_id')} className={entrada}>
                        <option value="">—</option>
                        {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta="Número do lote" erro={erros.batch_number}><input value={forma.batch_number} onChange={m('batch_number')} className={entrada} /></Campo>
                <Campo etiqueta="Quantidade" erro={erros.quantity} obrigatorio><input type="number" min="0" step="0.001" value={forma.quantity} onChange={m('quantity')} className={cls(entrada, 'text-right tabular-nums')} /></Campo>
                <Campo etiqueta="Fabrico" erro={erros.manufacturing_date}><input type="date" value={forma.manufacturing_date} onChange={m('manufacturing_date')} className={entrada} /></Campo>
                <Campo etiqueta="Validade" erro={erros.expiry_date}><input type="date" value={forma.expiry_date} onChange={m('expiry_date')} className={entrada} /></Campo>
                <Campo etiqueta="Custo unitário" erro={erros.cost_price}><input type="number" min="0" step="0.01" value={forma.cost_price} onChange={m('cost_price')} className={cls(entrada, 'text-right tabular-nums')} /></Campo>
                <Campo etiqueta="Avisar com antecedência (dias)" erro={erros.alert_days} obrigatorio><input type="number" min="1" max="365" value={forma.alert_days} onChange={m('alert_days')} className={cls(entrada, 'text-right tabular-nums')} /></Campo>
                <Campo etiqueta="Notas" erro={erros.notes} className="sm:col-span-2"><textarea rows={2} value={forma.notes} onChange={m('notes')} className={cls(entrada, 'h-auto py-2')} /></Campo>
            </div>
        </Modal>
    );
}
