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
import { Etiqueta } from '@/ui/Etiqueta';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * EMITIR UMA FACTURA DE VENDA (FT ou FR) — ou abrir uma que já existe.
 *
 * O ecrã mais delicado da casa, e por isso o que menos faz: recolhe o
 * cabeçalho e as linhas, pergunta os totais ao servidor a cada alteração, e
 * grava. Tudo o que a AGT compara — taxa, código SAFT, região, isenção,
 * IEC/IS, retenção, hash — é calculado no `EmissorDeFacturas`.
 *
 * Com `id`, abre a factura: um rascunho edita-se; uma factura emitida
 * abre-se só para ler, porque se rectifica com nota de crédito (Decreto
 * 71/25). O servidor é que diz qual é qual.
 *
 * Com `duplicarDe` (`?duplicar=123` na morada), abre com o CONTEÚDO de outra
 * factura e mais nada: sem `id`, sem número e sem série, gravar cria um
 * documento novo. O que viaja e o que fica está no `DuplicaDocumento`, do
 * lado do servidor — aqui nem sequer chegam os campos da identidade.
 *
 * A FACTURA-RECIBO é paga no acto: exige a forma de pagamento e nasce
 * liquidada, com a entrada na tesouraria. O ecrã só troca os campos; a regra
 * é do servidor.
 */

const LINHA_NOVA: LinhaDaFactura = { product_id: null, description: '', quantity: 1, price: 0, discount_percent: 0 };

