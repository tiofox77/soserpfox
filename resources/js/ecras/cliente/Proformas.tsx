import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { portal } from '@/api/portalDoCliente';
import { t } from '@/i18n';
import { Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, TRANSICAO, cls, data } from '@/ui/tokens';

import { Paginas } from '../plataforma/comum';
import { Cabecalho, Numero, kwanzas } from './comum';

const ESTADOS = () => ({
    sent: { rotulo: t('Enviada'), cor: 'primaria' as const },
    accepted: { rotulo: t('Aceite'), cor: 'bom' as const },
    converted: { rotulo: t('Convertida'), cor: 'bom' as const },
    rejected: { rotulo: t('Rejeitada'), cor: 'perigo' as const },
    expired: { rotulo: t('Expirada'), cor: 'neutra' as const },
});

/** AS PROFORMAS DO CLIENTE — as cotações que a empresa lhe enviou. */
export default function Proformas() {
    const [filtros, porFiltros] = useState<{ procura?: string; estado?: string; pagina: number }>({ pagina: 1 });

    const pedido = useQuery({ queryKey: ['portal', 'proformas', filtros], queryFn: () => portal.proformas(filtros), placeholderData: keepPreviousData });
    const estados = ESTADOS();

    return (
        <div>
            <Cabecalho titulo={t('Minhas Proformas')} subtitulo={t('Visualize suas proformas e cotações')} icone="fa-file-lines" gradiente="from-purple-600 to-fuchsia-600" />

            {pedido.data && (
                <div className="mb-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                    <Numero i={0} cor="roxo" rotulo={t('Total')} valor={pedido.data.numeros.total} icone="fa-file-lines" />
                    <Numero i={1} cor="ambar" rotulo={t('Em Aberto')} valor={pedido.data.numeros.em_aberto} icone="fa-hourglass-half" />
                    <Numero i={2} cor="verde" rotulo={t('Convertidas')} valor={pedido.data.numeros.convertidas} icone="fa-circle-check" />
                </div>
            )}

            <div className={cls(CARTAO, 'mb-6 grid gap-4 p-5 md:grid-cols-2')}>
                <label className="block">
                    <Rotulo>{t('Pesquisar')}</Rotulo>
                    <input type="search" className={entrada} placeholder={t('Número da proforma...')} value={filtros.procura ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, procura: e.target.value || undefined, pagina: 1 })} />
                </label>
                <label className="block">
                    <Rotulo>{t('Status')}</Rotulo>
                    <select className={entrada} value={filtros.estado ?? ''} onChange={(e) => porFiltros({ ...filtros, estado: e.target.value || undefined, pagina: 1 })}>
                        <option value="">{t('Todos os status')}</option>
                        {Object.entries(estados).map(([valor, e]) => <option key={valor} value={valor}>{e.rotulo}</option>)}
                    </select>
                </label>
            </div>

            <section className={cls(CARTAO, 'overflow-hidden')}>
                {pedido.isPending ? <div className="p-6"><Carregando linhas={5} /></div> : pedido.isError ? (
                    <p role="alert" className="p-6 text-red-700">{t('Não foi possível abrir as proformas.')}</p>
                ) : pedido.data.proformas.length === 0 ? <SemNada icone="fa-file-lines" frase={t('Nenhuma proforma encontrada')} /> : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th className="px-6 py-3">{t('Número')}</th><th className="px-6 py-3">{t('Data')}</th><th className="px-6 py-3">{t('Válida até')}</th><th className="px-6 py-3 text-right">{t('Valor')}</th><th className="px-6 py-3">{t('Status')}</th></tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {pedido.data.proformas.map((p, i) => {
                                    const e = estados[p.estado as keyof typeof estados] ?? { rotulo: p.estado, cor: 'neutra' as const };

                                    return (
                                        <tr key={p.id} className={cls('entra hover:bg-purple-50/40', TRANSICAO)} style={cascata(i)}>
                                            <td className="px-6 py-3 font-mono font-semibold text-gray-900">{p.numero}</td>
                                            <td className="px-6 py-3 text-gray-600">{data(p.data)}</td>
                                            <td className={cls('px-6 py-3', p.expirada ? 'font-semibold text-red-600' : 'text-gray-600')}>{data(p.valida_ate)}</td>
                                            <td className="px-6 py-3 text-right font-semibold tabular-nums text-gray-900">{kwanzas(p.total)}</td>
                                            <td className="px-6 py-3"><Etiqueta cor={p.expirada && p.estado === 'sent' ? 'neutra' : e.cor} ponto>{p.expirada && p.estado === 'sent' ? t('Expirada') : e.rotulo}</Etiqueta></td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
                {pedido.data && <div className="px-6 pb-4"><Paginas pagina={pedido.data.paginacao.pagina} ultima={pedido.data.paginacao.ultima} aMudar={(p) => porFiltros({ ...filtros, pagina: p })} /></div>}
            </section>
        </div>
    );
}
