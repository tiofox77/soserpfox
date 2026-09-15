import { useState } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';

import { painelDaOficina, type OrdemUrgente, type SatisfacaoDosClientes, type Serie, type ViaturaComDocumentos } from '@/api/oficina';
import { Estrelas } from './AvaliarServico';
import { ErroDaApi } from '@/api/cliente';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { GraficoHorizontal } from '@/ui/GraficoHorizontal';
import { Campo, entrada } from '@/ui/Campo';
import { Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O PAINEL DA OFICINA — o que está em cima da bancada, hoje.
 *
 * Não é uma lista de números bonitos: a pergunta que traz alguém a este ecrã é
 * «o que é que eu tenho de fazer». Por isso as ORDENS URGENTES e os DOCUMENTOS
 * A CADUCAR estão aqui, e a carga por mecânico também — é ela que decide a
 * próxima marcação.
 *
 * O DINHEIRO SÓ APARECE A QUEM PODE VER RELATÓRIOS, e a decisão é do servidor:
 * quando não pode, os valores nem viajam. O ecrã em Blade escondia-os com
 * «•••» no HTML e mandava o número na mesma para o browser.
 */

const paraGrafico = (s: Serie) => s.etiquetas.map((rotulo, i) => ({ rotulo, valor: s.valores[i] ?? 0 }));

/** As cores dos estados: «concluída» é verde e «cancelada» vermelha em todo o produto. */
const COR_DO_ESTADO: Record<string, string> = {
    pending: 'bg-amber-500',
    scheduled: 'bg-sky-500',
    in_progress: 'bg-orange-500',
    completed: 'bg-emerald-500',
    delivered: 'bg-slate-500',
    cancelled: 'bg-red-500',
};

export default function Painel() {
    const [periodo, porPeriodo] = useState<{ de: string; ate: string }>(() => {
        const hoje = new Date();
        const inicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1);

        const iso = (d: Date) => d.toISOString().slice(0, 10);

        return { de: iso(inicio), ate: iso(hoje) };
    });

    const q = useQuery({
        queryKey: ['oficina', 'painel', periodo],
        queryFn: () => painelDaOficina.ler(periodo.de, periodo.ate),
        placeholderData: keepPreviousData,
        staleTime: 60_000,
    });

    if (q.isPending) return <Carregando linhas={10} />;
    if (q.isError) return <Falhou erro={q.error} />;

    const d = q.data;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    return (
        <div className={cls('space-y-4', q.isFetching && 'opacity-80 transition-opacity')}>
            <Faixa
                titulo={t('Painel da Oficina')}
                subtitulo={t('O que está em cima da bancada, hoje')}
                icone="fa-gauge-high"
                cor="ciano"
            />

            {/* O PERÍODO. As pendentes e as em curso NÃO dependem dele: o que
                está na bancada está lá hoje, tenha entrado quando tiver. */}
            <div className={cls(CARTAO, 'grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4')}>
                <Campo etiqueta={t('De')}>
                    <input type="date" value={periodo.de} className={cls(entrada, 'tabular-nums')}
                        onChange={(e) => porPeriodo((p) => ({ ...p, de: e.target.value }))} />
                </Campo>
                <Campo etiqueta={t('Até')}>
                    <input type="date" value={periodo.ate} className={cls(entrada, 'tabular-nums')}
                        onChange={(e) => porPeriodo((p) => ({ ...p, ate: e.target.value }))} />
                </Campo>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero aspecto="claro" rotulo={t('Ordens no período')} tom="azul" icone="fa-clipboard-list"
                    valor={numero(d.cartoes.ordens)} />
                <CartaoNumero aspecto="claro" rotulo={t('Pendentes')} tom={d.cartoes.pendentes > 0 ? 'ambar' : 'cinza'}
                    icone="fa-clock" nota={t('em qualquer data')} valor={numero(d.cartoes.pendentes)} />
                <CartaoNumero aspecto="claro" rotulo={t('Em curso')} tom="laranja" icone="fa-screwdriver-wrench"
                    nota={t('em qualquer data')} valor={numero(d.cartoes.em_curso)} />
                <CartaoNumero aspecto="claro" rotulo={t('Concluídas')} tom="verde" icone="fa-circle-check"
                    valor={numero(d.cartoes.concluidas)} />
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {/* Sem permissão de relatórios não há cartões de dinheiro —
                    nem sequer um «•••» a dizer que há um número escondido. */}
                {d.dinheiro && (
                    <>
                        <CartaoNumero aspecto="claro" rotulo={t('Facturado')} tom="verde" icone="fa-money-bill-wave"
                            nota={t('no período, já pago')} valor={`${kz(d.dinheiro.facturado)} Kz`} />
                        <CartaoNumero aspecto="claro" rotulo={t('Por receber')} tom={d.dinheiro.a_receber > 0 ? 'ambar' : 'cinza'}
                            icone="fa-hourglass-half" nota={t('concluídas e entregues')} valor={`${kz(d.dinheiro.a_receber)} Kz`} />
                    </>
                )}
                <CartaoNumero aspecto="claro" rotulo={t('Viaturas')} tom="indigo" icone="fa-car"
                    nota={t(':n activa(s)', { n: numero(d.viaturas.activas) })} valor={numero(d.viaturas.total)} />
            </div>

            <Cartao titulo={d.ve_dinheiro ? t('Facturação da oficina, mês a mês') : t('Ordens concluídas, mês a mês')}
                icone="fa-chart-column">
                <GraficoDeBarras dados={paraGrafico(d.series.mensal)} altura={220}
                    titulo={d.ve_dinheiro ? t('Ordens concluídas (Kz)') : t('Ordens concluídas')} />
            </Cartao>

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo={t('Ordens por estado')} icone="fa-chart-pie">
                    <EstadosDaOficina serie={d.series.estados} />
                </Cartao>

                <Cartao titulo={d.ve_dinheiro ? t('Serviços que mais rendem') : t('Serviços mais feitos')} icone="fa-star">
                    <GraficoHorizontal dados={paraGrafico(d.series.servicos)}
                        titulo={d.ve_dinheiro ? t('Receita por serviço') : t('Vezes que se fez')}
                        unidade={d.ve_dinheiro ? 'Kz' : ''}
                        vazio={t('Não houve serviços neste período.')} />
                </Cartao>
            </div>

            <Cartao titulo={t('Carga por mecânico')} icone="fa-user-gear"
                subtitulo={t('Ordens em aberto — quem está sobrecarregado')}>
                <GraficoHorizontal dados={paraGrafico(d.series.mecanicos)} titulo={t('Ordens em aberto')}
                    unidade="" vazio={t('Não há ordens em aberto.')} />
            </Cartao>

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo={t('Ordens urgentes')} icone="fa-triangle-exclamation">
                    {d.urgentes.length === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-400">{t('Não há nenhuma ordem urgente.')}</p>
                    ) : (
                        <ul className="space-y-2">
                            {d.urgentes.map((o, i) => <Urgente key={o.id} o={o} i={i} />)}
                        </ul>
                    )}
                </Cartao>

                <Cartao titulo={t('Documentos a caducar')} icone="fa-file-circle-exclamation"
                    subtitulo={t('Nos próximos 30 dias, ou já passados')}>
                    {d.documentos_a_caducar.length === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-400">{t('Está tudo em dia.')}</p>
                    ) : (
                        <ul className="space-y-2">
                            {d.documentos_a_caducar.map((v, i) => <ViaturaACaducar key={v.id} v={v} i={i} />)}
                        </ul>
                    )}
                </Cartao>
            </div>

            {/* OF-14: o que os clientes dizem do serviço. */}
            {d.satisfacao && <SatisfacaoNoPainel s={d.satisfacao} />}

            {d.ve_dinheiro && d.top_servicos.length > 0 && (
                <Cartao titulo={t('Os cinco serviços do período')} icone="fa-ranking-star">
                    <ul className="divide-y divide-slate-100">
                        {d.top_servicos.map((s, i) => (
                            <li key={s.nome} className="entra flex items-center justify-between gap-3 py-2"
                                style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                <span className="min-w-0">
                                    <span className="block truncate font-semibold text-slate-800">{s.nome}</span>
                                    <span className="block text-xs text-slate-400">
                                        {t(':n vez(es)', { n: s.vezes.toLocaleString(etiquetaIntl()) })}
                                    </span>
                                </span>
                                <span className="flex-none font-semibold tabular-nums text-emerald-700">
                                    {kz(s.receita ?? 0)} <span className="text-xs font-normal text-slate-400">Kz</span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </Cartao>
            )}
        </div>
    );
}

/* ─── As peças ──────────────────────────────────────────────────────── */

/**
 * A SATISFAÇÃO DOS CLIENTES (OF-14): a média, quantos recomendam, a
 * distribuição das estrelas e os últimos comentários — o mau primeiro a ver.
 */
function SatisfacaoNoPainel({ s }: { s: SatisfacaoDosClientes }) {
    const maior = Math.max(1, ...s.estrelas.map((e) => e.quantos));

    return (
        <Cartao titulo={t('Satisfação dos clientes')} icone="fa-star" subtitulo={t(':r respostas de :e pedidos no período', { r: s.respostas, e: s.enviados })}>
            {s.respostas === 0 ? (
                <p className="py-6 text-center text-sm text-slate-400">{t('Ainda sem avaliações neste período. O link segue quando a viatura é entregue.')}</p>
            ) : (
                <div className="grid gap-5 lg:grid-cols-[14rem_1fr_1.4fr]">
                    <div className="flex flex-col items-center justify-center gap-1 text-center">
                        <span className="text-5xl font-extrabold tabular-nums text-slate-900">{(s.media ?? 0).toLocaleString(etiquetaIntl(), { minimumFractionDigits: 1 })}</span>
                        <Estrelas nota={Math.round(s.media ?? 0)} tamanho="text-xl" />
                        {s.recomendam !== null && (
                            <span className={cls('mt-2 rounded-full px-2.5 py-1 text-xs font-bold', s.recomendam >= 70 ? 'bg-emerald-100 text-emerald-700' : s.recomendam >= 40 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-700')}>
                                <i className="fas fa-thumbs-up mr-1" aria-hidden="true" />{t(':p% recomendam', { p: s.recomendam })}
                            </span>
                        )}
                    </div>
                    <ul className="space-y-1.5 self-center">
                        {s.estrelas.map((e, i) => (
                            <li key={e.estrelas} className="entra flex items-center gap-2 text-xs" style={{ '--i': i } as React.CSSProperties}>
                                <span className="w-6 text-right font-bold tabular-nums text-slate-600">{e.estrelas}<i className="fas fa-star ml-0.5 text-[9px] text-amber-400" aria-hidden="true" /></span>
                                <span className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                    <span className={cls('block h-full rounded-full transition-all duration-700', e.estrelas >= 4 ? 'bg-emerald-500' : e.estrelas === 3 ? 'bg-amber-400' : 'bg-red-500')} style={{ width: `${(e.quantos / maior) * 100}%` }} />
                                </span>
                                <span className="w-6 tabular-nums text-slate-500">{e.quantos}</span>
                            </li>
                        ))}
                    </ul>
                    <ul className="space-y-2">
                        {s.ultimos.map((u, i) => (
                            <li key={`${u.ordem_id}-${i}`} className={cls('entra border-l-4 bg-slate-50 p-2.5', RAIO, u.nota <= 2 ? 'border-red-500' : u.nota === 3 ? 'border-amber-400' : 'border-emerald-500')}
                                style={{ '--i': i } as React.CSSProperties}>
                                <div className="flex flex-wrap items-center gap-2 text-xs">
                                    <Estrelas nota={u.nota} tamanho="text-[11px]" />
                                    <span className="font-semibold text-slate-700">{u.matricula ?? '—'}</span>
                                    <span className="text-slate-500">{u.dono}{u.mecanico ? ` · ${u.mecanico}` : ''}</span>
                                    <span className="ml-auto text-slate-400">{data(u.quando)}</span>
                                </div>
                                {u.comentario && <p className="mt-1 text-sm italic text-slate-600">«{u.comentario}»</p>}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </Cartao>
    );
}

/**
 * OS ESTADOS EM LINHAS, e não numa rosca.
 *
 * O painel em Blade usava um donut do Chart.js, que empurra os nomes para uma
 * legenda ao lado e obriga a saltar entre a cor e o texto para ler cada fatia.
 * Em linha, cada estado lê-se de uma vez — e a cor continua a ser a de sempre,
 * porque «concluída» tem de ser verde em todo o produto.
 */
function EstadosDaOficina({ serie }: { serie: Serie }) {
    const total = serie.valores.reduce((a, b) => a + b, 0);

    if (total === 0) {
        return <p className="py-6 text-center text-sm text-slate-400">{t('Não entraram ordens neste período.')}</p>;
    }

    return (
        <ul className="space-y-2">
            {serie.etiquetas.map((rotulo, i) => {
                const valor = serie.valores[i] ?? 0;
                const chave = serie.chaves?.[i] ?? '';
                const parte = Math.round((valor / total) * 100);

                return (
                    <li key={rotulo} className="entra" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                        <div className="flex items-center justify-between gap-3 text-sm">
                            <span className="flex min-w-0 items-center gap-2">
                                <span className={cls('h-2.5 w-2.5 flex-none rounded-full', COR_DO_ESTADO[chave] ?? 'bg-slate-300')} aria-hidden="true" />
                                <span className="truncate font-medium text-slate-700">{rotulo}</span>
                            </span>
                            <span className="flex-none font-semibold tabular-nums text-slate-800">
                                {valor.toLocaleString(etiquetaIntl())}
                                <span className="ml-1.5 text-xs font-normal text-slate-400">{parte}%</span>
                            </span>
                        </div>
                        <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div className={cls('h-full rounded-full transition-all duration-500', COR_DO_ESTADO[chave] ?? 'bg-slate-300')}
                                style={{ width: `${parte}%` }} />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

function Urgente({ o, i }: { o: OrdemUrgente; i: number }) {
    return (
        <li
            className={cls('entra flex items-center justify-between gap-3 border-l-4 border-red-500 bg-red-50/70 p-3', RAIO)}
            style={{ '--i': Math.min(i, 12) } as React.CSSProperties}
        >
            <span className="min-w-0">
                <span className="block truncate font-bold text-slate-900">{o.numero}</span>
                <span className="block truncate text-sm text-slate-600">
                    {o.matricula ?? '—'}{o.dono ? ` · ${o.dono}` : ''}
                </span>
            </span>
            <span className="flex flex-none flex-col items-end gap-1">
                <Etiqueta cor="perigo" icone="fa-bolt">{t('Urgente')}</Etiqueta>
                <span className="text-xs text-slate-500">{o.estado_rotulo}</span>
            </span>
        </li>
    );
}

/**
 * UMA VIATURA E OS DOCUMENTOS QUE LHE ESTÃO A CADUCAR — dizendo QUAL.
 *
 * O ecrã em Blade juntava as três datas num parágrafo corrido, sem separar o
 * que já passou do que está quase: o seguro caducado lia-se igual à inspecção
 * que caduca daqui a três semanas.
 */
function ViaturaACaducar({ v, i }: { v: ViaturaComDocumentos; i: number }) {
    return (
        <li
            className={cls('entra border-l-4 border-amber-500 bg-amber-50/70 p-3', RAIO)}
            style={{ '--i': Math.min(i, 12) } as React.CSSProperties}
        >
            <div className="flex items-baseline justify-between gap-3">
                <span className="font-bold text-slate-900">{v.matricula}</span>
                <span className="truncate text-sm text-slate-600">{v.viatura}</span>
            </div>

            <ul className="mt-1.5 flex flex-wrap gap-1.5">
                {v.documentos.map((doc) => (
                    <li key={doc.nome}>
                        <span className={cls(
                            'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums',
                            doc.dias < 0 ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800',
                        )}>
                            <i className={cls('fas text-[10px]', doc.dias < 0 ? 'fa-triangle-exclamation' : 'fa-clock')} aria-hidden="true" />
                            {doc.nome}: {data(doc.quando)}
                            <span className="font-bold">
                                {doc.dias < 0 ? t('caducou') : t('faltam :n dias', { n: doc.dias })}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </li>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6', FOCO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o painel da oficina')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
