import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { salao } from '@/api/salao';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { SemNada, cascata } from '@/ui/SemNada';
import { Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, RAIO, cls, data, kz } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * O RELATÓRIO DE TEMPOS — quanto tempo leva, de facto.
 *
 * É O RELATÓRIO QUE MONTA A AGENDA. Um corte marcado para 30 minutos que leva
 * sempre 50 faz a agenda inteira atrasar-se a partir do meio da manhã, e a
 * culpa parece ser de quem atende. Aqui vê-se o previsto ao lado do real, por
 * atendimento, por pessoa e por serviço.
 *
 * A TOLERÂNCIA É DE CINCO MINUTOS para cada lado: num salão, cinco minutos não
 * são um atraso — são a conversa à porta.
 */

/** Minutos em «1h 30min», que é como se lê um tempo de atendimento. */
function tempo(minutos: number | null | undefined): string {
    if (minutos === null || minutos === undefined) return '—';

    const h = Math.floor(minutos / 60);
    const m = minutos % 60;

    return h > 0 ? `${h}h ${m}min` : `${m}min`;
}

const ATALHOS = [
    { chave: 'semana', rotulo: 'Esta semana' },
    { chave: 'mes', rotulo: 'Este mês' },
    { chave: 'mes-passado', rotulo: 'Mês passado' },
] as const;

function intervalo(chave: string): { de: string; ate: string } {
    const hoje = new Date();
    const iso = (d: Date) => d.toISOString().slice(0, 10);

    if (chave === 'semana') {
        const inicio = new Date(hoje);
        inicio.setDate(hoje.getDate() - ((hoje.getDay() + 6) % 7));

        return { de: iso(inicio), ate: iso(hoje) };
    }

    if (chave === 'mes-passado') {
        return {
            de: iso(new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1)),
            ate: iso(new Date(hoje.getFullYear(), hoje.getMonth(), 0)),
        };
    }

    return { de: iso(new Date(hoje.getFullYear(), hoje.getMonth(), 1)), ate: iso(hoje) };
}

