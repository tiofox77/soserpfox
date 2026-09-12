/**
 * A PONTE DOS ECRÃS DAS COMPRAS E DO INVENTÁRIO.
 *
 * Fala com `/api/v1/invoicing/react/compras/*` e `/inventario/*` — a morada
 * dos ecrãs com sessão, a mesma de todos os outros módulos. Os dois módulos
 * partilham a ponte porque partilham o assunto: o que entra em armazém.
 */

import { api } from './cliente';

export type Escolha = { valor: string; rotulo: string };

type Recado = { message: string };

export type Serie = { etiquetas: string[]; valores: number[] };

type Meta = {
    current_page: number; last_page: number; per_page: number;
    total: number; from: number | null; to: number | null;
};

/* ─── O painel das compras ────────────────────────────────────────────── */

export type PainelDasCompras = {
    resumo: {
        por_decidir: number;
        por_encomendar: number;
        em_curso: number;
        atrasadas: number;
        por_facturar: number;
        valor_em_curso: number;
    };
    a_decidir: Array<{
        id: number; numero: string; autor: string | null; linhas: number;
        necessaria_em: string | null; justificacao: string | null; quando: string | null;
    }>;
    atrasadas: Array<{
        id: number; numero: string; fornecedor: string | null;
        prometida: string | null; dias: number; total: number;
    }>;
    por_mes: Serie;
    fornecedores: Serie & { quantas: number[] };
};

/* ─── As requisições ──────────────────────────────────────────────────── */

export type Requisicao = {
    id: number;
    numero: string;
    estado: string;
    estado_rotulo: string;
    warehouse_id: number | null;
    armazem: string | null;
    necessaria_em: string | null;
    justificacao: string | null;
    motivo_recusa: string | null;
    autor: string | null;
    linhas: number;
    criada_em: string | null;
    pode_editar: boolean;
    pode_encomendar: boolean;
};

export type ItemDaRequisicao = {
    id: number;
    product_id: number | null;
    artigo: string | null;
    codigo: string | null;
    descricao: string;
    quantidade: number;
    encomendada: number;
    por_encomendar: number;
    custo_estimado: number | null;
    unidade: string | null;
    notas: string | null;
};

export type ArtigoSugerido = {
    id: number; nome: string; codigo: string | null;
    unidade: string | null; custo: number;
};

/* ─── As encomendas ───────────────────────────────────────────────────── */

export type Encomenda = {
    id: number;
    numero: string;
    estado: string;
    estado_rotulo: string;
    supplier_id: number | null;
    fornecedor: string | null;
    warehouse_id: number | null;
    armazem: string | null;
    requisicao_id: number | null;
    data: string | null;
    entrega_prevista: string | null;
    atrasada: boolean;
    notas: string | null;
    subtotal: number;
    total: number;
    linhas: number;
    quantidade_pedida: number;
    quantidade_recebida: number;
    percentagem_recebida: number;
    pode_editar: boolean;
    pode_receber: boolean;
    pode_facturar: boolean;
    factura: { id: number; numero: string; estado: string } | null;
};

export type ItemDaEncomenda = {
    id: number;
    product_id: number | null;
    artigo: string | null;
    codigo: string | null;
    descricao: string;
    quantidade: number;
    recebida: number;
    por_receber: number;
    preco_unitario: number;
    desconto_percent: number;
    total: number;
    unidade: string | null;
};

/* ─── O inventário ────────────────────────────────────────────────────── */

export type PainelDoInventario = {
    resumo: {
        valor: number; artigos_geridos: number;
        negativos: number; a_zero: number; quebras_mes: number;
    };
    negativos: Array<{ nome: string; quantidade: number }>;
    mais_valiosos: Array<{ nome: string; quantidade: number; custo: number; valor: number }>;
    movimentos: { etiquetas: string[]; entradas: number[]; saidas: number[] };
    contagens: Array<{
        id: number; armazem: string | null; fechada_em: string | null;
        dias: number | null; acertos: number; custo: number;
    }>;
};

export type Movimento = {
    id: number;
    quando: string | null;
    tipo: string;
    tipo_rotulo: string;
    artigo: string | null;
    codigo: string | null;
    unidade: string | null;
    quantidade: number;
    armazem: string | null;
    origem: string | null;
    origem_rotulo: string;
    referencia: number | null;
    quem: string | null;
    notas: string | null;
};

export type Contagem = {
    id: number;
    estado: string;
    estado_rotulo: string;
    warehouse_id: number | null;
    armazem: string | null;
    aberta_em: string | null;
    fechada_em: string | null;
    quem_abriu: string | null;
    notas: string | null;
    contados: number;
    acertos: number;
    custo: number;
};

export type LinhaDaContagem = {
    id: number;
    product_id: number;
    artigo: string | null;
    codigo: string | null;
    unidade: string | null;
    esperado: number;
    contado: number | null;
    diferenca: number | null;
    custo: number;
};

const C = '/compras';
const I = '/inventario';

