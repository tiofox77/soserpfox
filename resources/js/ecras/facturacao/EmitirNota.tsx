import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { notas, type LinhaDaFactura, type NotaAberta, type TipoDeNota } from '@/api/notas';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { FOCO, RAIO, cls, data as fmtData, kz } from '@/ui/tokens';
import { t } from '@/i18n';
import { useImprimirAoGravar } from './imprimirAoGravar';
import {
    CABECALHO_DA_TABELA,
    CELULA_DO_CABECALHO,
    LINHA_DA_TABELA,
    NaoAbriu,
    PainelDeSucesso,
    PapelBloqueado,
    SemNada,
    cascata,
} from './PecasDoEditor';

/**
 * EMITIR UMA NOTA DE CRÉDITO OU DE DÉBITO — ou abrir uma emitida, só para ler.
 *
 * O QUE ESTE ECRÃ FAZ: escolhe a factura, mostra as linhas dela, deixa
 * acertar QUANTIDADES, e grava. O que ele NÃO faz é tudo o que a AGT compara:
 * a taxa, o código SAFT, a região, o motivo de isenção e os totais vêm da
 * linha original e são calculados no servidor pelo `EmissorDeNotas`.
 *
 * O TRAVÃO DO E43 vive lá e responde 422 com a razão. Uma nota emitida não se
 * edita: rectifica-se com outra.
 */
type Linha = LinhaDaFactura & { quantidade: number | string };

const redondo = (v: number) => Math.round((v + Number.EPSILON) * 100) / 100;

/**
 * O que a linha vale NA NOTA, pelas contas do servidor (EmissorDeNotas).
 *
 * O desconto vem da factura e já inclui a parte do desconto do documento: a
 * FR do balcão com 15.900 de desconto anula 274.000, não os 289.900 do preço.
 * É só para ver — o servidor volta a fazer as contas.
 */
function contasDaLinha(l: Linha) {
    const bruto = redondo(l.price * (Number(l.quantidade) || 0));
    const desconto = redondo((bruto * (l.discount_percent || 0)) / 100);
    const liquido = redondo(bruto - desconto);

    return { desconto, total: redondo(liquido + redondo((liquido * (l.tax_rate || 0)) / 100)) };
}

export default function EmitirNota({ tipo, id, facturaId, clienteId }: { tipo: TipoDeNota; id?: number; facturaId?: number; clienteId?: number | null }) {
    if (id !== undefined) {
        return <NotaEmitida tipo={tipo} id={id} />;
    }

    return <Emitir tipo={tipo} facturaId={facturaId} clienteId={clienteId} />;
}

