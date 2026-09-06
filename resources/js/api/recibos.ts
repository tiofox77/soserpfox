import { api } from './cliente';

export type FacturaPorReceber = {
    id: number;
    numero: string;
    data: string | null;
    total: number;
    pago: number;
    falta: number;
};

export type OpcoesDoRecibo = {
    clientes: Array<{ id: number; name: string; nif: string | null }>;
    fornecedores: Array<{ id: number; name: string; nif: string | null }>;
    formas: Array<{ valor: string; rotulo: string }>;
    permissoes: { pode_criar: boolean };
};

/** Um recibo aberto para consulta. É documento fiscal: não se edita. */
export type ReciboAberto = {
    id: number; numero: string | null; type: 'sale' | 'purchase'; estado: string; parte: string | null; factura: string | null;
    payment_date: string; payment_method: string; amount_paid: number; reference: string | null; notes: string | null; pdf: string;
};

export const recibos = {
    opcoes: () => api.ler<OpcoesDoRecibo>('/recibos/opcoes'),
    facturas: (tipo: 'sale' | 'purchase', parteId?: string) =>
        api.ler<{ data: FacturaPorReceber[] }>('/recibos/facturas', { tipo, parte_id: parteId }),
    guardar: (corpo: Record<string, unknown>) =>
        api.criar<{ id: number; numero: string; agt: string | null; abrir: string; message: string }>(
            '/recibos',
            corpo,
        ),
    mostrar: (id: number) => api.ler<{ recibo: ReciboAberto }>(`/recibos/${id}`),
};
