import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    documentos,
    type FiltrosDeDocumentos,
    type LinhaDeDocumento,
} from '@/api/documentos';
import { compra } from '@/api/compra';
import { ErroDaApi } from '@/api/cliente';
import { etiquetaIntl, t } from '@/i18n';
import { RegistarPagamento } from '@/ecras/facturacao/RegistarPagamento';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { PdfDoEcra } from '@/ui/PdfDoEcra';
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
    const cache = useQueryClient();
    const [filtros, porFiltros] = useState<FiltrosDeDocumentos>({ procura: '', page: 1 });
    // A factura de compra que se está a pagar, e o que o servidor disse depois.
    const [aPagar, porAPagar] = useState<LinhaDeDocumento | null>(null);
    const [recado, porRecado] = useState('');
    const [aviso, porAviso] = useState('');

    /*
     * ANULAR e MARCAR COMO PAGA — só nas facturas de compra.
     *
     * Uma factura de compra NÃO se elimina: anula-se, e o stock que tinha
     * entrado é revertido. Não é coisa que se faça por engano, por isso
     * pergunta-se antes. Quem pode e quando pode vem decidido do servidor,
     * linha a linha; aqui só se pergunta e se conta o que ele respondeu.
     */
    const accao = useMutation({
        mutationFn: ({ id, qual }: { id: number; qual: 'anular' | 'pagar' }) =>
            qual === 'anular' ? compra.anular(id) : compra.marcarComoPaga(id),
        onSuccess: (r) => {
            porAviso('');
            porRecado(r.message);
            void cache.invalidateQueries({ queryKey: ['documentos', tipo] });
        },
        onError: (e) => porAviso(e instanceof ErroDaApi ? e.message : t('Não foi possível concluir a operação.')),
    });

    function anular(d: LinhaDeDocumento): void {
        if (!window.confirm(t('Anular a factura de compra :numero? O stock que entrou será revertido.', { numero: d.numero }))) return;
        accao.mutate({ id: d.id, qual: 'anular' });
    }

    function marcarPaga(d: LinhaDeDocumento): void {
        if (!window.confirm(t('Marcar :numero como paga? Não lança recibo nem movimento de caixa.', { numero: d.numero }))) return;
        accao.mutate({ id: d.id, qual: 'pagar' });
    }

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
            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            {aviso && (
                <div role="alert" className={cls('flex items-center justify-between gap-3 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900', RAIO)}>
                    <span><i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />{aviso}</span>
                    <button type="button" onClick={() => porAviso('')} aria-label={t('Fechar')} className={cls('p-1 text-red-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            {/* Pagar uma factura de compra: o modal partilhado, o mesmo caminho do Livewire. */}
            {aPagar && (
                <RegistarPagamento
                    tipo="purchase"
                    id={aPagar.id}
                    aoFechar={() => porAPagar(null)}
                    aoRegistar={(m) => { porAPagar(null); porRecado(m); void cache.invalidateQueries({ queryKey: ['documentos', tipo] }); }}
                />
            )}

            {/* OS CARTÕES DO TOPO, como no ecrã de sempre.
                Ver o `Cartoes` mais abaixo: diz-se de onde vem cada número. */}
            <Cartoes total={contas?.total} linhas={linhas} temSaldo={temSaldo} aActualizar={lista.isFetching} />

            <Cartao titulo={t('Filtros')} icone="fa-filter">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="block lg:col-span-2">
                        <Rotulo>{t('Procurar')}</Rotulo>
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))}
                            placeholder={t('Número ou :parte', { parte: opcoes.data?.parte ?? t('nome') })}
                            className={entrada}
                        />
                    </label>

                    <label className="block">
                        <Rotulo>{t('Estado')}</Rotulo>
                        <select
                            value={filtros.estado ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value, page: 1 }))}
                            className={entrada}
                        >
                            <option value="">{t('Todos')}</option>
                            {opcoes.data?.estados.map((e) => (
                                <option key={e.valor} value={e.valor}>
                                    {e.rotulo}
                                </option>
                            ))}
                        </select>
                    </label>

                    <div className="grid grid-cols-2 gap-2">
                        <label className="block">
                            <Rotulo>{t('De')}</Rotulo>
                            <input
                                type="date"
                                value={filtros.de ?? ''}
                                onChange={(e) => porFiltros((f) => ({ ...f, de: e.target.value, page: 1 }))}
                                className={entrada}
                            />
                        </label>
                        <label className="block">
                            <Rotulo>{t('Até')}</Rotulo>
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
                        {contas
                            ? t(':quantos documento(s)', { quantos: contas.total.toLocaleString('pt-PT') })
                            : t('A contar…')}
                        {lista.isFetching && <span className="ml-2 text-xs">{t('a actualizar…')}</span>}
                    </p>
                    <Botao icone="fa-eraser" onClick={() => porFiltros({ procura: '', page: 1 })}>
                        {t('Limpar')}
                    </Botao>
                </div>
            </Cartao>

            {lista.isPending ? (
                <Carregando />
            ) : linhas.length === 0 ? (
                <EstadoVazio
                    icone="fa-file-lines"
                    titulo={t('Nenhum documento com estes filtros')}
                    frase={t('Alargue as datas ou limpe os filtros.')}
                    accao={
                        <Botao icone="fa-eraser" onClick={() => porFiltros({ procura: '', page: 1 })}>
                            {t('Limpar')}
                        </Botao>
                    }
                />
            ) : (
                <Cartao titulo={opcoes.data?.titulo} icone="fa-list" semPadding>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            {/* O CABEÇALHO DA TABELA de sempre: fundo cinzento
                                claro, maiúsculas pequenas e um ícone por coluna.
                                Os ícones vão todos no mesmo tom — no Blade cada um
                                tinha a sua cor, e sete cores num cabeçalho não
                                ajudam a encontrar nada. */}
                            <thead className="bg-slate-50">
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-600">
                                    <Cabecalho icone="fa-hashtag">{t('Número')}</Cabecalho>
                                    <Cabecalho icone="fa-user">
                                        {opcoes.data?.parte === 'fornecedor' ? t('Fornecedor') : t('Cliente')}
                                    </Cabecalho>
                                    <Cabecalho icone="fa-calendar">{t('Data')}</Cabecalho>
                                    <Cabecalho icone="fa-circle-info">{t('Estado')}</Cabecalho>
                                    <Cabecalho icone="fa-landmark">{t('Portal AGT')}</Cabecalho>
                                    <Cabecalho icone="fa-money-bill" direita>{t('Valor')}</Cabecalho>
                                    {temSaldo && (
                                        <Cabecalho icone="fa-clock" direita>{t('Falta pagar')}</Cabecalho>
                                    )}
                                    <Cabecalho icone="fa-gear" direita>{t('Acções')}</Cabecalho>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {linhas.map((d, i) => (
                                    <Linha
                                        key={d.id}
                                        i={i}
                                        d={d}
                                        rota={rota}
                                        temSaldo={temSaldo}
                                        podeDuplicar={opcoes.data?.pode_duplicar ?? false}
                                        aTrabalhar={accao.isPending}
                                        aoPagar={opcoes.data?.pode_pagar ? () => porAPagar(d) : undefined}
                                        aoAnular={() => anular(d)}
                                        aoMarcarPaga={() => marcarPaga(d)}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Cartao>
            )}

            {contas && contas.last_page > 1 && (
                <nav className={cls(CARTAO, 'flex items-center justify-between px-5 py-3')} aria-label={t('Páginas')}>
                    <Botao
                        icone="fa-chevron-left"
                        altura="pequeno"
                        disabled={contas.current_page <= 1}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page - 1 }))}
                    >
                        {t('Anterior')}
                    </Botao>
                    <span className="text-sm tabular-nums text-slate-600">
                        {t('Página :pagina de :paginas', { pagina: contas.current_page, paginas: contas.last_page })}
                    </span>
                    <Botao
                        icone="fa-chevron-right"
                        altura="pequeno"
                        disabled={contas.current_page >= contas.last_page}
                        onClick={() => porFiltros((f) => ({ ...f, page: contas.current_page + 1 }))}
                    >
                        {t('Seguinte')}
                    </Botao>
                </nav>
            )}
        </div>
    );
}

