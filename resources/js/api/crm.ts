/**
 * A PONTE DOS ECRÃS DO CRM.
 *
 * Fala com `/api/v1/invoicing/react/crm/*` — a morada dos ecrãs com sessão, a
 * mesma de todos os outros módulos.
 */

import { api } from './cliente';

export type Escolha = { valor: string; rotulo: string };

type Recado = { message: string };

export type Serie = { etiquetas: string[]; valores: number[] };

type Meta = {
    current_page: number; last_page: number; per_page: number;
    total: number; from: number | null; to: number | null;
};

/* ─── O painel ────────────────────────────────────────────────────────── */

export type PainelDoCrm = {
    resumo: {
        funil_valor: number;
        funil_ponderado: number;
        funil_contagem: number;
        ganho_mes: number;
        ganhas_mes: number;
        facturado_mes: number;
        por_facturar_mes: number;
        taxa: number | null;
        leads_novos_mes: number;
        leads_abertos: number;
    };
    por_etapa: Serie;
    ganhos_por_mes: Serie;
    por_origem: Serie;
    tarefas: Array<{
        id: number; tipo: string; tipo_rotulo: string; assunto: string;
        prazo: string | null; atrasada: boolean;
        lead: string | null; oportunidade: string | null;
    }>;
    ultimas: Array<{
        id: number; titulo: string; cliente: string | null;
        etapa: string | null; valor: number; estado: string;
    }>;
};

/* ─── Os leads ────────────────────────────────────────────────────────── */

export type Lead = {
    id: number;
    nome: string;
    empresa: string | null;
    telefone: string | null;
    email: string | null;
    origem: string;
    origem_rotulo: string;
    estado: string;
    estado_rotulo: string;
    notas: string | null;
    motivo_da_perda: string | null;
    responsavel: string | null;
    cliente: string | null;
    client_id: number | null;
    actividades: number;
    criado_em: string | null;
    seguinte: string | null;
};

export type OpcoesDosLeads = {
    origens: Escolha[];
    estados: Escolha[];
    tipos_de_actividade: Escolha[];
    whatsapp_ligado: boolean;
    permissoes: { pode_gerir: boolean };
};

export type ActividadeNaConversa = {
    id: number;
    tipo: string;
    tipo_rotulo: string;
    assunto: string;
    notas: string | null;
    sentido: string | null;
    feita: boolean;
    quando: string | null;
};

/* ─── As oportunidades ────────────────────────────────────────────────── */

export type Oportunidade = {
    id: number;
    titulo: string;
    client_id: number | null;
    cliente: string | null;
    stage_id: number | null;
    etapa: string | null;
    probabilidade: number;
    valor: number;
    ponderado: number;
    fecho_previsto: string | null;
    notas: string | null;
    estado: string;
    estado_rotulo: string;
    motivo_da_perda: string | null;
    fechada_em: string | null;
    responsavel: string | null;
    actividades: number;
    factura: {
        id: number; numero: string; dia: string | null;
        estado: string; total: number;
    } | null;
};

export type OpcoesDasOportunidades = {
    etapas: Array<Escolha & { probabilidade: number }>;
    clientes: Escolha[];
    estados: Escolha[];
    tipos_de_actividade: Escolha[];
    permissoes: { pode_gerir: boolean; pode_facturar: boolean };
};

export type ColunaDoFunil = {
    id: number;
    nome: string;
    probabilidade: number;
    total: number;
    ponderado: number;
    cartoes: Array<{
        id: number; titulo: string; cliente: string | null;
        responsavel: string | null; valor: number; ponderado: number;
        fecho_previsto: string | null;
    }>;
};

/* ─── O Meta ──────────────────────────────────────────────────────────── */

export type LigacaoMeta = {
    app_id: string;
    webhook_verify_token: string;
    facebook_enabled: boolean;
    facebook_page_id: string;
    facebook_page_name: string;
    instagram_enabled: boolean;
    instagram_account_id: string;
    instagram_username: string;
    whatsapp_enabled: boolean;
    whatsapp_phone_number_id: string;
    whatsapp_business_account_id: string;
    whatsapp_display_number: string;
    criar_leads: boolean;
    lead_ads_enabled: boolean;
};

const R = '/crm';

