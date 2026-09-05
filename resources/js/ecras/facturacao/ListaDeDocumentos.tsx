import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import {
    documentos,
    type FiltrosDeDocumentos,
    type LinhaDeDocumento,
} from '@/api/documentos';
import { ErroDaApi } from '@/api/cliente';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';

/**
 * UMA LISTA PARA CINCO DOCUMENTOS.
 *
 * Proformas de venda e de compra, orçamentos, facturas de compra e recibos
 * têm todos a mesma forma: número, a outra parte, data, estado e valor. Em
 * Blade são cinco ficheiros e 4.700 linhas que se repetem — corrigir a
 * paginação num deixava os outros quatro por corrigir.
 *
 * O que muda entre eles vem do servidor (`TiposDeDocumento`): o título, o
 * cabeçalho da coluna da outra parte, a lista de estados que ESTA tabela tem
 * mesmo, e se há saldo a mostrar. O ecrã não sabe nada de proformas nem de
 * recibos, e é por isso que serve os cinco.
 */
export default function ListaDeDocumentos({ tipo }: { tipo: string }) {
    const [filtros, porFiltros] = useState<FiltrosDeDocumentos>({ procura: '', page: 1 });

    const opcoes = useQuery({
        queryKey: ['documentos', tipo, 'opcoes'],
        queryFn: () => documentos.opcoes(tipo),
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['documentos', tipo, filtros],
        queryFn: () => documentos.lista(tipo, filtros),
        placeholderData: keepPreviousData,
    });

    if (lista.isError) {
        return <Falhou erro={lista.error} />;
    }

    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const temSaldo = opcoes.data?.tem_saldo ?? false;
    const rota = opcoes.data?.rota ?? '';

    return (
        <div className="space-y-4">
            <Cartao titulo="Filtros">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="block lg:col-span-2">
                        <Rotulo>Procurar</Rotulo>
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))}
                            placeholder={`Número ou ${opcoes.data?.parte ?? 'nome'}`}
                            className={entrada}
                        />
                    </label>

                    <label className="block">
                        <Rotulo>Estado</Rotulo>
                        <select
                            value={filtros.estado ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value, page: 1 }))}
                            className={entrada}
                        >
                            <option value="">Todos</option>
                            {opcoes.data?.estados.map((e) => (
                                <option key={e.valor} value={e.valor}>
                                    {e.rotulo}
                                </option>
                            ))}
                        </select>
                    </label>

                    <div className="grid grid-cols-2 gap-2">
                        <label className="block">
                            <Rotulo>De</Rotulo>
                            <input
                                type="date"
                                value={filtros.de ?? ''}
                                onChange={(e) => porFiltros((f) => ({ ...f, de: e.target.value, page: 1 }))}
                                className={entrada}
                            />
                        </label>
                        <label className="block">
                            <Rotulo>Até</Rotulo>
                            <input
                                type="date"
                                value={filtros.ate ?? ''}
                                onChange={(e) => porFiltros((f) => ({ ...f, ate: e.target.value, page: 1 }))}
                                className={entrada}
                            />
                        </label>
                    </div>
                </div>

                <div className="mt-4 flex items-center justify-between gap-4">
                    <p className="text-sm text-slate-500">
                        {contas ? `${contas.total.toLocaleString('pt-PT')} documento(s)` : 'A contar…'}
                        {lista.isFetching && <span className="ml-2 text-xs">a actualizar…</span>}
                    </p>
                    <Botao icone="fa-eraser" onClick={() => porFiltros({ procura: '', page: 1 })}>
                        Limpar
                    </Botao>
                </div>
            </Cartao>

            {lista.isPending ? (
                <Carregando />
            ) : linhas.length === 0 ? (
                <div className={cls(CARTAO, 'px-6 py-14 text-center')}>
                    <i className="fas fa-file-lines mb-3 text-4xl text-slate-300" aria-hidden="true" />
                    <p className="font-semibold text-slate-700">Nenhum documento com estes filtros</p>
                    <p className="mt-1 text-sm text-slate-500">Alargue as datas ou limpe os filtros.</p>
                </div>
            ) : (
                <Cartao titulo={opcoes.data?.titulo} semPadding>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <th className="px-4 py-3 font-semibold">Número</th>
                                    <th className="px-4 py-3 font-semibold">
                                        {opcoes.data?.parte === 'fornecedor' ? 'Fornecedor' : 'Cliente'}
                                    </th>
                                    <th className="px-4 py-3 font-semibold">Data</th>
                                    <th className="px-4 py-3 font-semibold">Estado</th>
                                    <th className="px-4 py-3 text-right font-semibold">Valor</th>
                                    {temSaldo && (
                                        <th className="px-4 py-3 text-right font-semibold">Falta pagar</th>
                                    )}
                                    <th className="px-4 py-3 text-right font-semibold">Acções</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {linhas.map((d) => (
                                    <Linha key={d.id} d={d} rota={rota} temSaldo={temSaldo} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Cartao>
            )}

            {contas && contas.last_page > 1 && (
                <nav className={cls(CARTAO, 'flex items-center justify-between px-5 py-3')} aria-label="Páginas">
                    <Botao
                        icone="fa-chevron-left"
                        altura="pequeno"
                        disabled={contas.current_page <= 1}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}
                    >
                        Anterior
                    </Botao>
                    <span className="text-sm tabular-nums text-slate-600">
                        Página {contas.current_page} de {contas.last_page}
                    </span>
                    <Botao
                        icone="fa-chevron-right"
                        altura="pequeno"
                        disabled={contas.current_page >= contas.last_page}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}
                    >
                        Seguinte
                    </Botao>
                </nav>
            )}
        </div>
    );
}

