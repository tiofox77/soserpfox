import { useEffect, useMemo, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
    produtos,
    type Artigo,
    type ArtigoParaGravar,
    type Escolha,
    type FiltrosDeArtigos,
    type OpcoesDosArtigos,
} from '@/api/produtos';
import { ErroDaApi } from '@/api/cliente';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Modal } from '@/ui/Modal';
import { IntervaloDeDatas, PorPagina } from '@/ui/FiltrosComuns';
import { CARTAO, FOCO, GRADIENTES, RAIO, cls, data, kz } from '@/ui/tokens';
import { etiquetaIntl, t, tPartes } from '@/i18n';

/**
 * OS ARTIGOS.
 *
 * A regra que este ecrã tem de respeitar e que o Livewire aprendeu à sua
 * custa: **o stock não se edita aqui**. É um agregado das linhas de stock, e
 * escrevê-lo numa ficha devolvia o valor que estava no ecrã quando ele abriu —
 * revertendo as vendas que aconteceram entretanto. Por isso a quantidade só
 * aparece na CRIAÇÃO; a editar, o campo nem existe e o servidor ignora-o.
 *
 * A SEGUNDA REGRA, a dos campos de sector: **o perfil da empresa decide o que
 * aparece por omissão, nunca o que existe nem o que se esconde**. Um artigo
 * com receita marcada mostra o campo da receita mesmo que o perfil de farmácia
 * esteja desligado — caso contrário, desligar uma definição de visualização
 * deixava dados gravados sem forma de os ver nem de os corrigir. E quem não
 * tem perfil nenhum ligado chega aos campos por um botão, sem ter de ir às
 * Definições só para marcar um artigo isolado.
 *
 * A TERCEIRA, a das imagens: sobem à parte, em multipart, DEPOIS de o artigo
 * existir. Um ficheiro não viaja em JSON, e ao criar nem há número de artigo
 * antes da resposta do servidor.
 */

const VAZIO: ArtigoParaGravar = {
    name: '',
    type: 'produto',
    description: '',
    code: '',
    sku: '',
    barcode: '',
    price: '',
    cost: '',
    unit: 'un',
    category_id: '',
    brand_id: '',
    supplier_id: '',
    tax_type: 'iva',
    tax_rate_id: '',
    exemption_reason: '',
    manage_stock: true,
    preco_no_pos: false,
    stock_min: '',
    stock_max: '',
    is_active: true,
    stock_quantity: 0,

    track_batches: false,
    track_expiry: false,
    require_batch_on_purchase: false,
    require_batch_on_sale: false,

    requires_prescription: false,
    is_controlled: false,
    active_ingredient: '',
    dosage: '',
    pharmaceutical_form: '',
    armed_registration: '',
    size: '',
    color: '',
    gender: '',
    material: '',
    net_content: '',
    pao_months: '',
    inci_ingredients: '',
    storage_conditions: '',
    allergens: '',
    origin_country: '',
};

/** Formas farmacêuticas sugeridas. Texto livre: a lista real nunca acaba. */
const FORMAS_FARMACEUTICAS = [
    'comprimido',
    'cápsula',
    'xarope',
    'suspensão',
    'injectável',
    'pomada',
    'creme',
    'gotas',
    'supositório',
];

/** As quatro marcas de lote, para o mapa não perder o tipo pelo caminho. */
type ChaveDeLote = 'track_batches' | 'track_expiry' | 'require_batch_on_purchase' | 'require_batch_on_sale';

type Recado = { texto: string; mau: boolean };

/** O que sobe numa gravação: a ficha, e os ficheiros que ainda não têm morada. */
type Envio = { dados: ArtigoParaGravar; destaque: File | null; galeria: File[] };

function preenchido(v: unknown): boolean {
    return v !== null && v !== undefined && String(v).trim() !== '';
}

function rotuloDe(lista: Escolha[] | undefined, valor: string | null): string {
    return lista?.find((e) => e.valor === valor)?.rotulo ?? valor ?? '';
}

