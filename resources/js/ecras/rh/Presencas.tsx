import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    presencas,
    type FiltrosDoPonto,
    type LinhaDePonto,
    type OpcoesDoPonto,
} from '@/api/ponto';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O PONTO — quem entrou, a que horas, e quem faltou.
 *
 * É a origem do que a folha desconta: uma falta a menos aqui é dinheiro a
 * mais no salário, e um atraso por marcar é um atraso que ninguém paga.
 *
 * O ECRÃ ABRE NO DIA DE HOJE, e com a lista de QUEM AINDA NÃO PICOU: no fim
 * da manhã é essa a lista que interessa, e cada nome tem um botão que marca a
 * entrada com um clique. O calendário do mês é a outra vista — uma linha por
 * pessoa, uma célula por dia, para se ver o mês inteiro de relance.
 */

const TOM: Record<string, TomDoCartao> = {
    bom: 'verde', aviso: 'ambar', perigo: 'vermelho', primaria: 'indigo', roxo: 'roxo', neutra: 'cinza',
};

const SINAL: Record<string, string> = {
    bom: 'fa-circle-check', aviso: 'fa-clock', perigo: 'fa-circle-xmark',
    primaria: 'fa-circle-half-stroke', roxo: 'fa-notes-medical', neutra: 'fa-umbrella-beach',
};

type Valores = Record<string, unknown>;

