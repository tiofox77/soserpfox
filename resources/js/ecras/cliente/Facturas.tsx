import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { portal } from '@/api/portalDoCliente';
import { t } from '@/i18n';
import { Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, TRANSICAO, cls, data } from '@/ui/tokens';

import { Paginas } from '../plataforma/comum';
import { Cabecalho, EstadoDaFactura, kwanzas } from './comum';

/** AS FACTURAS DO CLIENTE — procurar pelo número e filtrar pelo estado. */
export default function Facturas() {
    const [filtros, porFiltros] = useState<{ procura?: string; estado?: string; pagina: number }>({ pagina: 1 });

    const pedido = useQuery({ queryKey: ['portal', 'facturas', filtros], queryFn: () => portal.facturas(filtros), placeholderData: keepPreviousData });

    return (
        <div>
            <Cabecalho titulo={t('Minhas Faturas')} subtitulo={t('Visualize e gerencie suas faturas')} icone="fa-file-invoice" gradiente="from-green-600 to-emerald-600" />

            <div className={cls(CARTAO, 'mb-6 grid gap-4 p-5 md:grid-cols-2')}>
                <label className="block">
                    <Rotulo>{t('Pesquisar')}</Rotulo>
                    <span className="relative block">
                        <i className="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true" />
                        <input type="search" className={cls(entrada, 'pl-9')} placeholder={t('Número da fatura...')} value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros({ ...filtros, procura: e.target.value || undefined, pagina: 1 })} />
                    </span>
                </label>
                <label className="block">
                    <Rotulo>{t('Status')}</Rotulo>
                    <select className={entrada} value={filtros.estado ?? ''} onChange={(e) => porFiltros({ ...filtros, estado: e.target.value || undefined, pagina: 1 })}>
                        <option value="">{t('Todos os status')}</option>
                        <option value="sent">{t('Emitida')}</option>
                        <option value="pending">{t('Pendente')}</option>
                        <option value="partially_paid">{t('Parcialmente Pago')}</option>
                        <option value="paid">{t('Pago')}</option>
                        <option value="overdue">{t('Atrasado')}</option>
                        <option value="credited">{t('Creditada')}</option>
                        <option value="cancelled">{t('Cancelada')}</option>
                    </select>
                </label>
            </div>

            <section className={cls(CARTAO, 'overflow-hidden')}>
                {pedido.isPending ? <div className="p-6"><Carregando linhas={5} /></div> : pedido.isError ? (
                    <p role="alert" className="p-6 text-red-700">{t('Não foi possível abrir as faturas.')}</p>
                ) : pedido.data.facturas.length === 0 ? <SemNada icone="fa-file-invoice" frase={t('Nenhuma fatura encontrada')} /> : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="px-6 py-3">{t('Número')}</th><th className="px-6 py-3">{t('Data')}</th><th className="px-6 py-3">{t('Vencimento')}</th>
                                    <th className="px-6 py-3 text-right">{t('Valor')}</th><th className="px-6 py-3 text-right">{t('Saldo')}</th><th className="px-6 py-3">{t('Status')}</th><th className="px-6 py-3"><span className="sr-only">{t('PDF')}</span></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {pedido.data.facturas.map((f, i) => (
                                    <tr key={f.id} className={cls('entra hover:bg-green-50/40', TRANSICAO)} style={cascata(i)}>
                                        <td className="px-6 py-3 font-mono font-semibold text-gray-900">{f.numero}</td>
                                        <td className="px-6 py-3 text-gray-600">{data(f.data)}</td>
                                        <td className={cls('px-6 py-3', f.atrasada ? 'font-semibold text-red-600' : 'text-gray-600')}>{data(f.vencimento)}</td>
                                        <td className="px-6 py-3 text-right font-semibold tabular-nums text-gray-900">{kwanzas(f.total)}</td>
                                        <td className={cls('px-6 py-3 text-right tabular-nums', f.saldo > 0 ? 'font-semibold text-red-600' : 'text-gray-400')}>{kwanzas(f.saldo)}</td>
                                        <td className="px-6 py-3"><EstadoDaFactura estado={f.estado} rotulo={f.estado_rotulo} atrasada={f.atrasada} /></td>
                                        <td className="px-6 py-3 text-right">
                                            {/* O PAPEL DA FACTURA — o mesmo PDF da empresa, para pagar ou arquivar. */}
                                            <a href={`/client/facturas/${f.id}/pdf`} target="_blank" rel="noreferrer" title={t('PDF')} aria-label={t('PDF da factura :n', { n: f.numero })}
                                                className={cls('inline-grid h-9 w-9 place-items-center rounded-lg bg-red-50 text-red-600 hover:scale-110 hover:bg-red-100', TRANSICAO)}>
                                                <i className="fas fa-file-pdf" aria-hidden="true" />
                                            </a>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {pedido.data && <div className="px-6 pb-4"><Paginas pagina={pedido.data.paginacao.pagina} ultima={pedido.data.paginacao.ultima} aMudar={(p) => porFiltros({ ...filtros, pagina: p })} /></div>}
            </section>
        </div>
    );
}
