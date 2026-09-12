/**
 * A PONTE DOS ECRÃS DAS NOTIFICAÇÕES.
 *
 * Fala com `/api/v1/invoicing/react/notificacoes/*`. OS SEGREDOS NUNCA VÊM DE
 * LÁ: a senha do SMTP e os tokens das operadoras ficam no servidor, e o que
 * chega é só a informação de que existe um guardado.
 */

import { api } from './cliente';

export type Escolha = { valor: string; rotulo: string };

type Recado = { message: string };

/* ─── As definições dos canais ────────────────────────────────────────── */

export type CanalDeEmail = {
    enabled: boolean;
    smtp_host: string | null;
    smtp_port: number | null;
    smtp_username: string | null;
    smtp_encryption: string | null;
    from_email: string | null;
    from_name: string | null;
    notifications: Record<string, boolean>;
    notification_templates: Record<string, number | string>;
};

export type CanalDeSms = {
    enabled: boolean;
    provider: string;
    account_sid: string | null;
    from_number: string | null;
    sender_id: string | null;
    notifications: Record<string, boolean>;
    notification_templates: Record<string, number | string>;
};

export type ModeloDeWhatsApp = { sid: string; name?: string; language?: string };

export type CanalDeWhatsApp = {
    enabled: boolean;
    provider: string;
    account_sid: string | null;
    from_number: string | null;
    business_account_id: string | null;
    sandbox: boolean;
    notifications: Record<string, boolean>;
    notification_templates: Record<string, number | string>;
    templates: ModeloDeWhatsApp[];
};

export type TipoDeAviso = { valor: string; rotulo: string; grupo: string };

export type ModeloEmLista = {
    id: number;
    nome: string;
    modulo: string;
    email: boolean;
    sms: boolean;
    whatsapp: boolean;
};

export type DefinicoesDeNotificacao = {
    data: { email: CanalDeEmail; sms: CanalDeSms; whatsapp: CanalDeWhatsApp };
    /** Campo → já existe um guardado. O valor nunca viaja. */
    segredos: Record<string, boolean>;
    tipos: TipoDeAviso[];
    modelos: ModeloEmLista[];
    operadoras: {
        sms: Array<Escolha & { chave: string }>;
        whatsapp: Escolha[];
    };
    permissoes: { configurar: boolean; testar: boolean };
};

/* ─── Os modelos ──────────────────────────────────────────────────────── */

export type Modelo = {
    id: number;
    nome: string;
    slug: string | null;
    modulo: string;
    modulo_rotulo: string;
    descricao: string | null;
    evento: string;
    evento_rotulo: string;
    activo: boolean;
    canais: string[];
    antes_minutos: number | null;
    a_hora: string | null;
    /** Um modelo pode estar certo e não disparar — e isso é pior do que desligado. */
    dispara: boolean;
    porque_nao_dispara: string | null;
};

export type FichaDoModelo = Modelo & {
    email_subject: string | null;
    email_body: string | null;
    email_template_id: number | null;
    sms_body: string | null;
    sms_template_sid: string | null;
    whatsapp_template_sid: string | null;
    email_enabled: boolean;
    sms_enabled: boolean;
    whatsapp_enabled: boolean;
    variable_mappings: Record<string, string>;
    conditions: Array<{ field: string; operator: string; value: string }>;
};

export type ListaDeModelos = {
    data: Modelo[];
    resumo: {
        total: number; activos: number;
        email: number; sms: number; whatsapp: number;
        /** Um modelo sem canal ligado nunca manda nada. */
        sem_canal: number;
    };
    /** Quantos modelos em falta foram criados ao abrir. */
    criados_agora: number;
};

export type VariavelDoModulo = { chave: string; rotulo: string; campo: string | null };

export type Previsao = { assunto: string; corpo: string; sms: string };

export type PreparacaoDoTeste = {
    data: Modelo;
    variaveis: Record<string, string>;
    exemplo: Record<string, string>;
    email_sugerido: string | null;
    previsao: Previsao;
};

const N = '/notificacoes';

export const notificacoes = {
    definicoes: {
        ler: () => api.ler<DefinicoesDeNotificacao>(`${N}/definicoes`),
        guardar: (dados: Record<string, unknown>) =>
            api.guardar<Recado & { segredos: Record<string, boolean> }>(`${N}/definicoes`, dados),
        testarEmail: (dados: Record<string, unknown>) =>
            api.criar<Recado>(`${N}/definicoes/testar-email`, dados),
        testarSms: (dados: Record<string, unknown>) =>
            api.criar<Recado & { aviso?: boolean }>(`${N}/definicoes/testar-sms`, dados),
        modelosDeWhatsApp: (dados: Record<string, unknown>) =>
            api.criar<Recado & { data: ModeloDeWhatsApp[] }>(`${N}/definicoes/modelos-whatsapp`, dados),
    },

    modelos: {
        opcoes: () => api.ler<{
            modulos: Escolha[];
            eventos: Escolha[];
            permissoes: { gerir: boolean; testar: boolean };
        }>(`${N}/modelos/opcoes`),
        variaveis: (modulo: string) => api.ler<{
            variaveis: VariavelDoModulo[];
            exemplo: Record<string, string>;
        }>(`${N}/modelos/variaveis/${modulo}`),
        listar: (f: { canal?: string; modulo?: string; procura?: string; estado?: string }) =>
            api.ler<ListaDeModelos>(`${N}/modelos`, f),
        ficha: (id: number) => api.ler<{ data: FichaDoModelo }>(`${N}/modelos/${id}`),
        guardar: (id: number | null, dados: Record<string, unknown>) =>
            id ? api.guardar<Recado & { data: Modelo }>(`${N}/modelos/${id}`, dados)
               : api.criar<Recado & { data: Modelo }>(`${N}/modelos`, dados),
        apagar: (id: number) => api.apagar<Recado>(`${N}/modelos/${id}`),
        estado: (id: number) => api.criar<Recado & { activo: boolean }>(`${N}/modelos/${id}/estado`, {}),

        preparar: (id: number) => api.ler<PreparacaoDoTeste>(`${N}/modelos/${id}/teste`),
        previsao: (id: number, variaveis: Record<string, string>) =>
            api.criar<{ previsao: Previsao }>(`${N}/modelos/${id}/previsao`, { variaveis }),
        testar: (id: number, dados: Record<string, unknown>) =>
            api.criar<Recado & { enviados: string[]; falhas: string[] }>(`${N}/modelos/${id}/testar`, dados),
    },
};
