import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    limpeza,
    type FiltrosDaLimpeza,
    type OpcoesDaLimpeza,
    type QuartoNaPlanta,
    type TarefaDeLimpeza,
    type TarefaParaGravar,
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
import { CARTAO, FOCO, RAIO, cls, data } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t, tPartes } from '@/i18n';

/**
 * A LIMPEZA DOS QUARTOS — o quadro que a governanta olha de manhã.
 *
 * DUAS VISTAS DA MESMA COISA, como no ecrã de sempre: o QUADRO é a fila de
 * trabalho por estado; os QUARTOS são a planta da casa pintada pelo estado de
 * limpeza. O quadro responde a «o que falta fazer»; a planta responde a «este
 * quarto pode ser vendido?», e não são a mesma pergunta.
 *
 * O QUE ESTA MIGRAÇÃO CORRIGE:
 *
 *  • A COLUNA «COM PROBLEMAS» estava no quadro e não havia maneira nenhuma de
 *    lá pôr uma tarefa — ficava sempre vazia. Agora há o botão, e marcar um
 *    problema deixa o quarto fora de serviço.
 *  • O CARTÃO NUNCA MOSTROU O HÓSPEDE: lia a ficha antiga (`hotel_guests`),
 *    que está vazia, enquanto o modal ao lado lia a certa e mostrava.
 *  • A URGENTE VINHA EM ÚLTIMO. A ordenação era alfabética sobre a chave —
 *    «high, low, normal, urgent» — no ecrã que existe para pôr o urgente à
 *    frente.
 */

const COR_DA_PRIORIDADE: Record<string, 'neutra' | 'primaria' | 'aviso' | 'perigo'> = {
    low: 'neutra',
    normal: 'primaria',
    high: 'aviso',
    urgent: 'perigo',
};

const ICONE_DO_TIPO: Record<string, string> = {
    checkout_clean: 'fa-right-from-bracket',
    stay_clean: 'fa-bed',
    deep_clean: 'fa-spray-can-sparkles',
    turndown: 'fa-moon',
    inspection: 'fa-magnifying-glass',
};

/**
 * AS QUATRO COLUNAS DO QUADRO — e não cinco.
 *
 * «Concluída» e «Verificada» são o mesmo sítio para quem está a olhar: o
 * trabalho está feito, falta só alguém dar-lhe o visto. Separá-las dava uma
 * coluna que a governanta nunca lê.
 */
const COLUNAS = [
    { chave: 'pendentes', estados: ['pending'], rotulo: () => t('Pendentes'), icone: 'fa-clock', cor: 'amber' },
    { chave: 'em_curso', estados: ['in_progress'], rotulo: () => t('Em curso'), icone: 'fa-broom', cor: 'blue' },
    { chave: 'concluidas', estados: ['completed', 'verified'], rotulo: () => t('Concluídas'), icone: 'fa-check', cor: 'emerald' },
    { chave: 'problemas', estados: ['issue'], rotulo: () => t('Com problemas'), icone: 'fa-triangle-exclamation', cor: 'red' },
] as const;

const CABECA_DA_COLUNA: Record<string, string> = {
    amber: 'text-amber-700',
    blue: 'text-blue-700',
    emerald: 'text-emerald-700',
    red: 'text-red-700',
};

/** O quarto pinta-se pelo estado de limpeza — é a legenda de sempre. */
const COR_DO_QUARTO: Record<string, string> = {
    clean: 'bg-gradient-to-br from-emerald-500 to-green-600',
    dirty: 'bg-gradient-to-br from-red-500 to-rose-600',
    in_progress: 'bg-gradient-to-br from-blue-500 to-indigo-600',
    inspecting: 'bg-gradient-to-br from-amber-400 to-orange-500',
    out_of_order: 'bg-gradient-to-br from-slate-500 to-slate-600',
};

const PONTO_DA_LEGENDA: Record<string, string> = {
    clean: 'bg-emerald-500',
    dirty: 'bg-red-500',
    in_progress: 'bg-blue-500',
    inspecting: 'bg-amber-400',
    out_of_order: 'bg-slate-500',
};

const hoje = () => new Date().toISOString().slice(0, 10);

const somarDias = (dia: string, quantos: number) => {
    const d = new Date(`${dia}T12:00:00`);

    d.setDate(d.getDate() + quantos);

    return d.toISOString().slice(0, 10);
};

const formularioVazio = (dia: string, quarto = ''): TarefaParaGravar => ({
    room_id: quarto, task_type: 'checkout_clean', priority: 'normal', assigned_to: '',
    scheduled_date: dia, scheduled_time: '', estimated_duration: '', notes: '',
});

