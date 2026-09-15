import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState, type ReactNode } from 'react';

import { indicadoresDaOficina, type IndicadoresDoPeriodo } from '@/api/oficina';
import { Faixa } from '@/ecras/facturacao/faixa';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { GraficoDeLinha } from '@/ui/GraficoDeLinha';
import { cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, data, kz } from '@/ui/tokens';

/**
 * OS INDICADORES DA OFICINA (15/09/2026, OF-18).
 *
 * Os números que dizem se a oficina ganha dinheiro — cada um com a comparação
 * ao período anterior (a seta verde é sempre «melhor», mesmo quando o número
 * desce, como o tempo na oficina) — e a margem das peças e da mão-de-obra.
 */

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const ATALHOS: Array<{ rotulo: string; periodo: () => { de: string; ate: string } }> = [
    { rotulo: 'Este mês', periodo: () => { const h = new Date(); return { de: iso(new Date(h.getFullYear(), h.getMonth(), 1)), ate: iso(h) }; } },
    { rotulo: 'Mês passado', periodo: () => { const h = new Date(); return { de: iso(new Date(h.getFullYear(), h.getMonth() - 1, 1)), ate: iso(new Date(h.getFullYear(), h.getMonth(), 0)) }; } },
    { rotulo: 'Últimos 90 dias', periodo: () => { const h = new Date(); const d = new Date(h); d.setDate(d.getDate() - 89); return { de: iso(d), ate: iso(h) }; } },
    { rotulo: 'Este ano', periodo: () => { const h = new Date(); return { de: iso(new Date(h.getFullYear(), 0, 1)), ate: iso(h) }; } },
];

const num = (n: number, casas = 0) => n.toLocaleString('pt-PT', { minimumFractionDigits: casas, maximumFractionDigits: casas });

function horas(h: number | null): string {
    if (h === null) return '—';
    return h >= 48 ? t(':n dias', { n: num(h / 24, 1) }) : t(':n h', { n: num(h, 1) });
}

