import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    calendario,
    type BarraDoCalendario,
    type FiltrosDoCalendario,
    type GrelhaDoCalendario,
} from '@/api/hotel';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O CALENDÁRIO — a planta do hotel ao longo do tempo.
 *
 * Uma linha por quarto, uma coluna por dia, e cada estada é uma barra que
 * atravessa as noites que ocupa. É o ecrã onde se vê o que a lista não mostra:
 * os BURACOS — as noites em que um quarto fica por vender.
 *
 * O QUE ESTA MIGRAÇÃO CORRIGE:
 *
 *  • Todas as barras diziam «Sem hóspede»: liam a ficha antiga, que está
 *    vazia.
 *  • Uma reserva SEM QUARTO aparecia em TODOS os quartos do seu tipo — cinco
 *    barras para uma estada, e cinco quartos a parecer vendidos. Agora ficam
 *    numa faixa própria, que é o que elas são: procura por atribuir.
 *  • Arrastar uma barra gravava sem perguntar se o destino estava livre. O
 *    servidor recusa, e diz porquê.
 *  • Os tipos de quarto eram todos da mesma cor: o ecrã lia uma coluna
 *    `color` que nunca existiu.
 */

const COR_DA_BARRA: Record<string, string> = {
    pending: 'bg-gradient-to-r from-amber-400 to-amber-500 text-amber-950',
    confirmed: 'bg-gradient-to-r from-indigo-500 to-violet-500 text-white',
    checked_in: 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white',
    checked_out: 'bg-gradient-to-r from-slate-400 to-slate-500 text-white',
    no_show: 'bg-gradient-to-r from-orange-400 to-red-400 text-white',
};

const COR_DO_ESTADO: Record<string, 'neutra' | 'primaria' | 'aviso' | 'bom' | 'perigo'> = {
    pending: 'aviso',
    confirmed: 'primaria',
    checked_in: 'bom',
    checked_out: 'neutra',
    cancelled: 'perigo',
    no_show: 'aviso',
};

/** A largura de cada dia. Fixa, para o cabeçalho e as barras baterem certo. */
const LARGURA_DO_DIA = 40;

const hoje = () => new Date().toISOString().slice(0, 10);

function andar(dia: string, vista: 'semana' | 'mes', quantos: number): string {
    const d = new Date(`${dia}T12:00:00`);

    if (vista === 'semana') {
        d.setDate(d.getDate() + 7 * quantos);
    } else {
        d.setMonth(d.getMonth() + quantos, 1);
    }

    return d.toISOString().slice(0, 10);
}

