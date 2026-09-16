import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { revenda, type Contagens } from '@/api/revenda';
import { t } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { Paginacao } from '@/ui/Paginacao';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, data } from '@/ui/tokens';

import { Cabecalho, EstadoDaEmpresaEtiquetas, GRADIENTE_DO_PORTAL, kwanzas } from './comum';

/**
 * AS MINHAS EMPRESAS (RV-08) — a conta de cada uma na plataforma, sem os dados
 * de negócio. Os cartões de cima são filtros.
 */
const FILTROS: Array<{ chave: string; rotulo: string; icone: string; tom: string; conta: (c: Contagens) => number }> = [
    { chave: '', rotulo: 'Todas', icone: 'fa-building', tom: 'from-violet-500 to-purple-600', conta: (c) => c.todas },
    { chave: 'activa', rotulo: 'Activas', icone: 'fa-circle-check', tom: 'from-emerald-500 to-teal-600', conta: (c) => c.activa },
    { chave: 'teste', rotulo: 'Em teste', icone: 'fa-hourglass-half', tom: 'from-sky-500 to-indigo-600', conta: (c) => c.teste },
    { chave: 'por_pagar', rotulo: 'Por pagar', icone: 'fa-hand-holding-dollar', tom: 'from-amber-500 to-orange-600', conta: (c) => c.por_pagar },
    { chave: 'a_vencer', rotulo: 'A vencer', icone: 'fa-clock', tom: 'from-orange-500 to-red-500', conta: (c) => c.a_vencer },
    { chave: 'vencida', rotulo: 'Vencidas', icone: 'fa-circle-xmark', tom: 'from-red-500 to-rose-600', conta: (c) => c.vencida },
];

