import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';

import { turnos, type ProdutoDoTurno, type TipoDeFecho, type Turno } from '@/api/turnos';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';
import { t } from '@/i18n';
import { SemNada, cascata } from './faixa';

/**
 * O FECHO COM PRODUTOS (16/09/2026) — as peças partilhadas pelo turno do
 * balcão e pelo histórico: a pergunta «resumido ou com produtos», as vendas
 * artigo a artigo com os totais e os documentos, e as ligações para o talão e
 * o PDF no formato escolhido. Os números vêm todos do servidor
 * (`ProdutosDoTurno`), os mesmos que saem no papel.
 */
const kz = (v: number | null | undefined) => `${new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v) || 0)} Kz`;
const qtd = (v: number) => new Intl.NumberFormat('pt-PT', { maximumFractionDigits: 3 }).format(v);

const TIPOS: Array<{ valor: TipoDeFecho; titulo: string; frase: string; icone: string; cor: string }> = [
    { valor: 'resumido', titulo: 'Fecho resumido', frase: 'Os totais por meio de pagamento e a conferência da caixa.', icone: 'fa-receipt', cor: 'from-slate-600 to-slate-800' },
    { valor: 'produtos', titulo: 'Fecho com produtos', frase: 'O resumido, mais as vendas de cada produto, os totais e os documentos do turno.', icone: 'fa-boxes-stacked', cor: 'from-emerald-500 to-teal-600' },
];

