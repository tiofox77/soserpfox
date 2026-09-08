import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { FiltrosDosMovimentos, Movimento } from '@/api/tesouraria';
import { movimentos } from '@/api/tesouraria';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { IntervaloDeDatas, PorPagina } from '@/ui/FiltrosComuns';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, Faixa } from '../facturacao/faixa';
import { EtiquetaDeEstado, EtiquetaDeTipo, FichaDoMovimento } from './FichaDoMovimento';
import { ModalDoMovimento } from './ModalDoMovimento';

/**
 * OS MOVIMENTOS DA TESOURARIA — entradas, saídas e o que sobra.
 *
 * O DINHEIRO MEXE-SE NUM SÍTIO SÓ: a API chama o `TreasuryMovementService`,
 * que grava o movimento e move o saldo sob bloqueio. Este ecrã desenha.
 *
 * O QUE ESTAVA ESCRITO E NÃO SE VIA. O componente em Blade tinha o `edit()`,
 * o `confirmDelete()` e o modal de eliminar completos — e a tabela não tinha
 * botão nenhum para lá chegar. Um movimento lançado com o valor errado só se
 * corrigia na base de dados. Os botões existem agora, cada um atrás da sua
 * permissão.
 *
 * ESTORNAR NÃO É ANULAR UMA VENDA. Um movimento sem factura estorna-se aqui,
 * com uma saída de sinal contrário. Com factura por trás, o caminho é a nota
 * de crédito — linhas escolhidas, imposto recalculado, stock reposto, hash e
 * comunicação à AGT — e o botão leva ao relatório do POS, que a emite pela
 * porta própria.
 */