function NotaEmitida({ tipo, id }: { tipo: TipoDeNota; id: number }) {
    const q = useQuery({ queryKey: ['notas', tipo, 'mostrar', id], queryFn: () => notas.mostrar(tipo, id) });
    const eCredito = tipo === 'credito';

    if (q.isPending) return <Carregando linhas={6} />;
    if (q.isError) {
        return (
            <NaoAbriu
                titulo={t('Não foi possível abrir a nota')}
                mensagem={q.error instanceof ErroDaApi ? q.error.message : t('Verifique a ligação.')}
            />
        );
    }

    const n: NotaAberta = q.data.nota;

    return (
        <div className="space-y-4" data-documento-aberto>
            <Cartao
                titulo={<span className="flex items-center gap-2"><i className={cls('fas text-slate-400', eCredito ? 'fa-file-circle-minus' : 'fa-file-circle-plus')} aria-hidden="true" />{n.numero ?? (eCredito ? t('Nota de crédito') : t('Nota de débito'))}<Etiqueta cor={n.estado === 'cancelled' ? 'perigo' : 'bom'}>{n.estado}</Etiqueta></span>}
                accoes={<span className="flex gap-2"><a href={n.pdf} target="_blank" rel="noreferrer" className={cls('inline-flex items-center gap-2 border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-md', RAIO, FOCO)}><i className="fas fa-file-pdf text-red-500" aria-hidden="true" />{t('PDF')}</a><Botao icone="fa-list" onClick={() => (window.location.href = eCredito ? '/invoicing/credit-notes' : '/invoicing/debit-notes')}>{t('Ver as notas')}</Botao></span>}
            >
                <p className="mb-4 text-sm text-slate-500">{t('Documento fiscal emitido: abre-se para consultar. Corrige-se com outra nota.')}</p>
                <dl className="grid gap-3 text-sm sm:grid-cols-3">
                    {[
                        [t('Cliente'), n.cliente ?? '—'], [t('Factura'), n.factura ?? '—'], [t('Data'), fmtData(n.issue_date)],
                        [t('Motivo'), n.reason ?? '—'], [eCredito ? t('Alcance') : t('Vencimento'), eCredito ? (n.type === 'total' ? t('Anulação total') : t('Rectificação parcial')) : fmtData(n.due_date)], [t('Observações'), n.notes ?? '—'],
                    ].map(([rotulo, valor]) => (
                        <div key={rotulo}><dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</dt><dd className="font-medium text-slate-900">{valor}</dd></div>
                    ))}
                </dl>
            </Cartao>
            <Cartao titulo={t('Linhas')} icone="fa-box" semPadding>
                <table className="w-full text-sm">
                    <thead className={CABECALHO_DA_TABELA}><tr className="border-b border-slate-200"><th className={CELULA_DO_CABECALHO}>{t('Artigo')}</th><th className={cls('text-right', CELULA_DO_CABECALHO)}>{t('Qtd.')}</th><th className={cls('text-right', CELULA_DO_CABECALHO)}>{t('Preço')}</th><th className={cls('text-right', CELULA_DO_CABECALHO)}>{t('IVA')}</th><th className={cls('text-right', CELULA_DO_CABECALHO)}>{t('Total')}</th></tr></thead>
                    <tbody className="divide-y divide-slate-100">
                        {n.linhas.map((l, i) => <tr key={i} className={LINHA_DA_TABELA} style={cascata(i)}><td className="px-4 py-2 font-medium text-slate-800">{l.nome}</td><td className="px-4 py-2 text-right tabular-nums">{l.quantity}</td><td className="px-4 py-2 text-right tabular-nums">{kz(l.price)}</td><td className="px-4 py-2 text-right tabular-nums text-slate-500">{l.tax_rate}%</td><td className="px-4 py-2 text-right font-semibold tabular-nums">{kz(l.total)}</td></tr>)}
                    </tbody>
                    {/* O TOTAL DA NOTA, com o peso do total de um documento. */}
                    <tfoot><tr className="border-t-2 border-emerald-200 bg-emerald-50/70"><td className="px-4 py-3 text-base font-bold text-slate-700" colSpan={4}>{t('Total')}</td><td className="px-4 py-3 text-right text-xl font-bold tabular-nums text-emerald-700">{kz(n.total)} <span className="text-sm font-normal text-emerald-800/60">Kz</span></td></tr></tfoot>
                </table>
            </Cartao>
        </div>
    );
}

