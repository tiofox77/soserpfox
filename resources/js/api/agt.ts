import { api } from './cliente';

export type Ambiente = 'sandbox' | 'production';

export type OpcoesDaAgt = {
    ambientes: Array<{ valor: Ambiente; rotulo: string }>;
    operacoes: Array<{ valor: string; rotulo: string }>;
    cae: Array<{ codigo: string; descricao: string }>;
    empresas: Array<{ id: number; nome: string; nif: string | null }>;
    permissoes: { pode_editar: boolean; escolhe_empresa: boolean };
};

export type Relatorio = {
    keys_configured?: boolean;
    api_configured?: boolean;
    series?: { total: number; registered: number; pending: number };
    submissions?: { total: number; pending: number; submitted: number; validated: number; rejected: number };
    invoices_30_days?: { total: number; with_hash: number; with_jws: number; with_atcud: number; agt_validated: number };
};

export type Submissao = {
    id: number; quando: string | null; document_type_code: string | null; document_number: string | null; status: string;
    agt_reference: string | null; atcud: string | null; error_code: string | null; error_message: string | null; retry_count: number;
    pode_reenviar: boolean; esgotada: boolean;
};

export type Registo = {
    id: number; quando: string | null; service: string | null; method: string | null; endpoint: string | null;
    response_status: number | null; response_time: number | null; success: boolean; error_message: string | null;
};

export type EstadoDaAgt = {
    empresa: { id: number; nome: string; nif: string | null };
    ambiente: Ambiente;
    definicoes: { agt_environment: Ambiente; agt_auto_submit: boolean; agt_eac_code: string; agt_require_validation: boolean };
    ambientes: Record<Ambiente, { rotulo: string; chaves: boolean; activo: boolean; a_ver: boolean; produtor: boolean }>;
    chaves: { publica: boolean; privada: boolean; produtor: boolean };
    em_falta: string[];
    relatorio: Relatorio;
    series: Array<{ id: number; series_code: string; name: string; document_type: string; agt_series_id: string | null; registada: boolean }>;
    submissoes: Submissao[];
    logs: Registo[];
};

export type Contribuinte = {
    agt_environment: Ambiente; tax_registration_number: string; agt_establishment_number: string; agt_auto_submit: boolean;
    agt_require_validation: boolean; agt_notification_emails: string; agt_eac_code: string | null; produtor: boolean; chave_legado: boolean;
};

type Resposta = { message: string };

/** A empresa só viaja quando foi escolhida (super admin); os outros ficam na sua. */
const com = <T extends object>(empresa: number | undefined, resto: T = {} as T): T & { tenant?: number } => (empresa ? { tenant: empresa, ...resto } : resto);

export const agt = {
    opcoes: () => api.ler<OpcoesDaAgt>('/agt/opcoes'),
    estado: (ambiente: Ambiente | null, empresa?: number) => api.ler<EstadoDaAgt>('/agt/estado', com(empresa, ambiente ? { ambiente } : {})),
    guardar: (corpo: Record<string, unknown>, empresa?: number) => api.criar<Resposta>('/agt/definicoes', com(empresa, corpo)),
    activarAmbiente: (ambiente: Ambiente, empresa?: number) => api.criar<Resposta & { tipo: string; ambiente_activo: Ambiente }>('/agt/ambiente', com(empresa, { ambiente })),
    guardarChaves: (corpo: { ambiente: Ambiente; contributorPublicKey: string; contributorPrivateKey: string }, empresa?: number) => api.criar<Resposta>('/agt/chaves', com(empresa, corpo)),
    removerChaves: (ambiente: Ambiente, empresa?: number) => api.criar<Resposta>('/agt/chaves/remover', com(empresa, { ambiente })),
    testarLigacao: (ambiente: Ambiente, empresa?: number) => api.criar<Resposta & { data: { success?: boolean; error?: string } & Record<string, unknown> }>('/agt/ligacao', com(empresa, { ambiente })),
    sincronizarSeries: (ambiente: Ambiente, empresa?: number) => api.criar<Resposta & { data: Record<string, unknown> }>('/agt/series/sincronizar', com(empresa, { ambiente })),
    actualizarEstados: (empresa?: number) => api.criar<Resposta>('/agt/submissoes/actualizar', com(empresa)),
    reenviar: (id: number, ambiente: Ambiente, repor: boolean, empresa?: number) => api.criar<Resposta>(`/agt/submissoes/${id}/reenviar`, com(empresa, { ambiente, repor })),
    consultar: (corpo: Record<string, unknown>, empresa?: number) => api.criar<{ data: Record<string, unknown> }>('/agt/consulta', com(empresa, corpo)),
    contribuinte: (empresa?: number) => api.ler<{ data: Contribuinte; permissoes: { pode_editar: boolean } }>('/agt/contribuinte', com(empresa)),
    guardarContribuinte: (corpo: Record<string, unknown>, empresa?: number) => api.criar<Resposta & { data: Contribuinte }>('/agt/contribuinte', com(empresa, corpo)),

    /*
     * A chave privada «do modo antigo». Sobe, nunca desce: a resposta traz a
     * ficha do contribuinte, onde `chave_legado` diz apenas se está instalada.
     */
    guardarChaveLegado: (pem: string, empresa?: number) => api.criar<Resposta & { data: Contribuinte }>('/agt/contribuinte/chave', com(empresa, { contributor_private_key: pem })),
    removerChaveLegado: (empresa?: number) => api.criar<Resposta & { data: Contribuinte }>('/agt/contribuinte/chave/remover', com(empresa)),
};
