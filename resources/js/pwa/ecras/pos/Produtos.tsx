import { memo } from 'react';

import { t, tn } from '@/i18n';

import { dinheiro } from '../../ganchos';
import type { Produto } from '../../motor/base';
import { numero } from '../../motor/util';
import { eServico, esgotado, geraStock, LOGO_DO_POS, quantidadeNoCarrinho, STOCK_BAIXO, textoDoStock } from './comum';
import type { ControloDoPos } from './usePos';

/**
 * A COLUNA DOS ARTIGOS — a barra de pesquisa e a grelha.
 */
export function ColunaDosProdutos({ pos }: { pos: ControloDoPos }) {
    return (
        <section className="min-w-0 bg-slate-100 lg:h-full lg:flex lg:flex-col lg:overflow-hidden">
            <BarraDoPos pos={pos} />
            <GrelhaDeProdutos pos={pos} />
        </section>
    );
}

function BarraDoPos({ pos }: { pos: ControloDoPos }) {
    const { turno: c } = pos;
    const nPendentes = c.pendentes;
    const nFalhados = pos.falhados.length;

    return (
        /*
         * `lg:static`: no desktop esta barra NÃO pode ser sticky.
         *
         * A partir de lg a coluna dos artigos é uma coluna flex com o seu próprio
         * scroll, e a barra já está fora dela. Sticky com `top-14` empurrava-a
         * 56px para baixo do lugar dela e TAPAVA 44px do topo da primeira fila de
         * cartões — era o corte que se via. No telemóvel continua sticky, porque
         * aí quem rola é a página inteira e a barra tem de acompanhar.
         */
        <div className="sticky top-[var(--pwa-topo,56px)] lg:static z-30 bg-slate-100/95 backdrop-blur px-3 pt-3 pb-2 space-y-2 border-b border-slate-200">
            {/*
              `min-w-0` NÃO É DECORAÇÃO, é o que impede esta barra de sair do ecrã.
              Um item flex recusa-se a encolher abaixo do seu conteúdo, e um
              `<input>` traz uma largura mínima própria do browser (~170px) que o
              `flex-1` não desfaz. Num telemóvel de 375px a caixa de pesquisa não
              cedia, a linha transbordava, e o armazém e o turno ficavam cortados na
              margem direita — sem barra de deslocamento para lá chegar. Precisa de
              estar no contentor E no input: a regra tem de descer toda a cadeia de flex.
            */}
            <div className="flex gap-2 items-center">
                <div className="flex-1 min-w-0 flex items-center gap-2 bg-white rounded-2xl shadow-sm px-3 h-12 border-2 border-transparent focus-within:border-blue-400 transition">
                    <i className="fas fa-magnifying-glass text-gray-400 shrink-0" aria-hidden="true" />
                    <input type="search" inputMode="search" name="pesquisa" autoComplete="off"
                           aria-label={t('Pesquisar ou scan código de barras…')}
                           placeholder={t('Pesquisar ou scan código de barras…')}
                           value={pos.pesquisa}
                           onChange={(e) => pos.mudarPesquisa(e.target.value)}
                           onKeyDown={(e) => {
                               if (e.key === 'Enter') {
                                   e.preventDefault();
                                   pos.lerCodigo(pos.pesquisa);
                               }
                           }}
                           className="flex-1 min-w-0 bg-transparent text-sm focus:outline-none" />
                    {pos.pesquisa && (
                        <button type="button" onClick={() => pos.mudarPesquisa('')} aria-label={t('Limpar')}
                                className="pwa-aparece shrink-0 text-gray-400 hover:text-gray-600 text-xl leading-none px-1">&times;</button>
                    )}
                    <button type="button" onClick={pos.abrirCamara}
                            title={t('Ler código de barras com câmara')} aria-label={t('Ler código de barras com câmara')}
                            className="pwa-toque shrink-0 text-gray-400 hover:text-blue-600 text-lg px-1">
                        <i className="fas fa-camera" aria-hidden="true" />
                    </button>
                </div>

                {/*
                  Armazém activo (o por omissão da empresa).
                  O NOME SÓ APARECE QUANDO HÁ ESPAÇO. É informação de leitura — o
                  armazém não se troca aqui, é sempre o da empresa — e num telemóvel
                  um «Armazém da Ba…» cortado não diz mais do que o ícone e rouba a
                  largura à pesquisa, que é o que se usa a sério. A partir de `sm` o
                  nome volta, inteiro.
                */}
                {pos.armazem && (
                    <div title={t('Armazém: :nome', { nome: String(pos.armazem.name ?? '') })}
                         className="pwa-aparece shrink-0 h-12 px-3 bg-indigo-100 text-indigo-700 rounded-2xl text-[10px] font-bold flex flex-col items-center justify-center">
                        <i className="fas fa-warehouse text-sm" aria-hidden="true" />
                        <span className="hidden sm:block max-w-[80px] truncate">{String(pos.armazem.name ?? '')}</span>
                    </div>
                )}

                {/* Estado do turno — toca-se para abrir ou fechar. */}
                <button type="button" onClick={c.gerir} title={t('Gerir turno')}
                        className={`pwa-toque shrink-0 h-12 px-3 rounded-2xl text-[10px] font-bold flex flex-col items-center justify-center transition ${c.turno.open
                            ? 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700'
                            : 'bg-red-100 hover:bg-red-200 text-red-700 pwa-pulsa'}`}>
                    <i className={`fas ${c.turno.open ? 'fa-lock-open' : 'fa-triangle-exclamation'} text-sm`} aria-hidden="true" />
                    <span>{c.turno.open ? t('Turno') : t('S/ turno')}</span>
                </button>

                {/*
                  Pendentes. Também aparece só com trabalhos FALHADOS: sem isto, um
                  trabalho recusado com zero vendas por enviar deixava a gaveta sem
                  porta — e o «Tentar todos» vive lá dentro.
                */}
                {(nPendentes > 0 || nFalhados > 0) && (
                    <button type="button" onClick={() => pos.setMostrarPendentes(true)}
                            title={t('Documentos Offline')}
                            aria-label={nPendentes > 0
                                ? tn(':n documento por sincronizar|:n documentos por sincronizar', nPendentes, { n: nPendentes })
                                : tn(':n com erro permanente|:n com erro permanente', nFalhados, { n: nFalhados })}
                            className={`pwa-toque pwa-aparece relative shrink-0 h-12 px-3 rounded-2xl text-[10px] font-bold flex flex-col items-center justify-center transition ${nFalhados > 0 && nPendentes === 0
                                ? 'bg-red-100 hover:bg-red-200 text-red-700'
                                : 'bg-amber-100 hover:bg-amber-200 text-amber-700'}`}>
                        <i className={`fas ${nPendentes > 0 ? 'fa-clock' : 'fa-circle-exclamation'} text-sm`} aria-hidden="true" />
                        <span>{nPendentes > 0 ? nPendentes : nFalhados}</span>
                        {nFalhados > 0 && nPendentes > 0 && (
                            <span className="absolute -top-1 -right-1 w-3 h-3 rounded-full bg-red-500 ring-2 ring-slate-100 animate-pulse" aria-hidden="true" />
                        )}
                    </button>
                )}
            </div>

            {/* Categorias */}
            <div className="flex gap-2 overflow-x-auto pb-1 text-xs no-scrollbar" role="group" aria-label={t('Categorias')}>
                <button type="button" onClick={() => pos.mudarCategoria(null)} aria-pressed={!pos.categoria}
                        className={`pwa-toque px-3.5 py-2 rounded-full font-semibold whitespace-nowrap transition ${!pos.categoria ? 'bg-blue-600 text-white shadow' : 'bg-white text-gray-700 hover:bg-blue-50'}`}>
                    <i className="fas fa-border-all mr-1" aria-hidden="true" />{t('Todos')}
                </button>
                {pos.categorias.map((cat) => (
                    <button key={cat} type="button" onClick={() => pos.mudarCategoria(cat)} aria-pressed={pos.categoria === cat}
                            className={`pwa-toque px-3.5 py-2 rounded-full font-semibold whitespace-nowrap transition ${pos.categoria === cat ? 'bg-blue-600 text-white shadow' : 'bg-white text-gray-700 hover:bg-blue-50'}`}>
                        {cat}
                    </button>
                ))}
            </div>
        </div>
    );
}