export default function EmitirFactura({ id, duplicarDe }: { id?: number; duplicarDe?: number }) {
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
    const [feito, porFeito] = useState<{ numero: string; agt: string | null; abrir: string; pdf: string; mensagem: string } | null>(null);
    const [totais, porTotais] = useState<Totais | null>(null);
    const [aContar, porAContar] = useState(false);

    const opcoes = useQuery({ queryKey: ['factura', 'opcoes'], queryFn: factura.opcoes, staleTime: 5 * 60_000 });
    const aberta = useQuery({ queryKey: ['factura', 'abrir', id], queryFn: () => factura.abrir(id ?? 0), enabled: id !== undefined });
    const copia = useQuery({ queryKey: ['factura', 'duplicar', duplicarDe], queryFn: () => factura.duplicar(duplicarDe ?? 0), enabled: id === undefined && duplicarDe !== undefined });

    /*
     * O conteúdo que entra no formulário — venha de uma factura aberta ou de
     * uma duplicada. É o MESMO carregamento, e é isso que garante que o
     * duplicado herda o que a edição herdaria: o que ele não herda é o que o
     * servidor não mandou.
     */
    const carregado = aberta.data ?? copia.data ?? null;

    useEffect(() => {
        const d = carregado?.documento;
        if (!d) return;
        porTipo(d.invoice_type === 'FR' ? 'FR' : 'FT');
        porClienteId(d.client_id ? String(d.client_id) : '');
        porArmazemId(d.warehouse_id ? String(d.warehouse_id) : '');
        // A SÉRIE SÓ VEM DE UMA FACTURA ABERTA. Um duplicado nasce na série
        // por omissão do seu tipo — herdar a do original era herdar metade da
        // identidade dele.
        porSerieId(aberta.data?.documento.series_id ? String(aberta.data.documento.series_id) : '');
        porDia(d.invoice_date ?? new Date().toISOString().slice(0, 10));
        porVencimento(d.due_date ?? '');
        porEntrega(d.delivery_date ?? '');
        porRegiao(d.tax_country_region ?? '');
        porPagamento(d.payment_method ?? '');
        porDescontoComercial(d.discount_commercial ? String(d.discount_commercial) : '');
        porDescontoFinanceiro(d.discount_financial ? String(d.discount_financial) : '');
        porRetencaoTipo(d.withholding_type ?? '');
        porRetencaoPct(d.withholding_percentage ? String(d.withholding_percentage) : '');
        porNotas(d.notes ?? '');
        porLinhas(carregado.linhas.length > 0 ? carregado.linhas.map((l) => ({ ...l, description: l.description ?? '' })) : [{ ...LINHA_NOVA }]);
    }, [carregado]);

    /* A série por omissão do tipo escolhido. A FR usa a sequência do POS. Uma factura aberta traz a sua. */
    useEffect(() => {
        if (!opcoes.data || id !== undefined) return;
        const doTipo = opcoes.data.series.filter((s) => (tipo === 'FR' ? s.document_type === 'pos' : s.document_type === 'invoice'));
        const padrao = doTipo.find((s) => s.is_default) ?? doTipo[0];
        porSerieId(padrao ? String(padrao.id) : '');
    }, [opcoes.data, tipo, id]);

    /*
     * O VENCIMENTO SAI DA CONDIÇÃO DE PAGAMENTO DO CLIENTE.
     *
     * Escolher o cliente propõe a data: dia da factura + os dias da condição
     * dele (pronto pagamento vence no próprio dia). Continua a poder mudar-se
     * à mão, e uma factura já aberta traz o vencimento que tem.
     */
    useEffect(() => {
        if (!opcoes.data || id !== undefined || !clienteId) return;
        const cliente = opcoes.data.clientes.find((c) => String(c.id) === clienteId);
        if (!cliente) return;
        const d = new Date(dia + 'T00:00:00');
        d.setDate(d.getDate() + (cliente.payment_term_days ?? 0));
        porVencimento(d.toISOString().slice(0, 10));
    }, [opcoes.data, clienteId, dia, id]);

    /* Uma linha sem artigo, sem preço e sem descrição ainda não é uma linha. */
    const comConteudo = (l: { product_id: number | null; quantity: number | string; price: number | string; description: string }) =>
        Number(l.quantity) > 0 && (l.product_id !== null || Number(l.price) > 0 || l.description.trim() !== '');

    /* A CONTA PEDE-SE AO SERVIDOR, com pausa e cancelamento — ver EmitirProposta. */
    useEffect(() => {
        let cancelado = false;
        const comLinhas = linhas.filter(comConteudo);

        if (comLinhas.length === 0) {
            porTotais(null);
            return;
        }

        porAContar(true);

        const pausa = setTimeout(() => {
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

        return () => { cancelado = true; clearTimeout(pausa); };
    }, [linhas, descontoComercial, descontoFinanceiro]);

    const retencaoValor = totais && retencaoPct ? Math.round(totais.base * Number(retencaoPct)) / 100 : 0;

    const guardar = useMutation({
        mutationFn: (status: 'draft' | 'pending') => {
            const corpo = {
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
                linhas: linhas.filter(comConteudo),
            };
            return id !== undefined ? factura.actualizar(id, corpo) : factura.guardar(corpo);
        },
        onSuccess: (r) => { porFeito({ numero: r.numero, agt: r.agt, abrir: r.abrir, pdf: r.pdf, mensagem: r.message }); porErros({}); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (opcoes.isPending || (id !== undefined && aberta.isPending) || (copia.isPending && copia.fetchStatus !== 'idle')) return <Carregando linhas={8} />;

    if (opcoes.isError || aberta.isError || copia.isError) {
        const erro = opcoes.error ?? aberta.error ?? copia.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a factura')}</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    if (feito) {
        return (
            <div className={cls(CARTAO, 'p-8 text-center')}>
                <i className="fas fa-circle-check mb-3 text-4xl text-emerald-500" aria-hidden="true" />
                <h2 className="text-xl font-bold text-slate-900">{feito.numero}</h2>
                <p className="mt-1 text-sm text-slate-500">{feito.mensagem}</p>
                {feito.agt && <p className="mt-1 text-xs text-slate-400">{feito.agt}</p>}
                <div className="mt-6 flex justify-center gap-2">
                    <Botao cor="primaria" tom="solida" icone="fa-file-pdf" onClick={() => window.open(feito.pdf, '_blank')}>{t('PDF')}</Botao>
                    <Botao icone="fa-list" onClick={() => (window.location.href = '/invoicing/sales/invoices')}>{t('Ver as facturas')}</Botao>
                    {id === undefined && <Botao icone="fa-plus" onClick={() => { porFeito(null); porLinhas([{ ...LINHA_NOVA }]); porClienteId(''); porNotas(''); }}>{t('Emitir outra')}</Botao>}
                </div>
            </div>
        );
    }

    const o = opcoes.data;
    const doc = aberta.data?.documento ?? null;
    const soLeitura = doc !== null && !doc.pode_editar;
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
        <div className="space-y-4" data-emissor="factura">
            <AvisoDeErro erro={guardar.error} />

            {doc && (
                <div className={cls('flex flex-wrap items-center justify-between gap-3 border px-4 py-3 text-sm', RAIO, soLeitura ? 'border-slate-200 bg-slate-50 text-slate-700' : 'border-amber-200 bg-amber-50 text-amber-900')} data-documento-aberto>
                    <span className="flex items-center gap-2">
                        <strong>{doc.numero ?? t('Rascunho')}</strong>
                        <Etiqueta cor={soLeitura ? 'neutra' : 'aviso'}>{doc.estado}</Etiqueta>
                        {soLeitura ? t('Documento emitido: só leitura. Rectifica-se com nota de crédito.') : t('Rascunho: pode alterar e emitir.')}
                    </span>
                    <a href={doc.pdf} target="_blank" rel="noreferrer" className={cls('inline-flex items-center gap-2 border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50', RAIO)}><i className="fas fa-file-pdf" aria-hidden="true" />{t('PDF')}</a>
                </div>
            )}

            {/* Duplicado: diz de onde veio, e diz que não é o mesmo documento. */}
            {copia.data && (
                <div className={cls('flex flex-wrap items-center gap-2 border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900', RAIO)} data-duplicado-de={copia.data.origem.numero ?? ''}>
                    <i className="fas fa-copy" aria-hidden="true" />
                    <span>
                        {t('Duplicado de')} <strong>{copia.data.origem.numero ?? t('documento sem número')}</strong>{' '}
                        {t('— nasce como factura nova, sem número nem série. Confira as datas e emita.')}
                    </span>
                </div>
            )}

            {/* Um fieldset desligado fecha tudo o que está dentro, botões incluídos. */}
            <fieldset disabled={soLeitura} className="min-w-0 space-y-4 border-0 p-0">
            <Cartao titulo={t('Documento')}>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Campo etiqueta={t('Tipo')} erro={erros.invoice_type} obrigatorio>
                        {/* O tipo define a série e só se fixa na criação. */}
                        <select value={tipo} onChange={(e) => porTipo(e.target.value as 'FT' | 'FR')} disabled={id !== undefined} className={entrada}>
                            <option value="FT">{t('Factura (FT)')}</option>
                            <option value="FR">{t('Factura-Recibo (FR) — paga no acto')}</option>
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Série')} erro={erros.series_id}>
                        <select value={serieId} onChange={(e) => porSerieId(e.target.value)} disabled={id !== undefined} className={entrada}>
                            {seriesDoTipo.length === 0 && <option value="">{t('Sem série activa para este tipo')}</option>}
                            {seriesDoTipo.map((s) => (
                                <option key={s.id} value={s.id}>{s.series_code} · {s.name}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Cliente')} erro={erros.client_id} obrigatorio>
                        <select value={clienteId} onChange={(e) => porClienteId(e.target.value)} className={entrada}>
                            <option value="">{t('Escolher…')}</option>
                            {o.clientes.map((c) => (
                                <option key={c.id} value={c.id}>{c.name}{c.nif ? ` · ${c.nif}` : ''}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Data')} erro={erros.invoice_date} obrigatorio>
                        <input type="date" value={dia} onChange={(e) => porDia(e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Vencimento')} erro={erros.due_date}>
                        <input type="date" value={vencimento} onChange={(e) => porVencimento(e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Data de entrega')} erro={erros.delivery_date}>
                        <input type="date" value={entrega} onChange={(e) => porEntrega(e.target.value)} className={entrada} />
                    </Campo>

                    {/* O armazém só é obrigatório com artigos físicos. */}
                    <Campo etiqueta={t('Armazém')} erro={erros.warehouse_id} obrigatorio={temFisicos}>
                        <select value={armazemId} onChange={(e) => porArmazemId(e.target.value)} className={entrada}>
                            <option value="">{temFisicos ? t('Escolher…') : t('Só serviços — não é preciso')}</option>
                            {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </Campo>

                    {/* Cabinda tem regime próprio. Por omissão deriva da província do cliente. */}
                    <Campo etiqueta={t('Região fiscal')} erro={erros.tax_country_region}>
                        <select value={regiao} onChange={(e) => porRegiao(e.target.value)} className={entrada}>
                            {o.regioes.map((r) => <option key={r.valor} value={r.valor}>{r.rotulo}</option>)}
                        </select>
                    </Campo>

                    {tipo === 'FR' && (
                        <Campo etiqueta={t('Forma de pagamento')} erro={erros.payment_method} obrigatorio className="lg:col-span-2">
                            <select value={pagamento} onChange={(e) => porPagamento(e.target.value)} className={entrada}>
                                <option value="">{t('Escolher…')}</option>
                                {o.formas_de_pagamento.map((f) => <option key={f.id} value={f.code}>{f.name}</option>)}
                            </select>
                        </Campo>
                    )}
                </div>
            </Cartao>

            <Cartao titulo={t('Linhas')} accoes={!soLeitura && <Botao icone="fa-plus" onClick={() => porLinhas((ls) => [...ls, { ...LINHA_NOVA }])}>{t('Nova linha')}</Botao>} semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                <th className="px-4 py-3 font-semibold">{t('Artigo')}</th>
                                <th className="px-4 py-3 font-semibold">{t('Descrição')}</th>
                                <th className="w-24 px-4 py-3 text-right font-semibold">{t('Qtd.')}</th>
                                <th className="w-32 px-4 py-3 text-right font-semibold">{t('Preço')}</th>
                                <th className="w-24 px-4 py-3 text-right font-semibold">{t('Desc. %')}</th>
                                <th className="w-12 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {linhas.map((l, i) => (
                                <tr key={i}>
                                    <td className="px-4 py-2">
                                        <select value={l.product_id ?? ''} onChange={(e) => mudarLinha(i, 'product_id', e.target.value)} aria-label={t('Artigo da linha :n', { n: i + 1 })} className={entrada}>
                                            <option value="">{t('Escolher…')}</option>
                                            {o.artigos.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                                        </select>
                                    </td>
                                    <td className="px-4 py-2"><input value={l.description} onChange={(e) => mudarLinha(i, 'description', e.target.value)} aria-label={t('Descrição da linha :n', { n: i + 1 })} className={entrada} /></td>
                                    <td className="px-4 py-2"><input type="number" min="0" step="0.001" value={l.quantity} onChange={(e) => mudarLinha(i, 'quantity', e.target.value)} aria-label={t('Quantidade da linha :n', { n: i + 1 })} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2"><input type="number" min="0" step="0.01" value={l.price} onChange={(e) => mudarLinha(i, 'price', e.target.value)} aria-label={t('Preço da linha :n', { n: i + 1 })} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2"><input type="number" min="0" max="100" step="0.01" value={l.discount_percent} onChange={(e) => mudarLinha(i, 'discount_percent', e.target.value)} aria-label={t('Desconto da linha :n', { n: i + 1 })} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2 text-right">
                                        {linhas.length > 1 && !soLeitura && (
                                            <button type="button" onClick={() => porLinhas((ls) => ls.filter((_, j) => j !== i))} aria-label={t('Apagar linha :n', { n: i + 1 })} className={cls('p-2 text-red-500 transition hover:bg-red-50', RAIO, FOCO)}>
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
                <Cartao titulo={t('Descontos e retenção')}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Desconto comercial (Kz)')} erro={erros.discount_commercial}>
                            <input type="number" min="0" step="0.01" value={descontoComercial} onChange={(e) => porDescontoComercial(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                        </Campo>
                        <Campo etiqueta={t('Desconto financeiro (Kz)')} erro={erros.discount_financial}>
                            <input type="number" min="0" step="0.01" value={descontoFinanceiro} onChange={(e) => porDescontoFinanceiro(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                        </Campo>
                        <Campo etiqueta={t('Retenção na fonte')} erro={erros.withholding_type}>
                            <select value={retencaoTipo} onChange={(e) => porRetencaoTipo(e.target.value)} className={entrada}>
                                <option value="">{t('Sem retenção')}</option>
                                {o.retencoes.map((r) => <option key={r.valor} value={r.valor}>{r.rotulo}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta={t('Percentagem')} erro={erros.withholding_percentage}>
                            <input type="number" min="0" max="100" step="0.01" value={retencaoPct} onChange={(e) => porRetencaoPct(e.target.value)} disabled={!retencaoTipo} className={cls(entrada, 'text-right tabular-nums')} />
                        </Campo>
                    </div>
                    <div className="mt-4">
                        <Campo etiqueta={t('Observações')} erro={erros.notes}>
                            <textarea rows={2} value={notas} onChange={(e) => porNotas(e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                        </Campo>
                    </div>
                </Cartao>

                {/* OS TOTAIS SÃO OS DO SERVIDOR. */}
                <Cartao titulo={t('Totais')}>
                    {totais ? (
                        <dl className={cls('space-y-1.5 text-sm', aContar && 'opacity-50')}>
                            <Total rotulo={t('Valor bruto')} valor={totais.bruto} />
                            {totais.desconto_comercial > 0 && <Total rotulo={t('Desconto comercial')} valor={-totais.desconto_comercial} />}
                            <Total rotulo={t('Incidência de IVA')} valor={totais.base} />
                            <Total rotulo={t('Imposto')} valor={totais.imposto} />
                            {Number(descontoFinanceiro) > 0 && <Total rotulo={t('Desconto financeiro')} valor={-Number(descontoFinanceiro)} />}
                            {retencaoValor > 0 && <Total rotulo={t('Retenção :tipo', { tipo: retencaoTipo })} valor={-retencaoValor} />}
                            <div className="mt-2 flex items-baseline justify-between border-t border-slate-200 pt-2">
                                <dt className="font-bold text-slate-900">{t('Total')}</dt>
                                <dd className="text-xl font-bold tabular-nums text-slate-900">{kz(totais.total - retencaoValor)} <span className="text-sm font-normal text-slate-400">Kz</span></dd>
                            </div>
                            <p className="pt-1 text-xs text-slate-400">{t('Contado no servidor — é o mesmo cálculo que assina o documento.')}</p>
                        </dl>
                    ) : (
                        <p className="py-6 text-center text-sm text-slate-400">{t('Escolha um artigo e uma quantidade para ver os totais.')}</p>
                    )}
                </Cartao>
            </div>
            </fieldset>

            <div className="flex items-center justify-end gap-2">
                <Botao onClick={() => (window.location.href = '/invoicing/sales/invoices')}>{soLeitura ? t('Voltar às facturas') : t('Cancelar')}</Botao>
                {!soLeitura && <Botao icone="fa-file" aTrabalhar={guardar.isPending && guardar.variables === 'draft'} onClick={() => guardar.mutate('draft')}>{t('Guardar rascunho')}</Botao>}
                {!soLeitura && (
                    <Botao cor="primaria" tom="solida" altura="grande" icone="fa-file-signature" aTrabalhar={guardar.isPending && guardar.variables === 'pending'} disabled={!o.permissoes.pode_criar} onClick={() => guardar.mutate('pending')}>
                        {tipo === 'FR' ? t('Emitir factura-recibo') : t('Emitir factura')}
                    </Botao>
                )}
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
