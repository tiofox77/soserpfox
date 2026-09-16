import type { Registo } from './base';
import { arredondar2, numero } from './util';

/**
 * O FECHO COM PRODUTOS NO APARELHO (16/09/2026) — a mesma pergunta do balcão
 * online, feita com as vendas que este aparelho guardou.
 *
 * Só as vendas DESDE A ABERTURA do turno: o aparelho guarda 30 dias de vendas
 * sincronizadas, e o relatório de fecho somava-as todas.
 *
 * O total de cada linha segue `totaisDaVenda`: o desconto comercial da venda
 * (em %) sai da base, e o IVA incide sobre o que fica.
 */
export interface ProdutoVendido {
    chave: string;
    nome: string;
    quantidade: number;
    total: number;
    vendas: number;
}

export interface VendasPorProdutoLocal {
    produtos: ProdutoVendido[];
    quantidade: number;
    total: number;
    vendas: number;
}

export function vendasDesdeAAbertura(openedAt: string | null | undefined, vendas: Registo[]): Registo[] {
    const abertura = openedAt ? new Date(openedAt).getTime() : Number.NaN;

    if (Number.isNaN(abertura)) return vendas;

    return vendas.filter((v) => new Date(v.created_at).getTime() >= abertura);
}

export function produtosVendidos(vendas: Registo[]): VendasPorProdutoLocal {
    const mapa = new Map<string, ProdutoVendido & { docs: Set<string> }>();
    let quantidade = 0;
    let total = 0;

    vendas.forEach((v, i) => {
        const fica = 1 - numero(v.discount_commercial) / 100;
        const doc = String(v.local_uuid ?? i);

        for (const it of (v.items || []) as Registo[]) {
            const nome = String(it.product_name || it.name || it.description || '').trim() || '—';
            const chave = Number.isInteger(it.product_id) ? `p${it.product_id}` : `n${nome.toLowerCase()}`;
            const q = numero(it.quantity);
            const base = q * numero(it.unit_price) * fica;
            const linha = base + base * numero(it.tax_rate) / 100;

            const p = mapa.get(chave) ?? { chave, nome, quantidade: 0, total: 0, vendas: 0, docs: new Set<string>() };
            p.quantidade += q;
            p.total += linha;
            p.docs.add(doc);
            mapa.set(chave, p);

            quantidade += q;
            total += linha;
        }
    });

    const produtos = [...mapa.values()]
        .map(({ docs, ...p }) => ({ ...p, quantidade: Math.round(p.quantidade * 1000) / 1000, total: arredondar2(p.total), vendas: docs.size }))
        .sort((a, b) => b.total - a.total || a.nome.localeCompare(b.nome));

    return { produtos, quantidade: Math.round(quantidade * 1000) / 1000, total: arredondar2(total), vendas: vendas.length };
}
