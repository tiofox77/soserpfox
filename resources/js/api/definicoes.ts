import { api } from './cliente';

/** As colunas que o ecrã edita — os nomes são os das colunas. */
export type Definicoes = {
    default_warehouse_id: number | null;
    default_client_id: number | null;
    default_supplier_id: number | null;
    default_tax_id: number | null;
    default_currency: string;
    default_exchange_rate: number | string;
    default_payment_method: string | null;
    number_format: string | null;
    decimal_places: number | string;
    price_mask_enabled: boolean;
    pos_formato_impressao: 'a4' | 'talao';
    rounding_mode: string | null;
    proforma_series: string;
    invoice_series: string;
    receipt_series: string;
    proforma_next_number: number;
    invoice_next_number: number;
    receipt_next_number: number;
    default_tax_rate: number | string;
    default_irt_rate: number | string;
    apply_irt_services: boolean;
    allow_line_discounts: boolean;
    allow_commercial_discount: boolean;
    allow_financial_discount: boolean;
    max_discount_percent: number | string;
    proforma_validity_days: number | string;
    invoice_due_days: number | string;
    auto_print_after_save: boolean;
    show_company_logo: boolean;
    nome_nos_documentos: 'social' | 'comercial';
    invoice_footer_text: string | null;
    default_notes: string | null;
    default_terms: string | null;
    pos_auto_print: boolean;
    pos_play_sounds: boolean;
    pos_validate_stock: boolean;
    pos_allow_negative_stock: boolean;
    pos_hide_out_of_stock: boolean;
    pos_show_product_images: boolean;
    pos_products_per_page: number | string;
    pos_auto_complete_sale: boolean;
    pos_require_customer: boolean;
    pos_default_payment_method_id: number | null;
    pwa_menu: string[];
    default_payment_term_id: number | null;
    profile_pharmacy: boolean;
    profile_clothing: boolean;
    profile_cosmetics: boolean;
    profile_grocery: boolean;
};

export type Escolha = { valor: string; rotulo: string };
export type Nomeado = { id: number; name: string };

export type Serie = {
    id: number;
    document_type: string;
    series_code: string;
    name: string;
    prefix: string;
    next_number: number;
    is_default: boolean;
    agt_series_id: string | null;
    pode_renomear: boolean;
    description: string | null;
};

export type TipoDeSerie = { tipo: string; nome: string; icone: string; cor: string; prefixo: string | null };

export type EcraDasDefinicoes = {
    definicoes: Definicoes;
    opcoes: {
        armazens: Nomeado[];
        clientes: Nomeado[];
        fornecedores: Nomeado[];
        impostos: Array<{ id: number; name: string; rate: number }>;
        formas_de_pagamento: Array<{ id: number; code: string; name: string }>;
        condicoes_de_pagamento: Array<{ id: number; name: string; days: number }>;
        moedas: Escolha[];
        metodos_de_pagamento: Escolha[];
        formatos_de_numero: Escolha[];
        modos_de_arredondamento: Escolha[];
        nomes_nos_documentos: Escolha[];
        entradas_do_pwa: Array<{ chave: string; etiqueta: string; icone: string }>;
    };
    series: {
        tipos: TipoDeSerie[];
        por_tipo: Record<string, Serie[]>;
        outras: Serie[];
    };
    permissoes: { pode_editar: boolean };
};

export type AvisoDeNumeracao = { nova: string; nova_proximo: number; em_uso: string; em_uso_proximo: number };

export const definicoes = {
    ler: () => api.ler<EcraDasDefinicoes>('/definicoes'),

    guardar: (corpo: Partial<Definicoes>) =>
        api.guardar<{ message: string; aviso: string | null }>('/definicoes', corpo),

    criarSerie: (corpo: { tipo: string; codigo: string; nome?: string; descricao?: string }) =>
        api.criar<{ serie: Serie; message: string }>('/definicoes/series', corpo),

    renomearSerie: (id: number, corpo: { codigo: string; nome?: string; descricao?: string }) =>
        api.guardar<{ serie: Serie; message: string }>(`/definicoes/series/${id}`, corpo),

    tornarPadrao: (id: number, confirmado: boolean) =>
        api.criar<{ aviso: AvisoDeNumeracao | null; message: string | null }>(`/definicoes/series/${id}/padrao`, { confirmado }),
};
