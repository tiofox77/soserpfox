import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { Dimensao, Etiqueta as EtiquetaAnalitica } from '@/api/contabilidade';
import { contabilidade } from '@/api/contabilidade';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, Faixa } from '../facturacao/faixa';

/**
 * A CONTABILIDADE ANALÍTICA — as dimensões e as suas etiquetas.
 *
 * Uma DIMENSÃO é uma pergunta que se faz a cada lançamento («que projecto?»,
 * «que loja?»); as ETIQUETAS são as respostas possíveis. Uma dimensão
 * obrigatória é uma pergunta que não se pode deixar em branco.
 *
 * O QUE ESTAVA PARTIDO, e era grave: EDITAR UMA ETIQUETA CRIAVA OUTRA — o ecrã
 * carregava-a no formulário e gravava sempre uma nova, pelo que corrigir um
 * nome deixava duas etiquetas iguais e os lançamentos repartidos entre elas. A
 * dimensão não se editava nem se apagava, o código não era único, e nada olhava
 * à empresa: o id bastava para mexer na etiqueta de outra companhia.
 *
 * E A LISTA DE ETIQUETAS ABRIA VAZIA até se carregar numa dimensão, sem dizer
 * que era preciso — parecia que não havia etiquetas nenhumas. Agora abre na
 * primeira.
 */