/** A variação ao período anterior, com a cor de «melhor» ou «pior». */
function Variacao({ agora, antes, menorEMelhor = false, pontos = false }: { agora: number | null; antes: number | null; menorEMelhor?: boolean; pontos?: boolean }) {
    if (agora === null || antes === null) return <span className="text-xs text-slate-400">{t('sem comparação')}</span>;
    const dif = pontos ? agora - antes : antes === 0 ? (agora === 0 ? 0 : 100) : ((agora - antes) / Math.abs(antes)) * 100;
    if (Math.abs(dif) < 0.05) return <span className="text-xs font-semibold text-slate-400"><i className="fas fa-equals mr-1" aria-hidden="true" />{t('igual')}</span>;
    const melhor = menorEMelhor ? dif < 0 : dif > 0;

    return (
        <span className={cls('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold', melhor ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700')}>
            <i className={cls('fas', dif > 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down')} aria-hidden="true" />
            {dif > 0 ? '+' : ''}{num(dif, 1)}{pontos ? ' pp' : '%'}
        </span>
    );
}

function Indicador({ i, icone, tom, rotulo, valor, nota, variacao, ajuda }: { i: number; icone: string; tom: string; rotulo: string; valor: ReactNode; nota?: ReactNode; variacao: ReactNode; ajuda: string }) {
    return (
        <div style={cascata(i)} title={ajuda} className={cls('entra card-hover flex flex-col gap-2 border border-slate-200 bg-white p-4 shadow-sm', RAIO_GRANDE, TRANSICAO, 'hover:-translate-y-1 hover:shadow-lg')}>
            <div className="flex items-start justify-between gap-2">
                <span className={cls('grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br text-white shadow', tom)}><i className={cls('fas icon-float', icone)} aria-hidden="true" /></span>
                {variacao}
            </div>
            <p className="text-xs font-semibold text-slate-500">{rotulo}</p>
            <p className="text-2xl font-extrabold tabular-nums text-slate-900">{valor}</p>
            {nota && <p className="text-xs text-slate-500">{nota}</p>}
        </div>
    );
}

function Margem({ titulo, icone, receita, custo, margem, aviso }: { titulo: string; icone: string; receita: number; custo: number; margem: number | null; aviso?: string | null }) {
    const largura = margem === null ? 0 : Math.max(0, Math.min(100, margem));
    const tom = margem === null ? 'bg-slate-300' : margem >= 40 ? 'bg-emerald-500' : margem >= 20 ? 'bg-amber-400' : 'bg-red-500';

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between gap-2">
                <span className="flex items-center gap-2 text-sm font-bold text-slate-800"><i className={cls('fas text-slate-400', icone)} aria-hidden="true" />{titulo}</span>
                <span className="text-lg font-extrabold tabular-nums text-slate-900">{margem === null ? '—' : `${num(margem, 1)}%`}</span>
            </div>
            <div className="h-3 overflow-hidden rounded-full bg-slate-100">
                <div className={cls('h-full rounded-full transition-all duration-700', tom)} style={{ width: `${largura}%` }} />
            </div>
            <p className="flex flex-wrap justify-between gap-2 text-xs text-slate-500">
                <span>{t('Vendido')}: <b className="tabular-nums text-slate-700">{kz(receita)} Kz</b></span>
                <span>{t('Custo')}: <b className="tabular-nums text-slate-700">{kz(custo)} Kz</b></span>
            </p>
            {aviso && <p className="text-[11px] text-amber-700"><i className="fas fa-circle-info mr-1" aria-hidden="true" />{aviso}</p>}
        </div>
    );
}

export default function Indicadores() {
    const [periodo, porPeriodo] = useState(() => ATALHOS[0]!.periodo());
    const q = useQuery({ queryKey: ['oficina', 'indicadores', periodo], queryFn: () => indicadoresDaOficina.ler(periodo.de, periodo.ate), placeholderData: keepPreviousData });

    if (q.isPending) return <Carregando linhas={10} />;
    if (q.isError) return <AvisoDeErro erro={q.error} />;

    const d = q.data;
    const a: IndicadoresDoPeriodo = d.actual;
    const b: IndicadoresDoPeriodo = d.anterior;

    return (
        <div className={cls('space-y-4', q.isFetching && 'opacity-80 transition-opacity')}>
            <Faixa titulo={t('Indicadores da Oficina')} subtitulo={t('Ticket médio, aprovação, horas, tempo na oficina e margens — comparados com o período anterior')} icone="fa-gauge-high" cor="primaria" />

            <div className={cls('flex flex-wrap items-end gap-3 border border-slate-200 bg-white p-3 shadow-sm', RAIO_GRANDE)}>
                <div className="flex flex-wrap gap-1">
                    {ATALHOS.map((x) => {
                        const p = x.periodo();
                        const activo = p.de === periodo.de && p.ate === periodo.ate;
                        return (
                            <button key={x.rotulo} type="button" aria-pressed={activo} onClick={() => porPeriodo(p)}
                                className={cls('px-3 py-1.5 text-xs font-semibold', RAIO, TRANSICAO, FOCO, activo ? 'bg-indigo-600 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200')}>
                                {t(x.rotulo)}
                            </button>
                        );
                    })}
                </div>
                <label className="text-xs font-semibold text-slate-600">{t('De')}<input type="date" value={periodo.de} onChange={(e) => porPeriodo({ ...periodo, de: e.target.value })} className={cls(entrada, 'mt-1')} /></label>
                <label className="text-xs font-semibold text-slate-600">{t('Até')}<input type="date" value={periodo.ate} onChange={(e) => porPeriodo({ ...periodo, ate: e.target.value })} className={cls(entrada, 'mt-1')} /></label>
                <p className="text-xs text-slate-500 sm:ml-auto">{t('Comparado com :de – :ate', { de: data(d.anterior_periodo.de), ate: data(d.anterior_periodo.ate) })}</p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Indicador i={0} icone="fa-sack-dollar" tom="from-emerald-500 to-teal-600" rotulo={t('Receita das ordens fechadas')} valor={<>{kz(a.receita)} <span className="text-sm text-slate-400">Kz</span></>}
                    nota={t(':o ordens · :v viaturas', { o: a.ordens, v: a.viaturas })} variacao={<Variacao agora={a.receita} antes={b.receita} />} ajuda={t('Ordens concluídas (ou entregues) no período.')} />
                <Indicador i={1} icone="fa-receipt" tom="from-indigo-500 to-violet-600" rotulo={t('Ticket médio')} valor={a.ticket_medio === null ? '—' : <>{kz(a.ticket_medio)} <span className="text-sm text-slate-400">Kz</span></>}
                    nota={t('por ordem fechada')} variacao={<Variacao agora={a.ticket_medio} antes={b.ticket_medio} />} ajuda={t('Receita ÷ ordens fechadas.')} />
                <Indicador i={2} icone="fa-thumbs-up" tom="from-sky-500 to-blue-600" rotulo={t('Aprovação dos orçamentos')} valor={a.aprovacao.taxa === null ? '—' : `${num(a.aprovacao.taxa, 1)}%`}
                    nota={t(':a aprovadas · :r recusadas', { a: a.aprovacao.aprovadas, r: a.aprovacao.recusadas })} variacao={<Variacao agora={a.aprovacao.taxa} antes={b.aprovacao.taxa} pontos />} ajuda={t('Valor aprovado ÷ valor decidido pelo cliente no período.')} />
                <Indicador i={3} icone="fa-hourglass-half" tom="from-amber-500 to-orange-500" rotulo={t('Tempo médio na oficina')} valor={horas(a.tempo_medio_horas)}
                    nota={t('da entrada ao fecho')} variacao={<Variacao agora={a.tempo_medio_horas} antes={b.tempo_medio_horas} menorEMelhor />} ajuda={t('Média do tempo entre a entrada e a conclusão.')} />
                <Indicador i={4} icone="fa-stopwatch" tom="from-purple-500 to-pink-600" rotulo={t('Eficiência (vendidas ÷ trabalhadas)')} valor={a.horas.eficiencia === null ? '—' : `${a.horas.eficiencia}%`}
                    nota={t(':v h vendidas · :t h trabalhadas', { v: num(a.horas.vendidas, 1), t: num(a.horas.trabalhadas, 1) })} variacao={<Variacao agora={a.horas.eficiencia} antes={b.horas.eficiencia} pontos />} ajuda={t('Horas das linhas de serviço ÷ horas do relógio.')} />
                <Indicador i={5} icone="fa-gears" tom="from-teal-500 to-cyan-600" rotulo={t('Margem das peças')} valor={a.pecas.margem === null ? '—' : `${num(a.pecas.margem, 1)}%`}
                    nota={t(':v Kz vendidos', { v: kz(a.pecas.receita) })} variacao={<Variacao agora={a.pecas.margem} antes={b.pecas.margem} pontos />} ajuda={t('(Venda − custo do artigo) ÷ venda, só nas peças com custo.')} />
                <Indicador i={6} icone="fa-screwdriver-wrench" tom="from-rose-500 to-red-600" rotulo={t('Margem da mão-de-obra')} valor={a.mao_de_obra.margem === null ? '—' : `${num(a.mao_de_obra.margem, 1)}%`}
                    nota={t(':v Kz vendidos', { v: kz(a.mao_de_obra.receita) })} variacao={<Variacao agora={a.mao_de_obra.margem} antes={b.mao_de_obra.margem} pontos />} ajuda={t('(Venda − horas × preço/hora do mecânico) ÷ venda.')} />
                <Indicador i={7} icone="fa-star" tom="from-yellow-400 to-amber-500" rotulo={t('Satisfação dos clientes')} valor={a.satisfacao === null ? '—' : <>{num(a.satisfacao, 1)} <span className="text-sm text-slate-400">/ 5</span></>}
                    nota={a.recomendacoes.total ? t(':p% das recomendações aceites', { p: num(a.recomendacoes.taxa ?? 0, 0) }) : t('sem recomendações no período')} variacao={<Variacao agora={a.satisfacao} antes={b.satisfacao} />} ajuda={t('Média das avaliações respondidas no período.')} />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <section className={cls('space-y-5 border border-slate-200 bg-white p-5 shadow-sm', RAIO_GRANDE)}>
                    <h2 className="flex items-center gap-2 text-sm font-bold text-slate-800"><i className="fas fa-scale-balanced text-indigo-500" aria-hidden="true" />{t('Margens do período')}</h2>
                    <Margem titulo={t('Peças')} icone="fa-gears" receita={a.pecas.receita} custo={a.pecas.custo} margem={a.pecas.margem}
                        aviso={a.pecas.linhas_sem_custo ? t(':n linha(s) de peças sem custo no catálogo ficaram fora da margem.', { n: a.pecas.linhas_sem_custo }) : null} />
                    <Margem titulo={t('Mão-de-obra')} icone="fa-screwdriver-wrench" receita={a.mao_de_obra.receita} custo={a.mao_de_obra.custo} margem={a.mao_de_obra.margem}
                        aviso={a.mao_de_obra.sem_preco_hora ? t('Os mecânicos não têm preço/hora: a margem da mão-de-obra fica a 100%. Preencha-o em Mecânicos.') : a.mao_de_obra.estimado ? t('Sem registo de tempos: o custo usa as horas vendidas.') : null} />
                    <div className={cls('grid grid-cols-2 gap-3 bg-slate-50 p-3 text-xs', RAIO)}>
                        <span>{t('Recusado pelos clientes')}: <b className="tabular-nums text-slate-800">{kz(a.aprovacao.valor_recusado)} Kz</b></span>
                        <span className="text-right">{t('Recomendações por vender')}: <b className="tabular-nums text-slate-800">{kz(a.recomendacoes.por_vender)} Kz</b></span>
                    </div>
                </section>

                <section className={cls('space-y-4 border border-slate-200 bg-white p-5 shadow-sm', RAIO_GRANDE)}>
                    <h2 className="flex items-center gap-2 text-sm font-bold text-slate-800"><i className="fas fa-chart-column text-emerald-500" aria-hidden="true" />{t('Últimos 12 meses')}</h2>
                    <GraficoDeBarras dados={d.meses.map((m) => ({ rotulo: m.rotulo, valor: m.receita }))} altura={170} titulo={t('Receita das ordens fechadas (Kz)')} />
                    <GraficoDeLinha dados={d.meses.map((m) => ({ rotulo: m.rotulo, valor: m.ticket_medio }))} titulo={t('Ticket médio (Kz)')} unidade="Kz" altura={140} />
                </section>
            </div>
        </div>
    );
}
