import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    pedidos,
    type CampoDoPedido,
    type FichaDePedido,
    type FiltrosDePedidos,
    type LinhaDePedido,
    type OpcoesDoPedido,
} from '@/api/pedidos';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PorPagina } from '@/ui/FiltrosComuns';
import { ACCAO_DA_FAIXA, Faixa, type TomDaFaixa } from '@/ecras/facturacao/faixa';
import { CalendarioDePedidos } from './CalendarioDePedidos';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';

/**
 * UM ECRÃ PARA SEIS PEDIDOS.
 *
 * Férias, licenças, horas extras, turno nocturno, adiantamentos e descontos
 * salariais têm todos a mesma forma: alguém pede, alguém APROVA ou RECUSA com
 * um motivo, e depois paga-se. Em Livewire eram seis componentes com 2.210
 * linhas e as mesmas quatro operações escritas seis vezes.
 *
 * O que muda entre eles vem do servidor (`PedidosDeRh`): o título, a cor, os
 * campos do formulário, as colunas da tabela, os estados que ESTA tabela tem
 * mesmo, e que acções aceita. Este ecrã não sabe o que é um pedido de férias.
 *
 * A DECISÃO ESTÁ SEMPRE À VISTA: quem aprovou, quando, e — quando foi
 * recusado — porquê. Um pedido decidido sem se saber por quem não serve de
 * prova a ninguém.
 */

const TOM_DO_CARTAO: Record<string, TomDoCartao> = {
    primaria: 'indigo', neutra: 'cinza', bom: 'verde', aviso: 'ambar',
    perigo: 'vermelho', laranja: 'laranja', roxo: 'roxo', ciano: 'azul', rosa: 'roxo',
};

const SINAL_DO_ESTADO: Record<string, string> = {
    bom: 'fa-circle-check', primaria: 'fa-paper-plane', aviso: 'fa-clock',
    perigo: 'fa-circle-xmark', neutra: 'fa-circle-minus',
};

type Valores = Record<string, unknown>;

