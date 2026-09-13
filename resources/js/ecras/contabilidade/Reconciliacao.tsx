import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useRef, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { LinhaDoExtracto, Reconciliacao } from '@/api/contabilidade';
import { contabilidade } from '@/api/contabilidade';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

import { ACCAO_DA_FAIXA, Faixa } from '../facturacao/faixa';

/**
 * A RECONCILIAÇÃO BANCÁRIA — o extracto do banco contra os lançamentos.
 *
 * IMPORTAR UM EXTRACTO NUNCA FUNCIONOU: o serviço procurava no `MoveLine` uma
 * relação que não existia, e o Eloquent atirava «Call to undefined relationship»
 * no auto-match que corre no fim da importação. O ecrã apanhava a excepção e
 * mostrava-a como «Erro ao importar». Desde o primeiro dia.
 *
 * E SE TIVESSE FUNCIONADO, não havia por onde continuar: o botão «Ver» da lista
 * era um `<button>` sem clique nenhum. As linhas do extracto não se viam, não se
 * casavam à mão, e o casamento manual e as sugestões — escritos e completos no
 * serviço — não tinham quem os chamasse.
 *
 * É esse ecrã que aqui existe: abrir o extracto, ver cada linha, aceitar a
 * sugestão ou escolher outra, desfazer, e aprovar quando tudo bate.
 */
