import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { guias, type Guia, type LinhaDaGuia } from '@/api/guias';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls, data } from '@/ui/tokens';

/**
 * AS GUIAS DE TRANSPORTE E DE REMESSA — a lista e o registo.
 *
 * Uma guia acompanha mercadoria em trânsito: cliente, viatura, motorista,
 * onde carrega e onde descarrega, e as linhas. Pode nascer de uma factura,
 * copiando o cliente e as linhas dela. O número, a série, a assinatura e a
 * comunicação à AGT são do servidor (`EmissorDeGuias`), o mesmo que o ecrã
 * Livewire chama.
 */

const LINHA_NOVA: LinhaDaGuia = { product_id: null, product_name: '', description: '', quantity: 1, unit: 'un' };

export default function GuiasDeTransporte() {
    const cache = useQueryClient();
    const [procura, porProcura] = useState('');
    const [pagina, porPagina] = useState(1);
    const [aRegistar, porARegistar] = useState(false);
    const [aAnular, porAAnular] = useState<Guia | null>(null);
    const [recado, porRecado] = useState('');

    const opcoes = useQuery({ queryKey: ['guias', 'opcoes'], queryFn: guias.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({ queryKey: ['guias', { procura, pagina }], queryFn: () => guias.lista({ procura, page: pagina }), placeholderData: keepPreviousData });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['guias'] });

    const comunicar = useMutation({
        mutationFn: (g: Guia) => guias.comunicar(g.id),
        onSuccess: (r) => { invalidar(); porRecado(r.message); },
    });

    const anular = useMutation({
        mutationFn: (g: Guia) => guias.anular(g.id),
        onSuccess: (r) => { invalidar(); porAAnular(null); porRecado(r.message); },
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) {
        const erro = opcoes.error ?? lista.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir as guias</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : 'Verifique a ligação.'}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label="Fechar" className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <AvisoDeErro erro={comunicar.error ?? anular.error} />

            <Cartao
                titulo={<span className="flex items-center gap-2"><i className="fas fa-truck text-slate-400" aria-hidden="true" />Guias de Transporte</span>}
                accoes={o.permissoes.pode_criar && <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porARegistar(true)}>Nova guia</Botao>}
            >
                <label className="block max-w-md text-sm">
                    <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Procurar</span>
                    <input value={procura} onChange={(e) => { porProcura(e.target.value); porPagina(1); }} placeholder="Número da guia" className={entrada} />
                </label>
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                <th className="px-4 py-3 font-semibold">Número</th>
                                <th className="px-4 py-3 font-semibold">Tipo</th>
                                <th className="px-4 py-3 font-semibold">Cliente</th>
                                <th className="px-4 py-3 font-semibold">Data</th>
                                <th className="px-4 py-3 font-semibold">Estado</th>
                                <th className="px-4 py-3 font-semibold">AGT</th>
                                <th className="w-40 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className={cls('divide-y divide-slate-100', lista.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && <tr><td colSpan={7} className="px-4 py-10 text-center text-slate-400">{lista.isPending ? 'A carregar…' : 'Nenhuma guia.'}</td></tr>}
                            {linhas.map((g) => (
                                <tr key={g.id}>
                                    <td className="px-4 py-2 font-mono font-semibold text-slate-900">{g.numero}</td>
                                    <td className="px-4 py-2">{g.tipo_rotulo}</td>
                                    <td className="px-4 py-2">{g.cliente}</td>
                                    <td className="px-4 py-2 tabular-nums">{data(g.data)}</td>
                                    <td className="px-4 py-2"><Etiqueta cor={g.estado === 'cancelled' ? 'perigo' : 'bom'}>{g.estado === 'cancelled' ? 'Anulada' : 'Emitida'}</Etiqueta></td>
                                    <td className="px-4 py-2">
                                        {g.agt ? <Etiqueta cor="primaria" icone="fa-shield">{g.agt}</Etiqueta> : g.assinada ? <Etiqueta>Por comunicar</Etiqueta> : <Etiqueta cor="aviso">Sem assinatura</Etiqueta>}
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <span className="flex justify-end gap-1">
                                            <a href={g.pdf} target="_blank" rel="noreferrer" title="PDF" aria-label={`PDF de ${g.numero}`} className={cls('p-2 text-red-500 hover:bg-red-50', RAIO, FOCO)}><i className="fas fa-file-pdf" aria-hidden="true" /></a>
                                            {g.assinada && !g.agt && (
                                                <button type="button" onClick={() => comunicar.mutate(g)} title="Comunicar à AGT" aria-label={`Comunicar ${g.numero} à AGT`} className={cls('p-2 text-indigo-600 hover:bg-indigo-50', RAIO, FOCO)}><i className="fas fa-paper-plane" aria-hidden="true" /></button>
                                            )}
                                            <button type="button" onClick={() => porAAnular(g)} title="Anular" aria-label={`Anular ${g.numero}`} className={cls('p-2 text-slate-400 hover:text-red-600', RAIO, FOCO)}><i className="fas fa-ban" aria-hidden="true" /></button>
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {contas && contas.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm text-slate-500">
                        <span>Página {contas.current_page} de {contas.last_page} · {contas.total} guias</span>
                        <span className="flex gap-1">
                            <Botao icone="fa-chevron-left" disabled={contas.current_page <= 1} onClick={() => porPagina(contas.current_page - 1)}>Anterior</Botao>
                            <Botao icone="fa-chevron-right" disabled={contas.current_page >= contas.last_page} onClick={() => porPagina(contas.current_page + 1)}>Seguinte</Botao>
                        </span>
                    </div>
                )}
            </Cartao>

            {aRegistar && <NovaGuia aoFechar={() => porARegistar(false)} aoRegistar={(m) => { porARegistar(false); porRecado(m); invalidar(); }} />}

            <Modal
                aberto={aAnular !== null}
                aoFechar={() => porAAnular(null)}
                titulo="Anular a guia?"
                rodape={<><Botao onClick={() => porAAnular(null)}>Cancelar</Botao><Botao cor="perigo" tom="solida" icone="fa-ban" aTrabalhar={anular.isPending} onClick={() => aAnular && anular.mutate(aAnular)}>Anular</Botao></>}
            >
                <p className="text-sm text-slate-700">A guia <strong>{aAnular?.numero}</strong> fica anulada e sai da lista. O número fica gasto.</p>
            </Modal>
        </div>
    );
}

/* ─── O registo ─────────────────────────────────────────────────────── */

function NovaGuia({ aoFechar, aoRegistar }: { aoFechar: () => void; aoRegistar: (mensagem: string) => void }) {
    const opcoes = useQuery({ queryKey: ['guias', 'opcoes'], queryFn: guias.opcoes, staleTime: 5 * 60_000 });
    const [tipo, porTipo] = useState<'GT' | 'GR'>('GT');
    const [facturaId, porFacturaId] = useState('');
    const [clienteId, porClienteId] = useState('');
    const [dia, porDia] = useState(() => new Date().toISOString().slice(0, 10));
    const [carga, porCarga] = useState('');
    const [matricula, porMatricula] = useState('');
    const [motorista, porMotorista] = useState('');
    const [documento, porDocumento] = useState('');
    const [ondeCarrega, porOndeCarrega] = useState('');
    const [ondeDescarrega, porOndeDescarrega] = useState('');
    const [notas, porNotas] = useState('');
    const [linhas, porLinhas] = useState<LinhaDaGuia[]>([]);
    const [artigo, porArtigo] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});

    /* Escolher a factura de origem copia o cliente e as linhas dela. */
    const copiar = useMutation({
        mutationFn: (id: number) => guias.linhasDaFactura(id),
        onSuccess: (r) => { if (r.client_id) porClienteId(String(r.client_id)); porLinhas(r.linhas); },
    });

    const guardar = useMutation({
        mutationFn: () =>
            guias.guardar({
                type: tipo,
                invoice_id: Number(facturaId) || null,
                client_id: Number(clienteId) || null,
                issue_date: dia,
                loading_datetime: carga || null,
                vehicle_plate: matricula || null,
                driver_name: motorista || null,
                driver_document: documento || null,
                load_address: ondeCarrega || null,
                unload_address: ondeDescarrega || null,
                notes: notas || null,
                linhas,
            }),
        onSuccess: (r) => aoRegistar(r.message),
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const o = opcoes.data;

    const juntarArtigo = () => {
        const a = o?.artigos.find((x) => String(x.id) === artigo);
        if (!a) return;
        porLinhas((ls) => [...ls, { ...LINHA_NOVA, product_id: a.id, product_name: a.name, description: a.name, unit: a.unit ?? 'un' }]);
        porArtigo('');
    };

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo="Nova guia"
            largura="lg"
            rodape={<><Botao onClick={aoFechar}>Cancelar</Botao><Botao cor="primaria" tom="solida" icone="fa-truck" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>Registar guia</Botao></>}
        >
            <AvisoDeErro erro={guardar.error} />
            {!o ? <Carregando linhas={4} /> : (
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta="Tipo" erro={erros.type} obrigatorio>
                            <select value={tipo} onChange={(e) => porTipo(e.target.value as 'GT' | 'GR')} className={entrada}>
                                {o.tipos.map((t) => <option key={t.valor} value={t.valor}>{t.rotulo}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta="Factura de origem" erro={erros.invoice_id}>
                            <select value={facturaId} onChange={(e) => { porFacturaId(e.target.value); if (e.target.value) copiar.mutate(Number(e.target.value)); }} className={entrada}>
                                <option value="">Sem factura</option>
                                {o.facturas.map((f) => <option key={f.id} value={f.id}>{f.invoice_number}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta="Cliente" erro={erros.client_id} obrigatorio>
                            <select value={clienteId} onChange={(e) => porClienteId(e.target.value)} className={entrada}>
                                <option value="">Escolher…</option>
                                {o.clientes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta="Data" erro={erros.issue_date} obrigatorio>
                            <input type="date" value={dia} onChange={(e) => porDia(e.target.value)} className={entrada} />
                        </Campo>
                        <Campo etiqueta="Carga (data e hora)" erro={erros.loading_datetime}>
                            <input type="datetime-local" value={carga} onChange={(e) => porCarga(e.target.value)} className={entrada} />
                        </Campo>
                        <Campo etiqueta="Matrícula" erro={erros.vehicle_plate}>
                            <input value={matricula} onChange={(e) => porMatricula(e.target.value)} className={entrada} />
                        </Campo>
                        <Campo etiqueta="Motorista" erro={erros.driver_name}>
                            <input value={motorista} onChange={(e) => porMotorista(e.target.value)} className={entrada} />
                        </Campo>
                        <Campo etiqueta="Documento do motorista" erro={erros.driver_document}>
                            <input value={documento} onChange={(e) => porDocumento(e.target.value)} className={entrada} />
                        </Campo>
                        <Campo etiqueta="Local de carga" erro={erros.load_address}>
                            <input value={ondeCarrega} onChange={(e) => porOndeCarrega(e.target.value)} className={entrada} />
                        </Campo>
                        <Campo etiqueta="Local de descarga" erro={erros.unload_address}>
                            <input value={ondeDescarrega} onChange={(e) => porOndeDescarrega(e.target.value)} className={entrada} />
                        </Campo>
                        <Campo etiqueta="Observações" erro={erros.notes} className="sm:col-span-2">
                            <textarea rows={2} value={notas} onChange={(e) => porNotas(e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                        </Campo>
                    </div>

                    <div className={cls('border border-slate-200', RAIO)}>
                        <div className="flex items-center gap-2 border-b border-slate-200 px-3 py-2">
                            <select value={artigo} onChange={(e) => porArtigo(e.target.value)} aria-label="Artigo a juntar" className={cls(entrada, 'flex-1')}>
                                <option value="">Juntar artigo…</option>
                                {o.artigos.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                            </select>
                            <Botao icone="fa-plus" onClick={juntarArtigo} disabled={!artigo}>Juntar</Botao>
                        </div>
                        {linhas.length === 0 ? (
                            <p className="px-3 py-4 text-center text-sm text-slate-400">Sem linhas. Escolha uma factura de origem ou junte artigos.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <tbody className="divide-y divide-slate-100">
                                    {linhas.map((l, i) => (
                                        <tr key={i}>
                                            <td className="px-3 py-2">
                                                <input value={l.description} onChange={(e) => porLinhas((ls) => ls.map((x, j) => (j === i ? { ...x, description: e.target.value } : x)))} aria-label={`Descrição da linha ${i + 1}`} className={entrada} />
                                            </td>
                                            <td className="w-28 px-3 py-2">
                                                <input type="number" min="0" step="0.001" value={l.quantity} onChange={(e) => porLinhas((ls) => ls.map((x, j) => (j === i ? { ...x, quantity: e.target.value } : x)))} aria-label={`Quantidade da linha ${i + 1}`} className={cls(entrada, 'text-right tabular-nums')} />
                                            </td>
                                            <td className="w-20 px-3 py-2 text-slate-500">{l.unit}</td>
                                            <td className="w-12 px-3 py-2 text-right">
                                                <button type="button" onClick={() => porLinhas((ls) => ls.filter((_, j) => j !== i))} aria-label={`Apagar linha ${i + 1}`} className={cls('p-2 text-red-500 hover:bg-red-50', RAIO, FOCO)}><i className="fas fa-trash" aria-hidden="true" /></button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                        {erros.linhas?.[0] && <p role="alert" className="border-t border-red-100 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{erros.linhas[0]}</p>}
                    </div>
                </div>
            )}
        </Modal>
    );
}
