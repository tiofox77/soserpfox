import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { Lancamento, OpcoesDosLancamentos } from '@/api/contabilidade';
import { contabilidade } from '@/api/contabilidade';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { IntervaloDeDatas, PorPagina } from '@/ui/FiltrosComuns';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

import { ACCAO_DA_FAIXA, Faixa } from '../facturacao/faixa';

/**
 * OS LANÇAMENTOS CONTABILÍSTICOS.
 *
 * AS REGRAS VIVEM NO SERVIDOR (`Services\Accounting\Lancamentos`) e este ecrã
 * mostra-as antes de as bater com a cabeça. O que o ecrã antigo verificava era
 * o equilíbrio e o período fechado ao criar; o que passava:
 *
 *  · um lançamento todo a zeros (zero é igual a zero);
 *  · uma linha com débito E crédito ao mesmo tempo;
 *  · um lançamento numa conta de AGREGAÇÃO, a contar o valor duas vezes;
 *  · uma data fora do período a que o lançamento diz pertencer;
 *  · um rascunho de Janeiro confirmado em Março, num período já fechado;
 *  · e APAGAR um lançamento CONFIRMADO — reescrever a contabilidade sem rasto.
 *
 * UM CONFIRMADO NÃO SE APAGA: ESTORNA-SE. Fica o original e fica o estorno, e
 * é essa a diferença entre corrigir e fazer de conta que nunca aconteceu.
 */

type Filtros = {
    procura?: string;
    estado?: string;
    diario?: number | '';
    periodo?: number | '';
    de?: string;
    ate?: string;
    por_pagina?: number;
    page?: number;
};

