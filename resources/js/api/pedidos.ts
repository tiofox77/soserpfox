import { api } from './cliente';
import type { Cor } from '@/ui/tokens';

/**
 * OS SEIS PEDIDOS DO RH — férias, licenças, horas extras, turno nocturno,
 * adiantamentos e descontos.
 *
 * Todos têm a mesma forma: alguém pede, alguém aprova ou recusa com um
 * motivo, e depois paga-se. O que muda entre eles vem do servidor
 * (`PedidosDeRh`) como esquema — o ecrã não sabe o que é um pedido de férias.
 */

export type Escolha = { valor: string; rotulo: string };

export type CampoDoPedido = {
    chave: string;
    rotulo: string;
    tipo: 'texto' | 'textarea' | 'numero' | 'dinheiro' | 'data' | 'hora' | 'escolha' | 'booleano' | 'funcionario';
    obrigatorio?: boolean;
    omissao?: string | number | boolean | null;
    largura?: 'meia' | 'inteira';
    opcoes?: Escolha[];
    ajuda?: string;
    passo?: number;
    min?: number;
    max?: number;
};

export type ColunaDoPedido = {
    chave: string;
    rotulo: string;
    formato: 'texto' | 'numero' | 'dinheiro' | 'data' | 'escolha';
};

export type OpcoesDoPedido = {
    titulo: string;
    singular: string;
    novo: string;
    icone: string;
    cor: string;
    descricao: string;
    rota: string;
    /** A morada do PDF, com `:id` por substituir. Nulo onde não há papel. */
    pdf: string | null;
    campos: CampoDoPedido[];
    colunas: ColunaDoPedido[];
    estados: Array<{ valor: string; rotulo: string; cor: Cor }>;
    accoes: { aprovar: boolean; rejeitar: boolean; pagar: boolean; cancelar: boolean; apagar: boolean };
    valor: { coluna: string; rotulo: string };
    anexo: { rotulo: string } | null;
    /** Só o adiantamento: aprovar é decidir QUANTO, e tem formulário próprio. */
    ao_aprovar: { campos: CampoDoPedido[] } | null;
    /** Se este pedido ocupa DIAS, e por isso tem calendário. */
    calendario: boolean;
    funcionarios: Array<Escolha & { nota: string | null }>;
    permissoes: { pode_criar: boolean; pode_aprovar: boolean; pode_apagar: boolean };
};

export type LinhaDePedido = {
    id: number;
    numero: string;
    funcionario: string;
    estado: string;
    valor: number;
    criado_em: string | null;
    rotulos?: Record<string, string>;
} & Record<string, unknown>;

export type FichaDePedido = LinhaDePedido & {
    decisao: {
        aprovado_por: string | null;
        aprovado_em: string | null;
        recusado_por: string | null;
        recusado_em: string | null;
        motivo_da_recusa: string | null;
    };
    anexo: string | null;
};

export type ResumoDosPedidos = {
    total: number;
    por_estado: Array<{ valor: string; rotulo: string; cor: Cor; quantos: number }>;
    valor: number;
};

/** Uma barra no calendário: um pedido que ocupa dias. */
export type EventoDoCalendario = {
    id: number;
    numero: string;
    funcionario: string;
    de: string | null;
    ate: string | null;
    estado: string;
};

export type FiltrosDePedidos = {
    procura?: string;
    estado?: string;
    funcionario?: string;
    ano?: number;
    page?: number;
    por_pagina?: number;
};

const raiz = (tipo: string) => `/rh/pedidos/${tipo}`;

export const pedidos = {
    opcoes: (tipo: string) => api.ler<OpcoesDoPedido>(`${raiz(tipo)}/opcoes`),

    lista: (tipo: string, filtros: FiltrosDePedidos) =>
        api.ler<{
            data: LinhaDePedido[];
            meta: { total: number; current_page: number; last_page: number };
            resumo: ResumoDosPedidos;
        }>(raiz(tipo), filtros),

    ficha: (tipo: string, id: number) => api.ler<{ documento: FichaDePedido }>(`${raiz(tipo)}/${id}`),

    guardar: (tipo: string, dados: Record<string, unknown>) =>
        api.criar<{ documento: FichaDePedido; message: string }>(raiz(tipo), dados),

    eliminar: (tipo: string, id: number) => api.apagar<{ message: string }>(`${raiz(tipo)}/${id}`),

    aprovar: (tipo: string, id: number, dados: Record<string, unknown> = {}) =>
        api.criar<{ documento: FichaDePedido; message: string }>(`${raiz(tipo)}/${id}/aprovar`, dados),

    rejeitar: (tipo: string, id: number, motivo: string) =>
        api.criar<{ documento: FichaDePedido; message: string }>(`${raiz(tipo)}/${id}/rejeitar`, { rejection_reason: motivo }),

    pagar: (tipo: string, id: number) =>
        api.criar<{ documento: FichaDePedido; message: string }>(`${raiz(tipo)}/${id}/pagar`, {}),

    cancelar: (tipo: string, id: number, motivo?: string) =>
        api.criar<{ documento: FichaDePedido; message: string }>(`${raiz(tipo)}/${id}/cancelar`, { cancellation_reason: motivo ?? null }),

    /** Quem está fora, e quando — o mês inteiro de uma vez. */
    calendario: (tipo: string, ano: number, mes: number) =>
        api.ler<{ de: string; ate: string; eventos: EventoDoCalendario[] }>(`${raiz(tipo)}/calendario`, { ano, mes }),

    anexo: (tipo: string, id: number, ficheiro: File) => {
        const corpo = new FormData();
        corpo.append('ficheiro', ficheiro);

        return api.enviar<{ documento: FichaDePedido; message: string }>(`${raiz(tipo)}/${id}/anexo`, corpo);
    },
};
