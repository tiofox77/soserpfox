import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type EmpresaDaLista, type FiltrosDasEmpresas, plataforma } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, kz } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { ApagarDefinitivo, Desactivar, Suspender } from './empresas/Accoes';
import { Detalhes } from './empresas/Detalhes';
import { Formulario } from './empresas/Formulario';
import { PlanoAMedida } from './empresas/PlanoAMedida';
import { PlanoDaEmpresa } from './empresas/PlanoDaEmpresa';
import { Utilizadores } from './empresas/Utilizadores';
import { corDaSubscricao } from './empresas/cores';

/**
 * AS EMPRESAS — quem são, se estão vivas, e o que se lhes faz.
 *
 * Era o componente maior do painel do dono (1460 linhas e sete modais num só
 * ficheiro de Livewire). A lista, os cartões de estado e os filtros vivem aqui;
 * cada janela vive no seu ficheiro em `./empresas/`.
 *
 * O QUE MELHOROU:
 *
 *  · AS ACÇÕES DE CADA EMPRESA estavam escondidas até se passar o rato por
 *    cima do cartão (`opacity-0 group-hover:opacity-100`). Num telemóvel ou num
 *    tablet não há rato: não havia como chegar a nenhuma. Ficam sempre à vista.
 *  · O «A ENVIAR EMAILS…» do botão de desligar prendia o ecrã enquanto o
 *    servidor mandava um email por pessoa. Os emails saem depois da resposta, e
 *    o ecrã volta logo.
 *  · O NIF QUE NÃO É DE EMPRESA e o país entram na ficha, e a ficha diz o que o
 *    plano já dá, em vez de deixar escrever um limite que o servidor ignora.
 */