export const compras = {
    painel: () => api.ler<PainelDasCompras>(`${C}/painel`),

    requisicoes: {
        opcoes: () => api.ler<{
            estados: Escolha[];
            armazens: Escolha[];
            permissoes: { pode_gerir: boolean; pode_decidir: boolean };
        }>(`${C}/requisicoes/opcoes`),
        artigos: (procura: string) => api.ler<{ data: ArtigoSugerido[] }>(`${C}/requisicoes/artigos`, { procura }),
        lista: (f: { procura?: string; estado?: string; por_pagina?: number; page?: number }) =>
            api.ler<{
                data: Requisicao[]; meta: Meta;
                resumo: { rascunhos: number; submetidas: number; aprovadas: number; total: number };
            }>(`${C}/requisicoes`, f),
        ficha: (id: number) => api.ler<{
            data: Requisicao & { decisor: string | null; decidida_em: string | null };
            itens: ItemDaRequisicao[];
            encomendas: Array<{ id: number; numero: string; estado: string }>;
        }>(`${C}/requisicoes/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Requisicao }>(`${C}/requisicoes/${id}`, dados)
               : api.criar<Recado & { data: Requisicao }>(`${C}/requisicoes`, dados),
        submeter: (id: number) => api.criar<Recado & { data: Requisicao }>(`${C}/requisicoes/${id}/submeter`, {}),
        aprovar: (id: number) => api.criar<Recado & { data: Requisicao }>(`${C}/requisicoes/${id}/aprovar`, {}),
        rejeitar: (id: number, motivo: string) =>
            api.criar<Recado & { data: Requisicao }>(`${C}/requisicoes/${id}/rejeitar`, { motivo }),
        cancelar: (id: number) => api.criar<Recado & { data: Requisicao }>(`${C}/requisicoes/${id}/cancelar`, {}),
    },

    encomendas: {
        opcoes: () => api.ler<{
            estados: Escolha[];
            fornecedores: Escolha[];
            armazens: Array<Escolha & { padrao: boolean }>;
            requisicoes: Array<Escolha & { linhas: number }>;
            permissoes: { pode_gerir: boolean; pode_receber: boolean };
        }>(`${C}/encomendas/opcoes`),
        lista: (f: {
            procura?: string; estado?: string; fornecedor?: number | '';
            por_pagina?: number; page?: number;
        }) => api.ler<{
            data: Encomenda[]; meta: Meta;
            resumo: { abertas: number; atrasadas: number; por_facturar: number; valor_aberto: number };
        }>(`${C}/encomendas`, f),
        ficha: (id: number) => api.ler<{
            data: Encomenda & {
                autor: string | null; requisicao: string | null;
                fornecedor_telefone: string | null; fornecedor_email: string | null;
            };
            itens: ItemDaEncomenda[];
        }>(`${C}/encomendas/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Encomenda }>(`${C}/encomendas/${id}`, dados)
               : api.criar<Recado & { data: Encomenda }>(`${C}/encomendas`, dados),
        daRequisicao: (dados: Record<string, unknown>) =>
            api.criar<Recado & { data: Encomenda }>(`${C}/encomendas/da-requisicao`, dados),
        enviar: (id: number) => api.criar<Recado & { data: Encomenda }>(`${C}/encomendas/${id}/enviar`, {}),
        confirmar: (id: number) => api.criar<Recado & { data: Encomenda }>(`${C}/encomendas/${id}/confirmar`, {}),
        recepcao: (id: number) => api.ler<{ data: Encomenda; itens: ItemDaEncomenda[] }>(`${C}/encomendas/${id}/recepcao`),
        receber: (id: number, quantidades: Record<string, number | string>) =>
            api.criar<Recado & { data: Encomenda }>(`${C}/encomendas/${id}/receber`, { quantidades }),
        facturar: (id: number) => api.criar<Recado & { data: Encomenda }>(`${C}/encomendas/${id}/facturar`, {}),
        cancelar: (id: number) => api.criar<Recado & { data: Encomenda }>(`${C}/encomendas/${id}/cancelar`, {}),
    },
};

export const inventario = {
    painel: () => api.ler<PainelDoInventario>(`${I}/painel`),

    movimentos: (f: {
        procura?: string; tipo?: string; armazem?: number | '';
        de?: string; ate?: string; por_pagina?: number; page?: number;
    }) => api.ler<{
        de: string; ate: string;
        data: Movimento[]; meta: Meta;
        resumo: { movimentos: number; entradas: number; saidas: number };
        opcoes: { tipos: Escolha[]; armazens: Escolha[] };
    }>(`${I}/movimentos`, f),

    contagem: {
        estado: () => api.ler<{
            aberta: Contagem | null;
            historico: Contagem[];
            armazens: Escolha[];
        }>(`${I}/contagem`),
        abrir: (warehouse_id: number, notas?: string) =>
            api.criar<Recado & { data: Contagem }>(`${I}/contagem`, { warehouse_id, notas }),
        linhas: (id: number, f: { procura?: string; filtro?: string; por_pagina?: number; page?: number }) =>
            api.ler<{
                contagem: Contagem; data: LinhaDaContagem[]; meta: Meta;
                progresso: { total: number; contados: number; diferencas: number };
            }>(`${I}/contagem/${id}/linhas`, f),
        contar: (id: number, product_id: number, contado: number | null) =>
            api.criar<{ contado: number | null; diferenca: number | null }>(
                `${I}/contagem/${id}/contar`, { product_id, contado },
            ),
        resumo: (id: number) => api.ler<{ contados: number; acertos: number; custo: number }>(
            `${I}/contagem/${id}/resumo`,
        ),
        fechar: (id: number) => api.criar<Recado & { data: Contagem }>(`${I}/contagem/${id}/fechar`, {}),
        cancelar: (id: number) => api.criar<Recado>(`${I}/contagem/${id}/cancelar`, {}),
        diferencas: (id: number) => api.ler<{
            data: Contagem;
            ajustadas: Array<{
                id: number; artigo: string | null; unidade: string | null;
                esperado: number; contado: number; diferenca: number; custo: number;
            }>;
        }>(`${I}/contagem/${id}/diferencas`),
    },
};