export default function Calendario() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDoCalendario>({ dia: hoje(), vista: 'mes' });
    const [aVer, porAVer] = useState<BarraDoCalendario | null>(null);
    const [aMover, porAMover] = useState<BarraDoCalendario | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    const opcoes = useQuery({ queryKey: ['hotel', 'calendario', 'opcoes'], queryFn: calendario.opcoes, staleTime: 5 * 60_000 });

    const grelha = useQuery({
        queryKey: ['hotel', 'calendario', 'grelha', filtros],
        queryFn: () => calendario.grelha(filtros),
        placeholderData: keepPreviousData,
        enabled: opcoes.isSuccess,
    });

    const mover = useMutation({
        mutationFn: ({ id, quarto, dia }: { id: number; quarto: number; dia: string }) => calendario.mover(id, quarto, dia),
        onSuccess: (r) => {
            void cache.invalidateQueries({ queryKey: ['hotel', 'calendario'] });
            porAMover(null); porAVer(null); porRecado(r.message);
        },
    });

    if (opcoes.isPending) return <Carregando linhas={10} />;
    if (opcoes.isError) return <Falhou erro={opcoes.error} />;

    const o = opcoes.data;
    const g = grelha.data;
    const vista = filtros.vista ?? 'mes';
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Calendário')}
                subtitulo={t('Onde há buracos, e quem os pode encher')}
                icone="fa-calendar-days"
                cor="primaria"
                accoes={
                    <>
                        <span className={cls('flex items-center gap-1 bg-white/20 p-1', RAIO)}>
                            <button type="button" aria-label={t('Período anterior')}
                                onClick={() => porFiltros((f) => ({ ...f, dia: andar(f.dia ?? hoje(), vista, -1) }))}
                                className={cls('px-2.5 py-1.5 transition-all hover:bg-white/20 active:scale-95', RAIO, FOCO)}>
                                <i className="fas fa-chevron-left" aria-hidden="true" />
                            </button>
                            <span className="min-w-40 text-center text-sm font-semibold">
                                {g ? t(':de a :ate', { de: data(g.periodo.de), ate: data(g.periodo.ate) }) : '…'}
                            </span>
                            <button type="button" aria-label={t('Período seguinte')}
                                onClick={() => porFiltros((f) => ({ ...f, dia: andar(f.dia ?? hoje(), vista, 1) }))}
                                className={cls('px-2.5 py-1.5 transition-all hover:bg-white/20 active:scale-95', RAIO, FOCO)}>
                                <i className="fas fa-chevron-right" aria-hidden="true" />
                            </button>
                        </span>

                        <button type="button" onClick={() => porFiltros((f) => ({ ...f, dia: hoje() }))}
                            className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-crosshairs" aria-hidden="true" />
                            {t('Hoje')}
                        </button>

                        <a href="/hotel/reservations" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-list" aria-hidden="true" />
                            {t('Lista')}
                        </a>
                    </>
                }
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} className={cls('p-1', FOCO, RAIO)} aria-label={t('Fechar')}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                    </button>
                </div>
            )}

            <AvisoDeErro erro={mover.error} />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <CartaoNumero aspecto="claro" rotulo={t('Ocupação hoje')} tom="verde" icone="fa-chart-pie"
                    nota={g ? t(':n de :total quartos', { n: numero(g.resumo.ocupados), total: numero(g.resumo.quartos) }) : undefined}
                    valor={g ? `${g.resumo.ocupacao}%` : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Entram hoje')} tom="azul" icone="fa-right-to-bracket"
                    valor={g ? numero(g.resumo.entram_hoje) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Saem hoje')} tom="laranja" icone="fa-right-from-bracket"
                    valor={g ? numero(g.resumo.saem_hoje) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Reservas do período')} tom="indigo" icone="fa-calendar-check"
                    valor={g ? numero(g.resumo.reservas) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Receita do período')} tom="roxo" icone="fa-money-bill-wave"
                    valor={g ? `${kz(g.resumo.receita)} Kz` : '—'} />
            </div>

            <div className={cls(CARTAO, 'p-4')}>
                <div className="mb-4 flex flex-wrap gap-2" role="tablist" aria-label={t('Vista')}>
                    {([['semana', t('Semana'), 'fa-calendar-week'], ['mes', t('Mês'), 'fa-calendar-days']] as const).map(([qual, rotulo, icone]) => (
                        <button
                            key={qual}
                            type="button"
                            role="tab"
                            aria-selected={vista === qual}
                            onClick={() => porFiltros((f) => ({ ...f, vista: qual }))}
                            className={cls(
                                'inline-flex items-center gap-2 border px-3 py-2 text-sm font-semibold transition-all duration-200',
                                'hover:-translate-y-0.5 active:translate-y-0', RAIO, FOCO,
                                vista === qual
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm'
                                    : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700',
                            )}
                        >
                            <i className={cls('fas', icone)} aria-hidden="true" />
                            {rotulo}
                        </button>
                    ))}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Campo etiqueta={t('Tipo de quarto')}>
                        <select value={filtros.tipo_de_quarto ?? ''} className={entrada}
                            onChange={(e) => porFiltros((f) => ({ ...f, tipo_de_quarto: e.target.value }))}>
                            <option value="">{t('Todos')}</option>
                            {o.tipos_de_quarto.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Estado')}>
                        <select value={filtros.estado ?? ''} className={entrada}
                            onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value }))}>
                            <option value="">{t('Todos')}</option>
                            {o.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Ir para')}>
                        <input type="date" value={filtros.dia ?? hoje()} className={cls(entrada, 'tabular-nums')}
                            onChange={(e) => porFiltros((f) => ({ ...f, dia: e.target.value || hoje() }))} />
                    </Campo>
                </div>

                <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    {o.estados.filter((e) => e.valor !== 'cancelled').map((e) => (
                        <span key={e.valor} className="inline-flex items-center gap-1.5">
                            <span className={cls('h-2.5 w-5 rounded-sm', COR_DA_BARRA[e.valor] ?? 'bg-slate-300')} aria-hidden="true" />
                            {e.rotulo}
                        </span>
                    ))}
                </div>
            </div>

            {grelha.isPending ? (
                <Carregando linhas={10} />
            ) : grelha.isError ? (
                <Falhou erro={grelha.error} />
            ) : g && (
                <div className={cls('space-y-4', grelha.isFetching && 'opacity-70 transition-opacity')}>
                    {/* AS ESTADAS SEM QUARTO, numa faixa própria. São procura por
                        atribuir, e não cinco quartos vendidos. */}
                    {g.por_atribuir.length > 0 && (
                        <section className={cls(CARTAO, 'border-l-4 border-l-amber-400 p-4')}>
                            <h2 className="mb-3 flex items-center gap-2 text-sm font-bold text-amber-800">
                                <i className="fas fa-circle-exclamation" aria-hidden="true" />
                                {t('Por atribuir quarto')}
                                <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs tabular-nums text-amber-700">
                                    {numero(g.por_atribuir.length)}
                                </span>
                            </h2>
                            <ul className="flex flex-wrap gap-2">
                                {g.por_atribuir.map((b) => (
                                    <li key={b.id}>
                                        <button type="button" onClick={() => porAVer(b)}
                                            className={cls(
                                                'border border-amber-200 bg-amber-50 px-3 py-2 text-left text-xs transition-all duration-200',
                                                'hover:-translate-y-0.5 hover:shadow-sm', RAIO, FOCO,
                                            )}>
                                            <span className="block font-bold text-amber-900">{b.hospede}</span>
                                            <span className="block text-amber-700">
                                                {b.tipo_de_quarto ?? '—'} · {data(b.entrada)} → {data(b.saida)}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <div className={cls(CARTAO, 'overflow-hidden')}>
                        {g.quartos.length === 0 ? (
                            <SemNada
                                icone="fa-door-open"
                                titulo={t('Nenhum quarto')}
                                frase={t('Sem quartos activos não há calendário para desenhar.')}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <div style={{ minWidth: 160 + g.dias.length * LARGURA_DO_DIA }}>
                                    {/* Os dias */}
                                    <div className="flex border-b border-slate-200 bg-slate-50">
                                        <div className="w-40 flex-none border-r border-slate-200 px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            {t('Quarto')}
                                        </div>
                                        {g.dias.map((d) => (
                                            <div key={d.dia}
                                                style={{ width: LARGURA_DO_DIA }}
                                                className={cls(
                                                    'flex-none border-r border-slate-100 py-1.5 text-center',
                                                    d.hoje && 'bg-indigo-100',
                                                    d.fim_de_semana && !d.hoje && 'bg-slate-100',
                                                )}>
                                                <span className={cls('block text-[10px] uppercase', d.hoje ? 'text-indigo-700' : 'text-slate-400')}>
                                                    {d.nome}
                                                </span>
                                                <span className={cls('block text-sm font-bold tabular-nums', d.hoje ? 'text-indigo-700' : 'text-slate-600')}>
                                                    {d.numero}
                                                </span>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Os quartos */}
                                    {g.quartos.map((q, i) => (
                                        <div key={q.id} className="entra flex border-b border-slate-100 last:border-b-0"
                                            style={{ '--i': Math.min(i, 20) } as React.CSSProperties}>
                                            <div className="w-40 flex-none border-r border-slate-200 px-3 py-2">
                                                <span className="flex items-center gap-2">
                                                    <span className="h-3 w-1.5 flex-none rounded-full" style={{ background: q.cor }} aria-hidden="true" />
                                                    <span className="min-w-0">
                                                        <span className="block truncate text-sm font-bold text-slate-800">{q.numero}</span>
                                                        <span className="block truncate text-[11px] text-slate-400">{q.tipo}</span>
                                                    </span>
                                                </span>
                                            </div>

                                            <div className="relative flex-1" style={{ height: 44 }}>
                                                {/* As grades dos dias, por baixo. */}
                                                <div className="absolute inset-0 flex" aria-hidden="true">
                                                    {g.dias.map((d) => (
                                                        <div key={d.dia} style={{ width: LARGURA_DO_DIA }}
                                                            className={cls(
                                                                'flex-none border-r border-slate-100',
                                                                d.hoje && 'bg-indigo-50',
                                                                d.fim_de_semana && !d.hoje && 'bg-slate-50',
                                                            )} />
                                                    ))}
                                                </div>

                                                {q.barras.map((b) => (
                                                    <button
                                                        key={b.id}
                                                        type="button"
                                                        onClick={() => porAVer(b)}
                                                        title={t(':hospede · :entrada a :saida', {
                                                            hospede: b.hospede, entrada: data(b.entrada), saida: data(b.saida),
                                                        })}
                                                        style={{
                                                            left: b.inicio * LARGURA_DO_DIA + 2,
                                                            width: b.largura * LARGURA_DO_DIA - 4,
                                                        }}
                                                        className={cls(
                                                            'absolute top-1.5 flex h-8 items-center overflow-hidden px-2 text-[11px] font-semibold shadow-sm',
                                                            'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md',
                                                            RAIO, FOCO,
                                                            COR_DA_BARRA[b.estado] ?? 'bg-slate-400 text-white',
                                                            b.vem_de_tras && 'rounded-l-none',
                                                            b.segue_para_a_frente && 'rounded-r-none',
                                                        )}
                                                    >
                                                        {b.vem_de_tras && <i className="fas fa-caret-left mr-1 flex-none opacity-70" aria-hidden="true" />}
                                                        <span className="truncate">{b.hospede}</span>
                                                        {b.segue_para_a_frente && <i className="fas fa-caret-right ml-auto flex-none opacity-70" aria-hidden="true" />}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {aVer && (
                <FichaDaBarra
                    barra={aVer}
                    podeEditar={o.permissoes.pode_editar}
                    aoFechar={() => porAVer(null)}
                    aoMover={() => porAMover(aVer)}
                />
            )}

            <Mover
                barra={aMover}
                quartos={g?.quartos ?? []}
                aTrabalhar={mover.isPending}
                erro={mover.error}
                aoFechar={() => porAMover(null)}
                aoMover={(quarto, dia) => aMover && mover.mutate({ id: aMover.id, quarto, dia })}
            />
        </div>
    );
}

/* ─── As peças ──────────────────────────────────────────────────────── */

function FichaDaBarra({ barra, podeEditar, aoFechar, aoMover }: {
    barra: BarraDoCalendario;
    podeEditar: boolean;
    aoFechar: () => void;
    aoMover: () => void;
}) {
    // Só se move o que ainda não entrou: mudar as datas de uma estada a
    // decorrer reescreve o que já aconteceu.
    const podeMover = podeEditar && ['pending', 'confirmed'].includes(barra.estado);

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={barra.hospede}
            subtitulo={barra.numero}
            icone="fa-calendar-check"
            cor="primaria"
            largura="md"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            <div className="space-y-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Etiqueta cor={COR_DO_ESTADO[barra.estado] ?? 'neutra'}>{barra.estado_rotulo}</Etiqueta>
                    <Etiqueta cor={barra.estado_de_pagamento === 'paid' ? 'bom' : 'aviso'}>
                        {barra.estado_de_pagamento === 'paid' ? t('Pago') : t('Por receber')}
                    </Etiqueta>
                </div>

                <dl className={cls('space-y-1.5 border border-slate-200 p-4', RAIO)}>
                    <Dado rotulo={t('Check-in')} valor={data(barra.entrada)} />
                    <Dado rotulo={t('Check-out')} valor={data(barra.saida)} />
                    <Dado rotulo={t('Noites')} valor={String(barra.noites)} />
                    <Dado rotulo={t('Pessoas')}
                        valor={t(':a adulto(s) + :c criança(s)', { a: barra.adultos, c: barra.criancas })} />
                    {barra.tipo_de_quarto && <Dado rotulo={t('Tipo de quarto')} valor={barra.tipo_de_quarto} />}
                    <div className="flex items-baseline justify-between gap-3 border-t border-slate-200 pt-1.5 text-sm">
                        <dt className="font-bold text-slate-700">{t('Total')}</dt>
                        <dd className="text-lg font-bold tabular-nums text-slate-900">{kz(barra.total)} Kz</dd>
                    </div>
                </dl>

                <div className="flex flex-wrap gap-2">
                    <a href={`/hotel/reservations?procura=${encodeURIComponent(barra.numero)}`}
                        className={cls('inline-flex items-center gap-2 bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition-all hover:-translate-y-0.5 hover:bg-indigo-700', RAIO, FOCO)}>
                        <i className="fas fa-up-right-from-square" aria-hidden="true" />
                        {t('Abrir na lista')}
                    </a>
                    <a href={`/hotel/reservations/${barra.id}/folio`}
                        className={cls('inline-flex items-center gap-2 border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition-all hover:-translate-y-0.5 hover:border-slate-300', RAIO, FOCO)}>
                        <i className="fas fa-list-ul" aria-hidden="true" />
                        {t('Folio')}
                    </a>
                    {podeMover && (
                        <Botao icone="fa-arrows-up-down-left-right" onClick={aoMover}>{t('Mover')}</Botao>
                    )}
                </div>
            </div>
        </Modal>
    );
}

function Mover({ barra, quartos, aTrabalhar, erro, aoFechar, aoMover }: {
    barra: BarraDoCalendario | null;
    quartos: GrelhaDoCalendario['quartos'];
    aTrabalhar: boolean;
    erro: unknown;
    aoFechar: () => void;
    aoMover: (quarto: number, dia: string) => void;
}) {
    const [quarto, porQuarto] = useState('');
    const [dia, porDia] = useState('');
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <Modal
            aberto={barra !== null}
            aoFechar={aoFechar}
            titulo={t('Mover reserva')}
            subtitulo={barra ? t(':numero · :hospede', { numero: barra.numero, hospede: barra.hospede }) : undefined}
            icone="fa-arrows-up-down-left-right"
            cor="primaria"
            largura="sm"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={aTrabalhar}
                        disabled={!quarto || !dia}
                        onClick={() => aoMover(Number(quarto), dia)}>
                        {t('Mover')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erro} />

            <p className="mb-3 text-sm text-slate-600">
                {barra && t('A estada mantém as :n noite(s). O check-out anda com o check-in.', { n: barra.noites })}
            </p>

            <div className="grid gap-3">
                <Campo etiqueta={t('Quarto')} obrigatorio erro={daApi?.erros.quarto}>
                    <select value={quarto} className={entrada} onChange={(e) => porQuarto(e.target.value)}>
                        <option value="">{t('Escolha…')}</option>
                        {quartos.map((q) => (
                            <option key={q.id} value={q.id}>{t(':n — :tipo', { n: q.numero, tipo: q.tipo })}</option>
                        ))}
                    </select>
                </Campo>
                <Campo etiqueta={t('Novo check-in')} obrigatorio erro={daApi?.erros.dia}>
                    <input type="date" value={dia} className={cls(entrada, 'tabular-nums')}
                        onChange={(e) => porDia(e.target.value)} />
                </Campo>
            </div>
        </Modal>
    );
}

function Dado({ rotulo, valor }: { rotulo: string; valor?: string | null }) {
    if (!valor) return null;

    return (
        <div className="flex items-baseline justify-between gap-3 text-sm">
            <dt className="text-slate-500">{rotulo}</dt>
            <dd className="font-semibold tabular-nums text-slate-800">{valor}</dd>
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o calendário')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
