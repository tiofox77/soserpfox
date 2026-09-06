import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { transferencias, type ItemDaTransferencia, type LoteDoHistorico, type OpcoesDasTransferencias, type Resumo } from '@/api/transferencias';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { Carrinho, Comprovativo } from '@/ecras/facturacao/transferencias/Carrinho';

/**
 * TRANSFERÊNCIAS ENTRE ARMAZÉNS E AJUSTES EM LOTE — e o histórico dos dois.
 *
 * Cada transferência ou ajuste é um lote com referência (MOV/AAAA/NNNNNN) e
 * comprovativo. O que acontece ao stock — os quatro saldos, as duas pernas,
 * os lotes por FEFO — é do servidor (`TransferenciaDeStock`), o mesmo que o
 * ecrã Livewire chama.
 */
export default function TransferenciasEntreArmazens() {
    const cache = useQueryClient();
    const [filtros, porFiltros] = useState({ procura: '', armazem: '', de: '', ate: '', page: 1 });
    const [modal, porModal] = useState<'transferir' | 'ajustar' | null>(null);
    const [detalhe, porDetalhe] = useState<LoteDoHistorico | null>(null);
    const [recado, porRecado] = useState('');

    const opcoes = useQuery({ queryKey: ['transferencias', 'opcoes'], queryFn: transferencias.opcoes, staleTime: 5 * 60_000 });
    const historico = useQuery({ queryKey: ['transferencias', 'historico', filtros], queryFn: () => transferencias.historico(filtros), placeholderData: keepPreviousData });

    const feito = (m: string) => { porRecado(m); void cache.invalidateQueries({ queryKey: ['transferencias'] }); };

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || historico.isError) {
        const erro = opcoes.error ?? historico.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir as transferências</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : 'Verifique a ligação.'}</p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = historico.data?.data ?? [];
    const contas = historico.data?.meta;

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label="Fechar" className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <Cartao
                titulo={<span className="flex items-center gap-2"><i className="fas fa-exchange-alt text-slate-400" aria-hidden="true" />Transferências e Ajustes de Stock</span>}
                accoes={
                    <span className="flex gap-2">
                        {o.permissoes.pode_ajustar && <Botao icone="fa-sliders" onClick={() => porModal('ajustar')}>Ajustar em lote</Botao>}
                        {o.permissoes.pode_transferir && <Botao cor="primaria" tom="solida" icone="fa-right-left" onClick={() => porModal('transferir')}>Transferir</Botao>}
                    </span>
                }
            >
                <div className="flex flex-wrap items-end gap-3">
                    <label className="min-w-[14rem] flex-1 text-sm"><span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Procurar</span><input value={filtros.procura} onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))} placeholder="Artigo" className={entrada} /></label>
                    <label className="text-sm"><span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Armazém</span>
                        <select value={filtros.armazem} onChange={(e) => porFiltros((f) => ({ ...f, armazem: e.target.value, page: 1 }))} className={entrada}><option value="">Todos</option>{o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}</select>
                    </label>
                    <label className="text-sm"><span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">De</span><input type="date" value={filtros.de} onChange={(e) => porFiltros((f) => ({ ...f, de: e.target.value, page: 1 }))} className={entrada} /></label>
                    <label className="text-sm"><span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Até</span><input type="date" value={filtros.ate} onChange={(e) => porFiltros((f) => ({ ...f, ate: e.target.value, page: 1 }))} className={entrada} /></label>
                </div>
            </Cartao>

            <Cartao semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-4 py-3 font-semibold">Referência</th><th className="px-4 py-3 font-semibold">Tipo</th><th className="px-4 py-3 font-semibold">Quando</th><th className="px-4 py-3 font-semibold">Armazém</th><th className="px-4 py-3 text-right font-semibold">Artigos</th><th className="px-4 py-3 text-right font-semibold">Unidades</th><th className="px-4 py-3 font-semibold">Quem</th><th className="w-24 px-4 py-3"></th></tr></thead>
                        <tbody className={cls('divide-y divide-slate-100', historico.isFetching && 'opacity-60')}>
                            {linhas.length === 0 && <tr><td colSpan={8} className="px-4 py-10 text-center text-slate-400">{historico.isPending ? 'A carregar…' : 'Sem movimentações.'}</td></tr>}
                            {linhas.map((l) => (
                                <tr key={l.id}>
                                    <td className="px-4 py-2 font-mono font-semibold text-slate-900">{l.referencia ?? <span className="text-slate-400">#{l.reference_id}</span>}</td>
                                    <td className="px-4 py-2"><Etiqueta cor={l.tipo === 'transfer' ? 'primaria' : 'aviso'}>{l.tipo_rotulo}</Etiqueta></td>
                                    <td className="px-4 py-2 tabular-nums text-slate-600">{l.quando}</td>
                                    <td className="px-4 py-2">{l.armazem}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{l.produtos}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">{l.quantidade.toLocaleString('pt-PT')}</td>
                                    <td className="px-4 py-2">{l.quem}</td>
                                    <td className="px-4 py-2 text-right">
                                        <span className="flex justify-end gap-1">
                                            <button type="button" onClick={() => porDetalhe(l)} title="Detalhe" aria-label={`Detalhe de ${l.referencia ?? l.reference_id}`} className={cls('p-2 text-slate-400 hover:text-indigo-600', RAIO, FOCO)}><i className="fas fa-list" aria-hidden="true" /></button>
                                            {l.pdf && <a href={l.pdf} target="_blank" rel="noreferrer" title="PDF" aria-label={`PDF de ${l.referencia}`} className={cls('p-2 text-red-500 hover:bg-red-50', RAIO, FOCO)}><i className="fas fa-file-pdf" aria-hidden="true" /></a>}
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

            {modal === 'transferir' && <Transferir o={o} aoFechar={() => porModal(null)} aoFeito={feito} />}
            {modal === 'ajustar' && <Ajustar o={o} aoFechar={() => porModal(null)} aoFeito={feito} />}
            {detalhe && <Detalhe l={detalhe} aoFechar={() => porDetalhe(null)} />}
        </div>
    );
}

function Transferir({ o, aoFechar, aoFeito }: { o: OpcoesDasTransferencias; aoFechar: () => void; aoFeito: (m: string) => void }) {
    const [de, porDe] = useState('');
    const [para, porPara] = useState('');
    const [notas, porNotas] = useState('');
    const [itens, porItens] = useState<ItemDaTransferencia[]>([]);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [feito, porFeito] = useState<{ referencia: string; resumo: Resumo[]; pdf: string } | null>(null);

    const gravar = useMutation({
        mutationFn: () => transferencias.entreArmazens({ de: Number(de) || null, para: Number(para) || null, notas: notas || null, itens: itens.map((i) => ({ product_id: i.product_id, product_name: i.product_name, product_code: i.product_code, quantity: Number(i.quantity) || 0 })) }),
        onSuccess: (r) => { porFeito(r); aoFeito(r.message); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={feito ? 'Transferência registada' : 'Transferir entre armazéns'} largura="lg" rodape={feito ? <Botao onClick={aoFechar}>Fechar</Botao> : <><Botao onClick={aoFechar}>Cancelar</Botao><Botao cor="primaria" tom="solida" icone="fa-right-left" aTrabalhar={gravar.isPending} disabled={itens.length === 0} onClick={() => gravar.mutate()}>Transferir</Botao></>}>
            {feito ? (
                <Comprovativo titulo="Transferência registada" referencias={[{ rotulo: 'Referência', valor: feito.referencia }]} resumo={feito.resumo} pdf={feito.pdf} colunas={[{ chave: 'produto', rotulo: 'Artigo' }, { chave: 'quantidade', rotulo: 'Qtd.', numero: true }, { chave: 'origem_antes', rotulo: 'Origem antes', numero: true }, { chave: 'origem_depois', rotulo: 'Origem depois', numero: true }, { chave: 'destino_antes', rotulo: 'Destino antes', numero: true }, { chave: 'destino_depois', rotulo: 'Destino depois', numero: true }]} />
            ) : (
                <div className="space-y-4">
                    <AvisoDeErro erro={gravar.error} />
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Campo etiqueta="Do armazém" erro={erros.de} obrigatorio><select value={de} onChange={(e) => { porDe(e.target.value); porItens([]); }} className={entrada}><option value="">Escolher…</option>{o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}</select></Campo>
                        <Campo etiqueta="Para o armazém" erro={erros.para} obrigatorio><select value={para} onChange={(e) => porPara(e.target.value)} className={entrada}><option value="">Escolher…</option>{o.armazens.filter((a) => String(a.id) !== de).map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}</select></Campo>
                        <Campo etiqueta="Observações" erro={erros.notas}><input value={notas} onChange={(e) => porNotas(e.target.value)} className={entrada} /></Campo>
                    </div>
                    <Carrinho armazem={de} itens={itens} aoMudar={porItens} comTecto />
                    {erros.itens?.[0] && <p role="alert" className="text-sm font-medium text-red-700">{erros.itens[0]}</p>}
                </div>
            )}
        </Modal>
    );
}

function Ajustar({ o, aoFechar, aoFeito }: { o: OpcoesDasTransferencias; aoFechar: () => void; aoFeito: (m: string) => void }) {
    const [armazem, porArmazem] = useState('');
    const [tipo, porTipo] = useState<'in' | 'out'>('in');
    const [motivo, porMotivo] = useState('');
    const [itens, porItens] = useState<ItemDaTransferencia[]>([]);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [feito, porFeito] = useState<{ referencia: string; resumo: Resumo[]; pdf: string } | null>(null);

    const gravar = useMutation({
        mutationFn: () => transferencias.ajuste({ armazem: Number(armazem) || null, tipo, motivo, itens: itens.map((i) => ({ product_id: i.product_id, product_name: i.product_name, product_code: i.product_code, quantity: Number(i.quantity) || 0 })) }),
        onSuccess: (r) => { porFeito(r); aoFeito(r.message); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={feito ? 'Ajuste registado' : 'Ajustar stock em lote'} largura="lg" rodape={feito ? <Botao onClick={aoFechar}>Fechar</Botao> : <><Botao onClick={aoFechar}>Cancelar</Botao><Botao cor="primaria" tom="solida" icone="fa-sliders" aTrabalhar={gravar.isPending} disabled={itens.length === 0} onClick={() => gravar.mutate()}>Ajustar</Botao></>}>
            {feito ? (
                <Comprovativo titulo="Ajuste registado" referencias={[{ rotulo: 'Referência', valor: feito.referencia }]} resumo={feito.resumo} pdf={feito.pdf} colunas={[{ chave: 'produto', rotulo: 'Artigo' }, { chave: 'quantidade', rotulo: 'Qtd.', numero: true }, { chave: 'antes', rotulo: 'Antes', numero: true }, { chave: 'depois', rotulo: 'Depois', numero: true }]} />
            ) : (
                <div className="space-y-4">
                    <AvisoDeErro erro={gravar.error} />
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Campo etiqueta="Armazém" erro={erros.armazem} obrigatorio><select value={armazem} onChange={(e) => { porArmazem(e.target.value); porItens([]); }} className={entrada}><option value="">Escolher…</option>{o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}</select></Campo>
                        <Campo etiqueta="Sentido" erro={erros.tipo} obrigatorio><select value={tipo} onChange={(e) => { porTipo(e.target.value as 'in' | 'out'); porItens([]); }} className={entrada}><option value="in">Entrada (acrescenta)</option><option value="out">Saída (retira)</option></select></Campo>
                        <Campo etiqueta="Motivo" erro={erros.motivo} obrigatorio><input value={motivo} onChange={(e) => porMotivo(e.target.value)} placeholder="Contagem, avaria, oferta…" className={entrada} /></Campo>
                    </div>
                    {/* O tecto só existe na saída: numa entrada acrescenta-se o que for preciso. */}
                    <Carrinho armazem={armazem} itens={itens} aoMudar={porItens} comTecto={tipo === 'out'} soComStock={tipo === 'out'} />
                    {erros.itens?.[0] && <p role="alert" className="text-sm font-medium text-red-700">{erros.itens[0]}</p>}
                </div>
            )}
        </Modal>
    );
}

function Detalhe({ l, aoFechar }: { l: LoteDoHistorico; aoFechar: () => void }) {
    const q = useQuery({ queryKey: ['transferencias', 'detalhes', l.referencia, l.reference_id], queryFn: () => transferencias.detalhes({ referencia: l.referencia, reference_id: l.referencia ? null : l.reference_id }) });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={`Lote ${l.referencia ?? '#' + l.reference_id}`} largura="lg" rodape={<Botao onClick={aoFechar}>Fechar</Botao>}>
            {q.isPending ? <Carregando linhas={4} /> : q.isError ? <AvisoDeErro erro={q.error} /> : (
                <table className="w-full text-sm">
                    <thead><tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500"><th className="px-3 py-2">Artigo</th><th className="px-3 py-2">Armazém</th><th className="px-3 py-2 text-right">Qtd.</th><th className="px-3 py-2 text-right">Antes</th><th className="px-3 py-2 text-right">Depois</th><th className="px-3 py-2">Notas</th></tr></thead>
                    <tbody className="divide-y divide-slate-100">
                        {q.data.data.map((m) => (
                            <tr key={m.id}><td className="px-3 py-2">{m.artigo}</td><td className="px-3 py-2">{m.armazem}</td><td className={cls('px-3 py-2 text-right tabular-nums', m.quantidade < 0 ? 'text-red-600' : 'text-emerald-700')}>{m.quantidade.toLocaleString('pt-PT')}</td><td className="px-3 py-2 text-right tabular-nums text-slate-500">{m.antes?.toLocaleString('pt-PT') ?? ''}</td><td className="px-3 py-2 text-right tabular-nums">{m.depois?.toLocaleString('pt-PT') ?? ''}</td><td className="px-3 py-2 text-xs text-slate-500">{m.notas}</td></tr>
                        ))}
                    </tbody>
                </table>
            )}
        </Modal>
    );
}
