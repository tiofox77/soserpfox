import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { tarifas, type OpcoesDasTarifas, type TarifaEspecial, type TarifaPorDia } from '@/api/hotel';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { t, tPartes } from '@/i18n';

/**
 * AS TARIFAS — quanto custa uma noite, e porquê.
 *
 * Três camadas, por esta ordem: a ÉPOCA muda o preço base de um período
 * (alta/baixa); o DIA DA SEMANA multiplica-o (a sexta custa mais); a TARIFA
 * ESPECIAL de um dia concreto substitui tudo.
 *
 * O CALENDÁRIO É A PRIMEIRA ABA de propósito: é a única maneira de ver o que
 * as três camadas fazem JUNTAS, e é a pergunta que se faz a este ecrã —
 * «quanto custa a noite de sábado?».
 *
 * A CONTA NUNCA FOI APLICADA A NADA: vivia como método estático de um
 * componente Livewire e o único sítio que a chamava era o calendário do seu
 * próprio ecrã. Definir uma época alta não mudava uma reserva. Agora o
 * formulário da reserva pergunta o preço a esta mesma conta.
 */

const ABAS = [
    { chave: 'calendario', rotulo: () => t('Calendário de preços'), icone: 'fa-calendar-days' },
    { chave: 'epocas', rotulo: () => t('Épocas'), icone: 'fa-sun' },
    { chave: 'dias', rotulo: () => t('Dias da semana'), icone: 'fa-calendar-week' },
    { chave: 'especiais', rotulo: () => t('Dias especiais'), icone: 'fa-star' },
] as const;

type Aba = typeof ABAS[number]['chave'];

const hoje = () => new Date().toISOString().slice(0, 10);

function andarMes(dia: string, quantos: number): string {
    const d = new Date(`${dia}T12:00:00`);

    d.setMonth(d.getMonth() + quantos, 1);

    return d.toISOString().slice(0, 10);
}

export default function Tarifas() {
    const [aba, porAba] = useState<Aba>('calendario');
    const [recado, porRecado] = useState('');

    const opcoes = useQuery({ queryKey: ['hotel', 'tarifas', 'opcoes'], queryFn: tarifas.opcoes, staleTime: 5 * 60_000 });

    if (opcoes.isPending) return <Carregando linhas={10} />;
    if (opcoes.isError) return <Falhou erro={opcoes.error} />;

    const o = opcoes.data;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Tarifas')}
                subtitulo={t('Quanto custa uma noite, e porquê')}
                icone="fa-tags"
                cor="roxo"
                accoes={
                    <a href="/hotel/room-types" className={ACCAO_DA_FAIXA}>
                        <i className="fas fa-bed" aria-hidden="true" />
                        {t('Tipos de Quarto')}
                    </a>
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

            <div className={cls(CARTAO, 'p-4')}>
                <div className="flex flex-wrap gap-2" role="tablist" aria-label={t('Tarifas')}>
                    {ABAS.map((x) => (
                        <button
                            key={x.chave}
                            type="button"
                            role="tab"
                            aria-selected={aba === x.chave}
                            onClick={() => porAba(x.chave)}
                            className={cls(
                                'inline-flex items-center gap-2 border px-3 py-2 text-sm font-semibold transition-all duration-200',
                                'hover:-translate-y-0.5 active:translate-y-0', RAIO, FOCO,
                                aba === x.chave
                                    ? 'border-purple-500 bg-purple-50 text-purple-700 shadow-sm'
                                    : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700',
                            )}
                        >
                            <i className={cls('fas', x.icone)} aria-hidden="true" />
                            {x.rotulo()}
                        </button>
                    ))}
                </div>

                <p className="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                    {t('A época muda o preço base do período; o dia da semana multiplica-o; a tarifa de um dia concreto substitui tudo.')}
                </p>
            </div>

            {aba === 'calendario' && <CalendarioDePrecos o={o} />}
            {aba === 'epocas' && <Epocas />}
            {aba === 'dias' && <PorDia o={o} aoGravar={porRecado} />}
            {aba === 'especiais' && <Especiais o={o} aoGravar={porRecado} />}
        </div>
    );
}

