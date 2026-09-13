import { api } from './cliente';
import type { CriarParte } from './partes';

export type LinhaDoEditor = {
    product_id: number | null;
    description: string;
    quantity: number | string;
    price: number | string;
    discount_percent: number | string;
};

/** O que o SERVIDOR devolve de cada linha. O ecrã não faz contas. */
export type LinhaCalculada = {
    product_id: number | null;
    nome: string;
    description: string | null;
    unit: string;
    quantity: number;
    price: number;
    discount_percent: number;
    bruto: number;
    desconto: number;
    base: number;
    tax_rate: number;
    tax_code: string | null;
    exemption_reason: string | null;
    imposto: number;
    total: number;
};

export type Totais = {
    bruto: number;
    desconto_comercial: number;
    desconto_por_linha: number;
    liquido: number;
    base: number;
    imposto: number;
    retencao: number;
    total: number;
};

/**
 * Um modelo de proposta e os campos livres que ele pede a quem faz o
 * orçamento. Só o orçamento tem modelos: nos outros a lista vem vazia.
 */
export type ModeloDeProposta = {
    id: number;
    nome: string;
    is_default: boolean;
    campos: Array<{ chave: string; rotulo: string; ajuda: string; linhas: number; titulo: string }>;
};

export type OpcoesDoEmissor = {
    titulo: string;
    parte: string;
    rota: string;
    partes: Array<{ id: number; name: string; nif: string | null }>;
    /** Que preço o `price` dos artigos é: o de venda, ou o custo numa proforma de compra. */
    preco: 'venda' | 'custo';
    artigos: Array<{ id: number; name: string; code: string | null; price: number; unit: string; type: string }>;
    armazens: Array<{ id: number; name: string }>;
    armazem_padrao: number | null;
    modelos: ModeloDeProposta[];
    modelo_padrao: number | null;
    regioes: Array<{ valor: string; rotulo: string }>;
    /**
     * A OUTRA PARTE CRIADA AQUI MESMO: o cliente rápido numa proposta de
     * venda, o fornecedor rápido numa de compra.
     */
    criar_parte: CriarParte;
    permissoes: { pode_criar: boolean };
};

/** O CONTEÚDO COMERCIAL de uma proposta: o que se aproveita ao duplicar. */
export type ConteudoDaProposta = {
    parte_id: number | null;
    warehouse_id: number | null;
    data: string | null;
    valido_ate: string | null;
    tax_country_region: string | null;
    /** Prestação de serviço: retém-se IRT a 6,5% e o armazém deixa de fazer falta. */
    is_service: boolean;
    notas: string | null;
    /** As condições que saem no papel (termos e condições). */
    condicoes: string | null;
    /**
     * OS TRÊS DESCONTOS DO DOCUMENTO, em kwanzas.
     *
     * O comercial desconta antes do IVA, o financeiro depois, e o «legado» é o
     * campo antigo (`discount_amount`) que soma ao comercial — existe na base
     * desde antes dos outros dois e as propostas antigas têm-no preenchido.
     */
    desconto_comercial: number;
    desconto_legado: number;
    desconto_financeiro: number;
    /** Só no orçamento: o modelo por que a proposta é desenhada. */
    quote_template_id: number | null;
    campos_proposta: Record<string, string>;
};

/** Uma proposta aberta no editor — e se ainda se pode mexer (só rascunhos). */
export type PropostaAberta = {
    documento: ConteudoDaProposta & { id: number; numero: string | null; estado: string; pode_editar: boolean; pdf: string };
    linhas: LinhaDoEditor[];
};

/** O conteúdo de uma proposta para NASCER OUTRA VEZ — sem número nem estado. */
export type PropostaDuplicada = {
    origem: { id: number; numero: string | null };
    documento: ConteudoDaProposta;
    linhas: LinhaDoEditor[];
};

type Gravada = { id: number; numero: string; total: number; abrir: string; estado: string; message: string };

export const emissor = {
    opcoes: (tipo: string) => api.ler<OpcoesDoEmissor>(`/emissor/${tipo}/opcoes`),
    calcular: (tipo: string, corpo: { linhas: LinhaDoEditor[]; desconto_comercial?: number; desconto_legado?: number; desconto_financeiro?: number; is_service?: boolean }) =>
        api.criar<{ linhas: LinhaCalculada[]; totais: Totais }>(`/emissor/${tipo}/calcular`, corpo),
    guardar: (tipo: string, corpo: Record<string, unknown>) => api.criar<Gravada>(`/emissor/${tipo}`, corpo),
    abrir: (tipo: string, id: number) => api.ler<PropostaAberta>(`/emissor/${tipo}/${id}`),
    /** Duplicar NÃO grava: traz o conteúdo para o editor abrir em branco. */
    duplicar: (tipo: string, id: number) => api.ler<PropostaDuplicada>(`/emissor/${tipo}/${id}/duplicar`),
    actualizar: (tipo: string, id: number, corpo: Record<string, unknown>) => api.guardar<Gravada>(`/emissor/${tipo}/${id}`, corpo),
};
