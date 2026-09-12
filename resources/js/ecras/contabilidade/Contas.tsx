import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { Conta, OpcoesDasContas } from '@/api/contabilidade';
import { contabilidade } from '@/api/contabilidade';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { PorPagina } from '@/ui/FiltrosComuns';
import { Modal } from '@/ui/Modal';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, Faixa } from '../facturacao/faixa';

/**
 * O PLANO DE CONTAS.
 *
 * O QUE ESTAVA PARTIDO E AQUI SE CONSERTA — as regras vivem no servidor, este
 * ecrã mostra-as:
 *
 *  · uma conta apagava-se sem uma pergunta, mesmo com movimento em cima (a
 *    razão ficava órfã) ou com filhas penduradas (a árvore partia-se ao meio);
 *  · o CÓDIGO não era único: duas contas «11» no mesmo plano;
 *  · a conta-mãe podia ser uma conta de movimento, e o valor contava a dobrar;
 *  · o NÍVEL era um campo livre à mão — agora sai da mãe.
 *
 * E O QUE FALTAVA: a RAZÃO da conta. Via-se o plano e mais nada; para saber o
 * que uma conta tem dentro era preciso ir aos relatórios montar o balancete
 * inteiro. Agora abre-se ali, com saldo de abertura e acumulado linha a linha.
 */

type Filtros = {
    procura?: string;
    tipo?: string;
    nivel?: number | '';
    natureza?: string;
    estado?: string;
    por_pagina?: number;
    page?: number;
};

const NIVEIS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

