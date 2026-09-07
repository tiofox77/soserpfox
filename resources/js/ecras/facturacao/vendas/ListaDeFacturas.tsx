import { useState } from 'react';
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    facturacao,
    type FacturaDeVenda,
    type FiltrosDeFacturas,
    type SomasDasFacturas,
} from '@/api/facturacao';
import { ErroDaApi } from '@/api/cliente';
import { etiquetaIntl, t } from '@/i18n';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { RegistarPagamento } from '@/ecras/facturacao/RegistarPagamento';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { PdfDoEcra } from '@/ui/PdfDoEcra';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';

/**
 * A LISTA DE FACTURAS DE VENDA.
 *
 * O primeiro ecrã a sair do Livewire, e foi escolhido de propósito: tem
 * tabela, filtros e paginação — prova o padrão inteiro — e não emite nem
 * assina nada. Se estiver errado, ninguém fica com uma factura inválida.
 *
 * O QUE MUDA PARA QUEM USA: escrever no campo de procura deixa de ser um pedido
 * ao servidor por tecla. A conta faz-se aqui e só se pergunta ao servidor
 * quando a pessoa pára de escrever.
 *
 * O QUE NÃO MUDA: quem pode ver o quê. O `pode_creditar` de cada linha vem
 * decidido do servidor — este ecrã apenas apaga um botão, e um botão apagado
 * nunca foi segurança.
 */

const FILTROS_VAZIOS: FiltrosDeFacturas = {
    procura: '',
    estado: '',
    tipo: '',
    armazem: '',
    autor: '',
    de: '',
    ate: '',
    por_pagina: 15,
    page: 1,
};

