import { api } from './cliente';

export type OpcoesDoSaft = {
    tipos: Array<{ valor: string; rotulo: string }>;
    seccoes: Array<{ chave: string; rotulo: string }>;
    periodo: { de: string; ate: string };
    permissoes: { pode_gerar: boolean };
    descarga: string;
};

export type EstatisticasDoSaft = {
    totalInvoices: number; totalValue: number; totalCreditNotes: number; totalDebitNotes: number; totalReceipts: number;
    totalMovements: number; totalCustomers: number; totalSuppliers: number; totalProducts: number;
};

export type PeriodoDoSaft = { startDate: string; endDate: string; documentType: string };

export const saft = {
    opcoes: () => api.ler<OpcoesDoSaft>('/saft/opcoes'),
    estatisticas: (p: PeriodoDoSaft) => api.ler<{ data: EstatisticasDoSaft }>('/saft/estatisticas', p),
};