export default function Pedidos({ tipo }: { tipo: string }) {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDePedidos>({ procura: '', page: 1 });
    const [formulario, porFormulario] = useState<Valores | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aVer, porAVer] = useState<LinhaDePedido | null>(null);
    const [aDecidir, porADecidir] = useState<{ linha: LinhaDePedido; accao: 'aprovar' | 'rejeitar' | 'pagar' | 'cancelar' } | null>(null);
    const [aApagar, porAApagar] = useState<LinhaDePedido | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');
    /* LISTA ou CALENDÁRIO — nos pedidos que ocupam dias, ver o mês responde a
       «quem já está fora nessa semana?», que é a pergunta que se faz antes de
       aprovar mais um. */
    const [vista, porVista] = useState<'lista' | 'calendario'>('lista');

    const opcoes = useQuery({ queryKey: ['rh', 'pedidos', tipo, 'opcoes'], queryFn: () => pedidos.opcoes(tipo), staleTime: 5 * 60_000 });
    const lista = useQuery({
        queryKey: ['rh', 'pedidos', tipo, filtros],
        queryFn: () => pedidos.lista(tipo, filtros),
        placeholderData: keepPreviousData,
    });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['rh', 'pedidos', tipo] });

    const gravar = useMutation({
        mutationFn: (v: Valores) => pedidos.guardar(tipo, v),
        onSuccess: (r) => { invalidar(); porFormulario(null); porErros({}); porRecado(r.message); },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const apagar = useMutation({
        mutationFn: (l: LinhaDePedido) => pedidos.eliminar(tipo, l.id),
        onSuccess: (r) => { invalidar(); porAApagar(null); porRecado(r.message); },
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) return <Falhou erro={opcoes.error ?? lista.error} />;

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const resumo = lista.data?.resumo;

    const vazio = (): Valores => Object.fromEntries(o.campos.map((c) => [
        c.chave,
        c.omissao ?? (c.tipo === 'booleano' ? false : c.tipo === 'data' ? new Date().toISOString().slice(0, 10) : ''),
    ]));

    return (
        <div className="space-y-4">
            <Faixa
                titulo={o.titulo}
                subtitulo={o.descricao}
                icone={o.icone}
                cor={(o.cor as TomDaFaixa) ?? 'primaria'}
                accoes={
                    <>
                        {/* LISTA ou CALENDÁRIO — só nos pedidos que ocupam dias. */}
                        {o.calendario && (
                            <button
                                type="button"
                                onClick={() => porVista((v) => (v === 'lista' ? 'calendario' : 'lista'))}
                                className={cls(ACCAO_DA_FAIXA, 'group')}
                            >
                                <i
                                    className={cls('fas transition-transform duration-300 group-hover:scale-110',
                                        vista === 'lista' ? 'fa-calendar-days' : 'fa-list')}
                                    aria-hidden="true"
                                />
                                {vista === 'lista' ? t('Calendário') : t('Lista')}
                            </button>
                        )}

                        {o.permissoes.pode_criar && (
                            <button type="button" onClick={() => { porErros({}); porFormulario(vazio()); }} className={cls(ACCAO_DA_FAIXA, 'group')}>
                                <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />
                                {o.novo}
                            </button>
                        )}
                    </>
                }
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <AvisoDeErro erro={apagar.error} />

            {/* OS CARTÕES: um por estado que ESTA tabela tem, mais o total e o
                dinheiro. As contagens são da empresa inteira — «3 pendentes»
                tem de querer dizer três pendentes, e é o número que diz a quem
                aprova que tem trabalho à espera. */}
            <div className={cls('grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5', lista.isFetching && 'opacity-70')}>
                <CartaoNumero
                    aspecto="claro"
                    rotulo={o.titulo}
                    tom={TOM_DO_CARTAO[o.cor] ?? 'indigo'}
                    icone={o.icone}
                    nota={t('nesta empresa')}
                    valor={resumo === undefined ? '—' : resumo.total.toLocaleString(etiquetaIntl())}
                />

                {(resumo?.por_estado ?? []).slice(0, 3).map((e) => (
                    <CartaoNumero
                        key={e.valor}
                        aspecto="claro"
                        rotulo={e.rotulo}
                        tom={TOM_DO_CARTAO[e.cor] ?? 'cinza'}
                        icone={SINAL_DO_ESTADO[e.cor] ?? 'fa-circle-info'}
                        valor={e.quantos.toLocaleString(etiquetaIntl())}
                    />
                ))}

                <CartaoNumero
                    aspecto="claro"
                    rotulo={o.valor.rotulo}
                    tom="verde"
                    icone="fa-money-bill-wave"
                    sufixo="Kz"
                    nota={t('somado de todos')}
                    valor={resumo === undefined ? '—' : kz(resumo.valor)}
                />
            </div>

            {vista === 'calendario' && o.calendario ? (
                <CalendarioDePedidos
                    tipo={tipo}
                    o={o}
                    aoAbrir={(id) => {
                        const l = linhas.find((x) => x.id === id);

                        // A linha pode não estar na página à frente: pede-se a
                        // ficha na mesma, com o mínimo que o modal precisa.
                        porAVer(l ?? { id, numero: '', funcionario: '', estado: '', valor: 0, criado_em: null });
                    }}
                />
            ) : (
            <>
            <Cartao titulo={t('Filtros')} icone="fa-filter">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Procurar')}</span>
                        <input type="search" value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))}
                            placeholder={t('Número ou motivo')} className={entrada} />
                    </label>

                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Estado')}</span>
                        <select value={filtros.estado ?? ''} onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">{t('Todos')}</option>
                            {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                        </select>
                    </label>

                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Funcionário')}</span>
                        <select value={filtros.funcionario ?? ''} onChange={(e) => porFiltros((f) => ({ ...f, funcionario: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">{t('Todos')}</option>
                            {o.funcionarios.map((f) => <option key={f.valor} value={f.valor}>{f.rotulo}</option>)}
                        </select>
                    </label>

                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Ano')}</span>
                        <input type="number" min="2000" max="2100" value={filtros.ano ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, ano: e.target.value ? Number(e.target.value) : undefined, page: 1 }))}
                            placeholder={t('Todos')} className={cls(entrada, 'tabular-nums')} />
                    </label>
                </div>

                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-500">
                        {contas ? t(':quantos pedido(s)', { quantos: contas.total.toLocaleString(etiquetaIntl()) }) : t('A contar…')}
                        {lista.isFetching && <span className="ml-2 text-xs">{t('a actualizar…')}</span>}
                    </p>
                    <div className="flex flex-wrap items-center gap-3">
                        <PorPagina valor={filtros.por_pagina} aoMudar={(n) => porFiltros((f) => ({ ...f, por_pagina: n, page: 1 }))} />
                        <Botao altura="pequeno" icone="fa-eraser" onClick={() => porFiltros({ procura: '', page: 1 })}>{t('Limpar')}</Botao>
                    </div>
                </div>
            </Cartao>

            {lista.isPending ? (
                <Carregando />
            ) : linhas.length === 0 ? (
                <div className={cls(CARTAO, 'animate-fade-in px-6 py-16 text-center')}>
                    <div className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                        <i className={cls('fas', o.icone, 'text-4xl text-slate-300')} aria-hidden="true" />
                    </div>
                    <p className="text-lg font-bold text-slate-800">{t('Nada com estes filtros')}</p>
                    <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">{t('Limpe os filtros, ou registe o primeiro.')}</p>
                </div>
            ) : (
                <Cartao titulo={o.titulo} icone="fa-list" semPadding>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[48rem] text-sm">
                            <thead className="bg-slate-50">
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-600">
                                    <th className="px-4 py-3 font-bold"><i className="fas fa-hashtag mr-1.5 text-slate-400" aria-hidden="true" />{t('Número')}</th>
                                    <th className="px-4 py-3 font-bold"><i className="fas fa-user mr-1.5 text-slate-400" aria-hidden="true" />{t('Funcionário')}</th>
                                    {o.colunas.map((c) => (
                                        <th key={c.chave} className={cls('px-4 py-3 font-bold', c.formato === 'dinheiro' && 'text-right')}>{c.rotulo}</th>
                                    ))}
                                    <th className="px-4 py-3 font-bold"><i className="fas fa-circle-info mr-1.5 text-slate-400" aria-hidden="true" />{t('Estado')}</th>
                                    <th className="px-4 py-3 text-right font-bold">{o.valor.rotulo}</th>
                                    <th className="px-4 py-3 text-right font-bold"><i className="fas fa-gear mr-1.5 text-slate-400" aria-hidden="true" />{t('Acções')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {linhas.map((l, i) => (
                                    <Linha
                                        key={l.id}
                                        i={i}
                                        l={l}
                                        o={o}
                                        aTrabalhar={apagar.isPending}
                                        aoVer={() => porAVer(l)}
                                        aoDecidir={(accao) => porADecidir({ linha: l, accao })}
                                        aoApagar={() => porAApagar(l)}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Cartao>
            )}

            {contas && contas.last_page > 1 && (
                <nav className={cls(CARTAO, 'flex items-center justify-between px-5 py-3')} aria-label={t('Páginas')}>
                    <Botao icone="fa-chevron-left" altura="pequeno" disabled={contas.current_page <= 1}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}>{t('Anterior')}</Botao>
                    <span className="text-sm tabular-nums text-slate-600">
                        {t('Página :pagina de :paginas', { pagina: contas.current_page, paginas: contas.last_page })}
                    </span>
                    <Botao icone="fa-chevron-right" altura="pequeno" disabled={contas.current_page >= contas.last_page}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}>{t('Seguinte')}</Botao>
                </nav>
            )}
            </>
            )}

            {formulario && (
                <Formulario
                    o={o}
                    valores={formulario}
                    erros={erros}
                    aGravar={gravar.isPending}
                    erroGeral={gravar.error}
                    aoMudar={porFormulario}
                    aoFechar={() => { porFormulario(null); porErros({}); }}
                    aoGravar={() => gravar.mutate(formulario)}
                />
            )}

            <Ficha tipo={tipo} o={o} linha={aVer} aoFechar={() => porAVer(null)} />

            {aDecidir && (
                <Decisao
                    tipo={tipo}
                    o={o}
                    linha={aDecidir.linha}
                    accao={aDecidir.accao}
                    aoFechar={() => porADecidir(null)}
                    aoFeito={(m) => { porADecidir(null); porRecado(m); invalidar(); }}
                />
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar :pedido', { pedido: o.singular.toLowerCase() })}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending}
                            onClick={() => aApagar && apagar.mutate(aApagar)}>{t('Eliminar')}</Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">{t('Vai eliminar :numero. Não há volta.', { numero: aApagar?.numero ?? '' })}</p>
                {/* DIZ-SE A REGRA: só o que ainda não foi decidido se elimina. */}
                <p className="mt-2 text-sm text-slate-500">{t('Só um pedido pendente se elimina — um já decidido anula-se, para o histórico não ficar com um buraco.')}</p>
            </Modal>
        </div>
    );
}

/* ─── A linha ───────────────────────────────────────────────────────── */

function Linha({ i, l, o, aTrabalhar, aoVer, aoDecidir, aoApagar }: {
    i: number;
    l: LinhaDePedido;
    o: OpcoesDoPedido;
    aTrabalhar: boolean;
    aoVer: () => void;
    aoDecidir: (accao: 'aprovar' | 'rejeitar' | 'pagar' | 'cancelar') => void;
    aoApagar: () => void;
}) {
    const estado = o.estados.find((e) => e.valor === l.estado);
    const pendente = l.estado === 'pending';
    const aprovado = l.estado === 'approved';

    return (
        <tr className="entra transition-all duration-200 hover:bg-indigo-50/60" style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
            <td className="px-4 py-3 font-semibold text-indigo-700">{l.numero}</td>
            <td className="px-4 py-3 text-slate-700">{l.funcionario}</td>

            {o.colunas.map((c) => (
                <td key={c.chave} className={cls('px-4 py-3', c.formato === 'dinheiro' ? 'text-right tabular-nums' : 'text-slate-600', c.formato === 'numero' && 'tabular-nums')}>
                    <Celula c={c} l={l} />
                </td>
            ))}

            <td className="px-4 py-3">
                <Etiqueta cor={estado?.cor ?? 'neutra'} icone={SINAL_DO_ESTADO[estado?.cor ?? 'neutra']}>
                    {estado?.rotulo ?? l.estado}
                </Etiqueta>
            </td>

            <td className="px-4 py-3 text-right font-bold tabular-nums text-slate-900">
                {l.valor > 0 ? kz(l.valor) : <span className="font-normal text-slate-300">—</span>}
            </td>

            <td className="px-4 py-3">
                <div className="flex items-center justify-end gap-1">
                    <button type="button" onClick={aoVer} title={t('Ver detalhes')} aria-label={t('Ver :numero', { numero: l.numero })}
                        className={cls('p-2 text-indigo-600 transition-all duration-200 hover:scale-110 hover:bg-indigo-50 active:scale-100', RAIO, FOCO)}>
                        <i className="fas fa-eye" aria-hidden="true" />
                    </button>

                    {o.pdf && (
                        <a href={o.pdf.replace(':id', String(l.id))} target="_blank" rel="noreferrer" title={t('PDF')}
                            aria-label={t('PDF de :numero', { numero: l.numero })}
                            className={cls('p-2 text-red-500 transition-all duration-200 hover:scale-110 hover:bg-red-50 active:scale-100', RAIO, FOCO)}>
                            <i className="fas fa-file-pdf" aria-hidden="true" />
                        </a>
                    )}

                    {/* APROVAR E RECUSAR só num pedido PENDENTE: decidir duas
                        vezes reescrevia a data e apagava quem tinha decidido
                        antes. */}
                    {o.permissoes.pode_aprovar && pendente && o.accoes.aprovar && (
                        <button type="button" onClick={() => aoDecidir('aprovar')} disabled={aTrabalhar} title={t('Aprovar')}
                            aria-label={t('Aprovar :numero', { numero: l.numero })}
                            className={cls('p-2 text-emerald-600 transition-all duration-200 hover:scale-110 hover:bg-emerald-50 active:scale-100 disabled:opacity-40', RAIO, FOCO)}>
                            <i className="fas fa-check" aria-hidden="true" />
                        </button>
                    )}

                    {o.permissoes.pode_aprovar && pendente && o.accoes.rejeitar && (
                        <button type="button" onClick={() => aoDecidir('rejeitar')} disabled={aTrabalhar} title={t('Recusar')}
                            aria-label={t('Recusar :numero', { numero: l.numero })}
                            className={cls('p-2 text-amber-600 transition-all duration-200 hover:scale-110 hover:bg-amber-50 active:scale-100 disabled:opacity-40', RAIO, FOCO)}>
                            <i className="fas fa-xmark" aria-hidden="true" />
                        </button>
                    )}

                    {/* PAGAR só depois de aprovado: pagar o que ninguém
                        autorizou é dinheiro a sair sem decisão por trás. */}
                    {o.permissoes.pode_aprovar && aprovado && o.accoes.pagar && (
                        <button type="button" onClick={() => aoDecidir('pagar')} disabled={aTrabalhar} title={t('Dar por pago')}
                            aria-label={t('Dar :numero por pago', { numero: l.numero })}
                            className={cls('p-2 text-sky-600 transition-all duration-200 hover:scale-110 hover:bg-sky-50 active:scale-100 disabled:opacity-40', RAIO, FOCO)}>
                            <i className="fas fa-money-bill-wave" aria-hidden="true" />
                        </button>
                    )}

                    {o.permissoes.pode_aprovar && (pendente || aprovado) && o.accoes.cancelar && (
                        <button type="button" onClick={() => aoDecidir('cancelar')} disabled={aTrabalhar} title={t('Anular')}
                            aria-label={t('Anular :numero', { numero: l.numero })}
                            className={cls('p-2 text-slate-400 transition-all duration-200 hover:scale-110 hover:text-slate-700 active:scale-100 disabled:opacity-40', RAIO, FOCO)}>
                            <i className="fas fa-ban" aria-hidden="true" />
                        </button>
                    )}

                    {o.permissoes.pode_apagar && pendente && o.accoes.apagar && (
                        <button type="button" onClick={aoApagar} disabled={aTrabalhar} title={t('Eliminar')}
                            aria-label={t('Eliminar :numero', { numero: l.numero })}
                            className={cls('p-2 text-red-500 transition-all duration-200 hover:scale-110 hover:bg-red-50 active:scale-100 disabled:opacity-40', RAIO, FOCO)}>
                            <i className="fas fa-trash" aria-hidden="true" />
                        </button>
                    )}
                </div>
            </td>
        </tr>
    );
}

function Celula({ c, l }: { c: OpcoesDoPedido['colunas'][number]; l: LinhaDePedido }) {
    const v = l[c.chave];

    switch (c.formato) {
        case 'data':
            return v ? <span className="tabular-nums">{data(String(v))}</span> : <span className="text-slate-300">—</span>;
        case 'dinheiro':
            return <span className="font-semibold">{kz(Number(v ?? 0))}</span>;
        case 'numero':
            return <>{v === null || v === undefined ? '—' : String(v)}</>;
        case 'escolha':
            return <>{l.rotulos?.[c.chave] ?? (v === null || v === undefined ? '—' : String(v))}</>;
        default:
            return <>{v === null || v === undefined ? '—' : String(v)}</>;
    }
}

/* ─── O formulário ──────────────────────────────────────────────────── */

function Formulario({ o, valores, erros, aGravar, erroGeral, aoMudar, aoFechar, aoGravar }: {
    o: OpcoesDoPedido;
    valores: Valores;
    erros: Record<string, string[]>;
    aGravar: boolean;
    erroGeral: unknown;
    aoMudar: (v: Valores) => void;
    aoFechar: () => void;
    aoGravar: () => void;
}) {
    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={o.novo}
            subtitulo={o.descricao}
            icone={o.icone}
            cor={(o.cor as 'primaria') ?? 'primaria'}
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={aGravar} onClick={aoGravar}>{t('Registar')}</Botao>
                </>
            }
        >
            <AvisoDeErro erro={erroGeral} />

            {/* A RECUSA DO SERVIÇO — «não tem dias de férias suficientes»,
                «passa o tecto do adiantamento» — é uma regra de negócio e não
                um erro de campo. Vem em `regra` e mostra-se inteira. */}
            {erros.regra?.[0] && (
                <div role="alert" className={cls('mb-4 border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900', RAIO)}>
                    <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                    {erros.regra[0]}
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                {o.campos.map((c) => (
                    <CampoDoEsquema
                        key={c.chave}
                        c={c}
                        o={o}
                        valor={valores[c.chave]}
                        erro={erros[c.chave]}
                        aoMudar={(v) => aoMudar({ ...valores, [c.chave]: v })}
                    />
                ))}
            </div>
        </Modal>
    );
}

function CampoDoEsquema({ c, o, valor, erro, aoMudar }: {
    c: CampoDoPedido;
    o: OpcoesDoPedido;
    valor: unknown;
    erro?: string[];
    aoMudar: (v: unknown) => void;
}) {
    const texto = valor === null || valor === undefined ? '' : String(valor);
    const largura = c.largura === 'inteira' ? 'sm:col-span-2' : undefined;

    if (c.tipo === 'booleano') {
        return (
            <label className={cls('flex cursor-pointer items-center gap-3 px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50', RAIO, largura)}>
                <input type="checkbox" checked={Boolean(valor)} onChange={(e) => aoMudar(e.target.checked)}
                    className="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                {c.rotulo}
            </label>
        );
    }

    let controlo: React.ReactNode;

    switch (c.tipo) {
        case 'funcionario':
            controlo = (
                <select value={texto} onChange={(e) => aoMudar(e.target.value)} className={entrada}>
                    <option value="">{t('Escolher…')}</option>
                    {o.funcionarios.map((f) => (
                        <option key={f.valor} value={f.valor}>{f.rotulo}{f.nota ? ` · ${f.nota}` : ''}</option>
                    ))}
                </select>
            );
            break;
        case 'escolha':
            controlo = (
                <select value={texto} onChange={(e) => aoMudar(e.target.value)} className={entrada}>
                    {!c.obrigatorio && <option value="">—</option>}
                    {(c.opcoes ?? []).map((op) => <option key={op.valor} value={op.valor}>{op.rotulo}</option>)}
                </select>
            );
            break;
        case 'data':
            controlo = <input type="date" value={texto} onChange={(e) => aoMudar(e.target.value)} className={cls(entrada, 'tabular-nums')} />;
            break;
        case 'hora':
            controlo = (
                <span className="relative block">
                    <i className="fas fa-clock pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true" />
                    <input type="time" value={texto.slice(0, 5)} onChange={(e) => aoMudar(e.target.value)} className={cls(entrada, 'pl-9 tabular-nums')} />
                </span>
            );
            break;
        case 'dinheiro':
            controlo = <input type="number" min="0" step="0.01" value={texto} onChange={(e) => aoMudar(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />;
            break;
        case 'numero':
            controlo = <input type="number" step={c.passo ?? 1} min={c.min} max={c.max} value={texto} onChange={(e) => aoMudar(e.target.value)} className={cls(entrada, 'text-right tabular-nums')} />;
            break;
        case 'textarea':
            controlo = <textarea rows={3} value={texto} onChange={(e) => aoMudar(e.target.value)} className={cls(entrada, 'h-auto py-2')} />;
            break;
        default:
            controlo = <input value={texto} onChange={(e) => aoMudar(e.target.value)} className={entrada} />;
    }

    return (
        <Campo etiqueta={c.rotulo} erro={erro} obrigatorio={c.obrigatorio} ajuda={c.ajuda} className={largura}>
            {controlo}
        </Campo>
    );
}

/* ─── A decisão ─────────────────────────────────────────────────────── */

/**
 * APROVAR, RECUSAR, PAGAR OU ANULAR — num modal só.
 *
 * A RECUSA PEDE MOTIVO, e é obrigatório: uma recusa sem motivo é uma pessoa a
 * perguntar porquê a quem já não se lembra.
 *
 * O ADIANTAMENTO aprova-se por um VALOR, que pode ser menor do que o pedido —
 * é o único pedido cuja aprovação é também uma decisão sobre quanto. Os campos
 * vêm do esquema.
 */
function Decisao({ tipo, o, linha, accao, aoFechar, aoFeito }: {
    tipo: string;
    o: OpcoesDoPedido;
    linha: LinhaDePedido;
    accao: 'aprovar' | 'rejeitar' | 'pagar' | 'cancelar';
    aoFechar: () => void;
    aoFeito: (mensagem: string) => void;
}) {
    const [motivo, porMotivo] = useState('');
    const [extra, porExtra] = useState<Valores>(() =>
        Object.fromEntries((o.ao_aprovar?.campos ?? []).map((c) => [
            c.chave,
            // O valor pedido é o ponto de partida do que se aprova.
            c.chave === 'approved_amount' ? String(linha.requested_amount ?? '') : '',
        ])));
    const [erros, porErros] = useState<Record<string, string[]>>({});

    const correr = useMutation({
        mutationFn: () => {
            if (accao === 'aprovar') return pedidos.aprovar(tipo, linha.id, extra);
            if (accao === 'rejeitar') return pedidos.rejeitar(tipo, linha.id, motivo);
            if (accao === 'pagar') return pedidos.pagar(tipo, linha.id);

            return pedidos.cancelar(tipo, linha.id, motivo || undefined);
        },
        onSuccess: (r) => aoFeito(r.message),
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const desenho = {
        aprovar: { titulo: t('Aprovar :pedido', { pedido: o.singular.toLowerCase() }), icone: 'fa-check', cor: 'bom' as const, botao: t('Aprovar') },
        rejeitar: { titulo: t('Recusar :pedido', { pedido: o.singular.toLowerCase() }), icone: 'fa-xmark', cor: 'aviso' as const, botao: t('Recusar') },
        pagar: { titulo: t('Dar por pago'), icone: 'fa-money-bill-wave', cor: 'primaria' as const, botao: t('Dar por pago') },
        cancelar: { titulo: t('Anular :pedido', { pedido: o.singular.toLowerCase() }), icone: 'fa-ban', cor: 'perigo' as const, botao: t('Anular') },
    }[accao];

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={desenho.titulo}
            subtitulo={`${linha.numero} · ${linha.funcionario}`}
            icone={desenho.icone}
            cor={desenho.cor}
            largura="sm"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor={desenho.cor} tom="solida" icone={desenho.icone} aTrabalhar={correr.isPending} onClick={() => correr.mutate()}>
                        {desenho.botao}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={correr.error} />

            {erros.status?.[0] && (
                <div role="alert" className={cls('mb-3 border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900', RAIO)}>
                    {erros.status[0]}
                </div>
            )}

            <div className="space-y-4">
                {accao === 'aprovar' && o.ao_aprovar && (
                    <div className="grid gap-4">
                        {o.ao_aprovar.campos.map((c) => (
                            <Campo key={c.chave} etiqueta={c.rotulo} erro={erros[c.chave]} obrigatorio={c.obrigatorio}>
                                <input type="number" min="0" step="0.01" value={String(extra[c.chave] ?? '')}
                                    onChange={(e) => porExtra((v) => ({ ...v, [c.chave]: e.target.value }))}
                                    className={cls(entrada, 'text-right tabular-nums')} />
                            </Campo>
                        ))}
                        <p className="text-xs text-slate-500">
                            <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                            {t('Pode aprovar-se por menos do que foi pedido. É deste valor que sai o saldo e a prestação.')}
                        </p>
                    </div>
                )}

                {accao === 'rejeitar' && (
                    <Campo etiqueta={t('Motivo da recusa')} erro={erros.rejection_reason} obrigatorio
                        ajuda={t('Fica escrito no pedido, e é o que a pessoa vai ler.')}>
                        <textarea rows={3} value={motivo} onChange={(e) => porMotivo(e.target.value)}
                            placeholder={t('Porque é que este pedido não pode ser aprovado…')} className={cls(entrada, 'h-auto py-2')} />
                    </Campo>
                )}

                {accao === 'cancelar' && (
                    <Campo etiqueta={t('Motivo da anulação')} erro={erros.cancellation_reason}>
                        <textarea rows={2} value={motivo} onChange={(e) => porMotivo(e.target.value)} className={cls(entrada, 'h-auto py-2')} />
                    </Campo>
                )}

                {accao === 'pagar' && (
                    <p className="text-sm text-slate-700">
                        {t('Vai dar :numero por pago, no valor de :valor Kz.', { numero: linha.numero, valor: kz(linha.valor) })}
                    </p>
                )}
            </div>
        </Modal>
    );
}

/* ─── A ficha ───────────────────────────────────────────────────────── */

function Ficha({ tipo, o, linha, aoFechar }: {
    tipo: string;
    o: OpcoesDoPedido;
    linha: LinhaDePedido | null;
    aoFechar: () => void;
}) {
    const q = useQuery({
        queryKey: ['rh', 'pedidos', tipo, 'ficha', linha?.id],
        queryFn: () => pedidos.ficha(tipo, linha!.id),
        enabled: linha !== null,
    });

    if (!linha) return null;

    const f = q.data?.documento;
    const estado = o.estados.find((e) => e.valor === linha.estado);

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={linha.numero}
            subtitulo={linha.funcionario}
            icone={o.icone}
            cor={(o.cor as 'primaria') ?? 'primaria'}
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Fechar')}</Botao>
                    {o.pdf && (
                        <a href={o.pdf.replace(':id', String(linha.id))} target="_blank" rel="noreferrer"
                            className={cls('inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl', RAIO)}>
                            <i className="fas fa-file-pdf" aria-hidden="true" />
                            {t('PDF')}
                        </a>
                    )}
                </>
            }
        >
            {q.isPending || !f ? (
                <Carregando />
            ) : (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Etiqueta cor={estado?.cor ?? 'neutra'} icone={SINAL_DO_ESTADO[estado?.cor ?? 'neutra']}>
                            {estado?.rotulo ?? linha.estado}
                        </Etiqueta>
                        {f.valor > 0 && (
                            <span className="text-lg font-bold tabular-nums text-slate-900">{kz(f.valor)} Kz</span>
                        )}
                    </div>

                    <section className={cls('border border-slate-200 bg-slate-50 p-4', RAIO)}>
                        <div className="space-y-1">
                            {o.campos.map((c) => {
                                const v = f[c.chave];
                                const escrito = c.tipo === 'escolha'
                                    ? (c.opcoes ?? []).find((op) => op.valor === String(v))?.rotulo ?? String(v ?? '')
                                    : c.tipo === 'funcionario'
                                        ? o.funcionarios.find((x) => x.valor === String(v))?.rotulo ?? ''
                                        : c.tipo === 'data' && v
                                            ? data(String(v))
                                            : c.tipo === 'dinheiro' && v
                                                ? `${kz(Number(v))} Kz`
                                                : c.tipo === 'booleano'
                                                    ? (v ? t('Sim') : t('Não'))
                                                    : v;

                                return (
                                    <div key={c.chave} className="flex items-baseline gap-2 text-sm">
                                        <span className="w-40 flex-none text-slate-500">{c.rotulo}</span>
                                        <span className="min-w-0 break-words font-medium text-slate-800">
                                            {escrito ? String(escrito) : <span className="font-normal text-slate-300">—</span>}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    {/* A DECISÃO — quem, quando, e porquê se foi recusado.
                        É o que faz o pedido servir de prova. */}
                    {(f.decisao.aprovado_em || f.decisao.recusado_em) && (
                        <section className={cls(
                            'border p-4',
                            RAIO,
                            f.decisao.recusado_em ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50',
                        )}>
                            <h4 className="mb-2 text-sm font-bold text-slate-800">
                                <i className={cls('fas mr-2', f.decisao.recusado_em ? 'fa-xmark' : 'fa-check')} aria-hidden="true" />
                                {f.decisao.recusado_em ? t('Recusado') : t('Aprovado')}
                            </h4>
                            <p className="text-sm text-slate-700">
                                {f.decisao.recusado_em
                                    ? t('Por :quem, em :quando.', { quem: f.decisao.recusado_por ?? '—', quando: f.decisao.recusado_em })
                                    : t('Por :quem, em :quando.', { quem: f.decisao.aprovado_por ?? '—', quando: f.decisao.aprovado_em ?? '' })}
                            </p>
                            {f.decisao.motivo_da_recusa && (
                                <p className="mt-2 whitespace-pre-line text-sm text-slate-600">{f.decisao.motivo_da_recusa}</p>
                            )}
                        </section>
                    )}

                    {o.anexo && (
                        <section className={cls('flex items-center justify-between gap-3 border border-slate-200 p-3', RAIO)}>
                            <span className="text-sm font-semibold text-slate-700">
                                <i className="fas fa-paperclip mr-2 text-slate-400" aria-hidden="true" />
                                {o.anexo.rotulo}
                            </span>
                            {f.anexo ? (
                                <a href={f.anexo} target="_blank" rel="noreferrer" className="text-sm font-semibold text-indigo-600 hover:underline">
                                    {t('Ver ficheiro')}
                                </a>
                            ) : (
                                <span className="text-xs text-slate-400">{t('sem ficheiro')}</span>
                            )}
                        </section>
                    )}
                </div>
            )}
        </Modal>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os pedidos')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
