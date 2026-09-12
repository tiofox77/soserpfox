import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { salao, type MarcacaoNaAgenda } from '@/api/salao';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { GraficoHorizontal } from '@/ui/GraficoHorizontal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O PAINEL DO SALÃO — o dia, cadeira a cadeira.
 *
 * A AGENDA POR PESSOA é a razão de ser deste ecrã: um salão não trabalha por
 * lista de marcações, trabalha por cadeira. Quem abre isto de manhã quer saber
 * quem tem o dia cheio e quem tem buracos — e isso não se vê numa lista
 * ordenada por hora.
 *
 * AS FALTAS TÊM CARTÃO PRÓPRIO. Um salão com muitos «não compareceu» tem um
 * problema de confirmação, não de procura.
 */

/**
 * A COR DE CADA ESTADO.
 *
 * São os cinco papéis da casa e mais nenhum — `aviso` é o que ainda não está
 * confirmado, `primaria` o que está a andar, `bom` o que acabou, `perigo` o que
 * se perdeu. O ÍCONE repete a informação para quem não distingue as cores.
 */
export const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    scheduled: 'aviso',
    confirmed: 'primaria',
    arrived: 'aviso',
    in_progress: 'primaria',
    completed: 'bom',
    cancelled: 'perigo',
    no_show: 'perigo',
};

export const ICONE_DO_ESTADO: Record<string, string> = {
    scheduled: 'fa-calendar',
    confirmed: 'fa-circle-check',
    arrived: 'fa-person-walking-arrow-right',
    in_progress: 'fa-scissors',
    completed: 'fa-flag-checkered',
    cancelled: 'fa-xmark',
    no_show: 'fa-user-slash',
};

const cor = (estado: string) => COR_DO_ESTADO[estado] ?? 'neutra';

