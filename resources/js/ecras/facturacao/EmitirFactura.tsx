import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { factura, type LinhaDaFactura } from '@/api/factura';
import type { Totais } from '@/api/emissor';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

/**
 * EMITIR UMA FACTURA DE VENDA (FT ou FR).
 *
 * O ecrã mais delicado da casa, e por isso o que menos faz: recolhe o
 * cabeçalho e as linhas, pergunta os totais ao servidor a cada alteração, e
 * grava. Tudo o que a AGT compara — taxa, código SAFT, região, isenção,
 * IEC/IS, retenção, hash — é calculado no `EmissorDeFacturas`, o mesmo que o
 * ecrã Livewire chama. Uma segunda cópia disso em TypeScript é um cêntimo de
 * diferença numa factura que a AGT recusa dias depois.
 *
 * A FACTURA-RECIBO é paga no acto: exige a forma de pagamento e nasce
 * liquidada, com a entrada na tesouraria. O ecrã só troca os campos; a regra
 * é do servidor.
 */

const LINHA_NOVA: LinhaDaFactura = { product_id: null, description: '', quantity: 1, price: 0, discount_percent: 0 };

export default function EmitirFactura() {
    const [tipo, porTipo] = useState<'FT' | 'FR'>('FT');
    const [clienteId, porClienteId] = useState('');
    const [armazemId, porArmazemId] = useState('');
    const [serieId, porSerieId] = useState('');
    const [dia, porDia] = useState(() => new Date().toISOString().slice(0, 10));
    const [vencimento, porVencimento] = useState('');
    const [entrega, porEntrega] = useState('');
    const [regiao, porRegiao] = useState('');
    const [pagamento, porPagamento] = useState('');
    const [descontoComercial, porDescontoComercial] = useState('');
    const [descontoFinanceiro, porDescontoFinanceiro] = useState('');
    const [retencaoTipo, porRetencaoTipo] = useState('');
    const [retencaoPct, porRetencaoPct] = useState('');
    const [notas, porNotas] = useState('');
    const [linhas, porLinhas] = useState<LinhaDaFactura[]>([{ ...LINHA_NOVA }]);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [feito, porFeito] = useState<{ numero: string; agt: string | null; abrir: string; pdf: string } | null>(null);
    const [totais, porTotais] = useState<Totais | null>(null);
    const [aContar, porAContar] = useState(false);

    const opcoes = useQuery({ queryKey: ['factura', 'opcoes'], queryFn: factura.opcoes, staleTime: 5 * 60_000 });

    /* A série por omissão do tipo escolhido. A FR usa a sequência do POS. */
    useEffect(() => {
        if (!opcoes.data) return;
        const doTipo = opcoes.data.series.filter((s) => (tipo === 'FR' ? s.document_type === 'pos' : s.document_type === 'invoice'));
        const padrao = doTipo.find((s) => s.is_default) ?? doTipo[0];
        porSerieId(padrao ? String(padrao.id) : '');
    }, [opcoes.data, tipo]);

    /* A CONTA PEDE-SE AO SERVIDOR, com pausa e cancelamento — ver EmitirProposta. */
    useEffect(() => {
        let cancelado = false;
        const comLinhas = linhas.filter((l) => Number(l.quantity) > 0);

        if (comLinhas.length === 0) {
            porTotais(null);
            return;
        }

        porAContar(true);

        const t = setTimeout(() => {
            factura
                .calcular({
                    linhas: comLinhas,
                    discount_commercial: Number(descontoComercial) || 0,
                    discount_financial: Number(descontoFinanceiro) || 0,
                })
                .then((r) => { if (!cancelado) porTotais(r.totais); })
                .catch(() => { if (!cancelado) porTotais(null); })
                .finally(() => { if (!cancelado) porAContar(false); });
        }, 400);

        return () => { cancelado = true; clearTimeout(t); };
    }, [linhas, descontoComercial, descontoFinanceiro]);

    const retencaoValor = totais && retencaoPct ? Math.round(totais.base * Number(retencaoPct)) / 100 : 0;

    const guardar = useMutation({
        mutationFn: (status: 'draft' | 'pending') =>
            factura.guardar({
                client_id: Number(clienteId) || null,
                warehouse_id: Number(armazemId) || null,
                invoice_type: tipo,
                series_id: Number(serieId) || null,
                invoice_date: dia,
                due_date: vencimento || null,
                delivery_date: entrega || null,
                tax_country_region: regiao || null,
                payment_method: tipo === 'FR' ? pagamento || null : null,
                discount_commercial: Number(descontoComercial) || 0,
                discount_financial: Number(descontoFinanceiro) || 0,
                withholding_type: retencaoTipo || null,
                withholding_percentage: Number(retencaoPct) || 0,
                withholding_amount: retencaoValor,
                notes: notas || null,
                status,
                linhas: linhas.filter((l) => Number(l.quantity) > 0),
            }),
        onSuccess: (r) => { porFeito({ numero: r.numero, agt: r.agt, abrir: r.abrir, pdf: r.pdf }); porErros({}); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;

    if (opcoes.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir o emissor</h2>
                <p className="text-sm text-red-800">{opcoes.error instanceof ErroDaApi ? opcoes.error.message : 'Verifique a ligação.'}</p>
            </div>
        );
    }

    if (feito) {
        return (
            <div className={cls(CARTAO, 'p-8 text-center')}>
                <i className="fas fa-circle-check mb-3 text-4xl text-emerald-500" aria-hidden="true" />
                <h2 className="text-xl font-bold text-slate-900">{feito.numero}</h2>
                <p className="mt-1 text-sm text-slate-500">Emitida.</p>
                {feito.agt && <p className="mt-1 text-xs text-slate-400">{feito.agt}</p>}
                <div className="mt-6 flex justify-center gap-2">
                    <Botao cor="primaria" tom="solida" icone="fa-file-pdf" onClick={() => window.open(feito.pdf, '_blank')}>PDF</Botao>
                    <Botao icone="fa-eye" onClick={() => (window.location.href = feito.abrir)}>Abrir</Botao>
                    <Botao icone="fa-plus" onClick={() => { porFeito(null); porLinhas([{ ...LINHA_NOVA }]); porClienteId(''); porNotas(''); }}>Emitir outra</Botao>
                </div>
            </div>
        );
    }

    const o = opcoes.data;
    const seriesDoTipo = o.series.filter((s) => (tipo === 'FR' ? s.document_type === 'pos' : s.document_type === 'invoice'));
    const temFisicos = linhas.some((l) => o.artigos.find((a) => a.id === l.product_id)?.type !== 'servico' && l.product_id !== null);

    const mudarLinha = (i: number, campo: keyof LinhaDaFactura, valor: string) =>
        porLinhas((ls) =>
            ls.map((l, j) => {
                if (j !== i) return l;
                if (campo === 'product_id') {
                    const artigo = o.artigos.find((a) => String(a.id) === valor);
                    return { ...l, product_id: valor ? Number(valor) : null, price: artigo ? artigo.price : l.price, description: artigo ? artigo.name : l.description };
                }
                return { ...l, [campo]: valor };
            }),
        );

    return (
        <div className="space-y-4">
            <AvisoDeErro erro={guardar.error} />

            <Cartao titulo="Documento">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Campo etiqueta="Tipo" erro={erros.invoice_type} obrigatorio>
                        <select value={tipo} onChange={(e) => porTipo(e.target.value as 'FT' | 'FR')} className={entrada}>
                            <option value="FT">Factura (FT)</option>
                            <option value="FR">Factura-Recibo (FR) — paga no acto</option>
                        </select>
                    </Campo>

                    <Campo etiqueta="Série" erro={erros.series_id}>
                        <select value={serieId} onChange={(e) => porSerieId(e.target.value)} className={entrada}>
                            {seriesDoTipo.length === 0 && <option value="">Sem série activa para este tipo</option>}
                            {seriesDoTipo.map((s) => (
                                <option key={s.id} value={s.id}>{s.series_code} · {s.name}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta="Cliente" erro={erros.client_id} obrigatorio>
                        <select value={clienteId} onChange={(e) => porClienteId(e.target.value)} className={entrada}>
                            <option value="">Escolher…</option>
                            {o.clientes.map((c) => (
                                <option key={c.id} value={c.id}>{c.name}{c.nif ? ` · ${c.nif}` : ''}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta="Data" erro={erros.invoice_date} obrigatorio>
                        <input type="date" value={dia} onChange={(e) => porDia(e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta="Vencimento" erro={erros.due_date}>
                        <input type="date" value={vencimento} onChange={(e) => porVencimento(e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta="Data de entrega" erro={erros.delivery_date}>
                        <input type="date" value={entrega} onChange={(e) => porEntrega(e.target.value)} className={entrada} />
                    </Campo>

                    {/* O armazém só é obrigatório com artigos físicos. */}
                    <Campo etiqueta="Armazém" erro={erros.warehouse_id} obrigatorio={temFisicos}>
                        <select value={armazemId} onChange={(e) => porArmazemId(e.target.value)} className={entrada}>
                            <option value="">{temFisicos ? 'Escolher…' : 'Só serviços — não é preciso'}</option>
                            {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </Campo>

                    {/* Cabinda tem regime próprio. Por omissão deriva da província do cliente. */}
                    <Campo etiqueta="Região fiscal" erro={erros.tax_country_region}>
                        <select value={regiao} onChange={(e) => porRegiao(e.target.value)} className={entrada}>
                            {o.regioes.map((r) => <option key={r.valor} value={r.valor}>{r.rotulo}</option>)}
                        </select>
                    </Campo>

                    {tipo === 'FR' && (
                        <Campo etiqueta="Forma de pagamento" erro={erros.payment_method} obrigatorio className="lg:col-span-2">
                            <select value={pagamento} onChange={(e) => porPagamento(e.target.value)} className={entrada}>
                                <option value="">Escolher…</option>
                                {o.formas_de_pagamento.map((f) => <option key={f.id} value={f.code}>{f.name}</option>)}
                            </select>
                        </Campo>
                    )}
                </div>
            </Cartao>

            <Cartao titulo="Linhas" accoes={<Botao icone="fa-plus" onClick={() => porLinhas((ls) => [...ls, { ...LINHA_NOVA }])}>Nova linha</Botao>} semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                <th className="px-4 py-3 font-semibold">Artigo</th>
                                <th className="px-4 py-3 font-semibold">Descrição</th>
                                <th className="w-24 px-4 py-3 text-right font-semibold">Qtd.</th>
                                <th className="w-32 px-4 py-3 text-right font-semibold">Preço</th>
                                <th className="w-24 px-4 py-3 text-right font-semibold">Desc. %</th>
                                <th className="w-12 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {linhas.map((l, i) => (
                                <tr key={i}>
                                    <td className="px-4 py-2">
                                        <select value={l.product_id ?? ''} onChange={(e) => mudarLinha(i, 'product_id', e.target.value)} aria-label={`Artigo da linha ${i + 1}`} className={entrada}>
                                            <option value="">Escolher…</option>
                                            {o.artigos.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                                        </select>
                                    </td>
                                    <td className="px-4 py-2"><input value={l.description} onChange={(e) => mudarLinha(i, 'description', e.target.value)} aria-label={`Descrição da linha ${i + 1}`} className={entrada} /></td>
                                    <td className="px-4 py-2"><input type="number" min="0" step="0.001" value={l.quantity} onChange={(e) => mudarLinha(i, 'quantity', e.target.value)} aria-label={`Quantidade da linha ${i + 1}`} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2"><input type="number" min="0" step="0.01" value={l.price} onChange={(e) => mudarLinha(i, 'price', e.target.value)} aria-label={`Preço da linha ${i + 1}`} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2"><input type="number" min="0" max="100" step="0.01" value={l.discount_percent} onChange={(e) => mudarLinha(i, 'discount_percent', e.target.value)} aria-label={`Desconto da linha ${i + 1}`} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2 text-right">
                                        {linhas.length > 1 && (
                                            <button type="button" onClick={() => porLinhas((ls) => ls.filter((_, j) => j !== i))} aria-label={`Apagar linha ${i + 1}`} className={cls('p-2 text-red-500 transition hover:bg-red-50', RAIO, FOCO)}>
                                                <i className="fas fa-trash" aria-hidden="true" />
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {erros.linhas?.[0] && <p role="alert" className="border-t border-red-100 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{erros.linhas[0]}</p>}
            </Cartao>

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo="Descontos e retenção">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta="Desconto comercial (Kz)" erro={erros.discount_commercial}>
                            <input type="number" min="0" step="0.01" value={descontoComercial} onChange={(e) => porDescontoComercial(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                        </Campo>
                        <Campo etiqueta="Desconto financeiro (Kz)" erro={erros.discount_financial}>
                            <input type="number" min="0" step="0.01" value={descontoFinanceiro} onChange={(e) => porDescontoFinanceiro(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                        </Campo>
                        <Campo etiqueta="Retenção na fonte" erro={erros.withholding_type}>
                            <select value={retencaoTipo} onChange={(e) => porRetencaoTipo(e.target.value)} className={entrada}>
                                <option value="">Sem retenção</option>
                                {o.retencoes.map((r) => <option key={r.valor} value={r.valor}>{r.rotulo}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta="Percentagem" erro={erros.withholding_percentage}>
                            <input type="number" min="0" max="100" step="0.01" value={retencaoPct} onChange={(e) => porRetencaoPct(e.target.value)} disabled={!retencaoTipo} className={cls(entrada, 'text-right tabular-nums')} />
                        </Campo>
                    </div>
                    <div className="mt-4">
                        <Campo etiqueta="Observações" erro={erros.notes}>
                            <textarea rows={2} value={notas} onChange={(e) => porNotas(e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                        </Campo>
                    </div>
                </Cartao>

                {/* OS TOTAIS SÃO OS DO SERVIDOR. */}
                <Cartao titulo="Totais">
                    {totais ? (
                        <dl className={cls('space-y-1.5 text-sm', aContar && 'opacity-50')}>
                            <Total rotulo="Valor bruto" valor={totais.bruto} />
                            {totais.desconto_comercial > 0 && <Total rotulo="Desconto comercial" valor={-totais.desconto_comercial} />}
                            <Total rotulo="Incidência de IVA" valor={totais.base} />
                            <Total rotulo="Imposto" valor={totais.imposto} />
                            {Number(descontoFinanceiro) > 0 && <Total rotulo="Desconto financeiro" valor={-Number(descontoFinanceiro)} />}
                            {retencaoValor > 0 && <Total rotulo={`Retenção ${retencaoTipo}`} valor={-retencaoValor} />}
                            <div className="mt-2 flex items-baseline justify-between border-t border-slate-200 pt-2">
                                <dt className="font-bold text-slate-900">Total</dt>
                                <dd className="text-xl font-bold tabular-nums text-slate-900">{kz(totais.total - retencaoValor)} <span className="text-sm font-normal text-slate-400">Kz</span></dd>
                            </div>
                            <p className="pt-1 text-xs text-slate-400">Contado no servidor — é o mesmo cálculo que assina o documento.</p>
                        </dl>
                    ) : (
                        <p className="py-6 text-center text-sm text-slate-400">Escolha um artigo e uma quantidade para ver os totais.</p>
                    )}
                </Cartao>
            </div>

            <div className="flex items-center justify-end gap-2">
                <Botao onClick={() => (window.location.href = '/invoicing/sales/invoices')}>Cancelar</Botao>
                <Botao icone="fa-file" aTrabalhar={guardar.isPending && guardar.variables === 'draft'} onClick={() => guardar.mutate('draft')}>Guardar rascunho</Botao>
                <Botao cor="primaria" tom="solida" altura="grande" icone="fa-file-signature" aTrabalhar={guardar.isPending && guardar.variables === 'pending'} disabled={!o.permissoes.pode_criar} onClick={() => guardar.mutate('pending')}>
                    {tipo === 'FR' ? 'Emitir factura-recibo' : 'Emitir factura'}
                </Botao>
            </div>
        </div>
    );
}

function Total({ rotulo, valor }: { rotulo: string; valor: number }) {
    return (
        <div className="flex items-baseline justify-between">
            <dt className="text-slate-500">{rotulo}</dt>
            <dd className="tabular-nums text-slate-800">{kz(valor)}</dd>
        </div>
    );
}
