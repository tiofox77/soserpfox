import { useQuery } from '@tanstack/react-query';
import type { CSSProperties, ReactNode } from 'react';

import { plataforma, type InicioDaPlataforma } from '@/api/plataforma';
import { etiquetaIntl, t } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { cascata } from '@/ui/SemNada';
import { cls, haQuanto } from '@/ui/tokens';

import { ErroDoEcra } from './comum';

const numero = (n: number, casas = 0) => n.toLocaleString(etiquetaIntl(), { maximumFractionDigits: casas });

const ATALHOS = [
    { url: '/superadmin/dashboard', icone: 'fa-chart-line', rotulo: 'Dashboard', cor: 'text-blue-600 hover:bg-blue-50 hover:border-blue-200' },
    { url: '/superadmin/analytics', icone: 'fa-fire', rotulo: 'Leads', cor: 'text-orange-600 hover:bg-orange-50 hover:border-orange-200' },
    { url: '/superadmin/plans', icone: 'fa-tags', rotulo: 'Planos', cor: 'text-pink-600 hover:bg-pink-50 hover:border-pink-200' },
    { url: '/superadmin/modules', icone: 'fa-puzzle-piece', rotulo: 'Módulos', cor: 'text-purple-600 hover:bg-purple-50 hover:border-purple-200' },
    { url: '/superadmin/system-commands', icone: 'fa-terminal', rotulo: 'Comandos', cor: 'text-green-600 hover:bg-green-50 hover:border-green-200' },
    { url: '/superadmin/system-settings', icone: 'fa-cog', rotulo: 'Definições', cor: 'text-gray-600 hover:bg-gray-100 hover:border-gray-200' },
];

const ICONE_DA_ORIGEM: Record<string, string> = {
    google: 'fab fa-google text-blue-500',
    facebook: 'fab fa-facebook text-blue-700',
    whatsapp: 'fab fa-whatsapp text-green-500',
    direct: 'fas fa-arrow-right text-gray-500',
    other: 'fas fa-globe text-gray-500',
};

/**
 * AS BOAS-VINDAS DO SUPER ADMIN (`/home`).
 *
 * Os números da plataforma, o crescimento das últimas duas semanas, quem anda
 * pela página inicial e o estado do sistema — o mesmo que o Blade mostrava,
 * agora com os dados de /api/v1/plataforma/react/inicio.
 */
