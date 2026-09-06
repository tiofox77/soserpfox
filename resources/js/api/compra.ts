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

/** O CONTEÚDO COMERCIAL de uma compra: o que se aproveita ao duplicar. */
export type ConteudoDaCompra = {
    supplier_id: number | null; warehouse_id: number | null; invoice_date: string | null; due_date: string | null;
    tax_country_region: string; is_service: boolean; discount_commercial: number; discount_financial: number; notes: string | null;
};

/** Uma compra aberta no editor. Só um rascunho se altera: a registada já deu entrada do stock. */
export type CompraAberta = {
    documento: ConteudoDaCompra & { id: number; numero: string | null; estado: string; pode_editar: boolean };
    linhas: LinhaDaCompra[];
};

/** O conteúdo de uma compra para NASCER OUTRA VEZ — sem número nem estado. */
export type CompraDuplicada = {
    origem: { id: number; numero: string | null };
    documento: ConteudoDaCompra;
    linhas: LinhaDaCompra[];
};

type Gravada = { id: number; numero: string; total: number; abrir: string; message: string };

/** O que o servidor diz depois de anular ou de dar a compra por paga. */
type Mudada = { estado: string; message: string };

export const compra = {
    opcoes: () => api.ler<OpcoesDaCompra>('/compra/opcoes'),
    calcular: (corpo: Record<string, unknown>) =>
        api.criar<{ linhas: LinhaCalculada[]; totais: Totais }>('/compra/calcular', corpo),
    guardar: (corpo: Record<string, unknown>) => api.criar<Gravada>('/compra', corpo),
    abrir: (id: number) => api.ler<CompraAberta>(`/compra/${id}`),
    /** Duplicar NÃO grava e NÃO mexe em stock: traz o conteúdo para o editor. */
    duplicar: (id: number) => api.ler<CompraDuplicada>(`/compra/${id}/duplicar`),
    actualizar: (id: number, corpo: Record<string, unknown>) => api.guardar<Gravada>(`/compra/${id}`, corpo),

    /*
     * Anular é POST e não DELETE de propósito: a factura de compra NÃO se
     * apaga — muda de estado, e o stock que tinha entrado é revertido.
     */
    anular: (id: number) => api.criar<Mudada>(`/compra/${id}/anular`, {}),
    marcarComoPaga: (id: number) => api.criar<Mudada>(`/compra/${id}/pagar`, {}),
};