export default function PainelDoSalao() {
    const cache = useQueryClient();

    const [dia, porDia] = useState(() => new Date().toISOString().slice(0, 10));
    const [recado, porRecado] = useState('');
    const [erro, porErro] = useState<unknown>(null);

    const painel = useQuery({
        queryKey: ['salao', 'painel', dia],
        queryFn: () => salao.painel(dia),
        refetchInterval: 60_000,
    });

    const estado = useMutation({
        mutationFn: ({ id, estado }: { id: number; estado: string }) => salao.marcacoes.estado(id, estado),
        onSuccess: (r) => {
            porErro(null);
            porRecado(r.message);
            void cache.invalidateQueries({ queryKey: ['salao'] });
        },
        onError: porErro,
    });

    if (painel.isPending) return <Carregando linhas={8} />;
    if (painel.isError) return <AvisoDeErro erro={painel.error} />;

    const p = painel.data;
    const r = p.resumo;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    const andar = (passo: number) =>
        porDia(new Date(new Date(dia).getTime() + passo * 86400_000).toISOString().slice(0, 10));

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Painel do Salão')}
                subtitulo={t('O dia, cadeira a cadeira')}
                icone="fa-spa"
                cor="rosa"
                accoes={
                    <>
                        <a href="/salon/appointments" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-calendar-check" aria-hidden="true" />
                            {t('Marcações')}
                        </a>
                        <a href="/salon/pos" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-cash-register" aria-hidden="true" />
                            {t('Balcão')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <Botao altura="pequeno" icone="fa-chevron-left" onClick={() => andar(-1)} aria-label={t('Dia anterior')} />
                    <EstadoNaFaixa icone="fa-calendar-day">
                        {new Date(dia).toLocaleDateString(etiquetaIntl(), {
                            weekday: 'long', day: '2-digit', month: 'long',
                        })}
                    </EstadoNaFaixa>
                    <Botao altura="pequeno" icone="fa-chevron-right" onClick={() => andar(1)} aria-label={t('Dia seguinte')} />
                    <Botao altura="pequeno" onClick={() => porDia(new Date().toISOString().slice(0, 10))}>
                        {t('Hoje')}
                    </Botao>
                </div>
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero
                    aspecto="claro" rotulo={t('Marcações do dia')} valor={numero(r.marcacoes)}
                    icone="fa-calendar-check" tom="roxo"
                    nota={t(':c confirmadas · :e em curso', { c: numero(r.confirmadas), e: numero(r.em_curso) })}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Receita do dia')} valor={kz(r.receita_do_dia)} sufixo="Kz"
                    icone="fa-sack-dollar" tom="verde"
                    nota={t('No mês: :v Kz', { v: kz(r.receita_do_mes, 0) })}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Tempo médio')} valor={numero(r.duracao_media)} sufixo="min"
                    icone="fa-stopwatch" tom="indigo"
                    nota={t('Espera média: :n min', { n: numero(r.espera_media) })}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Faltas')} valor={numero(r.faltas)}
                    icone="fa-user-slash" tom={r.faltas > 0 ? 'vermelho' : 'teal'}
                    nota={t('Mede a confirmação, não a procura')}
                />
            </div>

            {p.a_seguir.length > 0 && (
                <Cartao
                    titulo={t('A seguir')}
                    subtitulo={t('As próximas cinco à porta')}
                    icone="fa-hourglass-half"
                    semPadding
                >
                    <ul className="flex gap-3 overflow-x-auto px-5 py-4">
                        {p.a_seguir.map((m, i) => (
                            <li
                                key={m.id}
                                style={cascata(i)}
                                className={cls('entra w-56 flex-none border border-slate-200 bg-white p-3 transition hover:-translate-y-0.5 hover:shadow-md', CARTAO, RAIO)}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-sm font-bold tabular-nums text-slate-900">{m.inicio ?? '—'}</span>
                                    <Etiqueta cor={cor(m.estado)} icone={ICONE_DO_ESTADO[m.estado]}>
                                        {m.estado_rotulo}
                                    </Etiqueta>
                                </div>
                                <p className="mt-2 truncate text-sm font-semibold text-slate-800">{m.cliente}</p>
                                <p className="truncate text-xs text-slate-500">
                                    {m.profissional ?? t('Sem profissional')}
                                </p>
                                <p className="mt-1 truncate text-xs text-slate-400">
                                    {m.servicos.join(' · ') || t('Sem serviços')}
                                </p>
                            </li>
                        ))}
                    </ul>
                </Cartao>
            )}

            <Cartao
                titulo={t('A agenda do dia')}
                subtitulo={t('Quem tem o dia cheio, e quem tem buracos')}
                icone="fa-user-clock"
                semPadding
            >
                {p.agenda.length === 0 ? (
                    <SemNada
                        icone="fa-user-clock"
                        titulo={t('Ainda não há profissionais')}
                        frase={t('Sem profissionais não há agenda — e a página de marcação não oferece horas nenhumas.')}
                        accao={
                            <a href="/salon/professionals" className={cls('inline-flex h-10 items-center gap-2 rounded-xl bg-gradient-to-r from-pink-600 to-rose-600 px-4 text-sm font-semibold text-white shadow-md', FOCO)}>
                                <i className="fas fa-user-plus" aria-hidden="true" />
                                {t('Profissionais')}
                            </a>
                        }
                    />
                ) : (
                    <div className="divide-y divide-slate-100">
                        {p.agenda.map((prof, i) => (
                            <section key={prof.id} style={cascata(i)} className="entra px-5 py-4">
                                <header className="mb-2 flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-2">
                                        <span className="grid h-9 w-9 flex-none place-items-center rounded-xl bg-pink-50 text-pink-600">
                                            <i className="fas fa-scissors" aria-hidden="true" />
                                        </span>
                                        <div>
                                            <p className="text-sm font-bold text-slate-800">{prof.nome}</p>
                                            {prof.especialidade && (
                                                <p className="text-xs text-slate-500">{prof.especialidade}</p>
                                            )}
                                        </div>
                                    </div>
                                    <span className="text-xs font-semibold text-slate-500">
                                        {t(':n marcações', { n: numero(prof.marcacoes.length) })}
                                    </span>
                                </header>

                                {prof.marcacoes.length === 0 ? (
                                    <p className="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-400">
                                        {t('Dia livre.')}
                                    </p>
                                ) : (
                                    <ul className="space-y-2">
                                        {prof.marcacoes.map((m) => (
                                            <LinhaDaAgenda
                                                key={m.id}
                                                marcacao={m}
                                                aTrabalhar={estado.isPending}
                                                aoEstado={(e) => estado.mutate({ id: m.id, estado: e })}
                                            />
                                        ))}
                                    </ul>
                                )}
                            </section>
                        ))}
                    </div>
                )}
            </Cartao>

            <div className="grid gap-5 lg:grid-cols-2">
                <Cartao titulo={t('Receita dos últimos 30 dias')} icone="fa-chart-line" subtitulo={t('Os dias fechados aparecem a zero')}>
                    <GraficoDeBarras
                        titulo={t('Receita por dia')}
                        dados={p.por_dia.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_dia.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('Marcações do mês por estado')} icone="fa-chart-pie">
                    <GraficoHorizontal
                        titulo={t('Marcações por estado')}
                        unidade=""
                        vazio={t('Ainda não há marcações este mês.')}
                        dados={p.por_estado.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_estado.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('Quanto rende cada profissional')} icone="fa-award" subtitulo={t('Este mês')}>
                    <GraficoHorizontal
                        titulo={t('Receita por profissional')}
                        vazio={t('Ainda não houve atendimentos concluídos este mês.')}
                        dados={p.por_profissional.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_profissional.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('Serviços mais pedidos')} icone="fa-fire" subtitulo={t('Este mês')}>
                    <GraficoHorizontal
                        titulo={t('Serviços mais pedidos')}
                        unidade=""
                        vazio={t('Ainda não há serviços marcados este mês.')}
                        dados={p.servicos.etiquetas.map((e, i) => ({ rotulo: e, valor: p.servicos.valores[i] ?? 0 }))}
                    />
                </Cartao>
            </div>
        </div>
    );
}

/**
 * Uma linha da agenda — e os passos que a marcação pode dar a seguir.
 *
 * OS BOTÕES SAEM DO SERVIDOR (`pode`). O ecrã em Livewire mostrava-os todos, e
 * dava para concluir uma marcação cancelada.
 */
function LinhaDaAgenda({
    marcacao, aTrabalhar, aoEstado,
}: {
    marcacao: MarcacaoNaAgenda & { pode?: Array<{ valor: string; rotulo: string }> };
    aTrabalhar: boolean;
    aoEstado: (estado: string) => void;
}) {
    const seguintes = (marcacao as { pode?: Array<{ valor: string; rotulo: string }> }).pode ?? [];

    const principal = seguintes.find((s) => ['confirmed', 'arrived', 'in_progress', 'completed'].includes(s.valor));

    return (
        <li className={cls('flex flex-wrap items-center gap-3 border border-slate-200 bg-white px-3 py-2', RAIO)}>
            <span className="grid h-11 w-16 flex-none place-items-center rounded-lg bg-slate-100 text-slate-700">
                <span className="text-sm font-bold tabular-nums">{marcacao.inicio ?? '—'}</span>
                <span className="text-[10px] text-slate-500">{t(':n min', { n: String(marcacao.duracao) })}</span>
            </span>

            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-slate-800">{marcacao.cliente}</p>
                <p className="truncate text-xs text-slate-500">
                    {marcacao.servicos.join(' · ') || t('Sem serviços')}
                </p>
            </div>

            <Etiqueta cor={cor(marcacao.estado)} icone={ICONE_DO_ESTADO[marcacao.estado]}>
                {marcacao.estado_rotulo}
            </Etiqueta>

            <span className="flex-none text-sm font-bold tabular-nums text-slate-900">{kz(marcacao.total)}</span>

            {principal && (
                <Botao
                    altura="pequeno"
                    cor={principal.valor === 'completed' ? 'bom' : 'primaria'}
                    tom="solida"
                    icone="fa-arrow-right"
                    aTrabalhar={aTrabalhar}
                    onClick={() => aoEstado(principal.valor)}
                >
                    {principal.rotulo}
                </Botao>
            )}
        </li>
    );
}