export default function Empresas() {
    const [procura, porProcura] = useState('');
    const [procurar, porProcurar] = useState('');
    const [estado, porEstado] = useState(() => new URLSearchParams(window.location.search).get('estado') ?? '');
    const [pagina, porPagina] = useState(1);

    // A procura espera que se pare de escrever.
    useEffect(() => {
        const espera = window.setTimeout(() => { porProcurar(procura); porPagina(1); }, 350);
        return () => window.clearTimeout(espera);
    }, [procura]);

    const q = useQuery({
        queryKey: ['revenda', 'empresas', procurar, estado, pagina],
        queryFn: () => revenda.empresas({ procura: procurar, estado, pagina }),
        placeholderData: keepPreviousData,
    });

    return (
        <div className="space-y-6">
            <Cabecalho titulo={t('As minhas empresas')} subtitulo={t('O plano, o estado e o que falta pagar de cada uma')} icone="fa-building" gradiente={GRADIENTE_DO_PORTAL}>
                <a href="/revendedor/empresas/nova" className={cls('group inline-flex items-center gap-2 bg-white px-4 py-2.5 text-sm font-bold text-violet-700 shadow hover:-translate-y-0.5 hover:shadow-lg', RAIO, TRANSICAO, FOCO)}>
                    <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />{t('Nova empresa')}
                </a>
            </Cabecalho>

            {q.data && (
                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    {FILTROS.map((f, i) => {
                        const activo = estado === f.chave;
                        return (
                            <button key={f.chave || 'todas'} type="button" style={cascata(i)} aria-pressed={activo}
                                onClick={() => { porEstado(f.chave); porPagina(1); }}
                                className={cls('entra group flex items-center gap-3 border p-3 text-left', RAIO, TRANSICAO, FOCO,
                                    activo ? 'border-violet-400 bg-violet-50 shadow-md ring-2 ring-violet-200' : 'border-gray-100 bg-white hover:-translate-y-0.5 hover:shadow-md')}>
                                <span className={cls('grid h-10 w-10 shrink-0 place-items-center bg-gradient-to-br text-white shadow transition-transform duration-300 group-hover:scale-110', RAIO, f.tom)}>
                                    <i className={cls('fas', f.icone)} aria-hidden="true" />
                                </span>
                                <span className="min-w-0">
                                    <span className="block text-xl font-black tabular-nums text-gray-900">{f.conta(q.data.contagens)}</span>
                                    <span className="block truncate text-xs font-semibold text-gray-500">{t(f.rotulo)}</span>
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}

            <div className={cls(CARTAO, 'p-4')}>
                <label htmlFor="rv-procura" className="sr-only">{t('Procurar')}</label>
                <div className="relative">
                    <i className="fas fa-magnifying-glass pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true" />
                    <input id="rv-procura" type="search" value={procura} onChange={(e) => porProcura(e.target.value)} placeholder={t('Nome, NIF ou email da empresa')}
                        className={cls('w-full border border-gray-300 bg-white py-2.5 pl-10 pr-3 text-sm outline-none focus:border-violet-500 focus:ring-4 focus:ring-violet-100', RAIO)} />
                </div>
            </div>

            {q.isPending ? <Carregando linhas={6} /> : q.isError ? (
                <p role="alert" className="rounded-xl bg-red-50 p-6 text-red-800">{t('Não foi possível carregar as empresas.')}</p>
            ) : q.data.empresas.length === 0 ? (
                <div className={CARTAO}>
                    <SemNada icone="fa-building" titulo={procurar || estado ? t('Nenhuma empresa encontrada') : t('Ainda sem empresas')}
                        frase={procurar || estado ? t('Experimente outro filtro ou outra procura.') : t('Partilhe o seu link, dê o seu código aos clientes ou crie a empresa por eles.')} />
                </div>
            ) : (
                <div className={cls(CARTAO, 'overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3"><i className="fas fa-building mr-1.5 text-slate-400" aria-hidden="true" />{t('Empresa')}</th>
                                    <th className="px-4 py-3"><i className="fas fa-layer-group mr-1.5 text-slate-400" aria-hidden="true" />{t('Plano')}</th>
                                    <th className="px-4 py-3"><i className="fas fa-signal mr-1.5 text-slate-400" aria-hidden="true" />{t('Estado')}</th>
                                    <th className="px-4 py-3"><i className="fas fa-calendar-day mr-1.5 text-slate-400" aria-hidden="true" />{t('Validade')}</th>
                                    <th className="px-4 py-3"><i className="fas fa-clock-rotate-left mr-1.5 text-slate-400" aria-hidden="true" />{t('Último acesso')}</th>
                                    <th className="px-4 py-3 text-right"><i className="fas fa-users mr-1.5 text-slate-400" aria-hidden="true" />{t('Pessoas')}</th>
                                    <th className="px-4 py-3" aria-label={t('Acções')} />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {q.data.empresas.map((e, i) => (
                                    <tr key={e.id} style={cascata(i)} className="entra group transition-colors hover:bg-violet-50/40">
                                        <td className="px-4 py-3">
                                            <a href={`/revendedor/empresas/${e.id}`} className={cls('flex items-center gap-3', FOCO, RAIO)}>
                                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-violet-500 to-purple-600 text-xs font-bold text-white shadow-sm transition-transform duration-200 group-hover:scale-110">{e.nome.slice(0, 2).toUpperCase()}</span>
                                                <span className="min-w-0">
                                                    <span className="block font-semibold text-gray-900 group-hover:text-violet-700">{e.nome}</span>
                                                    <span className="block text-xs text-gray-500">{[e.nif, e.via_rotulo].filter(Boolean).join(' · ')}</span>
                                                </span>
                                            </a>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="block font-medium text-gray-800">{e.plano ?? '—'}</span>
                                            {e.valor !== null && <span className="block text-xs text-gray-500">{kwanzas(e.valor)} · {e.ciclo}</span>}
                                        </td>
                                        <td className="px-4 py-3"><EstadoDaEmpresaEtiquetas e={e.estado} /></td>
                                        <td className="px-4 py-3 text-gray-700">{e.estado.ate ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-700" title={e.ultima_entrada ? data(e.ultima_entrada) : undefined}>{e.ultima_entrada_ha ?? t('Nunca')}</td>
                                        <td className="px-4 py-3 text-right tabular-nums text-gray-700">{e.utilizadores}</td>
                                        <td className="px-4 py-3 text-right">
                                            <a href={`/revendedor/empresas/${e.id}`} className={cls('inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-100', RAIO, TRANSICAO, FOCO)}>
                                                <i className="fas fa-eye" aria-hidden="true" />{t('Abrir')}
                                            </a>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {q.data && q.data.paginacao.total > 0 && (
                <Paginacao pagina={q.data.paginacao.pagina} ultima={q.data.paginacao.ultima} aMudar={porPagina}
                    total={q.data.paginacao.total} de={q.data.paginacao.de} ate={q.data.paginacao.ate} aCarregar={q.isFetching} emCartao />
            )}
        </div>
    );
}
