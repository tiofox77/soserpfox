import { useQuery } from '@tanstack/react-query';

import { eventos, type Evento } from '@/api/eventos';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
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
 * O PAINEL DOS EVENTOS.
 *
 * O painel antigo tinha quatro números e uma lista dos cinco seguintes. Faltava
 * o que quem produz eventos precisa de saber ao abrir a manhã:
 *
 *  · O PROGRESSO de cada evento. Um evento a três dias com o checklist a 20% é
 *    um problema; o mesmo a 90% não é. O número existia na base de dados e não
 *    aparecia em ecrã nenhum.
 *  · O EQUIPAMENTO EM ATRASO, com secção própria. Um projector que devia ter
 *    voltado há uma semana é um projector que não está cá para sábado — e isso
 *    só se descobria na montagem.
 */

export const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    orcamento: 'neutra',
    confirmado: 'primaria',
    em_montagem: 'aviso',
    em_andamento: 'primaria',
    concluido: 'bom',
    cancelado: 'perigo',
};

export const cor = (estado: string) => COR_DO_ESTADO[estado] ?? 'neutra';

export default function PainelDosEventos() {
    const painel = useQuery({
        queryKey: ['eventos', 'painel'],
        queryFn: () => eventos.painel(),
        refetchInterval: 120_000,
    });

    if (painel.isPending) return <Carregando linhas={8} />;
    if (painel.isError) return <AvisoDeErro erro={painel.error} />;

    const p = painel.data;
    const r = p.resumo;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Painel dos Eventos')}
                subtitulo={t('Onde está cada um, e o que lhe falta')}
                icone="fa-calendar-alt"
                cor="rosa"
                accoes={
                    <>
                        <a href="/events/calendar" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-calendar-days" aria-hidden="true" />
                            {t('Agenda')}
                        </a>
                        <a href="/events/equipment" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-tools" aria-hidden="true" />
                            {t('Equipamentos')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-user-tie">
                        {t(':n técnicos', { n: numero(r.tecnicos) })}
                    </EstadoNaFaixa>
                    <EstadoNaFaixa icone="fa-map-marker-alt">
                        {t(':n locais', { n: numero(r.locais) })}
                    </EstadoNaFaixa>
                </div>
            </Faixa>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero
                    aspecto="claro" rotulo={t('Eventos do mês')} valor={numero(r.do_mes)}
                    icone="fa-calendar-check" tom="roxo"
                    nota={t(':c confirmados · :a a decorrer', {
                        c: numero(r.confirmados), a: numero(r.a_decorrer),
                    })}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Valor do mês')} valor={kz(r.valor_do_mes)} sufixo="Kz"
                    icone="fa-sack-dollar" tom="verde"
                    nota={t('Os cancelados não contam')}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Equipamento em serviço')} valor={numero(r.equipamento_em_uso)}
                    icone="fa-tools" tom="indigo"
                    nota={t('De :n no parque', { n: numero(r.equipamento_total) })}
                />
                <CartaoNumero
                    aspecto="claro" rotulo={t('Por devolver')} valor={numero(p.em_atraso.length)}
                    icone="fa-clock" tom={p.em_atraso.length > 0 ? 'vermelho' : 'teal'}
                    nota={t('O que não está cá para a próxima montagem')}
                />
            </div>

            {/*
              * O ATRASO VEM PRIMEIRO, e antes dos gráficos: é a única coisa
              * desta página que obriga alguém a pegar no telefone hoje.
              */}
            {p.em_atraso.length > 0 && (
                <Cartao
                    titulo={t('Equipamento por devolver')}
                    subtitulo={t('Passou a data e não voltou')}
                    icone="fa-triangle-exclamation"
                    semPadding
                >
                    <ul className="divide-y divide-slate-100">
                        {p.em_atraso.map((e, i) => (
                            <li key={e.id} style={cascata(i)} className="entra flex flex-wrap items-center gap-3 px-5 py-3">
                                <span className="grid h-9 w-9 flex-none place-items-center rounded-xl bg-red-50 text-red-600">
                                    <i className="fas fa-clock" aria-hidden="true" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-slate-800">{e.nome}</p>
                                    <p className="truncate text-xs text-slate-500">
                                        {e.com_quem ?? t('Sem destinatário')}
                                    </p>
                                </div>
                                <Etiqueta cor="perigo" icone="fa-hourglass-end">
                                    {t(':n dias', { n: numero(e.dias) })}
                                </Etiqueta>
                            </li>
                        ))}
                    </ul>
                </Cartao>
            )}

            <Cartao
                titulo={t('Os próximos eventos')}
                subtitulo={t('Com o progresso do checklist à vista')}
                icone="fa-list-check"
                semPadding
            >
                {p.a_seguir.length === 0 ? (
                    <SemNada
                        icone="fa-calendar-plus"
                        titulo={t('Nenhum evento marcado')}
                        frase={t('Sem eventos não há montagem, nem escala, nem material reservado.')}
                        accao={
                            <a href="/events/calendar" className={cls('inline-flex h-10 items-center gap-2 rounded-xl bg-gradient-to-r from-pink-600 to-rose-600 px-4 text-sm font-semibold text-white shadow-md', FOCO)}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Marcar evento')}
                            </a>
                        }
                    />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {p.a_seguir.map((e, i) => (
                            <LinhaDoEvento key={e.id} evento={e} posicao={i} />
                        ))}
                    </ul>
                )}
            </Cartao>

            <div className="grid gap-5 lg:grid-cols-2">
                <Cartao titulo={t('Eventos dos últimos 12 meses')} icone="fa-chart-column" subtitulo={t('Os meses sem eventos aparecem a zero')}>
                    <GraficoDeBarras
                        titulo={t('Eventos por mês')}
                        dados={p.por_mes.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_mes.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('Em que fase estão')} icone="fa-diagram-project" subtitulo={t('Só os que ainda andam')}>
                    <GraficoHorizontal
                        titulo={t('Eventos por fase')}
                        unidade=""
                        vazio={t('Nenhum evento em curso.')}
                        dados={p.por_fase.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_fase.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('Eventos por estado')} icone="fa-chart-pie">
                    <GraficoHorizontal
                        titulo={t('Eventos por estado')}
                        unidade=""
                        vazio={t('Ainda não há eventos.')}
                        dados={p.por_estado.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_estado.valores[i] ?? 0 }))}
                    />
                </Cartao>

                <Cartao titulo={t('Que tipo de eventos se faz')} icone="fa-tags">
                    <GraficoHorizontal
                        titulo={t('Eventos por tipo')}
                        unidade=""
                        vazio={t('Ainda não há eventos.')}
                        dados={p.por_tipo.etiquetas.map((e, i) => ({ rotulo: e, valor: p.por_tipo.valores[i] ?? 0 }))}
                    />
                </Cartao>
            </div>
        </div>
    );
}