export default function Presencas() {
    const cache = useQueryClient();
    const hoje = new Date().toISOString().slice(0, 10);

    const [filtros, porFiltros] = useState<FiltrosDoPonto>({ de: hoje, ate: hoje, page: 1 });
    const [vista, porVista] = useState<'dia' | 'mes'>('dia');
    const [formulario, porFormulario] = useState<Valores | null>(null);
    const [aEditar, porAEditar] = useState<LinhaDePonto | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aApagar, porAApagar] = useState<LinhaDePonto | null>(null);
    const [aImportar, porAImportar] = useState(false);
    const [recado, porRecado] = useRecadoNoCanto('');

    const opcoes = useQuery({ queryKey: ['rh', 'presencas', 'opcoes'], queryFn: presencas.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({
        queryKey: ['rh', 'presencas', filtros],
        queryFn: () => presencas.lista(filtros),
        placeholderData: keepPreviousData,
    });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['rh', 'presencas'] });

    const gravar = useMutation({
        mutationFn: (v: Valores) => (aEditar ? presencas.actualizar(aEditar.id, v) : presencas.guardar(v)),
        onSuccess: (r) => { invalidar(); porFormulario(null); porAEditar(null); porErros({}); porRecado(r.message); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const marcar = useMutation({
        mutationFn: (id: number) => presencas.entrada(id),
        onSuccess: (r) => { invalidar(); porRecado(r.message); },
    });

    const sair = useMutation({
        mutationFn: (l: LinhaDePonto) => presencas.saida(l.id),
        onSuccess: (r) => { invalidar(); porRecado(r.message); },
    });

    const apagar = useMutation({
        mutationFn: (l: LinhaDePonto) => presencas.eliminar(l.id),
        onSuccess: (r) => { invalidar(); porAApagar(null); porRecado(r.message); },
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) return <Falhou erro={opcoes.error ?? lista.error} />;

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const resumo = lista.data?.resumo;
    const porMarcar = lista.data?.por_marcar ?? [];

    const abrirNovo = () => {
        porAEditar(null);
        porErros({});
        porFormulario({ employee_id: '', shift_id: '', date: filtros.de ?? hoje, check_in: '', check_out: '', status: 'present', notes: '' });
    };

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Presenças')}
                subtitulo={t('O ponto de cada dia — e a origem do que a folha desconta')}
                icone="fa-clock"
                cor="bom"
                accoes={
                    <>
                        <button type="button" onClick={() => porVista((v) => (v === 'dia' ? 'mes' : 'dia'))} className={cls(ACCAO_DA_FAIXA, 'group')}>
                            <i className={cls('fas transition-transform duration-300 group-hover:scale-110', vista === 'dia' ? 'fa-calendar-days' : 'fa-list')} aria-hidden="true" />
                            {vista === 'dia' ? t('Mês') : t('Dia')}
                        </button>
                        <button type="button" onClick={() => porAImportar(true)} className={cls(ACCAO_DA_FAIXA, 'group')}>
                            <i className="fas fa-file-import transition-transform duration-300 group-hover:-translate-y-0.5" aria-hidden="true" />
                            {t('Importar picagens')}
                        </button>
                        <button type="button" onClick={abrirNovo} className={cls(ACCAO_DA_FAIXA, 'group')}>
                            <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />
                            {t('Marcar ponto')}
                        </button>
                    </>
                }
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <AvisoDeErro erro={marcar.error ?? sair.error ?? apagar.error} />

            {vista === 'mes' ? (
                <CalendarioDoMes />
            ) : (
                <>
                    <div className={cls('grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5', lista.isFetching && 'opacity-70')}>
                        {(resumo?.por_estado ?? []).slice(0, 3).map((e) => (
                            <CartaoNumero key={e.valor} aspecto="claro" rotulo={e.rotulo} tom={TOM[e.cor] ?? 'cinza'}
                                icone={SINAL[e.cor] ?? 'fa-circle-info'} valor={e.quantos.toLocaleString(etiquetaIntl())} />
                        ))}
                        <CartaoNumero aspecto="claro" rotulo={t('Horas trabalhadas')} tom="azul" icone="fa-hourglass-half"
                            nota={t('no período')} valor={resumo === undefined ? '—' : resumo.horas.toLocaleString(etiquetaIntl(), { maximumFractionDigits: 1 })} />
                        <CartaoNumero aspecto="claro" rotulo={t('Atrasos')} tom={resumo && resumo.atrasos > 0 ? 'ambar' : 'cinza'}
                            icone="fa-person-running" valor={resumo === undefined ? '—' : resumo.atrasos.toLocaleString(etiquetaIntl())} />
                    </div>

                    {/* QUEM AINDA NÃO PICOU HOJE — a razão de existir do botão
                        de entrada rápida: no fim da manhã, é esta a lista que
                        interessa, e cada nome marca-se com um clique. */}
                    {porMarcar.length > 0 && (
                        <Cartao titulo={t('Por marcar hoje')} icone="fa-user-clock">
                            <div className="flex flex-wrap gap-2">
                                {porMarcar.map((p, i) => (
                                    <button
                                        key={p.id}
                                        type="button"
                                        onClick={() => marcar.mutate(p.id)}
                                        disabled={marcar.isPending}
                                        style={{ '--i': Math.min(i, 12) } as React.CSSProperties}
                                        className={cls(
                                            'entra inline-flex items-center gap-2 border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700',
                                            'transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-400 hover:text-emerald-700 hover:shadow-sm',
                                            'disabled:opacity-50',
                                            RAIO, FOCO,
                                        )}
                                    >
                                        <i className="fas fa-right-to-bracket text-xs text-emerald-600" aria-hidden="true" />
                                        {p.nome}
                                    </button>
                                ))}
                            </div>
                            <p className="mt-3 text-xs text-slate-500">
                                <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                                {t('Um clique marca a entrada agora. A saída marca-se na linha, quando a pessoa sair.')}
                            </p>
                        </Cartao>
                    )}

                    <Cartao titulo={t('Filtros')} icone="fa-filter">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label className="block">
                                <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('De')}</span>
                                <input type="date" value={filtros.de ?? ''} onChange={(e) => porFiltros((f) => ({ ...f, de: e.target.value, page: 1 }))} className={cls(entrada, 'tabular-nums')} />
                            </label>
                            <label className="block">
                                <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Até')}</span>
                                <input type="date" value={filtros.ate ?? ''} onChange={(e) => porFiltros((f) => ({ ...f, ate: e.target.value, page: 1 }))} className={cls(entrada, 'tabular-nums')} />
                            </label>
                            <label className="block">
                                <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Funcionário')}</span>
                                <select value={filtros.funcionario ?? ''} onChange={(e) => porFiltros((f) => ({ ...f, funcionario: e.target.value, page: 1 }))} className={entrada}>
                                    <option value="">{t('Todos')}</option>
                                    {o.funcionarios.map((f) => <option key={f.valor} value={f.valor}>{f.rotulo}</option>)}
                                </select>
                            </label>
                            <label className="block">
                                <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Estado')}</span>
                                <select value={filtros.estado ?? ''} onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value, page: 1 }))} className={entrada}>
                                    <option value="">{t('Todos')}</option>
                                    {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                                </select>
                            </label>
                        </div>

                        <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-slate-500">
                                {t(':quantos ponto(s)', { quantos: (lista.data?.meta.total ?? 0).toLocaleString(etiquetaIntl()) })}
                                {lista.isFetching && <span className="ml-2 text-xs">{t('a actualizar…')}</span>}
                            </p>
                            <Botao altura="pequeno" icone="fa-calendar-day" onClick={() => porFiltros({ de: hoje, ate: hoje, page: 1 })}>{t('Hoje')}</Botao>
                        </div>
                    </Cartao>

                    {lista.isPending ? (
                        <Carregando />
                    ) : linhas.length === 0 ? (
                        <div className={cls(CARTAO, 'animate-fade-in px-6 py-16 text-center')}>
                            <div className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                                <i className="fas fa-clock text-4xl text-slate-300" aria-hidden="true" />
                            </div>
                            <p className="text-lg font-bold text-slate-800">{t('Nenhum ponto neste período')}</p>
                            <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">{t('Marque a entrada de quem já chegou, ou importe as picagens do relógio.')}</p>
                        </div>
                    ) : (
                        <Cartao titulo={t('Pontos')} icone="fa-list" semPadding>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[46rem] text-sm">
                                    <thead className="bg-slate-50">
                                        <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-600">
                                            <th className="px-4 py-3 font-bold">{t('Funcionário')}</th>
                                            <th className="px-4 py-3 font-bold">{t('Dia')}</th>
                                            <th className="px-4 py-3 font-bold">{t('Entrada')}</th>
                                            <th className="px-4 py-3 font-bold">{t('Saída')}</th>
                                            <th className="px-4 py-3 text-right font-bold">{t('Horas')}</th>
                                            <th className="px-4 py-3 font-bold">{t('Estado')}</th>
                                            <th className="px-4 py-3 text-right font-bold">{t('Acções')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {linhas.map((l, i) => {
                                            const estado = o.estados.find((e) => e.valor === l.status);

                                            return (
                                                <tr key={l.id} className="entra transition-all duration-200 hover:bg-emerald-50/50" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                                    <td className="px-4 py-3">
                                                        <span className="block font-semibold text-slate-900">{l.funcionario}</span>
                                                        {l.numero && <span className="block font-mono text-xs text-slate-400">{l.numero}</span>}
                                                    </td>
                                                    <td className="px-4 py-3 tabular-nums text-slate-600">{l.date ? data(l.date) : '—'}</td>
                                                    <td className="px-4 py-3 tabular-nums">
                                                        {l.check_in ?? <span className="text-slate-300">—</span>}
                                                        {/* O ATRASO ao lado da entrada, que é onde se lê. */}
                                                        {l.is_late && (
                                                            <span className="ml-1.5 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">
                                                                +{l.late_minutes}′
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 tabular-nums">
                                                        {l.check_out ?? (
                                                            <Botao altura="pequeno" icone="fa-right-from-bracket" aTrabalhar={sair.isPending}
                                                                onClick={() => sair.mutate(l)}>{t('Saída')}</Botao>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-semibold tabular-nums text-slate-900">
                                                        {l.hours_worked > 0 ? l.hours_worked.toLocaleString(etiquetaIntl(), { maximumFractionDigits: 2 }) : <span className="font-normal text-slate-300">—</span>}
                                                        {l.overtime_hours > 0 && (
                                                            <span className="block text-[10px] font-bold text-indigo-600">+{l.overtime_hours} {t('extra')}</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Etiqueta cor={estado?.cor ?? 'neutra'} icone={SINAL[estado?.cor ?? 'neutra']}>
                                                            {estado?.rotulo ?? l.status}
                                                        </Etiqueta>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <button type="button" onClick={() => { porAEditar(l); porErros({}); porFormulario({ ...l, check_in: l.check_in ?? '', check_out: l.check_out ?? '', shift_id: l.shift_id ?? '', notes: l.notes ?? '' }); }}
                                                                title={t('Editar')} aria-label={t('Editar o ponto de :nome', { nome: l.funcionario })}
                                                                className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 hover:text-indigo-600', RAIO, FOCO)}>
                                                                <i className="fas fa-pen" aria-hidden="true" />
                                                            </button>
                                                            <button type="button" onClick={() => porAApagar(l)} title={t('Eliminar')}
                                                                aria-label={t('Eliminar o ponto de :nome', { nome: l.funcionario })}
                                                                className={cls('p-2 text-red-500 transition-all duration-200 hover:scale-110 hover:bg-red-50', RAIO, FOCO)}>
                                                                <i className="fas fa-trash" aria-hidden="true" />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            {/* O servidor manda 25 de cada vez: sem isto, o 26.º ponto não se via. */}
                            {lista.data?.meta && (
                                <Paginacao
                                    pagina={lista.data.meta.current_page}
                                    ultima={lista.data.meta.last_page}
                                    aMudar={(p) => porFiltros((f) => ({ ...f, page: p }))}
                                    aCarregar={lista.isFetching}
                                />
                            )}
                        </Cartao>
                    )}
                </>
            )}

            {formulario && (
                <Modal
                    aberto
                    aoFechar={() => { porFormulario(null); porAEditar(null); porErros({}); }}
                    titulo={aEditar ? t('Editar ponto') : t('Marcar ponto')}
                    icone="fa-clock"
                    cor="bom"
                    largura="md"
                    rodape={
                        <>
                            <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                            <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate(formulario)}>
                                {t('Guardar')}
                            </Botao>
                        </>
                    }
                >
                    <AvisoDeErro erro={gravar.error} />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Funcionário')} erro={erros.employee_id} obrigatorio className="sm:col-span-2">
                            <select value={String(formulario.employee_id ?? '')} disabled={aEditar !== null}
                                onChange={(e) => {
                                    const f = o.funcionarios.find((x) => x.valor === e.target.value);
                                    // O TURNO VEM DA PESSOA: é contra ele que o
                                    // atraso se mede, e escrevê-lo à mão era
                                    // uma escolha a mais para errar.
                                    porFormulario((v) => ({ ...v!, employee_id: e.target.value, shift_id: f?.turno ?? '' }));
                                }}
                                className={entrada}>
                                <option value="">{t('Escolher…')}</option>
                                {o.funcionarios.map((f) => <option key={f.valor} value={f.valor}>{f.rotulo}{f.nota ? ` · ${f.nota}` : ''}</option>)}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Dia')} erro={erros.date} obrigatorio>
                            <input type="date" value={String(formulario.date ?? '')} onChange={(e) => porFormulario((v) => ({ ...v!, date: e.target.value }))} className={cls(entrada, 'tabular-nums')} />
                        </Campo>

                        <Campo etiqueta={t('Estado')} erro={erros.status} obrigatorio>
                            <select value={String(formulario.status ?? 'present')} onChange={(e) => porFormulario((v) => ({ ...v!, status: e.target.value }))} className={entrada}>
                                {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Entrada')} erro={erros.check_in}>
                            <span className="relative block">
                                <i className="fas fa-clock pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true" />
                                <input type="time" value={String(formulario.check_in ?? '')} onChange={(e) => porFormulario((v) => ({ ...v!, check_in: e.target.value }))} className={cls(entrada, 'pl-9 tabular-nums')} />
                            </span>
                        </Campo>

                        <Campo etiqueta={t('Saída')} erro={erros.check_out} ajuda={t('As horas são contadas — a saída antes da entrada é do dia seguinte.')}>
                            <span className="relative block">
                                <i className="fas fa-clock pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true" />
                                <input type="time" value={String(formulario.check_out ?? '')} onChange={(e) => porFormulario((v) => ({ ...v!, check_out: e.target.value }))} className={cls(entrada, 'pl-9 tabular-nums')} />
                            </span>
                        </Campo>

                        <Campo etiqueta={t('Notas')} erro={erros.notes} className="sm:col-span-2">
                            <textarea rows={2} value={String(formulario.notes ?? '')} onChange={(e) => porFormulario((v) => ({ ...v!, notes: e.target.value }))} className={cls(entrada, 'h-auto py-2')} />
                        </Campo>
                    </div>
                </Modal>
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar ponto')}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar)}>{t('Eliminar')}</Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {t('Vai eliminar o ponto de :nome em :dia.', { nome: aApagar?.funcionario ?? '', dia: aApagar?.date ? data(aApagar.date) : '' })}
                </p>
                <p className="mt-2 text-sm text-slate-500">{t('A folha desse mês passa a contar um dia a menos de presença.')}</p>
            </Modal>

            {aImportar && (
                <Importacao
                    o={o}
                    aoFechar={() => porAImportar(false)}
                    aoImportar={(m) => { porAImportar(false); porRecado(m); invalidar(); }}
                />
            )}
        </div>
    );
}

/* ─── O calendário do mês ───────────────────────────────────────────── */

/**
 * O MÊS INTEIRO: uma linha por pessoa, uma célula por dia.
 *
 * É a vista que responde a «como correu o mês» — quem faltou, quem chegou
 * tarde, quem esteve de férias. A lista responde por dia; isto responde pelo
 * mês, e é onde se vê um padrão.
 */
function CalendarioDoMes() {
    const agora = new Date();
    const [ano, porAno] = useState(agora.getFullYear());
    const [mes, porMes] = useState(agora.getMonth() + 1);

    const q = useQuery({
        queryKey: ['rh', 'presencas', 'mes', ano, mes],
        queryFn: () => presencas.calendario(ano, mes),
        placeholderData: keepPreviousData,
    });

    const andar = (passo: number) => {
        const d = new Date(ano, mes - 1 + passo, 1);
        porAno(d.getFullYear());
        porMes(d.getMonth() + 1);
    };

    const dias = q.data?.dias ?? 0;
    const nomeDoMes = new Date(ano, mes - 1, 1).toLocaleDateString(etiquetaIntl(), { month: 'long', year: 'numeric' });

    /* A cor de cada estado, na célula. */
    const tomDoDia: Record<string, string> = {
        present: 'bg-emerald-100 text-emerald-700',
        late: 'bg-amber-100 text-amber-800',
        half_day: 'bg-sky-100 text-sky-700',
        absent: 'bg-red-100 text-red-700',
        sick: 'bg-purple-100 text-purple-700',
        vacation: 'bg-indigo-100 text-indigo-700',
    };

    const letra: Record<string, string> = {
        present: 'P', late: 'A', half_day: '½', absent: 'F', sick: 'D', vacation: 'V',
    };

    return (
        <Cartao
            titulo={t('O mês')}
            icone="fa-calendar-days"
            accoes={
                <div className="flex items-center gap-2">
                    <Botao altura="pequeno" icone="fa-chevron-left" onClick={() => andar(-1)}>{t('Anterior')}</Botao>
                    <span className="min-w-[9rem] text-center text-sm font-bold capitalize text-slate-700">{nomeDoMes}</span>
                    <Botao altura="pequeno" icone="fa-chevron-right" onClick={() => andar(1)}>{t('Seguinte')}</Botao>
                </div>
            }
            semPadding
        >
            {q.isPending ? (
                <Carregando linhas={5} />
            ) : (q.data?.linhas.length ?? 0) === 0 ? (
                <p className="px-6 py-12 text-center text-sm text-slate-400">{t('Nenhum ponto marcado neste mês.')}</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-xs">
                        <thead className="bg-slate-50">
                            <tr className="border-b border-slate-200">
                                <th className="sticky left-0 z-10 bg-slate-50 px-4 py-2 text-left font-bold uppercase tracking-wider text-slate-600">
                                    {t('Funcionário')}
                                </th>
                                {Array.from({ length: dias }, (_, i) => i + 1).map((d) => (
                                    <th key={d} className="w-7 py-2 text-center font-bold tabular-nums text-slate-400">{d}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {(q.data?.linhas ?? []).map((linha, i) => (
                                <tr key={linha.id} className="entra hover:bg-slate-50" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                    <td className="sticky left-0 z-10 bg-white px-4 py-2 font-semibold text-slate-800">{linha.nome}</td>
                                    {Array.from({ length: dias }, (_, k) => k + 1).map((d) => {
                                        const chave = `${ano}-${String(mes).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                                        const ponto = linha.dias[chave];

                                        return (
                                            <td key={d} className="p-0.5 text-center">
                                                {ponto ? (
                                                    <span
                                                        title={`${linha.nome} · ${chave}${ponto.horas ? ` · ${ponto.horas}h` : ''}`}
                                                        className={cls(
                                                            'inline-grid h-6 w-6 place-items-center rounded text-[10px] font-bold transition-transform duration-150 hover:scale-110',
                                                            tomDoDia[ponto.estado] ?? 'bg-slate-100 text-slate-500',
                                                        )}
                                                    >
                                                        {letra[ponto.estado] ?? '·'}
                                                    </span>
                                                ) : (
                                                    <span className="inline-block h-6 w-6 rounded bg-slate-50" aria-hidden="true" />
                                                )}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <div className="flex flex-wrap gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500">
                        {Object.entries(letra).map(([estado, l]) => (
                            <span key={estado} className="inline-flex items-center gap-1.5">
                                <span className={cls('inline-grid h-4 w-4 place-items-center rounded text-[9px] font-bold', tomDoDia[estado])}>{l}</span>
                                {t({ present: 'Presente', late: 'Atrasado', half_day: 'Meio dia', absent: 'Ausente', sick: 'Doente', vacation: 'De férias' }[estado] ?? estado)}
                            </span>
                        ))}
                    </div>
                </div>
            )}
        </Cartao>
    );
}

/* ─── Importar as picagens ──────────────────────────────────────────── */

/**
 * O FICHEIRO DO RELÓGIO DE PONTO.
 *
 * Escrever trezentas picagens à mão é o que isto poupa. É idempotente:
 * reimportar o mesmo ficheiro actualiza as linhas em vez de as duplicar.
 */
function Importacao({ o, aoFechar, aoImportar }: {
    o: OpcoesDoPonto;
    aoFechar: () => void;
    aoImportar: (m: string) => void;
}) {
    const [sistema, porSistema] = useState('zkteco');
    const [ficheiro, porFicheiro] = useState<File | null>(null);
    const [erros, porErros] = useState<string[]>([]);

    const correr = useMutation({
        mutationFn: () => presencas.importar(ficheiro!, sistema),
        onSuccess: (r) => {
            // OS ERROS MOSTRAM-SE, e não se engolem: quem importa tem de saber
            // que nomes o ficheiro trazia e que não existem na casa.
            if (r.erros.length > 0) {
                porErros(r.erros);

                return;
            }

            aoImportar(r.message);
        },
    });

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Importar picagens')}
            subtitulo={t('Do ficheiro que o relógio de ponto exporta')}
            icone="fa-file-import"
            cor="primaria"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{erros.length > 0 ? t('Fechar') : t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-file-import" aTrabalhar={correr.isPending}
                        disabled={!ficheiro} onClick={() => { porErros([]); correr.mutate(); }}>
                        {t('Importar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={correr.error} />

            <div className="space-y-4">
                <Campo etiqueta={t('Sistema')} obrigatorio ajuda={t('É ele que diz em que colunas está cada coisa.')}>
                    <select value={sistema} onChange={(e) => porSistema(e.target.value)} className={entrada}>
                        {o.sistemas.map((s) => <option key={s.valor} value={s.valor}>{s.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Ficheiro')} obrigatorio ajuda={t('Excel ou CSV, até 5 MB.')}>
                    <input type="file" accept=".xlsx,.xls,.csv" onChange={(e) => porFicheiro(e.target.files?.[0] ?? null)}
                        className={cls(entrada, 'h-auto py-2 file:mr-3 file:rounded file:border-0 file:bg-indigo-50 file:px-3 file:py-1 file:text-sm file:font-semibold file:text-indigo-700')} />
                </Campo>

                {erros.length > 0 && (
                    <div role="alert" className={cls('border border-amber-200 bg-amber-50 p-3', RAIO)}>
                        <p className="mb-2 text-sm font-bold text-amber-900">
                            <i className="fas fa-triangle-exclamation mr-1.5" aria-hidden="true" />
                            {t('Algumas linhas foram ignoradas')}
                        </p>
                        <ul className="space-y-0.5 text-xs text-amber-900">
                            {erros.map((e, i) => <li key={i}>{e}</li>)}
                        </ul>
                    </div>
                )}

                <p className="text-xs text-slate-500">
                    <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                    {t('O funcionário reconhece-se pelo número, pelo nome completo ou pelo primeiro nome. Reimportar o mesmo ficheiro actualiza — não duplica.')}
                </p>
            </div>
        </Modal>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as presenças')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
