import { api } from './cliente';
import type { LinhaCalculada, Totais } from './emissor';

export type LinhaDaFactura = {
    product_id: number | null;
    description: string;
    quantity: number | string;
    price: number | string;
    discount_percent: number | string;
    iec?: string;
    is?: string;
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

export const factura = {
    opcoes: () => api.ler<OpcoesDaFactura>('/factura/opcoes'),

    calcular: (corpo: Record<string, unknown>) =>
        api.criar<{ linhas: LinhaCalculada[]; totais: Totais }>('/factura/calcular', corpo),

    guardar: (corpo: Record<string, unknown>) =>
        api.criar<{ id: number; numero: string; total: number; agt: string | null; abrir: string; pdf: string; message: string }>(
            '/factura',
            corpo,
        ),
};
