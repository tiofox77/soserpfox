import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { projetos, type Lancamento } from '@/api/projetos';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';

/**
 * A FOLHA DE HORAS — a semana de quem a está a ver.
 *
 * MOSTRA-SE UMA SEMANA DE CADA VEZ, de propósito: quem lança horas lança-as do
 * que se lembra, e a memória não vai além de dias. Uma lista infinita convida a
 * lançar tudo ao molho no fim do mês, e horas lançadas ao molho são horas
 * inventadas.
 *
 * AS HORAS JÁ FACTURADAS NÃO SE MEXEM: estão a sustentar um documento emitido,
 * e corrigi-las mudaria o que o cliente já viu. O cadeado diz porquê.
 */

const VAZIO = {
    projeto_id: '', tarefa_id: '', data: '', horas: '',
    descricao: '', facturavel: true,
};

export default function FolhaDeHoras() {
    const cache = useQueryClient();

    const [semana, porSemana] = useState(() => {
        const hoje = new Date();
        const segunda = new Date(hoje);

        // A semana começa à SEGUNDA, como em toda a casa. O `getDay()` devolve
        // 0 ao domingo, e um domingo sem este ajuste saltava para a semana
        // seguinte.
        segunda.setDate(hoje.getDate() - ((hoje.getDay() + 6) % 7));

        return segunda.toISOString().slice(0, 10);
    });

    const [recado, porRecado] = useRecadoNoCanto('');
    const [erro, porErro] = useState<unknown>(null);
    const [formulario, porFormulario] = useState<typeof VAZIO | null>(null);
    const [aEditar, porAEditar] = useState<number | null>(null);
    const [aApagar, porAApagar] = useState<Lancamento | null>(null);

    const opcoes = useQuery({ queryKey: ['projetos', 'horas', 'opcoes'], queryFn: () => projetos.horas.opcoes() });

    const folha = useQuery({
        queryKey: ['projetos', 'horas', semana],
        queryFn: () => projetos.horas.semana(semana),
    });

    const tarefas = useQuery({
        queryKey: ['projetos', 'horas', 'tarefas', formulario?.projeto_id],
        queryFn: () => projetos.horas.tarefasDo(Number(formulario!.projeto_id)),
        enabled: !!formulario?.projeto_id,
    });

    const feito = (m: string) => {
        porErro(null);
        porRecado(m);
        void cache.invalidateQueries({ queryKey: ['projetos'] });
    };

    const guardar = useMutation({
        mutationFn: (d: Record<string, unknown>) => projetos.horas.guardar(aEditar, d),
        onSuccess: (r) => { feito(r.message); porFormulario(null); porAEditar(null); },
        onError: porErro,
    });

    const apagar = useMutation({
        mutationFn: (id: number) => projetos.horas.apagar(id),
        onSuccess: (r) => { feito(r.message); porAApagar(null); },
        onError: porErro,
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError) return <AvisoDeErro erro={opcoes.error} />;

    const o = opcoes.data;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());
    const erros = (guardar.error as { erros?: Record<string, string[]> } | null)?.erros ?? {};

    const andarSemana = (passo: number) => {
        const d = new Date(semana);

        d.setDate(d.getDate() + passo * 7);
        porSemana(d.toISOString().slice(0, 10));
    };

    const lancarEm = (dia: string) => {
        porAEditar(null);
        porFormulario({ ...VAZIO, data: dia });
    };

    return (
        <div className="space-y-5">
            <Faixa
                titulo={t('Folha de Horas')}
                subtitulo={t('Uma semana de cada vez — a memória não vai além de dias')}
                icone="fa-clock"
                cor="roxo"
                accoes={
                    <>
                        <a href="/projetos/tarefas" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-list-check" aria-hidden="true" />
                            {t('Tarefas')}
                        </a>
                        <a href="/projetos/dashboard" className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-chart-pie" aria-hidden="true" />
                            {t('Painel')}
                        </a>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <Botao altura="pequeno" icone="fa-chevron-left" onClick={() => andarSemana(-1)}
                        aria-label={t('Semana anterior')} />
                    <EstadoNaFaixa icone="fa-calendar-week">
                        {folha.data ? t('De :de a :ate', { de: folha.data.de, ate: folha.data.ate }) : semana}
                    </EstadoNaFaixa>
                    <Botao altura="pequeno" icone="fa-chevron-right" onClick={() => andarSemana(1)}
                        aria-label={t('Semana seguinte')} />
                    <Botao altura="pequeno" onClick={() => {
                        const hoje = new Date();

                        hoje.setDate(hoje.getDate() - ((hoje.getDay() + 6) % 7));
                        porSemana(hoje.toISOString().slice(0, 10));
                    }}>
                        {t('Esta semana')}
                    </Botao>
                </div>
            </Faixa>

            {recado && (
                <p role="status" className={cls('animate-fade-in border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800', RAIO)}>
                    <i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}
                </p>
            )}

            <AvisoDeErro erro={erro} />

            {folha.isPending ? (
                <Carregando linhas={7} />
            ) : folha.isError ? (
                <AvisoDeErro erro={folha.error} />
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <CartaoNumero
                            aspecto="claro" rotulo={t('Horas da semana')} valor={numero(folha.data.resumo.total)} sufixo="h"
                            icone="fa-clock" tom="roxo"
                        />
                        <CartaoNumero
                            aspecto="claro" rotulo={t('Facturáveis')} valor={numero(folha.data.resumo.facturavel)} sufixo="h"
                            icone="fa-file-invoice" tom="verde"
                            nota={t('As que podem virar factura')}
                        />
                        <CartaoNumero
                            aspecto="claro" rotulo={t('Já facturadas')} valor={numero(folha.data.resumo.facturadas)} sufixo="h"
                            icone="fa-lock" tom="indigo"
                            nota={t('Estas já não se corrigem')}
                        />
                    </div>

                    <div className="grid gap-3 lg:grid-cols-7">
                        {folha.data.dias.map((dia, i) => (
                            <section
                                key={dia.dia}
                                style={cascata(i)}
                                className={cls(
                                    'entra flex flex-col', CARTAO,
                                    dia.hoje && 'ring-2 ring-inset ring-purple-400',
                                )}
                            >
                                <header className="flex items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/60 px-3 py-2">
                                    <p className={cls('text-xs font-bold capitalize', dia.hoje ? 'text-purple-700' : 'text-slate-700')}>
                                        {dia.rotulo}
                                    </p>
                                    <span className="text-xs font-bold tabular-nums text-slate-500">
                                        {dia.total > 0 ? t(':n h', { n: numero(dia.total) }) : '—'}
                                    </span>
                                </header>

                                <ul className="flex-1 space-y-1.5 p-2">
                                    {dia.linhas.length === 0 ? (
                                        <li className="rounded-lg border border-dashed border-slate-200 px-2 py-4 text-center text-[11px] text-slate-400">
                                            {t('Sem horas')}
                                        </li>
                                    ) : dia.linhas.map((l) => (
                                        <li key={l.id}
                                            className={cls('border px-2 py-1.5', RAIO,
                                                l.facturada ? 'border-indigo-200 bg-indigo-50/60' : 'border-slate-200 bg-white')}>
                                            <div className="flex items-start justify-between gap-1">
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate text-[11px] font-bold text-slate-800">
                                                        {l.projeto ?? '—'}
                                                    </span>
                                                    {l.tarefa && (
                                                        <span className="block truncate text-[10px] text-slate-500">{l.tarefa}</span>
                                                    )}
                                                </span>
                                                <span className="flex-none text-xs font-bold tabular-nums text-slate-900">
                                                    {numero(l.horas)}
                                                </span>
                                            </div>

                                            {l.descricao && (
                                                <p className="truncate text-[10px] text-slate-500">{l.descricao}</p>
                                            )}

                                            <div className="mt-1 flex items-center gap-1">
                                                {!l.facturavel && (
                                                    <span className="text-[10px] font-semibold text-slate-400">
                                                        {t('Não facturável')}
                                                    </span>
                                                )}

                                                {/*
                                                  * O CADEADO DIZ PORQUÊ. Uma hora
                                                  * já facturada está a sustentar
                                                  * um documento emitido: mexer-lhe
                                                  * mudaria o que o cliente já viu.
                                                  */}
                                                {l.facturada ? (
                                                    <Etiqueta cor="primaria" icone="fa-lock">{t('Facturada')}</Etiqueta>
                                                ) : (
                                                    <span className="ml-auto flex items-center gap-1">
                                                        <button type="button"
                                                            onClick={() => {
                                                                porAEditar(l.id);
                                                                porFormulario({
                                                                    projeto_id: l.projeto_id ? String(l.projeto_id) : '',
                                                                    tarefa_id: l.tarefa_id ? String(l.tarefa_id) : '',
                                                                    data: l.dia ?? '',
                                                                    horas: String(l.horas),
                                                                    descricao: l.descricao ?? '',
                                                                    facturavel: l.facturavel,
                                                                });
                                                            }}
                                                            className={cls('grid h-6 w-6 place-items-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-purple-600', FOCO)}
                                                            aria-label={t('Corrigir lançamento')}
                                                        >
                                                            <i className="fas fa-pen text-[10px]" aria-hidden="true" />
                                                        </button>
                                                        <button type="button"
                                                            onClick={() => porAApagar(l)}
                                                            className={cls('grid h-6 w-6 place-items-center rounded-md text-slate-400 transition hover:bg-red-50 hover:text-red-600', FOCO)}
                                                            aria-label={t('Apagar lançamento')}
                                                        >
                                                            <i className="fas fa-trash text-[10px]" aria-hidden="true" />
                                                        </button>
                                                    </span>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>

                                <footer className="border-t border-slate-100 p-2">
                                    <Botao altura="pequeno" icone="fa-plus" className="w-full"
                                        onClick={() => lancarEm(dia.dia)}>
                                        {t('Lançar')}
                                    </Botao>
                                </footer>
                            </section>
                        ))}
                    </div>

                    {folha.data.resumo.total === 0 && (
                        <SemNada
                            icone="fa-clock"
                            titulo={t('Semana sem horas')}
                            frase={t('Lance as horas no dia em que as fez — no fim do mês já ninguém se lembra.')}
                        />
                    )}
                </>
            )}

            {/* ─── Lançar ─────────────────────────────────────────────── */}

            <Modal
                aberto={formulario !== null}
                aoFechar={() => { porFormulario(null); porAEditar(null); }}
                titulo={aEditar ? t('Corrigir lançamento') : t('Lançar horas')}
                subtitulo={t('O preço/hora do projeto congela nesta linha')}
                icone="fa-clock"
                cor="roxo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => { porFormulario(null); porAEditar(null); }}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={guardar.isPending}
                            onClick={() => formulario && guardar.mutate({
                                ...formulario,
                                projeto_id: formulario.projeto_id ? Number(formulario.projeto_id) : null,
                                tarefa_id: formulario.tarefa_id ? Number(formulario.tarefa_id) : null,
                                horas: Number(formulario.horas || 0),
                            })}>
                            {t('Guardar')}
                        </Botao>
                    </>
                }
            >
                {formulario && (
                    <div className="space-y-4">
                        <AvisoDeErro erro={guardar.error} />

                        <Campo etiqueta={t('Projeto')} obrigatorio erro={erros.projeto_id}
                            ajuda={t('Só os projetos a andar aceitam horas.')}>
                            <select value={formulario.projeto_id}
                                onChange={(e) => porFormulario({ ...formulario, projeto_id: e.target.value, tarefa_id: '' })}
                                className={entrada}>
                                <option value="">{t('Escolher…')}</option>
                                {o.projetos.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                            </select>
                        </Campo>

                        <Campo etiqueta={t('Tarefa')} erro={erros.tarefa_id}
                            ajuda={t('Opcional — mas é ela que liga as horas ao trabalho concreto.')}>
                            <select value={formulario.tarefa_id}
                                onChange={(e) => porFormulario({ ...formulario, tarefa_id: e.target.value })}
                                disabled={!formulario.projeto_id}
                                className={entrada}>
                                <option value="">{t('Sem tarefa')}</option>
                                {(tarefas.data?.data ?? []).map((x) => (
                                    <option key={x.valor} value={x.valor}>{x.rotulo}</option>
                                ))}
                            </select>
                        </Campo>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta={t('Dia')} obrigatorio erro={erros.data}>
                                <input type="date" value={formulario.data}
                                    onChange={(e) => porFormulario({ ...formulario, data: e.target.value })}
                                    className={entrada} />
                            </Campo>
                            <Campo etiqueta={t('Horas')} obrigatorio erro={erros.horas}>
                                <input type="number" step="0.25" min="0.25" max="24" value={formulario.horas}
                                    onChange={(e) => porFormulario({ ...formulario, horas: e.target.value })}
                                    className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                        </div>

                        <Campo etiqueta={t('O que se fez')} erro={erros.descricao}
                            ajuda={t('Aparece na linha da factura que o cliente recebe.')}>
                            <textarea rows={2} value={formulario.descricao}
                                onChange={(e) => porFormulario({ ...formulario, descricao: e.target.value })}
                                className={entrada} />
                        </Campo>

                        <label className="flex items-start gap-3">
                            <input type="checkbox" checked={formulario.facturavel}
                                onChange={(e) => porFormulario({ ...formulario, facturavel: e.target.checked })}
                                className="mt-0.5 h-5 w-5 rounded text-purple-600" />
                            <span>
                                <span className="block text-sm font-medium text-slate-700">{t('Facturável')}</span>
                                <span className="block text-xs text-slate-500">
                                    {t('Desligado, as horas contam para o consumo do orçamento mas não vão a factura nenhuma.')}
                                </span>
                            </span>
                        </label>
                    </div>
                )}
            </Modal>

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar lançamento')}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar.id)}>
                            {t('Apagar')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={apagar.error} />
                <p className="text-sm text-slate-600">
                    {t('Apagar :n horas de :dia? Uma hora já facturada não se apaga — está a sustentar um documento emitido.', {
                        n: String(aApagar?.horas ?? 0), dia: aApagar?.dia ?? '',
                    })}
                </p>
            </Modal>
        </div>
    );
}
