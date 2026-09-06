import { api } from './cliente';

/** Uma linha de qualquer das listas que partilham forma. */
export type LinhaDeDocumento = {
    id: number;
    /** A série INTERNA, a que a empresa reconhece — é por ela que se procura. */
    numero: string;
    /** A da AGT, logo abaixo; null enquanto a série não estiver registada. */
    numero_agt: string | null;
    /** O selo do Portal AGT, já decidido pelo servidor. */
    agt: {
        natureza: 'propria' | 'fornecedor' | 'nao-fiscal';
        estado: string | null;
        rotulo: string;
        cor: 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo';
    };
    parte: string;
    data: string | null;
    estado: string;
    estado_rotulo: string;
    estado_cor: 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo';
    valor: number;
    /** Só nas facturas de compra, que são as únicas com pagamentos. */
    pago?: number;
    saldo?: number;
    /**
     * As acções que ESTA factura de compra ainda aceita, decididas no
     * servidor pelo mesmo serviço que depois as executa — um botão que
     * aparece e depois recusa é pior do que um botão que não aparece.
     */
    pode_anular?: boolean;
    pode_marcar_paga?: boolean;
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
    /** O que a coluna «Portal AGT» diz neste documento. */
    agt: 'propria' | 'fornecedor' | 'nao-fiscal';
    /** Só nas facturas de compra, e só para quem pode emitir recibos. */
    pode_pagar: boolean;
    /**
     * Se esta lista oferece duplicar. Quais o fazem está no servidor
     * (`TiposDeDocumento::duplicaveis`), com a permissão de criar já pesada.
     */
    pode_duplicar: boolean;
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