export default function Produtos() {
    const cache = useQueryClient();

    const [filtros, porFiltros] = useState<FiltrosDeArtigos>({ procura: '', page: 1 });
    /** O artigo cujo rastreio está aberto. Null é o modal fechado. */
    const [aRastrear, porARastrear] = useState<Artigo | null>(null);
    const [aEditar, porAEditar] = useState<Artigo | null>(null);
    const [formulario, porFormulario] = useState<ArtigoParaGravar | null>(null);
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [aApagar, porAApagar] = useState<Artigo | null>(null);
    const [recado, porRecado] = useState<Recado | null>(null);

    const opcoes = useQuery({
        queryKey: ['produtos', 'opcoes'],
        queryFn: produtos.opcoes,
        staleTime: 5 * 60_000,
    });

    const lista = useQuery({
        queryKey: ['produtos', filtros],
        queryFn: () => produtos.lista(filtros),
        placeholderData: keepPreviousData,
    });

    const gravar = useMutation({
        mutationFn: async ({ dados, destaque, galeria }: Envio) => {
            const resposta = aEditar ? await produtos.guardar(aEditar.id, dados) : await produtos.criar(dados);

            let artigo = resposta.data;
            let avisoDaImagem: string | null = null;

            /*
             * O ARTIGO JÁ ESTÁ GRAVADO. Se uma imagem falhar aqui, o que se
             * perdeu foi a imagem — não a ficha. Rebentar a gravação inteira
             * por causa de um JPEG grande demais era pedir para escrever tudo
             * outra vez.
             */
            try {
                if (destaque) {
                    artigo = (await produtos.imagem(artigo.id, destaque)).data;
                }
                if (galeria.length > 0) {
                    artigo = (await produtos.galeria(artigo.id, galeria)).data;
                }
            } catch (e) {
                avisoDaImagem =
                    e instanceof ErroDaApi
                        ? (e.erros.imagem?.[0] ?? e.erros.imagens?.[0] ?? e.message)
                        : t('Não foi possível enviar a imagem.');
            }

            return { artigo, avisoDaImagem, editava: aEditar !== null };
        },
        onSuccess: ({ avisoDaImagem, editava }) => {
            void cache.invalidateQueries({ queryKey: ['produtos'] });
            porFormulario(null);
            porAEditar(null);
            porErros({});

            const feito = editava ? t('Artigo guardado') : t('Artigo criado');

            porRecado(
                avisoDaImagem
                    ? { texto: t(':feito, mas a imagem ficou por enviar: :aviso', { feito, aviso: avisoDaImagem }), mau: true }
                    : { texto: `${feito}.`, mau: false },
            );
        },
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    const apagar = useMutation({
        mutationFn: (a: Artigo) => produtos.apagar(a.id),
        onSuccess: (r) => {
            void cache.invalidateQueries({ queryKey: ['produtos'] });
            porAApagar(null);
            // O servidor diz o que fez: apagou, ou desactivou porque já foi
            // vendido. São coisas diferentes e a pessoa tem de saber qual.
            porRecado({ texto: r.message, mau: false });
        },
    });

    const permissoes = opcoes.data?.permissoes;

    if (lista.isError) {
        return <Falhou erro={lista.error} />;
    }

    const linhas = lista.data?.data ?? [];
    const contas = lista.data?.meta;

    function abrirNovo() {
        porAEditar(null);
        porErros({});
        porFormulario({ ...VAZIO });
    }

    function abrirEdicao(a: Artigo) {
        porAEditar(a);
        porErros({});
        porFormulario({
            name: a.name,
            type: a.type,
            description: a.description ?? '',
            code: a.code ?? '',
            sku: a.sku ?? '',
            barcode: a.barcode ?? '',
            price: a.price,
            cost: a.cost ?? '',
            unit: a.unit,
            category_id: a.category_id ?? '',
            brand_id: a.brand_id ?? '',
            supplier_id: a.supplier_id ?? '',
            tax_type: a.tax_type,
            tax_rate_id: a.tax_rate_id ?? '',
            exemption_reason: a.exemption_reason ?? '',
            manage_stock: a.manage_stock,
            preco_no_pos: a.preco_no_pos,
            stock_min: a.stock_min ?? '',
            stock_max: a.stock_max ?? '',
            is_active: a.is_active,

            track_batches: a.track_batches,
            track_expiry: a.track_expiry,
            require_batch_on_purchase: a.require_batch_on_purchase,
            require_batch_on_sale: a.require_batch_on_sale,

            requires_prescription: a.requires_prescription,
            is_controlled: a.is_controlled,
            active_ingredient: a.active_ingredient ?? '',
            dosage: a.dosage ?? '',
            pharmaceutical_form: a.pharmaceutical_form ?? '',
            armed_registration: a.armed_registration ?? '',
            size: a.size ?? '',
            color: a.color ?? '',
            gender: a.gender ?? '',
            material: a.material ?? '',
            net_content: a.net_content ?? '',
            pao_months: a.pao_months ?? '',
            inci_ingredients: a.inci_ingredients ?? '',
            storage_conditions: a.storage_conditions ?? '',
            allergens: a.allergens ?? '',
            origin_country: a.origin_country ?? '',
        });
    }

    return (
        <div className="space-y-4">
            {recado && (
                <div
                    role="status"
                    className={cls(
                        'flex items-start justify-between gap-3 border px-4 py-3 text-sm',
                        recado.mau
                            ? 'border-amber-200 bg-amber-50 text-amber-900'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-900',
                        RAIO,
                    )}
                >
                    <span>
                        <i
                            className={cls('mr-2 fas', recado.mau ? 'fa-triangle-exclamation' : 'fa-circle-check')}
                            aria-hidden="true"
                        />
                        {recado.texto}
                    </span>
                    <button type="button" onClick={() => porRecado(null)} aria-label={t('Fechar aviso')}>
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            {/* OS CARTÕES DO TOPO, como o ecrã em Blade tinha.
                A CONTAGEM é a do servidor e conta tudo o que passa nos filtros;
                as outras três são das linhas à vista, e dizem-no no próprio
                cartão. Os totais do catálogo inteiro exigiriam outra pergunta ao
                servidor, e este lote não mexe na API. */}
            <Cartoes total={contas?.total} linhas={linhas} aActualizar={lista.isFetching} />

            <Cartao
                titulo={t('Artigos')}
                icone="fa-box"
                accoes={
                    permissoes?.pode_criar && (
                        <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={abrirNovo}>
                            {t('Novo artigo')}
                        </Botao>
                    )
                }
            >
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="block lg:col-span-2">
                        <Rotulo>{t('Procurar')}</Rotulo>
                        <input
                            type="search"
                            value={filtros.procura ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, procura: e.target.value, page: 1 }))}
                            placeholder={t('Nome, código, SKU ou código de barras')}
                            className={entrada}
                        />
                    </label>

                    <label className="block">
                        <Rotulo>{t('Tipo')}</Rotulo>
                        <select
                            value={filtros.tipo ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, tipo: e.target.value, page: 1 }))}
                            className={entrada}
                        >
                            <option value="">{t('Todos')}</option>
                            <option value="produto">{t('Produto')}</option>
                            <option value="servico">{t('Serviço')}</option>
                        </select>
                    </label>

                    <label className="block">
                        <Rotulo>{t('Categoria')}</Rotulo>
                        <select
                            value={filtros.categoria ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, categoria: e.target.value, page: 1 }))}
                            className={entrada}
                        >
                            <option value="">{t('Todas')}</option>
                            {opcoes.data?.categorias.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </label>

                    {/* O QUE FALTA NA FICHA — o `qualidadeFilter` de sempre.
                        Serve para arrumar o catálogo: um artigo sem preço
                        vende-se a zero no POS e um sem categoria não aparece em
                        filtro nenhum. São erros que só se acham procurando. */}
                    <label className="block">
                        <Rotulo>{t('Ficha incompleta')}</Rotulo>
                        <select
                            value={filtros.qualidade ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, qualidade: e.target.value, page: 1 }))}
                            className={entrada}
                        >
                            <option value="">{t('Todas as fichas')}</option>
                            <option value="sem_preco">{t('Sem preço')}</option>
                            <option value="sem_codigo_barras">{t('Sem código de barras')}</option>
                            <option value="sem_categoria">{t('Sem categoria')}</option>
                        </select>
                    </label>

                    <IntervaloDeDatas
                        de={filtros.de}
                        ate={filtros.ate}
                        aoMudar={(campo, valor) => porFiltros((f) => ({ ...f, [campo]: valor, page: 1 }))}
                    />
                </div>

                <FiltrosDeSector opcoes={opcoes.data} filtros={filtros} aoMudar={porFiltros} />

                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-500">
                        {contas ? t(':quantos artigo(s)', { quantos: contas.total.toLocaleString('pt-PT') }) : t('A contar…')}
                        {lista.isFetching && <span className="ml-2 text-xs">{t('a actualizar…')}</span>}
                    </p>
                    <div className="flex flex-wrap items-center gap-3">
                        <PorPagina
                            valor={filtros.por_pagina}
                            aoMudar={(n) => porFiltros((f) => ({ ...f, por_pagina: n, page: 1 }))}
                        />
                        <Botao
                            altura="pequeno"
                            cor={filtros.so_em_falta === '1' ? 'aviso' : 'neutra'}
                            tom={filtros.so_em_falta === '1' ? 'solida' : 'suave'}
                            icone="fa-triangle-exclamation"
                            onClick={() =>
                                porFiltros((f) => ({
                                    ...f,
                                    so_em_falta: f.so_em_falta === '1' ? '' : '1',
                                    page: 1,
                                }))
                            }
                        >
                            {t('Só os que estão em falta')}
                        </Botao>
                        <Botao
                            altura="pequeno"
                            icone="fa-eraser"
                            onClick={() => porFiltros({ procura: '', page: 1 })}
                        >
                            {t('Limpar')}
                        </Botao>
                    </div>
                </div>
            </Cartao>

            {lista.isPending ? (
                <Carregando />
            ) : linhas.length === 0 ? (
                <EstadoVazio
                    icone="fa-box-open"
                    titulo={t('Nenhum artigo com estes filtros')}
                    frase={t('Alargue a procura ou limpe os filtros para ver mais.')}
                    accao={
                        <Botao icone="fa-eraser" onClick={() => porFiltros({ procura: '', page: 1 })}>
                            {t('Limpar')}
                        </Botao>
                    }
                />
            ) : (
                <Cartao semPadding>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            {/* O cabeçalho de sempre: fundo cinzento claro,
                                maiúsculas pequenas e um ícone por coluna — todos
                                no mesmo tom, que no Blade cada um tinha a sua cor
                                e sete cores num cabeçalho não ajudam a encontrar
                                nada. */}
                            <thead className="bg-slate-50">
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-600">
                                    <Cabecalho icone="fa-box">{t('Artigo')}</Cabecalho>
                                    <Cabecalho icone="fa-folder">{t('Categoria')}</Cabecalho>
                                    <Cabecalho icone="fa-percent">{t('Imposto')}</Cabecalho>
                                    <Cabecalho icone="fa-money-bill" direita>{t('Preço')}</Cabecalho>
                                    <Cabecalho icone="fa-warehouse" direita>{t('Stock')}</Cabecalho>
                                    <Cabecalho icone="fa-toggle-on">{t('Estado')}</Cabecalho>
                                    <Cabecalho icone="fa-gear" direita>{t('Acções')}</Cabecalho>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {linhas.map((a, i) => (
                                    <tr
                                        key={a.id}
                                        className="entra transition-all duration-200 hover:bg-indigo-50/60"
                                        style={cascata(i)}
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-start gap-3">
                                                {/* A MEDALHA DO ARTIGO. O ecrã em Blade
                                                    punha aqui um círculo com as duas
                                                    primeiras letras; se houver imagem,
                                                    é a imagem que vai — é o que quem
                                                    procura no catálogo reconhece
                                                    primeiro. Fica fora da árvore de
                                                    acessibilidade: o nome do artigo
                                                    está já ao lado, escrito. */}
                                                <Medalha nome={a.name} imagem={a.imagem} />
                                                <div className="min-w-0">
                                                    <div className="font-semibold text-slate-800">
                                                        {a.name}
                                                        {preenchido(a.net_content) && (
                                                            <span className="ml-1 font-semibold text-slate-400">
                                                                · {a.net_content}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <CrachasDeSector artigo={a} opcoes={opcoes.data} />
                                                    <div className="text-xs text-slate-400">
                                                        {[a.code, a.sku].filter(Boolean).join(' · ') || '—'}
                                                        {a.type === 'servico' && ` · ${t('serviço')}`}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {a.category?.name ?? <span className="text-slate-300">—</span>}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {a.tax_type === 'isento' ? (
                                                <span className="text-xs">{t('Isento · :motivo', { motivo: a.exemption_reason ?? '—' })}</span>
                                            ) : (
                                                <span className="tabular-nums">{a.taxa ?? '—'}%</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right font-bold tabular-nums text-slate-900">
                                            {kz(a.price)}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {/* Um serviço não tem stock. Mostrar «0» seria mentira.
                                                O crachá com ícone é o do ecrã de sempre: esgotado,
                                                abaixo do mínimo e em ordem distinguem-se pelo
                                                desenho e não só pela cor. */}
                                            {a.stock === null ? (
                                                <span className="text-slate-300">—</span>
                                            ) : (
                                                <Etiqueta
                                                    cor={a.esgotado ? 'perigo' : a.em_falta ? 'aviso' : 'bom'}
                                                    icone={
                                                        a.esgotado
                                                            ? 'fa-circle-xmark'
                                                            : a.em_falta
                                                              ? 'fa-triangle-exclamation'
                                                              : 'fa-circle-check'
                                                    }
                                                >
                                                    <span className="tabular-nums">
                                                        {kz(a.stock, 0)}
                                                        {a.stock_min ? (
                                                            <span className="ml-0.5 font-normal opacity-70">
                                                                /{a.stock_min}
                                                            </span>
                                                        ) : null}
                                                    </span>
                                                </Etiqueta>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {a.is_active ? (
                                                <Etiqueta cor="bom" ponto>{t('Activo')}</Etiqueta>
                                            ) : (
                                                <Etiqueta cor="neutra" ponto>{t('Desactivado')}</Etiqueta>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                {/* PARA ONDE FOI ESTE ARTIGO. O botão que o
                                                    ecrã de sempre tinha em cada linha: junta as
                                                    vendas aos movimentos de stock, e é a
                                                    diferença entre os dois que denuncia a baixa
                                                    que falhou. Basta a permissão de VER — é uma
                                                    consulta, não mexe em nada. */}
                                                <button
                                                    type="button"
                                                    onClick={() => porARastrear(a)}
                                                    title={t('Rastreio')}
                                                    aria-label={t('Rastrear :nome', { nome: a.name })}
                                                    className={cls('p-2 text-teal-600 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-teal-50', RAIO, FOCO)}
                                                >
                                                    <i className="fas fa-timeline" aria-hidden="true" />
                                                </button>

                                                {permissoes?.pode_editar && (
                                                    <button
                                                        type="button"
                                                        onClick={() => abrirEdicao(a)}
                                                        title={t('Editar')}
                                                        aria-label={t('Editar :nome', { nome: a.name })}
                                                        className={cls('p-2 text-slate-500 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-slate-100', RAIO, FOCO)}
                                                    >
                                                        <i className="fas fa-pen" aria-hidden="true" />
                                                    </button>
                                                )}
                                                {permissoes?.pode_apagar && (
                                                    <button
                                                        type="button"
                                                        onClick={() => porAApagar(a)}
                                                        title={t('Apagar')}
                                                        aria-label={t('Apagar :nome', { nome: a.name })}
                                                        className={cls('p-2 text-red-500 transition-all duration-200 hover:scale-110 active:scale-100 hover:bg-red-50', RAIO, FOCO)}
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
                        {t('Página :actual de :total', { actual: contas.current_page, total: contas.last_page })}
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

            <Rastreio artigo={aRastrear} aoFechar={() => porARastrear(null)} />

            <Formulario
                dados={formulario}
                aEditar={aEditar}
                erros={erros}
                aGravar={gravar.isPending}
                erroDeGravar={gravar.error}
                opcoes={opcoes.data}
                aoMudar={porFormulario}
                aoFechar={() => {
                    porFormulario(null);
                    porAEditar(null);
                    porErros({});
                }}
                aoGravar={(envio) => gravar.mutate(envio)}
            />

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar artigo')}
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
                            {t('Apagar')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {tPartes('Apagar :nome?', { nome: <strong>{aApagar?.name}</strong> })}
                </p>
                {/* Um artigo já vendido não desaparece: fica desactivado, porque
                    a linha da factura aponta para ele. Dizer isto ANTES evita a
                    surpresa de carregar em «Apagar» e ver o artigo continuar lá. */}
                <p className="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    {tPartes('Se já tiver sido vendido, é :estado em vez de apagado — os documentos antigos apontam para ele e não podem ficar sem artigo.', { estado: <strong>{t('desactivado')}</strong> })}
                </p>
                {apagar.isError && (
                    <p className="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-800">
                        {apagar.error instanceof ErroDaApi ? apagar.error.message : t('Não foi possível apagar.')}
                    </p>
                )}
            </Modal>
        </div>
    );
}

/* ─── O aspecto da lista ──────────────────────────────────────────────── */

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
 * A MEDALHA DO ARTIGO: a imagem se houver, as duas primeiras letras se não.
 *
 * Vem do ecrã em Blade, que punha aqui um círculo com iniciais em gradiente.
 * Toda ela `aria-hidden`: quem ouve o ecrã já tem o nome do artigo escrito ao
 * lado, e «PA» lido em voz alta não acrescenta nada.
 */
function Medalha({ nome, imagem }: { nome: string; imagem?: string | null }) {
    if (imagem) {
        return (
            <img
                src={imagem}
                alt=""
                aria-hidden="true"
                className="h-10 w-10 flex-none rounded-full border border-slate-200 object-cover shadow-sm"
            />
        );
    }

    return (
        <span
            aria-hidden="true"
            className={cls(
                'grid h-10 w-10 flex-none place-items-center rounded-full text-xs font-bold text-white shadow-sm',
                GRADIENTES.primaria,
            )}
        >
            {nome.slice(0, 2).toUpperCase()}
        </span>
    );
}

/**
 * O ESTADO VAZIO COM DESENHO — o círculo de 80px com o ícone lá dentro, como o
 * ecrã em Blade tinha. Uma linha de texto solta numa caixa branca lê-se como um
 * erro de carregamento; isto lê-se como uma resposta, e diz o que fazer a
 * seguir.
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
 * O ecrã em Blade tinha três (total, valor médio, serviços) e a migração
 * deixou a página a começar por uma caixa branca de filtros. Voltam com o
 * mesmo gradiente, pelo `CartaoNumero`.
 *
 * A COR SEGUE O SIGNIFICADO: o que está em falta é âmbar, e leva ícone — cor
 * sozinha não chega a quem não a distingue.
 */
function Cartoes({
    total,
    linhas,
    aActualizar,
}: {
    total?: number;
    linhas: Artigo[];
    aActualizar: boolean;
}) {
    const comPreco = linhas.filter((a) => Number(a.price) > 0);
    const medio = comPreco.length > 0 ? comPreco.reduce((s, a) => s + Number(a.price), 0) / comPreco.length : 0;
    const servicos = linhas.filter((a) => a.type === 'servico').length;
    const emFalta = linhas.filter((a) => a.esgotado || a.em_falta).length;
    const nesta = t(':quantos nesta página', { quantos: linhas.length });

    return (
        <div className={cls('grid gap-3 sm:grid-cols-2 lg:grid-cols-4', aActualizar && 'opacity-70')}>
            <CartaoNumero
                rotulo={t('Artigos')}
                tom="indigo"
                icone="fa-box"
                nota={t('com os filtros actuais')}
                valor={total === undefined ? <span className="text-white/50">—</span> : total.toLocaleString(etiquetaIntl())}
            />
            <CartaoNumero
                rotulo={t('Preço médio nesta página')}
                tom="verde"
                icone="fa-money-bill-wave"
                sufixo="Kz"
                nota={nesta}
                valor={kz(medio)}
            />
            <CartaoNumero
                rotulo={t('Serviços nesta página')}
                tom="azul"
                icone="fa-bell-concierge"
                nota={nesta}
                valor={servicos.toLocaleString(etiquetaIntl())}
            />
            <CartaoNumero
                rotulo={t('Em falta nesta página')}
                tom={emFalta > 0 ? 'ambar' : 'cinza'}
                icone="fa-triangle-exclamation"
                nota={nesta}
                valor={emFalta.toLocaleString(etiquetaIntl())}
            />
        </div>
    );
}

/* ─── Os filtros de sector ────────────────────────────────────────────── */

/**
 * Receita, tamanho, cor e conservação.
 *
 * Aparecem a quem diz trabalhar com isto nas Definições **OU** a quem já tem
 * artigos assim marcados. A segunda metade não é decoração: sem ela, desligar
 * o perfil deixava dados gravados sem forma nenhuma de os filtrar. Numa
 * oficina — sem perfil e sem dados — continuam escondidos, que é o que se
 * pretende.
 */
function FiltrosDeSector({
    opcoes,
    filtros,
    aoMudar,
}: {
    opcoes?: OpcoesDosArtigos;
    filtros: FiltrosDeArtigos;
    aoMudar: (f: (anterior: FiltrosDeArtigos) => FiltrosDeArtigos) => void;
}) {
    if (!opcoes) {
        return null;
    }

    const perfis = new Set(opcoes.perfis);
    const tamanhos = opcoes.variantes.tamanhos;
    const cores = opcoes.variantes.cores;

    const mostraReceita = perfis.has('farmacia') || opcoes.variantes.ha_receituario;
    const mostraTamanho = perfis.has('vestuario') || tamanhos.length > 0;
    const mostraCor = perfis.has('vestuario') || cores.length > 0;
    // Não depende de haver artigos de cada tipo: a lista é fechada, por isso o
    // select nunca fica vazio a parecer avariado.
    const mostraConservacao = perfis.has('mercearia') || opcoes.variantes.ha_conservacao;

    if (!mostraReceita && !mostraTamanho && !mostraCor && !mostraConservacao) {
        return null;
    }

    const mudar = (chave: keyof FiltrosDeArtigos, valor: string) =>
        aoMudar((f) => ({ ...f, [chave]: valor, page: 1 }));

    return (
        <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {mostraReceita && (
                <label className="block">
                    <Rotulo>{t('Receita')}</Rotulo>
                    <select
                        value={filtros.prescricao ?? ''}
                        onChange={(e) => mudar('prescricao', e.target.value)}
                        className={entrada}
                    >
                        <option value="">{t('Todos')}</option>
                        <option value="sim">{t('Exige receita')}</option>
                        <option value="nao">{t('Venda livre')}</option>
                    </select>
                </label>
            )}

            {mostraTamanho && (
                <label className="block">
                    <Rotulo>{t('Tamanho')}</Rotulo>
                    {/* Select e não caixa de texto: ninguém se lembra de como
                        escreveu o tamanho da última vez. Com o perfil acabado
                        de ligar ainda não há nada para escolher — em vez de um
                        select vazio que parece avariado, diz-se porquê. */}
                    <select
                        value={filtros.tamanho ?? ''}
                        disabled={tamanhos.length === 0}
                        onChange={(e) => mudar('tamanho', e.target.value)}
                        className={cls(entrada, 'disabled:bg-slate-100 disabled:text-slate-400')}
                    >
                        <option value="">{tamanhos.length > 0 ? t('Todos') : t('Ainda sem tamanhos registados')}</option>
                        {tamanhos.map((x) => (
                            <option key={x} value={x}>
                                {x}
                            </option>
                        ))}
                    </select>
                </label>
            )}

            {mostraCor && (
                <label className="block">
                    <Rotulo>{t('Cor')}</Rotulo>
                    <select
                        value={filtros.cor ?? ''}
                        disabled={cores.length === 0}
                        onChange={(e) => mudar('cor', e.target.value)}
                        className={cls(entrada, 'disabled:bg-slate-100 disabled:text-slate-400')}
                    >
                        <option value="">{cores.length > 0 ? t('Todas') : t('Ainda sem cores registadas')}</option>
                        {cores.map((c) => (
                            <option key={c} value={c}>
                                {c}
                            </option>
                        ))}
                    </select>
                </label>
            )}

            {mostraConservacao && (
                <label className="block">
                    <Rotulo>{t('Conservação')}</Rotulo>
                    {/* A pergunta do armazém, não a da ficha: «o que é que vai
                        para o frigorífico?» é o que se pergunta antes de
                        arrumar uma entrada de mercadoria. */}
                    <select
                        value={filtros.conservacao ?? ''}
                        onChange={(e) => mudar('conservacao', e.target.value)}
                        className={entrada}
                    >
                        <option value="">{t('Todas')}</option>
                        {opcoes.conservacao.map((c) => (
                            <option key={c.valor} value={c.valor}>
                                {c.rotulo}
                            </option>
                        ))}
                    </select>
                </label>
            )}
        </div>
    );
}

/* ─── Os crachás da lista ─────────────────────────────────────────────── */

/**
 * Receita, controlado, tamanho, cor, conservação e alergénios, junto ao nome.
 *
 * São o que distingue duas linhas com a mesma designação (a mesma t-shirt em M
 * e em L) e o que o balcão e o armazém têm de ver antes de dispensar ou de
 * arrumar.
 *
 * O PERFIL DA EMPRESA NÃO ENTRA AQUI, de propósito: o crachá só existe porque
 * o artigo tem o dado, e um crachá «Receita» nunca deve desaparecer por se ter
 * desligado uma definição de visualização. Ligar o perfil também não os
 * inventa — não há crachá sem valor por trás.
 */
function CrachasDeSector({ artigo, opcoes }: { artigo: Artigo; opcoes?: OpcoesDosArtigos }) {
    const tem =
        artigo.requires_prescription ||
        artigo.is_controlled ||
        preenchido(artigo.size) ||
        preenchido(artigo.color) ||
        preenchido(artigo.storage_conditions) ||
        preenchido(artigo.allergens);

    if (!tem) {
        return null;
    }

    return (
        <div className="mt-1 flex flex-wrap items-center gap-1">
            {artigo.requires_prescription && (
                <Etiqueta cor="perigo" icone="fa-file-prescription">
                    {t('Receita')}
                </Etiqueta>
            )}
            {artigo.is_controlled && (
                <Etiqueta cor="aviso" icone="fa-triangle-exclamation">
                    {t('Controlado')}
                </Etiqueta>
            )}
            {preenchido(artigo.size) && <Etiqueta cor="neutra">{artigo.size}</Etiqueta>}
            {preenchido(artigo.color) && <Etiqueta cor="neutra">{artigo.color}</Etiqueta>}
            {preenchido(artigo.storage_conditions) && (
                <Etiqueta cor="primaria" icone="fa-temperature-half">
                    {rotuloDe(opcoes?.conservacao, artigo.storage_conditions)}
                </Etiqueta>
            )}
            {preenchido(artigo.allergens) && (
                <Etiqueta cor="aviso" icone="fa-wheat-awn-circle-exclamation">
                    {t('Alergénios')}
                </Etiqueta>
            )}
        </div>
    );
}

/* ─── O formulário ────────────────────────────────────────────────────── */

function Formulario({
    dados,
    aEditar,
    erros,
    aGravar,
    erroDeGravar,
    opcoes,
    aoMudar,
    aoFechar,
    aoGravar,
}: {
    dados: ArtigoParaGravar | null;
    aEditar: Artigo | null;
    erros: Record<string, string[]>;
    aGravar: boolean;
    erroDeGravar: unknown;
    opcoes?: OpcoesDosArtigos;
    aoMudar: (d: ArtigoParaGravar) => void;
    aoFechar: () => void;
    aoGravar: (envio: Envio) => void;
}) {
    // Os ficheiros escolhidos e ainda por enviar. Vivem cá porque só sobem
    // depois de o artigo estar gravado — a criar, nem número tem antes disso.
    const [destaque, porDestaque] = useState<File | null>(null);
    const [galeria, porGaleria] = useState<File[]>([]);

    const chaveDaFicha = aEditar?.id ?? 'novo';

    // Fechar a janela deita fora o que ainda não subiu: deixá-lo pendurado
    // fazia o ficheiro reaparecer no artigo SEGUINTE que se abrisse.
    useEffect(() => {
        porDestaque(null);
        porGaleria([]);
    }, [chaveDaFicha, dados === null]);

    if (!dados) {
        return null;
    }

    const campo = <K extends keyof ArtigoParaGravar>(chave: K, valor: ArtigoParaGravar[K]) =>
        aoMudar({ ...dados, [chave]: valor });

    const eServico = dados.type === 'servico';

    /*
     * A EMPRESA AINDA NÃO TEM TAXAS DE IVA.
     *
     * Sem isto ficava um select vazio e ninguém percebia porquê: o formulário
     * exige a taxa, a lista não tem nenhuma, e o artigo não se gravava sem
     * explicação. Só se decide depois de as opções chegarem — enquanto elas
     * vêm a caminho, a lista está vazia por estar a carregar, e não por falta.
     */
    const semTaxas = !!opcoes && opcoes.taxas.length === 0;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={aEditar ? t('Editar :nome', { nome: aEditar.name }) : t('Novo artigo')}
            largura="lg"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao
                        cor="primaria"
                        tom="solida"
                        icone="fa-check"
                        aTrabalhar={aGravar}
                        onClick={() => aoGravar({ dados, destaque, galeria })}
                    >
                        {t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={erroDeGravar} />

            <form
                className="grid gap-4 sm:grid-cols-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    aoGravar({ dados, destaque, galeria });
                }}
            >
                <Campo etiqueta={t('Nome')} erro={erros.name} obrigatorio className="sm:col-span-2">
                    <input value={dados.name} onChange={(e) => campo('name', e.target.value)} className={entrada} />
                </Campo>

                <Campo etiqueta={t('Tipo')} erro={erros.type} obrigatorio>
                    <select
                        value={dados.type}
                        onChange={(e) => {
                            const tp = e.target.value as ArtigoParaGravar['type'];
                            // Um serviço não gere stock. Muda-se aqui para o
                            // ecrã dizer a verdade antes de o servidor a impor.
                            //
                            // A EDITAR não se toca: quem manda é o que está
                            // gravado, e trocar o tipo não pode apagar uma
                            // decisão que o utilizador já tomou.
                            aoMudar(aEditar ? { ...dados, type: tp } : { ...dados, type: tp, manage_stock: tp === 'produto' });
                        }}
                        className={entrada}
                    >
                        <option value="produto">{t('Produto')}</option>
                        <option value="servico">{t('Serviço')}</option>
                    </select>
                </Campo>

                <Campo etiqueta={t('Categoria')} erro={erros.category_id} obrigatorio>
                    <select
                        value={String(dados.category_id ?? '')}
                        onChange={(e) => campo('category_id', e.target.value)}
                        className={entrada}
                    >
                        <option value="">{t('Escolher…')}</option>
                        {opcoes?.categorias.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                </Campo>

                {/* A MARCA E O FORNECEDOR ficam ao pé da categoria: são as
                    três perguntas de arrumação do artigo — onde entra, de quem
                    é, a quem se compra — e nenhuma delas é obrigatória. */}
                <Campo etiqueta={t('Marca')} erro={erros.brand_id}>
                    <select
                        value={String(dados.brand_id ?? '')}
                        onChange={(e) => campo('brand_id', e.target.value)}
                        className={entrada}
                    >
                        <option value="">{t('Nenhuma')}</option>
                        {opcoes?.marcas.map((m) => (
                            <option key={m.id} value={m.id}>
                                {m.name}
                            </option>
                        ))}
                    </select>
                </Campo>

                <Campo etiqueta={t('Fornecedor')} erro={erros.supplier_id}>
                    <select
                        value={String(dados.supplier_id ?? '')}
                        onChange={(e) => campo('supplier_id', e.target.value)}
                        className={entrada}
                    >
                        <option value="">{t('Nenhum')}</option>
                        {opcoes?.fornecedores.map((f) => (
                            <option key={f.id} value={f.id}>
                                {f.name}
                            </option>
                        ))}
                    </select>
                </Campo>

                <Campo etiqueta={t('Unidade')} erro={erros.unit} obrigatorio>
                    <select value={dados.unit} onChange={(e) => campo('unit', e.target.value)} className={entrada}>
                        {opcoes?.unidades.map((u) => (
                            <option key={u} value={u}>
                                {u}
                            </option>
                        ))}
                    </select>
                </Campo>

                {/* O CÓDIGO DO ARTIGO. Gerado automaticamente e editável, como
                    sempre foi: quem já tem códigos no armazém escreve o seu, e
                    quem não tem deixa em branco e recebe um `PROD000001`. A
                    migração para React tinha-o deixado só na lista, sem
                    caminho para o mudar. */}
                <Campo
                    etiqueta={t('Código')}
                    erro={erros.code}
                    ajuda={
                        aEditar
                            ? t('Único nesta empresa.')
                            : t('Em branco, é gerado automaticamente.')
                    }
                >
                    <input
                        value={dados.code ?? ''}
                        onChange={(e) => campo('code', e.target.value)}
                        placeholder={aEditar ? '' : t('Ex.: PROD000001')}
                        className={cls(entrada, 'font-mono font-semibold')}
                    />
                </Campo>

                <Campo etiqueta="SKU" erro={erros.sku}>
                    <input value={dados.sku ?? ''} onChange={(e) => campo('sku', e.target.value)} className={entrada} />
                </Campo>

                <Campo etiqueta={t('Preço')} erro={erros.price} obrigatorio>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={dados.price}
                        onChange={(e) => campo('price', e.target.value)}
                        className={cls(entrada, 'text-right tabular-nums')}
                    />
                </Campo>

                <Campo etiqueta={t('Custo')} erro={erros.cost}>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={dados.cost ?? ''}
                        onChange={(e) => campo('cost', e.target.value)}
                        className={cls(entrada, 'text-right tabular-nums')}
                    />
                </Campo>

                <Campo etiqueta={t('Código de barras')} erro={erros.barcode}>
                    <input
                        value={dados.barcode ?? ''}
                        onChange={(e) => campo('barcode', e.target.value)}
                        className={entrada}
                    />
                </Campo>

                {/* O imposto sai do catálogo da empresa, nunca escrito à mão:
                    o regime fiscal já afinou as taxas. */}
                <Campo etiqueta={t('Imposto')} erro={erros.tax_type} obrigatorio>
                    <select
                        value={dados.tax_type}
                        onChange={(e) => campo('tax_type', e.target.value as ArtigoParaGravar['tax_type'])}
                        className={entrada}
                    >
                        <option value="iva">IVA</option>
                        <option value="isento">{t('Isento')}</option>
                    </select>
                </Campo>

                {dados.tax_type === 'iva' && semTaxas ? (
                    <div
                        role="alert"
                        className={cls('sm:col-span-2 border border-amber-300 bg-amber-50 p-4', RAIO)}
                    >
                        <p className="text-sm font-semibold text-amber-900">
                            <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                            {t('Nenhuma taxa de IVA cadastrada')}
                        </p>
                        <p className="mt-1 text-xs text-amber-800">
                            {t('Por favor, cadastre as taxas primeiro em:')}
                        </p>
                        <a
                            href="/invoicing/taxes"
                            target="_blank"
                            rel="noreferrer"
                            className={cls(
                                'mt-2 inline-flex items-center gap-2 bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700',
                                RAIO,
                                FOCO,
                            )}
                        >
                            <i className="fas fa-up-right-from-square" aria-hidden="true" />
                            {t('Ir para Taxas de IVA')}
                        </a>
                    </div>
                ) : dados.tax_type === 'iva' ? (
                    <Campo etiqueta={t('Taxa')} erro={erros.tax_rate_id} obrigatorio className="sm:col-span-2">
                        <select
                            value={String(dados.tax_rate_id ?? '')}
                            onChange={(e) => campo('tax_rate_id', e.target.value)}
                            className={entrada}
                        >
                            <option value="">{t('Escolher…')}</option>
                            {opcoes?.taxas.map((x) => (
                                <option key={x.id} value={x.id}>
                                    {x.name} — {x.rate}%
                                </option>
                            ))}
                        </select>
                    </Campo>
                ) : (
                    <Campo
                        etiqueta={t('Motivo da isenção')}
                        erro={erros.exemption_reason}
                        obrigatorio
                        className="sm:col-span-2"
                    >
                        <input
                            value={dados.exemption_reason ?? ''}
                            onChange={(e) => campo('exemption_reason', e.target.value)}
                            placeholder={t('Ex.: M99')}
                            className={entrada}
                        />
                    </Campo>
                )}

                {!eServico && (
                    <section className={cls('sm:col-span-3 border border-slate-200 bg-slate-50 p-4', RAIO)}>
                        {/* GERENCIAR STOCK — a caixa que o ecrã em Blade tinha à
                            cabeça deste bloco, e que abre ou fecha o resto.

                            Nasce LIGADO: a caixa desmarcada por omissão deixou
                            farmácias inteiras com artigos que se vendiam e nunca
                            desciam, e o sintoma só aparecia semanas depois, com
                            as contagens já fora. Mas há artigos que de facto não
                            se contam (a taxa de entrega, o serviço facturado como
                            produto), e sem este interruptor não havia como o
                            dizer — o ecrã em React ligava-o pelo tipo e mais
                            nada. Desligado, o POS deixa de esconder o artigo
                            quando o stock está a zero. */}
                        <label className="flex items-start gap-3">
                            <input
                                type="checkbox"
                                checked={dados.manage_stock}
                                onChange={(e) => campo('manage_stock', e.target.checked)}
                                className="mt-0.5 h-5 w-5 rounded border-slate-300 text-indigo-600 transition-transform duration-150 hover:scale-110"
                            />
                            <span>
                                <span className="text-sm font-bold text-slate-800">
                                    <i className="fas fa-warehouse mr-2 text-slate-400" aria-hidden="true" />
                                    {t('Gerenciar Stock')}
                                </span>
                                <span className="mt-0.5 block text-xs text-slate-500">
                                    {t('Desligado, o artigo vende-se sem descontar e não se esconde do POS a zero.')}
                                </span>
                            </span>
                        </label>

                        {dados.manage_stock && (
                    <div className="animate-fade-in mt-4 grid gap-4 sm:grid-cols-3">
                        <Campo etiqueta={t('Stock mínimo')} erro={erros.stock_min}>
                            <input
                                type="number"
                                min="0"
                                value={dados.stock_min ?? ''}
                                onChange={(e) => campo('stock_min', e.target.value)}
                                className={cls(entrada, 'text-right tabular-nums')}
                            />
                        </Campo>

                        <Campo etiqueta={t('Stock máximo')} erro={erros.stock_max}>
                            <input
                                type="number"
                                min="0"
                                value={dados.stock_max ?? ''}
                                onChange={(e) => campo('stock_max', e.target.value)}
                                className={cls(entrada, 'text-right tabular-nums')}
                            />
                        </Campo>

                        {/* A QUANTIDADE SÓ EXISTE AO CRIAR.
                            A editar, o stock é o que as linhas dizem — mexer nele
                            aqui revertia vendas feitas entretanto. Ajusta-se na
                            Gestão de Stock, com movimento registado. */}
                        {aEditar ? (
                            <div className="sm:col-span-1">
                                <Rotulo>{t('Stock actual')}</Rotulo>
                                <p className="flex h-10 items-center rounded-xl bg-slate-50 px-3 text-sm tabular-nums text-slate-600">
                                    {aEditar.stock ?? '—'}
                                    <span className="ml-2 text-xs text-slate-400">
                                        {t('ajusta-se na Gestão de Stock')}
                                    </span>
                                </p>
                            </div>
                        ) : (
                            <Campo etiqueta={t('Quantidade inicial')} erro={erros.stock_quantity}>
                                <input
                                    type="number"
                                    min="0"
                                    value={dados.stock_quantity ?? 0}
                                    onChange={(e) => campo('stock_quantity', e.target.value)}
                                    className={cls(entrada, 'text-right tabular-nums')}
                                />
                            </Campo>
                        )}
                    </div>
                        )}

                        {/* OS LOTES FICAM SEMPRE À VISTA, mesmo com o agregado
                            desligado: o `track_batches` é o que faz o artigo
                            descontar lote a lote, e escondê-lo atrás do
                            interruptor que ele dispensa deixava-o inalcançável. */}
                        <div className="mt-4">
                            <LotesEValidade dados={dados} aoMudar={aoMudar} />
                        </div>
                    </section>
                )}

                <Campo etiqueta={t('Descrição')} erro={erros.description} className="sm:col-span-3">
                    <textarea
                        rows={2}
                        value={dados.description ?? ''}
                        onChange={(e) => campo('description', e.target.value)}
                        className={cls(entrada, 'h-auto py-2')}
                    />
                </Campo>

                {/* A chave inclui os perfis: eles chegam do servidor e podem
                    chegar DEPOIS da janela abrir. Sem isso, a secção nascia
                    fechada e ficava assim até se fechar tudo e abrir de novo. */}
                <SeccaoDeSector
                    key={`${chaveDaFicha}-${opcoes?.perfis.join(',') ?? ''}`}
                    dados={dados}
                    erros={erros}
                    opcoes={opcoes}
                    aoMudar={aoMudar}
                />

                <Imagens
                    key={`imagens-${chaveDaFicha}`}
                    artigo={aEditar}
                    destaque={destaque}
                    galeria={galeria}
                    aoEscolherDestaque={porDestaque}
                    aoJuntarAGaleria={(fs) => porGaleria((g) => [...g, ...fs])}
                    aoTirarDaGaleria={(i) => porGaleria((g) => g.filter((_, n) => n !== i))}
                />

                {/*
                    PERGUNTAR O PREÇO NO POS.

                    Para trabalhos à medida — uma reparação, um bolo por
                    encomenda: o preço só se sabe na hora. Isto é do BALCÃO; na
                    factura de venda o preço escreve-se na linha, como sempre.
                */}
                <label className="flex items-start gap-2 text-sm text-slate-700 sm:col-span-3">
                    <input
                        type="checkbox"
                        checked={dados.preco_no_pos}
                        onChange={(e) => campo('preco_no_pos', e.target.checked)}
                        className="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600"
                    />
                    <span>
                        {t('Perguntar o preço no POS')}
                        <span className="mt-0.5 block text-xs text-slate-500">
                            {t('Para trabalhos à medida: no POS o preço é escrito na hora da venda. Na factura de venda escreve-se na linha, como sempre.')}
                        </span>
                    </span>
                </label>

                <label className="flex items-center gap-2 text-sm text-slate-700 sm:col-span-3">
                    <input
                        type="checkbox"
                        checked={dados.is_active}
                        onChange={(e) => campo('is_active', e.target.checked)}
                        className="h-4 w-4 rounded border-slate-300 text-indigo-600"
                    />
                    {t('Activo — aparece no POS e nas listas')}
                </label>
            </form>
        </Modal>
    );
}

/* ─── O rastreio: para onde foi este artigo ───────────────────────────── */

/**
 * AS VENDAS E OS MOVIMENTOS DE STOCK, LADO A LADO.
 *
 * As duas listas juntas de propósito — é essa a razão de este ecrã existir. As
 * vendas dizem para quem foi e por quanto; os movimentos dizem de que armazém
 * saiu e por que documento. É a DISCREPÂNCIA entre elas que denuncia problemas:
 * vendeu-se sem saída de stock (o artigo aparece disponível mas a baixa falha),
 * saiu sem venda, ajustou-se à mão sem justificação.
 *
 * Existia num botão por linha no ecrã em Blade e a migração deixou-o para trás
 * por inteiro — nem ecrã, nem API, nem contas.
 */
function Rastreio({ artigo, aoFechar }: { artigo: Artigo | null; aoFechar: () => void }) {
    const [dias, porDias] = useState(90);

    const q = useQuery({
        queryKey: ['artigos', 'rastreio', artigo?.id, dias],
        queryFn: () => produtos.rastreio(artigo!.id, dias),
        enabled: artigo !== null,
        placeholderData: keepPreviousData,
    });

    if (!artigo) {
        return null;
    }

    const r = q.data;
    // Milésimos porque as quantidades podem sê-lo (0,250 kg) — e uma
    // divergência de três milésimos ainda é uma divergência.
    const divergente = r ? Math.abs(r.resumo.divergencia) > 0.001 : false;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            largura="xl"
            icone="fa-timeline"
            cor="primaria"
            titulo={artigo.name}
            subtitulo={[artigo.code, t('rastreio de vendas e stock')].filter(Boolean).join(' · ')}
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <label className="flex items-center gap-2 text-sm text-slate-600">
                    <i className="fas fa-calendar-day text-slate-400" aria-hidden="true" />
                    {t('Período')}
                    <select
                        value={dias}
                        onChange={(e) => porDias(Number(e.target.value))}
                        aria-label={t('Período do rastreio')}
                        className="h-9 rounded-lg border border-slate-300 px-2 text-sm transition-colors hover:border-indigo-400"
                    >
                        <option value={30}>{t('30 dias')}</option>
                        <option value={90}>{t('90 dias')}</option>
                        <option value={365}>{t('1 ano')}</option>
                        <option value={0}>{t('Tudo')}</option>
                    </select>
                </label>
                {q.isFetching && <span className="text-xs text-slate-400">{t('a actualizar…')}</span>}
            </div>

            {q.isPending || !r ? (
                <Carregando />
            ) : (
                <div className="space-y-5">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <CartaoNumero
                            rotulo={t('Vendido')}
                            tom="azul"
                            icone="fa-cart-shopping"
                            valor={numero(r.resumo.qtd_vendida)}
                            nota={t(':quantos documento(s)', { quantos: r.resumo.documentos })}
                        />
                        <CartaoNumero
                            rotulo={t('Faturado')}
                            tom="verde"
                            icone="fa-coins"
                            valor={kz(r.resumo.valor_vendido)}
                            nota="Kz"
                        />
                        <CartaoNumero
                            rotulo={t('Stock actual')}
                            tom="indigo"
                            icone="fa-warehouse"
                            valor={numero(r.resumo.stock_total)}
                            nota={t(':quantos armazém(ns)', { quantos: r.por_armazem.length })}
                        />
                        {/* O CARTÃO QUE JUSTIFICA O ECRÃ. Vermelho só quando há
                            mesmo divergência: pintá-lo sempre ensinava a
                            ignorá-lo. */}
                        <CartaoNumero
                            rotulo={t('Vendido − saídas')}
                            tom={divergente ? 'vermelho' : 'cinza'}
                            icone={divergente ? 'fa-triangle-exclamation' : 'fa-circle-check'}
                            valor={numero(r.resumo.divergencia)}
                            nota={divergente ? t('stock não acompanhou a venda') : t('coerente')}
                        />
                    </div>

                    {divergente && (
                        <div
                            role="alert"
                            className={cls('animate-fade-in border-2 border-red-300 bg-red-50 p-3 text-sm text-red-800', RAIO)}
                        >
                            <strong className="block">
                                <i className="fas fa-triangle-exclamation mr-1.5" aria-hidden="true" />
                                {t('Vendas e stock não batem certo.')}
                            </strong>
                            {t(
                                'Foram vendidas :vendidas unidades mas só saíram :saidas do stock. É o sintoma do artigo que aparece disponível mas cuja baixa falha.',
                                { vendidas: numero(r.resumo.qtd_vendida), saidas: numero(r.resumo.saidas) },
                            )}
                        </div>
                    )}

                    {r.por_armazem.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {r.por_armazem.map((s, i) => (
                                <span
                                    key={i}
                                    className={cls('bg-slate-100 px-3 py-1.5 text-sm text-slate-700', RAIO)}
                                >
                                    <strong>{s.armazem}:</strong> {numero(s.quantidade)}
                                </span>
                            ))}
                        </div>
                    )}

                    <section>
                        <h4 className="mb-2 text-sm font-bold text-slate-700">
                            <i className="fas fa-file-invoice mr-1.5 text-slate-400" aria-hidden="true" />
                            {t('Vendas (:quantas)', { quantas: r.vendas.length })}
                        </h4>
                        <div className={cls('overflow-x-auto border border-slate-200', RAIO)}>
                            <table className="w-full min-w-[640px] text-sm">
                                <thead className="bg-slate-50 text-xs text-slate-600">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-semibold">{t('Data')}</th>
                                        <th className="px-3 py-2 text-left font-semibold">{t('Documento')}</th>
                                        <th className="px-3 py-2 text-left font-semibold">{t('Cliente')}</th>
                                        <th className="px-3 py-2 text-right font-semibold">{t('Qtd')}</th>
                                        <th className="px-3 py-2 text-right font-semibold">{t('Preço')}</th>
                                        <th className="px-3 py-2 text-right font-semibold">{t('Total')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {r.vendas.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="px-3 py-6 text-center text-slate-400">
                                                {t('Sem vendas no período.')}
                                            </td>
                                        </tr>
                                    ) : (
                                        r.vendas.map((v) => (
                                            <tr key={v.id} className="transition-colors hover:bg-indigo-50/50">
                                                <td className="whitespace-nowrap px-3 py-2 text-slate-500">
                                                    {data(v.data)}
                                                </td>
                                                <td className="px-3 py-2 font-semibold">
                                                    {v.documento_id ? (
                                                        <a
                                                            href={`/invoicing/sales/invoices/${v.documento_id}`}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="text-indigo-600 hover:underline"
                                                        >
                                                            {v.documento}
                                                        </a>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-slate-700">{v.cliente ?? '—'}</td>
                                                <td className="px-3 py-2 text-right font-semibold tabular-nums">
                                                    {numero(v.quantidade)}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums">{kz(v.preco)}</td>
                                                <td className="px-3 py-2 text-right font-semibold tabular-nums">
                                                    {kz(v.total)}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section>
                        <h4 className="mb-2 text-sm font-bold text-slate-700">
                            <i className="fas fa-arrow-right-arrow-left mr-1.5 text-slate-400" aria-hidden="true" />
                            {t('Movimentos de stock (:quantos)', { quantos: r.movimentos.length })}
                        </h4>
                        <div className={cls('overflow-x-auto border border-slate-200', RAIO)}>
                            <table className="w-full min-w-[640px] text-sm">
                                <thead className="bg-slate-50 text-xs text-slate-600">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-semibold">{t('Data')}</th>
                                        <th className="px-3 py-2 text-left font-semibold">{t('Tipo')}</th>
                                        <th className="px-3 py-2 text-left font-semibold">{t('Armazém')}</th>
                                        <th className="px-3 py-2 text-right font-semibold">{t('Qtd')}</th>
                                        <th className="px-3 py-2 text-left font-semibold">{t('Origem')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {r.movimentos.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-3 py-6 text-center text-slate-400">
                                                {t('Sem movimentos no período.')}
                                            </td>
                                        </tr>
                                    ) : (
                                        r.movimentos.map((m) => (
                                            <tr key={m.id} className="transition-colors hover:bg-indigo-50/50">
                                                <td className="whitespace-nowrap px-3 py-2 text-slate-500">
                                                    {data(m.data)}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {/* Entrada, saída ou o resto: cada um com o
                                                        seu ícone, para o tipo não depender só da
                                                        cor. */}
                                                    <Etiqueta
                                                        cor={
                                                            m.tipo === 'in'
                                                                ? 'bom'
                                                                : m.tipo === 'out'
                                                                  ? 'perigo'
                                                                  : 'primaria'
                                                        }
                                                        icone={
                                                            m.tipo === 'in'
                                                                ? 'fa-arrow-down'
                                                                : m.tipo === 'out'
                                                                  ? 'fa-arrow-up'
                                                                  : 'fa-right-left'
                                                        }
                                                    >
                                                        {m.tipo}
                                                    </Etiqueta>
                                                </td>
                                                <td className="px-3 py-2 text-slate-700">{m.armazem ?? '—'}</td>
                                                <td className="px-3 py-2 text-right font-semibold tabular-nums">
                                                    {numero(m.quantidade)}
                                                </td>
                                                <td className="px-3 py-2 text-xs text-slate-500">
                                                    {m.origem || '—'}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            )}
        </Modal>
    );
}

/**
 * Uma quantidade sem zeros à direita: 2,500 lê-se «2,5» e 3,000 lê-se «3».
 *
 * As quantidades podem ser milesimais (250 gramas é 0,250) e escrevê-las
 * sempre com três casas enche a tabela de zeros que não dizem nada.
 */
function numero(v: number): string {
    return v.toLocaleString(etiquetaIntl(), { maximumFractionDigits: 3 });
}

/* ─── Lotes e validades ───────────────────────────────────────────────── */

/**
 * O QUE LIGA ESTE ARTIGO AO MÓDULO DOS LOTES.
 *
 * Sem estes quatro interruptores um artigo nunca entra no controlo de lotes:
 * é o `track_batches` que o `Product::controlaStock()` lê para saber que este
 * artigo desconta lote a lote, e são os dois «exigir» que fazem a compra e a
 * venda pedirem o número da remessa.
 *
 * VIVE COM O STOCK, e só em PRODUTOS. Um serviço não tem remessa nem prazo de
 * validade — o servidor apaga-lhe estas marcas, e mostrá-las era prometer uma
 * coisa que não se cumpre.
 *
 * E NÃO SE ESCONDE ATRÁS DO «gerir stock»: é o próprio `track_batches` que faz
 * o artigo controlar stock, mesmo com o agregado desligado. Escondê-lo atrás
 * do interruptor que ele dispensa deixava-o inalcançável.
 */
function LotesEValidade({
    dados,
    aoMudar,
}: {
    dados: ArtigoParaGravar;
    aoMudar: (d: ArtigoParaGravar) => void;
}) {
    // Dentro do render, e não no topo do ficheiro: o dicionário chega depois
    // do arranque, e um `t()` avaliado à importação saía sempre em português.
    const marcas: Array<{ chave: ChaveDeLote; titulo: string; nota: string }> = [
        {
            chave: 'track_batches',
            titulo: t('Rastrear por Lotes'),
            nota: t('Controlar produto por números de lote'),
        },
        {
            chave: 'track_expiry',
            titulo: t('Controlar Validade'),
            nota: t('Gerenciar data de validade do produto'),
        },
        {
            chave: 'require_batch_on_purchase',
            titulo: t('Exigir Lote na Compra'),
            nota: t('Obrigatório informar lote ao comprar'),
        },
        {
            chave: 'require_batch_on_sale',
            titulo: t('Exigir Lote na Venda'),
            nota: t('Obrigatório selecionar lote ao vender'),
        },
    ];

    return (
        <section className={cls('border border-slate-200 bg-white p-4', RAIO)}>
            <h4 className="text-sm font-bold text-slate-800">
                <i className="fas fa-layer-group mr-2 text-slate-400" aria-hidden="true" />
                {t('Controle de Lotes e Validade')}
            </h4>
            <p className="mt-0.5 text-xs text-slate-500">
                {t('Rastreabilidade e gestão de validade do produto')}
            </p>

            <div className="mt-3 grid gap-2 sm:grid-cols-2">
                {marcas.map((m) => (
                    <label key={m.chave} className="flex items-start gap-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={dados[m.chave]}
                            onChange={(e) => aoMudar({ ...dados, [m.chave]: e.target.checked })}
                            className="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600"
                        />
                        <span>
                            {m.titulo}
                            <span className="block text-xs text-slate-500">{m.nota}</span>
                        </span>
                    </label>
                ))}
            </div>
        </section>
    );
}

/* ─── A secção do sector ──────────────────────────────────────────────── */

type Blocos = { med: boolean; vest: boolean; cosm: boolean; merc: boolean };

/**
 * Medicamento, vestuário, cosmética e mercearia.
 *
 * O QUE DECIDE O QUE APARECE, por esta ordem:
 *
 * 1. o artigo já tem o dado gravado → aparece, sempre, e o perfil não o
 *    esconde;
 * 2. o perfil está ligado nas Definições → aparece por omissão;
 * 3. nem uma coisa nem outra → fica atrás de um botão, a um clique. Obrigar a
 *    passar pelas Definições só para marcar um artigo isolado era pior do que
 *    a linha discreta que aqui se mostra.
 *
 * O estado inicial calcula-se UMA VEZ, à abertura. Se fosse recalculado a cada
 * tecla, limpar um campo fazia o bloco desaparecer debaixo do cursor.
 */
function SeccaoDeSector({
    dados,
    erros,
    opcoes,
    aoMudar,
}: {
    dados: ArtigoParaGravar;
    erros: Record<string, string[]>;
    opcoes?: OpcoesDosArtigos;
    aoMudar: (d: ArtigoParaGravar) => void;
}) {
    const [inicial] = useState(() => {
        const perfis = new Set(opcoes?.perfis ?? []);

        const temMedicamento =
            dados.requires_prescription ||
            dados.is_controlled ||
            preenchido(dados.active_ingredient) ||
            preenchido(dados.dosage) ||
            preenchido(dados.pharmaceutical_form) ||
            preenchido(dados.armed_registration);

        const temVestuario =
            preenchido(dados.size) ||
            preenchido(dados.color) ||
            preenchido(dados.gender) ||
            preenchido(dados.material);

        const temCosmetica = preenchido(dados.pao_months) || preenchido(dados.inci_ingredients);

        const temMercearia =
            preenchido(dados.storage_conditions) ||
            preenchido(dados.allergens) ||
            preenchido(dados.origin_country);

        // O conteúdo líquido é dos dois ramos e por isso não vive dentro de
        // nenhum: tem visibilidade própria, senão um artigo que só o tenha
        // preenchido obrigava a abrir duas secções vazias para o ver.
        const temEmbalagem = preenchido(dados.net_content);

        const mostraMed = perfis.has('farmacia') || temMedicamento;
        const mostraVest = perfis.has('vestuario') || temVestuario;
        const mostraCosm = perfis.has('cosmetica') || temCosmetica;
        const mostraMerc = perfis.has('mercearia') || temMercearia;
        const mostraEmb = perfis.has('cosmetica') || perfis.has('mercearia') || temEmbalagem;

        const revelavel = !mostraMed && !mostraVest && !mostraCosm && !mostraMerc && !mostraEmb;

        return {
            revelavel,
            aberto: temMedicamento || temVestuario || temCosmetica || temMercearia || temEmbalagem,
            blocos: {
                med: mostraMed || revelavel,
                vest: mostraVest || revelavel,
                cosm: mostraCosm || revelavel,
                merc: mostraMerc || revelavel,
            } as Blocos,
        };
    });

    const [revelado, porRevelado] = useState(!inicial.revelavel);
    const [aberto, porAberto] = useState(inicial.aberto);
    const [blocos, porBlocos] = useState<Blocos>(inicial.blocos);

    const campo = <K extends keyof ArtigoParaGravar>(chave: K, valor: ArtigoParaGravar[K]) =>
        aoMudar({ ...dados, [chave]: valor });

    // Uma loja de roupa não tem de ler «Medicamento» no cabeçalho de uma
    // secção que só lhe mostra tamanhos.
    const abertos = [
        blocos.med && t('Medicamento'),
        blocos.vest && t('Vestuário'),
        blocos.cosm && t('Cosmética'),
        blocos.merc && t('Mercearia'),
    ].filter(Boolean) as string[];

    const titulo =
        abertos.length === 1 ? abertos[0] : abertos.length === 0 ? t('Embalagem') : t('Detalhes específicos do artigo');

    if (!revelado) {
        return (
            <div className="sm:col-span-3">
                <button
                    type="button"
                    onClick={() => {
                        porRevelado(true);
                        porAberto(true);
                    }}
                    className={cls('text-xs text-indigo-700 underline decoration-dotted hover:text-indigo-900', FOCO)}
                >
                    <i className="fas fa-tags mr-1" aria-hidden="true" />
                    {t('Este artigo tem campos próprios do ramo (receita, tamanho, alergénios…)?')}
                </button>
            </div>
        );
    }

    return (
        <section className={cls('sm:col-span-3 border border-slate-200 bg-slate-50 p-4', RAIO)}>
            <button
                type="button"
                onClick={() => porAberto((v) => !v)}
                aria-expanded={aberto}
                className={cls('flex w-full items-center gap-3 text-left', FOCO, RAIO)}
            >
                <i className="fas fa-tags text-slate-400" aria-hidden="true" />
                <span className="flex-1">
                    <span className="block text-sm font-bold text-slate-800">{titulo}</span>
                    <span className="block text-xs text-slate-500">
                        {t('Campos opcionais — preencha apenas o que se aplica a este artigo')}
                    </span>
                </span>
                <i className={cls('fas', aberto ? 'fa-chevron-up' : 'fa-chevron-down')} aria-hidden="true" />
            </button>

            {aberto && (
                <div className="mt-4 space-y-4">
                    {blocos.med && (
                        <div className={cls('bg-white p-4', CARTAO)}>
                            <h4 className="mb-3 text-sm font-bold text-slate-800">
                                <i className="fas fa-pills mr-2 text-slate-400" aria-hidden="true" />
                                {t('Medicamento')}
                            </h4>

                            <div className="mb-4 grid gap-2 sm:grid-cols-2">
                                <label className="flex items-start gap-2 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={dados.requires_prescription}
                                        onChange={(e) => campo('requires_prescription', e.target.checked)}
                                        className="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600"
                                    />
                                    <span>
                                        {t('Exige receita médica')}
                                        <span className="block text-xs text-slate-500">
                                            {t('Só se dispensa com apresentação de receita')}
                                        </span>
                                    </span>
                                </label>

                                <label className="flex items-start gap-2 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={dados.is_controlled}
                                        onChange={(e) => campo('is_controlled', e.target.checked)}
                                        className="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600"
                                    />
                                    <span>
                                        {t('Psicotrópico / estupefaciente')}
                                        <span className="block text-xs text-slate-500">
                                            {t('Substância sujeita a controlo especial')}
                                        </span>
                                    </span>
                                </label>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Campo
                                    etiqueta={t('Substância activa (DCI)')}
                                    erro={erros.active_ingredient}
                                    className="sm:col-span-2"
                                >
                                    <input
                                        maxLength={255}
                                        value={dados.active_ingredient ?? ''}
                                        onChange={(e) => campo('active_ingredient', e.target.value)}
                                        placeholder={t('Ex.: Paracetamol')}
                                        className={entrada}
                                    />
                                </Campo>

                                <Campo etiqueta={t('Dosagem')} erro={erros.dosage}>
                                    <input
                                        maxLength={60}
                                        value={dados.dosage ?? ''}
                                        onChange={(e) => campo('dosage', e.target.value)}
                                        placeholder={t('Ex.: 500mg, 5mg/ml')}
                                        className={entrada}
                                    />
                                </Campo>

                                {/* Texto livre com sugestões e não um select: a
                                    lista de formas farmacêuticas é longa e a
                                    farmácia tem de poder registar uma que não
                                    esteja prevista. */}
                                <Campo etiqueta={t('Forma farmacêutica')} erro={erros.pharmaceutical_form}>
                                    <input
                                        maxLength={40}
                                        list="formas-farmaceuticas"
                                        value={dados.pharmaceutical_form ?? ''}
                                        onChange={(e) => campo('pharmaceutical_form', e.target.value)}
                                        placeholder={t('Ex.: comprimido, xarope, injectável')}
                                        className={entrada}
                                    />
                                    <datalist id="formas-farmaceuticas">
                                        {FORMAS_FARMACEUTICAS.map((f) => (
                                            <option key={f} value={f} />
                                        ))}
                                    </datalist>
                                </Campo>

                                <Campo
                                    etiqueta={t('N.º de registo ARMED')}
                                    erro={erros.armed_registration}
                                    className="sm:col-span-2"
                                >
                                    <input
                                        maxLength={60}
                                        value={dados.armed_registration ?? ''}
                                        onChange={(e) => campo('armed_registration', e.target.value)}
                                        placeholder={t('Registo na ARMED (Angola)')}
                                        className={cls(entrada, 'font-mono')}
                                    />
                                </Campo>
                            </div>
                        </div>
                    )}

                    {blocos.vest && (
                        <div className={cls('bg-white p-4', CARTAO)}>
                            <h4 className="mb-3 text-sm font-bold text-slate-800">
                                <i className="fas fa-shirt mr-2 text-slate-400" aria-hidden="true" />
                                {t('Vestuário')}
                            </h4>

                            <div className="grid gap-4 sm:grid-cols-2">
                                {/* Sugestões do que já existe no catálogo: sem
                                    isto ficava «Azul», «azul» e «AZUL» a serem
                                    três cores diferentes nos filtros. */}
                                <Campo etiqueta={t('Tamanho')} erro={erros.size}>
                                    <input
                                        maxLength={20}
                                        list="tamanhos-do-catalogo"
                                        value={dados.size ?? ''}
                                        onChange={(e) => campo('size', e.target.value)}
                                        placeholder={t('Ex.: S, M, L, 38, 40')}
                                        className={entrada}
                                    />
                                    <datalist id="tamanhos-do-catalogo">
                                        {(opcoes?.variantes.tamanhos ?? []).map((x) => (
                                            <option key={x} value={x} />
                                        ))}
                                    </datalist>
                                </Campo>

                                <Campo etiqueta={t('Cor')} erro={erros.color}>
                                    <input
                                        maxLength={40}
                                        list="cores-do-catalogo"
                                        value={dados.color ?? ''}
                                        onChange={(e) => campo('color', e.target.value)}
                                        placeholder={t('Ex.: azul-marinho')}
                                        className={entrada}
                                    />
                                    <datalist id="cores-do-catalogo">
                                        {(opcoes?.variantes.cores ?? []).map((c) => (
                                            <option key={c} value={c} />
                                        ))}
                                    </datalist>
                                </Campo>

                                {/* Lista fechada: o género alimenta filtros e
                                    relatórios, e texto livre daria «M», «masc»
                                    e «Homem» a significarem o mesmo. */}
                                <Campo etiqueta={t('Género')} erro={erros.gender}>
                                    <select
                                        value={dados.gender ?? ''}
                                        onChange={(e) => campo('gender', e.target.value)}
                                        className={entrada}
                                    >
                                        <option value="">{t('Não aplicável')}</option>
                                        {(opcoes?.generos ?? []).map((g) => (
                                            <option key={g.valor} value={g.valor}>
                                                {g.rotulo}
                                            </option>
                                        ))}
                                    </select>
                                </Campo>

                                <Campo etiqueta={t('Composição')} erro={erros.material}>
                                    <input
                                        maxLength={120}
                                        value={dados.material ?? ''}
                                        onChange={(e) => campo('material', e.target.value)}
                                        placeholder={t('Ex.: 100% algodão')}
                                        className={entrada}
                                    />
                                </Campo>
                            </div>
                        </div>
                    )}

                    {/* O conteúdo líquido é da cosmética E da mercearia, por
                        isso fica fora dos dois em vez de repetido em ambos. */}
                    <div className={cls('bg-white p-4', CARTAO)}>
                        <h4 className="mb-3 text-sm font-bold text-slate-800">
                            <i className="fas fa-box-open mr-2 text-slate-400" aria-hidden="true" />
                            {t('Embalagem')}
                        </h4>

                        {/* A ajuda fica FORA do `Campo`: lá dentro entrava no
                            nome do campo e um leitor de ecrã lia a frase toda
                            de cada vez que o cursor lá chegasse. */}
                        <div className="sm:w-1/2">
                            <Campo etiqueta={t('Conteúdo líquido')} erro={erros.net_content}>
                                <input
                                    maxLength={40}
                                    value={dados.net_content ?? ''}
                                    onChange={(e) => campo('net_content', e.target.value)}
                                    placeholder={t('Ex.: 50ml, 200g, 1kg')}
                                    className={entrada}
                                />
                            </Campo>
                            <p className="mt-1 text-xs text-slate-500">
                                {t('É o que distingue duas embalagens do mesmo produto.')}
                            </p>
                        </div>
                    </div>

                    {blocos.cosm && (
                        <div className={cls('bg-white p-4', CARTAO)}>
                            <h4 className="mb-3 text-sm font-bold text-slate-800">
                                <i className="fas fa-pump-soap mr-2 text-slate-400" aria-hidden="true" />
                                {t('Cosmética')}
                            </h4>

                            <div className="grid gap-4 sm:grid-cols-2">
                                {/* É o frasco aberto com «12M» no rótulo: quanto
                                    tempo dura DEPOIS de aberto. Não substitui o
                                    prazo de validade por abrir — a loja precisa
                                    dos dois. */}
                                <div>
                                    <Campo etiqueta={t('Meses após abertura (PAO)')} erro={erros.pao_months}>
                                        <input
                                            type="number"
                                            min="1"
                                            max="120"
                                            step="1"
                                            value={dados.pao_months ?? ''}
                                            onChange={(e) => campo('pao_months', e.target.value)}
                                            placeholder={t('Ex.: 12')}
                                            className={cls(entrada, 'text-right tabular-nums')}
                                        />
                                    </Campo>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {t('Validade depois de aberto, que é diferente do prazo por abrir.')}
                                    </p>
                                </div>

                                {/* O tom não tem campo próprio: é a mesma coisa
                                    que a cor, que já existe. Dois sítios para
                                    gravar o mesmo davam duas respostas à mesma
                                    pergunta. */}
                                <div className="self-end text-xs text-slate-600">
                                    <p className={cls('bg-slate-50 px-3 py-2', RAIO)}>
                                        {tPartes('O tom regista-se no campo :campo.', { campo: <strong>{t('Cor')}</strong> })}
                                        {!blocos.vest && (
                                            <button
                                                type="button"
                                                onClick={() => porBlocos((b) => ({ ...b, vest: true }))}
                                                className={cls('ml-1 text-indigo-700 underline decoration-dotted', FOCO)}
                                            >
                                                {t('Mostrar')}
                                            </button>
                                        )}
                                    </p>
                                </div>

                                {/* Área de texto e não uma linha: uma lista INCI
                                    a sério tem dezenas de nomes, e é com ela que
                                    se responde ao balcão a «isto tem parabenos?». */}
                                <Campo etiqueta={t('Lista INCI')} erro={erros.inci_ingredients} className="sm:col-span-2">
                                    <textarea
                                        rows={3}
                                        value={dados.inci_ingredients ?? ''}
                                        onChange={(e) => campo('inci_ingredients', e.target.value)}
                                        placeholder={t('Ex.: Aqua, Glycerin, Parfum, Sodium Chloride')}
                                        className={cls(entrada, 'h-auto py-2')}
                                    />
                                </Campo>
                            </div>
                        </div>
                    )}

                    {blocos.merc && (
                        <div className={cls('bg-white p-4', CARTAO)}>
                            <h4 className="mb-3 text-sm font-bold text-slate-800">
                                <i className="fas fa-basket-shopping mr-2 text-slate-400" aria-hidden="true" />
                                {t('Mercearia')}
                            </h4>

                            <div className="grid gap-4 sm:grid-cols-2">
                                {/* Lista fechada, como o género: isto diz a quem
                                    arruma se o artigo vai para a prateleira,
                                    para o frigorífico ou para a arca. */}
                                <Campo etiqueta={t('Conservação')} erro={erros.storage_conditions}>
                                    <select
                                        value={dados.storage_conditions ?? ''}
                                        onChange={(e) => campo('storage_conditions', e.target.value)}
                                        className={entrada}
                                    >
                                        <option value="">{t('Não indicado')}</option>
                                        {(opcoes?.conservacao ?? []).map((c) => (
                                            <option key={c.valor} value={c.valor}>
                                                {c.rotulo}
                                            </option>
                                        ))}
                                    </select>
                                </Campo>

                                <div>
                                    <Campo etiqueta={t('País de origem')} erro={erros.origin_country}>
                                        <input
                                            maxLength={60}
                                            value={dados.origin_country ?? ''}
                                            onChange={(e) => campo('origin_country', e.target.value)}
                                            placeholder={t('Ex.: Angola, Portugal, Brasil')}
                                            className={entrada}
                                        />
                                    </Campo>
                                    <p className="mt-1 text-xs text-slate-500">{t('Obrigatório no rótulo alimentar.')}</p>
                                </div>

                                <div className="sm:col-span-2">
                                    <Campo etiqueta={t('Alergénios')} erro={erros.allergens}>
                                        <input
                                            maxLength={255}
                                            value={dados.allergens ?? ''}
                                            onChange={(e) => campo('allergens', e.target.value)}
                                            placeholder={t('Ex.: glúten, leite, frutos de casca rija')}
                                            className={entrada}
                                        />
                                    </Campo>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {t('Informação obrigatória no rótulo e pergunta de balcão.')}
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* A SAÍDA PARA O BLOCO QUE O PERFIL NÃO ABRIU.
                        Sem isto, ligar só o perfil de farmácia deixava o
                        tamanho e a cor sem forma nenhuma de serem preenchidos —
                        o perfil passava a decidir o que EXISTE em vez do que
                        aparece. */}
                    {(!blocos.med || !blocos.vest || !blocos.cosm || !blocos.merc) && (
                        <div className="flex flex-wrap gap-4 pt-1 text-xs">
                            {!blocos.med && (
                                <BotaoDeRamo
                                    icone="fa-pills"
                                    onClick={() => porBlocos((b) => ({ ...b, med: true }))}
                                >
                                    {t('Este artigo também é medicamento')}
                                </BotaoDeRamo>
                            )}
                            {!blocos.vest && (
                                <BotaoDeRamo
                                    icone="fa-shirt"
                                    onClick={() => porBlocos((b) => ({ ...b, vest: true }))}
                                >
                                    {t('Este artigo também é vestuário')}
                                </BotaoDeRamo>
                            )}
                            {!blocos.cosm && (
                                <BotaoDeRamo
                                    icone="fa-pump-soap"
                                    onClick={() => porBlocos((b) => ({ ...b, cosm: true }))}
                                >
                                    {t('Este artigo também é cosmética')}
                                </BotaoDeRamo>
                            )}
                            {!blocos.merc && (
                                <BotaoDeRamo
                                    icone="fa-basket-shopping"
                                    onClick={() => porBlocos((b) => ({ ...b, merc: true }))}
                                >
                                    {t('Este artigo também é mercearia')}
                                </BotaoDeRamo>
                            )}
                        </div>
                    )}
                </div>
            )}
        </section>
    );
}

function BotaoDeRamo({
    icone,
    onClick,
    children,
}: {
    icone: string;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cls('text-indigo-700 underline decoration-dotted hover:text-indigo-900', FOCO, RAIO)}
        >
            <i className={cls('fas mr-1', icone)} aria-hidden="true" />
            {children}
        </button>
    );
}

/* ─── As imagens ──────────────────────────────────────────────────────── */

/**
 * Imagem de destaque e galeria.
 *
 * A ESCOLHER NÃO SOBE NADA. Os ficheiros ficam à espera e sobem quando se
 * carrega em «Guardar» — a criar, é a única altura possível, porque antes
 * disso o artigo ainda não tem número. Apagar uma imagem JÁ GUARDADA é
 * diferente: essa vai já, porque é uma acção sobre um artigo que existe.
 */
function Imagens({
    artigo,
    destaque,
    galeria,
    aoEscolherDestaque,
    aoJuntarAGaleria,
    aoTirarDaGaleria,
}: {
    artigo: Artigo | null;
    destaque: File | null;
    galeria: File[];
    aoEscolherDestaque: (f: File | null) => void;
    aoJuntarAGaleria: (fs: File[]) => void;
    aoTirarDaGaleria: (indice: number) => void;
}) {
    const cache = useQueryClient();

    const [guardado, porGuardado] = useState({
        imagem: artigo?.imagem ?? null,
        galeria: artigo?.galeria ?? [],
    });

    const previaDoDestaque = useMemo(
        () => (destaque ? URL.createObjectURL(destaque) : null),
        [destaque],
    );

    const previasDaGaleria = useMemo(() => galeria.map((f) => URL.createObjectURL(f)), [galeria]);

    // Cada `createObjectURL` segura o ficheiro em memória até se lhe chamar
    // `revoke`. Sem isto, abrir e fechar a janela vinte vezes deixava vinte
    // fotografias penduradas no browser.
    useEffect(() => {
        return () => {
            if (previaDoDestaque) URL.revokeObjectURL(previaDoDestaque);
        };
    }, [previaDoDestaque]);

    useEffect(() => {
        return () => previasDaGaleria.forEach((u) => URL.revokeObjectURL(u));
    }, [previasDaGaleria]);

    const remover = useMutation({
        mutationFn: (caminho: string | null) =>
            caminho === null
                ? produtos.apagarImagem(artigo!.id)
                : produtos.apagarDaGaleria(artigo!.id, caminho),
        onSuccess: (r) => {
            porGuardado({ imagem: r.data.imagem, galeria: r.data.galeria });
            void cache.invalidateQueries({ queryKey: ['produtos'] });
        },
    });

    const podeApagarNoServidor = artigo !== null && !remover.isPending;

    return (
        <div className="sm:col-span-3 grid gap-4 sm:grid-cols-2">
            <div>
                <Rotulo>{t('Imagem de destaque')}</Rotulo>

                <div className="flex items-start gap-3">
                    {previaDoDestaque ? (
                        <figure className="text-center">
                            <img
                                src={previaDoDestaque}
                                alt={t('A imagem escolhida, ainda por enviar')}
                                className={cls('h-24 w-24 border border-emerald-300 object-cover', RAIO)}
                            />
                            <figcaption className="mt-1 text-[11px] font-semibold text-emerald-700">
                                {t('por enviar')}
                            </figcaption>
                        </figure>
                    ) : guardado.imagem ? (
                        <img
                            src={guardado.imagem}
                            alt={t('Imagem de :nome', { nome: artigo?.name ?? t('artigo') })}
                            className={cls('h-24 w-24 border border-slate-200 object-cover', RAIO)}
                        />
                    ) : (
                        <div
                            className={cls(
                                'grid h-24 w-24 place-items-center border border-dashed border-slate-300 text-slate-300',
                                RAIO,
                            )}
                            aria-hidden="true"
                        >
                            <i className="fas fa-image text-2xl" />
                        </div>
                    )}

                    <div className="flex-1 space-y-2">
                        <label
                            className={cls(
                                'inline-flex cursor-pointer items-center gap-2 bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200',
                                RAIO,
                            )}
                        >
                            <i className="fas fa-upload" aria-hidden="true" />
                            {t('Escolher imagem')}
                            <input
                                type="file"
                                accept="image/*"
                                className="sr-only"
                                onChange={(e) => aoEscolherDestaque(e.target.files?.[0] ?? null)}
                            />
                        </label>

                        <p className="text-xs text-slate-500">{t('Até 2 MB — PNG, JPG ou GIF.')}</p>

                        {destaque && (
                            <button
                                type="button"
                                onClick={() => aoEscolherDestaque(null)}
                                className={cls('text-xs text-red-600 underline decoration-dotted', FOCO)}
                            >
                                {t('Cancelar a escolha')}
                            </button>
                        )}

                        {!destaque && guardado.imagem && podeApagarNoServidor && (
                            <button
                                type="button"
                                onClick={() => remover.mutate(null)}
                                className={cls('block text-xs text-red-600 underline decoration-dotted', FOCO)}
                            >
                                {t('Apagar a imagem')}
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <div>
                <Rotulo>{t('Galeria')}</Rotulo>

                <div className="flex flex-wrap gap-2">
                    {guardado.galeria.map((g) => (
                        <div key={g.caminho} className="relative">
                            <img
                                src={g.url}
                                alt=""
                                className={cls('h-20 w-20 border border-slate-200 object-cover', RAIO)}
                            />
                            {podeApagarNoServidor && (
                                <button
                                    type="button"
                                    onClick={() => remover.mutate(g.caminho)}
                                    aria-label={t('Apagar imagem da galeria')}
                                    className="absolute -right-2 -top-2 grid h-6 w-6 place-items-center rounded-full bg-red-600 text-xs text-white"
                                >
                                    <i className="fas fa-times" aria-hidden="true" />
                                </button>
                            )}
                        </div>
                    ))}

                    {previasDaGaleria.map((u, i) => (
                        <div key={u} className="relative">
                            <img
                                src={u}
                                alt={t('Imagem escolhida, ainda por enviar')}
                                className={cls('h-20 w-20 border border-emerald-300 object-cover', RAIO)}
                            />
                            <button
                                type="button"
                                onClick={() => aoTirarDaGaleria(i)}
                                aria-label={t('Tirar a imagem escolhida')}
                                className="absolute -right-2 -top-2 grid h-6 w-6 place-items-center rounded-full bg-slate-700 text-xs text-white"
                            >
                                <i className="fas fa-times" aria-hidden="true" />
                            </button>
                        </div>
                    ))}
                </div>

                <label
                    className={cls(
                        'mt-2 inline-flex cursor-pointer items-center gap-2 bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200',
                        RAIO,
                    )}
                >
                    <i className="fas fa-images" aria-hidden="true" />
                    {t('Juntar à galeria')}
                    <input
                        type="file"
                        accept="image/*"
                        multiple
                        className="sr-only"
                        onChange={(e) => {
                            aoJuntarAGaleria(Array.from(e.target.files ?? []));
                            // Sem isto, escolher o MESMO ficheiro outra vez não
                            // dispara `change` e parece que o botão não faz nada.
                            e.target.value = '';
                        }}
                    />
                </label>

                <p className="mt-1 text-xs text-slate-500">{t('Até 10 imagens, 2 MB cada.')}</p>

                {remover.isError && (
                    <p role="alert" className="mt-2 text-xs text-red-700">
                        {remover.error instanceof ErroDaApi
                            ? remover.error.message
                            : t('Não foi possível apagar a imagem.')}
                    </p>
                )}
            </div>
        </div>
    );
}

/* ─── Peças ───────────────────────────────────────────────────────────── */

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    if (daApi?.eSessaoMorta) {
        return (
            <div className={cls('border border-amber-200 bg-amber-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-amber-900">{t('A sessão expirou')}</h2>
                <p className="mb-4 text-sm text-amber-900">{t('Entre outra vez para continuar. Nada se perdeu.')}</p>
                <Botao cor="primaria" tom="solida" onClick={() => window.location.reload()}>
                    {t('Voltar a entrar')}
                </Botao>
            </div>
        );
    }

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível carregar os artigos')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação e tente outra vez.')}</p>
        </div>
    );
}