/**
 * Uma linha da lista — e a BARRA DE PROGRESSO, que é a novidade.
 *
 * A percentagem sozinha é um número; a barra ao lado da data diz de relance
 * quais os eventos que estão atrasados no preparo e quais não estão.
 */
function LinhaDoEvento({ evento, posicao }: { evento: Evento; posicao: number }) {
    const inicio = evento.inicio ? new Date(evento.inicio) : null;

    return (
        <li style={cascata(posicao)} className="entra flex flex-wrap items-center gap-3 px-5 py-3">
            <span
                className="grid h-11 w-14 flex-none place-items-center rounded-xl text-white"
                style={{ backgroundColor: evento.cor ?? '#6366f1' }}
            >
                <span className="text-[10px] font-semibold uppercase">
                    {inicio?.toLocaleDateString(etiquetaIntl(), { month: 'short' })}
                </span>
                <span className="text-base font-bold leading-none tabular-nums">{inicio?.getDate()}</span>
            </span>

            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-slate-800">
                    {evento.tipo_icone && <span className="mr-1.5">{evento.tipo_icone}</span>}
                    {evento.nome}
                </p>
                <p className="truncate text-xs text-slate-500">
                    {[evento.cliente, evento.local].filter(Boolean).join(' · ') || t('Sem cliente nem local')}
                </p>
            </div>

            <div className="w-28 flex-none">
                <div className="h-1.5 overflow-hidden rounded-full bg-slate-100">
                    <div
                        className={cls(
                            'h-full rounded-full transition-all duration-500',
                            evento.progresso >= 80 ? 'bg-emerald-500' : evento.progresso >= 40 ? 'bg-amber-500' : 'bg-red-400',
                        )}
                        style={{ width: `${Math.min(100, Math.max(2, evento.progresso))}%` }}
                    />
                </div>
                <p className="mt-1 text-[10px] font-semibold tabular-nums text-slate-500">
                    {t(':n% · :f', { n: String(evento.progresso), f: evento.fase_rotulo })}
                </p>
            </div>

            <Etiqueta cor={cor(evento.estado)} icone={evento.estado_icone}>
                {evento.estado_rotulo}
            </Etiqueta>

            <a
                href={`/events/calendar?evento=${evento.id}`}
                className={cls('grid h-9 w-9 flex-none place-items-center border border-slate-200 bg-white text-slate-600 transition hover:border-pink-300 hover:text-pink-600', RAIO, FOCO)}
                aria-label={t('Abrir o evento')}
            >
                <i className="fas fa-arrow-right" aria-hidden="true" />
            </a>
        </li>
    );
}