export default function Contas() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<Filtros>({ por_pagina: 25, page: 1 });
    const [recado, porRecado] = useState('');
    const [modal, porModal] = useState<{ aberto: boolean; id: number | null }>({ aberto: false, id: null });
    const [aVer, porAVer] = useState<number | null>(null);
    const [aApagar, porAApagar] = useState<Conta | null>(null);
    const [razao, porRazao] = useState<Conta | null>(null);

    const opcoes = useQuery({
        queryKey: ['contabilidade', 'contas', 'opcoes'],
        queryFn: contabilidade.contas.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['contabilidade', 'contas', filtros],
        queryFn: () => contabilidade.contas.listar(filtros),
        placeholderData: keepPreviousData,
    });

    function feito(mensagem: string) {
        porRecado(mensagem);
        void cache.invalidateQueries({ queryKey: ['contabilidade'] });
    }

    const apagar = useMutation({
        mutationFn: (c: Conta) => contabilidade.contas.apagar(c.id),
        onSuccess: (r) => {
            porAApagar(null);
            feito(r.message);
        },
    });

    const alternar = useMutation({
        mutationFn: (c: Conta) => contabilidade.contas.estado(c.id),
        onSuccess: (r) => feito(r.message),
    });

    if (opcoes.isPending) return <Carregando linhas={10} />;

    if (opcoes.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o plano de contas')}</h2>
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
        filtros.procura || filtros.tipo || filtros.nivel || filtros.natureza || filtros.estado,
    );

    const mudar = (campos: Partial<Filtros>) => porFiltros((f) => ({ ...f, ...campos, page: 1 }));

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Plano de Contas')}
                subtitulo={t('Gestão do plano de contas contabilístico')}
                icone="fa-sitemap"
                cor="bom"
                accoes={
                    o.permissoes.criar && (
                        <button type="button" onClick={() => porModal({ aberto: true, id: null })} className={ACCAO_DA_FAIXA}>
                            <i className="fas fa-plus" aria-hidden="true" />
                            {t('Nova Conta')}
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

            <ErroDaAccao erro={apagar.error ?? alternar.error} />

            {resumo && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <CartaoNumero rotulo={t('Total de Contas')} valor={resumo.total.toLocaleString('pt-PT')} icone="fa-list" tom="azul" aspecto="claro" nota={comFiltro ? t('Nos filtros escolhidos') : t('Todas as contas')} />
                    <CartaoNumero rotulo={t('Activas')} valor={resumo.activas.toLocaleString('pt-PT')} icone="fa-circle-check" tom="verde" aspecto="claro" nota={t('Recebem lançamentos')} />
                    <CartaoNumero rotulo={t('Bloqueadas')} valor={resumo.bloqueadas.toLocaleString('pt-PT')} icone="fa-lock" tom="vermelho" aspecto="claro" nota={t('Sem novos lançamentos')} />
                    <CartaoNumero rotulo={t('De agregação')} valor={resumo.agregacao.toLocaleString('pt-PT')} icone="fa-diagram-project" tom="roxo" aspecto="claro" nota={t('Somam as filhas')} />
                </div>
            )}

            <Cartao titulo={t('Filtrar')} icone="fa-filter">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <Campo etiqueta={t('Pesquisar')} className="xl:col-span-2">
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => mudar({ procura: e.target.value })}
                            placeholder={t('Código ou nome da conta…')}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Tipo de Conta')}>
                        <select value={filtros.tipo ?? ''} onChange={(e) => mudar({ tipo: e.target.value })} className={entrada}>
                            <option value="">{t('Todos os tipos')}</option>
                            {o.tipos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Nível')}>
                        <select
                            value={filtros.nivel ?? ''}
                            onChange={(e) => mudar({ nivel: e.target.value ? Number(e.target.value) : '' })}
                            className={entrada}
                        >
                            <option value="">{t('Todos os níveis')}</option>
                            {NIVEIS.map((n) => <option key={n} value={n}>{t('Nível :n', { n })}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Natureza')}>
                        <select value={filtros.natureza ?? ''} onChange={(e) => mudar({ natureza: e.target.value })} className={entrada}>
                            <option value="">{t('Todas as naturezas')}</option>
                            {o.naturezas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Status')}>
                        <select value={filtros.estado ?? ''} onChange={(e) => mudar({ estado: e.target.value })} className={entrada}>
                            <option value="">{t('Todos os status')}</option>
                            <option value="activas">{t('Ativa')}</option>
                            <option value="bloqueadas">{t('Bloqueada')}</option>
                            <option value="agregacao">{t('De agregação')}</option>
                        </select>
                    </Campo>
                </div>

                {comFiltro && (
                    <div className={cls('mt-4 flex flex-wrap items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-2.5', RAIO)}>
                        <span className="text-sm text-emerald-800">
                            <i className="fas fa-info-circle mr-1" aria-hidden="true" />
                            {t('A mostrar :quantos conta(s).', { quantos: (contas?.total ?? 0).toLocaleString('pt-PT') })}
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
                                {[t('Código'), t('Nome'), t('Tipo'), t('Natureza')].map((c) => (
                                    <th key={c} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-emerald-700">{c}</th>
                                ))}
                                <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Nível')}</th>
                                <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Status')}</th>
                                <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Ações')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {lista.isPending && (
                                <tr><td colSpan={7} className="px-4 py-8"><Carregando linhas={5} /></td></tr>
                            )}

                            {!lista.isPending && linhas.length === 0 && (
                                <tr>
                                    <td colSpan={7}>
                                        <SemNada
                                            icone="fa-sitemap"
                                            titulo={t('Nenhuma conta encontrada')}
                                            frase={comFiltro ? t('Experimente limpar os filtros.') : t('Comece por criar a primeira conta do plano.')}
                                        />
                                    </td>
                                </tr>
                            )}

                            {linhas.map((c, i) => (
                                <tr key={c.id} style={cascata(i)} className="entra transition-colors hover:bg-emerald-50/60">
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <span className="rounded bg-slate-100 px-2 py-1 font-mono text-xs font-bold text-slate-700">{c.codigo}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        {/* A INDENTAÇÃO DIZ O NÍVEL: um plano de
                                            contas é uma árvore, e uma lista
                                            plana esconde-o. */}
                                        <p className="font-semibold text-slate-900" style={{ paddingLeft: `${(c.nivel - 1) * 14}px` }}>
                                            {c.agregacao && <i className="fas fa-folder-open mr-1.5 text-xs text-purple-500" aria-hidden="true" />}
                                            {c.nome}
                                        </p>
                                        {c.mae && <p className="truncate text-xs text-slate-400" style={{ paddingLeft: `${(c.nivel - 1) * 14}px` }}>{c.mae}</p>}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-700">{c.tipo_rotulo}</td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <Etiqueta cor={c.natureza === 'debit' ? 'primaria' : 'neutra'}>
                                            {c.natureza === 'debit' ? t('Débito') : t('Crédito')}
                                        </Etiqueta>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-center tabular-nums text-slate-600">{c.nivel}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-center">
                                        <div className="flex flex-wrap justify-center gap-1">
                                            <Etiqueta cor={c.bloqueada ? 'perigo' : 'bom'} ponto>
                                                {c.bloqueada ? t('Bloqueada') : t('Ativa')}
                                            </Etiqueta>
                                            {c.agregacao && <Etiqueta cor="primaria" icone="fa-diagram-project">{t('Agregação')}</Etiqueta>}
                                        </div>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1.5">
                                            <button
                                                type="button"
                                                onClick={() => porRazao(c)}
                                                title={t('Ver a razão')}
                                                aria-label={t('Ver a razão de :conta', { conta: c.nome })}
                                                className={cls(BOTAO_DE_ACCAO, 'border-slate-200 bg-white text-slate-600 hover:text-emerald-700')}
                                            >
                                                <i className="fas fa-book-open" aria-hidden="true" />
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() => porAVer(c.id)}
                                                title={t('Ver')}
                                                aria-label={t('Ver :conta', { conta: c.nome })}
                                                className={cls(BOTAO_DE_ACCAO, 'border-cyan-200 bg-cyan-50 text-cyan-700')}
                                            >
                                                <i className="fas fa-eye" aria-hidden="true" />
                                            </button>

                                            {o.permissoes.editar && (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={() => porModal({ aberto: true, id: c.id })}
                                                        title={t('Editar')}
                                                        aria-label={t('Editar :conta', { conta: c.nome })}
                                                        className={cls(BOTAO_DE_ACCAO, 'border-blue-200 bg-blue-50 text-blue-700')}
                                                    >
                                                        <i className="fas fa-edit" aria-hidden="true" />
                                                    </button>

                                                    <button
                                                        type="button"
                                                        onClick={() => alternar.mutate(c)}
                                                        disabled={alternar.isPending}
                                                        title={c.bloqueada ? t('Desbloquear') : t('Bloquear')}
                                                        aria-label={c.bloqueada ? t('Desbloquear :conta', { conta: c.nome }) : t('Bloquear :conta', { conta: c.nome })}
                                                        className={cls(BOTAO_DE_ACCAO, c.bloqueada
                                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                            : 'border-amber-200 bg-amber-50 text-amber-700')}
                                                    >
                                                        <i className={cls('fas', c.bloqueada ? 'fa-unlock' : 'fa-lock')} aria-hidden="true" />
                                                    </button>
                                                </>
                                            )}

                                            {o.permissoes.eliminar && (
                                                <button
                                                    type="button"
                                                    onClick={() => porAApagar(c)}
                                                    title={t('Eliminar')}
                                                    aria-label={t('Eliminar :conta', { conta: c.nome })}
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

            <ModalDaConta
                aberto={modal.aberto}
                id={modal.id}
                o={o}
                aoFechar={() => porModal({ aberto: false, id: null })}
                aoGravar={(mensagem) => {
                    porModal({ aberto: false, id: null });
                    feito(mensagem);
                }}
            />

            <FichaDaContaModal
                id={aVer}
                podeEditar={o.permissoes.editar}
                aoFechar={() => porAVer(null)}
                aoEditar={(id) => {
                    porAVer(null);
                    porModal({ aberto: true, id });
                }}
            />

            <RazaoDaContaModal conta={razao} aoFechar={() => porRazao(null)} />

            {/* APAGAR — o que se perde, dito antes de acontecer. */}
            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar conta')}
                subtitulo={aApagar ? `${aApagar.codigo} · ${aApagar.nome}` : undefined}
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
                    {t('A conta sai do plano. Esta operação não se desfaz.')}
                </p>

                {(aApagar?.linhas ?? 0) > 0 && (
                    <div className={cls('mt-3 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}>
                        <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                        {t('Esta conta tem :n linha(s) de lançamento — o servidor vai recusar. Bloqueie-a em vez de a apagar: o que já foi lançado tem de continuar legível.', {
                            n: aApagar?.linhas ?? 0,
                        })}
                    </div>
                )}
            </Modal>
        </div>
    );
}

/* ─── A janela de criar e editar ──────────────────────────────────────── */

type Formulario = {
    code: string;
    name: string;
    type: string;
    nature: string;
    parent_id: string;
    level: number;
    description: string;
    default_tax_id: string;
    default_cost_center_id: string;
    debit_reflection_account_id: string;
    credit_reflection_account_id: string;
    account_key: string;
    account_subtype: string;
    is_fixed_cost: boolean;
    is_view: boolean;
    blocked: boolean;
};

const VAZIO: Formulario = {
    code: '', name: '', type: 'asset', nature: 'debit', parent_id: '', level: 1,
    description: '', default_tax_id: '', default_cost_center_id: '',
    debit_reflection_account_id: '', credit_reflection_account_id: '',
    account_key: '', account_subtype: '',
    is_fixed_cost: false, is_view: false, blocked: false,
};

/**
 * O formulário da conta, nas três abas que sempre teve: Dados Gerais,
 * Avançado e Configurações.
 *
 * O NÍVEL NÃO SE ESCREVE. Era um campo livre — e um nível que não concorda com
 * a mãe desalinha a árvore inteira nos relatórios. Aqui mostra-se, calculado
 * da mãe escolhida, e diz-se de onde vem.
 */
function ModalDaConta({
    aberto,
    id,
    o,
    aoFechar,
    aoGravar,
}: {
    aberto: boolean;
    id: number | null;
    o: OpcoesDasContas;
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const [f, porF] = useState<Formulario>(VAZIO);
    const [aba, porAba] = useState('gerais');

    const ficha = useQuery({
        queryKey: ['contabilidade', 'contas', 'ficha', id],
        queryFn: () => contabilidade.contas.ficha(id as number),
        enabled: aberto && id !== null,
    });

    useEffect(() => {
        if (!aberto) return;

        porAba('gerais');

        if (id === null) {
            porF(VAZIO);

            return;
        }

        const c = ficha.data?.data;

        if (c) {
            porF({
                code: c.codigo,
                name: c.nome,
                type: c.tipo,
                nature: c.natureza,
                parent_id: c.mae_id ? String(c.mae_id) : '',
                level: c.nivel,
                description: c.descricao ?? '',
                default_tax_id: c.imposto_id ? String(c.imposto_id) : '',
                default_cost_center_id: c.centro_de_custo_id ? String(c.centro_de_custo_id) : '',
                debit_reflection_account_id: c.reflexao_debito_id ? String(c.reflexao_debito_id) : '',
                credit_reflection_account_id: c.reflexao_credito_id ? String(c.reflexao_credito_id) : '',
                account_key: c.chave ?? '',
                account_subtype: c.subtipo ?? '',
                is_fixed_cost: c.custo_fixo,
                is_view: c.agregacao,
                blocked: c.bloqueada,
            });
        }
    }, [aberto, id, ficha.data]);

    const gravar = useMutation({
        mutationFn: () => contabilidade.contas.guardar(id, {
            ...f,
            parent_id: f.parent_id ? Number(f.parent_id) : null,
            default_tax_id: f.default_tax_id ? Number(f.default_tax_id) : null,
            default_cost_center_id: f.default_cost_center_id ? Number(f.default_cost_center_id) : null,
            debit_reflection_account_id: f.debit_reflection_account_id ? Number(f.debit_reflection_account_id) : null,
            credit_reflection_account_id: f.credit_reflection_account_id ? Number(f.credit_reflection_account_id) : null,
        }),
        onSuccess: (r) => aoGravar(r.message),
    });

    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    /** O nível sai da mãe. Sem mãe é raiz. */
    const nivel = useMemo(() => {
        if (!f.parent_id) return 1;

        const mae = o.maes.find((m) => m.valor === f.parent_id);

        return mae ? mae.nivel + 1 : 1;
    }, [f.parent_id, o.maes]);

    const porAbaErros = (campos: string[]) => campos.filter((c) => erros[c]?.length).length;

    const ABAS = [
        { chave: 'gerais', rotulo: t('Dados Gerais'), icone: 'fa-info-circle', erros: porAbaErros(['code', 'name', 'type', 'nature', 'parent_id', 'description']) },
        { chave: 'avancado', rotulo: t('Avançado'), icone: 'fa-sliders-h', erros: porAbaErros(['default_tax_id', 'default_cost_center_id', 'debit_reflection_account_id', 'credit_reflection_account_id', 'account_key', 'account_subtype']) },
        { chave: 'definicoes', rotulo: t('Configurações'), icone: 'fa-cog', erros: porAbaErros(['is_view', 'blocked']) },
    ];

    const mexer = <K extends keyof Formulario>(campo: K, valor: Formulario[K]) =>
        porF((x) => ({ ...x, [campo]: valor }));

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={id ? t('Editar Conta') : t('Nova Conta')}
            subtitulo={t('Preencha os dados da conta contabilística')}
            icone="fa-sitemap"
            cor="bom"
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times">{t('Cancelar')}</Botao>
                    <Botao
                        cor="bom"
                        tom="solida"
                        icone="fa-save"
                        aTrabalhar={gravar.isPending}
                        onClick={() => {
                            // Levar à primeira aba com erro, senão quem grava
                            // fica sem saber o que falta.
                            const comErro = ABAS.find((a) => (a.erros ?? 0) > 0);

                            if (comErro) porAba(comErro.chave);

                            gravar.mutate();
                        }}
                    >
                        {id ? t('Atualizar') : t('Guardar')}
                    </Botao>
                </>
            }
        >
            {ficha.isPending && id !== null ? (
                <Carregando linhas={8} />
            ) : (
                <>
                    <AvisoDeErro erro={gravar.error} />

                    {erros.geral?.[0] && (
                        <div role="alert" className={cls('mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
                            <i className="fas fa-circle-exclamation mr-2" aria-hidden="true" />
                            {erros.geral[0]}
                        </div>
                    )}

                    <Separadores abas={ABAS} activa={aba} aoMudar={porAba} />

                    <div className="pt-4">
                        <PainelDoSeparador chave="gerais" activa={aba}>
                            <div className="grid gap-4 md:grid-cols-2">
                                <Campo etiqueta={t('Código')} obrigatorio erro={erros.code}>
                                    <input
                                        type="text"
                                        value={f.code}
                                        onChange={(e) => mexer('code', e.target.value)}
                                        placeholder={t('Ex: 11')}
                                        className={cls(entrada, 'font-mono')}
                                    />
                                </Campo>

                                <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                                    <input
                                        type="text"
                                        value={f.name}
                                        onChange={(e) => mexer('name', e.target.value)}
                                        placeholder={t('Nome da conta')}
                                        className={entrada}
                                    />
                                </Campo>

                                <Campo etiqueta={t('Tipo de Conta')} obrigatorio erro={erros.type}>
                                    <select value={f.type} onChange={(e) => mexer('type', e.target.value)} className={entrada}>
                                        {o.tipos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                    </select>
                                </Campo>

                                <Campo
                                    etiqueta={t('Natureza')}
                                    obrigatorio
                                    erro={erros.nature}
                                    ajuda={t('Define se o saldo aumenta no débito ou no crédito.')}
                                >
                                    <select value={f.nature} onChange={(e) => mexer('nature', e.target.value)} className={entrada}>
                                        {o.naturezas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                    </select>
                                </Campo>

                                <Campo
                                    etiqueta={t('Conta Pai')}
                                    erro={erros.parent_id}
                                    ajuda={t('Só contas de agregação podem ter filhas — são as únicas que somam.')}
                                >
                                    <select value={f.parent_id} onChange={(e) => mexer('parent_id', e.target.value)} className={entrada}>
                                        <option value="">{t('Nenhuma (conta de raiz)')}</option>
                                        {o.maes.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                                    </select>
                                </Campo>

                                {/* O NÍVEL MOSTRA-SE, não se escreve. */}
                                <div>
                                    <Rotulo>{t('Nível')}</Rotulo>
                                    <p className={cls('flex h-10 items-center gap-2 border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700', RAIO)}>
                                        <i className="fas fa-layer-group text-emerald-600" aria-hidden="true" />
                                        <strong className="tabular-nums">{nivel}</strong>
                                        <span className="text-xs text-slate-400">
                                            {f.parent_id ? t('sai da conta-mãe') : t('raiz da árvore')}
                                        </span>
                                    </p>
                                </div>

                                <Campo etiqueta={t('Descrição')} erro={erros.description} className="md:col-span-2">
                                    <textarea
                                        rows={3}
                                        value={f.description}
                                        onChange={(e) => mexer('description', e.target.value)}
                                        placeholder={t('Descrição opcional da conta')}
                                        className={cls(entrada, 'h-auto py-2')}
                                    />
                                </Campo>
                            </div>
                        </PainelDoSeparador>

                        <PainelDoSeparador chave="avancado" activa={aba}>
                            <div className="grid gap-4 md:grid-cols-2">
                                <Campo etiqueta={t('Imposto Padrão (IVA)')} erro={erros.default_tax_id}>
                                    <select value={f.default_tax_id} onChange={(e) => mexer('default_tax_id', e.target.value)} className={entrada}>
                                        <option value="">{t('Nenhum')}</option>
                                        {o.impostos.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                    </select>
                                </Campo>

                                <Campo etiqueta={t('Centro de Custo Padrão')} erro={erros.default_cost_center_id}>
                                    <select value={f.default_cost_center_id} onChange={(e) => mexer('default_cost_center_id', e.target.value)} className={entrada}>
                                        <option value="">{t('Nenhum')}</option>
                                        {o.centros_de_custo.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                    </select>
                                </Campo>

                                <Campo etiqueta={t('Conta Reflexo (Débito)')} erro={erros.debit_reflection_account_id}>
                                    <select value={f.debit_reflection_account_id} onChange={(e) => mexer('debit_reflection_account_id', e.target.value)} className={entrada}>
                                        <option value="">{t('Nenhuma')}</option>
                                        {o.contas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                    </select>
                                </Campo>

                                <Campo etiqueta={t('Conta Reflexo (Crédito)')} erro={erros.credit_reflection_account_id}>
                                    <select value={f.credit_reflection_account_id} onChange={(e) => mexer('credit_reflection_account_id', e.target.value)} className={entrada}>
                                        <option value="">{t('Nenhuma')}</option>
                                        {o.contas.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                    </select>
                                </Campo>

                                <Campo etiqueta={t('Chave da Conta')} erro={erros.account_key}>
                                    <input
                                        type="text"
                                        value={f.account_key}
                                        onChange={(e) => mexer('account_key', e.target.value)}
                                        placeholder={t('Ex: KEY001')}
                                        className={entrada}
                                    />
                                </Campo>

                                <Campo etiqueta={t('Subtipo/Classificação')} erro={erros.account_subtype}>
                                    <input
                                        type="text"
                                        value={f.account_subtype}
                                        onChange={(e) => mexer('account_subtype', e.target.value)}
                                        placeholder={t('Ex: Operacional, Financeiro')}
                                        className={entrada}
                                    />
                                </Campo>

                                <Interruptor
                                    className="md:col-span-2"
                                    tom="azul"
                                    icone="fa-thumbtack"
                                    ligado={f.is_fixed_cost}
                                    aoMudar={(v) => mexer('is_fixed_cost', v)}
                                    titulo={t('Custo Fixo')}
                                    frase={t('Marcar se esta conta representa um custo fixo')}
                                />
                            </div>
                        </PainelDoSeparador>

                        <PainelDoSeparador chave="definicoes" activa={aba}>
                            <div className="space-y-4">
                                <Interruptor
                                    tom="azul"
                                    icone="fa-diagram-project"
                                    ligado={f.is_view}
                                    aoMudar={(v) => mexer('is_view', v)}
                                    titulo={t('Conta de Visualização')}
                                    frase={t('Esta conta é apenas para agrupamento e não aceita lançamentos diretos')}
                                    erro={erros.is_view}
                                />

                                <Interruptor
                                    tom="vermelho"
                                    icone="fa-lock"
                                    ligado={f.blocked}
                                    aoMudar={(v) => mexer('blocked', v)}
                                    titulo={t('Conta Bloqueada')}
                                    frase={t('Impede novos lançamentos nesta conta')}
                                    erro={erros.blocked}
                                />

                                <div className={cls('border border-slate-200 bg-slate-50 px-4 py-3', RAIO)}>
                                    <h4 className="mb-2 flex items-center gap-2 text-sm font-bold text-slate-900">
                                        <i className="fas fa-info-circle text-blue-600" aria-hidden="true" />
                                        {t('Informações Adicionais')}
                                    </h4>
                                    <ul className="space-y-1 text-xs text-slate-600">
                                        <li><strong>{t('Tipo')}:</strong> {t('Define a classificação contabilística da conta')}</li>
                                        <li><strong>{t('Natureza')}:</strong> {t('Define se o saldo aumenta no débito ou crédito')}</li>
                                        <li><strong>{t('Nível')}:</strong> {t('Indica a profundidade na hierarquia (1 = raiz) e sai da conta-mãe')}</li>
                                    </ul>
                                </div>
                            </div>
                        </PainelDoSeparador>
                    </div>
                </>
            )}
        </Modal>
    );
}

/* ─── A janela de ver ─────────────────────────────────────────────────── */

function FichaDaContaModal({
    id,
    podeEditar,
    aoFechar,
    aoEditar,
}: {
    id: number | null;
    podeEditar: boolean;
    aoFechar: () => void;
    aoEditar: (id: number) => void;
}) {
    const ficha = useQuery({
        queryKey: ['contabilidade', 'contas', 'ficha', id],
        queryFn: () => contabilidade.contas.ficha(id as number),
        enabled: id !== null,
    });

    const c = ficha.data?.data;

    return (
        <Modal
            aberto={id !== null}
            aoFechar={aoFechar}
            titulo={c ? `${c.codigo} · ${c.nome}` : t('Detalhes da Conta')}
            subtitulo={t('Informações completas da conta contabilística')}
            icone="fa-eye"
            cor="ciano"
            largura="lg"
            rodape={
                <>
                    {podeEditar && c && (
                        <Botao cor="primaria" tom="solida" icone="fa-edit" onClick={() => aoEditar(c.id)}>
                            {t('Editar')}
                        </Botao>
                    )}
                    <Botao onClick={aoFechar} icone="fa-times">{t('Fechar')}</Botao>
                </>
            }
        >
            {ficha.isPending || !c ? (
                <Carregando linhas={8} />
            ) : (
                <div className="space-y-4">
                    <section className={cls('border border-slate-200 bg-slate-50 px-4 py-3.5', RAIO)}>
                        <h4 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-900">
                            <i className="fas fa-id-card text-emerald-600" aria-hidden="true" />
                            {t('Identificação')}
                        </h4>
                        <dl className="grid gap-3 sm:grid-cols-3">
                            <Valor rotulo={t('Código')} mono>{c.codigo}</Valor>
                            <Valor rotulo={t('Nível')}>{String(c.nivel)}</Valor>
                            <Valor rotulo={t('Nome da Conta')} className="sm:col-span-3">{c.nome}</Valor>
                        </dl>
                    </section>

                    <section className={cls('border border-slate-200 px-4 py-3.5', RAIO)}>
                        <h4 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-900">
                            <i className="fas fa-layer-group text-blue-600" aria-hidden="true" />
                            {t('Classificação')}
                        </h4>
                        <dl className="grid gap-3 sm:grid-cols-3">
                            <Valor rotulo={t('Tipo')}>{c.tipo_rotulo}</Valor>
                            <Valor rotulo={t('Natureza')}>{c.natureza === 'debit' ? t('Débito') : t('Crédito')}</Valor>
                            <Valor rotulo={t('Conta Pai')}>{c.mae ?? t('Nenhuma (raiz)')}</Valor>
                        </dl>
                    </section>

                    {(c.imposto || c.centro_de_custo || c.reflexao_debito || c.reflexao_credito || c.chave || c.subtipo || c.custo_fixo) && (
                        <section className={cls('border border-purple-200 bg-purple-50/50 px-4 py-3.5', RAIO)}>
                            <h4 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-900">
                                <i className="fas fa-sliders-h text-purple-600" aria-hidden="true" />
                                {t('Configurações Avançadas')}
                            </h4>
                            <dl className="grid gap-3 sm:grid-cols-2">
                                {c.imposto && <Valor rotulo={t('Imposto Padrão')}>{c.imposto}</Valor>}
                                {c.centro_de_custo && <Valor rotulo={t('Centro de Custo')}>{c.centro_de_custo}</Valor>}
                                {c.reflexao_debito && <Valor rotulo={t('Reflexo Débito')}>{c.reflexao_debito}</Valor>}
                                {c.reflexao_credito && <Valor rotulo={t('Reflexo Crédito')}>{c.reflexao_credito}</Valor>}
                                {c.chave && <Valor rotulo={t('Chave da Conta')} mono>{c.chave}</Valor>}
                                {c.subtipo && <Valor rotulo={t('Subtipo')}>{c.subtipo}</Valor>}
                                {c.custo_fixo && <Valor rotulo={t('Custo Fixo')}>{t('Sim')}</Valor>}
                            </dl>
                        </section>
                    )}

                    <div className="grid gap-3 sm:grid-cols-2">
                        {c.agregacao && (
                            <div className={cls('border border-blue-200 bg-blue-50 px-4 py-3', RAIO)}>
                                <p className="text-xs font-semibold text-blue-700">{t('Tipo de Conta')}</p>
                                <p className="text-sm font-bold text-slate-900">{t('Conta de Visualização')}</p>
                                <p className="mt-1 text-xs text-slate-600">{t('Apenas para agrupamento')}</p>
                            </div>
                        )}

                        {c.bloqueada && (
                            <div className={cls('border border-red-200 bg-red-50 px-4 py-3', RAIO)}>
                                <p className="text-xs font-semibold text-red-700">{t('Status da Conta')}</p>
                                <p className="text-sm font-bold text-slate-900">{t('Conta Bloqueada')}</p>
                                <p className="mt-1 text-xs text-slate-600">{t('Sem novos lançamentos')}</p>
                            </div>
                        )}
                    </div>

                    {/* O QUE SEGURA A CONTA: linhas e filhas. É o que explica, de
                        antemão, por que é que apagar vai ser recusado. */}
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className={cls('border border-slate-200 px-4 py-3', RAIO)}>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Linhas de lançamento')}</p>
                            <p className="text-xl font-bold tabular-nums text-slate-900">{c.linhas.toLocaleString('pt-PT')}</p>
                        </div>
                        <div className={cls('border border-slate-200 px-4 py-3', RAIO)}>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Contas dependentes')}</p>
                            <p className="text-xl font-bold tabular-nums text-slate-900">{c.filhas.toLocaleString('pt-PT')}</p>
                        </div>
                    </div>

                    {c.descricao && (
                        <section className={cls('border border-slate-200 px-4 py-3.5', RAIO)}>
                            <h4 className="mb-2 flex items-center gap-2 text-sm font-bold text-slate-900">
                                <i className="fas fa-align-left text-blue-600" aria-hidden="true" />
                                {t('Descrição')}
                            </h4>
                            <p className="text-sm leading-relaxed text-slate-700">{c.descricao}</p>
                        </section>
                    )}

                    <section className={cls('border border-slate-200 bg-slate-50 px-4 py-3.5', RAIO)}>
                        <h4 className="mb-2 flex items-center gap-2 text-sm font-bold text-slate-700">
                            <i className="fas fa-clock text-slate-500" aria-hidden="true" />
                            {t('Informações do Sistema')}
                        </h4>
                        <ul className="grid gap-1.5 text-xs text-slate-600 sm:grid-cols-2">
                            <li><i className="fas fa-calendar-plus mr-1.5 text-slate-400" aria-hidden="true" />{t('Criado em')}: <strong>{c.criada_em ?? '—'}</strong></li>
                            <li><i className="fas fa-calendar-check mr-1.5 text-slate-400" aria-hidden="true" />{t('Atualizado em')}: <strong>{c.actualizada_em ?? '—'}</strong></li>
                            {c.chave_de_integracao && (
                                <li className="sm:col-span-2">
                                    <i className="fas fa-plug mr-1.5 text-slate-400" aria-hidden="true" />
                                    {t('Chave de Integração')}: <strong className="font-mono">{c.chave_de_integracao}</strong>
                                </li>
                            )}
                        </ul>
                    </section>
                </div>
            )}
        </Modal>
    );
}

/* ─── A razão da conta ────────────────────────────────────────────────── */

/**
 * O EXTRACTO DA CONTA, que o ecrã antigo não tinha.
 *
 * Começa no SALDO DE ABERTURA. Uma razão que começa a zero no primeiro dia do
 * filtro é um pedaço da conta, não a conta: sem a abertura, o saldo final não
 * bate com nada.
 */
function RazaoDaContaModal({ conta, aoFechar }: { conta: Conta | null; aoFechar: () => void }) {
    const hoje = new Date();

    const [periodo, porPeriodo] = useState({
        de: `${hoje.getFullYear()}-01-01`,
        ate: hoje.toISOString().slice(0, 10),
    });

    const razao = useQuery({
        queryKey: ['contabilidade', 'contas', 'razao', conta?.id, periodo],
        queryFn: () => contabilidade.contas.razao((conta as Conta).id, periodo),
        enabled: conta !== null,
    });

    const r = razao.data;

    return (
        <Modal
            aberto={conta !== null}
            aoFechar={aoFechar}
            titulo={conta ? `${conta.codigo} · ${conta.nome}` : t('Razão da conta')}
            subtitulo={t('O extracto da conta, linha a linha')}
            icone="fa-book-open"
            cor="primaria"
            largura="xl"
            rodape={<Botao onClick={aoFechar} icone="fa-times">{t('Fechar')}</Botao>}
        >
            <div className="mb-4 grid gap-3 sm:grid-cols-2">
                <Campo etiqueta={t('De')}>
                    <input
                        type="date"
                        value={periodo.de}
                        max={periodo.ate}
                        onChange={(e) => porPeriodo((x) => ({ ...x, de: e.target.value }))}
                        className={entrada}
                    />
                </Campo>
                <Campo etiqueta={t('até')}>
                    <input
                        type="date"
                        value={periodo.ate}
                        min={periodo.de}
                        onChange={(e) => porPeriodo((x) => ({ ...x, ate: e.target.value }))}
                        className={entrada}
                    />
                </Campo>
            </div>

            {razao.isPending || !r ? (
                <Carregando linhas={8} />
            ) : (
                <>
                    <div className="mb-4 grid gap-3 sm:grid-cols-4">
                        <Numero rotulo={t('Saldo de abertura')} valor={r.abertura} tom="ardosia" />
                        <Numero rotulo={t('Débito')} valor={r.totais.debito} tom="verde" />
                        <Numero rotulo={t('Crédito')} valor={r.totais.credito} tom="vermelho" />
                        <Numero rotulo={t('Saldo final')} valor={r.totais.saldo} tom="azul" destacado />
                    </div>

                    <p className="mb-3 text-xs text-slate-500">
                        <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                        {r.conta.cresce_a_debito
                            ? t('Esta conta cresce a débito: o acumulado é débito menos crédito.')
                            : t('Esta conta cresce a crédito: o acumulado é crédito menos débito.')}
                        {' '}
                        {t('Só entram lançamentos confirmados.')}
                    </p>

                    {r.data.length === 0 ? (
                        <SemNada
                            icone="fa-book-open"
                            titulo={t('Sem movimento neste período')}
                            frase={t('Experimente alargar as datas.')}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        {[t('Data'), t('Referência'), t('Diário'), t('Observação')].map((c) => (
                                            <th key={c} scope="col" className="px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{c}</th>
                                        ))}
                                        {[t('Débito'), t('Crédito'), t('Acumulado')].map((c) => (
                                            <th key={c} scope="col" className="px-3 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{c}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    <tr className="bg-slate-50/60">
                                        <td colSpan={6} className="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                            {t('Saldo de abertura')}
                                        </td>
                                        <td className="px-3 py-2 text-right font-bold tabular-nums text-slate-700">{kz(r.abertura)}</td>
                                    </tr>

                                    {r.data.map((l, i) => (
                                        <tr key={l.id} style={cascata(i)} className="entra transition-colors hover:bg-indigo-50/50">
                                            <td className="whitespace-nowrap px-3 py-2.5 text-slate-600">{l.dia}</td>
                                            <td className="whitespace-nowrap px-3 py-2.5">
                                                <span className="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">{l.ref}</span>
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2.5 text-slate-600">{l.diario ?? '—'}</td>
                                            <td className="max-w-xs truncate px-3 py-2.5 text-slate-600">{l.nota ?? '—'}</td>
                                            <td className="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-emerald-700">
                                                {l.debito > 0 ? kz(l.debito) : '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-red-700">
                                                {l.credito > 0 ? kz(l.credito) : '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2.5 text-right font-bold tabular-nums text-slate-900">
                                                {kz(l.acumulado)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="border-t-2 border-slate-300 bg-slate-50">
                                    <tr>
                                        <td colSpan={4} className="px-3 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-600">{t('Totais')}</td>
                                        <td className="px-3 py-2.5 text-right font-bold tabular-nums text-emerald-700">{kz(r.totais.debito)}</td>
                                        <td className="px-3 py-2.5 text-right font-bold tabular-nums text-red-700">{kz(r.totais.credito)}</td>
                                        <td className="px-3 py-2.5 text-right font-bold tabular-nums text-slate-900">{kz(r.totais.saldo)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    )}
                </>
            )}
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
    mono = false,
    className,
}: {
    rotulo: string;
    children: React.ReactNode;
    mono?: boolean;
    className?: string;
}) {
    return (
        <div className={className}>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</dt>
            <dd className={cls('text-sm font-semibold text-slate-900', mono && 'font-mono')}>{children}</dd>
        </div>
    );
}

const NUMEROS = {
    ardosia: 'border-slate-200 bg-slate-50 text-slate-700',
    verde: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    vermelho: 'border-red-200 bg-red-50 text-red-800',
    azul: 'border-blue-200 bg-blue-50 text-blue-900',
} as const;

function Numero({
    rotulo,
    valor,
    tom,
    destacado = false,
}: {
    rotulo: string;
    valor: number;
    tom: keyof typeof NUMEROS;
    destacado?: boolean;
}) {
    return (
        <div className={cls('border px-3 py-2.5', RAIO, NUMEROS[tom], destacado && 'ring-2 ring-blue-300')}>
            <p className="text-[11px] font-semibold uppercase tracking-wider opacity-80">{rotulo}</p>
            <p className="text-lg font-bold tabular-nums">{kz(valor)}</p>
        </div>
    );
}

/**
 * UM INTERRUPTOR COM EXPLICAÇÃO.
 *
 * «Conta de Visualização» não diz nada a quem não é contabilista; a frase por
 * baixo diz o que acontece, e era o que o ecrã em Blade tinha.
 */
function Interruptor({
    ligado,
    aoMudar,
    titulo,
    frase,
    icone,
    tom,
    erro,
    className,
}: {
    ligado: boolean;
    aoMudar: (v: boolean) => void;
    titulo: string;
    frase: string;
    icone: string;
    tom: 'azul' | 'vermelho';
    erro?: string[];
    className?: string;
}) {
    const cores = tom === 'azul'
        ? 'border-blue-200 bg-blue-50 text-blue-700'
        : 'border-red-200 bg-red-50 text-red-700';

    return (
        <div className={className}>
            <label className={cls('flex cursor-pointer items-start gap-3 border px-4 py-3 transition-all duration-200 hover:shadow-sm', RAIO, cores)}>
                <input
                    type="checkbox"
                    checked={ligado}
                    onChange={(e) => aoMudar(e.target.checked)}
                    className="mt-0.5 h-5 w-5 flex-none rounded border-slate-300 text-emerald-600 focus-visible:ring-2 focus-visible:ring-emerald-500"
                />
                <span className="min-w-0">
                    <span className="block text-sm font-bold text-slate-900">
                        <i className={cls('fas mr-1.5', icone)} aria-hidden="true" />
                        {titulo}
                    </span>
                    <span className="block text-xs text-slate-600">{frase}</span>
                </span>
            </label>

            {erro?.[0] && <p role="alert" className="mt-1 text-xs font-medium text-red-600">{erro[0]}</p>}
        </div>
    );
}
