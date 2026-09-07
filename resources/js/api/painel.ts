import { api } from './cliente';

/** Um atalho de período. Os rótulos vêm do servidor já traduzidos. */
export type PeriodoDoPainel = {
    valor: string;
    rotulo: string;
    rotulo_anterior: string;
    de: string;
    ate: string;
    opcoes: Array<{ valor: string; rotulo: string }>;
};

export type NumerosDoPainel = {
    periodo: PeriodoDoPainel;
    stats: {
        total_invoiced: number;
        total_invoiced_previous: number;
        total_received: number;
        total_pending: number;
        total_overdue: number;
        growth: number;
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
    /** A linha do gráfico do período escolhido: dias ou meses, conforme ele. */
    serie: Array<{ data: string; rotulo: string; valor: number }>;
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

    /** Os três gráficos do período, já em pares prontos a desenhar. */
    graficos: {
        meios_de_pagamento: Array<{ rotulo: string; valor: number }>;
        top_produtos: Array<{ rotulo: string; valor: number }>;
        /** A folga vem contada do servidor: vendas − compras, ponto a ponto. */
        vendas_contra_compras: Array<{
            rotulo: string;
            vendas: number;
            compras: number;
            folga: number;
        }>;
    };

    /** As últimas facturas criadas — com que o painel de sempre fechava. */
    actividades: Array<{
        id: number;
        numero: string;
        cliente: string;
        quando: string | null;
        estado: string;
        cor: string;
    }>;
};

export const painel = {
    /** O período viaja no pedido: quem conta é o servidor, não o ecrã. */
    numeros: (periodo?: string) => api.ler<NumerosDoPainel>('/painel', periodo ? { periodo } : {}),
};
