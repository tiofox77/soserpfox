import { useCallback, useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { produtos, type Artigo } from '@/api/produtos';
import { Botao } from '@/ui/Botao';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { entrada } from '@/ui/Campo';
import { FOCO, RAIO, TRANSICAO, cls, kz, type Cor } from '@/ui/tokens';
import { t } from '@/i18n';
import { eRica } from './descricaoRica';

/**
 * ESCOLHER ARTIGOS — com procura, como se faz uma factura ao balcão.
 *
 * O ecrã em Blade não tinha um `<select>` com o catálogo lá dentro: tinha o
 * botão «Adicionar Produto», que abria um modal com uma caixa de procura e
 * uma GRELHA DE CARTÕES — nome, código, preço e stock — em que se carrega
 * para juntar a linha. Com trezentos artigos, um `<select>` obriga a percorrer
 * a lista inteira; com a grelha escreve-se «cim» e clica-se no cimento.
 *
 * A MESMA JANELA SERVE DOIS GESTOS:
 *
 *  · JUNTAR (`EscolhaDeArtigo`, o botão «Adicionar artigo»): cada cartão junta
 *    uma linha e a janela fica aberta, porque ao balcão juntam-se vários.
 *  · TROCAR (`CampoDoArtigo`, o artigo de UMA linha): escolhe-se e fecha.
 *
 * O `<select>` de cada linha saiu em 23/09/2026. Era a lista crua do catálogo
 * que o editor carrega, e esse catálogo PÁRA NOS 500 ARTIGOS: numa farmácia
 * com milhares, o que ficava depois do 500.º por ordem alfabética não se
 * escolhia na linha, e um artigo junto pela procura aparecia em branco nela.
 * O campo novo procura o catálogo inteiro no servidor e lembra-se do que se
 * escolheu. Escrever uma letra no campo abre a janela já a procurar.
 *
 * A PROCURA É DO SERVIDOR (`GET /products`, com `procura`, `tipo` e
 * `categoria`), e é essa a razão de existir: descarregar cinco mil artigos para
 * filtrar no browser era exactamente o problema que se veio resolver. Quando
 * essa porta recusa — ela pede `invoicing.products.view`, e quem factura pode
 * não a ter — filtra-se o catálogo que o editor já carregou, que é pouco mas é
 * honesto, e diz-se.
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
    categoria: string | null;
};

type Tipo = '' | 'produto' | 'servico';

/**
 * A JANELA DOS ARTIGOS: procura, filtros e a grelha de cartões.
 */
export function JanelaDeArtigos({
    aberto,
    aoFechar,
    aoEscolher,
    modo = 'juntar',
    preco = 'venda',
    catalogo,
    cor = 'primaria',
    titulo,
    actual = null,
    procuraInicial = '',
}: {
    aberto: boolean;
    aoFechar: () => void;
    aoEscolher: (artigo: ArtigoDaLinha) => void;
    /** Juntar vários (fica aberta) ou trocar o de uma linha (fecha ao escolher). */
    modo?: 'juntar' | 'trocar';
    /** Numa compra o que se propõe na linha é o CUSTO, não o preço de venda. */
    preco?: 'venda' | 'custo';
    /** O catálogo que o editor já tem: a rede para quando a API recusa. */
    catalogo: ReadonlyArray<ArtigoDaLinha>;
    cor?: Cor;
    titulo?: string;
    /** No modo trocar, o artigo que a linha já tem — o cartão diz «Na linha». */
    actual?: number | null;
    /** O que se escreveu no campo da linha antes de a janela abrir. */
    procuraInicial?: string;
}) {
    const [procura, porProcura] = useState('');
    const [termo, porTermo] = useState('');
    const [tipo, porTipo] = useState<Tipo>('');
    const [categoria, porCategoria] = useState('');
    /* Os que já foram juntos nesta abertura — para o cartão o dizer. */
    const [juntos, porJuntos] = useState<number[]>([]);
    const caixa = useRef<HTMLInputElement>(null);

    /*
     * ABRIR PÕE O CURSOR NA PROCURA — e traz o que já se escreveu no campo.
     *
     * Um `autoFocus` não serve: o `<dialog>` está montado e FECHADO desde que a
     * página abre, e um elemento escondido não aceita foco. Este efeito corre
     * DEPOIS do `showModal()` do `Modal`, que é filho: os efeitos dos filhos
     * correm primeiro.
     */
    useEffect(() => {
        if (!aberto) return;

        porProcura(procuraInicial);
        porTermo(procuraInicial.trim());
        caixa.current?.focus();
    }, [aberto, procuraInicial]);

    /* A PROCURA VIAJA COM PAUSA: 300 ms, o mesmo `debounce.300ms` do ecrã de
       sempre. Sem ela, escrever «cimento» são sete consultas ao servidor. */
    useEffect(() => {
        const pausa = setTimeout(() => porTermo(procura.trim()), 300);

        return () => clearTimeout(pausa);
    }, [procura]);

    const lista = useQuery({
        queryKey: ['produtos', 'escolher', termo, tipo, categoria],
        queryFn: () => produtos.lista({
            procura: termo || undefined,
            tipo: tipo || undefined,
            categoria: categoria || undefined,
            activo: '1',
            por_pagina: 60,
        }),
        // Só se pergunta com o modal aberto: um editor de factura não tem de
        // ir buscar o catálogo a quem nunca abre esta janela.
        enabled: aberto,
        staleTime: 30_000,
        // Uma recusa de permissão não se repete três vezes: a resposta é a
        // mesma, e o que se quer é cair na rede depressa.
        retry: false,
        placeholderData: keepPreviousData,
    });

    // As categorias, para arrumar um catálogo grande. Sem acesso, não há filtro.
    const opcoes = useQuery({
        queryKey: ['produtos', 'opcoes'],
        queryFn: () => produtos.opcoes(),
        enabled: aberto,
        staleTime: 300_000,
        retry: false,
    });
    const categorias = opcoes.data?.categorias ?? [];

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
        categoria: a.category?.name ?? null,
    });

    /* A REDE: o catálogo do editor, filtrado aqui. Não tem stock nem categoria
       — o `opcoes` do emissor nunca os mandou — e por isso o cartão não os inventa. */
    const daRede = useMemo<NoCartao[]>(() => {
        const t0 = termo.toLowerCase();

        return catalogo
            .map((a) => ({ ...a, sku: null, stock: null, em_falta: false, esgotado: false, categoria: null }))
            .filter((a) => (tipo === '' || a.type === tipo) && (t0 === '' || `${a.name} ${a.code ?? ''}`.toLowerCase().includes(t0)));
    }, [catalogo, termo, tipo]);

    const recusou = lista.isError;
    const visiveis: NoCartao[] = recusou ? daRede : (lista.data?.data ?? []).map(paraCartao);
    const total = recusou ? daRede.length : (lista.data?.meta.total ?? 0);

    const fechar = () => {
        porProcura('');
        porTermo('');
        porTipo('');
        porCategoria('');
        porJuntos([]);
        aoFechar();
    };

    const escolher = (a: NoCartao) => {
        aoEscolher({ id: a.id, name: a.name, code: a.code, price: a.price, unit: a.unit, type: a.type });

        if (modo === 'trocar') {
            fechar();
        } else {
            porJuntos((j) => [...j, a.id]);
        }
    };

    // Enter na procura escolhe o primeiro: escreve-se «cim», Enter, e está.
    const aoTeclar = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();

        // Só depois de a procura assentar: antes disso o primeiro é de outra procura.
        if (procura.trim() === termo && !lista.isFetching && visiveis[0]) {
            escolher(visiveis[0]);
        }
    };

    const filtros: Array<{ valor: Tipo; rotulo: string; icone: string }> = [
        { valor: '', rotulo: t('Todos'), icone: 'fa-layer-group' },
        { valor: 'produto', rotulo: t('Produtos'), icone: 'fa-box' },
        { valor: 'servico', rotulo: t('Serviços'), icone: 'fa-concierge-bell' },
    ];

    return (
        <Modal
            aberto={aberto}
            aoFechar={fechar}
            titulo={titulo ?? (modo === 'trocar' ? t('Escolher o artigo') : t('Escolher artigos'))}
            subtitulo={modo === 'trocar'
                ? t('Escreva para procurar e carregue no artigo. Enter escolhe o primeiro.')
                : t('Escreva para procurar e carregue no artigo para o juntar ao documento.')}
            icone="fa-box-open"
            cor={cor}
            largura="xl"
            rodape={
                modo === 'trocar' ? (
                    <Botao icone="fa-xmark" onClick={fechar}>{t('Cancelar')}</Botao>
                ) : (
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
                )
            }
        >
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                {/* A LUPA DENTRO DA CAIXA, como na escolha do cliente. */}
                <div className="relative flex-1">
                    <i
                        className="fas fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        ref={caixa}
                        type="search"
                        value={procura}
                        onChange={(e) => porProcura(e.target.value)}
                        onKeyDown={aoTeclar}
                        placeholder={t('Pesquisar por nome ou código…')}
                        aria-label={t('Pesquisar produtos')}
                        className={cls(entrada, 'w-full pl-9')}
                    />
                </div>

                {/* ARRUMAR: o tipo em botões (três gestos, sempre à vista) e a categoria numa lista. */}
                <div className="flex flex-wrap items-center gap-2">
                    <div role="group" aria-label={t('Tipo de artigo')} className={cls('inline-flex border border-slate-200 bg-slate-50 p-0.5', RAIO)}>
                        {filtros.map((f) => (
                            <button
                                key={f.valor}
                                type="button"
                                aria-pressed={tipo === f.valor}
                                onClick={() => porTipo(f.valor)}
                                className={cls(
                                    'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold',
                                    TRANSICAO,
                                    FOCO,
                                    tipo === f.valor ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-800',
                                )}
                            >
                                <i className={cls('fas', f.icone)} aria-hidden="true" />
                                {f.rotulo}
                            </button>
                        ))}
                    </div>

                    {!recusou && categorias.length > 0 && (
                        <select
                            value={categoria}
                            onChange={(e) => porCategoria(e.target.value)}
                            aria-label={t('Categoria')}
                            className={cls(entrada, 'w-auto min-w-[11rem] py-1.5 text-sm')}
                        >
                            <option value="">{t('Todas as categorias')}</option>
                            {categorias.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.mae ? `${c.mae} › ${c.name}` : c.name}
                                </option>
                            ))}
                        </select>
                    )}
                </div>
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
                        {termo === '' && tipo === '' && categoria === ''
                            ? t('O catálogo está vazio. Crie artigos para os poder facturar.')
                            : t('Nenhum artigo encontrado. Experimente outra palavra ou o código.')}
                    </SemNada>
                ) : (
                    <div
                        className={cls('grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4', lista.isFetching && 'opacity-60')}
                    >
                        {visiveis.map((a, i) => (
                            <CartaoDoArtigo
                                key={a.id}
                                artigo={a}
                                indice={i}
                                marca={modo === 'trocar' ? (a.id === actual ? 'actual' : null) : (juntos.includes(a.id) ? 'junto' : null)}
                                aoCarregar={() => escolher(a)}
                            />
                        ))}
                    </div>
                )}
            </div>
        </Modal>
    );
}