/** O ícone acompanha a cor do selo — nunca só a cor. */
const SINAL_AGT: Record<string, string> = {
    bom: 'fa-circle-check',
    primaria: 'fa-cloud-arrow-up',
    perigo: 'fa-triangle-exclamation',
    aviso: 'fa-clock',
    neutra: 'fa-minus-circle',
};

function Linha({
    i,
    d,
    rota,
    temSaldo,
    podeDuplicar,
    aTrabalhar,
    aoPagar,
    aoAnular,
    aoMarcarPaga,
}: {
    /** A ordem na lista, só para a entrada em cascata. */
    i: number;
    d: LinhaDeDocumento;
    rota: string;
    temSaldo: boolean;
    podeDuplicar: boolean;
    aTrabalhar: boolean;
    aoPagar?: () => void;
    aoAnular: () => void;
    aoMarcarPaga: () => void;
}) {
    return (
        <tr className="entra transition-all duration-200 hover:bg-indigo-50/60" style={cascata(i)}>
            <td className="px-4 py-3">
                <div className="font-semibold text-indigo-700">{d.numero}</div>
                {/* Os DOIS números: a série interna em cima, a da AGT abaixo. */}
                {d.numero_agt && d.numero_agt !== d.numero && (
                    <div className="mt-0.5 font-mono text-[11px] text-slate-400">{d.numero_agt}</div>
                )}
            </td>
            <td className="px-4 py-3 text-slate-700">{d.parte}</td>
            <td className="px-4 py-3 tabular-nums text-slate-600">{data(d.data)}</td>
            <td className="px-4 py-3">
                <Etiqueta cor={d.estado_cor}>{d.estado_rotulo}</Etiqueta>
            </td>
            {/* O que a AGT disse. Numa proforma ou num orçamento diz «não
                comunicável»: dizer «pendente» era um alarme para uma coisa que
                nunca vai acontecer. */}
            <td className="px-4 py-3">
                <Etiqueta cor={d.agt.cor} icone={SINAL_AGT[d.agt.cor]}>
                    {d.agt.rotulo}
                </Etiqueta>
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
                    {/* Pagar abre o modal partilhado; só com saldo por pagar. */}
                    {aoPagar && temSaldo && (d.saldo ?? 0) > 0.01 && (
                        <button
                            type="button"
                            onClick={aoPagar}
                            title={t('Pagar')}
                            aria-label={t('Pagar :numero', { numero: d.numero })}
                            className={cls('p-2 text-emerald-600 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-emerald-50', RAIO, FOCO)}
                        >
                            <i className="fas fa-money-bill-wave" aria-hidden="true" />
                        </button>
                    )}
                    {/* As acções continuam a apontar para as páginas de sempre.
                        Reescrever o gerador de PDF para migrar uma LISTA seria
                        trocar o risco de sítio sem ganhar nada. */}
                    <a
                        href={`${rota}/${d.id}/edit`}
                        title={t('Abrir')}
                        aria-label={t('Abrir :numero', { numero: d.numero })}
                        className={cls('p-2 text-slate-500 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-slate-100', RAIO, FOCO)}
                    >
                        <i className="fas fa-eye" aria-hidden="true" />
                    </a>

                    {/* TRÊS CAMINHOS PARA O MESMO DOCUMENTO, e nenhum
                        substitui outro. A PRÉ-VISUALIZAÇÃO é a origem de tudo:
                        abre num separador e é de lá que se imprime. */}
                    <a
                        href={`${rota}/${d.id}/preview`}
                        target="_blank"
                        rel="noopener"
                        title={t('Pré-visualizar / Imprimir')}
                        aria-label={t('Pré-visualizar :numero', { numero: d.numero })}
                        className={cls('p-2 text-slate-500 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-slate-100', RAIO, FOCO)}
                    >
                        <i className="fas fa-print" aria-hidden="true" />
                    </a>
                    <a
                        href={`${rota}/${d.id}/pdf`}
                        title={t('PDF')}
                        aria-label={t('PDF de :numero', { numero: d.numero })}
                        className={cls('p-2 text-red-500 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-red-50', RAIO, FOCO)}
                    >
                        <i className="fas fa-file-pdf" aria-hidden="true" />
                    </a>

                    {/* E o PDF DO ECRÃ: a própria pré-visualização,
                        fotografada — o de cima sai do DomPDF e tem texto para
                        copiar. */}
                    <PdfDoEcra
                        url={`${rota}/${d.id}/preview`}
                        titulo={t('Descarregar :numero em PDF', { numero: d.numero })}
                    />

                    {/* Duplicar: aproveita o trabalho, nunca a identidade do
                        documento. Vai por `?duplicar=` para o ecrã de emissão
                        de sempre — o que muda é de onde vêm os valores
                        iniciais, não o ecrã. O que NUNCA se copia (número,
                        série, hash, datas, estado) está no DuplicaDocumento. */}
                    {podeDuplicar && (
                        <a
                            href={`${rota}/create?duplicar=${d.id}`}
                            title={t('Duplicar para novo documento')}
                            aria-label={t('Duplicar :numero', { numero: d.numero })}
                            className={cls('p-2 text-teal-600 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-teal-50', RAIO, FOCO)}
                        >
                            <i className="fas fa-copy" aria-hidden="true" />
                        </a>
                    )}

                    {/* Marcar como paga: fecha a conta de quem já pagou por
                        fora. Não lança recibo nem mexe na caixa — para isso há
                        o botão de pagar, aqui ao lado. */}
                    {d.pode_marcar_paga && (
                        <button
                            type="button"
                            onClick={aoMarcarPaga}
                            disabled={aTrabalhar}
                            title={t('Marcar como paga')}
                            aria-label={t('Marcar :numero como paga', { numero: d.numero })}
                            className={cls('p-2 text-green-600 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-green-50 disabled:opacity-40', RAIO, FOCO)}
                        >
                            <i className="fas fa-check-circle" aria-hidden="true" />
                        </button>
                    )}

                    {/* Anular: uma factura de compra NÃO se elimina — o
                        documento é do fornecedor e ficou registado que foi
                        recebido. Anula-se, e o stock que entrou é revertido. */}
                    {d.pode_anular && (
                        <button
                            type="button"
                            onClick={aoAnular}
                            disabled={aTrabalhar}
                            title={t('Anular (reverte o stock)')}
                            aria-label={t('Anular :numero', { numero: d.numero })}
                            className={cls('p-2 text-amber-600 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-amber-50 disabled:opacity-40', RAIO, FOCO)}
                        >
                            <i className="fas fa-ban" aria-hidden="true" />
                        </button>
                    )}
                </div>
            </td>
        </tr>
    );
}

