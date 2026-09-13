import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { portal } from '@/api/portalDoCliente';
import { etiquetaIntl, t } from '@/i18n';
import { Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, TRANSICAO, cls, data } from '@/ui/tokens';

import { Paginas } from '../plataforma/comum';
import { Cabecalho, EstadoDaFactura, Numero, kwanzas } from './comum';

/**
 * O EXTRATO FINANCEIRO DO CLIENTE — o que deve, o que está atrasado, o que já
 * pagou, e os últimos seis meses.
 *
 * Tudo pelo SALDO de cada factura, com a regra da empresa: a FR está paga por
 * definição, e o rascunho não aparece. O gráfico passou a dizer o valor de cada
 * barra ao passar o rato, e não só em milhares arredondados.
 */
export default function Extrato() {
    const [filtros, porFiltros] = useState<{ periodo: string; estado?: string; pagina: number }>({ periodo: 'all', pagina: 1 });

    const pedido = useQuery({ queryKey: ['portal', 'extrato', filtros], queryFn: () => portal.extrato(filtros), placeholderData: keepPreviousData });

    if (pedido.isPending) return <Carregando linhas={8} />;
    if (pedido.isError) return <p role="alert" className="rounded-xl bg-red-50 p-6 text-red-800">{t('Não foi possível abrir o extrato.')}</p>;

    const d = pedido.data;
    const n = d.numeros;
    const maximo = Math.max(1, ...d.meses.map((m) => m.recebido + m.em_aberto));
    // O mês por extenso curto e o ano à parte: juntos, o Intl devolve «04/26».
    const nomeDoMes = (ym: string) => {
        const d = new Date(`${ym}-01T12:00:00`);
        return `${d.toLocaleDateString(etiquetaIntl(), { month: 'short' }).replace('.', '')} ${ym.slice(2, 4)}`;
    };

    return (
        <div>
            <Cabecalho titulo={t('Extrato Financeiro')} subtitulo={t('Acompanhe suas movimentações e saldo')} icone="fa-chart-line" gradiente="from-amber-500 to-orange-600" />

            <div className="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                <Numero i={0} cor="vermelho" rotulo={t('Saldo Devedor')} valor={<span className="text-2xl">{kwanzas(n.saldo_devedor)}</span>} nota={t('Total a pagar')} icone="fa-scale-unbalanced" />
                <Numero i={1} cor="laranja" rotulo={t('Faturas Atrasadas')} valor={n.atrasadas} nota={kwanzas(n.valor_atrasado)} icone="fa-triangle-exclamation" />
                <Numero i={2} cor="azul" rotulo={t('Parcialmente Pagas')} valor={<span className="text-2xl">{kwanzas(n.parciais_total)}</span>}
                    nota={<span className="text-green-600">{t('Pago: :valor', { valor: kwanzas(n.parciais_pago) })}</span>} icone="fa-circle-half-stroke" />
                <Numero i={3} cor="verde" rotulo={t('Total Pago')} valor={<span className="text-2xl">{kwanzas(n.recebido)}</span>} nota={t('De :total faturados', { total: kwanzas(n.facturado) })} icone="fa-circle-check" />
            </div>

            <section className={cls(CARTAO, 'mb-8 p-6')}>
                <h2 className="mb-6 text-xl font-bold text-gray-900"><i className="fas fa-chart-column icon-float mr-2 text-amber-600" aria-hidden="true" />{t('Histórico dos Últimos 6 Meses')}</h2>
                <div className="flex h-56 items-end gap-3" role="img" aria-label={t('Recebido e em aberto por mês de emissão')}>
                    {d.meses.map((m, i) => (
                        <div key={m.mes} className="entra flex h-full flex-1 flex-col justify-end text-center" style={cascata(i)}>
                            <div className="flex flex-1 flex-col justify-end gap-0.5" title={`${nomeDoMes(m.mes)} — ${t('Recebido')}: ${kwanzas(m.recebido)} · ${t('Em aberto')}: ${kwanzas(m.em_aberto)}`}>
                                {m.em_aberto > 0 && <div className="rounded-t-md bg-red-400 transition-all duration-700" style={{ height: `${(m.em_aberto / maximo) * 100}%` }} />}
                                <div className={cls('bg-green-500 transition-all duration-700', m.em_aberto > 0 ? '' : 'rounded-t-md')} style={{ height: `${(m.recebido / maximo) * 100}%`, minHeight: m.recebido > 0 ? 2 : 0 }} />
                            </div>
                            <p className="mt-2 text-xs font-semibold capitalize text-gray-700">{nomeDoMes(m.mes)}</p>
                        </div>
                    ))}
                </div>
                <div className="mt-4 flex justify-center gap-6 text-xs text-gray-600">
                    <span><span className="mr-1 inline-block h-3 w-3 rounded bg-green-500 align-middle" />{t('Recebido')}</span>
                    <span><span className="mr-1 inline-block h-3 w-3 rounded bg-red-400 align-middle" />{t('Em aberto')}</span>
                </div>
            </section>

            <div className={cls(CARTAO, 'mb-6 grid gap-4 p-5 md:grid-cols-2')}>
                <label className="block">
                    <Rotulo>{t('Período')}</Rotulo>
                    <select className={entrada} value={filtros.periodo} onChange={(e) => porFiltros({ ...filtros, periodo: e.target.value, pagina: 1 })}>
                        <option value="all">{t('Todos os períodos')}</option>
                        <option value="month">{t('Este mês')}</option>
                        <option value="quarter">{t('Este trimestre')}</option>
                        <option value="year">{t('Este ano')}</option>
                    </select>
                </label>
                <label className="block">
                    <Rotulo>{t('Status')}</Rotulo>
                    <select className={entrada} value={filtros.estado ?? ''} onChange={(e) => porFiltros({ ...filtros, estado: e.target.value || undefined, pagina: 1 })}>
                        <option value="">{t('Todos os status')}</option>
                        <option value="sent">{t('Emitida')}</option>
                        <option value="pending">{t('Pendente')}</option>
                        <option value="partially_paid">{t('Parcialmente Paga')}</option>
                        <option value="paid">{t('Paga')}</option>
                        <option value="overdue">{t('Atrasada')}</option>
                        <option value="credited">{t('Creditada')}</option>
                        <option value="cancelled">{t('Cancelada')}</option>
                    </select>
                </label>
            </div>

            <section className={cls(CARTAO, 'overflow-hidden')}>
                <header className="border-b border-gray-200 px-6 py-4"><h2 className="text-lg font-bold text-gray-900"><i className="fas fa-list mr-2 text-amber-600" aria-hidden="true" />{t('Movimentações')}</h2></header>
                {d.facturas.length === 0 ? <SemNada icone="fa-receipt" frase={t('Nenhuma movimentação encontrada')} /> : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50 text-left text-xs font-bold uppercase tracking-wider text-gray-600">
                                <tr>
                                    <th className="px-6 py-3">{t('Número')}</th><th className="px-6 py-3">{t('Data Emissão')}</th><th className="px-6 py-3">{t('Vencimento')}</th>
                                    <th className="px-6 py-3 text-right">{t('Valor Total')}</th><th className="px-6 py-3 text-right">{t('Valor Pago')}</th><th className="px-6 py-3 text-right">{t('Saldo')}</th><th className="px-6 py-3">{t('Status')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {d.facturas.map((f, i) => (
                                    <tr key={f.id} className={cls('entra hover:bg-amber-50/40', TRANSICAO, f.atrasada && 'bg-red-50/40')} style={cascata(i)}>
                                        <td className="px-6 py-3 font-mono font-semibold text-gray-900">{f.numero}</td>
                                        <td className="px-6 py-3 text-gray-600">{data(f.data)}</td>
                                        <td className={cls('px-6 py-3', f.atrasada ? 'font-semibold text-red-600' : 'text-gray-600')}>{data(f.vencimento)}</td>
                                        <td className="px-6 py-3 text-right tabular-nums text-gray-900">{kwanzas(f.total)}</td>
                                        <td className="px-6 py-3 text-right tabular-nums text-green-700">{kwanzas(f.pago)}</td>
                                        <td className={cls('px-6 py-3 text-right font-semibold tabular-nums', f.saldo > 0 ? 'text-red-600' : 'text-gray-400')}>{kwanzas(f.saldo)}</td>
                                        <td className="px-6 py-3"><EstadoDaFactura estado={f.estado} rotulo={f.estado_rotulo} atrasada={f.atrasada} /></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <div className="px-6 pb-4"><Paginas pagina={d.paginacao.pagina} ultima={d.paginacao.ultima} aMudar={(p) => porFiltros({ ...filtros, pagina: p })} /></div>
            </section>
        </div>
    );
}
