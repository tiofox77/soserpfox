import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { pagamentosAFornecedores, type PedidoDePagamento } from '@/api/compras';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { PorPagina } from '@/ui/FiltrosComuns';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

import { Faixa } from '../facturacao/faixa';

/**
 * OS PAGAMENTOS A FORNECEDORES — o lado da tesouraria (27/09/2026).
 *
 * É aqui que chegam os pedidos das Compras. O tesoureiro vê o que tem para
 * pagar (os atrasados primeiro, pelo prazo), abre a encomenda de onde vem —
 * para ler, não para mudar — e paga: escolhe a conta ou a caixa, a forma e a
 * data. O dinheiro SAI DA TESOURARIA com recibo de compra; se ainda não há
 * factura, fica como adiantamento e liga-se a ela quando for emitida.
 *
 * O SALDO de cada conta e caixa está à vista na escolha, e o ecrã avisa antes
 * de deixar uma conta a descoberto — o servidor não impede, o tesoureiro decide.
 */

const COR: Record<PedidoDePagamento['estado'], 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    em_aprovacao: 'aviso',
    por_pagar: 'primaria',
    pago: 'bom',
    recusado: 'perigo',
    cancelado: 'neutra',
};

type Filtros = { estado: string; procura: string; meus: boolean; por_pagina: number; page: number };