/** O botão «Adicionar artigo» e a sua janela: junta linhas ao documento. */
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
    preco?: 'venda' | 'custo';
    catalogo: ReadonlyArray<ArtigoDaLinha>;
    cor?: Cor;
    rotulo?: string;
    className?: string;
}) {
    const [aberto, porAberto] = useState(false);

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

            <JanelaDeArtigos
                aberto={aberto}
                aoFechar={() => porAberto(false)}
                aoEscolher={aoEscolher}
                preco={preco}
                catalogo={catalogo}
                cor={cor}
            />
        </>
    );
}

/**
 * O ARTIGO DE UMA LINHA — um campo que abre a janela, no lugar do `<select>`.
 *
 * Mostra o artigo escolhido (nome, código e preço) ou «Escolher artigo…».
 * Carregar abre a janela; escrever uma letra também, e a letra vai para a
 * procura — quem trabalha por teclado escreve «cim», Enter, e tem o cimento.
 * Continua a chamar-se «Artigo da linha N» para quem lê o ecrã.
 */
export function CampoDoArtigo({
    n,
    artigo,
    aoEscolher,
    preco = 'venda',
    catalogo,
    invalido = false,
}: {
    n: number;
    /** O artigo que a linha tem, se se sabe qual é. */
    artigo: ArtigoDaLinha | null;
    aoEscolher: (artigo: ArtigoDaLinha) => void;
    preco?: 'venda' | 'custo';
    catalogo: ReadonlyArray<ArtigoDaLinha>;
    invalido?: boolean;
}) {
    const [aberto, porAberto] = useState(false);
    const [inicio, porInicio] = useState('');

    const abrir = (letras = '') => {
        porInicio(letras);
        porAberto(true);
    };

    const aoTeclar = (e: KeyboardEvent<HTMLButtonElement>) => {
        // Uma letra ou um número abre a procura já começada.
        if (e.key.length === 1 && /\S/.test(e.key) && !e.ctrlKey && !e.metaKey && !e.altKey) {
            e.preventDefault();
            abrir(e.key);
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            abrir();
        }
    };

    return (
        <>
            <button
                type="button"
                onClick={() => abrir()}
                onKeyDown={aoTeclar}
                aria-label={t('Artigo da linha :n', { n })}
                aria-haspopup="dialog"
                aria-invalid={invalido ? true : undefined}
                data-artigo-escolhido={artigo?.id ?? ''}
                title={artigo?.name}
                className={cls(
                    'group flex w-full min-w-[10rem] max-w-[18rem] items-center gap-2 border bg-white px-2.5 py-1.5 text-left text-sm shadow-sm',
                    TRANSICAO,
                    FOCO,
                    RAIO,
                    'hover:border-indigo-400 disabled:cursor-default disabled:bg-slate-50 disabled:hover:border-slate-300',
                    invalido ? 'border-red-400 bg-red-50/60 ring-1 ring-red-300' : 'border-slate-300',
                )}
            >
                {artigo ? (
                    <span className="min-w-0 flex-1">
                        <span className="block truncate font-medium text-slate-900">{artigo.name}</span>
                        <span className="block truncate text-xs text-slate-500">
                            {[artigo.code, `${kz(artigo.price)} Kz`].filter(Boolean).join(' · ')}
                        </span>
                    </span>
                ) : (
                    <span className="flex-1 truncate text-slate-400">{t('Escolher artigo…')}</span>
                )}
                <i className="fas fa-magnifying-glass flex-none text-xs text-slate-400 group-hover:text-indigo-500" aria-hidden="true" />
            </button>

            {/* Só existe aberta: dez linhas não são dez janelas escondidas na página. */}
            {aberto && (
            <JanelaDeArtigos
                aberto
                aoFechar={() => porAberto(false)}
                aoEscolher={aoEscolher}
                modo="trocar"
                preco={preco}
                catalogo={catalogo}
                titulo={t('Artigo da linha :n', { n })}
                actual={artigo?.id ?? null}
                procuraInicial={inicio}
            />
            )}
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
    marca,
    aoCarregar,
}: {
    artigo: NoCartao;
    indice: number;
    /** «Já no documento» (juntar) ou «Na linha» (trocar). */
    marca: 'junto' | 'actual' | null;
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
                marca === 'junto' ? 'border-emerald-300 bg-emerald-50/50' : marca === 'actual' ? 'border-indigo-300 bg-indigo-50/50' : 'border-slate-200',
            )}
        >
            <div className="flex items-start justify-between gap-2">
                <p className="line-clamp-2 text-sm font-bold text-slate-900">{artigo.name}</p>
                <span className={cls(
                    'flex-none rounded-full px-2 py-0.5 text-[10px] font-semibold',
                    servico ? 'bg-sky-100 text-sky-700' : 'bg-purple-100 text-purple-700',
                )}>
                    {servico ? t('Serviço') : t('Produto')}
                </span>
            </div>

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

            {artigo.categoria && (
                <p className="mt-0.5 flex items-center gap-1.5 truncate text-xs text-slate-400">
                    <i className="fas fa-folder flex-none" aria-hidden="true" />
                    <span className="truncate">{artigo.categoria}</span>
                </p>
            )}

            <div className="mt-auto w-full border-t border-slate-200 pt-3">
                <p className="text-lg font-bold tabular-nums text-indigo-600">
                    {kz(artigo.price)} <span className="text-xs font-normal text-indigo-500/70">Kz</span>
                </p>

                {!servico && artigo.stock !== null && (
                    <p className={cls('mt-1 flex items-center gap-1.5 text-xs font-medium', existencias.tinta)}>
                        <i className={`fas ${existencias.icone} flex-none`} aria-hidden="true" />
                        {existencias.texto}
                    </p>
                )}

                {marca === 'junto' && (
                    <p className="mt-1 flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                        <i className="fas fa-circle-check flex-none" aria-hidden="true" />
                        {t('Já no documento')}
                    </p>
                )}
                {marca === 'actual' && (
                    <p className="mt-1 flex items-center gap-1.5 text-xs font-semibold text-indigo-700">
                        <i className="fas fa-circle-dot flex-none" aria-hidden="true" />
                        {t('Na linha')}
                    </p>
                )}
            </div>
        </button>
    );
}