export default function Inicio() {
    const pedido = useQuery({ queryKey: ['plataforma', 'inicio'], queryFn: plataforma.inicio });

    if (pedido.isPending) return <Carregando linhas={8} />;
    if (pedido.isError) return <ErroDoEcra titulo={t('Não foi possível abrir a página inicial.')} erro={pedido.error} />;

    const d = pedido.data;
    const agora = new Date(d.agora).toLocaleString(etiquetaIntl(), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    return (
        <div className="space-y-6">
            <div className="animate-fade-in relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 p-6 text-white shadow-2xl md:p-8">
                <div className="absolute -right-12 -top-12 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                <div className="absolute -bottom-12 -left-12 h-64 w-64 rounded-full bg-yellow-400/10 blur-3xl" />
                <div className="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <span className="inline-flex items-center gap-1 rounded-full bg-white/20 px-3 py-1 text-xs font-bold backdrop-blur">
                            <span className="h-2 w-2 animate-pulse rounded-full bg-green-400" /> {t('Online')}
                        </span>
                        <h1 className="mt-2 text-3xl font-extrabold md:text-4xl">{t('Olá, :nome!', { nome: d.utilizador.nome })} 👋</h1>
                        <p className="mt-1 text-white/80">{agora.charAt(0).toLocaleUpperCase() + agora.slice(1)}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a href="/superadmin/tenants" className="flex items-center gap-2 rounded-xl bg-white/20 px-4 py-2 text-sm font-bold backdrop-blur transition hover:bg-white/30">
                            <i className="fas fa-building" aria-hidden="true" />{t('Empresas')}
                        </a>
                        <a href="/superadmin/analytics" className="flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-bold text-indigo-600 shadow-lg transition hover:-translate-y-0.5 hover:bg-yellow-300">
                            <i className="fas fa-fire text-orange-500" aria-hidden="true" />{t('Ver Leads')}
                        </a>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Kpi i={0} url="/superadmin/tenants" icone="fa-building" tom="bg-blue-100 text-blue-600" rotulo={t('Empresas')} valor={numero(d.empresas.total)}
                    selo={d.empresas.crescimento !== 0 ? <span className={cls('rounded-full px-2 py-0.5 text-xs font-bold', d.empresas.crescimento > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')}>{d.empresas.crescimento > 0 ? '↑' : '↓'} {Math.abs(d.empresas.crescimento)}%</span> : null}
                    nota={t(':activas activas · :teste em teste', { activas: d.empresas.activas, teste: d.empresas.em_teste })} />
                <Kpi i={1} url="/superadmin/tenants" icone="fa-users" tom="bg-purple-100 text-purple-600" rotulo={t('Utilizadores')} valor={numero(d.utilizadores.total)}
                    selo={d.utilizadores.novos_hoje > 0 ? <span className="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-bold text-purple-700">{t('+:n hoje', { n: d.utilizadores.novos_hoje })}</span> : null}
                    nota={t(':n activos hoje', { n: d.utilizadores.activos_hoje })} />
                <Kpi i={2} url="/superadmin/billing" icone="fa-coins" tom="bg-emerald-100 text-emerald-600" rotulo={t('Receita Mensal')} valor={<>{numero(d.receita.mrr)} <span className="text-sm">Kz</span></>}
                    selo={<span className="text-xs font-bold text-emerald-700">MRR</span>} nota={t('Pago no mês: :valor Kz', { valor: numero(d.receita.paga_no_mes) })} />
                <a href="/superadmin/analytics" style={cascata(3)} className="entra rounded-2xl bg-gradient-to-br from-orange-500 to-red-600 p-4 text-white shadow transition hover:-translate-y-1 hover:shadow-2xl">
                    <div className="mb-2 flex items-center justify-between">
                        <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20"><i className="fas fa-fire icon-float" aria-hidden="true" /></span>
                        <span className="rounded-full bg-white/20 px-2 py-0.5 text-xs font-bold">30d</span>
                    </div>
                    <p className="text-xs font-bold uppercase text-white/80">{t('Visitantes Landing')}</p>
                    <p className="text-3xl font-extrabold tabular-nums">{numero(d.visitas.visitantes)}</p>
                    <p className="mt-1 text-xs text-white/80">{t(':vistas páginas vistas · :cliques cliques no registo', { vistas: d.visitas.paginas_vistas, cliques: d.visitas.cliques_registo })}</p>
                </a>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Cartao className="lg:col-span-2">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 className="font-bold text-gray-900">📈 {t('Crescimento — últimos 14 dias')}</h3>
                            <p className="text-xs text-gray-500">{t('Novas empresas e utilizadores por dia')}</p>
                        </div>
                        <div className="flex gap-3 text-xs">
                            <span className="flex items-center gap-1"><span className="h-3 w-3 rounded bg-blue-500" />{t('Empresas')}</span>
                            <span className="flex items-center gap-1"><span className="h-3 w-3 rounded bg-purple-500" />{t('Utilizadores')}</span>
                        </div>
                    </div>
                    <Crescimento serie={d.serie} />
                    <div className="mt-4 grid grid-cols-3 gap-3 border-t border-gray-100 pt-4 text-center">
                        {[[t('Hoje'), d.empresas.hoje], [t('7 dias'), d.empresas.semana], [t('30 dias'), d.empresas.mes]].map(([r, v]) => (
                            <div key={String(r)}><p className="text-xs text-gray-500">{r}</p><p className="text-lg font-bold tabular-nums text-blue-600">{v}</p></div>
                        ))}
                    </div>
                </Cartao>

                <Cartao>
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="font-bold text-gray-900">🔥 {t('Leads quentes')}</h3>
                        <a href="/superadmin/analytics" className="text-xs font-bold text-blue-600 hover:underline">{t('Ver todos')} →</a>
                    </div>
                    {d.visitas.leads.length === 0 ? (
                        <p className="py-8 text-center text-sm italic text-gray-400">{t('Sem leads ainda. Aguarda visitantes na landing.')}</p>
                    ) : (
                        <ul className="space-y-2">
                            {d.visitas.leads.map((l, i) => {
                                const quente = l.pontos >= 50 ? ['🔥🔥', 'bg-red-100 text-red-700'] : l.pontos >= 20 ? ['🔥', 'bg-orange-100 text-orange-700'] : ['⭐', 'bg-amber-100 text-amber-700'];
                                return (
                                    <li key={l.visitante + i} style={cascata(i)} className="entra flex items-center gap-2 rounded-lg p-2 transition hover:bg-gray-50">
                                        <span className="text-lg" aria-hidden="true">{quente[0]}</span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-mono text-xs text-gray-600">{l.visitante}…</p>
                                            <p className="text-[10px] text-gray-400">{l.aparelho ?? '—'} · {l.pais ?? 'AO'}{l.origem ? ` · ${l.origem}` : ''}</p>
                                        </div>
                                        <span className={cls('inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold tabular-nums', quente[1])}>{l.pontos}</span>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </Cartao>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <Cartao>
                    <h3 className="mb-3 font-bold text-gray-900">🏆 {t('Páginas mais visitadas (30d)')}</h3>
                    <Barras itens={d.visitas.paginas.map((p) => ({ rotulo: <span className="font-mono">{p.caminho}</span>, valor: p.vistas }))} cor="from-blue-500 to-blue-600" texto="text-blue-600" />
                </Cartao>
                <Cartao>
                    <h3 className="mb-3 font-bold text-gray-900">🌐 {t('Origens de tráfego (30d)')}</h3>
                    <Barras itens={d.visitas.origens.map((o) => ({ rotulo: <span className="capitalize"><i className={cls(ICONE_DA_ORIGEM[o.origem] ?? 'fas fa-link text-gray-500', 'mr-1')} aria-hidden="true" />{o.origem}</span>, valor: o.visitantes }))} cor="from-emerald-500 to-teal-600" texto="text-emerald-600" />
                </Cartao>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Cartao className="lg:col-span-2">
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="font-bold text-gray-900">🏢 {t('Empresas recentes')}</h3>
                        <a href="/superadmin/tenants" className="text-xs font-bold text-blue-600 hover:underline">{t('Ver todos')} →</a>
                    </div>
                    {d.empresas_recentes.length === 0 ? (
                        <p className="py-8 text-center text-sm italic text-gray-400">{t('Nenhuma empresa ainda.')}</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 text-xs font-bold uppercase text-gray-500">
                                        <th className="py-2 text-left">{t('Empresa')}</th><th className="py-2 text-left">Slug</th><th className="py-2 text-center">{t('Estado')}</th><th className="py-2 text-right">{t('Criada')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {d.empresas_recentes.map((e, i) => (
                                        <tr key={e.id} style={cascata(i)} className="entra border-b border-gray-50 transition hover:bg-blue-50/30">
                                            <td className="py-2 font-semibold text-gray-800">{e.nome}</td>
                                            <td className="py-2 font-mono text-xs text-gray-500">{e.slug}</td>
                                            <td className="py-2 text-center"><span className={cls('inline-block h-2 w-2 rounded-full', e.activa ? 'bg-green-500' : 'bg-red-500')} title={e.activa ? t('Ativo') : t('Inativo')} /></td>
                                            <td className="py-2 text-right text-xs text-gray-500">{haQuanto(e.criada)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Cartao>

                <div className="space-y-4">
                    <Cartao>
                        <h3 className="mb-3 font-bold text-gray-900">⚡ {t('Atalhos rápidos')}</h3>
                        <div className="grid grid-cols-2 gap-2">
                            {ATALHOS.map((a) => (
                                <a key={a.url} href={a.url} className={cls('flex flex-col items-center gap-1 rounded-xl border border-transparent bg-gray-50 p-3 transition hover:-translate-y-0.5', a.cor)}>
                                    <i className={cls('fas', a.icone)} aria-hidden="true" />
                                    <span className="text-[10px] font-bold text-gray-700">{t(a.rotulo)}</span>
                                </a>
                            ))}
                        </div>
                    </Cartao>
                    <div className="rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 p-5 text-white shadow-lg">
                        <h3 className="mb-3 font-bold"><i className="fas fa-server mr-2" aria-hidden="true" />{t('Sistema')}</h3>
                        <dl className="grid grid-cols-2 gap-2 text-xs">
                            {([['PHP', d.sistema.php], ['Laravel', d.sistema.laravel], [t('Ambiente'), d.sistema.ambiente], ['Debug', d.sistema.debug ? 'ON' : 'OFF'], ['DB', d.sistema.base_de_dados], ['Cache', d.sistema.cache], ['Queue', d.sistema.fila], ['TZ', d.sistema.fuso]] as const).map(([r, v]) => (
                                <div key={r}><dt className="text-slate-400">{r}</dt><dd className={cls('font-mono font-bold', r === 'Debug' && (d.sistema.debug ? 'text-red-400' : 'text-green-400'))}>{v}</dd></div>
                            ))}
                        </dl>
                    </div>
                </div>
            </div>

            {d.pedidos_pendentes > 0 && (
                <div role="status" className="animate-fade-in flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-600 p-5 text-white shadow-lg">
                    <div>
                        <h3 className="text-lg font-bold"><i className="fas fa-bell mr-2 animate-pulse" aria-hidden="true" />{t(':n pedido(s) pendente(s)', { n: d.pedidos_pendentes })}</h3>
                        <p className="text-sm text-white/80">{t('Aguardam aprovação ou ação no sistema')}</p>
                    </div>
                    <a href="/superadmin/billing" className="rounded-xl bg-white px-5 py-2 font-bold text-orange-600 transition hover:bg-yellow-100">{t('Ver')} →</a>
                </div>
            )}
        </div>
    );
}

function Cartao({ className, children }: { className?: string; children: ReactNode }) {
    return <div className={cls('entra rounded-2xl border border-gray-100 bg-white p-5 shadow-lg', className)}>{children}</div>;
}

function Kpi({ i, url, icone, tom, rotulo, valor, selo, nota }: { i: number; url: string; icone: string; tom: string; rotulo: string; valor: ReactNode; selo: ReactNode; nota: string }) {
    return (
        <a href={url} style={cascata(i)} className="entra group rounded-2xl border border-gray-100 bg-white p-4 shadow transition hover:-translate-y-1 hover:shadow-2xl">
            <div className="mb-2 flex items-center justify-between">
                <span className={cls('icon-float flex h-10 w-10 items-center justify-center rounded-xl', tom)}><i className={cls('fas', icone)} aria-hidden="true" /></span>
                {selo}
            </div>
            <p className="text-xs font-bold uppercase text-gray-500">{rotulo}</p>
            <p className="text-2xl font-extrabold tabular-nums text-gray-900 md:text-3xl">{valor}</p>
            <p className="mt-1 text-xs text-gray-500">{nota}</p>
        </a>
    );
}

function Barras({ itens, cor, texto }: { itens: Array<{ rotulo: ReactNode; valor: number }>; cor: string; texto: string }) {
    if (!itens.length) return <p className="text-sm italic text-gray-400">{t('Ainda sem dados.')}</p>;
    const maximo = Math.max(1, ...itens.map((x) => x.valor));

    return (
        <div className="space-y-2">
            {itens.map((x, i) => (
                <div key={i}>
                    <div className="mb-1 flex justify-between gap-2 text-xs text-gray-700">
                        <span className="truncate">{x.rotulo}</span>
                        <span className={cls('font-bold tabular-nums', texto)}>{x.valor}</span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full bg-gray-100">
                        <div className={cls('h-full bg-gradient-to-r transition-all duration-700', cor)} style={{ width: `${(x.valor / maximo) * 100}%` } as CSSProperties} />
                    </div>
                </div>
            ))}
        </div>
    );
}

/** As duas linhas numa escala só: empresas e utilizadores novos por dia. */
function Crescimento({ serie }: { serie: InicioDaPlataforma['serie'] }) {
    const L = 700;
    const A = 200;
    const M = 30;
    const maximo = Math.max(1, ...serie.flatMap((p) => [p.empresas, p.utilizadores]));
    const passo = serie.length > 1 ? (L - M * 2) / (serie.length - 1) : 0;
    const x = (i: number) => M + i * passo;
    const y = (v: number) => A - (v / maximo) * (A - M - 10);
    const linha = (chave: 'empresas' | 'utilizadores') => serie.map((p, i) => `${x(i)},${y(p[chave])}`).join(' ');

    return (
        <svg viewBox={`0 0 ${L} ${A + 30}`} className="w-full" preserveAspectRatio="none" style={{ maxHeight: 220 }} role="img"
            aria-label={t('Novas empresas e utilizadores por dia')}>
            {[0, 1, 2, 3, 4].map((i) => (
                <line key={i} x1={M} x2={L - M} y1={M + (i * (A - M)) / 4} y2={M + (i * (A - M)) / 4} stroke="#e2e8f0" strokeWidth={0.5} strokeDasharray="2 2" />
            ))}
            <polyline points={linha('utilizadores')} fill="none" stroke="#a855f7" strokeWidth={2} />
            <polyline points={linha('empresas')} fill="none" stroke="#3b82f6" strokeWidth={2.5} />
            {serie.map((p, i) => (
                <g key={p.dia}>
                    <circle cx={x(i)} cy={y(p.utilizadores)} r={2.5} fill="#a855f7"><title>{`${p.dia}: ${p.utilizadores}`}</title></circle>
                    <circle cx={x(i)} cy={y(p.empresas)} r={3} fill="#3b82f6"><title>{`${p.dia}: ${p.empresas}`}</title></circle>
                    <text x={x(i)} y={A + 18} textAnchor="middle" fontSize={9} fill="#94a3b8">{`${p.dia.slice(8, 10)}/${p.dia.slice(5, 7)}`}</text>
                </g>
            ))}
        </svg>
    );
}
