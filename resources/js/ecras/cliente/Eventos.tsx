import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { portal } from '@/api/portalDoCliente';
import { t } from '@/i18n';
import { Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, cls, dataHora } from '@/ui/tokens';

import { Paginas } from '../plataforma/comum';
import { Cabecalho, Numero, kwanzas } from './comum';

const COR = { confirmado: 'primaria', em_andamento: 'bom', concluido: 'neutra', cancelado: 'perigo', orcamento: 'aviso', em_montagem: 'aviso' } as const;

/**
 * OS EVENTOS DO CLIENTE — o estado, a fase em que estão, o progresso da
 * lista de verificação e o valor.
 */
export default function Eventos() {
    const [filtros, porFiltros] = useState<{ procura?: string; estado?: string; pagina: number }>({ pagina: 1 });

    const pedido = useQuery({ queryKey: ['portal', 'eventos', filtros], queryFn: () => portal.eventos(filtros), placeholderData: keepPreviousData });

    return (
        <div>
            <Cabecalho titulo={t('Meus Eventos')} subtitulo={t('Acompanhe o status e detalhes dos seus eventos')} icone="fa-calendar-days" gradiente="from-indigo-600 to-blue-600" />

            {pedido.data && (
                <div className="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <Numero i={0} cor="indigo" rotulo={t('Total de Eventos')} valor={pedido.data.numeros.total} nota={t('Eventos cadastrados')} icone="fa-calendar-days" />
                    <Numero i={1} cor="azul" rotulo={t('Confirmados')} valor={pedido.data.numeros.confirmados} nota={t('Prontos para execução')} icone="fa-circle-check" />
                    <Numero i={2} cor="verde" rotulo={t('Em Andamento')} valor={pedido.data.numeros.em_andamento} nota={t('Acontecendo agora')} icone="fa-play" />
                    <Numero i={3} cor="cinza" rotulo={t('Concluídos')} valor={pedido.data.numeros.concluidos} nota={t('Finalizados')} icone="fa-flag-checkered" />
                </div>
            )}

            <div className={cls(CARTAO, 'mb-6 grid gap-4 p-5 md:grid-cols-2')}>
                <label className="block">
                    <Rotulo>{t('Pesquisar')}</Rotulo>
                    <input type="search" className={entrada} placeholder={t('Número ou nome do evento...')} value={filtros.procura ?? ''}
                        onChange={(e) => porFiltros({ ...filtros, procura: e.target.value || undefined, pagina: 1 })} />
                </label>
                <label className="block">
                    <Rotulo>{t('Status')}</Rotulo>
                    <select className={entrada} value={filtros.estado ?? ''} onChange={(e) => porFiltros({ ...filtros, estado: e.target.value || undefined, pagina: 1 })}>
                        <option value="">{t('Todos os status')}</option>
                        <option value="orcamento">{t('Orçamento')}</option>
                        <option value="confirmado">{t('Confirmado')}</option>
                        <option value="em_montagem">{t('Em Montagem')}</option>
                        <option value="em_andamento">{t('Em Andamento')}</option>
                        <option value="concluido">{t('Concluído')}</option>
                        <option value="cancelado">{t('Cancelado')}</option>
                    </select>
                </label>
            </div>

            {pedido.isPending ? <Carregando linhas={4} /> : pedido.isError ? (
                <p role="alert" className="rounded-xl bg-red-50 p-6 text-red-700">{t('Não foi possível abrir os eventos.')}</p>
            ) : pedido.data.eventos.length === 0 ? (
                <div className={CARTAO}><SemNada icone="fa-calendar-xmark" frase={t('Nenhum evento encontrado')} /></div>
            ) : (
                <div className="space-y-4">
                    {pedido.data.eventos.map((e, i) => (
                        <article key={e.id} className={cls(CARTAO, 'entra card-hover p-6')} style={cascata(i)}>
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="min-w-0 flex-1">
                                    <div className="mb-2 flex flex-wrap items-center gap-2">
                                        <span className="font-mono text-sm text-gray-500">{e.numero}</span>
                                        <Etiqueta cor={COR[e.estado as keyof typeof COR] ?? 'neutra'} ponto>{e.estado_rotulo}</Etiqueta>
                                        {e.fase_rotulo && (
                                            <span className="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                                <i className={cls(e.fase_icone ?? 'fas fa-circle', 'mr-1')} aria-hidden="true" />{e.fase_rotulo}
                                            </span>
                                        )}
                                    </div>
                                    <h3 className="mb-2 text-xl font-bold text-gray-900">{e.nome}</h3>
                                    <div className="flex flex-wrap gap-x-5 gap-y-1 text-sm text-gray-600">
                                        <span><i className="fas fa-calendar mr-1 text-indigo-500" aria-hidden="true" />{dataHora(e.inicio)}{e.fim && ` — ${dataHora(e.fim)}`}</span>
                                        {e.local && <span><i className="fas fa-location-dot mr-1 text-indigo-500" aria-hidden="true" />{e.local}</span>}
                                        {e.tipo && <span><i className="fas fa-tag mr-1 text-indigo-500" aria-hidden="true" />{e.tipo}</span>}
                                        {e.participantes ? <span><i className="fas fa-users mr-1 text-indigo-500" aria-hidden="true" />{t(':n participantes', { n: e.participantes })}</span> : null}
                                    </div>
                                    {e.progresso > 0 && (
                                        <div className="mt-4 max-w-md">
                                            <div className="mb-1 flex justify-between text-xs text-gray-600"><span>{t('Progresso da preparação')}</span><span className="font-semibold">{e.progresso}%</span></div>
                                            <div className="h-2 rounded-full bg-gray-200" role="progressbar" aria-valuenow={e.progresso} aria-valuemin={0} aria-valuemax={100}>
                                                <div className="h-2 rounded-full bg-blue-600 transition-all duration-700" style={{ width: `${e.progresso}%` }} />
                                            </div>
                                        </div>
                                    )}
                                </div>
                                {e.valor !== null && (
                                    <div className="text-right">
                                        <p className="text-sm text-gray-500">{t('Valor Total')}</p>
                                        <p className="text-2xl font-bold tabular-nums text-indigo-600">{kwanzas(e.valor)}</p>
                                    </div>
                                )}
                            </div>
                            {e.descricao && <p className="mt-4 border-t border-gray-100 pt-4 text-sm text-gray-600">{e.descricao}</p>}
                        </article>
                    ))}
                    <Paginas pagina={pedido.data.paginacao.pagina} ultima={pedido.data.paginacao.ultima} aMudar={(p) => porFiltros({ ...filtros, pagina: p })} />
                </div>
            )}
        </div>
    );
}