/* ─── O calendário ──────────────────────────────────────────────────── */

function CalendarioDePrecos({ o }: { o: OpcoesDasTarifas }) {
    const [mes, porMes] = useState(hoje());
    const [tipo, porTipo] = useState('');

    const calendario = useQuery({
        queryKey: ['hotel', 'tarifas', 'calendario', mes, tipo],
        queryFn: () => tarifas.calendario(mes, tipo || undefined),
        placeholderData: keepPreviousData,
    });

    const c = calendario.data;

    return (
        <div className="space-y-4">
            <div className={cls(CARTAO, 'flex flex-wrap items-end gap-3 p-4')}>
                <span className={cls('flex items-center gap-1 border border-slate-200 p-1', RAIO)}>
                    <button type="button" aria-label={t('Mês anterior')}
                        onClick={() => porMes((m) => andarMes(m, -1))}
                        className={cls('px-2.5 py-1.5 text-slate-500 transition-all hover:bg-slate-100 active:scale-95', RAIO, FOCO)}>
                        <i className="fas fa-chevron-left" aria-hidden="true" />
                    </button>
                    <input type="month" value={mes.slice(0, 7)}
                        aria-label={t('Mês')}
                        onChange={(e) => porMes(e.target.value ? `${e.target.value}-01` : hoje())}
                        className={cls('border-0 bg-transparent text-center text-sm font-semibold tabular-nums text-slate-700', FOCO, RAIO)} />
                    <button type="button" aria-label={t('Mês seguinte')}
                        onClick={() => porMes((m) => andarMes(m, 1))}
                        className={cls('px-2.5 py-1.5 text-slate-500 transition-all hover:bg-slate-100 active:scale-95', RAIO, FOCO)}>
                        <i className="fas fa-chevron-right" aria-hidden="true" />
                    </button>
                </span>

                <Campo etiqueta={t('Tipo de quarto')} className="min-w-52">
                    <select value={tipo} className={entrada} onChange={(e) => porTipo(e.target.value)}>
                        <option value="">{t('Todos')}</option>
                        {o.tipos_de_quarto.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>
            </div>

            {calendario.isPending ? (
                <Carregando linhas={8} />
            ) : calendario.isError ? (
                <Falhou erro={calendario.error} />
            ) : c && (
                <div className={cls(CARTAO, 'overflow-hidden', calendario.isFetching && 'opacity-70 transition-opacity')}>
                    {c.tipos.length === 0 ? (
                        <SemNada
                            icone="fa-bed"
                            titulo={t('Nenhum tipo de quarto')}
                            frase={t('Sem tipos de quarto activos não há preços para calcular.')}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <div style={{ minWidth: 180 + c.dias.length * 56 }}>
                                <div className="flex border-b border-slate-200 bg-slate-50">
                                    <div className="w-44 flex-none border-r border-slate-200 px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                                        {t('Tipo de quarto')}
                                    </div>
                                    {c.dias.map((d) => (
                                        <div key={d.dia} style={{ width: 56 }}
                                            className={cls('flex-none border-r border-slate-100 py-1.5 text-center',
                                                d.hoje && 'bg-purple-100', d.fim_de_semana && !d.hoje && 'bg-slate-100')}>
                                            <span className={cls('block text-[10px] uppercase', d.hoje ? 'text-purple-700' : 'text-slate-400')}>
                                                {d.nome}
                                            </span>
                                            <span className={cls('block text-sm font-bold tabular-nums', d.hoje ? 'text-purple-700' : 'text-slate-600')}>
                                                {d.numero}
                                            </span>
                                        </div>
                                    ))}
                                </div>

                                {c.tipos.map((tp, i) => (
                                    <div key={tp.id} className="entra flex border-b border-slate-100 last:border-b-0"
                                        style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                        <div className="w-44 flex-none border-r border-slate-200 px-3 py-2">
                                            <span className="block truncate text-sm font-bold text-slate-800">{tp.nome}</span>
                                            <span className="block text-[11px] tabular-nums text-slate-400">
                                                {t('base: :v Kz', { v: kz(tp.base) })}
                                            </span>
                                        </div>

                                        {c.dias.map((d) => {
                                            const preco = tp.dias[d.dia] ?? tp.base;
                                            // O QUE MUDOU EM RELAÇÃO AO BASE é o que
                                            // interessa: verde mais barato, âmbar mais caro.
                                            const acima = preco > tp.base;
                                            const abaixo = preco < tp.base;

                                            return (
                                                <div key={d.dia} style={{ width: 56 }}
                                                    title={t(':dia — :v Kz', { dia: data(d.dia), v: kz(preco) })}
                                                    className={cls(
                                                        'flex-none border-r border-slate-100 px-1 py-2 text-center text-[11px] font-semibold tabular-nums',
                                                        d.hoje && 'bg-purple-50',
                                                        d.fim_de_semana && !d.hoje && 'bg-slate-50',
                                                        acima ? 'text-amber-700' : abaixo ? 'text-emerald-700' : 'text-slate-500',
                                                    )}>
                                                    {kz(preco, 0)}
                                                </div>
                                            );
                                        })}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <p className="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-slate-100 px-4 py-3 text-xs text-slate-500">
                        <span><span className="mr-1 font-bold text-amber-700">↑</span>{t('acima do base')}</span>
                        <span><span className="mr-1 font-bold text-emerald-700">↓</span>{t('abaixo do base')}</span>
                        <span className="text-slate-400">{t('Os valores estão sem imposto, como o preço base.')}</span>
                    </p>
                </div>
            )}
        </div>
    );
}

/* ─── As épocas ─────────────────────────────────────────────────────── */

function Epocas() {
    const epocas = useQuery({ queryKey: ['hotel', 'tarifas', 'epocas'], queryFn: tarifas.epocas });

    if (epocas.isPending) return <Carregando linhas={6} />;
    if (epocas.isError) return <Falhou erro={epocas.error} />;

    const lista = epocas.data.data;

    return (
        <div className={cls(CARTAO, 'overflow-hidden')}>
            {lista.length === 0 ? (
                <SemNada
                    icone="fa-sun"
                    titulo={t('Nenhuma época')}
                    frase={t('Sem épocas, todas as noites custam o preço base do tipo de quarto.')}
                    accao={
                        <a href="/hotel/seasons"
                            className={cls('inline-flex items-center gap-2 bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition-all hover:-translate-y-0.5 hover:bg-indigo-700', RAIO, FOCO)}>
                            <i className="fas fa-plus" aria-hidden="true" />
                            {t('Criar época')}
                        </a>
                    }
                />
            ) : (
                <>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <th scope="col" className="px-4 py-3 text-left">{t('Época')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Período')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Efeito')}</th>
                                    <th scope="col" className="px-4 py-3 text-right">{t('Prioridade')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Estado')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {lista.map((e, i) => (
                                    <tr key={e.id} className="entra transition-colors duration-150 hover:bg-purple-50/40"
                                        style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                        <td className="px-4 py-2.5">
                                            <span className="flex items-center gap-2">
                                                <span className="h-3 w-3 flex-none rounded-full" style={{ background: e.cor }} aria-hidden="true" />
                                                <span className="font-semibold text-slate-800">{e.nome}</span>
                                                {e.a_correr && <Etiqueta cor="bom">{t('A correr')}</Etiqueta>}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5 tabular-nums text-slate-600">
                                            {e.de ? data(e.de) : '—'} → {e.ate ? data(e.ate) : '—'}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <span className="font-bold text-slate-800">{e.efeito}</span>
                                            <span className="ml-2 text-xs text-slate-400">{e.tipo_rotulo}</span>
                                        </td>
                                        <td className="px-4 py-2.5 text-right tabular-nums text-slate-600">{e.prioridade}</td>
                                        <td className="px-4 py-2.5">
                                            <Etiqueta cor={e.activa ? 'bom' : 'neutra'}>
                                                {e.activa ? t('Activa') : t('Inactiva')}
                                            </Etiqueta>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <p className="flex items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500">
                        <span>
                            <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                            {t('Duas épocas sobre o mesmo dia: ganha a de maior prioridade.')}
                        </span>
                        <a href="/hotel/seasons"
                            className={cls('inline-flex items-center gap-1.5 font-semibold text-indigo-600 hover:underline', FOCO, RAIO)}>
                            <i className="fas fa-pen" aria-hidden="true" />
                            {t('Gerir épocas')}
                        </a>
                    </p>
                </>
            )}
        </div>
    );
}

/* ─── Os dias da semana ─────────────────────────────────────────────── */

function PorDia({ o, aoGravar }: { o: OpcoesDasTarifas; aoGravar: (m: string) => void }) {
    const cache = useQueryClient();
    const [aEditar, porAEditar] = useState<TarifaPorDia | null>(null);

    const porDia = useQuery({ queryKey: ['hotel', 'tarifas', 'por-dia'], queryFn: tarifas.porDia });

    if (porDia.isPending) return <Carregando linhas={6} />;
    if (porDia.isError) return <Falhou erro={porDia.error} />;

    const lista = porDia.data.data;

    return (
        <>
            <div className={cls(CARTAO, 'overflow-hidden')}>
                {lista.length === 0 ? (
                    <SemNada icone="fa-bed" titulo={t('Nenhum tipo de quarto')}
                        frase={t('Sem tipos de quarto activos não há tarifas para definir.')} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <th scope="col" className="px-4 py-3 text-left">{t('Tipo de quarto')}</th>
                                    {(lista[0]?.dias ?? []).map((d) => (
                                        <th key={d.dia} scope="col" className="px-2 py-3 text-center">{d.rotulo}</th>
                                    ))}
                                    <th scope="col" className="px-4 py-3 text-right">{t('Acções')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {lista.map((tp, i) => (
                                    <tr key={tp.id} className="entra transition-colors duration-150 hover:bg-purple-50/40"
                                        style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                        <td className="px-4 py-2.5">
                                            <span className="block font-semibold text-slate-800">{tp.nome}</span>
                                            <span className="block text-xs tabular-nums text-slate-400">
                                                {t('base: :v Kz', { v: kz(tp.base) })}
                                            </span>
                                        </td>
                                        {tp.dias.map((d) => (
                                            <td key={d.dia} className="px-2 py-2.5 text-center">
                                                <span className={cls('block text-sm font-bold tabular-nums',
                                                    d.modificador > 1 ? 'text-amber-700' : d.modificador < 1 ? 'text-emerald-700' : 'text-slate-400')}>
                                                    ×{d.modificador}
                                                </span>
                                                <span className="block text-[10px] tabular-nums text-slate-400">
                                                    {kz(tp.base * d.modificador, 0)}
                                                </span>
                                            </td>
                                        ))}
                                        <td className="px-4 py-2.5 text-right">
                                            {o.permissoes.pode_editar && (
                                                <button type="button" onClick={() => porAEditar(tp)}
                                                    title={t('Editar')} aria-label={t('Editar: :nome', { nome: tp.nome })}
                                                    className={cls('p-2 text-slate-400 transition-all hover:scale-110 hover:text-purple-600', RAIO, FOCO)}>
                                                    <i className="fas fa-pen" aria-hidden="true" />
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {aEditar && (
                <EditarPorDia
                    tipo={aEditar}
                    aoFechar={() => porAEditar(null)}
                    aoGravado={(m) => {
                        void cache.invalidateQueries({ queryKey: ['hotel', 'tarifas'] });
                        porAEditar(null); aoGravar(m);
                    }}
                />
            )}
        </>
    );
}

function EditarPorDia({ tipo, aoFechar, aoGravado }: {
    tipo: TarifaPorDia;
    aoFechar: () => void;
    aoGravado: (m: string) => void;
}) {
    const [dias, porDias] = useState<Record<number, string>>(
        Object.fromEntries(tipo.dias.map((d) => [d.dia, String(d.modificador)])),
    );

    const guardar = useMutation({
        mutationFn: () => tarifas.guardarPorDia(String(tipo.id), dias),
        onSuccess: (r) => aoGravado(r.message),
    });

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Tarifas por dia da semana')}
            subtitulo={tipo.nome}
            icone="fa-calendar-week"
            cor="roxo"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                        onClick={() => guardar.mutate()}>
                        {t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={guardar.error} />

            <p className="mb-3 text-sm text-slate-600">
                {tPartes('Um multiplicador sobre o preço da época. :um deixa o dia como está.', {
                    um: <strong>×1</strong>,
                })}
            </p>

            <div className="space-y-2">
                {tipo.dias.map((d) => {
                    const valor = Number(dias[d.dia]) || 0;

                    return (
                        <div key={d.dia} className="flex items-center gap-3">
                            <span className="w-24 flex-none text-sm font-semibold text-slate-700">{d.rotulo}</span>
                            <input type="number" min={0} max={10} step="0.05" value={dias[d.dia] ?? '1'}
                                aria-label={d.rotulo}
                                className={cls(entrada, 'w-28 text-right tabular-nums')}
                                onChange={(e) => porDias((x) => ({ ...x, [d.dia]: e.target.value }))} />
                            <span className={cls('text-sm tabular-nums',
                                valor > 1 ? 'font-bold text-amber-700' : valor < 1 ? 'font-bold text-emerald-700' : 'text-slate-400')}>
                                {kz(tipo.base * valor)} Kz
                            </span>
                        </div>
                    );
                })}
            </div>
        </Modal>
    );
}

/* ─── Os dias especiais ─────────────────────────────────────────────── */

function Especiais({ o, aoGravar }: { o: OpcoesDasTarifas; aoGravar: (m: string) => void }) {
    const cache = useQueryClient();
    const [formulario, porFormulario] = useState<{ dia: string; tipo: string; preco: string; motivo: string } | null>(null);
    const [aApagar, porAApagar] = useState<TarifaEspecial | null>(null);

    const especiais = useQuery({ queryKey: ['hotel', 'tarifas', 'especiais'], queryFn: tarifas.especiais });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['hotel', 'tarifas'] });

    const guardar = useMutation({
        mutationFn: (dados: { dia: string; tipo: string; preco: string; motivo: string }) => tarifas.guardarEspecial(dados),
        onSuccess: (r) => { invalidar(); porFormulario(null); aoGravar(r.message); },
    });

    const apagar = useMutation({
        mutationFn: (x: TarifaEspecial) => tarifas.apagarEspecial(x.id),
        onSuccess: (r) => { invalidar(); porAApagar(null); aoGravar(r.message); },
    });

    if (especiais.isPending) return <Carregando linhas={6} />;
    if (especiais.isError) return <Falhou erro={especiais.error} />;

    const lista = especiais.data.data;
    const daApi = guardar.error instanceof ErroDaApi ? guardar.error : null;

    return (
        <>
            <div className={cls(CARTAO, 'overflow-hidden')}>
                {o.permissoes.pode_editar && (
                    <div className="flex justify-end border-b border-slate-100 p-3">
                        <Botao cor="primaria" tom="solida" icone="fa-plus"
                            onClick={() => porFormulario({ dia: hoje(), tipo: '', preco: '', motivo: '' })}>
                            {t('Nova tarifa de dia')}
                        </Botao>
                    </div>
                )}

                {lista.length === 0 ? (
                    <SemNada
                        icone="fa-star"
                        titulo={t('Nenhuma tarifa de dia')}
                        frase={t('Um dia de jogo, um feriado, uma conferência — o preço desse dia substitui tudo o resto.')}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <th scope="col" className="px-4 py-3 text-left">{t('Dia')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Tipo de quarto')}</th>
                                    <th scope="col" className="px-4 py-3 text-right">{t('Preço')}</th>
                                    <th scope="col" className="px-4 py-3 text-left">{t('Motivo')}</th>
                                    <th scope="col" className="px-4 py-3 text-right">{t('Acções')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {lista.map((x, i) => (
                                    <tr key={x.id} className={cls('entra transition-colors duration-150 hover:bg-purple-50/40', x.passada && 'opacity-50')}
                                        style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                        <td className="px-4 py-2.5 tabular-nums font-semibold text-slate-800">
                                            {data(x.dia)}
                                            {x.passada && <span className="ml-2 text-xs font-normal text-slate-400">{t('já passou')}</span>}
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-600">
                                            {x.tipo ?? <span className="text-slate-400">{t('Todos os tipos')}</span>}
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-bold tabular-nums text-slate-800">
                                            {x.preco === null ? '—' : `${kz(x.preco)} Kz`}
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-500">{x.motivo ?? '—'}</td>
                                        <td className="px-4 py-2.5 text-right">
                                            {o.permissoes.pode_apagar && (
                                                <button type="button" onClick={() => porAApagar(x)}
                                                    title={t('Apagar')} aria-label={t('Apagar: :nome', { nome: data(x.dia) })}
                                                    className={cls('p-2 text-slate-400 transition-all hover:scale-110 hover:text-red-600', RAIO, FOCO)}>
                                                    <i className="fas fa-trash" aria-hidden="true" />
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {formulario && (
                <Modal
                    aberto
                    aoFechar={() => porFormulario(null)}
                    titulo={t('Tarifa de um dia')}
                    icone="fa-star"
                    cor="roxo"
                    largura="md"
                    rodape={
                        <>
                            <Botao onClick={() => porFormulario(null)}>{t('Cancelar')}</Botao>
                            <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                                onClick={() => guardar.mutate(formulario)}>
                                {t('Guardar')}
                            </Botao>
                        </>
                    }
                >
                    <AvisoDeErro erro={guardar.error} />

                    <div className="grid gap-3 sm:grid-cols-2">
                        <Campo etiqueta={t('Dia')} obrigatorio erro={daApi?.erros.dia}>
                            <input type="date" value={formulario.dia} className={cls(entrada, 'tabular-nums')}
                                onChange={(e) => porFormulario((f) => f && ({ ...f, dia: e.target.value }))} />
                        </Campo>
                        <Campo etiqueta={t('Tipo de quarto')} erro={daApi?.erros.tipo}
                            ajuda={t('Em branco vale para a casa toda.')}>
                            <select value={formulario.tipo} className={entrada}
                                onChange={(e) => porFormulario((f) => f && ({ ...f, tipo: e.target.value }))}>
                                <option value="">{t('Todos os tipos')}</option>
                                {o.tipos_de_quarto.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                            </select>
                        </Campo>
                        <Campo etiqueta={t('Preço da noite (Kz)')} obrigatorio erro={daApi?.erros.preco}
                            ajuda={t('Substitui a época e o dia da semana.')}>
                            <input type="number" min={0} step="0.01" value={formulario.preco}
                                className={cls(entrada, 'text-right tabular-nums')}
                                onChange={(e) => porFormulario((f) => f && ({ ...f, preco: e.target.value }))} />
                        </Campo>
                        <Campo etiqueta={t('Motivo')} erro={daApi?.erros.motivo}>
                            <input value={formulario.motivo} className={entrada}
                                placeholder={t('Ex.: jogo no estádio')}
                                onChange={(e) => porFormulario((f) => f && ({ ...f, motivo: e.target.value }))} />
                        </Campo>
                    </div>
                </Modal>
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar tarifa de dia')}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar)}>
                            {t('Apagar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {tPartes('Vai apagar :nome. Não há volta.', {
                        nome: <strong>{aApagar ? data(aApagar.dia) : ''}</strong>,
                    })}
                </p>
            </Modal>
        </>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as tarifas')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