export default function Limpeza() {
    const cache = useQueryClient();

    const [vista, porVista] = useState<'quadro' | 'quartos'>('quadro');
    const [filtros, porFiltros] = useState<FiltrosDaLimpeza>({ dia: hoje() });
    const [formulario, porFormulario] = useState<TarefaParaGravar | null>(null);
    const [aEditar, porAEditar] = useState<TarefaDeLimpeza | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aVer, porAVer] = useState<TarefaDeLimpeza | null>(null);
    const [aAtribuir, porAAtribuir] = useState<TarefaDeLimpeza | null>(null);
    const [aMarcarProblema, porAMarcarProblema] = useState<TarefaDeLimpeza | null>(null);
    const [aApagar, porAApagar] = useState<TarefaDeLimpeza | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    const opcoes = useQuery({ queryKey: ['hotel', 'limpeza', 'opcoes'], queryFn: limpeza.opcoes, staleTime: 5 * 60_000 });

    const dia = useQuery({
        queryKey: ['hotel', 'limpeza', 'dia', filtros],
        queryFn: () => limpeza.dia(filtros),
        placeholderData: keepPreviousData,
        enabled: opcoes.isSuccess,
    });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['hotel', 'limpeza'] });

    const gravar = useMutation({
        mutationFn: (dados: TarefaParaGravar) => (aEditar ? limpeza.guardar(aEditar.id, dados) : limpeza.criar(dados)),
        onSuccess: (r) => { invalidar(); porFormulario(null); porAEditar(null); porErros({}); porRecado(r.message ?? ''); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const gerar = useMutation({
        mutationFn: () => limpeza.gerar(),
        onSuccess: (r) => { invalidar(); porRecado(r.message); },
    });

    const mudarEstado = useMutation({
        mutationFn: ({ id, accao, problema }: { id: number; accao: 'comecar' | 'acabar' | 'verificar' | 'problema'; problema?: string }) =>
            limpeza.estado(id, accao, problema),
        onSuccess: (r) => {
            invalidar(); porRecado(r.message ?? ''); porAMarcarProblema(null);
            // A ficha aberta segue a tarefa: fechá-la a cada botão obrigava a
            // reabri-la para marcar o ponto seguinte da lista.
            porAVer((x) => (x && x.id === r.data.id ? r.data : x));
        },
    });

    const marcarPonto = useMutation({
        mutationFn: ({ id, indice }: { id: number; indice: number }) => limpeza.ponto(id, indice),
        onSuccess: (r) => { invalidar(); porAVer((x) => (x && x.id === r.data.id ? r.data : x)); },
    });

    const atribuir = useMutation({
        mutationFn: ({ id, pessoa }: { id: number; pessoa: string }) => limpeza.atribuir(id, pessoa),
        onSuccess: (r) => {
            invalidar(); porAAtribuir(null); porRecado(r.message ?? '');
            porAVer((x) => (x && x.id === r.data.id ? r.data : x));
        },
    });

    const apagar = useMutation({
        mutationFn: (x: TarefaDeLimpeza) => limpeza.apagar(x.id),
        onSuccess: (r) => { invalidar(); porAApagar(null); porAVer(null); porRecado(r.message); },
    });

    if (opcoes.isPending) return <Carregando linhas={10} />;
    if (opcoes.isError) return <Falhou erro={opcoes.error} />;

    const o = opcoes.data;
    const d = dia.data;
    const podeGerir = o.permissoes.pode_gerir;
    const escolhido = filtros.dia ?? hoje();
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    const abrirNovo = (quarto = '') => {
        porAEditar(null); porErros({}); porFormulario(formularioVazio(escolhido, quarto));
    };

    const abrirEdicao = (x: TarefaDeLimpeza) => {
        porAEditar(x); porErros({});
        porFormulario({
            room_id: String(x.room_id ?? ''),
            task_type: x.tipo,
            priority: x.prioridade,
            assigned_to: String(x.assigned_to ?? ''),
            scheduled_date: x.dia ?? escolhido,
            scheduled_time: x.hora ?? '',
            estimated_duration: x.minutos_previstos === null ? '' : String(x.minutos_previstos),
            notes: x.notas ?? '',
        });
    };

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Housekeeping')}
                subtitulo={t('A limpeza dos quartos, dia a dia')}
                icone="fa-broom"
                cor="teal"
                accoes={
                    <>
                        {/* O DIA ANDA PARA A FRENTE E PARA TRÁS. Ontem serve
                            para ver o que ficou por fazer; amanhã, para
                            preparar a manhã seguinte. */}
                        <span className={cls('flex items-center gap-1 bg-white/20 p-1', RAIO)}>
                            <button type="button" aria-label={t('Dia anterior')}
                                onClick={() => porFiltros((f) => ({ ...f, dia: somarDias(escolhido, -1) }))}
                                className={cls('px-2.5 py-1.5 transition-all hover:bg-white/20 active:scale-95', RAIO, FOCO)}>
                                <i className="fas fa-chevron-left" aria-hidden="true" />
                            </button>
                            <input type="date" value={escolhido} aria-label={t('Dia')}
                                onChange={(e) => porFiltros((f) => ({ ...f, dia: e.target.value || hoje() }))}
                                className={cls('w-36 border-0 bg-transparent text-center text-sm font-semibold text-white tabular-nums', FOCO, RAIO)} />
                            <button type="button" aria-label={t('Dia seguinte')}
                                onClick={() => porFiltros((f) => ({ ...f, dia: somarDias(escolhido, 1) }))}
                                className={cls('px-2.5 py-1.5 transition-all hover:bg-white/20 active:scale-95', RAIO, FOCO)}>
                                <i className="fas fa-chevron-right" aria-hidden="true" />
                            </button>
                        </span>

                        {podeGerir && (
                            <>
                                <button type="button" onClick={() => gerar.mutate()} disabled={gerar.isPending}
                                    className={cls(ACCAO_DA_FAIXA, 'group disabled:opacity-60')}>
                                    <i className={cls('fas', gerar.isPending ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles transition-transform duration-300 group-hover:rotate-12')}
                                        aria-hidden="true" />
                                    {t('Gerar do dia')}
                                </button>
                                <button type="button" onClick={() => abrirNovo()} className={cls(ACCAO_DA_FAIXA, 'group')}>
                                    <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />
                                    {t('Nova Tarefa')}
                                </button>
                            </>
                        )}
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

            <AvisoDeErro erro={gerar.error ?? mudarEstado.error ?? marcarPonto.error ?? apagar.error} />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <CartaoNumero aspecto="claro" rotulo={t('Pendentes')} tom="ambar" icone="fa-clock"
                    nota={t('no dia escolhido')} valor={d ? numero(d.resumo.pendentes) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Em curso')} tom="azul" icone="fa-broom"
                    valor={d ? numero(d.resumo.em_curso) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Concluídas')} tom="verde" icone="fa-check"
                    valor={d ? numero(d.resumo.concluidas) : '—'} />
                {/* ATRASADAS SÃO DE TODOS OS DIAS: é o que ficou por acabar, e
                    no dia de hoje não aparece. */}
                <CartaoNumero aspecto="claro" rotulo={t('Atrasadas')}
                    tom={d && d.resumo.atrasadas > 0 ? 'vermelho' : 'cinza'} icone="fa-triangle-exclamation"
                    nota={t('de dias anteriores')} valor={d ? numero(d.resumo.atrasadas) : '—'} />
                <CartaoNumero aspecto="claro" rotulo={t('Quartos limpos')} tom="teal" icone="fa-percent"
                    nota={d ? t(':limpos de :total', { limpos: numero(d.resumo.quartos_limpos), total: numero(d.resumo.quartos) }) : undefined}
                    valor={d ? `${d.resumo.limpeza}%` : '—'} />
            </div>

            <div className={cls(CARTAO, 'p-4')}>
                <div className="mb-4 flex flex-wrap gap-2" role="tablist" aria-label={t('Vista')}>
                    {([['quadro', t('Quadro'), 'fa-table-columns'], ['quartos', t('Quartos'), 'fa-border-all']] as const).map(([qual, rotulo, icone]) => (
                        <button
                            key={qual}
                            type="button"
                            role="tab"
                            aria-selected={vista === qual}
                            onClick={() => porVista(qual)}
                            className={cls(
                                'inline-flex items-center gap-2 border px-3 py-2 text-sm font-semibold transition-all duration-200',
                                'hover:-translate-y-0.5 active:translate-y-0', RAIO, FOCO,
                                vista === qual
                                    ? 'border-teal-500 bg-teal-50 text-teal-700 shadow-sm'
                                    : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700',
                            )}
                        >
                            <i className={cls('fas', icone)} aria-hidden="true" />
                            {rotulo}
                        </button>
                    ))}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Campo etiqueta={t('Prioridade')}>
                        <select value={filtros.prioridade ?? ''} className={entrada}
                            onChange={(e) => porFiltros((f) => ({ ...f, prioridade: e.target.value }))}>
                            <option value="">{t('Todas')}</option>
                            {o.prioridades.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Responsável')}>
                        <select value={filtros.responsavel ?? ''} className={entrada}
                            onChange={(e) => porFiltros((f) => ({ ...f, responsavel: e.target.value }))}>
                            <option value="">{t('Todos')}</option>
                            {o.pessoas.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                        </select>
                    </Campo>
                    {vista === 'quartos' && (
                        <Campo etiqueta={t('Piso')}>
                            <select value={filtros.andar ?? ''} className={entrada}
                                onChange={(e) => porFiltros((f) => ({ ...f, andar: e.target.value }))}>
                                <option value="">{t('Todos')}</option>
                                {o.andares.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                            </select>
                        </Campo>
                    )}
                </div>

                {/* A LEGENDA das cores dos quartos. Sem ela, a planta é um
                    mosaico bonito que não diz nada. */}
                <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    {o.limpezas.map((l) => (
                        <span key={l.valor} className="inline-flex items-center gap-1.5">
                            <span className={cls('h-2.5 w-2.5 rounded-full', PONTO_DA_LEGENDA[l.valor] ?? 'bg-slate-300')} aria-hidden="true" />
                            {l.rotulo}
                        </span>
                    ))}
                </div>
            </div>

            {dia.isPending ? (
                <Carregando linhas={8} />
            ) : dia.isError ? (
                <Falhou erro={dia.error} />
            ) : d && (
                <div className={cls(dia.isFetching && 'opacity-70 transition-opacity')}>
                    {vista === 'quadro' ? (
                        <div className="grid gap-3 lg:grid-cols-4">
                            {COLUNAS.map((coluna) => {
                                const tarefas = d.tarefas.filter((x) => (coluna.estados as readonly string[]).includes(x.estado));

                                return (
                                    <section key={coluna.chave} className={cls(CARTAO, 'flex flex-col p-3')}>
                                        <h2 className={cls('mb-3 flex items-center justify-between text-sm font-bold', CABECA_DA_COLUNA[coluna.cor])}>
                                            <span className="flex items-center gap-2">
                                                <i className={cls('fas', coluna.icone)} aria-hidden="true" />
                                                {coluna.rotulo()}
                                            </span>
                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs tabular-nums text-slate-500">
                                                {numero(tarefas.length)}
                                            </span>
                                        </h2>

                                        {tarefas.length === 0 ? (
                                            <p className="py-6 text-center text-xs text-slate-400">{t('Nada aqui.')}</p>
                                        ) : (
                                            <ul className="space-y-2">
                                                {tarefas.map((x, i) => (
                                                    <li key={x.id} className="entra" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                                                        <CartaoDaTarefa
                                                            tarefa={x}
                                                            podeGerir={podeGerir}
                                                            aTrabalhar={mudarEstado.isPending}
                                                            aoAbrir={() => porAVer(x)}
                                                            aoAtribuir={() => porAAtribuir(x)}
                                                            aoAvancar={(accao) => mudarEstado.mutate({ id: x.id, accao })}
                                                        />
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </section>
                                );
                            })}
                        </div>
                    ) : (
                        <div className={cls(CARTAO, 'p-5')}>
                            {d.quartos.length === 0 ? (
                                <SemNada
                                    icone="fa-door-open"
                                    titulo={t('Nenhum quarto')}
                                    frase={t('Sem quartos activos não há limpeza para organizar.')}
                                />
                            ) : (
                                <Planta
                                    quartos={d.quartos}
                                    podeGerir={podeGerir}
                                    aoAbrirTarefa={(id) => {
                                        const tarefa = d.tarefas.find((x) => x.id === id);

                                        // A tarefa pode estar fora do filtro de
                                        // cima: aí o pino existe e a ficha não
                                        // está à mão — carregar abre a criação
                                        // do quarto, que é o que faz sentido.
                                        if (tarefa) porAVer(tarefa);
                                    }}
                                    aoAbrirQuarto={(q) => abrirNovo(String(q.id))}
                                />
                            )}
                        </div>
                    )}

                    <p className="mt-3 px-1 text-xs text-slate-500">
                        <i className="fas fa-calendar-day mr-1.5" aria-hidden="true" />
                        {t(':quantas tarefa(s) em :dia', { quantas: numero(d.resumo.total), dia: data(d.dia) })}
                        {d.resumo.com_problema > 0 && (
                            <span className="ml-2 font-semibold text-red-600">
                                <i className="fas fa-triangle-exclamation mr-1" aria-hidden="true" />
                                {t(':n com problema', { n: numero(d.resumo.com_problema) })}
                            </span>
                        )}
                    </p>
                </div>
            )}

            {formulario && (
                <FormularioDaTarefa
                    o={o}
                    valores={formulario}
                    erros={erros}
                    titulo={aEditar ? t('Editar Tarefa') : t('Nova Tarefa')}
                    subtitulo={aEditar?.quarto ? t('Quarto :n', { n: aEditar.quarto }) : undefined}
                    aGravar={gravar.isPending}
                    erroGeral={gravar.error}
                    aoMudar={porFormulario}
                    aoFechar={() => { porFormulario(null); porAEditar(null); porErros({}); }}
                    aoGravar={() => gravar.mutate(formulario)}
                />
            )}

            {aVer && (
                <FichaDaTarefa
                    tarefa={aVer}
                    podeGerir={podeGerir}
                    aTrabalhar={mudarEstado.isPending || marcarPonto.isPending}
                    aoFechar={() => porAVer(null)}
                    aoMarcarPonto={(indice) => marcarPonto.mutate({ id: aVer.id, indice })}
                    aoAvancar={(accao) => mudarEstado.mutate({ id: aVer.id, accao })}
                    aoAtribuir={() => porAAtribuir(aVer)}
                    aoProblema={() => porAMarcarProblema(aVer)}
                    aoEditar={() => { porAVer(null); abrirEdicao(aVer); }}
                    aoApagar={() => porAApagar(aVer)}
                />
            )}

            <Atribuir
                tarefa={aAtribuir}
                pessoas={o.pessoas}
                aTrabalhar={atribuir.isPending}
                erro={atribuir.error}
                aoFechar={() => porAAtribuir(null)}
                aoAtribuir={(pessoa) => aAtribuir && atribuir.mutate({ id: aAtribuir.id, pessoa })}
            />

            <MarcarProblema
                tarefa={aMarcarProblema}
                aTrabalhar={mudarEstado.isPending}
                erro={mudarEstado.error}
                aoFechar={() => porAMarcarProblema(null)}
                aoMarcar={(problema) => aMarcarProblema && mudarEstado.mutate({ id: aMarcarProblema.id, accao: 'problema', problema })}
            />

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar tarefa de limpeza')}
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
                        nome: <strong>{aApagar ? t('a :tipo do quarto :quarto', { tipo: aApagar.tipo_rotulo.toLowerCase(), quarto: aApagar.quarto ?? '—' }) : ''}</strong>,
                    })}
                </p>
            </Modal>
        </div>
    );
}

/* ─── As peças ──────────────────────────────────────────────────────── */

/** O botão que avança a tarefa — cada estado tem um só passo seguinte. */
function passoSeguinte(estado: string): { accao: 'comecar' | 'acabar' | 'verificar'; rotulo: string; icone: string; classe: string } | null {
    switch (estado) {
        case 'pending':
            return { accao: 'comecar', rotulo: t('Iniciar'), icone: 'fa-play', classe: 'bg-blue-500 hover:bg-blue-600' };
        case 'in_progress':
            return { accao: 'acabar', rotulo: t('Concluir'), icone: 'fa-check', classe: 'bg-green-500 hover:bg-green-600' };
        case 'completed':
            return { accao: 'verificar', rotulo: t('Verificar'), icone: 'fa-check-double', classe: 'bg-emerald-500 hover:bg-emerald-600' };
        default:
            return null;
    }
}

function CartaoDaTarefa({ tarefa, podeGerir, aTrabalhar, aoAbrir, aoAtribuir, aoAvancar }: {
    tarefa: TarefaDeLimpeza;
    podeGerir: boolean;
    aTrabalhar: boolean;
    aoAbrir: () => void;
    aoAtribuir: () => void;
    aoAvancar: (accao: 'comecar' | 'acabar' | 'verificar') => void;
}) {
    const risca: Record<string, string> = {
        urgent: 'border-l-red-500 bg-red-50/50',
        high: 'border-l-orange-500 bg-orange-50/50',
        normal: 'border-l-blue-500 bg-blue-50/40',
        low: 'border-l-slate-300 bg-slate-50/50',
    };

    const passo = passoSeguinte(tarefa.estado);

    return (
        <div className={cls(
            'border border-slate-200 border-l-4 bg-white p-3 transition-all duration-200',
            'hover:-translate-y-0.5 hover:shadow-md',
            RAIO, risca[tarefa.prioridade] ?? 'border-l-slate-300',
        )}>
            <button type="button" onClick={aoAbrir} className={cls('w-full text-left', FOCO, RAIO)}>
                <span className="flex items-center justify-between gap-2">
                    <span className="flex min-w-0 items-center gap-2">
                        <span className="text-lg font-bold text-slate-900">{tarefa.quarto ?? '—'}</span>
                        {tarefa.tipo_de_quarto && (
                            <span className="truncate rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">
                                {tarefa.tipo_de_quarto}
                            </span>
                        )}
                    </span>
                    {tarefa.prioridade === 'urgent' ? (
                        <span className="animate-pulse rounded-full bg-red-100 px-2 py-1 text-xs font-bold text-red-700">
                            <i className="fas fa-exclamation mr-1" aria-hidden="true" />
                            {t('Urgente')}
                        </span>
                    ) : (
                        <Etiqueta cor={COR_DA_PRIORIDADE[tarefa.prioridade] ?? 'neutra'}>{tarefa.prioridade_rotulo}</Etiqueta>
                    )}
                </span>

                <span className="mt-1.5 block text-sm text-slate-600">
                    <i className={cls('fas mr-1.5 text-slate-400', ICONE_DO_TIPO[tarefa.tipo] ?? 'fa-broom')} aria-hidden="true" />
                    {tarefa.tipo_rotulo}
                </span>

                {/* O HÓSPEDE. O cartão de sempre lia a ficha vazia e nunca
                    mostrou nome nenhum. */}
                {tarefa.hospede && tarefa.hospede !== '—' && (
                    <span className="mt-1 block truncate text-xs text-slate-500">
                        <i className="fas fa-user mr-1.5" aria-hidden="true" />
                        {tarefa.hospede}
                    </span>
                )}

                <span className="mt-2 block">
                    <span className="mb-1 flex items-center justify-between text-xs text-slate-500">
                        <span>{t('Lista')}</span>
                        <span className="tabular-nums">{t(':feitos de :total', { feitos: tarefa.feitos, total: tarefa.pontos })}</span>
                    </span>
                    <Progresso valor={tarefa.progresso} />
                </span>
            </button>

            <div className="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                {tarefa.responsavel ? (
                    <span className="flex min-w-0 items-center gap-1.5 text-slate-500">
                        <Inicial nome={tarefa.responsavel} />
                        <span className="truncate">{tarefa.responsavel}</span>
                    </span>
                ) : podeGerir ? (
                    <button type="button" onClick={aoAtribuir}
                        className={cls('font-semibold text-teal-600 transition-colors hover:text-teal-800 hover:underline', FOCO, RAIO)}>
                        <i className="fas fa-user-plus mr-1" aria-hidden="true" />
                        {t('Atribuir')}
                    </button>
                ) : (
                    <span className="text-slate-400">{t('Por atribuir')}</span>
                )}

                <span className="flex items-center gap-2">
                    {tarefa.hora && (
                        <span className="text-slate-400 tabular-nums">
                            <i className="fas fa-clock mr-1" aria-hidden="true" />{tarefa.hora}
                        </span>
                    )}
                    {tarefa.atrasada && (
                        <span className="font-bold text-red-500">
                            <i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />{t('Atrasada')}
                        </span>
                    )}
                </span>
            </div>

            {podeGerir && passo && (
                <div className="mt-2.5 border-t border-slate-100 pt-2.5">
                    <button
                        type="button"
                        disabled={aTrabalhar}
                        onClick={() => aoAvancar(passo.accao)}
                        className={cls(
                            'w-full px-3 py-1.5 text-xs font-semibold text-white transition-all duration-200',
                            'hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-60',
                            RAIO, FOCO, passo.classe,
                        )}
                    >
                        <i className={cls('fas mr-1', passo.icone)} aria-hidden="true" />
                        {passo.rotulo}
                    </button>
                </div>
            )}

            {tarefa.estado === 'verified' && (
                <p className="mt-2.5 border-t border-slate-100 pt-2.5 text-xs font-semibold text-emerald-600">
                    <i className="fas fa-circle-check mr-1" aria-hidden="true" />
                    {tarefa.verificada_por ? t('Verificada por :quem', { quem: tarefa.verificada_por }) : t('Verificada')}
                </p>
            )}

            {tarefa.estado === 'issue' && tarefa.problema && (
                <p className="mt-2.5 line-clamp-2 border-t border-slate-100 pt-2.5 text-xs text-red-600">
                    <i className="fas fa-triangle-exclamation mr-1" aria-hidden="true" />
                    {tarefa.problema}
                </p>
            )}
        </div>
    );
}

/** A PLANTA: os quartos por piso, pintados pelo estado de limpeza. */
function Planta({ quartos, podeGerir, aoAbrirTarefa, aoAbrirQuarto }: {
    quartos: QuartoNaPlanta[];
    podeGerir: boolean;
    aoAbrirTarefa: (id: number) => void;
    aoAbrirQuarto: (q: QuartoNaPlanta) => void;
}) {
    const pisos = new Map<string, QuartoNaPlanta[]>();

    for (const q of quartos) {
        const piso = q.piso ?? '';

        if (!pisos.has(piso)) pisos.set(piso, []);
        pisos.get(piso)!.push(q);
    }

    return (
        <div className="space-y-6">
            {[...pisos.entries()].map(([piso, doPiso]) => (
                <section key={piso || 'sem-piso'}>
                    <h3 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-700">
                        <i className="fas fa-layer-group text-teal-600" aria-hidden="true" />
                        {piso ? t('Piso :p', { p: piso }) : t('Sem piso')}
                        <span className="text-xs font-normal text-slate-500">
                            {t(':n quarto(s)', { n: doPiso.length })}
                        </span>
                    </h3>

                    <div className="grid grid-cols-3 gap-3 sm:grid-cols-5 lg:grid-cols-8 xl:grid-cols-10">
                        {doPiso.map((q, i) => {
                            const temTarefa = q.tarefa !== null;
                            const podeCarregar = temTarefa || podeGerir;

                            return (
                                <div key={q.id} className="entra relative" style={{ '--i': Math.min(i, 20) } as React.CSSProperties}>
                                    <button
                                        type="button"
                                        disabled={!podeCarregar}
                                        onClick={() => (q.tarefa ? aoAbrirTarefa(q.tarefa.id) : aoAbrirQuarto(q))}
                                        title={temTarefa ? t('Ver a tarefa deste quarto') : t('Abrir tarefa para este quarto')}
                                        aria-label={t('Quarto :n — :estado', { n: q.numero, estado: q.limpeza_rotulo })}
                                        className={cls(
                                            'grid aspect-square w-full place-items-center px-1 text-white shadow-md transition-all duration-200',
                                            podeCarregar && 'hover:-translate-y-1 hover:shadow-xl active:translate-y-0',
                                            !podeCarregar && 'cursor-default',
                                            RAIO, FOCO,
                                            COR_DO_QUARTO[q.limpeza ?? ''] ?? 'bg-gradient-to-br from-slate-300 to-slate-400',
                                        )}
                                    >
                                        <span className="text-center leading-tight">
                                            <span className="block text-lg font-bold">{q.numero}</span>
                                            {q.tipo && <span className="block truncate text-[10px] opacity-80">{q.tipo}</span>}
                                        </span>
                                    </button>

                                    {q.tarefa && (
                                        <span className="absolute -right-1 -top-1 grid h-5 w-5 place-items-center rounded-full bg-white shadow" aria-hidden="true">
                                            {q.tarefa.prioridade === 'urgent' ? (
                                                <i className="fas fa-exclamation text-xs text-red-500" />
                                            ) : q.tarefa.estado === 'in_progress' ? (
                                                <i className="fas fa-spinner fa-spin text-xs text-blue-500" />
                                            ) : q.tarefa.estado === 'issue' ? (
                                                <i className="fas fa-triangle-exclamation text-xs text-red-500" />
                                            ) : ['completed', 'verified'].includes(q.tarefa.estado) ? (
                                                <i className="fas fa-check text-xs text-emerald-500" />
                                            ) : (
                                                <i className="fas fa-broom text-xs text-slate-400" />
                                            )}
                                        </span>
                                    )}

                                    {q.tarefa?.responsavel && (
                                        <span className="absolute -bottom-1 left-1/2 max-w-full -translate-x-1/2 truncate rounded bg-white px-1 text-[9px] text-slate-600 shadow">
                                            {q.tarefa.responsavel}
                                        </span>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </section>
            ))}
        </div>
    );
}

function FormularioDaTarefa({ o, valores, erros, titulo, subtitulo, aGravar, erroGeral, aoMudar, aoFechar, aoGravar }: {
    o: OpcoesDaLimpeza;
    valores: TarefaParaGravar;
    erros: Record<string, string[]>;
    titulo: string;
    subtitulo?: string;
    aGravar: boolean;
    erroGeral: unknown;
    aoMudar: (v: TarefaParaGravar) => void;
    aoFechar: () => void;
    aoGravar: () => void;
}) {
    const mudar = (campo: keyof TarefaParaGravar, valor: string) => aoMudar({ ...valores, [campo]: valor });

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={titulo}
            subtitulo={subtitulo}
            icone="fa-broom"
            cor="teal"
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={aGravar} onClick={aoGravar}>
                        {t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erroGeral} />

            <div className="grid gap-3 sm:grid-cols-2">
                <Campo etiqueta={t('Quarto')} obrigatorio erro={erros.room_id} className="sm:col-span-2">
                    <select value={valores.room_id} onChange={(e) => mudar('room_id', e.target.value)} className={entrada}>
                        <option value="">{t('Escolha…')}</option>
                        {o.quartos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Tipo')} obrigatorio erro={erros.task_type}
                    ajuda={t('A lista de verificação sai do tipo escolhido.')}>
                    <select value={valores.task_type} onChange={(e) => mudar('task_type', e.target.value)} className={entrada}>
                        {o.tipos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Prioridade')} obrigatorio erro={erros.priority}>
                    <select value={valores.priority} onChange={(e) => mudar('priority', e.target.value)} className={entrada}>
                        {o.prioridades.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Atribuir a')} erro={erros.assigned_to} className="sm:col-span-2">
                    <select value={valores.assigned_to} onChange={(e) => mudar('assigned_to', e.target.value)} className={entrada}>
                        <option value="">{t('Por atribuir')}</option>
                        {o.pessoas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Data')} obrigatorio erro={erros.scheduled_date}>
                    <input type="date" value={valores.scheduled_date}
                        onChange={(e) => mudar('scheduled_date', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                </Campo>
                <Campo etiqueta={t('Hora')} erro={erros.scheduled_time}>
                    <input type="time" value={valores.scheduled_time}
                        onChange={(e) => mudar('scheduled_time', e.target.value)} className={cls(entrada, 'tabular-nums')} />
                </Campo>

                <Campo etiqueta={t('Tempo previsto (minutos)')} erro={erros.estimated_duration}
                    ajuda={t('Uma limpeza de saída leva cerca de 45; uma de estadia, 20.')}>
                    <input type="number" min={0} step={5} value={valores.estimated_duration}
                        onChange={(e) => mudar('estimated_duration', e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />
                </Campo>

                <Campo etiqueta={t('Notas')} erro={erros.notes} className="sm:col-span-2">
                    <textarea rows={2} value={valores.notes} placeholder={t('Instruções especiais…')}
                        onChange={(e) => mudar('notes', e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                </Campo>
            </div>
        </Modal>
    );
}

function FichaDaTarefa({ tarefa, podeGerir, aTrabalhar, aoFechar, aoMarcarPonto, aoAvancar, aoAtribuir, aoProblema, aoEditar, aoApagar }: {
    tarefa: TarefaDeLimpeza;
    podeGerir: boolean;
    aTrabalhar: boolean;
    aoFechar: () => void;
    aoMarcarPonto: (indice: number) => void;
    aoAvancar: (accao: 'comecar' | 'acabar' | 'verificar') => void;
    aoAtribuir: () => void;
    aoProblema: () => void;
    aoEditar: () => void;
    aoApagar: () => void;
}) {
    const passo = passoSeguinte(tarefa.estado);
    // UMA TAREFA VERIFICADA NÃO SE MEXE: a lista fica como ficou quando
    // alguém lhe deu o visto.
    const fechada = tarefa.estado === 'verified';

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Quarto :n', { n: tarefa.quarto ?? '—' })}
            subtitulo={tarefa.tipo_rotulo}
            icone={ICONE_DO_TIPO[tarefa.tipo] ?? 'fa-broom'}
            cor="teal"
            largura="lg"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            <div className="space-y-4">
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Quadrado rotulo={t('Tipo')} valor={tarefa.tipo_rotulo} />
                    <Quadrado rotulo={t('Prioridade')} valor={tarefa.prioridade_rotulo}
                        cor={tarefa.prioridade === 'urgent' ? 'text-red-600' : tarefa.prioridade === 'high' ? 'text-orange-600' : undefined} />
                    <Quadrado rotulo={t('Estado')} valor={tarefa.estado_rotulo}
                        cor={tarefa.estado === 'issue' ? 'text-red-600' : ['completed', 'verified'].includes(tarefa.estado) ? 'text-emerald-600' : undefined} />
                    <Quadrado rotulo={t('Progresso')} valor={`${tarefa.progresso}%`} cor="text-teal-600" />
                </div>

                {tarefa.responsavel && (
                    <div className={cls('flex items-center gap-2 border border-purple-200 bg-purple-50 p-3', RAIO)}>
                        <Inicial nome={tarefa.responsavel} grande />
                        <span>
                            <span className="block text-xs text-purple-600">{t('Atribuída a')}</span>
                            <span className="block font-bold text-purple-700">{tarefa.responsavel}</span>
                        </span>
                    </div>
                )}

                {tarefa.hospede && tarefa.hospede !== '—' && (
                    <div className={cls('border border-blue-200 bg-blue-50 p-3', RAIO)}>
                        <span className="flex items-center gap-2">
                            <i className="fas fa-user-tie text-blue-500" aria-hidden="true" />
                            <span>
                                <span className="block text-xs text-blue-600">{t('Hóspede')}</span>
                                <span className="block font-bold text-blue-700">{tarefa.hospede}</span>
                            </span>
                        </span>
                        {tarefa.saida && (
                            <p className="mt-1 text-xs text-blue-500">
                                {t('Saída: :dia', { dia: data(tarefa.saida) })}
                            </p>
                        )}
                    </div>
                )}

                {/* A LISTA DE VERIFICAÇÃO — é o corpo da tarefa. */}
                <section>
                    <h3 className="mb-2 flex items-center gap-2 text-sm font-bold text-slate-800">
                        <i className="fas fa-list-check text-teal-600" aria-hidden="true" />
                        {t('Lista de verificação')}
                        <span className="text-xs font-normal text-slate-500">
                            {t(':feitos de :total', { feitos: tarefa.feitos, total: tarefa.pontos })}
                        </span>
                    </h3>

                    {tarefa.lista.length === 0 ? (
                        <p className="text-sm text-slate-400">{t('Este tipo de tarefa não tem lista.')}</p>
                    ) : (
                        <ul className={cls('max-h-64 space-y-1 overflow-y-auto border border-slate-200 bg-slate-50 p-3', RAIO)}>
                            {tarefa.lista.map((ponto) => (
                                <li key={ponto.indice}>
                                    <label className={cls(
                                        'flex items-center gap-3 p-2 transition-colors duration-150',
                                        RAIO, !fechada && podeGerir ? 'cursor-pointer hover:bg-white' : 'cursor-default',
                                    )}>
                                        <input
                                            type="checkbox"
                                            checked={ponto.feito}
                                            disabled={fechada || !podeGerir || aTrabalhar}
                                            onChange={() => aoMarcarPonto(ponto.indice)}
                                            className={cls('h-5 w-5 rounded border-slate-300 text-teal-600', FOCO)}
                                        />
                                        <span className={cls('text-sm transition-colors', ponto.feito ? 'text-slate-400 line-through' : 'text-slate-700')}>
                                            {ponto.item}
                                        </span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="mt-3">
                        <Progresso valor={tarefa.progresso} alto />
                    </div>
                </section>

                <div className="grid gap-4 sm:grid-cols-2">
                    <section className={cls('border border-slate-200 p-4', RAIO)}>
                        <h3 className="mb-3 text-sm font-bold text-slate-900">{t('A tarefa')}</h3>
                        <dl className="space-y-1.5">
                            <Dado rotulo={t('Quarto')} valor={tarefa.quarto} />
                            <Dado rotulo={t('Tipo de quarto')} valor={tarefa.tipo_de_quarto} />
                            <Dado rotulo={t('Piso')} valor={tarefa.piso} />
                            <Dado rotulo={t('Data')} valor={tarefa.dia ? data(tarefa.dia) : null} />
                            <Dado rotulo={t('Hora')} valor={tarefa.hora} />
                            <Dado rotulo={t('Verificada por')} valor={tarefa.verificada_por} />
                        </dl>
                    </section>

                    <section className={cls('border border-slate-200 p-4', RAIO)}>
                        <h3 className="mb-3 text-sm font-bold text-slate-900">{t('Tempos')}</h3>
                        <dl className="space-y-1.5">
                            <Dado rotulo={t('Tempo previsto')}
                                valor={tarefa.minutos_previstos === null ? null : t(':n min', { n: tarefa.minutos_previstos })} />
                            <Dado rotulo={t('Tempo gasto')}
                                valor={tarefa.minutos_gastos === null ? null : t(':n min', { n: tarefa.minutos_gastos })} />
                            <Dado rotulo={t('Começou')} valor={tarefa.comecou ? data(tarefa.comecou) : null} />
                            <Dado rotulo={t('Acabou')} valor={tarefa.acabou ? data(tarefa.acabou) : null} />
                        </dl>
                    </section>
                </div>

                {tarefa.notas && (
                    <section className={cls('flex items-start gap-2 border border-amber-200 bg-amber-50 p-3', RAIO)}>
                        <i className="fas fa-note-sticky mt-0.5 text-amber-500" aria-hidden="true" />
                        <p className="whitespace-pre-line text-sm text-amber-800">{tarefa.notas}</p>
                    </section>
                )}

                {tarefa.problema && (
                    <section className={cls('flex items-start gap-2 border border-red-200 bg-red-50 p-3', RAIO)}>
                        <i className="fas fa-triangle-exclamation mt-0.5 text-red-500" aria-hidden="true" />
                        <p className="whitespace-pre-line text-sm text-red-800">{tarefa.problema}</p>
                    </section>
                )}

                {podeGerir && (
                    <div className="flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                        {passo && (
                            <Botao cor="primaria" tom="solida" icone={passo.icone} aTrabalhar={aTrabalhar}
                                onClick={() => aoAvancar(passo.accao)}>
                                {passo.rotulo}
                            </Botao>
                        )}
                        {tarefa.estado === 'pending' && (
                            <Botao icone="fa-user-plus" onClick={aoAtribuir}>{t('Atribuir')}</Botao>
                        )}
                        {/* MARCAR UM PROBLEMA. A coluna existia no quadro e não
                            havia por onde lá pôr uma tarefa. */}
                        {!['verified', 'issue'].includes(tarefa.estado) && (
                            <Botao cor="aviso" icone="fa-triangle-exclamation" onClick={aoProblema}>
                                {t('Marcar problema')}
                            </Botao>
                        )}
                        <Botao icone="fa-pen" onClick={aoEditar}>{t('Editar')}</Botao>
                        <Botao cor="perigo" icone="fa-trash" onClick={aoApagar}>{t('Apagar')}</Botao>
                    </div>
                )}
            </div>
        </Modal>
    );
}

function Atribuir({ tarefa, pessoas, aTrabalhar, erro, aoFechar, aoAtribuir }: {
    tarefa: TarefaDeLimpeza | null;
    pessoas: OpcoesDaLimpeza['pessoas'];
    aTrabalhar: boolean;
    erro: unknown;
    aoFechar: () => void;
    aoAtribuir: (pessoa: string) => void;
}) {
    const [pessoa, porPessoa] = useState('');

    return (
        <Modal
            aberto={tarefa !== null}
            aoFechar={aoFechar}
            titulo={t('Atribuir tarefa')}
            subtitulo={tarefa?.quarto ? t('Quarto :n', { n: tarefa.quarto }) : undefined}
            icone="fa-user-plus"
            cor="roxo"
            largura="sm"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={aTrabalhar}
                        onClick={() => aoAtribuir(pessoa)}>
                        {t('Atribuir')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erro} />

            <Campo etiqueta={t('Quem limpa')}
                ajuda={t('Deixe em branco para tirar o responsável.')}>
                <select value={pessoa} onChange={(e) => porPessoa(e.target.value)} className={entrada}>
                    <option value="">{t('Por atribuir')}</option>
                    {pessoas.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                </select>
            </Campo>
        </Modal>
    );
}

function MarcarProblema({ tarefa, aTrabalhar, erro, aoFechar, aoMarcar }: {
    tarefa: TarefaDeLimpeza | null;
    aTrabalhar: boolean;
    erro: unknown;
    aoFechar: () => void;
    aoMarcar: (problema: string) => void;
}) {
    const [problema, porProblema] = useState('');

    return (
        <Modal
            aberto={tarefa !== null}
            aoFechar={aoFechar}
            titulo={t('Marcar problema')}
            subtitulo={tarefa?.quarto ? t('Quarto :n', { n: tarefa.quarto }) : undefined}
            icone="fa-triangle-exclamation"
            cor="aviso"
            largura="sm"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="perigo" tom="solida" icone="fa-triangle-exclamation" aTrabalhar={aTrabalhar}
                        onClick={() => aoMarcar(problema)}>
                        {t('Marcar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erro} />

            <Campo etiqueta={t('O que se passa')}
                ajuda={t('O quarto fica FORA DE SERVIÇO até alguém resolver — não pode ser vendido.')}>
                <textarea rows={3} value={problema} onChange={(e) => porProblema(e.target.value)}
                    className={cls(entrada, 'h-auto py-2')}
                    placeholder={t('Ex.: chuveiro partido, cheiro a humidade…')} />
            </Campo>
        </Modal>
    );
}

function Progresso({ valor, alto }: { valor: number; alto?: boolean }) {
    return (
        <span className={cls('block w-full overflow-hidden rounded-full bg-slate-200', alto ? 'h-3' : 'h-1.5')}
            role="progressbar" aria-valuenow={valor} aria-valuemin={0} aria-valuemax={100}>
            <span className="block h-full rounded-full bg-gradient-to-r from-teal-500 to-cyan-500 transition-all duration-500"
                style={{ width: `${valor}%` }} />
        </span>
    );
}

function Inicial({ nome, grande }: { nome: string; grande?: boolean }) {
    return (
        <span className={cls(
            'grid flex-none place-items-center rounded-full bg-gradient-to-br from-teal-500 to-cyan-600 font-bold text-white',
            grande ? 'h-8 w-8 text-sm' : 'h-5 w-5 text-[10px]',
        )} aria-hidden="true">
            {nome.slice(0, 1).toUpperCase()}
        </span>
    );
}

function Quadrado({ rotulo, valor, cor }: { rotulo: string; valor: string; cor?: string }) {
    return (
        <div className={cls('bg-slate-50 p-3 text-center', RAIO)}>
            <p className="mb-1 text-xs text-slate-500">{rotulo}</p>
            <p className={cls('font-bold', cor ?? 'text-slate-900')}>{valor}</p>
        </div>
    );
}

function Dado({ rotulo, valor }: { rotulo: string; valor?: string | null }) {
    if (!valor) return null;

    return (
        <div className="flex items-baseline justify-between gap-3 text-sm">
            <dt className="text-slate-500">{rotulo}</dt>
            <dd className="font-semibold text-slate-800">{valor}</dd>
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a limpeza')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
