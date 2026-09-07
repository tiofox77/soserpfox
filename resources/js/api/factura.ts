import { api } from './cliente';
import type { LinhaCalculada, Totais } from './emissor';
import type { CriarParte } from './partes';

export type LinhaDaFactura = {
    product_id: number | null;
    description: string;
    quantity: number | string;
    price: number | string;
    discount_percent: number | string;
    iec?: string | null;
    is?: string | null;
};

export type OpcoesDaFactura = {
    clientes: Array<{ id: number; name: string; nif: string | null; province: string | null; payment_term_days: number }>;
    artigos: Array<{ id: number; name: string; code: string | null; price: number; unit: string; type: string }>;
    armazens: Array<{ id: number; name: string }>;
    series: Array<{ id: number; series_code: string; name: string; document_type: string; is_default: boolean }>;
    formas_de_pagamento: Array<{ id: number; code: string; name: string }>;
    retencoes: Array<{ valor: string; rotulo: string }>;
    regioes: Array<{ valor: string; rotulo: string }>;
    /** O CLIENTE RÁPIDO: se se pode criar aqui, e com que país por omissão. */
    criar_parte: CriarParte;
    permissoes: { pode_criar: boolean };
};

/**
 * O CONTEÚDO COMERCIAL de uma factura: o que se aproveita ao duplicar.
 *
 * Está separado da identidade (número, série, hash, estado) de propósito: é
 * exactamente esta a fronteira que o `DuplicaDocumento` guarda do lado do
 * servidor, e tê-la também no tipo faz o compilador recusar um duplicado que
 * traga o que não deve.
 */
export type ConteudoDaFactura = {
    client_id: number | null; warehouse_id: number | null; invoice_type: string;
    invoice_date: string | null; due_date: string | null; delivery_date: string | null;
    /** ONDE os bens são entregues — sai no documento, ao lado da data de entrega. */
    delivery_location: string | null;
    tax_country_region: string | null; payment_method: string | null;
    discount_commercial: number; discount_financial: number;
    withholding_type: string | null; withholding_percentage: number; notes: string | null;
    /** As condições que saem no papel (termos e condições). */
    terms: string | null;
};

/** Uma factura aberta no editor: o cabeçalho, as linhas, e se ainda se pode mexer. */
export type FacturaAberta = {
    documento: ConteudoDaFactura & {
        id: number; numero: string | null; estado: string; pode_editar: boolean;
        series_id: number | null; pdf: string;
    };
    linhas: LinhaDaFactura[];
};

/**
 * O conteúdo de uma factura para NASCER OUTRA VEZ.
 *
 * Sem `id`, sem número, sem série e sem estado — não é um documento, é o que
 * se escreve num documento. `origem` existe só para o ecrã poder dizer de
 * onde isto veio.
 */
export type FacturaDuplicada = {
    origem: { id: number; numero: string | null };
    documento: ConteudoDaFactura;
    linhas: LinhaDaFactura[];
};

type Gravada = { id: number; numero: string; total: number; agt: string | null; abrir: string; pdf: string; message: string };

export const factura = {
    opcoes: () => api.ler<OpcoesDaFactura>('/factura/opcoes'),

    calcular: (corpo: Record<string, unknown>) =>
        api.criar<{ linhas: LinhaCalculada[]; totais: Totais }>('/factura/calcular', corpo),

    guardar: (corpo: Record<string, unknown>) => api.criar<Gravada>('/factura', corpo),

    abrir: (id: number) => api.ler<FacturaAberta>(`/factura/${id}`),

    /** Duplicar NÃO grava: traz o conteúdo para o editor abrir em branco. */
    duplicar: (id: number) => api.ler<FacturaDuplicada>(`/factura/${id}/duplicar`),

    actualizar: (id: number, corpo: Record<string, unknown>) => api.guardar<Gravada>(`/factura/${id}`, corpo),
};
