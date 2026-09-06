import { api } from './cliente';
import type { LinhaCalculada, Totais } from './emissor';

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
    clientes: Array<{ id: number; name: string; nif: string | null; province: string | null }>;
    artigos: Array<{ id: number; name: string; code: string | null; price: number; unit: string; type: string }>;
    armazens: Array<{ id: number; name: string }>;
    series: Array<{ id: number; series_code: string; name: string; document_type: string; is_default: boolean }>;
    formas_de_pagamento: Array<{ id: number; code: string; name: string }>;
    retencoes: Array<{ valor: string; rotulo: string }>;
    regioes: Array<{ valor: string; rotulo: string }>;
    permissoes: { pode_criar: boolean };
};

/** Uma factura aberta no editor: o cabeçalho, as linhas, e se ainda se pode mexer. */
export type FacturaAberta = {
    documento: {
        id: number; numero: string | null; estado: string; pode_editar: boolean;
        client_id: number | null; warehouse_id: number | null; invoice_type: string; series_id: number | null;
        invoice_date: string | null; due_date: string | null; delivery_date: string | null;
        tax_country_region: string | null; payment_method: string | null;
        discount_commercial: number; discount_financial: number;
        withholding_type: string | null; withholding_percentage: number; notes: string | null; pdf: string;
    };
    linhas: LinhaDaFactura[];
};

type Gravada = { id: number; numero: string; total: number; agt: string | null; abrir: string; pdf: string; message: string };

export const factura = {
    opcoes: () => api.ler<OpcoesDaFactura>('/factura/opcoes'),

    calcular: (corpo: Record<string, unknown>) =>
        api.criar<{ linhas: LinhaCalculada[]; totais: Totais }>('/factura/calcular', corpo),

    guardar: (corpo: Record<string, unknown>) => api.criar<Gravada>('/factura', corpo),

    abrir: (id: number) => api.ler<FacturaAberta>(`/factura/${id}`),

    actualizar: (id: number, corpo: Record<string, unknown>) => api.guardar<Gravada>(`/factura/${id}`, corpo),
};
