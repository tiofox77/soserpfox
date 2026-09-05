import { api } from './cliente';

export type NumerosDoPainel = {
    stats: {
        total_invoiced: number;
        total_invoiced_last_month: number;
        total_received: number;
        total_pending: number;
        total_overdue: number;
        year_invoiced: number;
        year_invoiced_previous: number;
        growth: number;
        year_growth: number;
    };
    documentos: {
        invoices: number;
        credit_notes: number;
        debit_notes: number;
        receipts: number;
        advances: number;
    };
    estado_das_facturas: {
        paid: number;
        pending: number;
        partially_paid: number;
        overdue: number;
    };
    por_mes: Array<{ rotulo: string; valor: number }>;
    por_mes_ano_passado: Array<{ rotulo: string; valor: number }>;
    por_cobrar: Array<{
        id: number;
        numero: string;
        cliente: string;
        vencimento: string | null;
        vencida: boolean;
        saldo: number;
    }>;
    melhores_clientes: Array<{ cliente: string; total: number; documentos: number }>;
};

export const painel = {
    numeros: () => api.ler<NumerosDoPainel>('/painel'),
};