export default function ListaDeFacturas({ tipo }: { tipo?: 'FT' | 'FR' }) {
    const cache = useQueryClient();
    // O menu tem uma entrada só para as Faturas-Recibo (`?type=FR`): a lista
    // abre já filtrada, e o filtro continua a poder mudar-se.
    const [filtros, porFiltros] = useState<FiltrosDeFacturas>(tipo ? { ...FILTROS_VAZIOS, tipo } : FILTROS_VAZIOS);
    // A factura que se está a pagar, e o que o servidor disse depois.
    const [aPagar, porAPagar] = useState<FacturaDeVenda | null>(null);
    const [recado, porRecado] = useState('');

    /**
     * Muda-se um filtro, volta-se à primeira página.
     *
     * Ficar na página 7 de uma pesquisa que agora só tem 2 mostrava uma tabela
     * vazia sem explicação — e a pessoa concluía que não havia facturas.
     */
    function mudar<K extends keyof FiltrosDeFacturas>(campo: K, valor: FiltrosDeFacturas[K]): void {
        porFiltros((f) => {
            const novos: FiltrosDeFacturas = { ...f, [campo]: valor };
            novos.page = 1;

            return novos;
        });
    }

    const irParaPagina = (p: number) => porFiltros((f) => ({ ...f, page: p }));

    const opcoes = useQuery({
        queryKey: ['facturas', 'opcoes'],
        queryFn: facturacao.opcoesDasFacturas,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['facturas', filtros],
        queryFn: () => facturacao.facturasDeVenda(filtros),
        // Sem isto a tabela pisca a branco a cada tecla. Com isto, os
        // resultados anteriores ficam à vista enquanto os novos não chegam.
        placeholderData: keepPreviousData,
    });

    if (lista.isError) {
        return <Falhou erro={lista.error} />;
    }

    const facturas = lista.data?.data ?? [];
    const contas = lista.data?.meta;
    const podeVerAutores = (opcoes.data?.autores.length ?? 0) > 0;
    // Duplicar é começar uma factura nova: quem pode criar, pode duplicar.
    const podeDuplicar = opcoes.data?.permissoes.pode_criar ?? false;

    return (
        <div className="space-y-4">
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <Totais contas={contas} somas={contas?.somas} aActualizar={lista.isFetching} />

            {/* Pagar: o modal partilhado, o mesmo caminho do Livewire. */}
            {aPagar && (
                <RegistarPagamento
                    tipo="sale"
                    id={aPagar.id}
                    aoFechar={() => porAPagar(null)}
                    aoRegistar={(m) => { porAPagar(null); porRecado(m); void cache.invalidateQueries({ queryKey: ['facturas'] }); }}
                />
            )}

            <Cartao titulo={t('Filtros')} icone="fa-filter">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Campo etiqueta={t('Procurar')}>
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => mudar('procura', e.target.value)}
                            placeholder={t('Número, série ou cliente')}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Estado')}>
                        <select
                            value={filtros.estado ?? ''}
                            onChange={(e) => mudar('estado', e.target.value)}
                            className={entrada}
                        >
                            <option value="">{t('Todos')}</option>
                            {opcoes.data?.estados.map((e) => (
                                <option key={e.valor} value={e.valor}>
                                    {e.rotulo}
                                </option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Tipo')}>
                        <select
                            value={filtros.tipo ?? ''}
                            onChange={(e) => mudar('tipo', e.target.value)}
                            className={entrada}
                        >
                            <option value="">{t('Todos')}</option>
                            <option value="FT">{t('Factura (FT)')}</option>
                            <option value="FR">{t('Factura-Recibo (FR)')}</option>
                        </select>
                    </Campo>

                    <Campo etiqueta={t('Armazém')}>
                        <select
                            value={filtros.armazem ?? ''}
                            onChange={(e) => mudar('armazem', e.target.value)}
                            className={entrada}
                        >
                            <option value="">{t('Todos')}</option>
                            {opcoes.data?.armazens.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </select>
                    </Campo>

                    <Campo etiqueta={t('De')}>
                        <input
                            type="date"
                            value={filtros.de ?? ''}
                            onChange={(e) => mudar('de', e.target.value)}
                            className={entrada}
                        />
                    </Campo>

                    <Campo etiqueta={t('Até')}>
                        <input
                            type="date"
                            value={filtros.ate ?? ''}
                            onChange={(e) => mudar('ate', e.target.value)}
                            className={entrada}
                        />
                    </Campo>

                    {/* Só aparece a quem pode ver os documentos de todos — para
                        os outros a lista vem vazia do servidor, e um selector
                        vazio só levanta perguntas. */}
                    {podeVerAutores && (
                        <Campo etiqueta={t('Emitida por')}>
                            <select
                                value={filtros.autor ?? ''}
                                onChange={(e) => mudar('autor', e.target.value)}
                                className={entrada}
                            >
                                <option value="">{t('Todos')}</option>
                                {opcoes.data?.autores.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </Campo>
                    )}

                    <Campo etiqueta={t('Por página')}>
                        <select
                            value={filtros.por_pagina ?? 15}
                            onChange={(e) => mudar('por_pagina', Number(e.target.value))}
                            className={entrada}
                        >
                            {[15, 30, 50, 100].map((n) => (
                                <option key={n} value={n}>
                                    {n}
                                </option>
                            ))}
                        </select>
                    </Campo>
                </div>

                <div className="mt-4 flex items-center justify-between gap-4">
                    <p className="text-sm text-slate-500">
                        {contas
                            ? t(':quantos documento(s)', { quantos: contas.total.toLocaleString('pt-PT') })
                            : t('A contar…')}
                        {lista.isFetching && <span className="ml-2 text-xs">{t('a actualizar…')}</span>}
                    </p>
                    <Botao icone="fa-eraser" onClick={() => porFiltros(FILTROS_VAZIOS)}>
                        {t('Limpar')}
                    </Botao>
                </div>
            </Cartao>

            {lista.isPending ? (
                <Carregando />
            ) : facturas.length === 0 ? (
                <SemNada aoLimpar={() => porFiltros(FILTROS_VAZIOS)} />
            ) : (
                <Cartao titulo={t('Facturas de venda')} icone="fa-list" semPadding>
                    <Tabela facturas={facturas} podeDuplicar={podeDuplicar} aoPagar={porAPagar} />
                </Cartao>
            )}

            {contas && contas.last_page > 1 && (
                <Paginacao
                    pagina={contas.current_page}
                    paginas={contas.last_page}
                    aMudar={irParaPagina}
                />
            )}
        </div>
    );
}

/* ─── Os cartões do topo ──────────────────────────────────────────────── */

/**
 * O QUE ESTES CARTÕES SOMAM.
 *
 * O que está FILTRADO, não a página à vista: uma soma da página mudava ao
 * carregar em «Seguinte» e não queria dizer nada. E somam pelo SALDO, nunca
 * pelo nome do estado — quem só vê os documentos que emitiu vê aqui só os
 * seus, porque a conta corre no servidor sobre a mesma consulta da tabela.
 *
 * Os números vêm do `meta.somas`; ver o `SomasDasFacturas`.
 */
function Totais({
    contas,
    somas,
    aActualizar,
}: {
    contas?: { total: number };
    somas?: SomasDasFacturas;
    aActualizar: boolean;
}) {
    return (
        <div className={cls('grid gap-3 sm:grid-cols-2 lg:grid-cols-4', aActualizar && 'opacity-70')}>
            <Total rotulo={t('Documentos')} valor={contas?.total} contagem tom="indigo" icone="fa-file-invoice" />
            <Total rotulo={t('Facturado')} valor={somas?.facturado} tom="verde" icone="fa-money-bill-wave" />
            <Total rotulo={t('Por receber')} valor={somas?.por_receber} tom="ambar" icone="fa-clock" />
            <Total rotulo={t('Vencido')} valor={somas?.vencido} tom="vermelho" icone="fa-triangle-exclamation" />
        </div>
    );
}

function Total({
    rotulo,
    valor,
    contagem = false,
    tom,
    icone,
}: {
    rotulo: string;
    valor?: number;
    /** Uma contagem escreve-se inteira e sem moeda; um valor leva «Kz». */
    contagem?: boolean;
    tom: TomDoCartao;
    icone: string;
}) {
    return (
        <CartaoNumero
            rotulo={rotulo}
            tom={tom}
            icone={icone}
            sufixo={valor !== undefined && !contagem ? 'Kz' : undefined}
            valor={
                /* Enquanto não há resposta escreve-se um traço, não um zero:
                   um zero é uma afirmação, e ainda ninguém contou nada. */
                valor === undefined ? (
                    <span className="text-white/50">—</span>
                ) : contagem ? (
                    valor.toLocaleString(etiquetaIntl())
                ) : (
                    kz(valor)
                )
            }
        />
    );
}

/* ─── Tabela ──────────────────────────────────────────────────────────── */

function Tabela({
    facturas,
    podeDuplicar,
    aoPagar: porAPagar,
}: {
    facturas: FacturaDeVenda[];
    podeDuplicar: boolean;
    aoPagar: (f: FacturaDeVenda) => void;
}) {
    return (
        // A tabela rola dentro da sua caixa. Sem isto, uma linha larga põe a
        // PÁGINA a rolar de lado e o menu foge com ela.
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                {/* O CABEÇALHO DA TABELA de sempre: fundo cinzento claro,
                    maiúsculas pequenas e um ícone por coluna. Os ícones vão
                    todos no mesmo tom — no Blade cada um tinha a sua cor, e
                    oito cores num cabeçalho não ajudam a encontrar nada. */}
                <thead className="bg-slate-50">
                    <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-600">
                        <Cabecalho icone="fa-hashtag">{t('Número')}</Cabecalho>
                        <Cabecalho icone="fa-user">{t('Cliente')}</Cabecalho>
                        <Cabecalho icone="fa-calendar">{t('Data')}</Cabecalho>
                        <Cabecalho icone="fa-calendar-check">{t('Vencimento')}</Cabecalho>
                        <Cabecalho icone="fa-circle-info">{t('Estado')}</Cabecalho>
                        <Cabecalho icone="fa-landmark">AGT</Cabecalho>
                        <Cabecalho icone="fa-money-bill" direita>{t('Total')}</Cabecalho>
                        <Cabecalho icone="fa-gear" direita>{t('Acções')}</Cabecalho>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {facturas.map((f, i) => (
                        <tr
                            key={f.id}
                            className="entra transition-all duration-200 hover:bg-indigo-50/60"
                            style={cascata(i)}
                        >
                            <td className="px-4 py-3">
                                <a
                                    href={`/invoicing/sales/invoices/${f.id}`}
                                    className={cls('font-semibold text-indigo-700 hover:underline', FOCO)}
                                >
                                    {f.numero}
                                </a>
                                {/* Os DOIS números: o interno e o da AGT. */}
                                {f.numero_agt && f.numero_agt !== f.numero && (
                                    <div className="mt-0.5 font-mono text-[11px] text-slate-400">
                                        {f.numero_agt}
                                    </div>
                                )}
                            </td>
                            <td className="px-4 py-3">
                                <div className="font-medium text-slate-800">{f.cliente.nome}</div>
                                {f.cliente.nif && (
                                    <div className="text-xs text-slate-400">{f.cliente.nif}</div>
                                )}
                            </td>
                            <td className="px-4 py-3 tabular-nums text-slate-600">{data(f.data)}</td>
                            <td className="px-4 py-3 tabular-nums text-slate-600">{data(f.vencimento)}</td>
                            <td className="px-4 py-3">
                                <Etiqueta cor={f.estado_cor}>{f.estado_rotulo}</Etiqueta>
                            </td>
                            <td className="px-4 py-3">
                                <Etiqueta
                                    cor={f.agt.comunicada ? 'bom' : 'neutra'}
                                    icone={f.agt.comunicada ? 'fa-circle-check' : 'fa-clock'}
                                >
                                    {f.agt.rotulo}
                                </Etiqueta>
                            </td>
                            <td className="px-4 py-3 text-right">
                                <div className="font-bold tabular-nums text-slate-900">{kz(f.total)}</div>
                                {f.saldo > 0.01 && (
                                    <div className="text-xs tabular-nums text-amber-600">
                                        {t('falta :quanto', { quanto: kz(f.saldo) })}
                                    </div>
                                )}
                            </td>
                            <td className="px-4 py-3">
                                <Accoes factura={f} podeDuplicar={podeDuplicar} aoPagar={() => porAPagar(f)} />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * Os botões de cada linha.
 *
 * Continuam a apontar para as páginas Laravel que já existem — o PDF, o
 * recibo, a nota de crédito. Reescrever o gerador de PDF para migrar uma
 * LISTA seria trocar o risco de sítio sem ganhar nada.
 */
function Accoes({
    factura,
    podeDuplicar,
    aoPagar,
}: {
    factura: FacturaDeVenda;
    podeDuplicar: boolean;
    aoPagar: () => void;
}) {
    return (
        <div className="flex items-center justify-end gap-1">
            {/* Pagar abre o modal partilhado; só com saldo por receber. */}
            {factura.pode_receber && factura.saldo > 0.01 && (
                <button
                    type="button"
                    onClick={aoPagar}
                    title={t('Pagar')}
                    aria-label={t('Pagar :numero', { numero: factura.numero })}
                    className={cls(
                        'p-2 text-emerald-600 transition-all duration-200 hover:scale-110 hover:bg-emerald-50 active:scale-100',
                        RAIO,
                        FOCO,
                    )}
                >
                    <i className="fas fa-money-bill-wave" aria-hidden="true" />
                </button>
            )}
            <Accao href={`/invoicing/sales/invoices/${factura.id}`} icone="fa-eye" titulo={t('Ver')} />

            {/* TRÊS CAMINHOS PARA O MESMO DOCUMENTO, e nenhum substitui outro.
                A PRÉ-VISUALIZAÇÃO é a origem de tudo: abre num separador e é
                de lá que se imprime e se confere antes de mandar. */}
            <Accao
                href={`/invoicing/sales/invoices/${factura.id}/preview`}
                icone="fa-print"
                titulo={t('Pré-visualizar / Imprimir')}
                novoSeparador
            />
            <Accao
                href={`/invoicing/sales/invoices/${factura.id}/pdf`}
                icone="fa-file-pdf"
                titulo={t('PDF')}
            />

            {/* E o PDF DO ECRÃ: a própria pré-visualização, fotografada. É
                isso que garante que o papel não diverge do que se vê — o do
                servidor sai do DomPDF e tem texto para copiar e pesquisar. */}
            <PdfDoEcra
                url={`/invoicing/sales/invoices/${factura.id}/preview`}
                titulo={t('Descarregar :numero em PDF', { numero: factura.numero })}
            />

            {/* DUPLICAR: aproveita o trabalho, não a identidade.
                Vale para QUALQUER factura, incluindo as já emitidas — copiar o
                conteúdo de um documento fiscal para um novo não lhe toca. O que
                nunca se copia (número, série, hash, ATCUD, datas, estado) fica
                do lado do servidor, no `DuplicaDocumento`. */}
            {podeDuplicar && (
                <Accao
                    href={`/invoicing/sales/invoices/create?duplicar=${factura.id}`}
                    icone="fa-copy"
                    titulo={t('Duplicar para novo documento')}
                    cor="text-teal-600 hover:bg-teal-50"
                />
            )}

            {factura.pode_receber && (
                <Accao
                    href={`/invoicing/receipts/create?invoice=${factura.id}`}
                    icone="fa-receipt"
                    titulo={t('Receber')}
                    cor="text-emerald-600 hover:bg-emerald-50"
                />
            )}

            {/* Uma factura já inteiramente anulada não se credita outra vez. O
                botão fica apagado e diz porquê — desaparecer levava quem o
                procura a pensar que o sistema o perdeu. */}
            {factura.pode_creditar ? (
                <Accao
                    href={`/invoicing/credit-notes/create?invoice=${factura.id}`}
                    icone="fa-file-circle-minus"
                    titulo={t('Nota de crédito')}
                    cor="text-amber-600 hover:bg-amber-50"
                />
            ) : (
                <span
                    title={t('Já totalmente creditada — não há nada por anular')}
                    aria-disabled="true"
                    className={cls('cursor-not-allowed p-2 text-slate-300', RAIO)}
                >
                    <i className="fas fa-file-circle-minus" aria-hidden="true" />
                </span>
            )}
        </div>
    );
}

function Accao({
    href,
    icone,
    titulo,
    cor = 'text-slate-500 hover:bg-slate-100',
    novoSeparador = false,
}: {
    href: string;
    icone: string;
    titulo: string;
    cor?: string;
    /** A pré-visualização abre à parte: quem imprime não perde a lista. */
    novoSeparador?: boolean;
}) {
    return (
        <a
            href={href}
            title={titulo}
            aria-label={titulo}
            {...(novoSeparador ? { target: '_blank', rel: 'noopener' } : {})}
            // Levanta ao passar e afunda ao carregar: é o que dizia, no ecrã de
            // sempre, que um ícone de 34px é mesmo um botão.
            className={cls(
                'inline-block p-2 transition-all duration-200 hover:scale-110 active:scale-100',
                RAIO,
                cor,
                FOCO,
            )}
        >
            <i className={`fas ${icone}`} aria-hidden="true" />
        </a>
    );
}

/* ─── Peças pequenas ──────────────────────────────────────────────────── */

const entrada =
    'w-full h-10 px-3 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:border-indigo-500';

function Campo({ etiqueta, children }: { etiqueta: string; children: React.ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                {etiqueta}
            </span>
            {children}
        </label>
    );
}

function Paginacao({
    pagina,
    paginas,
    aMudar,
}: {
    pagina: number;
    paginas: number;
    aMudar: (p: number) => void;
}) {
    return (
        <nav className={cls(CARTAO, 'flex items-center justify-between px-5 py-3')} aria-label={t('Páginas')}>
            <Botao
                icone="fa-chevron-left"
                disabled={pagina <= 1}
                onClick={() => aMudar(pagina - 1)}
                altura="pequeno"
            >
                {t('Anterior')}
            </Botao>
            <span className="text-sm tabular-nums text-slate-600">
                {t('Página :pagina de :paginas', { pagina, paginas })}
            </span>
            <Botao
                icone="fa-chevron-right"
                disabled={pagina >= paginas}
                onClick={() => aMudar(pagina + 1)}
                altura="pequeno"
            >
                {t('Seguinte')}
            </Botao>
        </nav>
    );
}

/**
 * A ENTRADA EM CASCATA das linhas.
 *
 * O `--i` é o atraso da linha; a animação `entra` está no layout, com a guarda
 * de `prefers-reduced-motion`. O índice tem tecto: com 100 linhas por página,
 * 22ms cada dava dois segundos a ver a tabela a montar-se, que é o contrário
 * do que a cascata serve.
 */
function cascata(i: number): React.CSSProperties {
    return { '--i': Math.min(i, 12) } as React.CSSProperties;
}

/** Uma coluna do cabeçalho: o rótulo com o seu ícone, sempre no mesmo tom. */
function Cabecalho({
    icone,
    direita = false,
    children,
}: {
    icone: string;
    direita?: boolean;
    children: React.ReactNode;
}) {
    return (
        <th className={cls('px-4 py-3 font-bold', direita && 'text-right')}>
            <i className={`fas ${icone} mr-1.5 text-slate-400`} aria-hidden="true" />
            {children}
        </th>
    );
}

/**
 * O ESTADO VAZIO COM DESENHO — o círculo de 80px com o ícone lá dentro, como
 * o ecrã em Blade tinha. Uma linha de texto solta no meio de uma caixa branca
 * lê-se como um erro de carregamento; isto lê-se como uma resposta.
 *
 * O TEXTO É O CORRECTO, e não o do Blade. Lá dizia «Nenhuma proforma
 * encontrada / Crie a sua primeira proforma de compra» — numa lista de
 * FACTURAS DE VENDA, copiado de outro ecrã. E fala de FILTROS porque é o que
 * quase sempre está a acontecer: o período começa no mês corrente.
 */
function SemNada({ aoLimpar }: { aoLimpar: () => void }) {
    return (
        <div className={cls(CARTAO, 'animate-fade-in px-6 py-16 text-center')}>
            <div className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                <i className="fas fa-file-invoice text-4xl text-slate-300" aria-hidden="true" />
            </div>
            <p className="text-lg font-bold text-slate-800">{t('Nenhuma factura com estes filtros')}</p>
            <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">
                {t('Alargue as datas ou limpe os filtros para ver mais.')}
            </p>
            <div className="mt-5 flex justify-center">
                <Botao icone="fa-eraser" onClick={aoLimpar}>
                    {t('Limpar filtros')}
                </Botao>
            </div>
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    // A sessão morrer não é falta de rede, e dizer «sem ligação» a quem só
    // precisa de voltar a entrar manda a pessoa reiniciar o router.
    if (daApi?.eSessaoMorta) {
        return (
            <Aviso cor="amber" titulo={t('A sessão expirou')}>
                <p className="mb-4 text-sm">{t('Entre outra vez para continuar. Nada se perdeu.')}</p>
                <Botao cor="primaria" tom="solida" onClick={() => window.location.reload()}>
                    {t('Voltar a entrar')}
                </Botao>
            </Aviso>
        );
    }

    if (daApi?.eDePermissao) {
        return (
            <Aviso cor="slate" titulo={t('Sem acesso a este ecrã')}>
                <p className="text-sm">{daApi.message}</p>
            </Aviso>
        );
    }

    return (
        <Aviso cor="red" titulo={t('Não foi possível carregar as facturas')}>
            <p className="mb-4 text-sm">{daApi?.message ?? t('Verifique a ligação e tente outra vez.')}</p>
            <Botao cor="perigo" tom="solida" onClick={() => window.location.reload()}>
                {t('Tentar outra vez')}
            </Botao>
        </Aviso>
    );
}

function Aviso({
    cor,
    titulo,
    children,
}: {
    cor: 'red' | 'amber' | 'slate';
    titulo: string;
    children: React.ReactNode;
}) {
    const tintas = {
        red: 'border-red-200 bg-red-50 text-red-900',
        amber: 'border-amber-200 bg-amber-50 text-amber-900',
        slate: 'border-slate-200 bg-slate-50 text-slate-800',
    } as const;

    return (
        <div className={cls('border p-6', RAIO, tintas[cor])} role="alert">
            <h2 className="mb-2 text-lg font-bold">{titulo}</h2>
            {children}
        </div>
    );
}