export default function Empresas() {
    const fila = useQueryClient();
    const [filtros, porFiltros] = useState<FiltrosDasEmpresas>({ ordenar: 'recentes', por_pagina: '10', pagina: 1 });
    const [recado, porRecado] = useState<string | null>(null);

    const [aEditar, porAEditar] = useState<number | 'nova' | null>(null);
    const [aVer, porAVer] = useState<number | null>(null);
    const [aDesactivar, porADesactivar] = useState<EmpresaDaLista | null>(null);
    const [aSuspender, porASuspender] = useState<EmpresaDaLista | null>(null);
    const [aApagar, porAApagar] = useState<EmpresaDaLista | null>(null);
    const [pessoasDe, porPessoasDe] = useState<number | null>(null);
    const [planoDe, porPlanoDe] = useState<number | null>(null);
    const [medidaDe, porMedidaDe] = useState<number | null>(null);

    const lista = useQuery({
        queryKey: ['plataforma', 'empresas', filtros],
        queryFn: () => plataforma.empresas.ler(filtros),
        placeholderData: keepPreviousData,
        staleTime: 15_000,
    });

    const refrescar = () => void fila.invalidateQueries({ queryKey: ['plataforma', 'empresas'] });
    const feito = (mensagem: string) => { porRecado(mensagem); refrescar(); };

    const activar = useMutation({
        mutationFn: (id: number) => plataforma.empresas.activar(id),
        onSuccess: (r) => feito(r.message),
    });

    // QUALQUER FILTRO VOLTA À PRIMEIRA PÁGINA — filtrar na página 3 mostrava
    // «nenhum resultado» com resultados a existir na primeira.
    const mexer = (campo: keyof FiltrosDasEmpresas, valor: string) =>
        porFiltros((f) => ({ ...f, [campo]: valor || undefined, pagina: 1 }));

    const temFiltros = Boolean(filtros.procura || filtros.estado || filtros.plano || filtros.activa);

    if (lista.isPending) return <Carregando linhas={10} />;

    if (lista.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir as empresas')}</h2>
                <p className="text-sm text-red-800">
                    {lista.error instanceof ErroDaApi ? lista.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const { empresas, paginacao, contagens, opcoes } = lista.data;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Empresas')}
                subtitulo={t('Quem são, se estão vivas, e o que se lhes faz')}
                icone="fa-building"
                cor="primaria"
                accoes={
                    <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porAEditar('nova')}>
                        <i className="fas fa-plus" aria-hidden="true" />
                        {t('Nova empresa')}
                    </button>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <EstadoNaFaixa icone="fa-building">
                        {t(':n empresa(s) na lista', { n: paginacao.total })}
                    </EstadoNaFaixa>
                    {lista.isFetching && (
                        <EstadoNaFaixa icone="fa-rotate fa-spin">{t('A actualizar…')}</EstadoNaFaixa>
                    )}
                </div>
            </Faixa>

            {recado && (
                <div
                    role="status"
                    className={cls('entra flex items-start justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}
                >
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado(null)} className={cls('text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                        <span className="sr-only">{t('Fechar')}</span>
                    </button>
                </div>
            )}

            <AvisoDeErro erro={activar.error} />

            {/* OS CARTÕES DE ESTADO: contagem e filtro ao mesmo tempo. Contados
                ANTES do filtro de estado — clicar num não pode zerar os outros. */}
            <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                {estados().map((e, i) => {
                    const aceso = filtros.estado === e.chave;

                    return (
                        <button
                            key={e.chave}
                            type="button"
                            aria-pressed={aceso}
                            onClick={() => mexer('estado', aceso ? '' : e.chave)}
                            className={cls(
                                'cascata border-2 p-3 text-left', RAIO, TRANSICAO, FOCO,
                                'hover:-translate-y-0.5 hover:shadow-md',
                                aceso ? e.aceso : 'border-slate-200 bg-white',
                            )}
                            style={cascata(i)}
                        >
                            <div className="flex items-center justify-between">
                                <i className={cls('fas', e.icone, e.texto)} aria-hidden="true" />
                                <span className="text-2xl font-extrabold text-slate-900 tabular-nums">
                                    {contagens[e.chave] ?? 0}
                                </span>
                            </div>
                            <p className="mt-1 text-xs font-semibold text-slate-600">{e.rotulo}</p>
                        </button>
                    );
                })}
            </div>

            <div className={cls(CARTAO, 'p-4')}>
                <div className="grid gap-3 md:grid-cols-6">
                    <label className="block md:col-span-2">
                        <Rotulo>{t('Procurar')}</Rotulo>
                        <div className="relative">
                            <i className="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <input
                                type="search"
                                className={cls(entrada, 'pl-9')}
                                placeholder={t('Nome, email, NIF…')}
                                value={filtros.procura ?? ''}
                                onChange={(e) => mexer('procura', e.target.value)}
                            />
                        </div>
                    </label>
                    <label className="block">
                        <Rotulo>{t('Plano')}</Rotulo>
                        <select className={entrada} value={filtros.plano ?? ''} onChange={(e) => mexer('plano', e.target.value)}>
                            <option value="">{t('Todos os planos')}</option>
                            {opcoes.planos.map((p) => <option key={p.valor} value={p.valor}>{p.rotulo}</option>)}
                        </select>
                    </label>
                    <label className="block">
                        <Rotulo>{t('Estado')}</Rotulo>
                        <select className={entrada} value={filtros.activa ?? ''} onChange={(e) => mexer('activa', e.target.value)}>
                            <option value="">{t('Activas e desactivadas')}</option>
                            <option value="1">{t('Só activas')}</option>
                            <option value="0">{t('Só desactivadas')}</option>
                        </select>
                    </label>
                    <label className="block">
                        <Rotulo>{t('Ordenar')}</Rotulo>
                        <select className={entrada} value={filtros.ordenar ?? 'recentes'} onChange={(e) => mexer('ordenar', e.target.value)}>
                            {opcoes.ordenacoes.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                        </select>
                    </label>
                    <label className="block">
                        <Rotulo>{t('Por página')}</Rotulo>
                        <select className={entrada} value={filtros.por_pagina ?? '10'} onChange={(e) => mexer('por_pagina', e.target.value)}>
                            {['10', '25', '50'].map((n) => <option key={n} value={n}>{n}</option>)}
                        </select>
                    </label>
                </div>

                {temFiltros && (
                    <div className="entra mt-3 flex justify-end border-t border-slate-100 pt-3">
                        <Botao
                            cor="neutra"
                            altura="pequeno"
                            icone="fa-rotate-left"
                            onClick={() => porFiltros((f) => ({ ordenar: 'recentes', por_pagina: f.por_pagina, pagina: 1 }))}
                        >
                            {t('Limpar filtros')}
                        </Botao>
                    </div>
                )}
            </div>

            {empresas.length === 0 ? (
                <SemNada
                    icone="fa-building"
                    titulo={temFiltros ? t('Nenhuma empresa com estes filtros') : t('Ainda não há empresas')}
                    frase={temFiltros ? t('Mude ou limpe os filtros.') : t('As empresas aparecem aqui quando se registam ou quando as cria.')}
                    accao={!temFiltros && (
                        <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porAEditar('nova')}>
                            {t('Criar empresa')}
                        </Botao>
                    )}
                />
            ) : (
                <div className="space-y-3">
                    {empresas.map((e, i) => (
                        <CartaoDaEmpresa
                            key={e.id}
                            empresa={e}
                            indice={i}
                            aActivar={activar.isPending && activar.variables === e.id}
                            accoes={{
                                pessoas: () => porPessoasDe(e.id),
                                plano: () => porPlanoDe(e.id),
                                medida: () => porMedidaDe(e.id),
                                ver: () => porAVer(e.id),
                                editar: () => porAEditar(e.id),
                                alternar: () => (e.activa ? porADesactivar(e) : activar.mutate(e.id)),
                                suspender: () => porASuspender(e),
                                apagar: () => porAApagar(e),
                            }}
                        />
                    ))}
                </div>
            )}

            {paginacao.ultima > 1 && (
                <nav className={cls(CARTAO, 'flex items-center justify-between px-5 py-3')} aria-label={t('Páginas')}>
                    <Botao
                        icone="fa-chevron-left"
                        altura="pequeno"
                        disabled={paginacao.pagina <= 1}
                        onClick={() => porFiltros((f) => ({ ...f, pagina: paginacao.pagina - 1 }))}
                    >
                        {t('Anterior')}
                    </Botao>
                    <span className="text-sm tabular-nums text-slate-600">
                        {t('Página :pagina de :paginas', { pagina: paginacao.pagina, paginas: paginacao.ultima })}
                    </span>
                    <Botao
                        icone="fa-chevron-right"
                        altura="pequeno"
                        disabled={paginacao.pagina >= paginacao.ultima}
                        onClick={() => porFiltros((f) => ({ ...f, pagina: paginacao.pagina + 1 }))}
                    >
                        {t('Seguinte')}
                    </Botao>
                </nav>
            )}

            {aEditar !== null && (
                <Formulario
                    id={aEditar === 'nova' ? null : aEditar}
                    paises={opcoes.paises}
                    aoFechar={() => porAEditar(null)}
                    aoGuardar={(m) => { porAEditar(null); feito(m); }}
                />
            )}

            {aVer !== null && (
                <Detalhes
                    id={aVer}
                    aoFechar={() => porAVer(null)}
                    aoEditar={() => { porAEditar(aVer); porAVer(null); }}
                />
            )}

            {aDesactivar && (
                <Desactivar empresa={aDesactivar} aoFechar={() => porADesactivar(null)} aoFazer={(m) => { porADesactivar(null); feito(m); }} />
            )}

            {aSuspender && (
                <Suspender empresa={aSuspender} aoFechar={() => porASuspender(null)} aoFazer={(m) => { porASuspender(null); feito(m); }} />
            )}

            {aApagar && (
                <ApagarDefinitivo empresa={aApagar} aoFechar={() => porAApagar(null)} aoFazer={(m) => { porAApagar(null); feito(m); }} />
            )}

            {pessoasDe !== null && (
                <Utilizadores id={pessoasDe} aoFechar={() => { porPessoasDe(null); refrescar(); }} />
            )}

            {planoDe !== null && (
                <PlanoDaEmpresa id={planoDe} aoFechar={() => porPlanoDe(null)} aoGuardar={(m) => { porPlanoDe(null); feito(m); }} />
            )}

            {medidaDe !== null && (
                <PlanoAMedida id={medidaDe} aoFechar={() => porMedidaDe(null)} aoGuardar={(m) => { porMedidaDe(null); feito(m); }} />
            )}
        </div>
    );
}

/* ─── O cartão de uma empresa ─────────────────────────────────────────── */

function CartaoDaEmpresa({ empresa: e, indice, aActivar, accoes }: {
    empresa: EmpresaDaLista;
    indice: number;
    aActivar: boolean;
    accoes: Record<'pessoas' | 'plano' | 'medida' | 'ver' | 'editar' | 'alternar' | 'suspender' | 'apagar', () => void>;
}) {
    const vida = e.vida;
    const estado = vida ? estados().find((x) => x.chave === vida.chave) : null;

    return (
        <article className={cls(CARTAO, 'card-hover cascata p-5', !e.activa && 'bg-slate-50/80')} style={cascata(indice)}>
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start">
                <div className="relative shrink-0">
                    {e.logo ? (
                        <img src={e.logo} alt="" className="h-14 w-14 rounded-xl object-cover shadow" />
                    ) : (
                        <span className="grid h-14 w-14 place-items-center rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 text-lg font-bold text-white shadow">
                            {e.nome.slice(0, 2).toUpperCase()}
                        </span>
                    )}
                    <span className={cls(
                        'absolute -bottom-1 -right-1 h-5 w-5 rounded-full border-2 border-white shadow',
                        e.activa ? 'animate-pulse bg-emerald-500' : 'bg-slate-400',
                    )} />
                </div>

                <div className="min-w-0 flex-1 space-y-3">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="min-w-0">
                            <h3 className="truncate text-lg font-bold text-slate-900">{e.nome}</h3>
                            <p className="text-sm text-slate-500">{e.slug}</p>
                        </div>
                        <Etiqueta cor={e.activa ? 'bom' : 'neutra'} ponto>
                            {e.activa ? t('Activa') : t('Desactivada')}
                        </Etiqueta>
                    </div>

                    <dl className="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                        <Dado icone="fa-envelope" cor="bg-blue-100 text-blue-600" rotulo={t('Email')} valor={e.email ?? '—'} />
                        <Dado icone="fa-phone" cor="bg-emerald-100 text-emerald-600" rotulo={t('Telefone')} valor={e.telefone ?? '—'} />
                        <Dado
                            icone="fa-id-card"
                            cor="bg-amber-100 text-amber-600"
                            rotulo={t('NIF')}
                            valor={e.nif
                                ? (
                                    <>
                                        {/* Um NIF lê-se dígito a dígito: é assim que se
                                            confere ao telefone. */}
                                        <span className="font-mono">{e.nif}</span>
                                        {e.nif_de_empresa === false && (
                                            <span className="block text-[11px] font-semibold text-amber-700">
                                                <i className="fas fa-triangle-exclamation mr-1" aria-hidden="true" />
                                                {t('Não é NIF de empresa')}
                                            </span>
                                        )}
                                    </>
                                )
                                : <span className="text-slate-400">{t('por preencher')}</span>}
                        />
                        <Dado icone="fa-calendar" cor="bg-purple-100 text-purple-600" rotulo={t('Criada em')} valor={e.criada_em ?? '—'} />
                    </dl>

                    <div className="flex flex-wrap items-center gap-2">
                        {e.plano ? (
                            <span className="inline-flex items-center gap-1.5 rounded-lg border border-purple-200 bg-gradient-to-r from-purple-50 to-indigo-50 px-3 py-1.5 text-xs font-bold text-purple-700">
                                <i className="fas fa-crown text-yellow-500" aria-hidden="true" />
                                {e.plano}
                                {e.ciclo && <span className="rounded bg-purple-200 px-1.5 py-0.5 text-[10px] text-purple-800">{e.ciclo}</span>}
                            </span>
                        ) : (
                            <Etiqueta cor="neutra" icone="fa-circle-question">{t('Sem plano')}</Etiqueta>
                        )}
                        {/* «no plano» e «criados»: lado a lado, «5 utilizadores» e «1 users»
                            liam-se como uma contradição em vez de limite e realidade. */}
                        <Etiqueta cor="aviso" icone="fa-users">{t(':n utilizadores no plano', { n: e.max_utilizadores })}</Etiqueta>
                        <Etiqueta cor="primaria" icone="fa-user-check">{t(':n criados', { n: e.utilizadores })}</Etiqueta>
                        <Etiqueta cor="neutra" icone="fa-database">{t(':n MB de espaço', { n: e.max_espaco_mb })}</Etiqueta>
                        {e.modulos > 0 && <Etiqueta cor="primaria" icone="fa-puzzle-piece">{t(':n módulos', { n: e.modulos })}</Etiqueta>}
                    </div>

                    {/* ESTÁ VIVA OU É SÓ UMA LINHA NA BASE? Nome, plano e utilizadores
                        não distinguem um cliente que factura todos os dias de um que
                        se registou e nunca mais voltou. */}
                    {vida && (
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-slate-100 pt-3 text-xs text-slate-600">
                            {estado && (
                                <span className={cls('inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 font-bold', estado.suave)}>
                                    <i className={cls('fas', estado.icone)} aria-hidden="true" />
                                    {vida.texto}
                                </span>
                            )}
                            {/* EM QUE PÉ ESTÁ A SUBSCRIÇÃO: em teste? falta quanto? já
                                passou do prazo e continua a usar? */}
                            <span title={e.subscricao.detalhe}>
                                <Etiqueta cor={corDaSubscricao(e.subscricao.cor)} icone={e.subscricao.icone}>
                                    {e.subscricao.rotulo}
                                    {e.subscricao.falta && <span className="font-normal opacity-90">· {e.subscricao.falta}{e.subscricao.ate && ` (${e.subscricao.ate})`}</span>}
                                    {!e.subscricao.falta && e.subscricao.nota && <span className="font-normal opacity-90">· {e.subscricao.nota}</span>}
                                </Etiqueta>
                            </span>
                            <Sinal icone="fa-file-invoice" titulo={t('Facturas emitidas nos últimos 30 dias')}>
                                {t(':n factura(s)/30d', { n: vida.facturas_30d })}
                            </Sinal>
                            <Sinal icone="fa-box" titulo={t('Artigos no catálogo')}>
                                {t(':n artigo(s)', { n: kz(vida.artigos, 0) })}
                            </Sinal>
                            <Sinal icone="fa-right-left" titulo={t('Movimentos de stock nos últimos 30 dias')}>
                                {t(':n mov./30d', { n: vida.movimentos_30d })}
                            </Sinal>
                            <Sinal icone="fa-user-clock" titulo={t('Utilizadores que entraram nos últimos 30 dias')}>
                                {t(':a/:t activo(s)', { a: vida.entraram_30d, t: vida.utilizadores })}
                            </Sinal>
                            <span className={vida.entrou_ha_pouco ? '' : 'font-semibold text-red-600'} title={t('Última vez que alguém desta empresa entrou')}>
                                <i className="fas fa-right-to-bracket mr-1 text-slate-400" aria-hidden="true" />
                                {vida.ultima_entrada ? t('entrou :quando', { quando: vida.ultima_entrada }) : t('nunca entrou')}
                            </span>
                        </div>
                    )}

                    {/* AS ACÇÕES ESTÃO SEMPRE À VISTA: escondidas até ao passar do
                        rato, num tablet não havia como lhes chegar. */}
                    <div className="flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                        <Accao cor="text-orange-700 bg-orange-50 hover:bg-orange-100" icone="fa-users" aoClicar={accoes.pessoas}>{t('Utilizadores')}</Accao>
                        <Accao cor="text-purple-700 bg-purple-50 hover:bg-purple-100" icone="fa-crown" aoClicar={accoes.plano}>{t('Plano')}</Accao>
                        <Accao cor="text-amber-700 bg-amber-50 hover:bg-amber-100" icone="fa-sliders" aoClicar={accoes.medida} titulo={t('Montar um plano só para esta empresa')}>{t('À medida')}</Accao>
                        <Accao cor="text-emerald-700 bg-emerald-50 hover:bg-emerald-100" icone="fa-eye" aoClicar={accoes.ver}>{t('Ver detalhes')}</Accao>
                        <Accao cor="text-blue-700 bg-blue-50 hover:bg-blue-100" icone="fa-pen" aoClicar={accoes.editar}>{t('Editar')}</Accao>
                        <Accao
                            cor="text-slate-700 bg-slate-100 hover:bg-slate-200"
                            icone={aActivar ? 'fa-spinner fa-spin' : 'fa-power-off'}
                            aoClicar={accoes.alternar}
                            desligado={aActivar}
                        >
                            {e.activa ? t('Desactivar') : t('Activar')}
                        </Accao>
                        <Accao cor="text-red-700 bg-red-50 hover:bg-red-100" icone="fa-box-archive" aoClicar={accoes.suspender} titulo={t('Sai da lista e os dados ficam guardados')}>{t('Suspender')}</Accao>
                        <Accao cor="text-red-700 bg-red-50 ring-1 ring-inset ring-red-200 hover:bg-red-100" icone="fa-trash" aoClicar={accoes.apagar} titulo={t('Apagar da base de dados, sem retorno')}>{t('Apagar')}</Accao>
                    </div>
                </div>
            </div>
        </article>
    );
}

function Dado({ icone, cor, rotulo, valor }: { icone: string; cor: string; rotulo: string; valor: React.ReactNode }) {
    return (
        <div className="flex min-w-0 items-start gap-2">
            <span className={cls('grid h-7 w-7 shrink-0 place-items-center rounded-lg text-xs', cor)}>
                <i className={cls('fas', icone)} aria-hidden="true" />
            </span>
            <div className="min-w-0">
                <dt className="text-xs font-medium text-slate-500">{rotulo}</dt>
                <dd className="truncate text-sm text-slate-900">{valor}</dd>
            </div>
        </div>
    );
}

function Sinal({ icone, titulo, children }: { icone: string; titulo: string; children: React.ReactNode }) {
    return (
        <span title={titulo}>
            <i className={cls('fas mr-1 text-slate-400', icone)} aria-hidden="true" />
            <b className="font-semibold text-slate-700">{children}</b>
        </span>
    );
}

function Accao({ cor, icone, aoClicar, titulo, desligado = false, children }: {
    cor: string;
    icone: string;
    aoClicar: () => void;
    titulo?: string;
    desligado?: boolean;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={aoClicar}
            title={titulo}
            disabled={desligado}
            className={cls(
                'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold', RAIO, TRANSICAO, FOCO,
                'hover:-translate-y-0.5 disabled:opacity-50',
                cor,
            )}
        >
            <i className={cls('fas', icone)} aria-hidden="true" />
            {children}
        </button>
    );
}

// Uma função e não uma constante: o `t()` tem de correr depois de o dicionário
// chegar. E as classes escritas por inteiro: o Tailwind não vê `bg-${cor}-50` montado em
// tempo de execução, e era assim no Blade — os cartões acesos nunca tinham cor.
const estados = () => [
    { chave: 'activa', rotulo: t('A facturar'), icone: 'fa-file-invoice-dollar', texto: 'text-emerald-600', aceso: 'border-emerald-500 bg-emerald-50 shadow', suave: 'bg-emerald-100 text-emerald-700' },
    { chave: 'a_usar', rotulo: t('A usar'), icone: 'fa-computer', texto: 'text-blue-600', aceso: 'border-blue-500 bg-blue-50 shadow', suave: 'bg-blue-100 text-blue-700' },
    { chave: 'a_montar', rotulo: t('A montar'), icone: 'fa-screwdriver-wrench', texto: 'text-amber-600', aceso: 'border-amber-500 bg-amber-50 shadow', suave: 'bg-amber-100 text-amber-700' },
    { chave: 'adormecida', rotulo: t('Adormecidas'), icone: 'fa-moon', texto: 'text-orange-600', aceso: 'border-orange-500 bg-orange-50 shadow', suave: 'bg-orange-100 text-orange-700' },
    { chave: 'vazia', rotulo: t('Nunca usaram'), icone: 'fa-ghost', texto: 'text-red-600', aceso: 'border-red-500 bg-red-50 shadow', suave: 'bg-red-100 text-red-700' },
];