function GrelhaDeProdutos({ pos }: { pos: ControloDoPos }) {
    const n = pos.filtrados.length;
    const mostrados = Math.min(pos.limite, n);
    const procura = !!pos.pesquisa || !!pos.categoria;

    return (
        <div className="px-3 pt-3 pb-44 lg:pb-4 lg:flex-1 lg:overflow-y-auto"
             onScroll={(e) => {
                 // No ecrã largo quem rola é a coluna: ao chegar perto do fundo, carrega mais.
                 const el = e.currentTarget;
                 if (el.scrollTop + el.clientHeight >= el.scrollHeight - 320) pos.carregarMaisSeHouver();
             }}>
            {/* A chave pela categoria: trocar de categoria refaz a grelha com um aparecer suave. */}
            <div key={pos.categoria ?? '__todos'} className="pwa-aparece grid grid-cols-[repeat(auto-fill,minmax(8.5rem,1fr))] sm:grid-cols-[repeat(auto-fill,minmax(9.5rem,1fr))] 2xl:grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-2.5">
                {pos.visiveis.map((p) => (
                    <CartaoDoArtigo key={String(p.id)} p={p} qtd={quantidadeNoCarrinho(pos.carrinho, p)} aoTocar={pos.adicionar} />
                ))}
            </div>

            {/* Mostrar mais / contador */}
            {n > pos.limite && (
                <div className="mt-4 text-center">
                    <button type="button" onClick={pos.carregarMais}
                            className="pwa-toque bg-white border border-gray-200 hover:border-blue-300 hover:text-blue-700 text-gray-700 px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm transition">
                        <i className="fas fa-chevron-down mr-1" aria-hidden="true" />{t('Mostrar mais')}
                        <span className="text-gray-400 ml-1">(+{Math.min(80, n - pos.limite)})</span>
                    </button>
                </div>
            )}

            {/* Uma frase inteira, e não «X» + « de » + «Y» + « produtos» colados: noutras
                línguas a ordem das palavras é outra, e o plural não se resolve com um «(s)». */}
            {n > 0 && (
                <p className="mt-2 text-center text-[11px] text-gray-400">
                    {tn(':mostrados de :n produto|:mostrados de :n produtos', n, { mostrados, n })}
                </p>
            )}

            {/* Enquanto a base não responde: o esqueleto dos cartões (divs, não botões — não se tocam). */}
            {!pos.catalogoCarregado && (
                <div className="grid grid-cols-[repeat(auto-fill,minmax(8.5rem,1fr))] sm:grid-cols-[repeat(auto-fill,minmax(9.5rem,1fr))] 2xl:grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-2.5" aria-hidden="true">
                    {Array.from({ length: 8 }, (_, i) => (
                        <div key={i} className="bg-white rounded-2xl shadow-sm p-2.5 border border-gray-100 animate-pulse">
                            <div className="w-full h-20 sm:h-24 rounded-xl bg-slate-100 mb-2" />
                            <div className="h-3 bg-slate-100 rounded w-4/5 mb-1.5" />
                            <div className="h-2.5 bg-slate-100 rounded w-2/5 mb-3" />
                            <div className="h-3.5 bg-blue-50 rounded w-1/2" />
                        </div>
                    ))}
                </div>
            )}

            {pos.catalogoCarregado && n === 0 && (
                <div className="pwa-entra text-center py-16 text-gray-400">
                    <i className="fas fa-box-open text-5xl mb-3 block opacity-40 pwa-flutua" aria-hidden="true" />
                    <p className="text-sm font-medium">{procura ? t('Nenhum produto encontrado') : t('Sem produtos sincronizados')}</p>
                    {!procura && (
                        <button type="button" onClick={() => void pos.sincronizarAgora()} disabled={pos.aSincronizar}
                                className="pwa-toque mt-3 text-xs bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold shadow disabled:opacity-60">
                            <i className={`fas fa-rotate mr-1 ${pos.aSincronizar ? 'fa-spin' : ''}`} aria-hidden="true" />{t('Sincronizar catálogo')}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * Um cartão de artigo.
 *
 * `memo`: a grelha tem 80 cartões e o carrinho muda a cada toque — sem isto,
 * cada «+1» redesenhava a grelha inteira num telemóvel que já vai apertado.
 */
const CartaoDoArtigo = memo(function CartaoDoArtigo({ p, qtd, aoTocar }: { p: Produto; qtd: number; aoTocar: (p: Produto) => Promise<void> }) {
    const semStock = esgotado(p);
    const servico = eServico(p);
    const gere = geraStock(p);
    const stock = numero(p.stock_quantity);
    const detalhe = [p.dosage, p.pharmaceutical_form, p.active_ingredient].filter(Boolean).join(' · ') || String(p.name ?? '');
    const temCrachas = !!(p.is_controlled || p.requires_prescription || p.dosage || p.size || p.color);

    return (
        <button type="button" onClick={() => void aoTocar(p)} disabled={semStock}
                className={`pwa-cartao relative bg-white rounded-2xl shadow-sm p-2.5 text-left flex flex-col border border-gray-100 hover:border-blue-300 hover:shadow-md active:scale-95 transition ${semStock ? 'opacity-50 cursor-not-allowed' : ''}`}>
            {/*
              A miniatura tem ALTURA FIXA e baixa.

              Era `aspect-square`: num ecrã largo, com cinco colunas, dava 250px de
              altura para mostrar um ícone — cinco artigos enchiam o ecrã todo e
              parecia que o catálogo estava vazio. Com 80px cabem três vezes mais
              artigos e o preço, que é o que se procura, fica sempre à vista.

              O logótipo em marca de água em vez de um ícone genérico: os artigos
              não trazem fotografia (o sync não a envia, e offline não haveria como
              a ir buscar), portanto o espaço é sempre placeholder — mais vale que
              seja o da casa.
            */}
            <div className="relative w-full h-20 sm:h-24 rounded-xl bg-gradient-to-br from-slate-50 to-blue-50 border border-slate-100 flex items-center justify-center mb-2 overflow-hidden">
                <img src={LOGO_DO_POS} alt="" draggable={false}
                     className="h-8 sm:h-10 w-auto object-contain opacity-25 select-none pointer-events-none" />

                <span className="absolute bottom-1 left-1 w-5 h-5 rounded-full bg-white/85 flex items-center justify-center shadow-sm">
                    <i className={`fas ${servico ? 'fa-concierge-bell text-purple-500' : 'fa-box text-blue-500'} text-[10px]`} aria-hidden="true" />
                </span>

                {qtd > 0 && (
                    // A chave pela quantidade: cada toque faz o número «saltar» — confirma que entrou.
                    <span key={qtd} className="pwa-cresce absolute top-1 right-1 bg-emerald-600 text-white text-[11px] font-bold min-w-[24px] h-6 px-1 rounded-full flex items-center justify-center shadow"
                          aria-label={t('Quantidade')}>
                        {qtd}
                    </span>
                )}

                {semStock && (
                    <span className="absolute inset-0 rounded-xl bg-red-500/15 flex items-center justify-center">
                        <span className="bg-red-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">{t('ESGOTADO')}</span>
                    </span>
                )}
            </div>

            <p className="font-semibold text-[13px] leading-tight line-clamp-2 mb-0.5 text-slate-800" title={detalhe}>{String(p.name ?? '')}</p>
            {p.sku && <p className="text-[10px] text-gray-400 truncate">{String(p.sku)}</p>}

            {/*
              Crachás de balcão: só o que muda a decisão no momento de escolher. A
              receita e o psicotrópico mudam o que há a pedir ao cliente antes de
              entregar; a dosagem, o tamanho e a cor decidem qual dos cartões iguais
              é o certo. Os valores são dados da empresa e saem como estão gravados.
            */}
            {temCrachas && (
                <div className="flex flex-wrap items-center gap-1 mt-1">
                    {!!p.is_controlled && (
                        <span className="text-[9px] font-bold bg-red-600 text-white px-1.5 py-0.5 rounded-full whitespace-nowrap"
                              title={t('Psicotrópico ou estupefaciente — venda sujeita a registo obrigatório')}>
                            <i className="fas fa-triangle-exclamation" aria-hidden="true" /> {t('CONTROLADO')}
                        </span>
                    )}
                    {!!p.requires_prescription && (
                        <span className="text-[9px] font-bold bg-amber-500 text-white px-1.5 py-0.5 rounded-full whitespace-nowrap"
                              title={t('Exige receita médica')}>
                            <i className="fas fa-prescription" aria-hidden="true" /> {t('RECEITA')}
                        </span>
                    )}
                    {!!p.dosage && (
                        <span className="text-[9px] font-semibold bg-sky-100 text-sky-800 px-1.5 py-0.5 rounded-full whitespace-nowrap" title={t('Dosagem')}>{String(p.dosage)}</span>
                    )}
                    {!!p.size && (
                        <span className="text-[9px] font-semibold bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded-full whitespace-nowrap" title={t('Tamanho')}>{String(p.size)}</span>
                    )}
                    {!!p.color && (
                        <span className="text-[9px] font-semibold bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full whitespace-nowrap" title={t('Cor')}>{String(p.color)}</span>
                    )}
                </div>
            )}

            <div className="mt-auto pt-1.5 flex items-center justify-between gap-1">
                <span className="font-bold text-blue-700 text-sm whitespace-nowrap">{dinheiro(p.price)}</span>

                {/*
                  Três estados, os mesmos do POS online: com stock gerido mostra-se o
                  número (a âmbar quando já é pouco); serviço diz-se que é serviço; e
                  um produto sem gestão de stock diz que se vende sempre — um zero ali
                  parecia falta de stock.
                */}
                {gere && (
                    <span className={`text-[10px] font-semibold px-1.5 py-0.5 rounded-full whitespace-nowrap ${stock <= 0
                        ? 'bg-red-100 text-red-600'
                        : stock <= STOCK_BAIXO ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500'}`}>
                        {stock > 0 ? textoDoStock(stock) : t('Esgot.')}
                    </span>
                )}
                {servico && (
                    <span className="text-[10px] font-bold bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full whitespace-nowrap">{t('Serviço')}</span>
                )}
                {!servico && p.manage_stock === false && (
                    <span className="text-[10px] font-bold bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded-full whitespace-nowrap">{t('Sem stock gerido')}</span>
                )}
            </div>
        </button>
    );
});
