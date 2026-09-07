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
import { cls, kz } from '@/ui/tokens';
import { t } from '@/i18n';
import { EscolhaDaParte } from './EscolhaDaParte';
import { EscolhaDeArtigo, juntarArtigo } from './EscolhaDeArtigo';
import {
    ApagarLinha,
    CABECALHO_DA_TABELA,
    CELULA_DO_CABECALHO,
    CartaoDeTotais,
    FaixaDeDuplicado,
    FaixaDoDocumento,
    LINHA_DA_TABELA,
    NaoAbriu,
    PainelDeSucesso,
    ParcelaDoTotal,
    SemNada,
    TotalGrande,
    cascata,
} from './PecasDoEditor';

/**
 * REGISTAR UMA FACTURA DE COMPRA — ou abrir uma que já existe.
 *
 * É a factura do fornecedor, e é ela que dá ENTRADA de stock, cria os lotes
 * e actualiza o custo do artigo. Nada disso se faz aqui: o ecrã recolhe o
 * cabeçalho e as linhas, pergunta os totais ao servidor a cada alteração, e
 * grava pelo `EmissorDeCompras`. Com `id`, abre a compra: só um rascunho se
 * altera — a registada já deu entrada do stock e abre-se só para ler.
 *
 * Com `duplicarDe` (`?duplicar=123` na morada), abre com o CONTEÚDO de outra
 * compra — fornecedor, linhas, preços, lotes — e mais nada: sem `id` e sem
 * número, gravar regista uma compra nova. Duplicar não mexe em stock nenhum;
 * o stock só entra quando o duplicado for mesmo gravado.
 *
 * O PREÇO DE CADA LINHA É O QUE O FORNECEDOR COBROU: nasce do custo
 * conhecido do artigo e corrige-se à mão. O lote e a validade são por linha,
 * porque é a compra que os cria.
 */

const LINHA_NOVA: LinhaDaCompra = { product_id: null, description: '', quantity: 1, price: 0, discount_percent: 0, batch_number: '', expiry_date: '' };

type Estado = 'draft' | 'pending' | 'paid';

