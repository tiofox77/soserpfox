import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { ferramentas } from '@/api/plataforma';
import { t } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, dataHora, haQuanto, kz } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { ErroDoEcra, Paginas } from './comum';

type Filtro = 'tudo' | 'atrasados' | 'instalados' | 'adormecidos';

/**
 * QUE EMPRESAS USAM O PWA, EM QUE APARELHOS, E EM QUE VERSÃO.
 *
 * Existe porque um deploy do motor podia não chegar aos aparelhos e não havia
 * como o saber. A coluna que interessa é a VERSÃO, e a linha que interessa é a
 * atrasada. O resumo conta sempre tudo — um resumo que encolhe com o filtro faz
 * o problema parecer menor do que é. E os cartões são o filtro.
 */
export default function AparelhosPwa() {
    const [filtros, porFiltros] = useState<{ procura?: string; filtro: Filtro; pagina: number }>({ filtro: 'tudo', pagina: 1 });

    const lista = useQuery({
        queryKey: ['plataforma', 'aparelhos-pwa', filtros],
        queryFn: () => ferramentas.aparelhos.ler(filtros),
        placeholderData: keepPreviousData,
        refetchInterval: 60_000,
    });

    const filtrar = (filtro: Filtro) => porFiltros((f) => ({ ...f, filtro: f.filtro === filtro ? 'tudo' : filtro, pagina: 1 }));

    if (lista.isPending) return <Carregando linhas={8} />;
    if (lista.isError) return <ErroDoEcra titulo={t('Não foi possível abrir os aparelhos')} erro={lista.error} />;

    const d = lista.data;
    const r = d.resumo;
    const aceso = (f: Filtro, anel: string) => cls(filtros.filtro === f && `ring-2 ${anel}`);

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Aparelhos com PWA')}
                subtitulo={t('Que empresas usam o ponto de venda offline, em que aparelhos, e em que versão. A coluna que interessa é a versão.')}
                icone="fa-mobile-screen-button"
                cor="neutra"
                accoes={
                    <button type="button" className={ACCAO_DA_FAIXA} onClick={() => void lista.refetch()} disabled={lista.isFetching}>
                        <i className={cls('fas fa-rotate', lista.isFetching && 'fa-spin')} aria-hidden="true" />{t('Actualizar')}
                    </button>
                }
            >
                <EstadoNaFaixa icone="fa-code-branch">
                    {t('Versão servida agora')}: <span className="font-mono font-black">{d.versao_actual}</span>
                </EstadoNaFaixa>
            </Faixa>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <CartaoNumero rotulo={t('Empresas')} valor={kz(r.empresas, 0)} icone="fa-building" tom="cinza" aspecto="claro" nota={t('com pelo menos um aparelho')} />
                <CartaoNumero rotulo={t('Aparelhos')} valor={kz(r.aparelhos, 0)} icone="fa-mobile-screen" tom="azul" aspecto="claro" nota={t('já vistos')}
                    aoCarregar={() => filtrar('tudo')} className={aceso('tudo', 'ring-blue-300')} />
                <CartaoNumero rotulo={t('Instalados')} valor={kz(r.instalados, 0)} icone="fa-house-signal" tom="verde" aspecto="claro" nota={t('no ecrã principal')}
                    aoCarregar={() => filtrar('instalados')} className={aceso('instalados', 'ring-emerald-300')} />
                <CartaoNumero rotulo={t('Atrasados')} valor={kz(r.atrasados, 0)} icone="fa-clock-rotate-left" tom={r.atrasados > 0 ? 'vermelho' : 'cinza'} aspecto="claro" nota={t('não têm a última versão')}
                    aoCarregar={() => filtrar('atrasados')} className={aceso('atrasados', 'ring-red-300')} />
                <CartaoNumero rotulo={t('Adormecidos')} valor={kz(r.adormecidos, 0)} icone="fa-bed" tom="ambar" aspecto="claro" nota={t(':n dias sem falar', { n: d.dias_ate_adormecer })}
                    aoCarregar={() => filtrar('adormecidos')} className={aceso('adormecidos', 'ring-amber-300')} />
            </div>

            {r.atrasados > 0 && (
                <div role="alert" className={cls('entra flex items-start gap-3 border border-red-200 bg-red-50 p-4', RAIO)}>
                    <i className="fas fa-triangle-exclamation icon-float mt-0.5 text-red-500" aria-hidden="true" />
                    <div className="text-sm">
                        <p className="font-bold text-red-800">
                            {r.atrasados === 1 ? t(':n aparelho não tem a última versão', { n: r.atrasados }) : t(':n aparelhos não têm a última versão', { n: r.atrasados })}
                        </p>
                        <p className="mt-0.5 text-red-700">
                            {t('Cada um desses está a correr código antigo — uma correcção deployada pode não ter chegado a quem vende. Os instalados no ecrã principal são os que mais tempo ficam presos.')}
                        </p>
                    </div>
                </div>
            )}

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <label className={cls(CARTAO, 'flex h-11 flex-1 items-center gap-2 px-4')}>
                    <i className="fas fa-magnifying-glass text-slate-400" aria-hidden="true" />
                    <span className="sr-only">{t('Procurar')}</span>
                    <input type="search" className="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:ring-0" placeholder={t('Empresa, aparelho ou versão…')}
                        value={filtros.procura ?? ''} onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value || undefined, pagina: 1 }))} />
                </label>
                <div className="flex gap-2 overflow-x-auto" role="group" aria-label={t('Filtro')}>
                    {([['tudo', t('Todos')], ['atrasados', t('Atrasados')], ['instalados', t('Instalados')], ['adormecidos', t('Adormecidos')]] as Array<[Filtro, string]>).map(([chave, rotulo]) => (
                        <button key={chave} type="button" aria-pressed={filtros.filtro === chave}
                            onClick={() => porFiltros((f) => ({ ...f, filtro: chave, pagina: 1 }))}
                            className={cls('h-11 shrink-0 px-4 text-sm font-bold', RAIO, TRANSICAO, FOCO,
                                filtros.filtro === chave ? 'bg-slate-900 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50')}>
                            {rotulo}
                        </button>
                    ))}
                </div>
            </div>

            <section className={cls(CARTAO, 'overflow-hidden')}>
                {d.aparelhos.length === 0 ? (
                    <SemNada icone="fa-mobile-screen" frase={filtros.procura || filtros.filtro !== 'tudo'
                        ? t('Nada com este filtro.')
                        : t('Ainda nenhum aparelho se identificou. Aparecem aqui à primeira sincronização.')} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">{t('Empresa')}</th>
                                    <th className="px-4 py-3">{t('Versão')}</th>
                                    <th className="px-4 py-3">{t('Como')}</th>
                                    <th className="px-4 py-3">{t('Último operador')}</th>
                                    <th className="px-4 py-3">{t('Visto')}</th>
                                    <th className="px-4 py-3 text-right">{t('Sincs')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.aparelhos.map((a, i) => (
                                    <tr key={a.id} className={cls('entra', TRANSICAO, a.atrasado ? 'bg-red-50/40 hover:bg-red-50' : 'hover:bg-slate-50')} style={cascata(i)}>
                                        <td className="px-4 py-3">
                                            <p className="font-bold text-slate-800">{a.empresa ?? t('(empresa apagada)')}</p>
                                            <p className="font-mono text-[11px] text-slate-400" title={a.aparelho}>{a.aparelho.length > 14 ? `${a.aparelho.slice(0, 13)}…` : a.aparelho}</p>
                                        </td>
                                        <td className="px-4 py-3">
                                            {a.versao ? (
                                                <span className={cls('rounded-lg px-2 py-1 font-mono text-xs font-bold', a.atrasado ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700')}>{a.versao}</span>
                                            ) : (
                                                <span className="rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold text-slate-500">{t('não diz')}</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {a.instalado
                                                ? <span className="text-xs font-bold text-emerald-700"><i className="fas fa-mobile-screen-button mr-1" aria-hidden="true" />{t('Instalado')}</span>
                                                : <span className="text-xs text-slate-500"><i className="fas fa-globe mr-1" aria-hidden="true" />{t('Browser')}</span>}
                                            {a.plataforma && <p className="text-[11px] text-slate-400">{a.plataforma}</p>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="text-slate-700">{a.utilizador ?? '—'}</p>
                                            <p className="text-[11px] text-slate-400">{a.email}</p>
                                        </td>
                                        <td className="px-4 py-3">
                                            {a.visto_em
                                                ? <span title={dataHora(a.visto_em)} className={a.adormecido ? 'font-bold text-amber-700' : 'text-slate-600'}>{haQuanto(a.visto_em)}</span>
                                                : <span className="text-slate-400">—</span>}
                                        </td>
                                        <td className="px-4 py-3 text-right font-bold tabular-nums text-slate-700">{a.sincronizacoes}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <div className="px-4 pb-4">
                    <Paginas pagina={d.paginacao.pagina} ultima={d.paginacao.ultima} aMudar={(p) => porFiltros((f) => ({ ...f, pagina: p }))} />
                </div>
            </section>

            {d.com_modulo_sem_aparelho.length > 0 && (
                <section className={cls(CARTAO, 'card-hover p-5')}>
                    <h3 className="font-black text-slate-800">
                        <i className="fas fa-phone-volume icon-float mr-2 text-indigo-500" aria-hidden="true" />
                        {t('Têm Facturação mas nunca abriram o PWA')}
                        <span className="ml-1 text-sm font-bold text-slate-400">({d.com_modulo_sem_aparelho.length})</span>
                    </h3>
                    <p className="mt-0.5 text-xs text-slate-500">{t('Pagam pelo módulo e não estão a usar o ponto de venda offline. Vale uma chamada.')}</p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {d.com_modulo_sem_aparelho.map((e, i) => (
                            <span key={e.id} className="entra rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600" style={cascata(i)}>{e.nome}</span>
                        ))}
                    </div>
                </section>
            )}
        </div>
    );
}