function Linha({ d, rota, temSaldo }: { d: LinhaDeDocumento; rota: string; temSaldo: boolean }) {
    return (
        <tr className="transition hover:bg-slate-50">
            <td className="px-4 py-3 font-semibold text-indigo-700">{d.numero}</td>
            <td className="px-4 py-3 text-slate-700">{d.parte}</td>
            <td className="px-4 py-3 tabular-nums text-slate-600">{data(d.data)}</td>
            <td className="px-4 py-3">
                <Etiqueta cor={d.estado_cor}>{d.estado_rotulo}</Etiqueta>
            </td>
            <td className="px-4 py-3 text-right font-bold tabular-nums text-slate-900">{kz(d.valor)}</td>
            {temSaldo && (
                <td className="px-4 py-3 text-right tabular-nums">
                    {(d.saldo ?? 0) > 0.01 ? (
                        <span className="font-semibold text-amber-600">{kz(d.saldo)}</span>
                    ) : (
                        <span className="text-slate-300">—</span>
                    )}
                </td>
            )}
            <td className="px-4 py-3">
                <div className="flex items-center justify-end gap-1">
                    {/* As acções continuam a apontar para as páginas de sempre.
                        Reescrever o gerador de PDF para migrar uma LISTA seria
                        trocar o risco de sítio sem ganhar nada. */}
                    <a
                        href={`${rota}/${d.id}/edit`}
                        title="Abrir"
                        aria-label={`Abrir ${d.numero}`}
                        className={cls('p-2 text-slate-500 transition hover:bg-slate-100', RAIO, FOCO)}
                    >
                        <i className="fas fa-eye" aria-hidden="true" />
                    </a>
                    <a
                        href={`${rota}/${d.id}/pdf`}
                        title="PDF"
                        aria-label={`PDF de ${d.numero}`}
                        className={cls('p-2 text-red-500 transition hover:bg-red-50', RAIO, FOCO)}
                    >
                        <i className="fas fa-file-pdf" aria-hidden="true" />
                    </a>
                </div>
            </td>
        </tr>
    );
}

/* ─── Peças ───────────────────────────────────────────────────────────── */

const entrada =
    'w-full h-10 px-3 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:border-indigo-500';

function Rotulo({ children }: { children: React.ReactNode }) {
    return (
        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">
            {children}
        </span>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    if (daApi?.eSessaoMorta) {
        return (
            <div className={cls('border border-amber-200 bg-amber-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-amber-900">A sessão expirou</h2>
                <p className="mb-4 text-sm text-amber-900">Entre outra vez para continuar.</p>
                <Botao cor="primaria" tom="solida" onClick={() => window.location.reload()}>
                    Voltar a entrar
                </Botao>
            </div>
        );
    }

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">Não foi possível carregar</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? 'Verifique a ligação.'}</p>
        </div>
    );
}