/**
 * OS ARTIGOS QUE O EDITOR CONHECE: o catálogo que carregou (até 500) e os
 * que se escolheram pela procura. É o que a linha mostra no campo.
 *
 * Um documento aberto com um artigo fora dos 500 mostra, na falta de melhor,
 * o que a linha sabe dele (a descrição), em vez de parecer vazio.
 */
export function useArtigosConhecidos(catalogo: ReadonlyArray<ArtigoDaLinha> | undefined) {
    const [escolhidos, porEscolhidos] = useState<Record<number, ArtigoDaLinha>>({});

    const lembrar = useCallback((a: ArtigoDaLinha) => porEscolhidos((e) => ({ ...e, [a.id]: a })), []);

    const um = useCallback(
        (id: number | null, linha?: { description: string; price: number | string }): ArtigoDaLinha | null => {
            if (id === null) return null;

            const conhecido = catalogo?.find((a) => a.id === id) ?? escolhidos[id];
            if (conhecido) return conhecido;
            if (!linha) return null;

            const nome = eRica(linha.description) ? '' : linha.description.split('\n')[0]?.trim();

            return { id, name: nome || t('Artigo #:id', { id }), code: null, price: Number(linha.price) || 0, unit: '', type: '' };
        },
        [catalogo, escolhidos],
    );

    return { lembrar, um };
}

/**
 * TROCAR O ARTIGO DE UMA LINHA: artigo, preço e descrição.
 *
 * Uma descrição escrita no editor (proformas e orçamentos) não se perde por se
 * trocar o artigo; as outras passam a ser o nome do artigo novo, como sempre.
 */
export function trocarArtigo<L extends { product_id: number | null; description: string; price: number | string }>(
    linhas: L[],
    i: number,
    artigo: ArtigoDaLinha,
): L[] {
    return linhas.map((l, j) =>
        j === i
            ? { ...l, product_id: artigo.id, price: artigo.price, description: eRica(l.description) ? l.description : artigo.name }
            : l,
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
