import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { revenda } from '@/api/revenda';
import { t } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { Paginacao } from '@/ui/Paginacao';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, data } from '@/ui/tokens';

import { Cabecalho, EstadoDaComissao, GRADIENTE_DO_PORTAL, Numero, dataOuTraco, kwanzas } from './comum';

/**
 * AS COMISSÕES DO REVENDEDOR (RV-11 e RV-12) — o que ganhou em cada pagamento
 * e os pagamentos que já recebeu.
 */
const ESTADOS = [
    { valor: '', rotulo: 'Todas' },
    { valor: 'por_pagar', rotulo: 'Por pagar' },
    { valor: 'paga', rotulo: 'Pagas' },
    { valor: 'anulada', rotulo: 'Anuladas' },
];

export default function Comissoes() {
    const [estado, porEstado] = useState('');
    const [pagina, porPagina] = useState(1);
    const q = useQuery({
        queryKey: ['revenda', 'comissoes', estado, pagina],
        queryFn: () => revenda.comissoes({ estado, pagina }),
        placeholderData: keepPreviousData,
    });

    if (q.isPending) return <Carregando linhas={8} />;
    if (q.isError) return <p role="alert" className="rounded-xl bg-red-50 p-6 text-red-800">{t('Não foi possível carregar as comissões.')}</p>;

    const d = q.data;

    return (
        <div className="space-y-6">
            <Cabecalho titulo={t('Comissões')} subtitulo={t('A sua regra: :regra', { regra: d.regra })} icone="fa-sack-dollar" gradiente={GRADIENTE_DO_PORTAL} />

            <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <Numero i={0} cor="ambar" rotulo={t('Por receber')} valor={<span className="text-2xl">{kwanzas(d.totais.por_pagar)}</span>} nota={t(':n comissões', { n: d.totais.por_pagar_n })} icone="fa-hourglass-half" />
                <Numero i={1} cor="verde" rotulo={t('Recebido')} valor={<span className="text-2xl">{kwanzas(d.totais.pago)}</span>} nota={t('Em :n pagamentos', { n: d.pagamentos.length })} icone="fa-sack-dollar" />
                <Numero i={2} cor="roxo" rotulo={t('Este mês')} valor={<span className="text-2xl">{kwanzas(d.totais.do_mes)}</span>} nota={t('Comissões geradas')} icone="fa-calendar-check" />
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <section className={cls(CARTAO, 'entra overflow-hidden xl:col-span-2')}>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4">
                        <h2 className="flex items-center gap-2 text-lg font-bold text-gray-900"><i className="fas fa-coins text-emerald-500" aria-hidden="true" />{t('Comissões')}</h2>
                        <div role="group" aria-label={t('Filtrar por estado')} className="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1">
                            {ESTADOS.map((e) => (
                                <button key={e.valor || 'todas'} type="button" aria-pressed={estado === e.valor} onClick={() => { porEstado(e.valor); porPagina(1); }}
                                    className={cls('px-3 py-1.5 text-xs font-semibold', RAIO, TRANSICAO, FOCO, estado === e.valor ? 'bg-white text-violet-700 shadow' : 'text-slate-600 hover:text-slate-900')}>
                                    {t(e.rotulo)}
                                </button>
                            ))}
                        </div>
                    </div>
                    {d.comissoes.length === 0 ? (
                        <SemNada icone="fa-coins" titulo={t('Sem comissões')} frase={t('Cada pagamento confirmado de uma empresa sua dá-lhe uma comissão.')} />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">{t('Data')}</th>
                                        <th className="px-4 py-3">{t('Empresa')}</th>
                                        <th className="px-4 py-3">{t('Origem')}</th>
                                        <th className="px-4 py-3 text-right">{t('Base')}</th>
                                        <th className="px-4 py-3 text-right">{t('Comissão')}</th>
                                        <th className="px-4 py-3">{t('Estado')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {d.comissoes.map((c, i) => (
                                        <tr key={c.id} style={cascata(i)} className="entra hover:bg-violet-50/40">
                                            <td className="px-4 py-3 text-gray-700">{data(c.criada_em)}</td>
                                            <td className="px-4 py-3"><a href={`/revendedor/empresas/${c.empresa_id}`} className="font-semibold text-gray-900 hover:text-violet-700">{c.empresa}</a><span className="block text-xs text-gray-500">{c.plano}</span></td>
                                            <td className="px-4 py-3 text-gray-700">{c.origem_rotulo}<span className="block text-xs text-gray-500">{c.regra}</span></td>
                                            <td className="px-4 py-3 text-right tabular-nums text-gray-700">{kwanzas(c.base)}</td>
                                            <td className="px-4 py-3 text-right font-bold tabular-nums text-gray-900">{kwanzas(c.valor)}</td>
                                            <td className="px-4 py-3">
                                                <EstadoDaComissao c={c} />
                                                {c.pagamento && <span className="mt-1 block text-xs text-gray-500">{t('Paga a :data', { data: dataOuTraco(c.pagamento.data) })}</span>}
                                                {c.motivo && <span className="mt-1 block text-xs text-gray-500">{c.motivo}</span>}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {d.paginacao.total > 0 && (
                        <div className="border-t border-slate-100 p-3">
                            <Paginacao pagina={d.paginacao.pagina} ultima={d.paginacao.ultima} aMudar={porPagina} total={d.paginacao.total} de={d.paginacao.de} ate={d.paginacao.ate} aCarregar={q.isFetching} />
                        </div>
                    )}
                </section>

                <section className={cls(CARTAO, 'entra p-6')}>
                    <h2 className="mb-4 flex items-center gap-2 text-lg font-bold text-gray-900"><i className="fas fa-money-bill-transfer text-violet-500" aria-hidden="true" />{t('Pagamentos recebidos')}</h2>
                    {d.pagamentos.length === 0 ? (
                        <SemNada icone="fa-money-bill-transfer" frase={t('Ainda não recebeu nenhum pagamento de comissões.')} />
                    ) : (
                        <ol className="relative space-y-4 border-l-2 border-emerald-200 pl-5">
                            {d.pagamentos.map((p, i) => (
                                <li key={p.id} style={cascata(i)} className="entra relative">
                                    <span className="absolute -left-[29px] top-1 grid h-5 w-5 place-items-center rounded-full bg-emerald-500 text-[10px] text-white ring-4 ring-white"><i className="fas fa-check" aria-hidden="true" /></span>
                                    <p className="font-bold tabular-nums text-gray-900">{kwanzas(p.valor)}</p>
                                    <p className="text-xs text-gray-500">{[dataOuTraco(p.data), p.forma, p.referencia].filter(Boolean).join(' · ')}</p>
                                    <p className="text-xs text-gray-500">{t(':n comissões', { n: p.comissoes })}</p>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            </div>
        </div>
    );
}