/** A PERGUNTA do fecho. Não vem escolhida: quem fecha diz o que quer levar. */
export function EscolhaDoFecho({ valor, aoMudar }: { valor: TipoDeFecho | null; aoMudar: (v: TipoDeFecho) => void }) {
    return (
        <fieldset data-escolha-fecho>
            <legend className="mb-2 text-sm font-semibold text-slate-800">
                {t('Que fecho quer?')} <span className="text-red-600" aria-hidden="true">*</span>
            </legend>
            <div className="grid gap-3 sm:grid-cols-2" role="radiogroup">
                {TIPOS.map((o) => {
                    const escolhido = valor === o.valor;
                    return (
                        <button
                            key={o.valor}
                            type="button"
                            role="radio"
                            aria-checked={escolhido}
                            onClick={() => aoMudar(o.valor)}
                            className={cls(
                                'group relative flex items-start gap-3 border-2 p-4 text-left', RAIO, TRANSICAO, FOCO,
                                escolhido ? 'border-emerald-500 bg-emerald-50/70 shadow-md' : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:border-slate-300 hover:shadow',
                            )}
                        >
                            <span className={cls('grid h-11 w-11 shrink-0 place-items-center bg-gradient-to-br text-white shadow', RAIO, o.cor, escolhido && 'icon-float')}>
                                <i className={cls('fas text-lg', o.icone)} aria-hidden="true" />
                            </span>
                            <span className="min-w-0">
                                <span className="block font-bold text-slate-900">{t(o.titulo)}</span>
                                <span className="mt-0.5 block text-xs leading-relaxed text-slate-600">{t(o.frase)}</span>
                            </span>
                            <span className={cls('absolute right-3 top-3 grid h-5 w-5 place-items-center rounded-full border-2', TRANSICAO, escolhido ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300')}>
                                {escolhido && <i className="fas fa-check text-[10px]" aria-hidden="true" />}
                            </span>
                        </button>
                    );
                })}
            </div>
        </fieldset>
    );
}

const MOSAICO = {
    verde: 'from-emerald-500 to-green-600', azul: 'from-blue-500 to-blue-600', indigo: 'from-indigo-500 to-violet-600',
    teal: 'from-teal-500 to-cyan-600', ambar: 'from-amber-500 to-orange-500', vermelho: 'from-red-500 to-rose-600',
} as const;

/** Um número pequeno com ícone — o cartão grande não cabe numa janela. */
export function Mosaico({ rotulo, valor, icone, tom, nota, i = 0 }: { rotulo: string; valor: string; icone: string; tom: keyof typeof MOSAICO; nota?: string; i?: number }) {
    return (
        <div className={cls('entra flex items-center gap-3 border border-slate-200 bg-white p-3 shadow-sm hover:-translate-y-0.5 hover:shadow-md', RAIO, TRANSICAO)} style={cascata(i)}>
            <span className={cls('grid h-10 w-10 shrink-0 place-items-center bg-gradient-to-br text-white shadow', RAIO, MOSAICO[tom])}>
                <i className={cls('fas', icone)} aria-hidden="true" />
            </span>
            <span className="min-w-0">
                <span className="block text-xs font-semibold text-slate-500">{rotulo}</span>
                <span className="block whitespace-nowrap text-lg font-bold tabular-nums text-slate-900">{valor}</span>
                {nota && <span className="block truncate text-[11px] text-slate-500">{nota}</span>}
            </span>
        </div>
    );
}

/** As ligações do papel: o formato escolhido à frente, o outro ao lado. */
export function PapelDoFecho({ turno, tipo }: { turno: Turno; tipo: TipoDeFecho }) {
    const e = turno.exportar;
    const ligacao = (href: string, icone: string, rotulo: string, forte: boolean) => (
        <a key={href} href={href} target="_blank" rel="noreferrer"
            className={cls('inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold', RAIO, TRANSICAO, FOCO,
                forte ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow hover:-translate-y-0.5 hover:shadow-md' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50')}>
            <i className={cls('fas', icone)} aria-hidden="true" />{rotulo}
        </a>
    );
    const produtos = tipo === 'produtos';

    return (
        <div className="flex flex-wrap gap-2" data-papel-fecho>
            {ligacao(produtos ? e.talao_produtos : e.talao, 'fa-receipt', produtos ? t('Talão com produtos') : t('Talão resumido'), true)}
            {ligacao(produtos ? e.pdf_produtos : e.pdf, 'fa-file-pdf', produtos ? t('PDF com produtos') : t('PDF resumido'), true)}
            {ligacao(produtos ? e.talao : e.talao_produtos, 'fa-receipt', produtos ? t('Talão resumido') : t('Talão com produtos'), false)}
            {ligacao(produtos ? e.pdf : e.pdf_produtos, 'fa-file-pdf', produtos ? t('PDF resumido') : t('PDF com produtos'), false)}
        </div>
    );
}

/**
 * AS VENDAS DO TURNO, artigo a artigo. `compacto` mostra só os totais e os
 * cinco que mais venderam — é o que cabe na janela de fechar.
 */
export function VendasPorProduto({ id, compacto = false }: { id: number; compacto?: boolean }) {
    const q = useQuery({ queryKey: ['turnos', 'produtos', id], queryFn: () => turnos.produtos(id) });
    const [aba, porAba] = useState('produtos');
    const [procura, porProcura] = useState('');

    const filtrados = useMemo(() => {
        const lista = q.data?.produtos ?? [];
        const s = procura.trim().toLowerCase();
        return s ? lista.filter((p) => p.nome.toLowerCase().includes(s) || (p.codigo ?? '').toLowerCase().includes(s)) : lista;
    }, [q.data, procura]);

    if (q.isPending) return <Carregando linhas={compacto ? 3 : 6} />;
    if (q.isError) return <AvisoDeErro erro={q.error} />;

    const { produtos, totais, documentos } = q.data;

    const numeros = (
        <div className={cls('grid gap-3', compacto ? 'grid-cols-2' : 'sm:grid-cols-2 lg:grid-cols-4')}>
            {[
                <CartaoNumero key="l" rotulo={t('Total líquido')} valor={kz(totais.liquido)} icone="fa-sack-dollar" tom="verde" aspecto="claro"
                    nota={t('IVA incluído: :v', { v: kz(totais.imposto) })} />,
                <CartaoNumero key="a" rotulo={t('Artigos vendidos')} valor={String(totais.artigos)} icone="fa-boxes-stacked" tom="azul" aspecto="claro"
                    nota={t('Quantidade: :q', { q: qtd(totais.quantidade) })} />,
                <CartaoNumero key="f" rotulo={t('Facturas')} valor={String(totais.facturas)} icone="fa-file-invoice" tom="indigo" aspecto="claro"
                    nota={[totais.anuladas > 0 ? t(':n anulada(s)', { n: totais.anuladas }) : null, totais.notas > 0 ? t(':n nota(s) de crédito', { n: totais.notas }) : null].filter(Boolean).join(' · ') || t('Sem anuladas nem devoluções')} />,
                <CartaoNumero key="m" rotulo={t('Ticket médio')} valor={kz(totais.ticket_medio)} icone="fa-chart-simple" tom="teal" aspecto="claro" />,
            ].map((c, i) => <div key={i} className="entra" style={cascata(i)}>{c}</div>)}
        </div>
    );

    // A CONTA que leva dos artigos ao líquido: sem ela o total dos artigos e o
    // das facturas parecem não bater quando houve desconto no documento.
    const conta = (
        <dl className={cls('grid gap-x-6 gap-y-1 border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm tabular-nums sm:grid-cols-2', RAIO)} data-conta-do-fecho>
            <div className="flex justify-between gap-3"><dt className="text-slate-600">{t('Total dos artigos')}</dt><dd className="font-semibold">{kz(totais.bruto)}</dd></div>
            <div className="flex justify-between gap-3"><dt className="text-slate-600">{t('Descontos nos documentos')}</dt><dd className={cls('font-semibold', totais.descontos > 0 && 'text-amber-700')}>{totais.descontos > 0 ? `−${kz(totais.descontos)}` : kz(0)}</dd></div>
            <div className="flex justify-between gap-3"><dt className="text-slate-600">{t('Devoluções')}</dt><dd className={cls('font-semibold', totais.devolvido > 0 && 'text-red-700')}>{totais.devolvido > 0 ? `−${kz(totais.devolvido)}` : kz(0)}</dd></div>
            <div className="flex justify-between gap-3 border-t border-slate-200 pt-1 sm:border-t-0 sm:pt-0"><dt className="font-bold text-slate-900">{t('Total líquido')}</dt><dd className="font-bold text-emerald-700">{kz(totais.liquido)}</dd></div>
        </dl>
    );

    if (compacto) {
        const primeiros = produtos.slice(0, 5);
        return (
            <div className="space-y-3" data-vendas-compacto>
                <div className="grid gap-2 sm:grid-cols-2">
                    <Mosaico i={0} rotulo={t('Total líquido')} valor={kz(totais.liquido)} icone="fa-sack-dollar" tom="verde" nota={t('IVA incluído: :v', { v: kz(totais.imposto) })} />
                    <Mosaico i={1} rotulo={t('Artigos vendidos')} valor={String(totais.artigos)} icone="fa-boxes-stacked" tom="azul" nota={t('Quantidade: :q', { q: qtd(totais.quantidade) })} />
                    <Mosaico i={2} rotulo={t('Facturas')} valor={String(totais.facturas)} icone="fa-file-invoice" tom="indigo"
                        nota={[totais.anuladas > 0 ? t(':n anulada(s)', { n: totais.anuladas }) : null, totais.notas > 0 ? t(':n nota(s) de crédito', { n: totais.notas }) : null].filter(Boolean).join(' · ') || undefined} />
                    <Mosaico i={3} rotulo={t('Ticket médio')} valor={kz(totais.ticket_medio)} icone="fa-chart-simple" tom="teal" />
                </div>
                {produtos.length === 0
                    ? <p className="text-sm text-slate-500">{t('Ainda não há artigos vendidos neste turno.')}</p>
                    : (
                        <ul className={cls('divide-y divide-slate-100 border border-slate-200', RAIO)}>
                            {primeiros.map((p, i) => (
                                <li key={p.chave} className="entra flex items-center justify-between gap-3 px-3 py-2 text-sm" style={cascata(i)}>
                                    <span className="min-w-0 truncate"><span className="font-semibold text-slate-900">{p.nome}</span> <span className="text-slate-500">× {qtd(p.liquida)}</span></span>
                                    <span className="whitespace-nowrap font-semibold tabular-nums">{kz(p.liquido)}</span>
                                </li>
                            ))}
                            {produtos.length > primeiros.length && (
                                <li className="px-3 py-2 text-xs text-slate-500">{t('e mais :n artigo(s) — a lista inteira sai no talão e no PDF.', { n: produtos.length - primeiros.length })}</li>
                            )}
                        </ul>
                    )}
            </div>
        );
    }

    return (
        <div className="space-y-4" data-vendas-por-produto>
            {numeros}
            {conta}

            <Separadores
                abas={[
                    { chave: 'produtos', rotulo: t('Produtos (:n)', { n: produtos.length }), icone: 'fa-boxes-stacked' },
                    { chave: 'documentos', rotulo: t('Documentos (:n)', { n: documentos.length }), icone: 'fa-file-invoice' },
                ]}
                activa={aba}
                aoMudar={porAba}
            />

            <PainelDoSeparador chave="produtos" activa={aba}>
                {produtos.length === 0 ? (
                    <SemNada icone="fa-box-open" titulo={t('Sem artigos vendidos')} frase={t('Cada venda do balcão entra aqui assim que é fechada.')} />
                ) : (
                    <div className="space-y-3">
                        {produtos.length > 8 && (
                            <label className="relative block max-w-sm">
                                <span className="sr-only">{t('Procurar artigo')}</span>
                                <i className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                                <input type="search" value={procura} onChange={(e) => porProcura(e.target.value)} placeholder={t('Procurar artigo')}
                                    className={cls('w-full border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm', RAIO, FOCO)} />
                            </label>
                        )}
                        <TabelaDeProdutos linhas={filtrados} />
                    </div>
                )}
            </PainelDoSeparador>

            <PainelDoSeparador chave="documentos" activa={aba}>
                {documentos.length === 0 ? (
                    <SemNada icone="fa-inbox" titulo={t('Sem documentos')} frase={t('Ainda não houve vendas neste turno.')} />
                ) : (
                    <div className={cls('max-h-96 overflow-auto border border-slate-200', RAIO)}>
                        <table className="w-full min-w-[640px] text-sm">
                            <thead className="sticky top-0 z-10">
                                <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600">
                                    <th className="px-3 py-2 font-semibold">{t('Hora')}</th>
                                    <th className="px-3 py-2 font-semibold">{t('Documento')}</th>
                                    <th className="px-3 py-2 font-semibold">{t('Cliente')}</th>
                                    <th className="px-3 py-2 font-semibold">{t('Pagamento')}</th>
                                    <th className="px-3 py-2 text-right font-semibold">{t('Artigos')}</th>
                                    <th className="px-3 py-2 text-right font-semibold">{t('Total')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {documentos.map((d, i) => (
                                    <tr key={`${d.tipo}-${d.numero}`} className={cls('entra hover:bg-emerald-50/50', d.anulada && 'text-slate-400')} style={cascata(i)}>
                                        <td className="whitespace-nowrap px-3 py-2 tabular-nums">{d.hora ?? '—'}</td>
                                        <td className="px-3 py-2">
                                            <span className={cls('font-mono text-xs font-semibold', d.anulada && 'line-through')}>{d.numero}</span>
                                            {d.anulada && <span className="ml-2"><Etiqueta cor="perigo" icone="fa-ban">{t('Anulada')}</Etiqueta></span>}
                                            {d.tipo === 'nota' && !d.anulada && <span className="ml-2"><Etiqueta cor="aviso" icone="fa-rotate-left">{t('Devolução')}</Etiqueta></span>}
                                            {/* A série interna em cima, a da AGT abaixo. */}
                                            {d.numero_agt && <div className="mt-0.5 font-mono text-[11px] text-slate-400">{d.numero_agt}</div>}
                                        </td>
                                        <td className="px-3 py-2">{d.cliente ?? '—'}</td>
                                        <td className="px-3 py-2">{d.meio}</td>
                                        <td className="px-3 py-2 text-right tabular-nums">{d.artigos}</td>
                                        <td className={cls('whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums', d.total < 0 && 'text-red-700')}>{kz(d.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </PainelDoSeparador>
        </div>
    );
}

function TabelaDeProdutos({ linhas }: { linhas: ProdutoDoTurno[] }) {
    return (
        <div className={cls('max-h-[28rem] overflow-auto border border-slate-200', RAIO)}>
            <table className="w-full min-w-[720px] text-sm">
                <thead className="sticky top-0 z-10">
                    <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600">
                        <th className="px-3 py-2 font-semibold">{t('Artigo')}</th>
                        <th className="px-3 py-2 text-right font-semibold">{t('Qtd.')}</th>
                        <th className="px-3 py-2 text-right font-semibold">{t('Preço médio')}</th>
                        <th className="px-3 py-2 text-right font-semibold">{t('Vendido')}</th>
                        <th className="px-3 py-2 text-right font-semibold">{t('Devolvido')}</th>
                        <th className="px-3 py-2 text-right font-semibold">{t('Líquido')}</th>
                        <th className="w-36 px-3 py-2 font-semibold">{t('Peso')}</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {linhas.length === 0 && <tr><td colSpan={7} className="px-3 py-6 text-center text-slate-400">{t('Nenhum artigo com esse nome.')}</td></tr>}
                    {linhas.map((p, i) => (
                        <tr key={p.chave} className="entra hover:bg-emerald-50/50" style={cascata(i)}>
                            <td className="px-3 py-2">
                                <span className="font-semibold text-slate-900">{p.nome}</span>
                                <span className="block text-xs text-slate-500">
                                    {[p.codigo, t(':n documento(s)', { n: p.documentos })].filter(Boolean).join(' · ')}
                                </span>
                            </td>
                            <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                                <span className="font-semibold">{qtd(p.liquida)}</span>
                                {p.devolvida > 0 && <span className="block text-xs text-slate-500">{qtd(p.quantidade)} − {qtd(p.devolvida)}</span>}
                            </td>
                            <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-700">{kz(p.preco_medio)}</td>
                            <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums">{kz(p.total)}</td>
                            <td className={cls('whitespace-nowrap px-3 py-2 text-right tabular-nums', p.devolvido > 0 ? 'text-red-700' : 'text-slate-400')}>{p.devolvido > 0 ? `−${kz(p.devolvido)}` : '—'}</td>
                            <td className="whitespace-nowrap px-3 py-2 text-right font-bold tabular-nums text-slate-900">{kz(p.liquido)}</td>
                            <td className="px-3 py-2">
                                <div className="flex items-center gap-2">
                                    <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                        <div className="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 transition-all duration-700" style={{ width: `${Math.max(0, Math.min(100, p.peso))}%` }} />
                                    </div>
                                    <span className="w-12 text-right text-xs tabular-nums text-slate-600">{p.peso.toFixed(1)}%</span>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