function Emitir({ tipo, facturaId: daMorada, clienteId: clienteDaMorada }: { tipo: TipoDeNota; facturaId?: number; clienteId?: number | null }) {
    const eCredito = tipo === 'credito';

    /* Da lista de facturas chega-se aqui com `?invoice=`: o servidor já disse
       de que cliente é, e o ecrã abre com os dois escolhidos. */
    const [clienteId, porClienteId] = useState(clienteDaMorada ? String(clienteDaMorada) : '');
    const [facturaId, porFacturaId] = useState(daMorada ? String(daMorada) : '');
    const [linhas, porLinhas] = useState<Linha[]>([]);
    const [motivo, porMotivo] = useState('');
    const [tipoDeCredito, porTipoDeCredito] = useState<'total' | 'partial'>('total');
    const [dia, porDia] = useState(() => new Date().toISOString().slice(0, 10));
    const [vencimento, porVencimento] = useState('');
    const [notasTexto, porNotasTexto] = useState('');
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [feito, porFeito] = useState<{ numero: string; agt: string | null; abrir: string; pdf: string } | null>(null);

    const opcoes = useQuery({
        queryKey: ['notas', tipo, 'opcoes'],
        queryFn: () => notas.opcoes(tipo),
        staleTime: 5 * 60_000,
    });

    /* O PDF abre sozinho ao emitir, se a empresa o pediu — ver `imprimirAoGravar`. */
    const impressao = useImprimirAoGravar(opcoes.data?.imprimir_ao_gravar);

    const facturas = useQuery({
        queryKey: ['notas', tipo, 'facturas', clienteId],
        queryFn: () => notas.facturas(tipo, clienteId || undefined),
        enabled: clienteId !== '',
        staleTime: 15_000,
    });

    const linhasDaFactura = useQuery({
        queryKey: ['notas', tipo, 'linhas', facturaId],
        queryFn: () => notas.linhas(tipo, Number(facturaId)),
        enabled: facturaId !== '',
    });

    /* As linhas da factura chegam do servidor e entram todas, com a
       quantidade que a factura teve. Numa NC parcial acerta-se daqui. */
    useEffect(() => {
        if (linhasDaFactura.data) {
            porLinhas(linhasDaFactura.data.data.map((l) => ({ ...l, quantidade: l.quantity })));
        }
    }, [linhasDaFactura.data]);

    useEffect(() => {
        if (opcoes.data && !motivo) {
            porMotivo(opcoes.data.motivos[0]?.valor ?? '');
        }
    }, [opcoes.data, motivo]);

    const guardar = useMutation({
        mutationFn: () =>
            notas.guardar(tipo, {
                client_id: Number(clienteId),
                invoice_id: facturaId ? Number(facturaId) : null,
                issue_date: dia,
                due_date: eCredito ? null : vencimento || null,
                reason: motivo,
                type: eCredito ? tipoDeCredito : null,
                notes: notasTexto || null,
                linhas: linhas
                    .filter((l) => Number(l.quantidade) > 0)
                    .map((l) => ({ origem_line_id: l.origem_line_id, quantity: Number(l.quantidade) })),
            }),
        onSuccess: (r) => {
            porFeito({ numero: r.numero, agt: r.agt, abrir: r.abrir, pdf: r.pdf });
            porErros({});
            // Uma nota não tem rascunho: nasce emitida, e imprime-se já.
            impressao.depoisDeGravar(r.pdf);
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (opcoes.isPending) {
        return <Carregando linhas={5} />;
    }

    if (opcoes.isError) {
        return (
            <NaoAbriu
                titulo={t('Não foi possível abrir')}
                mensagem={opcoes.error instanceof ErroDaApi ? opcoes.error.message : t('Verifique a ligação.')}
            />
        );
    }

    if (feito) {
        return (
            <PainelDeSucesso
                numero={feito.numero}
                mensagem={eCredito ? t('Nota de crédito emitida.') : t('Nota de débito emitida.')}
                agt={feito.agt}
                icone={eCredito ? 'fa-file-circle-minus' : 'fa-file-circle-plus'}
                aviso={impressao.bloqueado && <PapelBloqueado />}
            >
                {/* O PAPEL DA NOTA. Não havia botão para ele: quem emitia uma
                    nota para entregar ao cliente tinha de a ir buscar à lista. */}
                <Botao cor="primaria" tom="solida" icone="fa-file-pdf" onClick={() => window.open(feito.pdf, '_blank', 'noopener')}>
                    {t('PDF')}
                </Botao>
                <Botao icone="fa-list" onClick={() => (window.location.href = feito.abrir)}>
                    {t('Ver as notas')}
                </Botao>
            </PainelDeSucesso>
        );
    }

    const o = opcoes.data;
    const listaDeFacturas = facturas.data?.data ?? [];
    const escolhida = listaDeFacturas.find((f) => String(f.id) === facturaId);

    const mudarQuantidade = (i: number, valor: string) =>
        porLinhas((ls) => ls.map((l, j) => (j === i ? { ...l, quantidade: valor } : l)));

    return (
        <div className="space-y-4">
            <AvisoDeErro erro={guardar.error} />

            <Cartao titulo={t('Documento a corrigir')} icone="fa-circle-info">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo etiqueta={t('Cliente')} erro={erros.client_id} obrigatorio>
                        <select
                            value={clienteId}
                            onChange={(e) => {
                                porClienteId(e.target.value);
                                porFacturaId('');
                                porLinhas([]);
                            }}
                            className={entrada}
                        >
                            <option value="">{t('Escolher…')}</option>
                            {o.clientes.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                    {c.nif ? ` · ${c.nif}` : ''}
                                </option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Factura')} erro={erros.invoice_id} obrigatorio>
                        <select
                            value={facturaId}
                            onChange={(e) => porFacturaId(e.target.value)}
                            disabled={!clienteId}
                            className={entrada}
                        >
                            <option value="">{clienteId ? t('Escolher…') : t('Escolha o cliente primeiro')}</option>
                            {listaDeFacturas.map((f) => (
                                <option key={f.id} value={f.id}>
                                    {f.numero} · {fmtData(f.data)} · {kz(f.total)} Kz
                                </option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Data')} erro={erros.issue_date} obrigatorio>
                        <input type="date" value={dia} onChange={(e) => porDia(e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Motivo')} erro={erros.reason} obrigatorio>
                        <select value={motivo} onChange={(e) => porMotivo(e.target.value)} className={entrada}>
                            {o.motivos.map((m) => (
                                <option key={m.valor} value={m.valor}>
                                    {m.rotulo}
                                </option>
                            ))}
                        </select>
                    </Campo>

                    {eCredito ? (
                        <Campo etiqueta={t('Alcance')} erro={erros.type} obrigatorio>
                            <select
                                value={tipoDeCredito}
                                onChange={(e) => porTipoDeCredito(e.target.value as 'total' | 'partial')}
                                className={entrada}
                            >
                                <option value="total">{t('Anulação total')}</option>
                                <option value="partial">{t('Rectificação parcial')}</option>
                            </select>
                        </Campo>
                    ) : (
                        <Campo etiqueta={t('Vencimento')} erro={erros.due_date}>
                            <input type="date" value={vencimento} onChange={(e) => porVencimento(e.target.value)} className={entrada} />
                        </Campo>
                    )}
                </div>

                {/* QUANTO AINDA SE PODE CREDITAR, à vista. É o número que o E43 compara. */}
                {eCredito && escolhida && (
                    <div className={cls('mt-4 flex items-start gap-3 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                        <span className="grid h-8 w-8 flex-none place-items-center rounded-lg bg-amber-100 text-amber-700">
                            <i className="fas fa-scale-balanced" aria-hidden="true" />
                        </span>
                        <p className="min-w-0 leading-relaxed">
                            {/* Sem o espaço explícito o JSX colava o valor à
                                frase seguinte: «12.000,00 Kzpor anular». */}
                            {t('Esta factura tem')}{' '}<strong className="tabular-nums">{kz(escolhida.por_creditar)} Kz</strong>{' '}
                            {t('por anular')}
                            {escolhida.por_creditar < escolhida.total && (
                                <span className="text-amber-700/80">{' '}{t('(de :total — o resto já foi creditado)', { total: kz(escolhida.total) })}</span>
                            )}
                            .
                        </p>
                    </div>
                )}
            </Cartao>

            <Cartao titulo={t('Linhas')} icone="fa-box" semPadding>
                {linhasDaFactura.isFetching ? (
                    <SemNada icone="fa-spinner fa-spin">{t('A ler as linhas da factura…')}</SemNada>
                ) : linhas.length === 0 ? (
                    <SemNada icone={eCredito ? 'fa-file-circle-minus' : 'fa-file-circle-plus'}>
                        {t('Escolha a factura para ver as linhas.')}
                    </SemNada>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className={CABECALHO_DA_TABELA}>
                                <tr className="border-b border-slate-200">
                                    <th className={CELULA_DO_CABECALHO}>{t('Artigo')}</th>
                                    <th className={cls('text-right', CELULA_DO_CABECALHO)}>{t('Na factura')}</th>
                                    <th className={cls('w-32 text-right', CELULA_DO_CABECALHO)}>{eCredito ? t('A anular') : t('A debitar')}</th>
                                    <th className={cls('text-right', CELULA_DO_CABECALHO)}>{t('Preço')}</th>
                                    <th className={cls('text-right', CELULA_DO_CABECALHO)}>{t('Desconto')}</th>
                                    <th className={cls('text-right', CELULA_DO_CABECALHO)}>{t('Imposto')}</th>
                                    <th className={cls('text-right', CELULA_DO_CABECALHO)}>{t('Valor')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {linhas.map((l, i) => (
                                    <tr key={l.origem_line_id} className={LINHA_DA_TABELA} style={cascata(i)}>
                                        <td className="px-4 py-2 font-medium text-slate-800">{l.nome}</td>
                                        <td className="px-4 py-2 text-right tabular-nums text-slate-500">{l.quantity}</td>
                                        <td className="px-4 py-2">
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.001"
                                                value={l.quantidade}
                                                onChange={(e) => mudarQuantidade(i, e.target.value)}
                                                aria-label={t('Quantidade da linha :n', { n: i + 1 })}
                                                className={cls(entrada, 'text-right tabular-nums', FOCO)}
                                            />
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums text-slate-600">{kz(l.price)}</td>
                                        <td className="px-4 py-2 text-right tabular-nums text-slate-600">
                                            {contasDaLinha(l).desconto > 0 ? `−${kz(contasDaLinha(l).desconto)}` : '—'}
                                        </td>
                                        {/* A taxa e a região são as da linha ORIGINAL — vêm do
                                            servidor, e é assim que a AGT as compara. */}
                                        <td className="px-4 py-2 text-right text-xs tabular-nums text-slate-500">
                                            {l.tax_rate}% · {l.tax_country_region}
                                        </td>
                                        <td className="px-4 py-2 text-right font-semibold tabular-nums text-slate-800">{kz(contasDaLinha(l).total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t-2 border-slate-200 bg-slate-50/70">
                                    <td className="px-4 py-3 font-bold text-slate-700" colSpan={6}>
                                        {eCredito ? t('A anular') : t('A debitar')}
                                    </td>
                                    <td className="px-4 py-3 text-right text-base font-bold tabular-nums text-slate-900">
                                        {kz(redondo(linhas.reduce((soma, l) => soma + contasDaLinha(l).total, 0)))}{' '}
                                        <span className="text-sm font-normal text-slate-500">Kz</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}

                {erros.linhas?.[0] && (
                    <p role="alert" className="border-t border-red-100 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                        {erros.linhas[0]}
                    </p>
                )}
            </Cartao>

            <Cartao titulo={t('Observações')} icone="fa-pen">
                <textarea
                    rows={3}
                    value={notasTexto}
                    onChange={(e) => porNotasTexto(e.target.value)}
                    aria-label={t('Observações')}
                    className={cls(entrada, 'h-auto py-2')}
                />
            </Cartao>

            <div className="flex items-center justify-end gap-2">
                <Botao onClick={() => (window.location.href = eCredito ? '/invoicing/credit-notes' : '/invoicing/debit-notes')}>
                    {t('Cancelar')}
                </Botao>
                <Botao
                    cor={eCredito ? 'perigo' : 'primaria'}
                    tom="solida"
                    altura="grande"
                    icone={eCredito ? 'fa-file-circle-minus' : 'fa-file-circle-plus'}
                    aTrabalhar={guardar.isPending}
                    disabled={!o.permissoes.pode_criar || linhas.length === 0}
                    onClick={() => guardar.mutate()}
                >
                    {eCredito ? t('Emitir nota de crédito') : t('Emitir nota de débito')}
                </Botao>
            </div>
        </div>
    );
}