/* ─── Peças ───────────────────────────────────────────────────────────── */

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
 * O ESTADO VAZIO COM DESENHO.
 *
 * O círculo de 80px com o ícone lá dentro é o que o ecrã em Blade tinha, e não
 * é enfeite: uma linha de texto no meio de uma caixa branca lê-se como um erro
 * de carregamento. A frase diz o que fazer a seguir, e a acção está ali ao
 * lado — quem chegou a uma lista vazia por causa de um filtro não tem de ir
 * procurar onde o desligar.
 */
function EstadoVazio({
    icone,
    titulo,
    frase,
    accao,
}: {
    icone: string;
    titulo: string;
    frase?: string;
    accao?: React.ReactNode;
}) {
    return (
        <div className={cls(CARTAO, 'animate-fade-in px-6 py-16 text-center')}>
            <div className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                <i className={`fas ${icone} text-4xl text-slate-300`} aria-hidden="true" />
            </div>
            <p className="text-lg font-bold text-slate-800">{titulo}</p>
            {frase && <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">{frase}</p>}
            {accao && <div className="mt-5 flex justify-center">{accao}</div>}
        </div>
    );
}

/**
 * OS CARTÕES DE NÚMERO DO TOPO.
 *
 * O ecrã em Blade tinha-os e a migração deixou a lista a começar por uma caixa
 * de filtros branca. Voltam com os mesmos gradientes, pelo `CartaoNumero`.
 *
 * DE ONDE VEM CADA NÚMERO, dito no próprio cartão: a CONTAGEM é a do servidor
 * e conta tudo o que passa nos filtros; as SOMAS são das linhas à vista, e por
 * isso dizem-no. Somar a página e chamar-lhe «total» seria um número que muda
 * ao carregar em «Seguinte» — os totais filtrados exigiriam outra pergunta ao
 * servidor, e este lote não mexe na API.
 */
function Cartoes({
    total,
    linhas,
    temSaldo,
    aActualizar,
}: {
    total?: number;
    linhas: LinhaDeDocumento[];
    temSaldo: boolean;
    aActualizar: boolean;
}) {
    const valor = linhas.reduce((soma, d) => soma + Number(d.valor ?? 0), 0);
    const porPagar = linhas.reduce((soma, d) => soma + Number(d.saldo ?? 0), 0);
    const nesta = t(':quantos nesta página', { quantos: linhas.length });

    return (
        <div className={cls('grid gap-3 sm:grid-cols-2', temSaldo ? 'lg:grid-cols-3' : 'lg:grid-cols-2', aActualizar && 'opacity-70')}>
            <CartaoNumero
                rotulo={t('Documentos')}
                tom="indigo"
                icone="fa-file-lines"
                nota={t('com os filtros actuais')}
                valor={
                    total === undefined ? (
                        <span className="text-white/50">—</span>
                    ) : (
                        total.toLocaleString(etiquetaIntl())
                    )
                }
            />
            <CartaoNumero
                rotulo={t('Valor nesta página')}
                tom="verde"
                icone="fa-money-bill-wave"
                sufixo="Kz"
                nota={nesta}
                valor={kz(valor)}
            />
            {temSaldo && (
                <CartaoNumero
                    rotulo={t('Falta pagar nesta página')}
                    tom="ambar"
                    icone="fa-clock"
                    sufixo="Kz"
                    nota={nesta}
                    valor={kz(porPagar)}
                />
            )}
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    if (daApi?.eSessaoMorta) {
        return (
            <div className={cls('border border-amber-200 bg-amber-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-amber-900">{t('A sessão expirou')}</h2>
                <p className="mb-4 text-sm text-amber-900">{t('Entre outra vez para continuar.')}</p>
                <Botao cor="primaria" tom="solida" onClick={() => window.location.reload()}>
                    {t('Voltar a entrar')}
                </Botao>
            </div>
        );
    }

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível carregar')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
