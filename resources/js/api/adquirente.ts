import { api } from './cliente';

/**
 * AS FACTURAS RECEBIDAS (ADQUIRENTE) — DS.120 §§4.3, 4.4, 4.7.
 *
 * Quem fala com a AGT é o servidor (`PainelDoAdquirente`): daqui só saem o
 * período, o número do documento e a decisão. Confirmar e rejeitar escrevem
 * lá — e só no ambiente em que a empresa emite.
 */

export type Ambiente = 'sandbox' | 'production';

export type Accao = 'C' | 'R';

export type EstadoDoAdquirente = {
    empresa: { id: number; nome: string; nif: string | null };
    ambiente: Ambiente;
    rotulo: string;
    em_falta: string[];
    periodo: { de: string; ate: string };
    accoes: Array<{ valor: Accao; rotulo: string }>;
};

export type FacturaRecebida = {
    numero: string | null;
    tipo: string | null;
    data: string | null;
    estado: string | null;
    estado_descricao: string | null;
    emissor: string | null;
    liquido: number | null;
    total: number | null;
};

export type ListaDoAdquirente = {
    periodo: { de: string; ate: string };
    total: number;
    facturas: FacturaRecebida[];
};

export type DetalheDoAdquirente = {
    documento: string;
    estado: string | null;
    detalhe: Record<string, unknown>;
};

export type ResultadoDaValidacao = {
    documento: string;
    accao: Accao;
    actionResultCode: string | null;
    documentStatusCode: string | null;
    mensagem: string;
};

export type Validacao = {
    documento: string;
    accao: Accao;
    percentagem_iva_dedutivel?: number | null;
    valor_nao_dedutivel?: number | null;
    ambiente?: Ambiente;
};

export const adquirente = {
    estado: () => api.ler<{ data: EstadoDoAdquirente; permissoes: { pode_validar: boolean } }>('/adquirente/estado'),

    listar: (de: string, ate: string, ambiente?: Ambiente) =>
        api.ler<{ data: ListaDoAdquirente }>('/adquirente/facturas', { de, ate, ambiente }),

    detalhe: (documento: string, ambiente?: Ambiente) =>
        api.ler<{ data: DetalheDoAdquirente }>('/adquirente/factura', { documento, ambiente }),

    validar: (corpo: Validacao) =>
        api.criar<{ data: ResultadoDaValidacao; message: string }>('/adquirente/validar', corpo),
};