export const crm = {
    painel: () => api.ler<PainelDoCrm>(`${R}/painel`),

    leads: {
        opcoes: () => api.ler<OpcoesDosLeads>(`${R}/leads/opcoes`),
        lista: (f: {
            procura?: string; estado?: string; origem?: string;
            por_pagina?: number; page?: number;
        }) => api.ler<{
            data: Lead[]; meta: Meta;
            resumo: { abertos: number; novos: number; convertidos: number; perdidos: number };
        }>(`${R}/leads`, f),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Lead }>(`${R}/leads/${id}`, dados)
               : api.criar<Recado & { data: Lead }>(`${R}/leads`, dados),
        avancar: (id: number) => api.criar<Recado & { data: Lead }>(`${R}/leads/${id}/avancar`, {}),
        converter: (id: number, title: string, amount: number) =>
            api.criar<Recado & { data: Lead }>(`${R}/leads/${id}/converter`, { title, amount }),
        perder: (id: number, motivo: string) => api.criar<Recado>(`${R}/leads/${id}/perder`, { motivo }),
        reabrir: (id: number) => api.criar<Recado>(`${R}/leads/${id}/reabrir`, {}),
        apagar: (id: number) => api.apagar<Recado>(`${R}/leads/${id}`),
        conversa: (id: number) => api.ler<{
            data: Lead; numero: string | null; pode_whatsapp: boolean;
            actividades: ActividadeNaConversa[];
        }>(`${R}/leads/${id}/conversa`),
        actividade: (id: number, dados: Record<string, unknown>) =>
            api.criar<Recado>(`${R}/leads/${id}/actividades`, dados),
        responder: (id: number, texto: string) =>
            api.criar<Recado>(`${R}/leads/${id}/responder`, { texto }),
    },

    oportunidades: {
        opcoes: () => api.ler<OpcoesDasOportunidades>(`${R}/oportunidades/opcoes`),
        lista: (f: {
            procura?: string; estado?: string; etapa?: number | '';
            por_pagina?: number; page?: number;
        }) => api.ler<{
            data: Oportunidade[]; meta: Meta;
            resumo: {
                abertas: number; valor: number; ponderado: number;
                ganhas: number; por_facturar: number;
            };
        }>(`${R}/oportunidades`, f),
        funil: () => api.ler<{
            colunas: ColunaDoFunil[];
            permissoes: { pode_gerir: boolean };
        }>(`${R}/oportunidades/funil`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Oportunidade }>(`${R}/oportunidades/${id}`, dados)
               : api.criar<Recado & { data: Oportunidade }>(`${R}/oportunidades`, dados),
        ganhar: (id: number) => api.criar<Recado & { data: Oportunidade }>(`${R}/oportunidades/${id}/ganhar`, {}),
        perder: (id: number, motivo: string) =>
            api.criar<Recado & { data: Oportunidade }>(`${R}/oportunidades/${id}/perder`, { motivo }),
        reabrir: (id: number) => api.criar<Recado & { data: Oportunidade }>(`${R}/oportunidades/${id}/reabrir`, {}),
        facturar: (id: number) => api.criar<Recado & { data: Oportunidade }>(`${R}/oportunidades/${id}/facturar`, {}),
        mover: (id: number, direccao: 'frente' | 'tras') =>
            api.criar<Recado>(`${R}/oportunidades/${id}/mover`, { direccao }),
        historico: (id: number) => api.ler<{ data: ActividadeNaConversa[] }>(`${R}/oportunidades/${id}/historico`),
        actividade: (id: number, dados: Record<string, unknown>) =>
            api.criar<Recado>(`${R}/oportunidades/${id}/actividades`, dados),
    },

    meta: {
        ler: () => api.ler<{
            data: LigacaoMeta;
            segredos: { app_secret: boolean; facebook_page_token: boolean; whatsapp_token: boolean };
            url_do_webhook: string;
        }>(`${R}/meta`),
        guardar: (dados: Record<string, unknown>) => api.guardar<Recado>(`${R}/meta`, dados),
        novoToken: () => api.criar<{ webhook_verify_token: string }>(`${R}/meta/token`, {}),
        testarWhatsApp: () => api.criar<{ ok: boolean; message: string }>(`${R}/meta/testar-whatsapp`, {}),
    },
};