export default function EmitirFacturaDeCompra({ id, duplicarDe }: { id?: number; duplicarDe?: number }) {
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
    const copia = useQuery({ queryKey: ['compra', 'duplicar', duplicarDe], queryFn: () => compra.duplicar(duplicarDe ?? 0), enabled: id === undefined && duplicarDe !== undefined });

    /*
     * O conteúdo que entra no formulário — venha de uma compra aberta ou de
     * uma duplicada. É o MESMO carregamento: o duplicado herda o que a edição
     * herdaria, e o que ele não herda é o que o servidor não mandou.
     */
    const carregado = aberta.data ?? copia.data ?? null;

    useEffect(() => {
        const d = carregado?.documento;
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
        porLinhas(carregado.linhas.length > 0 ? carregado.linhas : [{ ...LINHA_NOVA }]);
    }, [carregado]);

    /* O armazém por omissão, quando só há um — numa compra nova e vazia. */
    useEffect(() => {
        const unico = opcoes.data?.armazens.length === 1 ? opcoes.data.armazens[0] : undefined;
        if (unico && id === undefined && duplicarDe === undefined) porArmazemId(String(unico.id));
    }, [opcoes.data, id, duplicarDe]);

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

        return () => { cancelado = true; clearTimeout(pausa); };
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

    if (opcoes.isPending || (id !== undefined && aberta.isPending) || (copia.isPending && copia.fetchStatus !== 'idle')) return <Carregando linhas={8} />;

    if (opcoes.isError || aberta.isError || copia.isError) {
        const erro = opcoes.error ?? aberta.error ?? copia.error;
        return (
            <NaoAbriu
                titulo={t('Não foi possível abrir o registo de compras')}
                mensagem={erro instanceof ErroDaApi ? erro.message : t('Verifique a ligação.')}
            />
        );
    }

    if (feito) {
        return (
            <PainelDeSucesso numero={feito.numero} mensagem={feito.mensagem} icone="fa-truck-ramp-box">
                <Botao cor="primaria" tom="solida" icone="fa-list" onClick={() => (window.location.href = feito.abrir)}>{t('Ver compras')}</Botao>
                {id === undefined && <Botao icone="fa-plus" onClick={() => { porFeito(null); porLinhas([{ ...LINHA_NOVA }]); porFornecedorId(''); porNotas(''); }}>{t('Registar outra')}</Botao>}
            </PainelDeSucesso>
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
                <FaixaDoDocumento soLeitura={soLeitura} numero={doc.numero ?? t('Rascunho')} estado={doc.estado}>
                    {soLeitura ? t('Compra registada: só leitura. O stock já deu entrada.') : t('Rascunho: pode alterar e registar.')}
                </FaixaDoDocumento>
            )}

            {/* Duplicado: diz de onde veio, e diz que não é o mesmo documento. */}
            {copia.data && (
                <FaixaDeDuplicado numeroDaOrigem={copia.data.origem.numero ?? ''}>
                    {t('Duplicado de')} <strong className="font-bold">{copia.data.origem.numero ?? t('documento sem número')}</strong>{' '}
                    {t('— nasce como compra nova, sem número. O stock só entra quando esta for registada.')}
                </FaixaDeDuplicado>
            )}

            <fieldset disabled={soLeitura} className="min-w-0 space-y-4 border-0 p-0">
            <Cartao titulo={t('Documento do fornecedor')} icone="fa-circle-info">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* O fornecedor escolhe-se com procura, e cria-se aqui
                        mesmo quando ainda não existe — ver `EscolhaDaParte`. */}
                    <EscolhaDaParte
                        criar={o.criar_parte}
                        partes={o.fornecedores}
                        valor={fornecedorId}
                        aoEscolher={porFornecedorId}
                        erro={erros.supplier_id}
                        className="lg:col-span-2"
                    />

                    {/* A compra dá entrada de stock: o armazém é sempre obrigatório. */}
                    <Campo etiqueta={t('Armazém')} erro={erros.warehouse_id} obrigatorio>
                        <select value={armazemId} onChange={(e) => porArmazemId(e.target.value)} className={entrada}>
                            <option value="">{t('Escolher…')}</option>
                            {o.armazens.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </Campo>

                    {/* Cabinda tem regime próprio; o que manda é o local da operação. */}
                    <Campo etiqueta={t('Região fiscal')} erro={erros.tax_country_region}>
                        <select value={regiao} onChange={(e) => porRegiao(e.target.value)} className={entrada}>
                            {o.regioes.map((r) => <option key={r.valor} value={r.valor}>{r.rotulo}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Data')} erro={erros.invoice_date} obrigatorio>
                        <input type="date" value={dia} onChange={(e) => porDia(e.target.value)} className={entrada} />
                    </Campo>

                    <Campo etiqueta={t('Vencimento')} erro={erros.due_date}>
                        <input type="date" value={vencimento} onChange={(e) => porVencimento(e.target.value)} className={entrada} />
                    </Campo>

                    <label className="flex items-center gap-2 self-end pb-2 text-sm text-slate-700">
                        <input type="checkbox" checked={eServico} onChange={(e) => porEServico(e.target.checked)} className="h-4 w-4 rounded border-slate-300" />
                        {t('Prestação de serviço (retém IRT 6,5%)')}
                    </label>
                </div>
            </Cartao>

            <Cartao
                titulo={t('Linhas')}
                icone="fa-box"
                accoes={
                    !soLeitura && (
                        <>
                            {/* NUMA COMPRA O QUE SE PROPÕE É O CUSTO, não o
                                preço de venda — é o mesmo que o `<select>` já
                                fazia, e o cartão mostra o mesmo número. */}
                            <EscolhaDeArtigo
                                preco="custo"
                                catalogo={o.artigos.map((a) => ({ ...a, price: a.cost }))}
                                aoEscolher={(a) => porLinhas((ls) => juntarArtigo(ls, { ...LINHA_NOVA }, a))}
                            />
                            <Botao altura="pequeno" icone="fa-plus" onClick={() => porLinhas((ls) => [...ls, { ...LINHA_NOVA }])}>{t('Nova linha')}</Botao>
                        </>
                    )
                }
                semPadding
            >
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className={CABECALHO_DA_TABELA}>
                            <tr className="border-b border-slate-200">
                                <th className={CELULA_DO_CABECALHO}>{t('Artigo')}</th>
                                <th className={cls('w-24 text-right', CELULA_DO_CABECALHO)}>{t('Qtd.')}</th>
                                <th className={cls('w-32 text-right', CELULA_DO_CABECALHO)}>{t('Preço de compra')}</th>
                                <th className={cls('w-24 text-right', CELULA_DO_CABECALHO)}>{t('Desc. %')}</th>
                                <th className={cls('w-32', CELULA_DO_CABECALHO)}>{t('Lote')}</th>
                                <th className={cls('w-36', CELULA_DO_CABECALHO)}>{t('Validade')}</th>
                                <th className={cls('w-12', CELULA_DO_CABECALHO)}></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {linhas.map((l, i) => (
                                <tr key={i} className={LINHA_DA_TABELA} style={cascata(i)}>
                                    <td className="px-4 py-2">
                                        <select value={l.product_id ?? ''} onChange={(e) => mudarLinha(i, 'product_id', e.target.value)} aria-label={t('Artigo da linha :n', { n: i + 1 })} className={entrada}>
                                            <option value="">{t('Escolher…')}</option>
                                            {o.artigos.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                                        </select>
                                        {l.product_id === null && (
                                            <input value={l.description} onChange={(e) => mudarLinha(i, 'description', e.target.value)} placeholder={t('Ou descreva a linha…')} aria-label={t('Descrição da linha :n', { n: i + 1 })} className={cls(entrada, 'mt-1')} />
                                        )}
                                    </td>
                                    <td className="px-4 py-2 align-top"><input type="number" min="0" step="0.001" value={l.quantity} onChange={(e) => mudarLinha(i, 'quantity', e.target.value)} aria-label={t('Quantidade da linha :n', { n: i + 1 })} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2 align-top"><input type="number" min="0" step="0.01" value={l.price} onChange={(e) => mudarLinha(i, 'price', e.target.value)} aria-label={t('Preço da linha :n', { n: i + 1 })} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2 align-top"><input type="number" min="0" max="100" step="0.01" value={l.discount_percent} onChange={(e) => mudarLinha(i, 'discount_percent', e.target.value)} aria-label={t('Desconto da linha :n', { n: i + 1 })} className={cls(entrada, 'text-right tabular-nums')} /></td>
                                    <td className="px-4 py-2 align-top"><input value={l.batch_number} onChange={(e) => mudarLinha(i, 'batch_number', e.target.value)} aria-label={t('Lote da linha :n', { n: i + 1 })} className={entrada} /></td>
                                    <td className="px-4 py-2 align-top"><input type="date" value={l.expiry_date} onChange={(e) => mudarLinha(i, 'expiry_date', e.target.value)} aria-label={t('Validade da linha :n', { n: i + 1 })} className={entrada} /></td>
                                    <td className="px-4 py-2 text-right align-top">
                                        {/* A última linha não se apaga: um documento sem linhas não é um documento. */}
                                        {linhas.length > 1 && !soLeitura && (
                                            <ApagarLinha aoCarregar={() => porLinhas((ls) => ls.filter((_, j) => j !== i))} rotulo={t('Apagar linha :n', { n: i + 1 })} />
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
                <Cartao titulo={t('Descontos e observações')} icone="fa-tags">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Desconto comercial (Kz)')} erro={erros.discount_commercial}>
                            <input type="number" min="0" step="0.01" value={descontoComercial} onChange={(e) => porDescontoComercial(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                        </Campo>
                        <Campo etiqueta={t('Desconto financeiro (Kz)')} erro={erros.discount_financial}>
                            <input type="number" min="0" step="0.01" value={descontoFinanceiro} onChange={(e) => porDescontoFinanceiro(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                        </Campo>
                    </div>
                    <div className="mt-4">
                        <Campo etiqueta={t('Observações')} erro={erros.notes}>
                            <textarea rows={3} value={notas} onChange={(e) => porNotas(e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                        </Campo>
                    </div>
                </Cartao>

                {/* OS TOTAIS SÃO OS DO SERVIDOR. */}
                <CartaoDeTotais titulo={t('Totais')} aContar={aContar}>
                    {totais ? (
                        <>
                            <dl className={cls('px-5 pt-3', aContar && 'opacity-60')}>
                                <ParcelaDoTotal rotulo={t('Valor bruto')} valor={kz(totais.bruto)} />
                                {totais.desconto_comercial > 0 && <ParcelaDoTotal rotulo={t('Desconto comercial')} valor={kz(-totais.desconto_comercial)} icone="fa-scissors" />}
                                <ParcelaDoTotal rotulo={t('Incidência de IVA')} valor={kz(totais.base)} />
                                <ParcelaDoTotal rotulo={t('Imposto')} valor={kz(totais.imposto)} icone="fa-percent" realce="imposto" />
                                {Number(descontoFinanceiro) > 0 && <ParcelaDoTotal rotulo={t('Desconto financeiro')} valor={kz(-Number(descontoFinanceiro))} icone="fa-scissors" />}
                                {totais.retencao > 0 && <ParcelaDoTotal rotulo={t('Retenção IRT (6,5%)')} valor={kz(-totais.retencao)} icone="fa-hand-holding-dollar" realce="retencao" />}
                            </dl>
                            <TotalGrande
                                rotulo={t('Total a pagar')}
                                valor={<>{kz(totais.total)} <span className="text-base font-normal text-emerald-800/60">Kz</span></>}
                                nota={t('Contado no servidor.')}
                            />
                        </>
                    ) : (
                        <SemNada icone="fa-calculator">{t('Escolha um artigo e uma quantidade para ver os totais.')}</SemNada>
                    )}
                </CartaoDeTotais>
            </div>
            </fieldset>

            <div className="flex flex-wrap items-center justify-end gap-2">
                <Botao onClick={() => (window.location.href = '/invoicing/purchases/invoices')}>{soLeitura ? t('Voltar às compras') : t('Cancelar')}</Botao>
                {!soLeitura && <Botao icone="fa-file" aTrabalhar={aGuardar('draft')} onClick={() => guardar.mutate('draft')}>{t('Guardar rascunho')}</Botao>}
                {!soLeitura && <Botao icone="fa-money-bill" aTrabalhar={aGuardar('paid')} disabled={!o.permissoes.pode_criar} onClick={() => guardar.mutate('paid')}>{t('Registar como paga')}</Botao>}
                {!soLeitura && (
                    <Botao cor="primaria" tom="solida" altura="grande" icone="fa-truck-ramp-box" aTrabalhar={aGuardar('pending')} disabled={!o.permissoes.pode_criar} onClick={() => guardar.mutate('pending')}>
                        {t('Registar compra')}
                    </Botao>
                )}
            </div>
        </div>
    );
}
