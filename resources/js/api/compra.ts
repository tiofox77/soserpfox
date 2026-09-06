import { api } from './cliente';
import type { LinhaCalculada, Totais } from './emissor';

/** Uma linha da factura de compra. O lote e a validade nascem aqui. */
export type LinhaDaCompra = {
    product_id: number | null;
    description: string;
    quantity: number | string;
    price: number | string;
    discount_percent: number | string;
    batch_number: string;
    expiry_date: string;
};

export type OpcoesDaCompra = {
    fornecedores: Array<{ id: number; name: string; nif: string | null }>;
    artigos: Array<{ id: number; name: string; code: string | null; cost: number; unit: string; type: string }>;
    armazens: Array<{ id: number; name: string }>;
    regioes: Array<{ valor: string; rotulo: string }>;
    estados: Array<{ valor: string; rotulo: string }>;
    permissoes: { pode_criar: boolean };
};

/** Uma compra aberta no editor. Só um rascunho se altera: a registada já deu entrada do stock. */
export type CompraAberta = {
    documento: {
        id: number; numero: string | null; estado: string; pode_editar: boolean;
        supplier_id: number | null; warehouse_id: number | null; invoice_date: string | null; due_date: string | null;
        tax_country_region: string; is_service: boolean; discount_commercial: number; discount_financial: number; notes: string | null;
    };
    linhas: LinhaDaCompra[];
};

type Gravada = { id: number; numero: string; total: number; abrir: string; message: string };

export const compra = {
    opcoes: () => api.ler<OpcoesDaCompra>('/compra/opcoes'),
    calcular: (corpo: Record<string, unknown>) =>
        api.criar<{ linhas: LinhaCalculada[]; totais: Totais }>('/compra/calcular', corpo),
    guardar: (corpo: Record<string, unknown>) => api.criar<Gravada>('/compra', corpo),
    abrir: (id: number) => api.ler<CompraAberta>(`/compra/${id}`),
    actualizar: (id: number, corpo: Record<string, unknown>) => api.guardar<Gravada>(`/compra/${id}`, corpo),
};
