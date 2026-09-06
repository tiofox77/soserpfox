import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';

import { compra, type LinhaDaCompra } from '@/api/compra';
import type { Totais } from '@/api/emissor';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

/**
 * REGISTAR UMA FACTURA DE COMPRA — ou abrir uma que já existe.
 *
 * É a factura do fornecedor, e é ela que dá ENTRADA de stock, cria os lotes
 * e actualiza o custo do artigo. Nada disso se faz aqui: o ecrã recolhe o
 * cabeçalho e as linhas, pergunta os totais ao servidor a cada alteração, e
 * grava pelo `EmissorDeCompras`. Com `id`, abre a compra: só um rascunho se
 * altera — a registada já deu entrada do stock e abre-se só para ler.
 *
 * O PREÇO DE CADA LINHA É O QUE O FORNECEDOR COBROU: nasce do custo
 * conhecido do artigo e corrige-se à mão. O lote e a validade são por linha,
 * porque é a compra que os cria.
 */

const LINHA_NOVA: LinhaDaCompra = { product_id: null, description: '', quantity: 1, price: 0, discount_percent: 0, batch_number: '', expiry_date: '' };

type Estado = 'draft' | 'pending' | 'paid';

export default function EmitirFacturaDeCompra({ id }: { id?: number }) {
    const [fornecedorId, porFornecedorId] = useState('');
    const [armazemId, porArmazemId] = useState('');
    const [dia, porDia] = useState(() => new Date().toISOString().slice(0, 10));
    const [vencimento, porVencimento] = useState('');
    const [regiao, porRegiao] = useState('AO');
    const [eServico, porEServico] = useState(false);
    const [descontoComercial, porDescontoComercial] = useState('');
    const [descontoFinanceiro, porDescontoFinanceiro] = useState('');
    const [notas, porNotas] = useState('');
    const [linhas, porLinhas] = useState<LinhaDaCompra[]>([{ ...LINHA_NOVA }]);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [feito, porFeito] = useState<{ numero: string; abrir: string; mensagem: string } | null>(null);
    const [totais, porTotais] = useState<Totais | null>(null);
    const [aContar, porAContar] = useState(false);

    const opcoes = useQuery({ queryKey: ['compra', 'opcoes'], queryFn: compra.opcoes, staleTime: 5 * 60_000 });
    const aberta = useQuery({ queryKey: ['compra', 'abrir', id], queryFn: () => compra.abrir(id ?? 0), enabled: id !== undefined });

    /* A compra aberta entra no formulário tal como está. */
    useEffect(() => {
        const d = aberta.data?.documento;
        if (!d) return;
        porFornecedorId(d.supplier_id ? String(d.supplier_id) : '');
        porArmazemId(d.warehouse_id ? String(d.warehouse_id) : '');
        porDia(d.invoice_date ?? new Date().toISOString().slice(0, 10));
        porVencimento(d.due_date ?? '');
        porRegiao(d.tax_country_region || 'AO');
        porEServico(d.is_service);
        porDescontoComercial(d.discount_commercial ? String(d.discount_commercial) : '');
        porDescontoFinanceiro(d.discount_financial ? String(d.discount_financial) : '');
        porNotas(d.notes ?? '');
        porLinhas(aberta.data && aberta.data.linhas.length > 0 ? aberta.data.linhas : [{ ...LINHA_NOVA }]);
    }, [aberta.data]);

    /* O armazém por omissão, quando só há um — numa compra nova. */
    useEffect(() => {
        const unico = opcoes.data?.armazens.length === 1 ? opcoes.data.armazens[0] : undefined;
        if (unico && id === undefined) porArmazemId(String(unico.id));
    }, [opcoes.data, id]);

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

        const t = setTimeout(() => {
            compra
                .calcular({
                    linhas: comLinhas,
                    discount_commercial: Number(descontoComercial) || 0,
                    discount_financial: Number(descontoFinanceiro) || 0,
                    is_service: eServico,
                })
                .then((r) => { if (!cancelado) porTotais(r.totais); })
                .catch(() => { if (!cancelado) porTotais(null); })
                .finally(() => { if (!cancelado) porAContar(false); });
        }, 400);

        return () => { cancelado = true; clearTimeout(t); };
    }, [linhas, descontoComercial, descontoFinanceiro, eServico]);

    const guardar = useMutation({
        mutationFn: (status: Estado) => {
            const corpo = {
                supplier_id: Number(fornecedorId) || null,
                warehouse_id: Number(armazemId) || null,
                invoice_date: dia,
                due_date: vencimento || null,
                tax_country_region: regiao,
                is_service: eServico,
                discount_commercial: Number(descontoComercial) || 0,
                discount_financial: Number(descontoFinanceiro) || 0,
                notes: notas || null,
                status,
                linhas: linhas
                    .filter(comConteudo)
                    .map((l) => ({ ...l, batch_number: l.batch_number || null, expiry_date: l.expiry_date || null })),
            };
            return id !== undefined ? compra.actualizar(id, corpo) : compra.guardar(corpo);
        },
        onSuccess: (r) => { porFeito({ numero: r.numero, abrir: r.abrir, mensagem: r.message }); porErros({}); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    if (opcoes.isPending || (id !== undefined && aberta.isPending)) return <Carregando linhas={8} />;

    if (opcoes.isError || aberta.isError) {
        const erro = opcoes.error ?? aberta.error;
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível abrir o registo de compras</h2>
                <p className="text-sm text-red-800">{erro instanceof ErroDaApi ? erro.message : 'Verifique a ligação.'}</p>
            </div>
        );
    }

    if (feito) {
        return (
            <div className={cls(CARTAO, 'p-8 text-center')}>
                <i className="fas fa-circle-check mb-3 text-4xl text-emerald-500" aria-hidden="true" />
                <h2 className="text-xl font-bold text-slate-900">{feito.numero}</h2>
                <p className="mt-1 text-sm text-slate-500">{feito.mensagem}</p>
                <div className="mt-6 flex justify-center gap-2">
                    <Botao cor="primaria" tom="solida" icone="fa-list" onClick={() => (window.location.href = feito.abrir)}>Ver compras</Botao>
                    {id === undefined && <Botao icone="fa-plus" onClick={() => { porFeito(null); porLinhas([{ ...LINHA_NOVA }]); porFornecedorId(''); porNotas(''); }}>Registar outra</Botao>}
                </div>
            </div>
        );
    }

    const o = opcoes.data;
    const doc = aberta.data?.documento ?? null;
    const soLeitura = doc !== null && !doc.pode_editar;

    const mudarLinha = (i: number, campo: keyof LinhaDaCompra, valor: string) =>
        porLinhas((ls) =>
            ls.map((l, j) => {
                if (j !== i) return l;
                if (campo === 'product_id') {
                    const artigo = o.artigos.find((a) => String(a.id) === valor);
                    return { ...l, product_id: valor ? Number(valor) : null, price: artigo ? artigo.cost : l.price, description: artigo ? artigo.name : l.description };
                }
                return { ...l, [campo]: valor };
            }),
        );

    const aGuardar = (estado: Estado) => guardar.isPending && guardar.variables === estado;

    return (
        <div className="space-y-4" data-emissor="compra">
            <AvisoDeErro erro={guardar.error} />

            {doc && (
                <div className={cls('flex flex-wrap items-center gap-3 border px-4 py-3 text-sm', RAIO, soLeitura ? 'border-slate-200 bg-slate-50 text-slate-700' : 'border-amber-200 bg-amber-50 text-amber-900')} data-documento-aberto>
                    <strong>{doc.numero ?? 'Rascunho'}</strong>
                    <Etiqueta cor={soLeitura ? 'neutra' : 'aviso'}>{doc.estado}</Etiqueta>
                    {soLeitura ? 'Compra registada: só leitura. O stock já deu entrada.' : 'Rascunho: pode alterar e registar.'}
                </div>
            )}

            <fieldset disabled={soLeitura} className="min-w-0 space-y-4 border-0 p-0">
            <Cartao titulo="Documento do fornecedor">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Campo etiqueta="Fornecedor" erro={erros.supplier_id} obrigatorio className="lg:col-span-2">
                        <select value={fornecedorId} onChange={(e) => porFornecedorId(e.target.value)} className={entrada}>
                            <option value="">Escolher…</option>
                            {o.fornecedores.map((f) => (
                                <option key={f.id} value={f.id}>{f.name}{f.nif ? ` · ${f.nif}` : ''}</option>
                            ))}
                        </select>
                    </Campo>

                    {/* A compra dá entrada de stock: o armazém é sempre obrigatório. */}
                    <Campo etiqueta="Armazém" erro={erros.warehouse_id} obrigatorio>
                        <select value={armazemId} onChange={(e) => porArmazemId(e.target.value)} className={entrada}>
                            <option value="">Escolher…</option>
                            {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </Campo>

                    {/* Cabinda tem regime próprio; o que manda é o local da operação. */}
                    <Campo etiqueta="Região fiscal" erro={erros.tax_country_region}>
                        <select value={regiao} onChange={(e) => porRegiao(e.target.value)} className={entrada}>
                            {o.regioes.map((r) => <option key={r.valor} value={r.valor}>{r.rotulo}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta="Data" erro={erros.invoice_date} obrigatorio>
                        <input type="date" value={dia} onChange={(e) => porDia(e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta="Vencimento" erro={erros.due_date}>
                        <input type="date" value={vencimento} onChange={(e) => porVencimento(e.target.value)} className={entrada} />
                    </Campo>

                    <label className="flex items-center gap-2 self-end pb-2 text-sm text-slate-700">
                        <input type="checkbox" checked={eServico} onChange={(e) => porEServico(e.target.checked)} className="h-4 w-4 rounded border-slate-300" />
                        Prestação de serviço (retém IRT 6,5%)
                    </label>
                </div>
            </Cartao>

            <Cartao titulo="Linhas" accoes={!soLeitura && <Botao icone="fa-plus" onClick={() => porLinhas((ls) => [...ls, { ...LINHA_NOVA }])}>Nova linha</Botao>} semPadding>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                <th className="px-4 py-3 font-semibold">Artigo</th>
                                <th className="w-24 px-4 py-3 text-right font-semibold">Qtd.</th>
                                <th className="w-32 px-4 py-3 text-right font-semibold">Preço de compra</th>
                                <th className="w-24 px-4 py-3 text-right font-semibold">Desc. %</th>
                                <th className="w-32 px-4 py-3 font-semibold">Lote</th>
                                <th className="w-36 px-4 py-3 font-semibold">Validade</th>
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
                                        {l.product_id === null && (
                                            <input value={l.description} onChange={(e) => mudarLinha(i, 'description', e.target.value)} placeholder="Ou descreva a linha…" aria-label={`Descrição da linha ${i + 1}`} className={cls(entrada, 'mt-1')} />
                                        )}
                                    </td>
                                    <td className="px-4 py-2 align-top"><input type="number" min="0" step="0.001" value={l.quantity} onChange={(e) => mudarLinha(i, 'quantity', e.target.value)} aria-label={`Quantidade da linha ${i + 1}`} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2 align-top"><input type="number" min="0" step="0.01" value={l.price} onChange={(e) => mudarLinha(i, 'price', e.target.value)} aria-label={`Preço da linha ${i + 1}`} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2 align-top"><input type="number" min="0" max="100" step="0.01" value={l.discount_percent} onChange={(e) => mudarLinha(i, 'discount_percent', e.target.value)} aria-label={`Desconto da linha ${i + 1}`} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2 align-top"><input value={l.batch_number} onChange={(e) => mudarLinha(i, 'batch_number', e.target.value)} aria-label={`Lote da linha ${i + 1}`} className={entrada} /></td>
                                    <td className="px-4 py-2 align-top"><input type="date" value={l.expiry_date} onChange={(e) => mudarLinha(i, 'expiry_date', e.target.value)} aria-label={`Validade da linha ${i + 1}`} className={entrada} /></td>
                                    <td className="px-4 py-2 text-right align-top">
                                        {linhas.length > 1 && !soLeitura && (
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
                <Cartao titulo="Descontos e observações">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta="Desconto comercial (Kz)" erro={erros.discount_commercial}>
                            <input type="number" min="0" step="0.01" value={descontoComercial} onChange={(e) => porDescontoComercial(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                        </Campo>
                        <Campo etiqueta="Desconto financeiro (Kz)" erro={erros.discount_financial}>
                            <input type="number" min="0" step="0.01" value={descontoFinanceiro} onChange={(e) => porDescontoFinanceiro(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                        </Campo>
                    </div>
                    <div className="mt-4">
                        <Campo etiqueta="Observações" erro={erros.notes}>
                            <textarea rows={3} value={notas} onChange={(e) => porNotas(e.target.value)} className={cls(entrada, 'h-auto py-2')} />
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
                            {totais.retencao > 0 && <Total rotulo="Retenção IRT (6,5%)" valor={-totais.retencao} />}
                            <div className="mt-2 flex items-baseline justify-between border-t border-slate-200 pt-2">
                                <dt className="font-bold text-slate-900">Total a pagar</dt>
                                <dd className="text-xl font-bold tabular-nums text-slate-900">{kz(totais.total)} <span className="text-sm font-normal text-slate-400">Kz</span></dd>
                            </div>
                            <p className="pt-1 text-xs text-slate-400">Contado no servidor.</p>
                        </dl>
                    ) : (
                        <p className="py-6 text-center text-sm text-slate-400">Escolha um artigo e uma quantidade para ver os totais.</p>
                    )}
                </Cartao>
            </div>
            </fieldset>

            <div className="flex flex-wrap items-center justify-end gap-2">
                <Botao onClick={() => (window.location.href = '/invoicing/purchases/invoices')}>{soLeitura ? 'Voltar às compras' : 'Cancelar'}</Botao>
                {!soLeitura && <Botao icone="fa-file" aTrabalhar={aGuardar('draft')} onClick={() => guardar.mutate('draft')}>Guardar rascunho</Botao>}
                {!soLeitura && <Botao icone="fa-money-bill" aTrabalhar={aGuardar('paid')} disabled={!o.permissoes.pode_criar} onClick={() => guardar.mutate('paid')}>Registar como paga</Botao>}
                {!soLeitura && (
                    <Botao cor="primaria" tom="solida" altura="grande" icone="fa-truck-ramp-box" aTrabalhar={aGuardar('pending')} disabled={!o.permissoes.pode_criar} onClick={() => guardar.mutate('pending')}>
                        Registar compra
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
