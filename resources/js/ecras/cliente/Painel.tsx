import { useQuery } from '@tanstack/react-query';

import { portal } from '@/api/portalDoCliente';
import { t, tn } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, TRANSICAO, cls, data, dataHora } from '@/ui/tokens';

import { Cabecalho, EstadoDaFactura, Numero, kwanzas } from './comum';

/**
 * O INÍCIO DO PORTAL — quantas facturas, quantas por pagar, as últimas cinco
 * e os próximos eventos.
 */
export default function Painel() {
    const pedido = useQuery({ queryKey: ['portal', 'painel'], queryFn: portal.painel });

    if (pedido.isPending) return <Carregando linhas={6} />;
    if (pedido.isError) return <p role="alert" className="rounded-xl bg-red-50 p-6 text-red-800">{t('Não foi possível abrir o portal.')}</p>;

    const d = pedido.data;
    const veEventos = d.seccoes.includes('eventos');

    return (
        <div>
            <Cabecalho titulo={t('Bem-vindo, :nome!', { nome: d.cliente.nome })} subtitulo={t('Gerencie suas faturas, eventos e documentos')} icone="fa-house" gradiente="from-blue-600 to-purple-600" />

            {/* A OFICINA — o carro primeiro, para quem é cliente da oficina. */}
            {d.oficina && (
                <a href="/client/oficina" className={cls('entra card-hover mb-6 flex flex-wrap items-center gap-4 bg-gradient-to-r from-orange-500 to-red-600 p-5 text-white shadow-lg', 'rounded-2xl', FOCO)}>
                    <span className="grid h-14 w-14 place-items-center rounded-2xl bg-white/20 text-2xl backdrop-blur-sm"><i className="fas fa-car icon-float" aria-hidden="true" /></span>
                    <span className="min-w-0 flex-1">
                        <span className="block text-lg font-bold">{t('A Minha Oficina')}</span>
                        <span className="block text-sm text-white/90">
                            {d.oficina.na_oficina > 0
                                ? tn(':n viatura sua na oficina|:n viaturas suas na oficina', d.oficina.na_oficina, { n: d.oficina.na_oficina })
                                : t('Nenhuma viatura sua na oficina agora')}
                            {d.oficina.prontas > 0 && ` · ${tn(':n pronta para levantar|:n prontas para levantar', d.oficina.prontas, { n: d.oficina.prontas })}`}
                        </span>
                        {/* O ESTADO DE CADA CARRO, numa etiqueta — sem abrir a oficina. */}
                        {d.oficina.estados.length > 0 && (
                            <span className="mt-2 flex flex-wrap gap-1.5">
                                {d.oficina.estados.slice(0, 6).map((e) => (
                                    <span key={e.id} className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-xs font-semibold backdrop-blur-sm transition-colors duration-200 hover:bg-white/30" title={e.viatura}>
                                        <i className={cls('fas', e.icone)} aria-hidden="true" />
                                        <span className="font-mono">{e.matricula}</span>
                                        <span className="text-white/90">· {e.rotulo}</span>
                                    </span>
                                ))}
                            </span>
                        )}
                    </span>
                    {d.oficina.por_pagar > 0 && <span className="rounded-xl bg-white/20 px-3 py-2 text-sm font-semibold">{t('Por pagar: :v', { v: kwanzas(d.oficina.por_pagar) })}</span>}
                    <i className="fas fa-arrow-right" aria-hidden="true" />
                </a>
            )}

            {d.ve_facturas && (
            <div className="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                <Numero i={0} cor="azul" rotulo={t('Total de Faturas')} valor={d.numeros.facturas} nota={t('No total')} icone="fa-file-invoice" />
                <Numero i={1} cor="laranja" rotulo={t('Pendentes')} valor={d.numeros.pendentes} nota={t('Aguardando pagamento')} icone="fa-hourglass-half" />
                <Numero i={2} cor="verde" rotulo={t('Pagas')} valor={d.numeros.pagas} nota={t('Faturas quitadas')} icone="fa-circle-check" />
                <Numero i={3} cor="roxo" rotulo={t('Total Faturado')} valor={<span className="text-2xl">{kwanzas(d.numeros.facturado)}</span>} nota={t('Valor acumulado')} icone="fa-coins" />
            </div>

            )}

            <div className="grid gap-6 lg:grid-cols-3">
                {d.ve_facturas && (
                <section className={cls(CARTAO, 'overflow-hidden', veEventos ? 'lg:col-span-2' : 'lg:col-span-3')}>
                    <header className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                        <h2 className="text-xl font-bold text-gray-900"><i className="fas fa-file-invoice icon-float mr-2 text-blue-600" aria-hidden="true" />{t('Últimas Faturas')}</h2>
                        <a href="/client/invoices" className="text-sm font-medium text-blue-600 hover:text-blue-800">{t('Ver todas')} <i className="fas fa-arrow-right ml-1" aria-hidden="true" /></a>
                    </header>
                    {d.ultimas_facturas.length === 0 ? <SemNada icone="fa-file-invoice" frase={t('Nenhuma fatura encontrada')} /> : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                                    <tr><th className="px-6 py-3">{t('Número')}</th><th className="px-6 py-3">{t('Data')}</th><th className="px-6 py-3 text-right">{t('Valor')}</th><th className="px-6 py-3">{t('Status')}</th></tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {d.ultimas_facturas.map((f, i) => (
                                        <tr key={f.id} className={cls('entra hover:bg-blue-50/40', TRANSICAO)} style={cascata(i)}>
                                            <td className="px-6 py-3 font-mono font-semibold text-gray-900">{f.numero}</td>
                                            <td className="px-6 py-3 text-gray-600">{data(f.data)}</td>
                                            <td className="px-6 py-3 text-right font-semibold tabular-nums text-gray-900">{kwanzas(f.total)}</td>
                                            <td className="px-6 py-3"><EstadoDaFactura estado={f.estado} rotulo={f.estado_rotulo} atrasada={f.atrasada} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
                )}

                {veEventos && (
                <section className={cls(CARTAO, 'overflow-hidden')}>
                    <header className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                        <h2 className="text-xl font-bold text-gray-900"><i className="fas fa-calendar-days icon-float mr-2 text-indigo-600" aria-hidden="true" />{t('Próximos Eventos')}</h2>
                        <a href="/client/events" className="text-sm font-medium text-blue-600 hover:text-blue-800">{t('Ver todos')} <i className="fas fa-arrow-right ml-1" aria-hidden="true" /></a>
                    </header>
                    {d.proximos_eventos.length === 0 ? <SemNada icone="fa-calendar-xmark" frase={t('Nenhum evento agendado')} /> : (
                        <ul className="divide-y divide-gray-100">
                            {d.proximos_eventos.map((e, i) => (
                                <li key={e.id} className="entra card-hover p-5" style={cascata(i)}>
                                    <h3 className="mb-2 text-lg font-bold text-gray-900">{e.nome}</h3>
                                    <p className="text-sm text-gray-600"><i className="fas fa-clock mr-2 text-indigo-500" aria-hidden="true" />{dataHora(e.inicio)}</p>
                                    {e.local && <p className="text-sm text-gray-600"><i className="fas fa-location-dot mr-2 text-indigo-500" aria-hidden="true" />{e.local}</p>}
                                    {e.valor !== null && <p className="mt-2 text-lg font-bold text-indigo-600">{kwanzas(e.valor)}</p>}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
                )}
            </div>
        </div>
    );
}