export default function Lancamentos() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<Filtros>({ por_pagina: 25, page: 1 });
    const [recado, porRecado] = useRecadoNoCanto('');
    const [aCriar, porACriar] = useState(false);
    const [aVer, porAVer] = useState<number | null>(null);
    const [aConfirmar, porAConfirmar] = useState<Lancamento | null>(null);
    const [aApagar, porAApagar] = useState<Lancamento | null>(null);
    const [aEstornar, porAEstornar] = useState<Lancamento | null>(null);

    const opcoes = useQuery({
        queryKey: ['contabilidade', 'lancamentos', 'opcoes'],
        queryFn: contabilidade.lancamentos.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['contabilidade', 'lancamentos', filtros],
        queryFn: () => contabilidade.lancamentos.listar(filtros),
        placeholderData: keepPreviousData,
    });

    function feito(mensagem: string) {
        porRecado(mensagem);
        void cache.invalidateQueries({ queryKey: ['contabilidade'] });
    }

    const confirmar = useMutation({
        mutationFn: (m: Lancamento) => contabilidade.lancamentos.confirmar(m.id),
        onSuccess: (r) => {
            porAConfirmar(null);
            feito(r.message);
        },
    });

    const apagar = useMutation({
        mutationFn: (m: Lancamento) => contabilidade.lancamentos.apagar(m.id),
        onSuccess: (r) => {
            porAApagar(null);
            feito(r.message);
        },
    });

    if (opcoes.isPending) return <Carregando linhas={10} />;

    if (opcoes.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os lançamentos')}</h2>
                <p className="text-sm text-red-800">
                    {opcoes.error instanceof ErroDaApi ? opcoes.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const resumo = lista.data?.resumo;

    const comFiltro = Boolean(
        filtros.procura || filtros.estado || filtros.diario || filtros.periodo || filtros.de || filtros.ate,
    );

    const mudar = (campos: Partial<Filtros>) => porFiltros((f) => ({ ...f, ...campos, page: 1 }));

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Lançamentos Contabilísticos')}
                subtitulo={t('Registo de movimentos por diário e período')}
                icone="fa-file-invoice"
                cor="bom"
                accoes={
                    o.permissoes.gerir && (
                        <button type="button" onClick={() => porACriar(true)} className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-plus" aria-hidden="true" />
                            {t('Novo Lançamento')}
                        </button>
                    )
                }
            >
                {o.periodos.length === 0 && (
                    <span className={cls('inline-flex items-center gap-2 bg-white/20 px-3 py-1.5 text-sm font-semibold text-white', RAIO)}>
                        <i className="fas fa-triangle-exclamation" aria-hidden="true" />
                        {t('Não há períodos abertos — nada se pode lançar.')}
                    </span>
                )}
            </Faixa>

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            <ErroDaAccao erro={confirmar.error ?? apagar.error} />

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <CartaoNumero rotulo={t('Total Lançamentos')} valor={resumo.total.toLocaleString('pt-PT')} icone="fa-file-invoice" tom="indigo" aspecto="claro" nota={comFiltro ? t('Nos filtros escolhidos') : undefined} />
                    <CartaoNumero rotulo={t('Rascunhos')} valor={resumo.rascunhos.toLocaleString('pt-PT')} icone="fa-pen-to-square" tom="ambar" aspecto="claro" nota={t('Não entram em saldo nenhum')} />
                    <CartaoNumero rotulo={t('Lançados')} valor={resumo.confirmados.toLocaleString('pt-PT')} icone="fa-check-circle" tom="verde" aspecto="claro" />
                    <CartaoNumero rotulo={t('Este Mês')} valor={resumo.do_mes.toLocaleString('pt-PT')} icone="fa-calendar" tom="azul" aspecto="claro" />
                </div>
            )}

            {/* OS RASCUNHOS PRESOS. Ficavam ali para sempre a parecer trabalho
                por acabar, e ninguém sabia porquê. */}
            {(resumo?.presos ?? 0) > 0 && (
                <div className={cls('flex flex-wrap items-center justify-between gap-3 border-2 border-amber-300 bg-amber-50 px-5 py-3.5', RAIO)} role="status">
                    <span className="text-sm text-amber-900">
                        <i className="fas fa-lock mr-2" aria-hidden="true" />
                        {t('Há :n rascunho(s) em períodos já fechados: não se podem confirmar. Reabra o período ou lance-os de novo num período aberto.', {
                            n: resumo?.presos ?? 0,
                        })}
                    </span>
                    <a href="/accounting/periods" className={cls('text-sm font-semibold text-amber-800 underline underline-offset-2 hover:text-amber-950', FOCO, RAIO)}>
                        {t('Ver os períodos')} <i className="fas fa-arrow-right ml-1 text-xs" aria-hidden="true" />
                    </a>
                </div>
            )}

            <Cartao titulo={t('Filtrar')} icone="fa-filter">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <Campo etiqueta={t('Pesquisar Referência')} className="xl:col-span-2">
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => mudar({ procura: e.target.value })}
                            placeholder={t('Referência ou descrição…')}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Diário')}>
                        <select
                            value={filtros.diario ?? ''}
                            onChange={(e) => mudar({ diario: e.target.value ? Number(e.target.value) : '' })}
                            className={entrada}
                        >
                            <option value="">{t('Todos os diários')}</option>
                            {o.diarios.map((d) => <option key={d.valor} value={d.valor}>{d.rotulo}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Estado')}>
                        <select value={filtros.estado ?? ''} onChange={(e) => mudar({ estado: e.target.value })} className={entrada}>
                            <option value="">{t('Todos os estados')}</option>
                            {o.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>

                    <IntervaloDeDatas
                        de={filtros.de}
                        ate={filtros.ate}
                        aoMudar={(campo, valor) => mudar({ [campo]: valor })}
                        rotulo={t('Data de')}
                    />
                </div>

                {comFiltro && (
                    <div className={cls('mt-4 flex flex-wrap items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-2.5', RAIO)}>
                        <span className="text-sm text-emerald-800">
                            <i className="fas fa-info-circle mr-1" aria-hidden="true" />
                            {t('A mostrar :quantos lançamento(s) — os totais acima seguem estes filtros.', {
                                quantos: (contas?.total ?? 0).toLocaleString('pt-PT'),
                            })}
                        </span>
                        <button
                            type="button"
                            onClick={() => porFiltros({ por_pagina: filtros.por_pagina, page: 1 })}
                            className={cls('text-sm font-semibold text-emerald-700 hover:text-emerald-900', FOCO, RAIO)}
                        >
                            <i className="fas fa-times mr-1" aria-hidden="true" />
                            {t('Limpar filtros')}
                        </button>
                    </div>
                )}
            </Cartao>

            <section className={cls(CARTAO, 'overflow-hidden')}>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-gradient-to-r from-emerald-50 to-green-50">
                            <tr>
                                {[t('Data'), t('Referência'), t('Diário'), t('Período')].map((c) => (
                                    <th key={c} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-emerald-700">{c}</th>
                                ))}
                                <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Débito')}</th>
                                <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Crédito')}</th>
                                <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Estado')}</th>
                                <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Ações')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {lista.isPending && (
                                <tr><td colSpan={8} className="px-4 py-8"><Carregando linhas={5} /></td></tr>
                            )}

                            {!lista.isPending && linhas.length === 0 && (
                                <tr>
                                    <td colSpan={8}>
                                        <SemNada
                                            icone="fa-file-invoice"
                                            titulo={t('Nenhum lançamento encontrado')}
                                            frase={comFiltro ? t('Experimente alargar o período ou limpar os filtros.') : t('Comece criando seu primeiro lançamento contabilístico')}
                                        />
                                    </td>
                                </tr>
                            )}

                            {linhas.map((m, i) => (
                                <tr key={m.id} style={cascata(i)} className="entra transition-colors hover:bg-emerald-50/60">
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-600">{m.dia ?? '—'}</td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <span className="rounded bg-slate-100 px-2 py-1 font-mono text-xs font-bold text-slate-700">{m.ref}</span>
                                        {m.nota && <p className="mt-0.5 max-w-xs truncate text-xs text-slate-400">{m.nota}</p>}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-700">{m.diario ?? '—'}</td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <span className="text-slate-700">{m.periodo ?? '—'}</span>
                                        {!m.periodo_aberto && (
                                            <span className="ml-1.5 inline-flex" title={t('Período fechado')}>
                                                <i className="fas fa-lock text-xs text-amber-600" aria-hidden="true" />
                                                <span className="sr-only">{t('Período fechado')}</span>
                                            </span>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-emerald-700">{kz(m.debito)}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-red-700">{kz(m.credito)}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-center">
                                        <Etiqueta cor={m.estado === 'posted' ? 'bom' : 'aviso'} ponto>{m.estado_rotulo}</Etiqueta>
                                        <p className="mt-0.5 text-[11px] text-slate-400">{t(':n linha(s)', { n: m.linhas })}</p>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1.5">
                                            <button
                                                type="button"
                                                onClick={() => porAVer(m.id)}
                                                title={t('Ver')}
                                                aria-label={t('Ver o lançamento :ref', { ref: m.ref })}
                                                className={cls(BOTAO_DE_ACCAO, 'border-cyan-200 bg-cyan-50 text-cyan-700')}
                                            >
                                                <i className="fas fa-eye" aria-hidden="true" />
                                            </button>

                                            {o.permissoes.gerir && m.pode_confirmar && (
                                                <button
                                                    type="button"
                                                    onClick={() => porAConfirmar(m)}
                                                    title={t('Confirmar')}
                                                    aria-label={t('Confirmar o lançamento :ref', { ref: m.ref })}
                                                    className={cls(BOTAO_DE_ACCAO, 'border-emerald-200 bg-emerald-50 text-emerald-700')}
                                                >
                                                    <i className="fas fa-check" aria-hidden="true" />
                                                </button>
                                            )}

                                            {o.permissoes.gerir && m.pode_apagar && (
                                                <button
                                                    type="button"
                                                    onClick={() => porAApagar(m)}
                                                    title={t('Eliminar rascunho')}
                                                    aria-label={t('Eliminar o rascunho :ref', { ref: m.ref })}
                                                    className={cls(BOTAO_DE_ACCAO, 'border-red-200 bg-red-50 text-red-700')}
                                                >
                                                    <i className="fas fa-trash" aria-hidden="true" />
                                                </button>
                                            )}

                                            {o.permissoes.gerir && m.pode_estornar && (
                                                <button
                                                    type="button"
                                                    onClick={() => porAEstornar(m)}
                                                    title={t('Estornar')}
                                                    aria-label={t('Estornar o lançamento :ref', { ref: m.ref })}
                                                    className={cls(BOTAO_DE_ACCAO, 'border-amber-200 bg-amber-50 text-amber-700')}
                                                >
                                                    <i className="fas fa-rotate-left" aria-hidden="true" />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3">
                    <PorPagina valor={filtros.por_pagina} aoMudar={(n) => mudar({ por_pagina: n })} />

                    {contas && contas.last_page > 1 && (
                        <div className="flex items-center gap-3 text-sm text-slate-600">
                            <Botao
                                icone="fa-chevron-left"
                                altura="pequeno"
                                disabled={contas.current_page <= 1}
                                onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}
                            >
                                {t('Anterior')}
                            </Botao>
                            <span>{t('Página :pagina de :ultima', { pagina: contas.current_page, ultima: contas.last_page })}</span>
                            <Botao
                                icone="fa-chevron-right"
                                altura="pequeno"
                                disabled={contas.current_page >= contas.last_page}
                                onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}
                            >
                                {t('Seguinte')}
                            </Botao>
                        </div>
                    )}
                </div>
            </section>

            <ModalDoLancamento
                aberto={aCriar}
                o={o}
                aoFechar={() => porACriar(false)}
                aoGravar={(mensagem) => {
                    porACriar(false);
                    feito(mensagem);
                }}
            />

            <FichaDoLancamentoModal id={aVer} aoFechar={() => porAVer(null)} />

            <ModalDoEstorno
                lancamento={aEstornar}
                o={o}
                aoFechar={() => porAEstornar(null)}
                aoGravar={(mensagem) => {
                    porAEstornar(null);
                    feito(mensagem);
                }}
            />

            {/* CONFIRMAR — o que deixa de se poder fazer depois. */}
            <Modal
                aberto={aConfirmar !== null}
                aoFechar={() => porAConfirmar(null)}
                titulo={t('Confirmar lançamento')}
                subtitulo={aConfirmar?.ref}
                icone="fa-check-double"
                cor="bom"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAConfirmar(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="bom"
                            tom="solida"
                            icone="fa-check"
                            aTrabalhar={confirmar.isPending}
                            onClick={() => aConfirmar && confirmar.mutate(aConfirmar)}
                        >
                            {t('Confirmar')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={confirmar.error} />

                <p className="text-sm text-slate-700">
                    {t('O lançamento passa a contar nos saldos e deixa de se poder apagar. A partir daí corrige-se por estorno, que deixa rasto dos dois.')}
                </p>

                {aConfirmar && (
                    <dl className={cls('mt-3 grid gap-2 border border-slate-200 bg-slate-50 px-4 py-3 text-sm sm:grid-cols-2', RAIO)}>
                        <div><dt className="text-xs uppercase tracking-wider text-slate-500">{t('Data')}</dt><dd className="font-semibold text-slate-900">{aConfirmar.dia ?? '—'}</dd></div>
                        <div><dt className="text-xs uppercase tracking-wider text-slate-500">{t('Diário')}</dt><dd className="font-semibold text-slate-900">{aConfirmar.diario ?? '—'}</dd></div>
                        <div><dt className="text-xs uppercase tracking-wider text-slate-500">{t('Débito')}</dt><dd className="font-semibold tabular-nums text-emerald-700">{kz(aConfirmar.debito)} Kz</dd></div>
                        <div><dt className="text-xs uppercase tracking-wider text-slate-500">{t('Crédito')}</dt><dd className="font-semibold tabular-nums text-red-700">{kz(aConfirmar.credito)} Kz</dd></div>
                    </dl>
                )}
            </Modal>

            {/* APAGAR — só rascunhos, e diz-se. */}
            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar rascunho')}
                subtitulo={aApagar?.ref}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo"
                            tom="solida"
                            icone="fa-trash"
                            aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar)}
                        >
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={apagar.error} />

                <p className="text-sm text-slate-700">
                    {t('O rascunho e as suas linhas desaparecem. Como nunca foi confirmado, não mexeu saldo nenhum.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── A janela de lançar ──────────────────────────────────────────────── */

type LinhaNova = { account_id: string; debit: string; credit: string; narration: string };

const LINHA_VAZIA: LinhaNova = { account_id: '', debit: '', credit: '', narration: '' };

/**
 * O LANÇAMENTO, LINHA A LINHA.
 *
 * O EQUILÍBRIO VÊ-SE ENQUANTO SE ESCREVE. O ecrã antigo somava ao gravar e
 * devolvia «o lançamento não está equilibrado» depois de o formulário todo
 * estar preenchido — e não dizia por quanto. Aqui a diferença está debaixo das
 * linhas, sempre, e o botão de gravar diz o que falta.
 *
 * A REFERÊNCIA VEM DO SERVIDOR, sob tranca, e só ao gravar. A antiga saía ao
 * escolher o diário e só se incrementava ao gravar: duas pessoas a lançar ao
 * mesmo tempo levavam a mesma. O campo fica editável para quem tem numeração
 * própria, mas vazio quer dizer «dá-me a seguinte».
 */
function ModalDoLancamento({
    aberto,
    o,
    aoFechar,
    aoGravar,
}: {
    aberto: boolean;
    o: OpcoesDosLancamentos;
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const hoje = new Date().toISOString().slice(0, 10);

    const [f, porF] = useState({
        journal_id: '',
        period_id: '',
        document_type_id: '',
        date: hoje,
        ref: '',
        narration: '',
    });
    const [linhas, porLinhas] = useState<LinhaNova[]>([{ ...LINHA_VAZIA }, { ...LINHA_VAZIA }]);

    useEffect(() => {
        if (!aberto) return;

        porF({
            journal_id: o.diarios[0]?.valor ?? '',
            // O PERÍODO DE HOJE já escolhido: é o que se usa em nove casos em dez.
            period_id: o.periodo_de_hoje ? String(o.periodo_de_hoje) : (o.periodos[0]?.valor ?? ''),
            document_type_id: '',
            date: hoje,
            ref: '',
            narration: '',
        });
        porLinhas([{ ...LINHA_VAZIA }, { ...LINHA_VAZIA }]);
    }, [aberto, o, hoje]);

    const gravar = useMutation({
        mutationFn: () => contabilidade.lancamentos.criar({
            journal_id: f.journal_id ? Number(f.journal_id) : null,
            period_id: f.period_id ? Number(f.period_id) : null,
            document_type_id: f.document_type_id ? Number(f.document_type_id) : null,
            date: f.date,
            ref: f.ref.trim() || null,
            narration: f.narration.trim() || null,
            lines: linhas.map((l) => ({
                account_id: l.account_id ? Number(l.account_id) : null,
                debit: l.debit ? Number(l.debit) : 0,
                credit: l.credit ? Number(l.credit) : 0,
                narration: l.narration.trim() || null,
            })),
        }),
        onSuccess: (r) => aoGravar(r.message),
    });

    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    const debito = linhas.reduce((s, l) => s + (Number(l.debit) || 0), 0);
    const credito = linhas.reduce((s, l) => s + (Number(l.credit) || 0), 0);
    const diferenca = Math.round((debito - credito) * 100) / 100;
    const equilibrado = diferenca === 0 && debito > 0;

    /** O período escolhido manda nas datas aceitáveis. */
    const periodo = o.periodos.find((p) => p.valor === f.period_id);

    const mexerLinha = (i: number, campo: keyof LinhaNova, valor: string) =>
        porLinhas((xs) => xs.map((l, j) => {
            if (j !== i) return l;

            // DÉBITO E CRÉDITO NA MESMA LINHA não existe: escrever num limpa o
            // outro, em vez de deixar montar uma linha que o servidor recusa.
            if (campo === 'debit' && valor) return { ...l, debit: valor, credit: '' };
            if (campo === 'credit' && valor) return { ...l, credit: valor, debit: '' };

            return { ...l, [campo]: valor };
        }));

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={t('Novo Lançamento')}
            subtitulo={t('Débito e crédito têm de dar o mesmo — e nenhuma linha pode ter os dois')}
            icone="fa-file-invoice"
            cor="bom"
            largura="xl"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times">{t('Cancelar')}</Botao>
                    <Botao
                        cor="bom"
                        tom="solida"
                        icone="fa-save"
                        aTrabalhar={gravar.isPending}
                        disabled={!equilibrado}
                        onClick={() => gravar.mutate()}
                    >
                        {equilibrado
                            ? t('Guardar em rascunho')
                            : debito === 0 && credito === 0
                                ? t('Preencha os valores')
                                : t('Faltam :valor Kz', { valor: kz(Math.abs(diferenca)) })}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={gravar.error} />

            {erros.geral?.[0] && (
                <div role="alert" className={cls('mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
                    <i className="fas fa-circle-exclamation mr-2" aria-hidden="true" />
                    {erros.geral[0]}
                </div>
            )}

            {o.periodos.length === 0 && (
                <div className={cls('mb-4 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)} role="alert">
                    <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                    {t('Não há períodos abertos nesta empresa. Abra um período antes de lançar.')}
                </div>
            )}

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Campo etiqueta={t('Diário')} obrigatorio erro={erros.journal_id}>
                    <select value={f.journal_id} onChange={(e) => porF((x) => ({ ...x, journal_id: e.target.value }))} className={entrada}>
                        <option value="">{t('Selecione o diário…')}</option>
                        {o.diarios.map((d) => <option key={d.valor} value={d.valor}>{d.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Tipo de Documento')} erro={erros.document_type_id}>
                    <select value={f.document_type_id} onChange={(e) => porF((x) => ({ ...x, document_type_id: e.target.value }))} className={entrada}>
                        <option value="">{t('Nenhum')}</option>
                        {o.tipos_de_documento.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo
                    etiqueta={t('Período')}
                    obrigatorio
                    erro={erros.period_id}
                    ajuda={t('Só aparecem os períodos abertos.')}
                >
                    <select value={f.period_id} onChange={(e) => porF((x) => ({ ...x, period_id: e.target.value }))} className={entrada}>
                        <option value="">{t('Selecione o período…')}</option>
                        {o.periodos.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo
                    etiqueta={t('Data')}
                    obrigatorio
                    erro={erros.date}
                    ajuda={periodo?.de && periodo?.ate
                        ? t('O período vai de :de a :ate.', { de: periodo.de, ate: periodo.ate })
                        : undefined}
                >
                    <input
                        type="date"
                        value={f.date}
                        // A DATA TEM DE CAIR NO PERÍODO: o servidor recusa, e
                        // dizê-lo no calendário poupa a viagem.
                        min={periodo?.de ?? undefined}
                        max={periodo?.ate ?? undefined}
                        onChange={(e) => porF((x) => ({ ...x, date: e.target.value }))}
                        className={entrada}
                    />
                </Campo>

                <Campo
                    etiqueta={t('Referência')}
                    erro={erros.ref}
                    ajuda={t('Deixe vazio para o diário dar a seguinte, sem risco de repetir.')}
                >
                    <input
                        type="text"
                        value={f.ref}
                        onChange={(e) => porF((x) => ({ ...x, ref: e.target.value }))}
                        placeholder={t('Automática')}
                        className={cls(entrada, 'font-mono')}
                    />
                </Campo>

                <Campo etiqueta={t('Descrição')} erro={erros.narration} className="md:col-span-2 lg:col-span-3">
                    <input
                        type="text"
                        value={f.narration}
                        onChange={(e) => porF((x) => ({ ...x, narration: e.target.value }))}
                        placeholder={t('Descrição do lançamento…')}
                        className={entrada}
                    />
                </Campo>
            </div>

            {/* AS LINHAS */}
            <div className="mt-5">
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h3 className="flex items-center gap-2 text-sm font-bold text-slate-900">
                        <i className="fas fa-list-ol text-emerald-600" aria-hidden="true" />
                        {t('Linhas do lançamento')}
                    </h3>
                    <Botao
                        altura="pequeno"
                        icone="fa-plus"
                        onClick={() => porLinhas((xs) => [...xs, { ...LINHA_VAZIA }])}
                    >
                        {t('Adicionar linha')}
                    </Botao>
                </div>

                {erros.lines?.[0] && (
                    <p role="alert" className="mb-2 text-xs font-medium text-red-600">{erros.lines[0]}</p>
                )}

                <div className="space-y-2">
                    {linhas.map((l, i) => (
                        <div
                            key={i}
                            style={cascata(i)}
                            className={cls('entra grid gap-2 border border-slate-200 bg-slate-50/60 p-2.5 sm:grid-cols-12', RAIO)}
                        >
                            <div className="sm:col-span-4">
                                <Rotulo>{t('Conta')}</Rotulo>
                                <select
                                    value={l.account_id}
                                    onChange={(e) => mexerLinha(i, 'account_id', e.target.value)}
                                    aria-label={t('Conta da linha :n', { n: i + 1 })}
                                    className={entrada}
                                >
                                    <option value="">{t('Selecione a conta…')}</option>
                                    {o.contas.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                                </select>
                                {erros[`lines.${i}.account_id`]?.[0] && (
                                    <p role="alert" className="mt-1 text-xs font-medium text-red-600">{erros[`lines.${i}.account_id`]?.[0]}</p>
                                )}
                            </div>

                            <div className="sm:col-span-2">
                                <Rotulo>{t('Débito (Kz)')}</Rotulo>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={l.debit}
                                    onChange={(e) => mexerLinha(i, 'debit', e.target.value)}
                                    aria-label={t('Débito da linha :n', { n: i + 1 })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </div>

                            <div className="sm:col-span-2">
                                <Rotulo>{t('Crédito (Kz)')}</Rotulo>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={l.credit}
                                    onChange={(e) => mexerLinha(i, 'credit', e.target.value)}
                                    aria-label={t('Crédito da linha :n', { n: i + 1 })}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </div>

                            <div className="sm:col-span-3">
                                <Rotulo>{t('Observação')}</Rotulo>
                                <input
                                    type="text"
                                    value={l.narration}
                                    onChange={(e) => mexerLinha(i, 'narration', e.target.value)}
                                    placeholder={t('Observação desta linha…')}
                                    aria-label={t('Observação da linha :n', { n: i + 1 })}
                                    className={entrada}
                                />
                            </div>

                            <div className="flex items-end sm:col-span-1">
                                <button
                                    type="button"
                                    // DUAS LINHAS SÃO O MÍNIMO: um lançamento com
                                    // uma linha só nunca pode equilibrar.
                                    disabled={linhas.length <= 2}
                                    onClick={() => porLinhas((xs) => xs.filter((_, j) => j !== i))}
                                    title={t('Remover linha')}
                                    aria-label={t('Remover a linha :n', { n: i + 1 })}
                                    className={cls(
                                        'h-10 w-full border border-red-200 bg-red-50 text-red-600',
                                        'transition-all duration-200 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-40',
                                        RAIO,
                                        FOCO,
                                    )}
                                >
                                    <i className="fas fa-trash" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>

                {/* O EQUILÍBRIO, ENQUANTO SE ESCREVE. */}
                <div
                    className={cls(
                        'mt-3 flex flex-wrap items-center justify-between gap-3 border-2 px-4 py-3',
                        RAIO,
                        equilibrado
                            ? 'border-emerald-300 bg-emerald-50'
                            : 'border-amber-300 bg-amber-50',
                    )}
                    role="status"
                >
                    <div className="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
                        <span className="text-slate-600">
                            {t('Débito')}: <strong className="tabular-nums text-emerald-700">{kz(debito)} Kz</strong>
                        </span>
                        <span className="text-slate-600">
                            {t('Crédito')}: <strong className="tabular-nums text-red-700">{kz(credito)} Kz</strong>
                        </span>
                    </div>

                    <span className={cls('text-sm font-bold', equilibrado ? 'text-emerald-800' : 'text-amber-900')}>
                        <i className={cls('fas mr-2', equilibrado ? 'fa-scale-balanced' : 'fa-scale-unbalanced')} aria-hidden="true" />
                        {equilibrado
                            ? t('Equilibrado')
                            : debito === 0 && credito === 0
                                ? t('Ainda sem valores — um lançamento todo a zeros não é um lançamento.')
                                : t('Diferença de :valor Kz', { valor: kz(Math.abs(diferenca)) })}
                    </span>
                </div>
            </div>
        </Modal>
    );
}

/* ─── A janela de ver ─────────────────────────────────────────────────── */

function FichaDoLancamentoModal({ id, aoFechar }: { id: number | null; aoFechar: () => void }) {
    const ficha = useQuery({
        queryKey: ['contabilidade', 'lancamentos', 'ficha', id],
        queryFn: () => contabilidade.lancamentos.ficha(id as number),
        enabled: id !== null,
    });

    const m = ficha.data?.data;

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={m ? m.ref : t('Lançamento')}
            subtitulo={t('O lançamento e as suas linhas')}
            icone="fa-file-invoice"
            cor="ciano"
            largura="lg"
            rodape={<Botao onClick={aoFechar} icone="fa-times">{t('Fechar')}</Botao>}
        >
            {ficha.isPending || !m ? (
                <Carregando linhas={8} />
            ) : (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <Etiqueta cor={m.estado === 'posted' ? 'bom' : 'aviso'} ponto>{m.estado_rotulo}</Etiqueta>
                        {!m.periodo_aberto && <Etiqueta cor="aviso" icone="fa-lock">{t('Período fechado')}</Etiqueta>}
                    </div>

                    <dl className={cls('grid gap-3 border border-slate-200 bg-slate-50 px-4 py-3.5 sm:grid-cols-3', RAIO)}>
                        <Valor rotulo={t('Data')}>{m.dia ?? '—'}</Valor>
                        <Valor rotulo={t('Diário')}>{m.diario ?? '—'}</Valor>
                        <Valor rotulo={t('Período')}>{m.periodo ?? '—'}</Valor>
                        <Valor rotulo={t('Tipo de Documento')}>{m.tipo_de_documento ?? '—'}</Valor>
                        <Valor rotulo={t('Criado por')}>{m.autor ?? '—'}</Valor>
                        <Valor rotulo={t('Confirmado')}>
                            {m.confirmado_em ? `${m.confirmado_em}${m.confirmado_por ? ` · ${m.confirmado_por}` : ''}` : '—'}
                        </Valor>
                        {m.nota && <Valor rotulo={t('Descrição')} className="sm:col-span-3">{m.nota}</Valor>}
                    </dl>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th scope="col" className="px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t('Conta')}</th>
                                    <th scope="col" className="px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{t('Observação')}</th>
                                    <th scope="col" className="px-3 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t('Débito')}</th>
                                    <th scope="col" className="px-3 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{t('Crédito')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {m.linhas_do_lancamento.map((l, i) => (
                                    <tr key={l.id} style={cascata(i)} className="entra">
                                        <td className="px-3 py-2.5 font-semibold text-slate-800">{l.conta ?? '—'}</td>
                                        <td className="max-w-xs truncate px-3 py-2.5 text-slate-600">{l.nota ?? '—'}</td>
                                        <td className="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-emerald-700">{l.debito > 0 ? kz(l.debito) : '—'}</td>
                                        <td className="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-red-700">{l.credito > 0 ? kz(l.credito) : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t-2 border-slate-300 bg-slate-50">
                                <tr>
                                    <td colSpan={2} className="px-3 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-600">{t('Totais')}</td>
                                    <td className="px-3 py-2.5 text-right font-bold tabular-nums text-emerald-700">{kz(m.debito)}</td>
                                    <td className="px-3 py-2.5 text-right font-bold tabular-nums text-red-700">{kz(m.credito)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            )}
        </Modal>
    );
}

/* ─── O estorno ───────────────────────────────────────────────────────── */

/**
 * ESTORNAR: um lançamento simétrico que desfaz o outro.
 *
 * A data e o período do estorno podem não ser os do original — o mais comum é
 * o original estar num período que já fechou. Vazio quer dizer «hoje, no
 * período de hoje».
 */
function ModalDoEstorno({
    lancamento,
    o,
    aoFechar,
    aoGravar,
}: {
    lancamento: Lancamento | null;
    o: OpcoesDosLancamentos;
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const [dados, porDados] = useState({ date: '', period_id: '' });

    useEffect(() => {
        if (lancamento) porDados({ date: '', period_id: '' });
    }, [lancamento]);

    const estornar = useMutation({
        mutationFn: () => contabilidade.lancamentos.estornar((lancamento as Lancamento).id, {
            date: dados.date || null,
            period_id: dados.period_id ? Number(dados.period_id) : null,
        }),
        onSuccess: (r) => aoGravar(r.message),
    });

    const erros = estornar.error instanceof ErroDaApi ? estornar.error.erros : {};
    const periodo = o.periodos.find((p) => p.valor === dados.period_id);

    return (
        <Modal
            aberto={lancamento !== null}
            aoFechar={aoFechar}
            titulo={t('Estornar lançamento')}
            subtitulo={lancamento?.ref}
            icone="fa-rotate-left"
            cor="aviso"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao
                        cor="aviso"
                        tom="solida"
                        icone="fa-rotate-left"
                        aTrabalhar={estornar.isPending}
                        onClick={() => estornar.mutate()}
                    >
                        {t('Estornar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={estornar.error} />

            {erros.geral?.[0] && (
                <div role="alert" className={cls('mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
                    <i className="fas fa-circle-exclamation mr-2" aria-hidden="true" />
                    {erros.geral[0]}
                </div>
            )}

            <p className="text-sm text-slate-700">
                {t('Cria-se um lançamento simétrico — o débito de cada linha passa a crédito e ao contrário — e confirma-se logo. O original FICA: é essa a diferença entre corrigir e apagar.')}
            </p>

            {lancamento && (
                <dl className={cls('mt-3 grid gap-2 border border-slate-200 bg-slate-50 px-4 py-3 text-sm sm:grid-cols-3', RAIO)}>
                    <Valor rotulo={t('Original')}>{lancamento.ref}</Valor>
                    <Valor rotulo={t('Data')}>{lancamento.dia ?? '—'}</Valor>
                    <Valor rotulo={t('Valor')}>{kz(lancamento.debito)} Kz</Valor>
                </dl>
            )}

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <Campo
                    etiqueta={t('Período do estorno')}
                    erro={erros.period_id}
                    ajuda={t('Vazio usa o período do original, se ainda estiver aberto.')}
                >
                    <select value={dados.period_id} onChange={(e) => porDados((x) => ({ ...x, period_id: e.target.value }))} className={entrada}>
                        <option value="">{t('O mesmo do original')}</option>
                        {o.periodos.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo
                    etiqueta={t('Data do estorno')}
                    erro={erros.date}
                    ajuda={t('Vazio usa a data de hoje.')}
                >
                    <input
                        type="date"
                        value={dados.date}
                        min={periodo?.de ?? undefined}
                        max={periodo?.ate ?? undefined}
                        onChange={(e) => porDados((x) => ({ ...x, date: e.target.value }))}
                        className={entrada}
                    />
                </Campo>
            </div>
        </Modal>
    );
}

/* ─── As peças pequenas ───────────────────────────────────────────────── */

const BOTAO_DE_ACCAO = cls(
    'inline-flex items-center gap-1.5 border px-2.5 py-1.5 text-xs font-semibold',
    'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm',
    RAIO,
    FOCO,
);

function ErroDaAccao({ erro }: { erro: unknown }) {
    if (!erro) return null;

    const daApi = erro instanceof ErroDaApi ? erro : null;
    const geral = daApi?.erros.geral?.[0];

    return (
        <div role="alert" className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
            <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
            {geral ?? daApi?.message ?? t('A operação não foi concluída.')}
        </div>
    );
}

function Valor({
    rotulo,
    children,
    className,
}: {
    rotulo: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={className}>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</dt>
            <dd className="text-sm font-semibold text-slate-900">{children}</dd>
        </div>
    );
}