export default function Analitica() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<{ dimensao?: number | ''; procura?: string; estado?: string }>({});
    const [recado, porRecado] = useState('');
    const [modalDaDimensao, porModalDaDimensao] = useState<{ aberto: boolean; dimensao: Dimensao | null }>({ aberto: false, dimensao: null });
    const [modalDaEtiqueta, porModalDaEtiqueta] = useState<{ aberto: boolean; etiqueta: EtiquetaAnalitica | null }>({ aberto: false, etiqueta: null });
    const [dimensaoAApagar, porDimensaoAApagar] = useState<Dimensao | null>(null);
    const [etiquetaAApagar, porEtiquetaAApagar] = useState<EtiquetaAnalitica | null>(null);

    const lista = useQuery({
        queryKey: ['contabilidade', 'analitica', filtros],
        queryFn: () => contabilidade.analitica.listar(filtros),
        placeholderData: keepPreviousData,
    });

    function feito(mensagem: string) {
        porRecado(mensagem);
        void cache.invalidateQueries({ queryKey: ['contabilidade', 'analitica'] });
    }

    const apagarDimensao = useMutation({
        mutationFn: (d: Dimensao) => contabilidade.analitica.apagarDimensao(d.id),
        onSuccess: (r) => {
            porDimensaoAApagar(null);
            feito(r.message);
        },
    });

    const apagarEtiqueta = useMutation({
        mutationFn: (e: EtiquetaAnalitica) => contabilidade.analitica.apagarEtiqueta(e.id),
        onSuccess: (r) => {
            porEtiquetaAApagar(null);
            feito(r.message);
        },
    });

    if (lista.isPending) return <Carregando linhas={10} />;

    if (lista.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a analítica')}</h2>
                <p className="text-sm text-red-800">
                    {lista.error instanceof ErroDaApi ? lista.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const d = lista.data;
    const escolhida = d.dimensoes.find((x) => x.id === d.escolhida) ?? null;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Contabilidade Analítica')}
                subtitulo={t('As perguntas que se fazem a cada lançamento, e as respostas possíveis')}
                icone="fa-diagram-project"
                cor="roxo"
                accoes={
                    d.permissoes.gerir && (
                        <button
                            type="button"
                            onClick={() => porModalDaDimensao({ aberto: true, dimensao: null })}
                            className={ACCAO_DA_FAIXA}
                        >
                            <i className="fas fa-plus" aria-hidden="true" />
                            {t('Nova Dimensão')}
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

            <ErroDaAccao erro={apagarDimensao.error ?? apagarEtiqueta.error} />

            <div className="grid gap-4 sm:grid-cols-3">
                <CartaoNumero rotulo={t('Dimensões')} valor={d.resumo.dimensoes.toLocaleString('pt-PT')} icone="fa-diagram-project" tom="roxo" aspecto="claro" />
                <CartaoNumero rotulo={t('Obrigatórias')} valor={d.resumo.obrigatorias.toLocaleString('pt-PT')} icone="fa-asterisk" tom="ambar" aspecto="claro" nota={t('Não se deixam em branco')} />
                <CartaoNumero rotulo={t('Etiquetas')} valor={d.resumo.etiquetas.toLocaleString('pt-PT')} icone="fa-tags" tom="azul" aspecto="claro" />
            </div>

            {d.dimensoes.length === 0 ? (
                <section className={cls(CARTAO, 'overflow-hidden')}>
                    <SemNada
                        icone="fa-diagram-project"
                        titulo={t('Ainda não há dimensões')}
                        frase={d.permissoes.gerir
                            ? t('Uma dimensão é uma pergunta — «que projecto?», «que loja?». Comece por criar a primeira.')
                            : t('Peça a quem gere a contabilidade para criar a primeira dimensão.')}
                    />
                </section>
            ) : (
                <div className="grid gap-4 lg:grid-cols-[20rem_1fr]">
                    {/* AS DIMENSÕES — as perguntas. */}
                    <section className={cls(CARTAO, 'overflow-hidden')}>
                        <header className="border-b border-slate-100 px-4 py-3">
                            <h3 className="flex items-center gap-2 text-sm font-bold text-slate-900">
                                <i className="fas fa-diagram-project text-purple-600" aria-hidden="true" />
                                {t('Dimensões')}
                            </h3>
                            <p className="mt-0.5 text-xs text-slate-400">{t('A pergunta que se faz ao lançar')}</p>
                        </header>

                        <ul className="divide-y divide-slate-100">
                            {d.dimensoes.map((x, i) => {
                                const activa = x.id === d.escolhida;

                                return (
                                    <li key={x.id} style={cascata(i)} className="entra">
                                        <div className={cls('flex items-start gap-2 px-3 py-2.5 transition-colors',
                                            activa ? 'bg-purple-50' : 'hover:bg-slate-50')}>
                                            <button
                                                type="button"
                                                onClick={() => porFiltros((f) => ({ ...f, dimensao: x.id }))}
                                                aria-pressed={activa}
                                                className={cls('min-w-0 flex-1 text-left', FOCO, RAIO)}
                                            >
                                                <span className="flex items-center gap-1.5">
                                                    <span className={cls('truncate text-sm font-semibold',
                                                        activa ? 'text-purple-800' : 'text-slate-900')}>
                                                        {x.nome}
                                                    </span>
                                                    {x.obrigatoria && (
                                                        <span
                                                            className="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700"
                                                            title={t('Não se deixa em branco ao lançar')}
                                                        >
                                                            {t('obrigatória')}
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="block font-mono text-[11px] text-slate-400">{x.codigo}</span>
                                                <span className="block text-[11px] text-slate-500">
                                                    {t(':n etiqueta(s) · :a activa(s)', { n: x.etiquetas, a: x.etiquetas_activas })}
                                                </span>
                                            </button>

                                            {d.permissoes.gerir && (
                                                <span className="flex flex-none gap-1">
                                                    <button
                                                        type="button"
                                                        onClick={() => porModalDaDimensao({ aberto: true, dimensao: x })}
                                                        title={t('Editar')}
                                                        aria-label={t('Editar a dimensão :nome', { nome: x.nome })}
                                                        className={cls(BOTAO_DE_ACCAO, 'border-blue-200 bg-blue-50 text-blue-700')}
                                                    >
                                                        <i className="fas fa-edit" aria-hidden="true" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => porDimensaoAApagar(x)}
                                                        title={t('Eliminar')}
                                                        aria-label={t('Eliminar a dimensão :nome', { nome: x.nome })}
                                                        className={cls(BOTAO_DE_ACCAO, 'border-red-200 bg-red-50 text-red-700')}
                                                    >
                                                        <i className="fas fa-trash" aria-hidden="true" />
                                                    </button>
                                                </span>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>

                    {/* AS ETIQUETAS — as respostas da dimensão escolhida. */}
                    <section className={cls(CARTAO, 'overflow-hidden')}>
                        <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                            <div className="min-w-0">
                                <h3 className="flex items-center gap-2 text-sm font-bold text-slate-900">
                                    <i className="fas fa-tags text-blue-600" aria-hidden="true" />
                                    {escolhida ? t('Etiquetas de «:nome»', { nome: escolhida.nome }) : t('Etiquetas')}
                                </h3>
                                <p className="mt-0.5 text-xs text-slate-400">{t('As respostas possíveis a esta pergunta')}</p>
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <input
                                    type="search"
                                    value={filtros.procura ?? ''}
                                    onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value }))}
                                    placeholder={t('Código ou nome…')}
                                    aria-label={t('Procurar etiqueta')}
                                    className={cls(entrada, 'h-9 w-40')}
                                />
                                <select
                                    value={filtros.estado ?? ''}
                                    onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value }))}
                                    aria-label={t('Estado')}
                                    className={cls(entrada, 'h-9 w-32')}
                                >
                                    <option value="">{t('Todas')}</option>
                                    <option value="activas">{t('Activas')}</option>
                                    <option value="inactivas">{t('Inactivas')}</option>
                                </select>

                                {d.permissoes.gerir && escolhida && (
                                    <Botao
                                        altura="pequeno"
                                        cor="primaria"
                                        tom="solida"
                                        icone="fa-plus"
                                        onClick={() => porModalDaEtiqueta({ aberto: true, etiqueta: null })}
                                    >
                                        {t('Nova Etiqueta')}
                                    </Botao>
                                )}
                            </div>
                        </header>

                        {d.etiquetas.length === 0 ? (
                            <SemNada
                                icone="fa-tags"
                                titulo={t('Sem etiquetas nesta dimensão')}
                                frase={t('Uma dimensão sem respostas possíveis não se pode preencher ao lançar.')}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-100 text-sm">
                                    <thead className="bg-gradient-to-r from-purple-50 to-pink-50">
                                        <tr>
                                            {[t('Código'), t('Nome'), t('Descrição')].map((c) => (
                                                <th key={c} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-purple-700">{c}</th>
                                            ))}
                                            <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-purple-700">{t('Estado')}</th>
                                            <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-purple-700">{t('Ações')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {d.etiquetas.map((e, i) => (
                                            <tr key={e.id} style={cascata(i)} className="entra transition-colors hover:bg-purple-50/50">
                                                <td className="whitespace-nowrap px-4 py-3">
                                                    <span className="rounded bg-slate-100 px-2 py-1 font-mono text-xs font-bold text-slate-700">{e.codigo}</span>
                                                </td>
                                                <td className="px-4 py-3 font-semibold text-slate-900">{e.nome}</td>
                                                <td className="max-w-xs truncate px-4 py-3 text-slate-600">{e.descricao ?? '—'}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-center">
                                                    <Etiqueta cor={e.activa ? 'bom' : 'neutra'} ponto>
                                                        {e.activa ? t('Activa') : t('Inactiva')}
                                                    </Etiqueta>
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right">
                                                    {d.permissoes.gerir ? (
                                                        <div className="flex justify-end gap-1.5">
                                                            <button
                                                                type="button"
                                                                onClick={() => porModalDaEtiqueta({ aberto: true, etiqueta: e })}
                                                                title={t('Editar')}
                                                                aria-label={t('Editar a etiqueta :nome', { nome: e.nome })}
                                                                className={cls(BOTAO_DE_ACCAO, 'border-blue-200 bg-blue-50 text-blue-700')}
                                                            >
                                                                <i className="fas fa-edit" aria-hidden="true" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => porEtiquetaAApagar(e)}
                                                                title={t('Eliminar')}
                                                                aria-label={t('Eliminar a etiqueta :nome', { nome: e.nome })}
                                                                className={cls(BOTAO_DE_ACCAO, 'border-red-200 bg-red-50 text-red-700')}
                                                            >
                                                                <i className="fas fa-trash" aria-hidden="true" />
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-slate-400">—</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>
            )}

            <ModalDaDimensao
                aberto={modalDaDimensao.aberto}
                dimensao={modalDaDimensao.dimensao}
                aoFechar={() => porModalDaDimensao({ aberto: false, dimensao: null })}
                aoGravar={(mensagem, id) => {
                    porModalDaDimensao({ aberto: false, dimensao: null });
                    porFiltros((f) => ({ ...f, dimensao: id }));
                    feito(mensagem);
                }}
            />

            <ModalDaEtiqueta
                aberto={modalDaEtiqueta.aberto}
                etiqueta={modalDaEtiqueta.etiqueta}
                dimensao={d.escolhida}
                aoFechar={() => porModalDaEtiqueta({ aberto: false, etiqueta: null })}
                aoGravar={(mensagem) => {
                    porModalDaEtiqueta({ aberto: false, etiqueta: null });
                    feito(mensagem);
                }}
            />

            <Modal
                aberto={dimensaoAApagar !== null}
                aoFechar={() => porDimensaoAApagar(null)}
                titulo={t('Eliminar dimensão')}
                subtitulo={dimensaoAApagar?.nome}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porDimensaoAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo"
                            tom="solida"
                            icone="fa-trash"
                            aTrabalhar={apagarDimensao.isPending}
                            onClick={() => dimensaoAApagar && apagarDimensao.mutate(dimensaoAApagar)}
                        >
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={apagarDimensao.error} />

                <p className="text-sm text-slate-700">
                    {t('A pergunta desaparece. Esta operação não se desfaz.')}
                </p>

                {(dimensaoAApagar?.etiquetas ?? 0) > 0 && (
                    <div className={cls('mt-3 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                        <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                        {t('Esta dimensão tem :n etiqueta(s) — o servidor vai recusar. Apague-as primeiro.', {
                            n: dimensaoAApagar?.etiquetas ?? 0,
                        })}
                    </div>
                )}
            </Modal>

            <Modal
                aberto={etiquetaAApagar !== null}
                aoFechar={() => porEtiquetaAApagar(null)}
                titulo={t('Eliminar etiqueta')}
                subtitulo={etiquetaAApagar?.nome}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porEtiquetaAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo"
                            tom="solida"
                            icone="fa-trash"
                            aTrabalhar={apagarEtiqueta.isPending}
                            onClick={() => etiquetaAApagar && apagarEtiqueta.mutate(etiquetaAApagar)}
                        >
                            {t('Eliminar')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={apagarEtiqueta.error} />

                <p className="text-sm text-slate-700">
                    {t('A resposta sai desta dimensão. Se preferir guardá-la para trás, desactive-a em vez de a apagar.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── A janela da dimensão ────────────────────────────────────────────── */

function ModalDaDimensao({
    aberto,
    dimensao,
    aoFechar,
    aoGravar,
}: {
    aberto: boolean;
    dimensao: Dimensao | null;
    aoFechar: () => void;
    aoGravar: (mensagem: string, id: number) => void;
}) {
    const [f, porF] = useState({ code: '', name: '', is_mandatory: false });

    useEffect(() => {
        if (!aberto) return;

        porF(dimensao
            ? { code: dimensao.codigo, name: dimensao.nome, is_mandatory: dimensao.obrigatoria }
            : { code: '', name: '', is_mandatory: false });
    }, [aberto, dimensao]);

    const gravar = useMutation({
        mutationFn: () => contabilidade.analitica.guardarDimensao(dimensao?.id ?? null, f),
        onSuccess: (r) => aoGravar(r.message, r.id),
    });

    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={dimensao ? t('Editar Dimensão') : t('Nova Dimensão')}
            subtitulo={t('A pergunta que se faz a cada lançamento')}
            icone="fa-diagram-project"
            cor="roxo"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times">{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-save" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>
                        {dimensao ? t('Atualizar') : t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={gravar.error} />

            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta={t('Código')} obrigatorio erro={erros.code} ajuda={t('Ex.: PROJ, LOJA')}>
                    <input
                        type="text"
                        value={f.code}
                        onChange={(e) => porF((x) => ({ ...x, code: e.target.value }))}
                        className={cls(entrada, 'font-mono')}
                    />
                </Campo>

                <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                    <input
                        type="text"
                        value={f.name}
                        onChange={(e) => porF((x) => ({ ...x, name: e.target.value }))}
                        placeholder={t('Ex.: Projecto')}
                        className={entrada}
                    />
                </Campo>

                <label className={cls('flex cursor-pointer items-start gap-3 border border-amber-200 bg-amber-50 px-4 py-3 sm:col-span-2', RAIO)}>
                    <input
                        type="checkbox"
                        checked={f.is_mandatory}
                        onChange={(e) => porF((x) => ({ ...x, is_mandatory: e.target.checked }))}
                        className="mt-0.5 h-5 w-5 flex-none rounded border-slate-300 text-amber-600 focus-visible:ring-2 focus-visible:ring-amber-500"
                    />
                    <span>
                        <span className="block text-sm font-bold text-slate-900">
                            <i className="fas fa-asterisk mr-1.5" aria-hidden="true" />
                            {t('Obrigatória')}
                        </span>
                        <span className="block text-xs text-slate-600">
                            {t('Uma pergunta obrigatória não se deixa em branco: todo o lançamento tem de escolher uma etiqueta.')}
                        </span>
                    </span>
                </label>
            </div>
        </Modal>
    );
}

/* ─── A janela da etiqueta ────────────────────────────────────────────── */

/**
 * EDITAR EDITA. O ecrã antigo carregava a etiqueta no formulário e gravava
 * sempre uma NOVA — corrigir um nome deixava duas etiquetas iguais e os
 * lançamentos repartidos entre elas.
 */
function ModalDaEtiqueta({
    aberto,
    etiqueta,
    dimensao,
    aoFechar,
    aoGravar,
}: {
    aberto: boolean;
    etiqueta: EtiquetaAnalitica | null;
    dimensao: number | null;
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const [f, porF] = useState({ code: '', name: '', description: '', is_active: true });

    useEffect(() => {
        if (!aberto) return;

        porF(etiqueta
            ? {
                code: etiqueta.codigo, name: etiqueta.nome,
                description: etiqueta.descricao ?? '', is_active: etiqueta.activa,
            }
            : { code: '', name: '', description: '', is_active: true });
    }, [aberto, etiqueta]);

    const gravar = useMutation({
        mutationFn: () => contabilidade.analitica.guardarEtiqueta(etiqueta?.id ?? null, {
            ...f,
            dimension_id: dimensao,
        }),
        onSuccess: (r) => aoGravar(r.message),
    });

    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={etiqueta ? t('Editar Etiqueta') : t('Nova Etiqueta')}
            subtitulo={t('Uma resposta possível a esta pergunta')}
            icone="fa-tags"
            cor="primaria"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times">{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-save" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>
                        {etiqueta ? t('Atualizar') : t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={gravar.error} />

            {erros.dimension_id?.[0] && (
                <div role="alert" className={cls('mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
                    <i className="fas fa-circle-exclamation mr-2" aria-hidden="true" />
                    {erros.dimension_id[0]}
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <Campo
                    etiqueta={t('Código')}
                    obrigatorio
                    erro={erros.code}
                    ajuda={t('Único dentro desta dimensão.')}
                >
                    <input
                        type="text"
                        value={f.code}
                        onChange={(e) => porF((x) => ({ ...x, code: e.target.value }))}
                        className={cls(entrada, 'font-mono')}
                    />
                </Campo>

                <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                    <input
                        type="text"
                        value={f.name}
                        onChange={(e) => porF((x) => ({ ...x, name: e.target.value }))}
                        className={entrada}
                    />
                </Campo>

                <Campo etiqueta={t('Descrição')} erro={erros.description} className="sm:col-span-2">
                    <textarea
                        rows={3}
                        value={f.description}
                        onChange={(e) => porF((x) => ({ ...x, description: e.target.value }))}
                        className={cls(entrada, 'h-auto py-2')}
                    />
                </Campo>

                <label className={cls('flex cursor-pointer items-start gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 sm:col-span-2', RAIO)}>
                    <input
                        type="checkbox"
                        checked={f.is_active}
                        onChange={(e) => porF((x) => ({ ...x, is_active: e.target.checked }))}
                        className="mt-0.5 h-5 w-5 flex-none rounded border-slate-300 text-emerald-600 focus-visible:ring-2 focus-visible:ring-emerald-500"
                    />
                    <span>
                        <span className="block text-sm font-bold text-slate-900">
                            <i className="fas fa-circle-check mr-1.5" aria-hidden="true" />
                            {t('Activa')}
                        </span>
                        <span className="block text-xs text-slate-600">
                            {t('Uma etiqueta inactiva deixa de se poder escolher, e o que já foi lançado com ela continua legível.')}
                        </span>
                    </span>
                </label>
            </div>
        </Modal>
    );
}

/* ─── As peças pequenas ───────────────────────────────────────────────── */

const BOTAO_DE_ACCAO = cls(
    'inline-flex items-center gap-1.5 border px-2 py-1.5 text-xs font-semibold',
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
