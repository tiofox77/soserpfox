import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    documentos,
    type FiltrosDeDocumentos,
    type LinhaDeDocumento,
    type ResumoDosDocumentos,
} from '@/api/documentos';
import { compra } from '@/api/compra';
import { ErroDaApi } from '@/api/cliente';
import { etiquetaIntl, t } from '@/i18n';
import { RegistarPagamento } from '@/ecras/facturacao/RegistarPagamento';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { PdfDoEcra } from '@/ui/PdfDoEcra';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { IntervaloDeDatas, PorPagina } from '@/ui/FiltrosComuns';
import { Dado } from './ExtratoDaParte';
import { ACCAO_DA_FAIXA, Faixa } from './faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

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
    const [recado, porRecado] = useRecadoNoCanto('');
    const [aviso, porAviso] = useState('');
    /** O documento que se está a eliminar, a converter, ou cujo histórico se vê. */
    const [aApagar, porAApagar] = useState<LinhaDeDocumento | null>(null);
    const [aAnular, porAAnular] = useState<LinhaDeDocumento | null>(null);
    const [aConverter, porAConverter] = useState<LinhaDeDocumento | null>(null);
    const [aVerHistorico, porAVerHistorico] = useState<LinhaDeDocumento | null>(null);
    /** O documento cuja FICHA está aberta — só para ler, sem sair da lista. */
    const [aVer, porAVer] = useState<LinhaDeDocumento | null>(null);

    /*
     * ANULAR — só nas facturas de compra.
     *
     * Uma factura de compra NÃO se elimina: anula-se, e o stock que tinha
     * entrado é revertido. Não é coisa que se faça por engano, por isso
     * pergunta-se antes. Quem pode e quando pode vem decidido do servidor,
     * linha a linha; aqui só se pergunta e se conta o que ele respondeu.
     */
    const accao = useMutation({
        mutationFn: ({ id }: { id: number }) => compra.anular(id),
        onSuccess: (r) => {
            porAviso('');
            porRecado(r.message);
            void cache.invalidateQueries({ queryKey: ['documentos', tipo] });
        },
        onError: (e) => porAviso(e instanceof ErroDaApi ? e.message : t('Não foi possível concluir a operação.')),
    });

    /**
     * ELIMINAR — o que a lista em Blade tinha e a migração deixou cair.
     *
     * Pergunta-se antes: apagar um documento não se desfaz. Quem pode e o que
     * ainda se pode apagar vem decidido do servidor, linha a linha.
     */
    const apagar = useMutation({
        mutationFn: (d: LinhaDeDocumento) => documentos.apagar(tipo, d.id),
        onSuccess: (r) => {
            porAviso('');
            porAApagar(null);
            porRecado(r.message);
            void cache.invalidateQueries({ queryKey: ['documentos', tipo] });
        },
        onError: (e) => porAviso(e instanceof ErroDaApi ? e.message : t('Não foi possível eliminar.')),
    });

    /**
     * CONVERTER EM FACTURA.
     *
     * A conta é do modelo — a mesma de sempre, com a taxa de HOJE e não a que
     * ficou gravada na proposta. A factura nasce em rascunho, e o recado diz o
     * número para se ir lá confirmar.
     */
    const converter = useMutation({
        mutationFn: (d: LinhaDeDocumento) => documentos.converter(tipo, d.id),
        onSuccess: (r) => {
            porAviso('');
            porAConverter(null);
            porRecado(r.message);
            void cache.invalidateQueries({ queryKey: ['documentos', tipo] });
        },
        onError: (e) => porAviso(e instanceof ErroDaApi ? e.message : t('Não foi possível converter.')),
    });

    function anular(d: LinhaDeDocumento): void {
        porAAnular(d);
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
    // As colunas de valor a mais que ESTE documento declara.
    const montantes = opcoes.data?.montantes ?? [];
    const rota = opcoes.data?.rota ?? '';

    /*
     * AS COLUNAS QUE SÓ ALGUNS DOCUMENTOS TÊM.
     *
     * Quem decide é o servidor (`TiposDeDocumento`): o prazo chama-se
     * «Vencimento» na factura de compra e «Validade» na proposta, a factura de
     * origem só existe nas notas, e o motivo idem. O ecrã desenha o que lhe
     * disserem — é o mesmo princípio que faz um ecrã servir nove listas.
     */
    const prazo = opcoes.data?.prazo ?? null;
    const origem = opcoes.data?.origem ?? null;
    const motivos = (opcoes.data?.motivos.length ?? 0) > 0;
    // Os lados: só os recibos os têm — venda e compra.
    const lados = opcoes.data?.lados ?? null;

    return (
        <div className="space-y-4">
            {/* O CABEÇALHO DE SEMPRE: título, a frase que diz o que este
                documento é («Devoluções, descontos e correções» explica uma
                nota de crédito a quem nunca emitiu nenhuma) e o botão de criar.
                A migração deixou a página a começar por uma fila de cartões. */}
            <Faixa
                titulo={opcoes.data?.titulo ?? ''}
                subtitulo={opcoes.data?.descricao || undefined}
                icone="fa-file-lines"
                cor="primaria"
                accoes={
                    opcoes.data?.pode_criar && (
                        <a href={`${rota}/create`} className={cls(ACCAO_DA_FAIXA, 'group')}>
                            <i
                                className="fas fa-plus transition-transform duration-300 group-hover:rotate-90"
                                aria-hidden="true"
                            />
                            {opcoes.data.novo}
                        </a>
                    )
                }
            />

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
            <Cartoes
                resumo={lista.data?.resumo}
                linhas={linhas}
                temSaldo={temSaldo}
                aActualizar={lista.isFetching}
            />

            <Cartao titulo={t('Filtros')} icone="fa-filter">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="block lg:col-span-2">
                        <Rotulo>{t('Procurar')}</Rotulo>
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))}
                            /* Nos recibos a procura passa pelas duas partes, e o texto
                               da caixa tem de o dizer: procurar «cliente» num recibo de
                               compra não encontrava nada. */
                            placeholder={t('Número ou :parte', { parte: (opcoes.data?.parte_rotulo ?? opcoes.data?.parte ?? t('nome')).toLocaleLowerCase() })}
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

                    {/* O MOTIVO — só nas notas, que são as únicas que o têm. A
                        lista em Blade tinha-o em caixa própria: uma nota de
                        crédito de devolução e uma de correcção contam histórias
                        diferentes, e é por aqui que se separam. */}
                    {(opcoes.data?.motivos.length ?? 0) > 0 && (
                        <label className="block">
                            <Rotulo>{t('Motivo')}</Rotulo>
                            <select
                                value={filtros.motivo ?? ''}
                                onChange={(e) => porFiltros((f) => ({ ...f, motivo: e.target.value, page: 1 }))}
                                className={entrada}
                            >
                                <option value="">{t('Todos')}</option>
                                {opcoes.data?.motivos.map((m) => (
                                    <option key={m.valor} value={m.valor}>
                                        {m.rotulo}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}

                    {/* O LADO, nos recibos: um de venda é dinheiro que entrou,
                        um de compra é dinheiro que saiu. A lista de sempre
                        tinha-o em caixa própria, e sem ele os dois misturam-se
                        na mesma tabela sem maneira de os separar. */}
                    {opcoes.data?.lados && (
                        <label className="block">
                            <Rotulo>{opcoes.data.lados.rotulo}</Rotulo>
                            <select
                                value={filtros.lado ?? ''}
                                onChange={(e) => porFiltros((f) => ({ ...f, lado: e.target.value, page: 1 }))}
                                className={entrada}
                            >
                                <option value="">{t('Todos')}</option>
                                {opcoes.data.lados.opcoes.map((l) => (
                                    <option key={l.valor} value={l.valor}>
                                        {l.rotulo}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}

                    {/* Aqui as datas são as do DOCUMENTO — a do orçamento, a da
                        factura — e não as da criação da ficha. */}
                    <IntervaloDeDatas
                        de={filtros.de}
                        ate={filtros.ate}
                        rotulo={t('De')}
                        aoMudar={(campo, valor) => porFiltros((f) => ({ ...f, [campo]: valor, page: 1 }))}
                    />
                </div>

                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-500">
                        {contas
                            ? t(':quantos documento(s)', { quantos: contas.total.toLocaleString('pt-PT') })
                            : t('A contar…')}
                        {lista.isFetching && <span className="ml-2 text-xs">{t('a actualizar…')}</span>}
                    </p>
                    <div className="flex flex-wrap items-center gap-3">
                        <PorPagina
                            valor={filtros.por_pagina}
                            aoMudar={(n) => porFiltros((f) => ({ ...f, por_pagina: n, page: 1 }))}
                        />
                        <Botao altura="pequeno" icone="fa-eraser" onClick={() => porFiltros({ procura: '', page: 1 })}>
                            {t('Limpar')}
                        </Botao>
                    </div>
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
                                    {/* O TIPO, nos recibos: Venda ou Compra. Vem logo a
                                        seguir ao número, como na lista de sempre — é o
                                        que diz se o dinheiro entrou ou saiu. */}
                                    {lados && <Cabecalho icone="fa-tag">{lados.rotulo}</Cabecalho>}
                                    <Cabecalho icone="fa-user">
                                        {opcoes.data?.parte_rotulo ?? (opcoes.data?.parte === 'fornecedor' ? t('Fornecedor') : t('Cliente'))}
                                    </Cabecalho>
                                    {/* A FACTURA DE ORIGEM das notas: uma nota de
                                        crédito sem saber de que factura é não se
                                        lê. O cabeçalho vem do servidor porque
                                        muda («Fatura Origem» / «Fatura Ref.»). */}
                                    {origem && <Cabecalho icone="fa-file-invoice">{origem}</Cabecalho>}
                                    <Cabecalho icone="fa-calendar">{t('Data')}</Cabecalho>
                                    {/* O PRAZO: vencimento nas facturas de compra,
                                        validade nas propostas. */}
                                    {prazo && <Cabecalho icone="fa-calendar-check">{prazo}</Cabecalho>}
                                    {motivos && <Cabecalho icone="fa-tag">{t('Motivo')}</Cabecalho>}
                                    <Cabecalho icone="fa-circle-info">{t('Estado')}</Cabecalho>
                                    <Cabecalho icone="fa-landmark">{t('Portal AGT')}</Cabecalho>
                                    <Cabecalho icone="fa-money-bill" direita>{t('Valor')}</Cabecalho>
                                    {/* AS COLUNAS DE VALOR A MAIS: o «Usado» e o
                                        «Disponível» de um adiantamento. Um saldo que se
                                        vai gastando não se lê por um número só. */}
                                    {montantes.map((m) => (
                                        <Cabecalho key={m.chave} icone={m.icone} direita>{m.rotulo}</Cabecalho>
                                    ))}
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
                                        prazo={prazo}
                                        origem={origem}
                                        motivos={motivos}
                                        montantes={montantes}
                                        aTrabalhar={accao.isPending || apagar.isPending || converter.isPending}
                                        aoPagar={opcoes.data?.pode_pagar ? () => porAPagar(d) : undefined}
                                        aoAnular={() => anular(d)}
                                        aoApagar={opcoes.data?.pode_apagar ? () => porAApagar(d) : undefined}
                                        aoConverter={opcoes.data?.pode_converter ? () => porAConverter(d) : undefined}
                                        aoVerHistorico={
                                            opcoes.data?.tem_historico ? () => porAVerHistorico(d) : undefined
                                        }
                                        aoVer={() => porAVer(d)}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Cartao>
            )}

            {contas && (
                <Paginacao
                    emCartao
                    pagina={contas.current_page}
                    ultima={contas.last_page}
                    total={contas.total}
                    aCarregar={lista.isFetching}
                    aMudar={(p) => porFiltros((f) => ({ ...f, page: p }))}
                />
            )}

            {/* ELIMINAR — pergunta-se antes: não se desfaz. */}
            <Modal
                aberto={aAnular !== null}
                aoFechar={() => porAAnular(null)}
                titulo={t('Anular factura de compra')}
                subtitulo={t('Esta operação também corrige o stock')}
                icone="fa-ban"
                cor="aviso"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAAnular(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="aviso"
                            tom="solida"
                            icone="fa-ban"
                            aTrabalhar={accao.isPending}
                            onClick={() => {
                                if (!aAnular) return;
                                accao.mutate({ id: aAnular.id }, { onSuccess: () => porAAnular(null) });
                            }}
                        >
                            {t('Anular e reverter stock')}
                        </Botao>
                    </>
                }
            >
                <div className={cls('border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950', RAIO)}>
                    <p className="font-semibold">
                        {t('Confirma a anulação de :numero?', { numero: aAnular?.numero ?? '' })}
                    </p>
                    <p className="mt-2 text-amber-800">
                        {t('O documento ficará anulado e todo o stock que entrou por esta compra será revertido. Esta operação não pode ser desfeita.')}
                    </p>
                </div>
            </Modal>

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar documento')}
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
                <p className="text-sm text-slate-700">
                    {t('Vai eliminar :numero. Não há volta.', { numero: aApagar?.numero ?? '' })}
                </p>
            </Modal>

            {/* CONVERTER — diz-se o que vai acontecer antes de acontecer. */}
            <Modal
                aberto={aConverter !== null}
                aoFechar={() => porAConverter(null)}
                titulo={t('Converter em factura')}
                icone="fa-file-circle-plus"
                cor="bom"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAConverter(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="bom"
                            tom="solida"
                            icone="fa-file-circle-plus"
                            aTrabalhar={converter.isPending}
                            onClick={() => aConverter && converter.mutate(aConverter)}
                        >
                            {t('Converter')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {t('Vai nascer uma factura a partir de :numero.', { numero: aConverter?.numero ?? '' })}
                </p>
                <p className="mt-2 text-sm text-slate-500">
                    {/* As duas coisas que quem converte tem de saber: fica em
                        rascunho, e a taxa é a de hoje. */}
                    {t('Fica em RASCUNHO até ser emitida, e as taxas são as de hoje — não as que ficaram gravadas na proposta.')}
                </p>
            </Modal>

            <HistoricoDeConversoes
                tipo={tipo}
                documento={aVerHistorico}
                aoFechar={() => porAVerHistorico(null)}
            />

            <FichaDoDocumento
                tipo={tipo}
                documento={aVer}
                rota={rota}
                rotuloDaParte={opcoes.data?.parte_rotulo ?? (opcoes.data?.parte === 'fornecedor' ? t('Fornecedor') : t('Cliente'))}
                aoFechar={() => porAVer(null)}
            />
        </div>
    );
}

/**
 * A FICHA DE UM DOCUMENTO — o modal de VER que a lista em Blade tinha.
 *
 * Quem só quer conferir um documento não tem de abrir o editor (onde se
 * estraga um por engano) nem a pré-visualização (uma página inteira, feita
 * para imprimir). Isto é o que se olha de relance: quem, quando, o que leva e
 * quanto dá — e dali salta-se para a pré-visualização, que é de onde se
 * imprime.
 */
function FichaDoDocumento({
    tipo,
    documento,
    rota,
    rotuloDaParte,
    aoFechar,
}: {
    tipo: string;
    documento: LinhaDeDocumento | null;
    rota: string;
    /** «Cliente», «Fornecedor» ou «Cliente/Fornecedor» — o mesmo da tabela. */
    rotuloDaParte: string;
    aoFechar: () => void;
}) {
    const q = useQuery({
        queryKey: ['documentos', tipo, 'ficha', documento?.id],
        queryFn: () => documentos.ficha(tipo, documento!.id),
        enabled: documento !== null,
    });

    if (!documento) {
        return null;
    }

    const f = q.data;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={documento.numero}
            subtitulo={documento.numero_agt ?? t('Série ainda não registada na AGT')}
            icone="fa-file-invoice"
            cor="primaria"
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Fechar')}</Botao>

                    {/* OS DOIS PDF, como a linha da lista os tem — e como o
                        modal em Blade os tinha. Sem eles, quem abria a ficha
                        para conferir um orçamento e o queria mandar ao cliente
                        tinha de fechar, voltar à linha e procurar o botão. O
                        do servidor (DomPDF) tem texto para copiar; o do ecrã é
                        a própria pré-visualização, fotografada. O do servidor
                        abre à parte: no mesmo separador, o PDF tomava o lugar
                        da lista. */}
                    <div className="flex items-center justify-center gap-1">
                        <a
                            href={`${rota}/${documento.id}/pdf`}
                            target="_blank"
                            rel="noreferrer"
                            title={t('PDF')}
                            aria-label={t('PDF de :numero', { numero: documento.numero })}
                            className={cls(
                                'inline-flex h-10 items-center gap-2 border border-red-200 bg-white px-4 text-sm font-semibold text-red-600',
                                'transition-all duration-200 hover:-translate-y-0.5 hover:border-red-300 hover:bg-red-50 hover:shadow-sm',
                                RAIO,
                                FOCO,
                            )}
                        >
                            <i className="fas fa-file-pdf" aria-hidden="true" />
                            {t('PDF')}
                        </a>
                        <PdfDoEcra
                            url={`${rota}/${documento.id}/preview`}
                            titulo={t('Descarregar :numero em PDF', { numero: documento.numero })}
                        />
                    </div>

                    <a
                        href={`${rota}/${documento.id}/preview`}
                        target="_blank"
                        rel="noreferrer"
                        className={cls(
                            'inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-2',
                            'text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl',
                        )}
                    >
                        <i className="fas fa-print" aria-hidden="true" />
                        {t('Pré-visualizar / Imprimir')}
                    </a>
                </>
            }
        >
            {q.isPending || !f ? (
                <Carregando />
            ) : (
                <div className="space-y-5">
                    <div className="grid gap-4 md:grid-cols-2">
                        <section className={cls('border border-slate-200 bg-slate-50 p-4', RAIO)}>
                            <h4 className="mb-2 text-sm font-bold text-slate-800">
                                <i className="fas fa-user mr-2 text-slate-400" aria-hidden="true" />
                                {t('Informações do :parte', { parte: rotuloDaParte })}
                            </h4>
                            <p className="font-bold text-slate-900">{f.parte.nome}</p>
                            {f.parte.nif && <p className="text-sm text-slate-600">NIF: {f.parte.nif}</p>}
                            {f.parte.email && <p className="text-sm text-slate-600">{f.parte.email}</p>}
                            {f.parte.telefone && <p className="text-sm text-slate-600">{f.parte.telefone}</p>}
                        </section>

                        <section className={cls('border border-slate-200 bg-slate-50 p-4', RAIO)}>
                            <h4 className="mb-2 text-sm font-bold text-slate-800">
                                <i className="fas fa-circle-info mr-2 text-slate-400" aria-hidden="true" />
                                {t('Documento')}
                            </h4>
                            <div className="space-y-1.5">
                                <Dado rotulo={t('Data')} valor={data(f.data)} />
                                {f.prazo_rotulo && <Dado rotulo={f.prazo_rotulo} valor={data(f.prazo)} />}
                                {f.regiao_fiscal && <Dado rotulo={t('Região fiscal')} valor={f.regiao_fiscal} />}
                                <div className="flex items-start gap-2 pt-1 text-sm">
                                    <span className="w-28 flex-none text-slate-500">{t('Estado')}</span>
                                    <span className="flex flex-wrap gap-1.5">
                                        <Etiqueta cor={f.estado_cor}>{f.estado_rotulo}</Etiqueta>
                                        <Etiqueta cor={f.agt.cor} icone={SINAL_AGT[f.agt.cor]}>
                                            {f.agt.rotulo}
                                        </Etiqueta>
                                    </span>
                                </div>
                            </div>
                        </section>
                    </div>

                    {/* O DOCUMENTO RECTIFICADO, nas notas. Uma nota corrige uma
                        factura, e a factura tem de estar escrita na ficha: é o
                        que liga as duas na conferência. */}
                    {f.rectifica && (
                        <section className={cls('border border-amber-200 bg-amber-50 p-4', RAIO)}>
                            <h4 className="mb-2 text-sm font-bold text-amber-900">
                                <i className="fas fa-file-pen mr-2" aria-hidden="true" />
                                {t('Documento Rectificado')}
                            </h4>
                            <div className="grid gap-2 sm:grid-cols-3">
                                <div>
                                    <p className="text-xs text-amber-700">{f.rectifica.rotulo}</p>
                                    {f.rectifica.id ? (
                                        <a
                                            href={`/invoicing/sales/invoices/${f.rectifica.id}/preview`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="font-semibold text-indigo-700 hover:underline"
                                        >
                                            {f.rectifica.numero}
                                        </a>
                                    ) : (
                                        <p className="text-sm text-slate-500">{t('Sem factura associada')}</p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-xs text-amber-700">{t('Motivo')}</p>
                                    <p className="text-sm font-semibold text-slate-900">{f.rectifica.motivo ?? '—'}</p>
                                </div>
                                <div>
                                    {/* A expressão que a lei manda escrever na nota. */}
                                    <p className="text-xs text-amber-700">{t('Expressão (Art. 12.º)')}</p>
                                    <p className="text-sm font-semibold text-slate-900">{f.rectifica.expressao}</p>
                                </div>
                            </div>
                        </section>
                    )}

                    {/* AS LINHAS. Um recibo ou um adiantamento não as tem — são
                        dinheiro, não mercadoria — e a tabela não aparece em vez
                        de aparecer vazia. */}
                    {f.tem_linhas && (
                        <section>
                            <h4 className="mb-2 text-sm font-bold text-slate-700">
                                <i className="fas fa-box mr-1.5 text-slate-400" aria-hidden="true" />
                                {t('Produtos (:quantos)', { quantos: f.linhas.length })}
                            </h4>
                            <div className={cls('overflow-x-auto border border-slate-200', RAIO)}>
                                <table className="w-full min-w-[560px] text-sm">
                                    <thead className="bg-slate-50 text-xs text-slate-600">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold">{t('Produto')}</th>
                                            <th className="px-3 py-2 text-center font-semibold">{t('Qtd')}</th>
                                            <th className="px-3 py-2 text-right font-semibold">{t('Preço')}</th>
                                            <th className="px-3 py-2 text-center font-semibold">{t('Desc%')}</th>
                                            <th className="px-3 py-2 text-center font-semibold">IVA</th>
                                            <th className="px-3 py-2 text-right font-semibold">{t('Total')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {f.linhas.map((l, i) => (
                                            <tr key={i} className="transition-colors hover:bg-indigo-50/50">
                                                <td className="px-3 py-2">
                                                    <p className="font-semibold text-slate-900">{l.descricao}</p>
                                                    {l.unidade && (
                                                        <p className="text-xs text-slate-400">{l.unidade}</p>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-center tabular-nums">
                                                    {l.quantidade.toLocaleString(etiquetaIntl(), {
                                                        maximumFractionDigits: 3,
                                                    })}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums">{kz(l.preco)}</td>
                                                <td className="px-3 py-2 text-center tabular-nums">{l.desconto}%</td>
                                                <td className="px-3 py-2 text-center tabular-nums">{l.taxa}%</td>
                                                <td className="px-3 py-2 text-right font-bold tabular-nums">
                                                    {kz(l.total)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}

                    <section className={cls('border border-slate-200 bg-slate-50 p-4', RAIO)}>
                        <Soma rotulo={t('Subtotal')} valor={f.totais.subtotal} />
                        {f.totais.desconto_comercial > 0 && (
                            <Soma rotulo={t('Desconto Comercial')} valor={f.totais.desconto_comercial} />
                        )}
                        {f.totais.desconto_financeiro > 0 && (
                            <Soma rotulo={t('Desconto Financeiro')} valor={f.totais.desconto_financeiro} />
                        )}
                        <Soma rotulo="IVA" valor={f.totais.imposto} />
                        {/* IEC E IMPOSTO DE SELO. Sem eles o total não
                            reconcilia: quem soma o subtotal com o IVA fica a
                            dever a diferença sem perceber de onde vem. */}
                        {f.impostos_extra.map((x) => (
                            <Soma key={x.tipo} rotulo={x.tipo} valor={x.valor} />
                        ))}
                        {/* A RETENÇÃO NA FONTE, só quando existe — como no modal
                            de sempre. Sem ela, um documento com retenção mostra um
                            total que não fecha com as parcelas de cima. */}
                        {f.totais.retencao > 0 && <Soma rotulo={t('Retenção')} valor={f.totais.retencao} />}
                        <div className="mt-2 flex items-baseline justify-between border-t border-slate-300 pt-2">
                            <span className="font-bold text-slate-800">{t('TOTAL')}</span>
                            <span className="text-xl font-bold tabular-nums text-slate-900">
                                {kz(f.totais.total)} Kz
                            </span>
                        </div>
                    </section>

                    {f.notas && (
                        <section className={cls('border border-slate-200 bg-white p-4', RAIO)}>
                            <h4 className="mb-1 text-sm font-bold text-slate-700">
                                <i className="fas fa-align-left mr-1.5 text-slate-400" aria-hidden="true" />
                                {t('Notas')}
                            </h4>
                            <p className="whitespace-pre-line text-sm text-slate-600">{f.notas}</p>
                        </section>
                    )}

                    {/* O BLOCO FISCAL — só no que a empresa comunica. É o que
                        responde à pergunta que se faz a seguir a emitir: «foi
                        aceite?». Numa proforma não aparece: nunca é enviada. */}
                    {f.fiscal && (
                        <section className={cls('border border-slate-200 bg-slate-50 p-4', RAIO)}>
                            <h4 className="mb-2 text-sm font-bold text-slate-800">
                                <i className="fas fa-landmark mr-2 text-slate-400" aria-hidden="true" />
                                {t('Dados Fiscais AGT')}
                            </h4>
                            <div className="grid gap-3 text-sm sm:grid-cols-3">
                                <div>
                                    <p className="text-xs text-slate-500">{t('Hash SAFT')}</p>
                                    <p className="font-mono font-bold text-slate-900">{f.fiscal.hash ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-500">{t('Estado SAFT')}</p>
                                    <p className="font-bold text-slate-900">{f.fiscal.estado_saft ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-500">{t('Submissão AGT')}</p>
                                    <Etiqueta cor={f.agt.cor} icone={SINAL_AGT[f.agt.cor]}>
                                        {f.agt.rotulo}
                                    </Etiqueta>
                                </div>
                            </div>
                            {f.fiscal.agt_referencia && (
                                <p className="mt-2 text-xs text-slate-500">
                                    {t('Referência AGT:')}{' '}
                                    <span className="font-mono text-slate-700">{f.fiscal.agt_referencia}</span>
                                    {f.fiscal.agt_submetido_em && ` · ${f.fiscal.agt_submetido_em}`}
                                </p>
                            )}
                        </section>
                    )}
                </div>
            )}
        </Modal>
    );
}

/** Uma linha dos totais: rótulo à esquerda, valor à direita. */
function Soma({ rotulo, valor }: { rotulo: string; valor: number }) {
    return (
        <div className="flex items-baseline justify-between py-0.5 text-sm">
            <span className="text-slate-600">{rotulo}</span>
            <span className="font-semibold tabular-nums text-slate-800">{kz(valor)} Kz</span>
        </div>
    );
}

/**
 * O HISTÓRICO DE CONVERSÕES — que facturas já saíram desta proposta.
 *
 * A mesma proposta pode ser convertida MAIS DO QUE UMA VEZ, e é de propósito:
 * um fornecimento repetido factura-se a partir da mesma proforma. Este ecrã é
 * o que evita a duplicação por engano — mostra o que já saiu antes de se
 * converter outra vez.
 */
function HistoricoDeConversoes({
    tipo,
    documento,
    aoFechar,
}: {
    tipo: string;
    documento: LinhaDeDocumento | null;
    aoFechar: () => void;
}) {
    const q = useQuery({
        queryKey: ['documentos', tipo, 'historico', documento?.id],
        queryFn: () => documentos.historico(tipo, documento!.id),
        enabled: documento !== null,
    });

    if (!documento) {
        return null;
    }

    const h = q.data;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Histórico de Conversões')}
            subtitulo={documento.numero}
            icone="fa-clock-rotate-left"
            cor="primaria"
            largura="lg"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            {q.isPending || !h ? (
                <Carregando />
            ) : (
                <div className="space-y-4">
                    <div className={cls('grid gap-3 border border-slate-200 bg-slate-50 p-4 sm:grid-cols-4', RAIO)}>
                        <Dado rotulo={t('Cliente')} valor={h.documento.parte} />
                        <Dado rotulo={t('Data')} valor={data(h.documento.data)} />
                        <Dado rotulo={t('Total')} valor={`${kz(h.documento.valor)} Kz`} />
                        <div>
                            <p className="text-xs font-semibold text-slate-500">{t('Estado')}</p>
                            <Etiqueta cor={h.documento.estado_cor}>{h.documento.estado_rotulo}</Etiqueta>
                        </div>
                    </div>

                    <section>
                        <h4 className="mb-2 text-sm font-bold text-slate-700">
                            <i className="fas fa-file-invoice mr-1.5 text-slate-400" aria-hidden="true" />
                            {t('Faturas Geradas (:quantas)', { quantas: h.facturas.length })}
                        </h4>

                        {h.facturas.length === 0 ? (
                            <div className="py-10 text-center">
                                <div className="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-full bg-slate-100">
                                    <i className="fas fa-file-circle-question text-3xl text-slate-300" aria-hidden="true" />
                                </div>
                                <p className="text-sm font-semibold text-slate-600">
                                    {t('Nenhuma fatura gerada ainda')}
                                </p>
                                <p className="mt-1 text-xs text-slate-400">
                                    {t('Use «Converter em factura» para criar a primeira.')}
                                </p>
                            </div>
                        ) : (
                            <div className={cls('overflow-x-auto border border-slate-200', RAIO)}>
                                <table className="w-full min-w-[560px] text-sm">
                                    <thead className="bg-slate-50 text-xs text-slate-600">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold">{t('Número')}</th>
                                            <th className="px-3 py-2 text-left font-semibold">{t('Data Fatura')}</th>
                                            <th className="px-3 py-2 text-left font-semibold">{t('Vencimento')}</th>
                                            <th className="px-3 py-2 text-right font-semibold">{t('Total')}</th>
                                            <th className="px-3 py-2 text-left font-semibold">{t('Estado')}</th>
                                            <th className="px-3 py-2 text-right font-semibold">{t('Acções')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {h.facturas.map((f) => (
                                            <tr key={f.id} className="transition-colors hover:bg-indigo-50/50">
                                                <td className="px-3 py-2 font-semibold">
                                                    <a
                                                        href={`${f.rota}/${f.id}/preview`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="text-indigo-600 hover:underline"
                                                    >
                                                        {f.numero}
                                                    </a>
                                                </td>
                                                <td className="px-3 py-2 text-slate-500">
                                                    {data(f.data)}
                                                </td>
                                                <td className="px-3 py-2 text-slate-500">
                                                    {data(f.vencimento)}
                                                </td>
                                                <td className="px-3 py-2 text-right font-semibold tabular-nums">
                                                    {kz(f.total)}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <Etiqueta cor={f.estado_cor}>{f.estado_rotulo}</Etiqueta>
                                                </td>
                                                {/* O PAPEL DE CADA FACTURA QUE SAIU. O
                                                    histórico em Blade tinha o PDF ao lado
                                                    de cada uma; aqui só o número levava à
                                                    pré-visualização, e descarregar pedia
                                                    abri-la e procurar lá. A `rota` vem do
                                                    servidor — venda ou compra. */}
                                                <td className="px-3 py-2">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <a
                                                            href={`${f.rota}/${f.id}/pdf`}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            title={t('PDF')}
                                                            aria-label={t('PDF de :numero', { numero: f.numero })}
                                                            className={cls('p-2 text-red-500 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-red-50', RAIO, FOCO)}
                                                        >
                                                            <i className="fas fa-file-pdf" aria-hidden="true" />
                                                        </a>
                                                        <PdfDoEcra
                                                            url={`${f.rota}/${f.id}/preview`}
                                                            titulo={t('Descarregar :numero em PDF', { numero: f.numero })}
                                                        />
                                                    </div>
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
        </Modal>
    );
}

/**
 * A cor de um estado, traduzida para o tom do cartão e para um ícone.
 *
 * O servidor devolve o PAPEL da cor («bom», «aviso»), e não uma classe: sem
 * build, o Tailwind do browser não gera uma classe montada em tempo de
 * execução. E o ícone acompanha sempre — cor sozinha não chega a quem não a
 * distingue.
 */
const TOM_DO_ESTADO: Record<string, TomDoCartao> = {
    bom: 'verde',
    primaria: 'azul',
    aviso: 'ambar',
    perigo: 'vermelho',
    neutra: 'cinza',
};

const SINAL_DO_ESTADO: Record<string, string> = {
    bom: 'fa-circle-check',
    primaria: 'fa-paper-plane',
    aviso: 'fa-clock',
    perigo: 'fa-triangle-exclamation',
    neutra: 'fa-file',
};

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
    prazo,
    origem,
    motivos,
    montantes,
    aTrabalhar,
    aoPagar,
    aoAnular,
    aoApagar,
    aoConverter,
    aoVerHistorico,
    aoVer,
}: {
    /** A ordem na lista, só para a entrada em cascata. */
    i: number;
    d: LinhaDeDocumento;
    rota: string;
    temSaldo: boolean;
    podeDuplicar: boolean;
    /** As colunas que só alguns documentos têm — decididas pelo servidor. */
    prazo: string | null;
    origem: string | null;
    motivos: boolean;
    montantes: Array<{ chave: string; rotulo: string; icone: string }>;
    aTrabalhar: boolean;
    aoPagar?: () => void;
    aoAnular: () => void;
    /** Eliminar, converter e ver o histórico: `undefined` onde não existem. */
    aoApagar?: () => void;
    aoConverter?: () => void;
    aoVerHistorico?: () => void;
    /** Abre a FICHA — o modal de ver, sem sair da lista. */
    aoVer: () => void;
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
            {/* O TIPO — a etiqueta que diz se o recibo é de venda ou de
                compra. Só aparece onde o documento tem dois lados: o
                servidor manda o `lado` decidido, com cor e ícone. */}
            {d.lado && (
                <td className="px-4 py-3">
                    <Etiqueta cor={d.lado.cor} icone={d.lado.icone}>{d.lado.rotulo}</Etiqueta>
                </td>
            )}
            <td className="px-4 py-3 text-slate-700">{d.parte}</td>

            {/* A FACTURA DE ORIGEM, nas notas: leva ao documento que a nota
                corrige — é a primeira coisa que se quer ver a seguir. */}
            {origem && (
                <td className="px-4 py-3">
                    {d.origem ? (
                        <a
                            href={`/invoicing/sales/invoices/${d.origem.id}/preview`}
                            target="_blank"
                            rel="noreferrer"
                            className="font-semibold text-indigo-600 hover:underline"
                        >
                            {d.origem.numero}
                        </a>
                    ) : (
                        <span className="text-slate-300">—</span>
                    )}
                </td>
            )}

            <td className="px-4 py-3 tabular-nums text-slate-600">{data(d.data)}</td>

            {/* O PRAZO, e a marca de EXPIRADO — quem decide que já passou é o
                servidor, com o relógio dele. Uma proposta caducada não se
                converte, e uma compra vencida é dinheiro em atraso. */}
            {prazo && (
                <td className="px-4 py-3 tabular-nums">
                    {d.prazo ? (
                        <span className={d.expirado ? 'font-semibold text-red-600' : 'text-slate-600'}>
                            {data(d.prazo)}
                            {d.expirado && (
                                <i className="fas fa-triangle-exclamation ml-1.5" title={t('Prazo ultrapassado')} />
                            )}
                        </span>
                    ) : (
                        <span className="text-slate-300">—</span>
                    )}
                </td>
            )}

            {motivos && (
                <td className="px-4 py-3">
                    {d.motivo_rotulo ? (
                        <Etiqueta cor="neutra" icone="fa-tag">
                            {d.motivo_rotulo}
                        </Etiqueta>
                    ) : (
                        <span className="text-slate-300">—</span>
                    )}
                </td>
            )}

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
            {/* O usado e o disponível: o disponível a verde quando ainda há
                saldo, porque é a pergunta que se faz a um adiantamento. */}
            {montantes.map((m) => (
                <td key={m.chave} className="px-4 py-3 text-right tabular-nums">
                    {(d.montantes?.[m.chave] ?? 0) > 0.01 ? (
                        <span className={cls('font-semibold', m.chave === 'remaining_amount' ? 'text-emerald-600' : 'text-slate-600')}>
                            {kz(d.montantes?.[m.chave] ?? 0)}
                        </span>
                    ) : (
                        <span className="text-slate-300">—</span>
                    )}
                </td>
            ))}
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
                    {/* VER A FICHA — o modal que a lista em Blade tinha. Quem
                        só quer conferir não abre o editor (onde se estraga um
                        documento por engano) nem a pré-visualização, que é uma
                        página inteira feita para imprimir. */}
                    <button
                        type="button"
                        onClick={aoVer}
                        title={t('Ver detalhes')}
                        aria-label={t('Ver :numero', { numero: d.numero })}
                        className={cls('p-2 text-indigo-600 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-indigo-50', RAIO, FOCO)}
                    >
                        <i className="fas fa-eye" aria-hidden="true" />
                    </button>

                    {/* As acções continuam a apontar para as páginas de sempre.
                        Reescrever o gerador de PDF para migrar uma LISTA seria
                        trocar o risco de sítio sem ganhar nada. */}
                    <a
                        href={`${rota}/${d.id}/edit`}
                        title={t('Abrir para editar')}
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

                    {/* CONVERTER EM FACTURA — só nas propostas. A factura nasce
                        em RASCUNHO: converter não é emitir, e quem converte
                        confere e emite depois, no editor. */}
                    {aoConverter && (
                        <button
                            type="button"
                            onClick={aoConverter}
                            disabled={aTrabalhar}
                            title={t('Converter em factura')}
                            aria-label={t('Converter :numero em factura', { numero: d.numero })}
                            className={cls('p-2 text-emerald-600 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-emerald-50 disabled:opacity-40', RAIO, FOCO)}
                        >
                            <i className="fas fa-file-circle-plus" aria-hidden="true" />
                        </button>
                    )}

                    {/* O HISTÓRICO DE CONVERSÕES: a mesma proposta pode ser
                        convertida mais do que uma vez (um fornecimento repetido
                        factura-se da mesma proforma), e é isto que evita a
                        duplicação por engano — mostra o que já saiu. */}
                    {aoVerHistorico && (
                        <button
                            type="button"
                            onClick={aoVerHistorico}
                            title={t('Histórico de conversões')}
                            aria-label={t('Histórico de :numero', { numero: d.numero })}
                            className={cls('p-2 text-slate-500 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-slate-100', RAIO, FOCO)}
                        >
                            <i className="fas fa-clock-rotate-left" aria-hidden="true" />
                        </button>
                    )}

                    {/* ELIMINAR. Um documento já convertido ou anulado não se
                        elimina — quem o diz é o servidor, linha a linha. */}
                    {aoApagar && d.pode_apagar && (
                        <button
                            type="button"
                            onClick={aoApagar}
                            disabled={aTrabalhar}
                            title={t('Eliminar')}
                            aria-label={t('Eliminar :numero', { numero: d.numero })}
                            className={cls('p-2 text-red-500 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-red-50 disabled:opacity-40', RAIO, FOCO)}
                        >
                            <i className="fas fa-trash" aria-hidden="true" />
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
    resumo,
    linhas,
    temSaldo,
    aActualizar,
}: {
    resumo?: ResumoDosDocumentos;
    linhas: LinhaDeDocumento[];
    temSaldo: boolean;
    aActualizar: boolean;
}) {
    const porPagar = linhas.reduce((soma, d) => soma + Number(d.saldo ?? 0), 0);
    const nesta = t(':quantos nesta página', { quantos: linhas.length });

    /*
     * OS ESTADOS QUE MAIS PESAM, e não uma lista inventada.
     *
     * A lista em Blade tinha cinco cartões fixos — total, rascunhos, os
     * emitidos, os pagos e o valor — mas os estados não são os mesmos em todos
     * os documentos: uma proforma é «enviada» ou «aceite», uma nota é
     * «emitida». Mostram-se os TRÊS com mais documentos, que é o que responde
     * à mesma pergunta sem inventar estados que este tipo não tem.
     */
    const estados = (resumo?.por_estado ?? []).slice(0, 3);

    return (
        <div className={cls('grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5', aActualizar && 'opacity-70')}>
            <CartaoNumero
                aspecto="claro"
                rotulo={t('Total')}
                tom="indigo"
                icone="fa-file-lines"
                nota={t('com os filtros actuais')}
                valor={resumo === undefined ? '—' : resumo.total.toLocaleString(etiquetaIntl())}
            />

            {estados.map((e) => (
                <CartaoNumero
                    key={e.estado}
                    aspecto="claro"
                    rotulo={e.rotulo}
                    tom={TOM_DO_ESTADO[e.cor] ?? 'cinza'}
                    icone={SINAL_DO_ESTADO[e.cor] ?? 'fa-circle-info'}
                    nota={`${kz(e.valor)} Kz`}
                    valor={e.quantos.toLocaleString(etiquetaIntl())}
                />
            ))}

            <CartaoNumero
                aspecto="claro"
                rotulo={t('Valor Total')}
                tom="verde"
                icone="fa-money-bill-wave"
                sufixo="Kz"
                nota={t('com os filtros actuais')}
                valor={resumo === undefined ? '—' : kz(resumo.valor)}
            />

            {temSaldo && (
                <CartaoNumero
                    aspecto="claro"
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