export default function Tempos() {
    const [periodo, porPeriodo] = useState(() => intervalo('mes'));
    const [profissional, porProfissional] = useState<number | ''>('');
    const [servico, porServico] = useState<number | ''>('');

    const dados = useQuery({
        queryKey: ['salao', 'tempos', periodo, profissional, servico],
        queryFn: () => salao.tempos({ ...periodo, profissional, servico }),
        placeholderData: keepPreviousData,
    });

    if (dados.isPending) return <Carregando linhas={8} />;
    if (dados.isError) return <AvisoDeErro erro={dados.error} />;

    const d = dados.data;
    const r = d.resumo;

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Relatório de Tempos')}
                subtitulo={t('O previsto ao lado do real — é o que monta a agenda')}
                icone="fa-stopwatch"
                cor="ciano"
            />

            <div className="flex flex-wrap items-end gap-3">
                <Campo etiqueta={t('De')} className="w-44">
                    <input type="date" value={periodo.de} onChange={(e) => porPeriodo({ ...periodo, de: e.target.value })} className={entrada} />
                </Campo>

                <Campo etiqueta={t('Até')} className="w-44">
                    <input type="date" value={periodo.ate} onChange={(e) => porPeriodo({ ...periodo, ate: e.target.value })} className={entrada} />
                </Campo>

                <div className="flex flex-wrap gap-1.5 pb-0.5">
                    {ATALHOS.map((a) => (
                        <Botao key={a.chave} onClick={() => porPeriodo(intervalo(a.chave))}>{t(a.rotulo)}</Botao>
                    ))}
                </div>

                <Campo etiqueta={t('Profissional')} className="w-52">
                    <select value={profissional} onChange={(e) => porProfissional(e.target.value ? Number(e.target.value) : '')} className={entrada}>
                        <option value="">{t('Todos')}</option>
                        {d.opcoes.profissionais.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Serviço')} className="w-52">
                    <select value={servico} onChange={(e) => porServico(e.target.value ? Number(e.target.value) : '')} className={entrada}>
                        <option value="">{t('Todos')}</option>
                        {d.opcoes.servicos.map((s) => <option key={s.valor} value={s.valor}>{s.rotulo}</option>)}
                    </select>
                </Campo>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero
                    aspecto="claro" rotulo={t('Atendimentos')} valor={r.atendimentos}
                    icone="fa-clipboard-check" tom="indigo"
                    nota={t('Só os que têm as duas horas registadas')}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Tempo médio')} valor={tempo(r.tempo_medio)}
                    icone="fa-stopwatch" tom="teal"
                    nota={t('Total: :v', { v: tempo(r.tempo_total) })}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Espera média')} valor={tempo(r.espera_media)}
                    icone="fa-hourglass-half" tom={r.espera_media >= 15 ? 'vermelho' : 'ambar'}
                    nota={t('Da chegada ao início')}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Eficiência')} valor={r.eficiencia} sufixo="%"
                    icone="fa-gauge-high" tom={r.eficiencia >= 100 ? 'verde' : 'ambar'}
                    nota={t('Previsto a dividir pelo real')}
                />
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
                <div className={cls(CARTAO, 'flex items-center gap-3 p-4')}>
                    <span className="grid h-10 w-10 flex-none place-items-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i className="fas fa-check" aria-hidden="true" />
                    </span>
                    <div>
                        <p className="text-xs text-slate-500">{t('A horas')}</p>
                        <p className="text-lg font-bold tabular-nums text-slate-900">{r.a_horas}</p>
                    </div>
                </div>

                <div className={cls(CARTAO, 'flex items-center gap-3 p-4')}>
                    <span className="grid h-10 w-10 flex-none place-items-center rounded-xl bg-red-50 text-red-600">
                        <i className="fas fa-clock" aria-hidden="true" />
                    </span>
                    <div>
                        <p className="text-xs text-slate-500">{t('Atrasados')}</p>
                        <p className="text-lg font-bold tabular-nums text-slate-900">{r.atrasados}</p>
                    </div>
                </div>

                <div className={cls(CARTAO, 'flex items-center gap-3 p-4')}>
                    <span className="grid h-10 w-10 flex-none place-items-center rounded-xl bg-sky-50 text-sky-600">
                        <i className="fas fa-bolt" aria-hidden="true" />
                    </span>
                    <div>
                        <p className="text-xs text-slate-500">{t('Mais rápidos')}</p>
                        <p className="text-lg font-bold tabular-nums text-slate-900">{r.mais_rapidos}</p>
                    </div>
                </div>
            </div>

            <div className="grid gap-5 lg:grid-cols-2">
                <Cartao titulo={t('Por profissional')} icone="fa-user-group" semPadding>
                    {d.por_profissional.length === 0 ? (
                        <SemNada icone="fa-user-group" frase={t('Nenhum atendimento com tempo registado neste período.')} />
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {d.por_profissional.map((p, i) => (
                                <li key={p.nome} style={cascata(i)} className="entra flex items-center justify-between gap-3 px-5 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-slate-800">{p.nome}</p>
                                        <p className="text-xs text-slate-500">
                                            {t(':n atendimentos', { n: String(p.atendimentos) })}
                                            {' · '}
                                            {t('previsto :p, real :r', { p: tempo(p.previsto_medio), r: tempo(p.tempo_medio) })}
                                        </p>
                                    </div>

                                    <div className="flex flex-none items-center gap-3">
                                        <Etiqueta cor={p.eficiencia >= 100 ? 'bom' : p.eficiencia >= 85 ? 'aviso' : 'perigo'}>
                                            {p.eficiencia}%
                                        </Etiqueta>
                                        <span className="w-24 text-right text-sm font-bold tabular-nums text-slate-900">
                                            {kz(p.receita, 0)}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>

                <Cartao titulo={t('Por serviço')} icone="fa-scissors" semPadding>
                    {d.por_servico.length === 0 ? (
                        <SemNada icone="fa-scissors" frase={t('Nenhum serviço concluído neste período.')} />
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {d.por_servico.map((s, i) => (
                                <li key={s.nome} style={cascata(i)} className="entra flex items-center justify-between gap-3 px-5 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-slate-800">{s.nome}</p>
                                        <p className="text-xs text-slate-500">
                                            {t(':n vezes · :t previstos', { n: String(s.total), t: tempo(s.previsto_medio) })}
                                        </p>
                                    </div>
                                    <span className="flex-none text-sm font-bold tabular-nums text-slate-900">
                                        {kz(s.receita, 0)} <span className="text-xs font-normal text-slate-400">Kz</span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>
            </div>

            <Cartao titulo={t('Atendimento a atendimento')} icone="fa-list" semPadding>
                {d.data.length === 0 ? (
                    <SemNada
                        icone="fa-stopwatch"
                        titulo={t('Nada para medir')}
                        frase={t('Só contam os atendimentos com hora de início E de fim — sem as duas não há tempo real nenhum.')}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[48rem] text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50/60 text-left">
                                    <th scope="col" className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Quando')}</th>
                                    <th scope="col" className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Cliente')}</th>
                                    <th scope="col" className="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Profissional')}</th>
                                    <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Previsto')}</th>
                                    <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Real')}</th>
                                    <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Diferença')}</th>
                                    <th scope="col" className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Espera')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.data.map((m, i) => (
                                    <tr key={m.id} style={cascata(i)} className="entra">
                                        <td className="px-4 py-2.5 tabular-nums text-slate-600">
                                            {m.dia ? data(m.dia) : '—'} {m.inicio}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <p className="font-medium text-slate-800">{m.cliente}</p>
                                            <p className="truncate text-xs text-slate-500">{m.servicos.join(', ')}</p>
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-600">{m.profissional ?? '—'}</td>
                                        <td className="px-4 py-2.5 text-right tabular-nums text-slate-600">{tempo(m.previsto)}</td>
                                        <td className="px-4 py-2.5 text-right font-semibold tabular-nums text-slate-900">{tempo(m.real)}</td>
                                        <td className="px-4 py-2.5 text-right">
                                            {m.diferenca === null ? (
                                                <span className="text-xs text-slate-400">—</span>
                                            ) : (
                                                <Etiqueta cor={Math.abs(m.diferenca) <= 5 ? 'bom' : m.diferenca > 0 ? 'perigo' : 'primaria'}>
                                                    {m.diferenca > 0 ? '+' : ''}{m.diferenca} min
                                                </Etiqueta>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5 text-right tabular-nums text-slate-600">{tempo(m.espera)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Cartao>
        </div>
    );
}
