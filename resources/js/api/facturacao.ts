import { api, type Pagina } from './cliente';

/**
 * Os tipos do que a facturação devolve.
 *
 * Escritos à mão e não gerados, de propósito: escrevê-los obriga a olhar para
 * o `SalesInvoiceResource` e a decidir o que sai. Um tipo gerado a partir do
 * modelo publicava tudo o que a tabela tem — que é exactamente o que o
 * Resource existe para impedir.
 */

export type Cor = 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo';

export type FacturaDeVenda = {
    id: number;
    numero: string;
    numero_agt: string | null;
    tipo: 'FT' | 'FR';
    cliente: { id: number | null; nome: string; nif: string | null };
    data: string | null;
    vencimento: string | null;
    estado: string;
    estado_rotulo: string;
    estado_cor: Cor;
    total: number;
    pago: number;
    saldo: number;
    agt: { comunicada: boolean; rotulo: string };
    armazem: string | null;
    autor: string | null;
    pode_creditar: boolean;
    pode_receber: boolean;
    e_rascunho: boolean;
};

export type FiltrosDeFacturas = {
    procura?: string;
    estado?: string;
    tipo?: string;
    armazem?: string;
    autor?: string;
    de?: string;
    ate?: string;
    por_pagina?: number;
    page?: number;
};

/**
 * Os cartões do topo da lista.
 *
 * Vêm somados do SERVIDOR sobre a consulta já filtrada — não são a soma da
 * página à vista, que mudava ao carregar em «Seguinte». A regra («por receber»
 * é o que falta, nunca o nome do estado) vive no `SomasDasFacturas`.
 */
export type SomasDasFacturas = {
    facturado: number;
    por_receber: number;
    vencido: number;
};

/** A página de facturas traz as somas dentro do `meta`, ao lado das contagens. */
export type PaginaDeFacturas = Omit<Pagina<FacturaDeVenda>, 'meta'> & {
    meta: Pagina<FacturaDeVenda>['meta'] & { somas: SomasDasFacturas };
};

export type OpcoesDasFacturas = {
    armazens: Array<{ id: number; name: string }>;
    estados: Array<{ valor: string; rotulo: string }>;
    autores: Array<{ id: number; name: string }>;
    permissoes: {
        ve_de_todos: boolean;
        pode_criar: boolean;
        pode_creditar: boolean;
        pode_receber: boolean;
        pode_editar: boolean;
    };
};

export const facturacao = {
    facturasDeVenda: (filtros: FiltrosDeFacturas) =>
        api.ler<PaginaDeFacturas>('/sales-invoices', filtros),

    opcoesDasFacturas: () => api.ler<OpcoesDasFacturas>('/sales-invoices/opcoes'),

    /**
     * Fechar a conta de uma factura paga por fora, sem recibo.
     *
     * O segundo botão verde da lista de sempre. Quem quer o dinheiro
     * registado usa o outro — este só muda o estado.
     */
    marcarFacturaComoPaga: (id: number) =>
        api.criar<{ estado: string; message: string }>(`/sales-invoices/${id}/pagar`, {}),
};
