import { api, type Pagina } from './cliente';

export type Artigo = {
    id: number;
    name: string;
    code: string | null;
    sku: string | null;
    barcode: string | null;
    type: 'produto' | 'servico';
    tipo_rotulo: string;
    description: string | null;
    unit: string;
    category: { id: number; name: string } | null;
    category_id: number | null;
    price: number;
    cost: number | null;
    tax_type: 'iva' | 'isento';
    tax_rate_id: number | null;
    taxa: number | null;
    exemption_reason: string | null;
    manage_stock: boolean;
    /** Null num serviço: um serviço não tem stock nenhum. */
    stock: number | null;
    stock_min: number | null;
    stock_max: number | null;
    em_falta: boolean;
    esgotado: boolean;
    is_active: boolean;
};

/**
 * O que se envia ao gravar.
 *
 * `stock_quantity` só existe na CRIAÇÃO — numa edição o servidor ignora-o, e
 * é de propósito: o stock é derivado das linhas e ajusta-se na Gestão de
 * Stock, com movimento registado.
 */
export type ArtigoParaGravar = {
    name: string;
    type: 'produto' | 'servico';
    description: string | null;
    sku: string | null;
    barcode: string | null;
    price: number | string;
    cost: number | string | null;
    unit: string;
    category_id: number | string;
    tax_type: 'iva' | 'isento';
    tax_rate_id: number | string | null;
    exemption_reason: string | null;
    manage_stock: boolean;
    stock_min: number | string | null;
    stock_max: number | string | null;
    is_active: boolean;
    stock_quantity?: number | string;
};

export type FiltrosDeArtigos = {
    procura?: string;
    tipo?: string;
    categoria?: string;
    activo?: string;
    so_em_falta?: string;
    por_pagina?: number;
    page?: number;
};

export type OpcoesDosArtigos = {
    categorias: Array<{ id: number; name: string }>;
    taxas: Array<{ id: number; name: string; rate: number }>;
    unidades: string[];
    permissoes: { pode_criar: boolean; pode_editar: boolean; pode_apagar: boolean };
};

export const produtos = {
    lista: (filtros: FiltrosDeArtigos) => api.ler<Pagina<Artigo>>('/products', filtros),
    opcoes: () => api.ler<OpcoesDosArtigos>('/products/opcoes'),
    criar: (dados: ArtigoParaGravar) => api.criar<{ data: Artigo }>('/products', dados),
    guardar: (id: number, dados: ArtigoParaGravar) =>
        api.guardar<{ data: Artigo }>(`/products/${id}`, dados),
    apagar: (id: number) => api.apagar<{ message: string; desactivado: boolean }>(`/products/${id}`),
};
