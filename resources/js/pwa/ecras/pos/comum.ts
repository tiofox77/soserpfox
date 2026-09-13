import { etiquetaIntl, t } from '@/i18n';

import type { Produto } from '../../motor/base';
import { formasDeCodigo, numero } from '../../motor/util';

/**
 * AS PEÇAS PURAS DO BALCÃO — o que não desenha nada e não mexe na base.
 *
 * Ficam aqui, fora dos componentes, para as regras de «está esgotado?» e de
 * «quanto já está no carrinho?» serem UMA só: o cartão, o carrinho e o leitor
 * de código de barras perguntam a mesma coisa, e três respostas escritas à mão
 * acabam sempre a discordar num artigo sem gestão de stock.
 */

/** Quantos artigos se desenham de cada vez — com milhares no catálogo, desenhar todos trava um Android barato. */
export const LOTE = 80;

/**
 * Abaixo disto o número do stock sai a âmbar.
 *
 * O catálogo sincronizado NÃO traz o stock mínimo do artigo (o sync não o
 * envia), portanto isto é só um sinal visual — não trava nada nem muda a
 * decisão de vender. O que trava continua a ser o zero.
 */
export const STOCK_BAIXO = 5;

/** Os formatos que a câmara procura — os mesmos do ecrã antigo. */
export const FORMATOS_DE_CODIGO = ['ean_13', 'ean_8', 'code_128', 'code_39', 'qr_code', 'upc_a', 'upc_e'];

/** O ícone do PWA e NÃO o logótipo da empresa: o do tenant não está pré-guardado, e sem rede daria um quadrado partido em cada cartão. */
export const LOGO_DO_POS = '/pwa/icon-192x192.png';

export interface LinhaDoCarrinho {
    product_id: number | null;
    product_name: string;
    quantity: number;
    unit_price: number;
    tax_rate: number;
    discount_percent: number;
}

export type CodigoDoMetodo = 'cash' | 'card' | 'transfer' | 'mobile';

/**
 * DOIS rótulos por forma de pagamento, de propósito:
 *   label   — o que o caixa vê, na língua dele;
 *   labelPt — o que vai na nota do documento fiscal, que sai sempre em
 *             português (é matéria da AGT, não da interface).
 * Juntar os dois numa só chave punha «Cash» dentro de uma fatura angolana.
 */
export interface MetodoDePagamento {
    code: CodigoDoMetodo;
    labelPt: string;
    label: string;
    icon: string;
}

/** Uma linha do pagamento dividido. O valor fica como texto: é o que o campo tem enquanto se escreve. */
export interface LinhaDoPagamento {
    method: CodigoDoMetodo;
    amount: string;
}

export function metodosDePagamento(): MetodoDePagamento[] {
    return [
        { code: 'cash', labelPt: 'Dinheiro', label: t('Dinheiro'), icon: 'fa-money-bill-wave' },
        { code: 'card', labelPt: 'Cartão', label: t('Cartão'), icon: 'fa-credit-card' },
        { code: 'transfer', labelPt: 'Transf.', label: t('Transf.'), icon: 'fa-university' },
        { code: 'mobile', labelPt: 'Multic.', label: t('Multic.'), icon: 'fa-mobile-screen' },
    ];
}

export const eServico = (p: Produto): boolean => p.type === 'servico';

/** Serviços e artigos sem gestão de stock vendem-se sempre. */
export const geraStock = (p: Produto): boolean => !eServico(p) && p.manage_stock !== false;

/** Produto físico, com stock gerido, a zero: não se vende. */
export const esgotado = (p: Produto): boolean => geraStock(p) && numero(p.stock_quantity) <= 0;

/** O id do servidor; os artigos que ainda não subiram não têm id inteiro e a linha vai sem ele. */
export const idDoArtigo = (p: Produto): number | null => (Number.isInteger(p.id) ? (p.id as number) : null);

/** A taxa do artigo — NÃO usar «|| 14»: 0% (isento) é falsy e viraria 14%. */
export function taxaDoArtigo(p: Produto): number {
    const n = parseFloat(String(p.tax_rate ?? ''));

    return Number.isFinite(n) ? n : 0;
}

