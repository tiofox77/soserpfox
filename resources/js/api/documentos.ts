import { api } from './cliente';

/** Uma linha de qualquer das listas que partilham forma. */
export type LinhaDeDocumento = {
    id: number;
    numero: string;
    parte: string;
    data: string | null;
    estado: string;
    estado_rotulo: string;
    estado_cor: 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo';
    valor: number;
    /** Só nas facturas de compra, que são as únicas com pagamentos. */
    pago?: number;
    saldo?: number;
};

export type PaginaDeDocumentos = {
    data: LinhaDeDocumento[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
};

export type OpcoesDosDocumentos = {
    titulo: string;
    /** 'cliente' ou 'fornecedor' — é o cabeçalho da coluna. */
    parte: string;
    rota: string;
    tem_saldo: boolean;
    estados: Array<{ valor: string; rotulo: string }>;
};

export type FiltrosDeDocumentos = {
    procura?: string;
    estado?: string;
    de?: string;
    ate?: string;
    por_pagina?: number;
    page?: number;
};

export const documentos = {
    lista: (tipo: string, filtros: FiltrosDeDocumentos) =>
        api.ler<PaginaDeDocumentos>(`/documentos/${tipo}`, filtros),
    opcoes: (tipo: string) => api.ler<OpcoesDosDocumentos>(`/documentos/${tipo}/opcoes`),
};