export default function Reconciliacao() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{ conta?: number | ''; estado?: string; por_pagina?: number; page?: number }>({
        por_pagina: 20, page: 1,
    });
    const [recado, porRecado] = useRecadoNoCanto('');
    const [aImportar, porAImportar] = useState(false);
    const [aVer, porAVer] = useState<number | null>(null);
    const [aApagar, porAApagar] = useState<Reconciliacao | null>(null);

    const lista = useQuery({
        queryKey: ['contabilidade', 'reconciliacao', filtros],
        queryFn: () => contabilidade.reconciliacao.listar(filtros),
        placeholderData: keepPreviousData,
    });

    function feito(mensagem: string) {
        porRecado(mensagem);
        void cache.invalidateQueries({ queryKey: ['contabilidade', 'reconciliacao'] });
    }

    const apagar = useMutation({
        mutationFn: (r: Reconciliacao) => contabilidade.reconciliacao.apagar(r.id),
        onSuccess: (r) => {
            porAApagar(null);
            feito(r.message);
        },
    });

    const aprovar = useMutation({
        mutationFn: (r: Reconciliacao) => contabilidade.reconciliacao.aprovar(r.id),
        onSuccess: (r) => feito(r.message),
    });

    if (lista.isPending) return <Carregando linhas={10} />;

    if (lista.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a reconciliação')}</h2>
                <p className="text-sm text-red-800">
                    {lista.error instanceof ErroDaApi ? lista.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const d = lista.data;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Reconciliação Bancária')}
                subtitulo={t('O extracto do banco contra os lançamentos da conta')}
                icone="fa-right-left"
                cor="teal"
                accoes={
                    d.permissoes.gerir && (
                        <button type="button" onClick={() => porAImportar(true)} className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-file-import" aria-hidden="true" />
                            {t('Importar extracto')}
                        </button>
                    )
                }
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            <ErroDaAccao erro={apagar.error ?? aprovar.error} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero rotulo={t('Conciliações')} valor={d.resumo.total.toLocaleString('pt-PT')} icone="fa-right-left" tom="teal" aspecto="claro" />
                <CartaoNumero rotulo={t('Por conciliar')} valor={d.resumo.por_conciliar.toLocaleString('pt-PT')} icone="fa-hourglass-half" tom="ambar" aspecto="claro" />
                <CartaoNumero rotulo={t('Conciliadas')} valor={d.resumo.conciliadas.toLocaleString('pt-PT')} icone="fa-circle-check" tom="verde" aspecto="claro" />
                <CartaoNumero
                    rotulo={t('Com diferença')}
                    valor={d.resumo.com_diferenca.toLocaleString('pt-PT')}
                    icone="fa-scale-unbalanced"
                    tom={d.resumo.com_diferenca > 0 ? 'vermelho' : 'verde'}
                    aspecto="claro"
                    nota={t('Linhas do extracto sem lançamento')}
                />
            </div>

            <Cartao titulo={t('Filtrar')} icone="fa-filter">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo etiqueta={t('Conta')}>
                        <select
                            value={filtros.conta ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, conta: e.target.value ? Number(e.target.value) : '', page: 1 }))}
                            className={entrada}
                        >
                            <option value="">{t('Todas')}</option>
                            {d.contas.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Estado')}>
                        <select
                            value={filtros.estado ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value, page: 1 }))}
                            className={entrada}
                        >
                            <option value="">{t('Todos os estados')}</option>
                            {d.estados.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>
                </div>
            </Cartao>

            {d.data.length === 0 ? (
                <section className={cls(CARTAO, 'overflow-hidden')}>
                    <SemNada
                        icone="fa-right-left"
                        titulo={t('Nenhuma conciliação')}
                        frase={d.permissoes.gerir
                            ? t('Importe o extracto do banco: cada linha dele vai procurar o lançamento que lhe corresponde.')
                            : t('Peça a quem gere a contabilidade para importar o extracto do banco.')}
                    />
                </section>
            ) : (
                <section className={cls(CARTAO, 'overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-gradient-to-r from-teal-50 to-cyan-50">
                                <tr>
                                    {[t('Data'), t('Conta')].map((c) => (
                                        <th key={c} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-teal-700">{c}</th>
                                    ))}
                                    {[t('Saldo do extracto'), t('Saldo contabilístico'), t('Diferença')].map((c) => (
                                        <th key={c} scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-teal-700">{c}</th>
                                    ))}
                                    <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-teal-700">{t('Linhas')}</th>
                                    <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-teal-700">{t('Estado')}</th>
                                    <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-teal-700">{t('Ações')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.data.map((r, i) => (
                                    <tr key={r.id} style={cascata(i)} className="entra transition-colors hover:bg-teal-50/60">
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-600">{r.dia}</td>
                                        <td className="px-4 py-3 text-slate-700">{r.conta ?? '—'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700">{kz(r.saldo_do_extracto)}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700">{kz(r.saldo_contabilistico)}</td>
                                        <td className={cls('whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums',
                                            r.diferenca === 0 ? 'text-emerald-700' : 'text-red-700')}>
                                            {kz(r.diferenca)}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-center">
                                            {/* QUANTAS FALTAM, de relance: é o trabalho que sobra. */}
                                            <span className="font-semibold tabular-nums text-slate-900">{r.casadas}</span>
                                            <span className="text-slate-400"> / {r.linhas}</span>
                                            {r.por_casar > 0 && (
                                                <span className="mt-0.5 block text-[11px] font-semibold text-amber-700">
                                                    {t(':n por casar', { n: r.por_casar })}
                                                </span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-center">
                                            <Etiqueta
                                                cor={r.estado === 'approved' ? 'primaria' : r.estado === 'reconciled' ? 'bom' : 'aviso'}
                                                ponto
                                            >
                                                {r.estado_rotulo}
                                            </Etiqueta>
                                            {r.conciliado_em && (
                                                <span className="mt-0.5 block text-[11px] text-slate-400">{r.conciliado_em}</span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1.5">
                                                {/* O BOTÃO QUE NÃO FAZIA NADA. */}
                                                <button
                                                    type="button"
                                                    onClick={() => porAVer(r.id)}
                                                    title={t('Abrir o extracto')}
                                                    aria-label={t('Abrir o extracto de :dia', { dia: r.dia })}
                                                    className={cls(BOTAO_DE_ACCAO, 'border-cyan-200 bg-cyan-50 text-cyan-700')}
                                                >
                                                    <i className="fas fa-eye" aria-hidden="true" />
                                                </button>

                                                {d.permissoes.gerir && r.estado === 'reconciled' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => aprovar.mutate(r)}
                                                        disabled={aprovar.isPending}
                                                        title={t('Aprovar')}
                                                        aria-label={t('Aprovar a conciliação de :dia', { dia: r.dia })}
                                                        className={cls(BOTAO_DE_ACCAO, 'border-emerald-200 bg-emerald-50 text-emerald-700')}
                                                    >
                                                        <i className="fas fa-check-double" aria-hidden="true" />
                                                    </button>
                                                )}

                                                {d.permissoes.gerir && r.estado !== 'approved' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => porAApagar(r)}
                                                        title={t('Eliminar')}
                                                        aria-label={t('Eliminar a conciliação de :dia', { dia: r.dia })}
                                                        className={cls(BOTAO_DE_ACCAO, 'border-red-200 bg-red-50 text-red-700')}
                                                    >
                                                        <i className="fas fa-trash" aria-hidden="true" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {d.meta.last_page > 1 && (
                        <div className="flex items-center justify-end gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-600">
                            <Botao
                                icone="fa-chevron-left"
                                altura="pequeno"
                                disabled={d.meta.current_page <= 1}
                                onClick={() => porFiltros((f) => ({ ...f, page: d.meta.current_page - 1 }))}
                            >
                                {t('Anterior')}
                            </Botao>
                            <span>{t('Página :pagina de :ultima', { pagina: d.meta.current_page, ultima: d.meta.last_page })}</span>
                            <Botao
                                icone="fa-chevron-right"
                                altura="pequeno"
                                disabled={d.meta.current_page >= d.meta.last_page}
                                onClick={() => porFiltros((f) => ({ ...f, page: d.meta.current_page + 1 }))}
                            >
                                {t('Seguinte')}
                            </Botao>
                        </div>
                    )}
                </section>
            )}

            <ModalDeImportar
                aberto={aImportar}
                contas={d.contas}
                formatos={d.formatos}
                aoFechar={() => porAImportar(false)}
                aoImportar={(mensagem, id) => {
                    porAImportar(false);
                    feito(mensagem);
                    porAVer(id);
                }}
            />

            <FichaDaReconciliacaoModal
                id={aVer}
                podeGerir={d.permissoes.gerir}
                aoFechar={() => porAVer(null)}
                aoMexer={(mensagem) => feito(mensagem)}
            />

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar conciliação')}
                subtitulo={aApagar ? `${aApagar.conta ?? ''} · ${aApagar.dia}` : undefined}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar)}>
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={apagar.error} />

                <p className="text-sm text-slate-700">
                    {t('O extracto importado e as suas linhas desaparecem. Nenhum lançamento é tocado — conciliar não lança nada.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── Importar o extracto ─────────────────────────────────────────────── */

function ModalDeImportar({
    aberto,
    contas,
    formatos,
    aoFechar,
    aoImportar,
}: {
    aberto: boolean;
    contas: Array<{ valor: string; rotulo: string }>;
    formatos: Array<{ valor: string; rotulo: string }>;
    aoFechar: () => void;
    aoImportar: (mensagem: string, id: number) => void;
}) {
    const [conta, porConta] = useState('');
    const [formato, porFormato] = useState('csv');
    const ficheiro = useRef<HTMLInputElement>(null);

    const importar = useMutation({
        mutationFn: () => {
            const corpo = new FormData();

            corpo.append('account_id', conta);
            corpo.append('file_type', formato);

            const f = ficheiro.current?.files?.[0];

            if (f) corpo.append('file', f);

            return contabilidade.reconciliacao.importar(corpo);
        },
        onSuccess: (r) => aoImportar(r.message, r.id),
    });

    const erros = importar.error instanceof ErroDaApi ? importar.error.erros : {};

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={t('Importar extracto')}
            subtitulo={t('Cada linha do extracto vai procurar o lançamento que lhe corresponde')}
            icone="fa-file-import"
            cor="teal"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times">{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-file-import" aTrabalhar={importar.isPending} onClick={() => importar.mutate()}>
                        {t('Importar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={importar.error} />

            <div className="grid gap-4">
                <Campo etiqueta={t('Conta do banco')} obrigatorio erro={erros.account_id}>
                    <select value={conta} onChange={(e) => porConta(e.target.value)} className={entrada}>
                        <option value="">{t('Escolha a conta…')}</option>
                        {contas.map((c) => <option key={c.valor} value={c.valor}>{c.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo etiqueta={t('Formato')} obrigatorio erro={erros.file_type}>
                    <select value={formato} onChange={(e) => porFormato(e.target.value)} className={entrada}>
                        {formatos.map((f) => <option key={f.valor} value={f.valor}>{f.rotulo}</option>)}
                    </select>
                </Campo>

                <Campo
                    etiqueta={t('Ficheiro')}
                    obrigatorio
                    erro={erros.file}
                    ajuda={t('Até 10 MB.')}
                >
                    <input
                        ref={ficheiro}
                        type="file"
                        accept=".csv,.txt,.sta,.mt940,.ofx,.qfx"
                        className={cls(entrada, 'h-auto py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-teal-700')}
                    />
                </Campo>
            </div>

            {formato === 'csv' && (
                <p className={cls('mt-3 border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)}>
                    <i className="fas fa-circle-info mr-2 text-blue-600" aria-hidden="true" />
                    {t('O CSV tem uma linha de cabeçalho e quatro colunas: data, referência, descrição e valor (negativo para as saídas).')}
                </p>
            )}
        </Modal>
    );
}

/* ─── A ficha: as linhas do extracto ─────────────────────────────────── */

/**
 * O ECRÃ QUE NÃO EXISTIA.
 *
 * Cada linha do extracto ao lado do lançamento que lhe corresponde. A sugestão
 * vem com a CONFIANÇA à frente — valor, data e descrição — e quem confere
 * aceita-a ou escolhe outra. Uma linha já casada desfaz-se.
 */
function FichaDaReconciliacaoModal({
    id,
    podeGerir,
    aoFechar,
    aoMexer,
}: {
    id: number | null;
    podeGerir: boolean;
    aoFechar: () => void;
    aoMexer: (mensagem: string) => void;
}) {
    const cache = useQueryClient();

    const ficha = useQuery({
        queryKey: ['contabilidade', 'reconciliacao', 'ficha', id],
        queryFn: () => contabilidade.reconciliacao.ficha(id as number),
        enabled: id !== null,
    });

    function recarregar(mensagem: string) {
        void cache.invalidateQueries({ queryKey: ['contabilidade', 'reconciliacao'] });
        aoMexer(mensagem);
    }

    const casar = useMutation({
        mutationFn: ({ linha, lancamento }: { linha: number; lancamento: number }) =>
            contabilidade.reconciliacao.casar(linha, lancamento),
        onSuccess: (r) => recarregar(r.message),
    });

    const desfazer = useMutation({
        mutationFn: (linha: number) => contabilidade.reconciliacao.desfazer(linha),
        onSuccess: (r) => recarregar(r.message),
    });

    const automatico = useMutation({
        mutationFn: () => contabilidade.reconciliacao.automatico(id as number),
        onSuccess: (r) => recarregar(r.message),
    });

    const r = ficha.data?.data;

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={r ? t('Extracto de :dia', { dia: r.dia }) : t('Extracto')}
            subtitulo={r?.conta ?? undefined}
            icone="fa-list-check"
            cor="teal"
            largura="xl"
            rodape={
                <>
                    {podeGerir && r && r.por_casar > 0 && (
                        <Botao
                            cor="primaria"
                            icone="fa-wand-magic-sparkles"
                            aTrabalhar={automatico.isPending}
                            onClick={() => automatico.mutate()}
                        >
                            {t('Casar automaticamente')}
                        </Botao>
                    )}
                    <Botao onClick={aoFechar} icone="fa-times">{t('Fechar')}</Botao>
                </>
            }
        >
            {ficha.isPending || !r ? (
                <Carregando linhas={8} />
            ) : (
                <>
                    <ErroDaAccao erro={casar.error ?? desfazer.error ?? automatico.error} />

                    <div className="mb-4 grid gap-3 sm:grid-cols-4">
                        <Numero rotulo={t('Saldo do extracto')} valor={r.saldo_do_extracto} tom="azul" />
                        <Numero rotulo={t('Saldo contabilístico')} valor={r.saldo_contabilistico} tom="ardosia" />
                        <Numero rotulo={t('Diferença')} valor={r.diferenca} tom={r.diferenca === 0 ? 'verde' : 'vermelho'} />
                        <div className={cls('border border-slate-200 bg-slate-50 px-3 py-2.5', RAIO)}>
                            <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{t('Casadas')}</p>
                            <p className="text-lg font-bold tabular-nums text-slate-900">{r.casadas} / {r.linhas}</p>
                        </div>
                    </div>

                    {r.linhas_do_extracto.length === 0 ? (
                        <SemNada icone="fa-list-check" titulo={t('Este extracto não tem linhas')} />
                    ) : (
                        <ul className="space-y-2">
                            {r.linhas_do_extracto.map((l, i) => (
                                <LinhaDoExtractoCartao
                                    key={l.id}
                                    l={l}
                                    indice={i}
                                    podeGerir={podeGerir}
                                    aCasar={casar.isPending}
                                    aDesfazer={desfazer.isPending}
                                    aoCasar={(lancamento) => casar.mutate({ linha: l.id, lancamento })}
                                    aoDesfazer={() => desfazer.mutate(l.id)}
                                />
                            ))}
                        </ul>
                    )}
                </>
            )}
        </Modal>
    );
}

/** Uma linha do extracto, com o que se sabe dela e o que se pode fazer. */
function LinhaDoExtractoCartao({
    l,
    indice,
    podeGerir,
    aCasar,
    aDesfazer,
    aoCasar,
    aoDesfazer,
}: {
    l: LinhaDoExtracto;
    indice: number;
    podeGerir: boolean;
    aCasar: boolean;
    aDesfazer: boolean;
    aoCasar: (lancamento: number) => void;
    aoDesfazer: () => void;
}) {
    const casada = l.estado === 'matched';
    const entrada_ = l.tipo === 'credit';

    return (
        <li
            style={cascata(indice)}
            className={cls('entra border px-4 py-3', RAIO,
                casada ? 'border-emerald-200 bg-emerald-50/50' : 'border-amber-200 bg-amber-50/40')}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="flex flex-wrap items-center gap-2">
                        <span className="text-sm font-semibold text-slate-900">{l.dia}</span>
                        <Etiqueta cor={entrada_ ? 'bom' : 'perigo'}>{l.tipo_rotulo}</Etiqueta>
                        {casada ? (
                            <Etiqueta cor="bom" icone="fa-link">
                                {l.lancamento ?? t('Casada')}
                                {l.confianca !== null && l.confianca < 100 && ` · ${l.confianca}%`}
                            </Etiqueta>
                        ) : (
                            <Etiqueta cor="aviso" icone="fa-link-slash">{t('Por casar')}</Etiqueta>
                        )}
                    </p>
                    <p className="mt-0.5 truncate text-sm text-slate-700">{l.descricao || t('Sem descrição')}</p>
                    {l.referencia && <p className="font-mono text-xs text-slate-400">{l.referencia}</p>}
                </div>

                <div className="flex flex-none items-center gap-3">
                    <span className={cls('text-lg font-bold tabular-nums', entrada_ ? 'text-emerald-700' : 'text-red-700')}>
                        {entrada_ ? '+' : '−'} {kz(l.valor)}
                    </span>

                    {podeGerir && casada && (
                        <Botao altura="pequeno" icone="fa-link-slash" aTrabalhar={aDesfazer} onClick={aoDesfazer}>
                            {t('Desfazer')}
                        </Botao>
                    )}
                </div>
            </div>

            {/* AS SUGESTÕES, com a confiança à frente. */}
            {!casada && podeGerir && (
                l.sugestoes.length === 0 ? (
                    <p className="mt-2 border-t border-amber-200 pt-2 text-xs text-amber-800">
                        <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />
                        {t('Nenhum lançamento parecido nos cinco dias em volta. Lance-o na contabilidade e volte a casar automaticamente.')}
                    </p>
                ) : (
                    <ul className="mt-2 space-y-1.5 border-t border-amber-200 pt-2">
                        {l.sugestoes.map((s) => (
                            <li key={s.linha_id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white/70 px-3 py-2">
                                <span className="min-w-0 text-xs">
                                    <span className="mr-2 rounded bg-slate-100 px-1.5 py-0.5 font-mono font-bold text-slate-700">
                                        {s.lancamento ?? '—'}
                                    </span>
                                    <span className="text-slate-500">{s.dia}</span>
                                    {s.nota && <span className="ml-2 text-slate-600">{s.nota}</span>}
                                    <span className="ml-2 font-semibold tabular-nums text-slate-800">
                                        {kz(s.debito > 0 ? s.debito : s.credito)}
                                    </span>
                                </span>

                                <span className="flex flex-none items-center gap-2">
                                    {/* A CONFIANÇA, que é o que faz escolher. */}
                                    <span className={cls('rounded-full px-2 py-0.5 text-[11px] font-bold',
                                        s.confianca >= 90 ? 'bg-emerald-100 text-emerald-700'
                                            : s.confianca >= 70 ? 'bg-amber-100 text-amber-700'
                                                : 'bg-slate-100 text-slate-600')}>
                                        {s.confianca}%
                                    </span>
                                    <Botao
                                        altura="pequeno"
                                        cor="bom"
                                        tom="solida"
                                        icone="fa-link"
                                        aTrabalhar={aCasar}
                                        onClick={() => aoCasar(s.linha_id)}
                                    >
                                        {t('Casar')}
                                    </Botao>
                                </span>
                            </li>
                        ))}
                    </ul>
                )
            )}
        </li>
    );
}

/* ─── As peças pequenas ───────────────────────────────────────────────── */

const BOTAO_DE_ACCAO = cls(
    'inline-flex items-center gap-1.5 border px-2.5 py-1.5 text-xs font-semibold',
    'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm',
    RAIO,
    FOCO,
);

const NUMEROS = {
    ardosia: 'border-slate-200 bg-slate-50 text-slate-700',
    verde: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    vermelho: 'border-red-200 bg-red-50 text-red-800',
    azul: 'border-blue-200 bg-blue-50 text-blue-900',
} as const;

function Numero({ rotulo, valor, tom }: { rotulo: string; valor: number; tom: keyof typeof NUMEROS }) {
    return (
        <div className={cls('border px-3 py-2.5', RAIO, NUMEROS[tom])}>
            <p className="text-[11px] font-semibold uppercase tracking-wider opacity-80">{rotulo}</p>
            <p className="text-lg font-bold tabular-nums">{kz(valor)}</p>
        </div>
    );
}

function ErroDaAccao({ erro }: { erro: unknown }) {
    if (!erro) return null;

    const daApi = erro instanceof ErroDaApi ? erro : null;
    const geral = daApi?.erros.geral?.[0];

    return (
        <div role="alert" className={cls('mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
            <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
            {geral ?? daApi?.message ?? t('A operação não foi concluída.')}
        </div>
    );
}