export function filtrarProdutos(produtos: Produto[], pesquisa: string, categoria: string | null): Produto[] {
    const s = pesquisa.toLowerCase().trim();
    // As formas equivalentes do código (GS1, EAN com e sem o zero à frente…):
    // escrever ou colar o código lido tem de achar o artigo como o leitor acha.
    const formas = s ? formasDeCodigo(pesquisa) : [];
    const contem = (v: unknown) => String(v ?? '').toLowerCase().includes(s);

    return produtos.filter((p) => {
        if (categoria && p.category !== categoria) return false;
        if (!s) return true;

        return (!!p.barcode && formas.includes(String(p.barcode).trim()))
            || contem(p.name)
            || contem(p.sku)
            || contem(p.barcode)
            // Numa farmácia pergunta-se pela substância, não pela marca: quem
            // pede «paracetamol» não sabe se a caixa diz Ben-u-ron. Numa loja de
            // roupa pergunta-se pelo tamanho, que não está no nome nem no código.
            || contem(p.active_ingredient)
            || contem(p.size);
    });
}

export function categoriasDe(produtos: Produto[]): string[] {
    const set = new Set<string>();
    produtos.forEach((p) => { if (p.category) set.add(String(p.category)); });

    return [...set].sort((a, b) => a.localeCompare(b, 'pt'));
}

/** A mesma chave do carrinho: id do servidor E nome (os artigos locais não têm id). */
export function quantidadeNoCarrinho(carrinho: LinhaDoCarrinho[], p: Produto): number {
    const pid = idDoArtigo(p);

    return carrinho
        .filter((i) => i.product_id === pid && i.product_name === p.name)
        .reduce((s, i) => s + i.quantity, 0);
}

/**
 * Algum artigo pede mais do que o stock local?
 *
 * SOMA POR ARTIGO, e não linha a linha: um artigo de preço perguntado ao
 * balcão pode estar em duas linhas (dois preços), e cada linha sozinha cabia no
 * stock enquanto as duas juntas já não.
 */
export function haSobrevenda(carrinho: LinhaDoCarrinho[], produtos: Produto[]): boolean {
    const porId = new Map<number, number>();

    for (const i of carrinho) {
        if (!i.product_id) continue;
        porId.set(i.product_id, (porId.get(i.product_id) ?? 0) + i.quantity);
    }

    for (const [id, qtd] of porId) {
        const prod = produtos.find((p) => p.id === id);
        // Serviços e produtos sem controlo de stock nunca sobrevendem.
        if (!prod || !geraStock(prod)) continue;
        if (qtd > numero(prod.stock_quantity)) return true;
    }

    return false;
}

/** As notas redondas acima do total: 500, 1000 e 5000 — o que o cliente costuma estender. */
export function opcoesDeDinheiroRapido(total: number): number[] {
    if (total <= 0) return [];
    const opcoes = new Set<number>([
        Math.ceil(total / 500) * 500,
        Math.ceil(total / 1000) * 1000,
        Math.ceil(total / 5000) * 5000,
    ]);

    return [...opcoes].filter((v) => v >= total).slice(0, 3);
}

/** O stock como número legível (12, 12,5) — nunca «12.000000». */
export function textoDoStock(v: unknown): string {
    return numero(v).toLocaleString(etiquetaIntl(), { maximumFractionDigits: 3 });
}

/**
 * Um preço escrito à mão: «1500», «1500,50» ou «1.500,50».
 *
 * O ecrã antigo só trocava a vírgula por ponto, e «1.500,50» virava 1,5 — um
 * artigo de mil e quinhentos vendido a um kwanza e meio, sem ninguém dar por ela.
 */
export function precoEscrito(texto: string): number {
    const limpo = texto.trim().replace(/\s/g, '');
    const normal = limpo.includes(',') ? limpo.replace(/\./g, '').replace(',', '.') : limpo;

    return parseFloat(normal);
}

export function vibrar(padrao: number | number[]): void {
    try { navigator.vibrate?.(padrao); } catch { /* nem todos os aparelhos vibram */ }
}
