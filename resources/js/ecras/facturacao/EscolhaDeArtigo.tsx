import { useEffect, useMemo, useRef, useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { produtos, type Artigo } from '@/api/produtos';
import { Botao } from '@/ui/Botao';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { entrada } from '@/ui/Campo';
import { FOCO, RAIO, TRANSICAO, cls, kz, type Cor } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * ESCOLHER O ARTIGO DE UMA LINHA — com procura, como se faz uma factura ao
 * balcão.
 *
 * O ecrã em Blade não tinha um `<select>` com o catálogo lá dentro: tinha o
 * botão «Adicionar Produto», que abria um modal com uma caixa de procura e
 * uma GRELHA DE CARTÕES — nome, código, preço e stock — em que se carrega
 * para juntar a linha. Com trezentos artigos, um `<select>` obriga a percorrer
 * a lista inteira; com a grelha escreve-se «cim» e clica-se no cimento.
 *
 * O QUE ISTO NÃO FAZ: não substitui o `<select>` por linha. Esse continua a
 * ser o caminho de quem trabalha por teclado, e é por ele que os ensaios
 * escolhem artigos. Isto ACRESCENTA — junta uma linha nova já preenchida.
 *
 * A PROCURA É DO SERVIDOR (`GET /products`, com `procura`), e é essa a razão
 * de existir: descarregar cinco mil artigos para filtrar no browser era
 * exactamente o problema que se veio resolver. Quando essa porta recusa — ela
 * pede `invoicing.products.view`, e quem factura pode não a ter — filtra-se o
 * catálogo que o editor já carregou, que é pouco mas é honesto, e diz-se.
 */

/** O que uma linha de documento precisa de saber de um artigo. */
export type ArtigoDaLinha = {
    id: number;
    name: string;
    code: string | null;
    /** Já resolvido por quem chama: preço de venda numa venda, custo numa compra. */
    price: number;
    unit: string;
    type: string;
};

/** O mesmo, com o que só o cartão mostra. */
type NoCartao = ArtigoDaLinha & {
    sku: string | null;
    /** `null` quando o artigo não gere stock — ou quando não se sabe. */
    stock: number | null;
    em_falta: boolean;
    esgotado: boolean;
};

export function EscolhaDeArtigo({
    aoEscolher,
    preco = 'venda',
    catalogo,
    cor = 'primaria',
    rotulo,
    className,
}: {
    /** Chamado por cada cartão em que se carrega. O modal fica aberto. */
    aoEscolher: (artigo: ArtigoDaLinha) => void;
    /** Numa compra o que se propõe na linha é o CUSTO, não o preço de venda. */
    preco?: 'venda' | 'custo';
    /** O catálogo que o `<select>` já tem: a rede para quando a API recusa. */
    catalogo: ReadonlyArray<ArtigoDaLinha>;
    cor?: Cor;
    rotulo?: string;
    className?: string;
}) {
    const [aberto, porAberto] = useState(false);
    const [procura, porProcura] = useState('');
    const [termo, porTermo] = useState('');
    /* Os que já foram juntos nesta abertura — para o cartão o dizer. */
    const [juntos, porJuntos] = useState<number[]>([]);
    const caixa = useRef<HTMLInputElement>(null);

    /*
     * ABRIR PÕE O CURSOR NA PROCURA.
     *
     * É o gesto todo: abre-se, escreve-se «cim», carrega-se no cimento. Sem
     * isto obrigava-se a ir com o rato à caixa antes de poder escrever, e um
     * `autoFocus` não serve — o `<dialog>` está montado e FECHADO desde que a
     * página abre, e um elemento escondido não aceita foco. Este efeito corre
     * DEPOIS do `showModal()` do `Modal`, que é filho: os efeitos dos filhos
     * correm primeiro.
     */
    useEffect(() => {
        if (aberto) caixa.current?.focus();
    }, [aberto]);

    /* A PROCURA VIAJA COM PAUSA: 300 ms, o mesmo `debounce.300ms` do ecrã de
       sempre. Sem ela, escrever «cimento» são sete consultas ao servidor. */
    useEffect(() => {
        const pausa = setTimeout(() => porTermo(procura.trim()), 300);

        return () => clearTimeout(pausa);
    }, [procura]);

    const lista = useQuery({
        queryKey: ['produtos', 'escolher', termo],
        queryFn: () => produtos.lista({ procura: termo || undefined, activo: '1', por_pagina: 60 }),
        // Só se pergunta com o modal aberto: um editor de factura não tem de
        // ir buscar o catálogo a quem nunca abre esta janela.
        enabled: aberto,
        staleTime: 30_000,
        // Uma recusa de permissão não se repete três vezes: a resposta é a
        // mesma, e o que se quer é cair na rede depressa.
        retry: false,
        placeholderData: keepPreviousData,
    });

    const paraCartao = (a: Artigo): NoCartao => ({
        id: a.id,
        name: a.name,
        code: a.code,
        sku: a.sku,
        price: preco === 'custo' ? (a.cost ?? 0) : a.price,
        unit: a.unit,
        type: a.type,
        stock: a.manage_stock ? (a.stock ?? 0) : null,
        em_falta: a.em_falta,
        esgotado: a.esgotado,
    });

    /* A REDE: o catálogo do editor, filtrado aqui. Não tem stock — o `opcoes`
       do emissor nunca o mandou — e por isso o cartão não o inventa. */
    const daRede = useMemo<NoCartao[]>(() => {
        const t0 = termo.toLowerCase();
        const todos = catalogo.map((a) => ({ ...a, sku: null, stock: null, em_falta: false, esgotado: false }));

        return t0 === ''
            ? todos
            : todos.filter((a) => `${a.name} ${a.code ?? ''}`.toLowerCase().includes(t0));
    }, [catalogo, termo]);

    const recusou = lista.isError;
    const visiveis: NoCartao[] = recusou ? daRede : (lista.data?.data ?? []).map(paraCartao);
    const total = recusou ? daRede.length : (lista.data?.meta.total ?? 0);

    const juntar = (a: NoCartao) => {
        aoEscolher({ id: a.id, name: a.name, code: a.code, price: a.price, unit: a.unit, type: a.type });
        porJuntos((j) => [...j, a.id]);
    };

    const fechar = () => {
        porAberto(false);
        porProcura('');
        porTermo('');
        porJuntos([]);
    };

    return (
        <>
            <Botao
                cor={cor}
                tom="solida"
                altura="pequeno"
                icone="fa-magnifying-glass"
                className={className}
                onClick={() => porAberto(true)}
            >
                {rotulo ?? t('Adicionar artigo')}
            </Botao>

            <Modal
                aberto={aberto}
                aoFechar={fechar}
                titulo={t('Escolher artigos')}
                subtitulo={t('Escreva para procurar e carregue no artigo para o juntar ao documento.')}
                icone="fa-box-open"
                cor={cor}
                largura="xl"
                rodape={
                    <>
                        {/* O QUE JÁ FOI JUNTO, dito por extenso. O modal não se
                            fecha ao primeiro artigo — ao balcão juntam-se
                            vários — e sem esta contagem não se sabia se o
                            clique tinha feito alguma coisa. */}
                        <span role="status" className="mr-auto text-xs font-medium text-slate-500">
                            {juntos.length > 0 && (
                                <>
                                    <i className="fas fa-circle-check mr-1.5 text-emerald-600" aria-hidden="true" />
                                    {juntos.length === 1
                                        ? t('1 artigo junto ao documento.')
                                        : t(':quantos artigos juntos ao documento.', { quantos: juntos.length })}
                                </>
                            )}
                        </span>
                        <Botao cor={juntos.length > 0 ? 'bom' : 'neutra'} tom={juntos.length > 0 ? 'solida' : 'suave'} icone="fa-check" onClick={fechar}>
                            {t('Concluir')}
                        </Botao>
                    </>
                }
            >
                {/* A LUPA DENTRO DA CAIXA, como na escolha do cliente. */}
                <div className="relative">
                    <i
                        className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        ref={caixa}
                        type="search"
                        value={procura}
                        onChange={(e) => porProcura(e.target.value)}
                        placeholder={t('Pesquisar produtos…')}
                        aria-label={t('Pesquisar produtos')}
                        className={cls(entrada, 'w-full pl-9')}
                    />
                </div>

                {recusou && (
                    <p role="status" className="mt-3 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        <i className="fas fa-triangle-exclamation mt-0.5 flex-none" aria-hidden="true" />
                        {t('Sem acesso ao catálogo completo — a procurar na lista que o documento já carregou.')}
                    </p>
                )}

                {/* Quantos se mostram, de quantos há. Com um catálogo grande é
                    isto que diz «afine a procura» em vez de deixar acreditar
                    que o artigo não existe. */}
                {!recusou && total > visiveis.length && (
                    <p role="status" className="mt-3 flex items-center gap-1.5 text-xs text-slate-500">
                        <i className="fas fa-filter flex-none" aria-hidden="true" />
                        {t('A mostrar :quantos de :total. Escreva para afinar a procura.', { quantos: visiveis.length, total })}
                    </p>
                )}

                <div className="mt-4">
                    {lista.isFetching && visiveis.length === 0 ? (
                        <SemNada icone="fa-spinner fa-spin">{t('A procurar no catálogo…')}</SemNada>
                    ) : visiveis.length === 0 ? (
                        <SemNada icone="fa-box-open">
                            {termo === ''
                                ? t('O catálogo está vazio. Crie artigos para os poder facturar.')
                                : t('Nenhum artigo encontrado. Experimente outra palavra ou o código.')}
                        </SemNada>
                    ) : (
                        <div
                            className={cls('grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4', lista.isFetching && 'opacity-60')}
                        >
                            {visiveis.map((a, i) => (
                                <CartaoDoArtigo key={a.id} artigo={a} indice={i} junto={juntos.includes(a.id)} aoCarregar={() => juntar(a)} />
                            ))}
                        </div>
                    )}
                </div>
            </Modal>
        </>
    );
}

/**
 * UM ARTIGO, EM CARTÃO.
 *
 * É um `<button>` a sério e não uma `<div>` com `onClick`: assim chega-se-lhe
 * por teclado e um leitor de ecrã diz o que é. Levanta-se ao passar, como no
 * ecrã de sempre.
 */
function CartaoDoArtigo({
    artigo,
    indice,
    junto,
    aoCarregar,
}: {
    artigo: NoCartao;
    indice: number;
    junto: boolean;
    aoCarregar: () => void;
}) {
    const referencia = artigo.code ?? artigo.sku;
    const servico = artigo.type === 'servico';

    /* O STOCK, com a cor de quem está em falta — e nunca só com a cor: o
       ícone e a palavra dizem o mesmo a quem não a distingue. */
    const existencias = artigo.esgotado
        ? { tinta: 'text-red-700', icone: 'fa-circle-exclamation', texto: t('Sem stock') }
        : artigo.em_falta
          ? { tinta: 'text-amber-700', icone: 'fa-triangle-exclamation', texto: t('Stock baixo: :quanto', { quanto: kz(artigo.stock, 0) }) }
          : { tinta: 'text-slate-500', icone: 'fa-cube', texto: t('Stock: :quanto', { quanto: kz(artigo.stock, 0) }) };

    return (
        <button
            type="button"
            onClick={aoCarregar}
            // O que o cartão promete juntar à linha, para o ensaio do browser
            // poder confrontá-lo com o que a linha ficou a ter.
            data-artigo={artigo.id}
            data-preco={artigo.price}
            style={cascata(indice)}
            className={cls(
                'entra flex h-full flex-col border-2 bg-white p-4 text-left',
                RAIO,
                TRANSICAO,
                FOCO,
                'hover:-translate-y-0.5 hover:border-indigo-400 hover:shadow-lg',
                junto ? 'border-emerald-300 bg-emerald-50/50' : 'border-slate-200',
            )}
        >
            <p className="line-clamp-2 text-sm font-bold text-slate-900">{artigo.name}</p>

            <p className="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                {referencia ? (
                    <>
                        <i className="fas fa-barcode flex-none" aria-hidden="true" />
                        <span className="truncate">{referencia}</span>
                    </>
                ) : (
                    <span className="truncate">{artigo.unit}</span>
                )}
            </p>

            <div className="mt-auto w-full border-t border-slate-200 pt-3">
                <p className="text-lg font-bold tabular-nums text-indigo-600">
                    {kz(artigo.price)} <span className="text-xs font-normal text-indigo-500/70">Kz</span>
                </p>

                {servico ? (
                    <p className="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                        <i className="fas fa-screwdriver-wrench flex-none" aria-hidden="true" />
                        {t('Serviço')}
                    </p>
                ) : (
                    artigo.stock !== null && (
                        <p className={cls('mt-1 flex items-center gap-1.5 text-xs font-medium', existencias.tinta)}>
                            <i className={`fas ${existencias.icone} flex-none`} aria-hidden="true" />
                            {existencias.texto}
                        </p>
                    )
                )}

                {junto && (
                    <p className="mt-1 flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                        <i className="fas fa-circle-check flex-none" aria-hidden="true" />
                        {t('Já no documento')}
                    </p>
                )}
            </div>
        </button>
    );
}

/**
 * JUNTAR O ARTIGO ÀS LINHAS — ocupando a primeira que ainda está em branco.
 *
 * Um editor abre com uma linha vazia. Sem isto, carregar no primeiro artigo
 * deixava o documento com duas linhas, uma delas por preencher, e obrigava a
 * apagá-la à mão. Com isto, a primeira escolha ocupa a linha que já lá está e
 * as seguintes nascem por baixo.
 *
 * O que estiver escrito na linha em branco (um lote, uma validade) fica: só
 * se preenchem o artigo, a descrição e o preço.
 */
export function juntarArtigo<L extends { product_id: number | null; description: string; price: number | string }>(
    linhas: L[],
    vazia: L,
    artigo: ArtigoDaLinha,
): L[] {
    const campos = { product_id: artigo.id, description: artigo.name, price: artigo.price };
    const i = linhas.findIndex((l) => l.product_id === null && l.description.trim() === '' && Number(l.price) === 0);

    return i === -1
        ? [...linhas, { ...vazia, ...campos }]
        : linhas.map((l, j) => (j === i ? { ...l, ...campos } : l));
}