export default function Pagamentos() {
    const cache = useQueryClient();
    const [filtros, porFiltros] = useState<Filtros>({ estado: 'por_pagar', procura: '', meus: false, por_pagina: 15, page: 1 });
    const [recado, porRecado] = useRecadoNoCanto('');
    const [aVer, porAVer] = useState<number | null>(null);
    const [aPagar, porAPagar] = useState<PedidoDePagamento | null>(null);
    const [aDevolver, porADevolver] = useState<PedidoDePagamento | null>(null);
    const [aDecidir, porADecidir] = useState<{ p: PedidoDePagamento; aprova: boolean } | null>(null);

    const opcoes = useQuery({ queryKey: ['tesouraria', 'pagamentos', 'opcoes'], queryFn: () => pagamentosAFornecedores.opcoes() });
    const lista = useQuery({
        queryKey: ['tesouraria', 'pagamentos', filtros],
        queryFn: () => pagamentosAFornecedores.lista(filtros),
        placeholderData: keepPreviousData,
    });

    const feito = (m: string) => {
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['tesouraria'] });
        void cache.invalidateQueries({ queryKey: ['compras'] });
    };

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const resumo = lista.data?.resumo;
    const meta = lista.data?.meta;

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Pagamentos a Fornecedores')}
                subtitulo={t('Os pedidos das Compras — paga-se aqui, e o dinheiro sai da tesouraria')}
                icone="fa-hand-holding-dollar"
                cor="teal"
            />

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <CartaoNumero aspecto="claro" rotulo={t('Por pagar')} valor={String(resumo.por_pagar)} icone="fa-hourglass-half" tom="indigo" />
                    <CartaoNumero aspecto="claro" rotulo={t('Valor por pagar')} valor={kz(resumo.valor_por_pagar)} sufixo="Kz" icone="fa-sack-dollar" tom="ambar" />
                    <CartaoNumero aspecto="claro" rotulo={t('Prazo passado')} valor={String(resumo.atrasados)} icone="fa-triangle-exclamation"
                        tom={resumo.atrasados > 0 ? 'vermelho' : 'verde'} />
                    <CartaoNumero aspecto="claro" rotulo={t('Pago este mês')} valor={kz(resumo.pagos_no_mes)} sufixo="Kz" icone="fa-circle-check" tom="verde"
                        nota={resumo.em_aprovacao > 0 ? t(':n à espera de aprovação', { n: String(resumo.em_aprovacao) }) : undefined} />
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('Procurar')} className="min-w-[14rem] flex-1">
                    <input id="pag-procura" type="search" value={filtros.procura}
                        onChange={(e) => porFiltros({ ...filtros, procura: e.target.value, page: 1 })}
                        placeholder={t('Pedido, encomenda ou fornecedor…')} className={entrada} />
                </Campo>
                <Campo etiqueta={t('Estado')} className="w-56">
                    <select id="pag-estado" value={filtros.estado}
                        onChange={(e) => porFiltros({ ...filtros, estado: e.target.value, page: 1 })} className={entrada}>
                        {o.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        <option value="todos">{t('Todos')}</option>
                    </select>
                </Campo>
                <label htmlFor="pag-meus" className="flex h-10 cursor-pointer items-center gap-2 text-sm text-slate-700">
                    <input id="pag-meus" type="checkbox" checked={filtros.meus}
                        onChange={(e) => porFiltros({ ...filtros, meus: e.target.checked, page: 1 })} />
                    {t('Só os entregues a mim')}
                </label>
            </div>

            {lista.isPending ? (
                <Carregando linhas={6} />
            ) : lista.isError ? (
                <AvisoDeErro erro={lista.error} />
            ) : lista.data.data.length === 0 ? (
                <SemNada
                    icone="fa-hand-holding-dollar"
                    titulo={filtros.estado === 'por_pagar' ? t('Nada por pagar') : t('Nenhum pedido')}
                    frase={t('Os pedidos de pagamento chegam das Encomendas de Compra, quando quem compra carrega em «Pagamento».')}
                />
            ) : (
                <div className={cls('overflow-x-auto', CARTAO)}>
                    <table className="w-full min-w-[56rem] text-sm">
                        <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                            <tr>
                                <th className="px-4 py-3 text-left">{t('Pedido')}</th>
                                <th className="px-4 py-3 text-left">{t('Fornecedor')}</th>
                                <th className="px-4 py-3 text-right">{t('Valor')}</th>
                                <th className="px-4 py-3 text-left">{t('Pagar até')}</th>
                                <th className="px-4 py-3 text-left">{t('Pedido por')}</th>
                                <th className="px-4 py-3 text-left">{t('Estado')}</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {lista.data.data.map((p, i) => (
                                <tr key={p.id} style={cascata(i)} className="entra transition hover:bg-slate-50/70">
                                    <td className="px-4 py-3">
                                        <p className="font-mono font-semibold text-slate-800">{p.numero}</p>
                                        {p.encomenda && <p className="font-mono text-xs text-slate-500">{p.encomenda.numero}</p>}
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">{p.fornecedor ?? '—'}</td>
                                    <td className="px-4 py-3 text-right font-semibold tabular-nums text-slate-900">{kz(p.valor)}</td>
                                    <td className="px-4 py-3">
                                        <span className={cls('tabular-nums', p.atrasado ? 'font-bold text-red-600' : 'text-slate-600')}>{p.data_limite ?? '—'}</span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {p.pedido_por ?? '—'}
                                        {p.tesoureiro && <p className="text-xs text-slate-400">{t('para :q', { q: p.tesoureiro })}</p>}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Etiqueta cor={COR[p.estado]}>{p.estado_rotulo}</Etiqueta>
                                        {p.aprovacao && (
                                            <p className="mt-1 text-xs tabular-nums text-slate-500">
                                                {t(':s de :n aprovações', { s: String(p.aprovacao.sins), n: String(p.aprovacao.necessarias) })}
                                            </p>
                                        )}
                                        {p.estado === 'pago' && <p className="mt-1 text-xs text-slate-500">{p.pago_por} · {p.recibo}</p>}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1.5">
                                            <Botao altura="pequeno" icone="fa-eye" onClick={() => porAVer(p.id)} aria-label={t('Ver o pedido')} />
                                            {p.aprovacao?.pode_votar && (
                                                <>
                                                    <Botao altura="pequeno" cor="bom" tom="solida" icone="fa-check" onClick={() => porADecidir({ p, aprova: true })}>
                                                        {t('Aprovar')}
                                                    </Botao>
                                                    <Botao altura="pequeno" cor="perigo" icone="fa-xmark" onClick={() => porADecidir({ p, aprova: false })}
                                                        aria-label={t('Recusar o pagamento')} />
                                                </>
                                            )}
                                            {o.permissoes.pode_pagar && p.estado === 'por_pagar' && (
                                                <Botao altura="pequeno" cor="bom" tom="solida" icone="fa-money-bill-transfer" onClick={() => porAPagar(p)}>
                                                    {t('Pagar')}
                                                </Botao>
                                            )}
                                            {o.permissoes.pode_pagar && ['por_pagar', 'em_aprovacao'].includes(p.estado) && (
                                                <Botao altura="pequeno" cor="perigo" icone="fa-rotate-left" onClick={() => porADevolver(p)}
                                                    aria-label={t('Devolver a quem pediu')} />
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {meta && meta.last_page > 1 && (
                <Paginacao
                    pagina={meta.current_page}
                    ultima={meta.last_page}
                    aMudar={(pg) => porFiltros({ ...filtros, page: pg })}
                    total={meta.total}
                    de={meta.from}
                    ate={meta.to}
                    aCarregar={lista.isFetching}
                    emCartao
                    extra={<PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros({ ...filtros, por_pagina: n, page: 1 })} />}
                />
            )}

            <FichaDoPedido id={aVer} aoFechar={() => porAVer(null)} />

            <Pagar
                pedido={aPagar}
                formas={o.formas}
                contas={o.contas}
                caixas={o.caixas}
                aoFechar={() => porAPagar(null)}
                aoFeito={(m) => { feito(m); porAPagar(null); }}
            />

            <Devolver pedido={aDevolver} aoFechar={() => porADevolver(null)} aoFeito={(m) => { feito(m); porADevolver(null); }} />

            <Decidir alvo={aDecidir} aoFechar={() => porADecidir(null)} aoFeito={(m) => { feito(m); porADecidir(null); }} />
        </div>
    );
}

/* ─── Pagar ───────────────────────────────────────────────────────────── */

function Pagar({ pedido, formas, contas, caixas, aoFechar, aoFeito }: {
    pedido: PedidoDePagamento | null;
    formas: Array<{ valor: string; rotulo: string }>;
    contas: Array<{ id: number; nome: string; saldo: number }>;
    caixas: Array<{ id: number; nome: string; saldo: number; aberta?: boolean }>;
    aoFechar: () => void;
    aoFeito: (m: string) => void;
}) {
    const hoje = new Date().toISOString().slice(0, 10);
    const [dados, porDados] = useState({ forma: 'transfer', destino: '', data: hoje, referencia: '' });
    const [preenchido, porPreenchido] = useState<number | null>(null);

    if (pedido && preenchido !== pedido.id) {
        porPreenchido(pedido.id);
        const forma = pedido.forma_sugerida ?? 'transfer';
        const lista = forma === 'cash' ? caixas : contas;
        porDados({ forma, destino: lista[0] ? String(lista[0].id) : '', data: hoje, referencia: '' });
    }

    const emNumerario = dados.forma === 'cash';
    const destinos = emNumerario ? caixas : contas;
    const escolhido = destinos.find((d) => String(d.id) === dados.destino);
    const descoberto = pedido && escolhido ? escolhido.saldo - pedido.valor : null;

    const pagar = useMutation({
        mutationFn: () => pagamentosAFornecedores.pagar(pedido!.id, {
            forma: dados.forma,
            account_id: !emNumerario && dados.destino ? Number(dados.destino) : null,
            cash_register_id: emNumerario && dados.destino ? Number(dados.destino) : null,
            data: dados.data || null,
            referencia: dados.referencia.trim() || null,
        }),
        onSuccess: (r) => { porPreenchido(null); aoFeito(r.message); },
    });

    const erros = (pagar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};
    const fechar = () => { porPreenchido(null); aoFechar(); };

    return (
        <Modal
            aberto={pedido !== null}
            aoFechar={fechar}
            titulo={t('Pagar ao fornecedor')}
            subtitulo={pedido ? `${pedido.numero} · ${pedido.fornecedor ?? ''}` : undefined}
            icone="fa-money-bill-transfer"
            cor="teal"
            largura="md"
            rodape={
                <>
                    <Botao onClick={fechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={pagar.isPending} disabled={!dados.destino}
                        onClick={() => pagar.mutate()}>
                        {pedido ? t('Pagar :v Kz', { v: kz(pedido.valor) }) : t('Pagar')}
                    </Botao>
                </>
            }
        >
            {pedido && (
                <div className="space-y-4">
                    <AvisoDeErro erro={pagar.error} />

                    <div className={cls('flex flex-wrap items-baseline justify-between gap-2 border border-teal-200 bg-teal-50 px-4 py-3', RAIO)}>
                        <span className="text-sm text-teal-900">
                            {pedido.encomenda ? t('Encomenda :n', { n: pedido.encomenda.numero }) : ''}
                            {pedido.data_limite && ` · ${t('pagar até :d', { d: pedido.data_limite })}`}
                        </span>
                        <span className="text-xl font-bold tabular-nums text-teal-900">{kz(pedido.valor)} Kz</span>
                    </div>
                    {pedido.notas && <p className="text-sm text-slate-600">«{pedido.notas}»</p>}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Forma de pagamento')} obrigatorio erro={erros.forma}>
                            <select id="pagar-forma" value={dados.forma}
                                onChange={(e) => {
                                    const forma = e.target.value;
                                    const lista = forma === 'cash' ? caixas : contas;
                                    porDados({ ...dados, forma, destino: lista[0] ? String(lista[0].id) : '' });
                                }}
                                className={entrada}>
                                {formas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta={emNumerario ? t('Sai da caixa') : t('Sai da conta')} obrigatorio
                            erro={emNumerario ? erros.cash_register_id : erros.account_id}>
                            <select id="pagar-destino" value={dados.destino}
                                onChange={(e) => porDados({ ...dados, destino: e.target.value })} className={entrada}>
                                {destinos.length === 0 && <option value="">{emNumerario ? t('Sem caixas') : t('Sem contas')}</option>}
                                {destinos.map((d) => (
                                    <option key={d.id} value={d.id}>{d.nome} — {kz(d.saldo)} Kz</option>
                                ))}
                            </select>
                        </Campo>
                        <Campo etiqueta={t('Data do pagamento')} erro={erros.data}>
                            <input id="pagar-data" type="date" max={hoje} value={dados.data}
                                onChange={(e) => porDados({ ...dados, data: e.target.value })} className={entrada} />
                        </Campo>
                        <Campo etiqueta={t('Referência')} erro={erros.referencia} ajuda={t('O n.º da transferência ou do cheque.')}>
                            <input id="pagar-referencia" type="text" value={dados.referencia}
                                onChange={(e) => porDados({ ...dados, referencia: e.target.value })} className={entrada} />
                        </Campo>
                    </div>

                    {descoberto !== null && descoberto < 0 && (
                        <p role="alert" className={cls('border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800', RAIO)}>
                            <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                            {t('O saldo não chega: :d fica com :v Kz.', { d: escolhido!.nome, v: kz(descoberto) })}
                        </p>
                    )}
                </div>
            )}
        </Modal>
    );
}

/* ─── Devolver, decidir e ver ─────────────────────────────────────────── */

function Devolver({ pedido, aoFechar, aoFeito }: { pedido: PedidoDePagamento | null; aoFechar: () => void; aoFeito: (m: string) => void }) {
    const [motivo, porMotivo] = useState('');
    const devolver = useMutation({
        mutationFn: () => pagamentosAFornecedores.recusar(pedido!.id, motivo.trim()),
        onSuccess: (r) => { porMotivo(''); aoFeito(r.message); },
    });

    return (
        <Modal
            aberto={pedido !== null}
            aoFechar={aoFechar}
            titulo={t('Devolver o pedido')}
            subtitulo={pedido?.numero}
            icone="fa-rotate-left"
            cor="perigo"
            largura="sm"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="perigo" tom="solida" icone="fa-rotate-left" aTrabalhar={devolver.isPending}
                        disabled={motivo.trim().length < 3} onClick={() => devolver.mutate()}>
                        {t('Devolver')}
                    </Botao>
                </>
            }
        >
            <div className="space-y-4">
                <AvisoDeErro erro={devolver.error} />
                <Campo etiqueta={t('Motivo')} obrigatorio ajuda={t('Quem pediu vai lê-lo — valor errado, sem fundos, falta um documento…')}>
                    <textarea id="devolver-motivo" rows={3} value={motivo} onChange={(e) => porMotivo(e.target.value)} className={entrada} />
                </Campo>
            </div>
        </Modal>
    );
}

function Decidir({ alvo, aoFechar, aoFeito }: {
    alvo: { p: PedidoDePagamento; aprova: boolean } | null;
    aoFechar: () => void;
    aoFeito: (m: string) => void;
}) {
    const [comentario, porComentario] = useState('');
    const decidir = useMutation({
        mutationFn: () => pagamentosAFornecedores.decidir(alvo!.p.id, alvo!.aprova, comentario.trim() || undefined),
        onSuccess: (r) => { porComentario(''); aoFeito(r.message); },
    });
    const aprova = alvo?.aprova ?? true;

    return (
        <Modal
            aberto={alvo !== null}
            aoFechar={aoFechar}
            titulo={aprova ? t('Aprovar o pagamento') : t('Recusar o pagamento')}
            subtitulo={alvo ? `${alvo.p.numero} · ${kz(alvo.p.valor)} Kz` : undefined}
            icone={aprova ? 'fa-check' : 'fa-xmark'}
            cor={aprova ? 'bom' : 'perigo'}
            largura="sm"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor={aprova ? 'bom' : 'perigo'} tom="solida" icone={aprova ? 'fa-check' : 'fa-xmark'} aTrabalhar={decidir.isPending}
                        disabled={!aprova && comentario.trim().length < 3} onClick={() => decidir.mutate()}>
                        {aprova ? t('Aprovar') : t('Recusar')}
                    </Botao>
                </>
            }
        >
            <div className="space-y-4">
                <AvisoDeErro erro={decidir.error} />
                <Campo etiqueta={aprova ? t('Comentário (opcional)') : t('Motivo da recusa')} obrigatorio={!aprova}>
                    <textarea id="decidir-comentario" rows={3} value={comentario} onChange={(e) => porComentario(e.target.value)} className={entrada} />
                </Campo>
            </div>
        </Modal>
    );
}

function FichaDoPedido({ id, aoFechar }: { id: number | null; aoFechar: () => void }) {
    const ficha = useQuery({
        queryKey: ['tesouraria', 'pagamentos', 'ficha', id],
        queryFn: () => pagamentosAFornecedores.ficha(id as number),
        enabled: id !== null,
    });
    const p = ficha.data?.data;

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={p?.numero ?? t('Pedido de pagamento')}
            subtitulo={p?.fornecedor ?? undefined}
            icone="fa-hand-holding-dollar"
            cor="teal"
            largura="lg"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            {ficha.isPending ? (
                <Carregando linhas={5} />
            ) : ficha.isError ? (
                <AvisoDeErro erro={ficha.error} />
            ) : p ? (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <Etiqueta cor={COR[p.estado]}>{p.estado_rotulo}</Etiqueta>
                        {p.atrasado && <Etiqueta cor="perigo" icone="fa-clock">{t('Prazo passado')}</Etiqueta>}
                    </div>

                    <dl className="grid gap-3 sm:grid-cols-3">
                        <Dado rotulo={t('Valor')} valor={`${kz(p.valor)} Kz`} />
                        <Dado rotulo={t('Pagar até')} valor={p.data_limite} />
                        <Dado rotulo={t('NIF do fornecedor')} valor={p.fornecedor_nif} />
                        <Dado rotulo={t('Pedido por')} valor={p.pedido_por} nota={p.pedido_em} />
                        <Dado rotulo={t('Tesoureiro')} valor={p.tesoureiro ?? t('Qualquer')} />
                        <Dado rotulo={t('Pago por')} valor={p.pago_por} nota={p.pago_em ? `${p.pago_em}${p.recibo ? ` · ${p.recibo}` : ''}` : null} />
                    </dl>

                    {p.notas && <p className={cls('border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)}>{p.notas}</p>}
                    {p.motivo_recusa && <p className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>{p.motivo_recusa}</p>}

                    {p.encomenda && (
                        <section>
                            <h3 className="mb-2 text-sm font-bold text-slate-800">
                                <i className="fas fa-truck mr-2 text-teal-600" aria-hidden="true" />
                                {t('Encomenda :n', { n: p.encomenda.numero })}
                                <span className="ml-2 text-xs font-medium text-slate-500">
                                    {p.encomenda.estado} · {t('pago :p de :t Kz', { p: kz(p.encomenda.pago), t: kz(p.encomenda.total) })}
                                    {p.encomenda.factura && ` · ${t('factura :f', { f: p.encomenda.factura })}`}
                                </span>
                            </h3>
                            <div className={cls('overflow-x-auto', CARTAO)}>
                                <table className="w-full min-w-[32rem] text-sm">
                                    <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                        <tr>
                                            <th className="px-3 py-2 text-left">{t('O quê')}</th>
                                            <th className="px-3 py-2 text-right">{t('Quantidade')}</th>
                                            <th className="px-3 py-2 text-right">{t('Preço')}</th>
                                            <th className="px-3 py-2 text-right">{t('Total')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {p.encomenda.itens.map((i, k) => (
                                            <tr key={k}>
                                                <td className="px-3 py-2 text-slate-800">{i.descricao}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{i.quantidade} {i.unidade}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{kz(i.preco_unitario)}</td>
                                                <td className="px-3 py-2 text-right font-semibold tabular-nums">{kz(i.total)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}

                    {p.votos.length > 0 && (
                        <section>
                            <h3 className="mb-2 text-sm font-bold text-slate-800">{t('Aprovações')}</h3>
                            <ul className="space-y-1 text-sm">
                                {p.votos.map((v, k) => (
                                    <li key={k}>
                                        <Etiqueta cor={v.decisao === 'aprovado' ? 'bom' : 'perigo'}>{v.decisao === 'aprovado' ? t('Aprovou') : t('Recusou')}</Etiqueta>{' '}
                                        <span className="font-medium">{v.quem}</span> <span className="text-xs text-slate-500">{v.quando}</span>
                                        {v.comentario && <span className="block text-xs text-slate-600">«{v.comentario}»</span>}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </div>
            ) : null}
        </Modal>
    );
}

function Dado({ rotulo, valor, nota }: { rotulo: string; valor?: string | null; nota?: string | null }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</dt>
            <dd className="text-sm font-medium text-slate-800">{valor || '—'}</dd>
            {nota && <dd className="text-xs text-slate-500">{nota}</dd>}
        </div>
    );
}