export default function Movimentos() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDosMovimentos>({ por_pagina: 25, page: 1 });
    const [recado, porRecado] = useState('');
    const [modal, porModal] = useState<{ aberto: boolean; aEditar: Movimento | null }>({ aberto: false, aEditar: null });
    const [aVer, porAVer] = useState<number | null>(null);
    const [aEstornar, porAEstornar] = useState<Movimento | null>(null);
    const [aApagar, porAApagar] = useState<Movimento | null>(null);

    const opcoes = useQuery({
        queryKey: ['tesouraria', 'movimentos', 'opcoes'],
        queryFn: movimentos.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['tesouraria', 'movimentos', filtros],
        queryFn: () => movimentos.lista(filtros),
        placeholderData: keepPreviousData,
    });

    function feito(mensagem: string) {
        porRecado(mensagem);
        void cache.invalidateQueries({ queryKey: ['tesouraria'] });
    }

    const estornar = useMutation({
        mutationFn: (m: Movimento) => movimentos.creditar(m.id),
        onSuccess: (r) => {
            porAEstornar(null);
            feito(r.message);
        },
    });

    const apagar = useMutation({
        mutationFn: (m: Movimento) => movimentos.eliminar(m.id),
        onSuccess: (r) => {
            porAApagar(null);
            feito(r.message);
        },
    });

    if (opcoes.isPending) return <Carregando linhas={10} />;

    if (opcoes.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as transações')}</h2>
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
        filtros.procura || filtros.tipo || filtros.estado || filtros.categoria ||
        filtros.conta || filtros.caixa || filtros.de || filtros.ate,
    );

    /** Mexer num filtro volta à primeira página — senão a lista sai vazia. */
    const mudar = (campos: Partial<FiltrosDosMovimentos>) =>
        porFiltros((f) => ({ ...f, ...campos, page: 1 }));

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Transações')}
                subtitulo={t('Movimentos financeiros e extratos')}
                icone="fa-exchange-alt"
                cor="teal"
                accoes={
                    o.permissoes.pode_criar && (
                        <button
                            type="button"
                            onClick={() => porModal({ aberto: true, aEditar: null })}
                            className={ACCAO_DA_FAIXA}
                        >
                            <i className="fas fa-plus" aria-hidden="true" />
                            {t('Nova Transação')}
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

            <ErroDaAccao erro={estornar.error ?? apagar.error} />

            {/* OS QUATRO NÚMEROS. Seguem os filtros da lista — somar tudo desde
                sempre por cima de uma lista de um mês faz desconfiar dos dois. */}
            {resumo && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <CartaoNumero rotulo={t('Total Transações')} valor={resumo.movimentos.toLocaleString('pt-PT')} icone="fa-exchange-alt" tom="teal" aspecto="claro" />
                    <CartaoNumero rotulo={t('Entradas')} valor={kz(resumo.entradas)} sufixo="Kz" icone="fa-arrow-down" tom="verde" aspecto="claro" />
                    <CartaoNumero rotulo={t('Saídas')} valor={kz(resumo.saidas)} sufixo="Kz" icone="fa-arrow-up" tom="vermelho" aspecto="claro" />
                    <CartaoNumero
                        rotulo={t('Saldo')}
                        valor={kz(resumo.saldo)}
                        sufixo="Kz"
                        icone="fa-balance-scale"
                        tom={resumo.saldo < 0 ? 'vermelho' : 'azul'}
                        aspecto="claro"
                        nota={comFiltro ? t('No período e filtros escolhidos') : undefined}
                    />
                </div>
            )}

            <Cartao titulo={t('Filtrar')} icone="fa-filter">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Campo etiqueta={t('Pesquisar')} className="lg:col-span-2">
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => mudar({ procura: e.target.value })}
                            placeholder={t('Número, descrição ou referência…')}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Tipo')}>
                        <select value={filtros.tipo ?? ''} onChange={(e) => mudar({ tipo: e.target.value })} className={entrada}>
                            <option value="">{t('Todos Tipos')}</option>
                            <option value="income">{t('Entrada')}</option>
                            <option value="expense">{t('Saída')}</option>
                            <option value="transfer">{t('Transferência')}</option>
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Status')}>
                        <select value={filtros.estado ?? ''} onChange={(e) => mudar({ estado: e.target.value })} className={entrada}>
                            <option value="">{t('Todos Status')}</option>
                            <option value="pending">{t('Pendente')}</option>
                            <option value="completed">{t('Concluído')}</option>
                            <option value="cancelled">{t('Cancelado')}</option>
                        </select>
                    </Campo>

                    <IntervaloDeDatas
                        de={filtros.de}
                        ate={filtros.ate}
                        aoMudar={(campo, valor) => mudar({ [campo]: valor })}
                        rotulo={t('Data de')}
                    />

                    <Campo etiqueta={t('Categoria')}>
                        <select value={filtros.categoria ?? ''} onChange={(e) => mudar({ categoria: e.target.value })} className={entrada}>
                            <option value="">{t('Todas')}</option>
                            {o.categorias_para_filtrar.map((c) => (
                                <option key={c.valor} value={c.valor}>{c.rotulo}</option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Conta')}>
                        <select
                            value={filtros.conta ?? ''}
                            onChange={(e) => mudar({ conta: e.target.value ? Number(e.target.value) : '' })}
                            className={entrada}
                        >
                            <option value="">{t('Todas')}</option>
                            {o.contas.map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Caixa')}>
                        <select
                            value={filtros.caixa ?? ''}
                            onChange={(e) => mudar({ caixa: e.target.value ? Number(e.target.value) : '' })}
                            className={entrada}
                        >
                            <option value="">{t('Todos')}</option>
                            {o.caixas.map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
                        </select>
                    </Campo>
                </div>

                {comFiltro && (
                    <div className={cls('mt-4 flex flex-wrap items-center justify-between gap-3 border border-teal-200 bg-teal-50 px-4 py-2.5', RAIO)}>
                        <span className="text-sm text-teal-800">
                            <i className="fas fa-filter mr-1" aria-hidden="true" />
                            {t('A mostrar :quantos transacção(ões) — os totais acima seguem estes filtros.', {
                                quantos: (contas?.total ?? 0).toLocaleString('pt-PT'),
                            })}
                        </span>
                        <button
                            type="button"
                            onClick={() => porFiltros({ por_pagina: filtros.por_pagina, page: 1 })}
                            className={cls('text-sm font-semibold text-teal-700 hover:text-teal-900', FOCO, RAIO)}
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
                        <thead className="bg-gradient-to-r from-teal-50 to-cyan-50">
                            <tr>
                                {[t('Data'), t('Número'), t('Descrição'), t('Tipo'), t('Método')].map((c) => (
                                    <th key={c} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-teal-700">{c}</th>
                                ))}
                                <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-teal-700">{t('Valor')}</th>
                                <th scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-teal-700">{t('Status')}</th>
                                <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-teal-700">{t('Ações')}</th>
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
                                            icone="fa-exchange-alt"
                                            titulo={t('Nenhuma transação encontrada')}
                                            frase={comFiltro ? t("Experimente alargar o período ou limpar os filtros.") : undefined}
                                        />
                                    </td>
                                </tr>
                            )}

                            {linhas.map((m, i) => (
                                <tr key={m.id} style={cascata(i)} className="entra transition-colors hover:bg-teal-50/60">
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-600">{m.data_curta}</td>
                                    <td className="whitespace-nowrap px-4 py-3">
                                        <span className="rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700">{m.numero}</span>
                                    </td>
                                    <td className="max-w-xs px-4 py-3">
                                        <p className="truncate font-semibold text-slate-900">{m.descricao || t('Sem descrição')}</p>
                                        {m.categoria_nome && <p className="truncate text-xs text-slate-500">{m.categoria_nome}</p>}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3"><EtiquetaDeTipo tipo={m.tipo} /></td>
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-600">{m.forma_de_pagamento ?? '—'}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        <p className={cls('text-base font-bold tabular-nums', m.tipo === 'income' ? 'text-emerald-600' : 'text-red-600')}>
                                            {m.tipo === 'income' ? '+' : '−'} {kz(m.valor)}
                                        </p>
                                        <p className="text-xs text-slate-500">{m.moeda}</p>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3"><EtiquetaDeEstado estado={m.estado} /></td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1.5">
                                            <Accao
                                                icone="fa-eye"
                                                rotulo={t('Ver')}
                                                titulo={t('Ver :numero', { numero: m.numero })}
                                                classe="border-teal-200 bg-teal-50 text-teal-700 hover:border-teal-300"
                                                onClick={() => porAVer(m.id)}
                                            />

                                            {o.permissoes.pode_editar && (
                                                <Accao
                                                    icone="fa-pen"
                                                    rotulo={t('Editar')}
                                                    titulo={t('Editar :numero', { numero: m.numero })}
                                                    classe="border-indigo-200 bg-indigo-50 text-indigo-700 hover:border-indigo-300"
                                                    onClick={() => porModal({ aberto: true, aEditar: m })}
                                                />
                                            )}

                                            {/* ESTORNAR. Com factura por trás isto é uma
                                                nota de crédito e vai para o sítio que a
                                                emite; sem factura, resolve-se aqui. */}
                                            {o.permissoes.pode_criar && m.tipo === 'income' && m.estado === 'completed' && (
                                                m.factura_id ? (
                                                    <a
                                                        href={`/invoicing/pos/reports?credit_transaction=${m.id}`}
                                                        title={t('Anular a venda com uma nota de crédito')}
                                                        aria-label={t('Creditar :numero com nota de crédito', { numero: m.numero })}
                                                        className={cls(BOTAO_DE_ACCAO, 'border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-300')}
                                                    >
                                                        <i className="fas fa-file-circle-minus" aria-hidden="true" />
                                                        {t('Creditar')}
                                                    </a>
                                                ) : (
                                                    <Accao
                                                        icone="fa-rotate-left"
                                                        rotulo={t('Creditar')}
                                                        titulo={t('Estornar :numero', { numero: m.numero })}
                                                        classe="border-orange-200 bg-orange-50 text-orange-700 hover:border-orange-300"
                                                        onClick={() => porAEstornar(m)}
                                                    />
                                                )
                                            )}

                                            {o.permissoes.pode_apagar && (
                                                <Accao
                                                    icone="fa-trash"
                                                    rotulo=""
                                                    titulo={t('Eliminar :numero', { numero: m.numero })}
                                                    classe="border-red-200 bg-red-50 text-red-700 hover:border-red-300"
                                                    onClick={() => porAApagar(m)}
                                                />
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
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

            <ModalDoMovimento
                aberto={modal.aberto}
                o={o}
                aEditar={modal.aEditar}
                aoFechar={() => porModal({ aberto: false, aEditar: null })}
                aoGravar={(mensagem) => {
                    porModal({ aberto: false, aEditar: null });
                    feito(mensagem);
                }}
            />

            <FichaDoMovimento id={aVer} aoFechar={() => porAVer(null)} />

            {/* ESTORNAR — o que vai acontecer, escrito antes de acontecer. */}
            <Modal
                aberto={aEstornar !== null}
                aoFechar={() => porAEstornar(null)}
                titulo={t('Creditar Transação')}
                subtitulo={aEstornar?.numero}
                icone="fa-rotate-left"
                cor="laranja"
                largura="md"
                rodape={
                    <>
                        <Botao onClick={() => porAEstornar(null)} icone="fa-times">{t('Cancelar')}</Botao>
                        <Botao
                            cor="aviso"
                            tom="solida"
                            icone="fa-rotate-left"
                            aTrabalhar={estornar.isPending}
                            onClick={() => aEstornar && estornar.mutate(aEstornar)}
                        >
                            {t('Confirmar Crédito')}
                        </Botao>
                    </>
                }
            >
                {aEstornar && (
                    <div className="space-y-4">
                        <div className={cls('border-2 border-amber-300 bg-amber-50 px-4 py-3', RAIO)}>
                            <p className="mb-1 flex items-center gap-2 text-sm font-bold text-amber-900">
                                <i className="fas fa-exclamation-triangle" aria-hidden="true" />
                                {t('Atenção!')}
                            </p>
                            <p className="text-xs text-amber-800">
                                {t('Esta ação criará uma transação de estorno/crédito que reverterá o valor desta transação. O saldo será atualizado automaticamente.')}
                            </p>
                        </div>

                        <div className={cls('bg-slate-50 px-4 py-3', RAIO)}>
                            <p className="mb-3 text-xs font-semibold text-slate-500">{t('Transação Original')}:</p>
                            <dl className="space-y-2 text-sm">
                                <Par rotulo={t('Número')}><span className="font-mono font-bold">{aEstornar.numero}</span></Par>
                                <Par rotulo={t('Data')}>{aEstornar.data_curta}</Par>
                                <Par rotulo={t('Descrição')}>{aEstornar.descricao}</Par>
                                <div className="flex justify-between border-t border-slate-300 pt-2">
                                    <dt className="text-slate-600">{t('Valor a Creditar')}:</dt>
                                    <dd className="text-xl font-bold tabular-nums text-red-600">− {kz(aEstornar.valor)} {aEstornar.moeda}</dd>
                                </div>
                            </dl>
                        </div>

                        <div className={cls('border-2 border-red-200 bg-red-50 px-4 py-3', RAIO)}>
                            <p className="mb-3 text-xs font-semibold text-red-700">{t('Transação de Crédito que será criada')}:</p>
                            <ul className="space-y-1.5 text-sm text-slate-700">
                                {[
                                    [t('Tipo'), t('Saída (Estorno)')],
                                    [t('Categoria'), t('Nota de Crédito')],
                                    [t('Referência'), `CREDIT-${aEstornar.numero}`],
                                    [t('Status'), t('Concluído')],
                                ].map(([rotulo, valor]) => (
                                    <li key={rotulo} className="flex items-center gap-2">
                                        <i className="fas fa-check text-red-600" aria-hidden="true" />
                                        <span>{rotulo}: <strong>{valor}</strong></span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}
            </Modal>

            {/* ELIMINAR — o modal existia no Blade e não havia botão que lá chegasse. */}
            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar Transação')}
                subtitulo={aApagar?.numero}
                icone="fa-trash-alt"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)} icone="fa-times">{t('Cancelar')}</Botao>
                        <Botao
                            cor="perigo"
                            tom="solida"
                            icone="fa-trash-alt"
                            aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar)}
                        >
                            {t('Sim, Eliminar')}
                        </Botao>
                    </>
                }
            >
                {aApagar && (
                    <div className="space-y-4">
                        <p className="text-center text-slate-600">
                            {t('Tem certeza que deseja eliminar a transação')}{' '}
                            <span className="font-bold text-red-600">{aApagar.numero}</span>?
                        </p>
                        <div className={cls('border-l-4 border-red-500 bg-red-50 px-4 py-3', RAIO)}>
                            <p className="text-sm font-semibold text-red-700">
                                <i className="fas fa-exclamation-triangle mr-2" aria-hidden="true" />
                                {t('Atenção!')}
                            </p>
                            <p className="mt-1 text-sm text-red-600">
                                {t('Esta ação não pode ser revertida e afetará os saldos das contas/caixas.')}
                            </p>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}

/* ─── As peças ────────────────────────────────────────────────────────── */

const BOTAO_DE_ACCAO = cls(
    'inline-flex items-center gap-1.5 border px-2.5 py-1.5 text-xs font-semibold',
    'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm',
    RAIO,
    FOCO,
);

function Accao({
    icone,
    rotulo,
    titulo,
    classe,
    onClick,
}: {
    icone: string;
    /** Vazio deixa só o ícone — para o que se repete em todas as linhas. */
    rotulo: string;
    /** O nome acessível, sempre completo: «Eliminar TRX-2026-0004». */
    titulo: string;
    classe: string;
    onClick: () => void;
}) {
    return (
        <button type="button" onClick={onClick} title={titulo} aria-label={titulo} className={cls(BOTAO_DE_ACCAO, classe)}>
            <i className={cls('fas', icone)} aria-hidden="true" />
            {rotulo}
        </button>
    );
}

function Par({ rotulo, children }: { rotulo: string; children: React.ReactNode }) {
    return (
        <div className="flex justify-between gap-3">
            <dt className="shrink-0 text-slate-600">{rotulo}:</dt>
            <dd className="min-w-0 truncate text-right font-semibold text-slate-900">{children}</dd>
        </div>
    );
}

/**
 * O erro de uma acção — e a porta certa quando o servidor a indica.
 *
 * Estornar um movimento com factura devolve 409 com a morada do relatório do
 * POS. Sem isto, a indicação chegava e ficava por dizer.
 */
function ErroDaAccao({ erro }: { erro: unknown }) {
    if (!erro) return null;

    const daApi = erro instanceof ErroDaApi ? erro : null;
    const morada = typeof daApi?.corpo.redireccionar === 'string' ? daApi.corpo.redireccionar : null;

    return (
        <div role="alert" className={cls('flex flex-wrap items-center justify-between gap-3 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
            <span>
                <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                {daApi?.message ?? t('A operação não foi concluída.')}
            </span>
            {morada && (
                <a href={morada} className={cls(BOTAO_DE_ACCAO, 'border-red-300 bg-white text-red-700')}>
                    <i className="fas fa-arrow-right" aria-hidden="true" />
                    {t('Emitir a nota de crédito')}
                </a>
            )}
        </div>
    );
}
