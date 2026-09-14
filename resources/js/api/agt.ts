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
    pode_reenviar: boolean;
    /** Tentativas gastas e o documento ainda por validar: repõe-se o contador e reenvia-se. */
    pode_repor?: boolean;
    /** O nome antigo do `pode_repor` — fica enquanto o servidor o mandar. */
    esgotada?: boolean;
    tentativas_max?: number;
    erros?: ErroDaAgt[];
};

export type Registo = {
    id: number; quando: string | null; service: string | null; method: string | null; endpoint: string | null;
    response_status: number | null; response_time: number | null; success: boolean; error_message: string | null;
};

/**
 * O QUE O SISTEMA ESTÁ A FAZER NA PRÁTICA com os documentos da empresa — não
 * o que os interruptores dizem. `comunica` falso com o envio automático ligado
 * é a farmácia que tinha 1108 facturas emitidas e nenhuma comunicada.
 */
export type Comunicacao = { comunica: boolean; motivos: string[]; emitidos_30d: number; por_comunicar: number };

/** Uma condição para emitir em produção, com o seu sim ou não. */
export type ItemDeProntidao = { chave: string; rotulo: string; ok: boolean };

/**
 * As submissões por concluir em cada ambiente — as de um não andam enquanto se
 * emite no outro. Pelas chaves do ambiente (`sandbox`/`production`) ou pelos
 * nomes (`homologacao`/`producao`): aceitam-se as duas, e quem lê é o
 * `pendentesDe()` do ecrã.
 */
export type PendentesPorAmbiente = Partial<Record<'sandbox' | 'production' | 'homologacao' | 'producao', number>>;

/** Um erro que a AGT devolveu, com a explicação de cá quando a há. */
export type ErroDaAgt = { codigo: string | null; descricao: string; explicacao?: string | null };

export type EstadoDaSerie = 'registada' | 'por_registar' | 'rejeitada' | 'nao_aplicavel';

export type SerieDaAgt = {
    id: number; series_code: string; name: string; document_type: string; agt_series_id: string | null; registada: boolean;
    estado?: EstadoDaSerie;
    tipo_rotulo?: string;
    prefixo?: string | null;
    atcud?: string | null;
    erros?: ErroDaAgt[];
};

export type EstadoDaAgt = {
    empresa: { id: number; nome: string; nif: string | null };
    ambiente: Ambiente;
    definicoes: { agt_environment: Ambiente; agt_auto_submit: boolean; agt_eac_code: string; agt_require_validation: boolean };
    ambientes: Record<Ambiente, { rotulo: string; chaves: boolean; activo: boolean; a_ver: boolean; produtor: boolean }>;
    chaves: { publica: boolean; privada: boolean; produtor: boolean };
    em_falta: string[];
    relatorio: Relatorio;
    series: SerieDaAgt[];
    submissoes: Submissao[];
    logs: Registo[];

    /*
     * O que o servidor passou a mandar. Opcionais de propósito: um servidor
     * que ainda não os tenha não pode rebentar o ecrã — ausente quer dizer
     * «não sei», e o ecrã cala-se em vez de inventar.
     */
    comunicacao?: Comunicacao;
    prontidao_producao?: ItemDeProntidao[];
    pendentes_por_ambiente?: PendentesPorAmbiente;
    cae_em_falta?: boolean;
    auto_submit?: boolean;
};

export type Contribuinte = {
    agt_environment: Ambiente; tax_registration_number: string; agt_establishment_number: string; agt_auto_submit: boolean;
    agt_require_validation: boolean; agt_notification_emails: string; agt_eac_code: string | null; produtor: boolean; chave_legado: boolean;
};

export type DetalheDaSincronizacao = { serie_id: number; codigo: string; ok: boolean; erro: string | null; codigo_erro: string | null };

type Resposta = { message: string };

/** A resposta de testar a ligação, na forma nova (`ok`, `mensagem`…) ou na antiga (`data.success`). */
type RespostaDaLigacao = Partial<Resposta> & {
    ok?: boolean; mensagem?: string | null; ambiente?: string | null; http?: number | null;
    data?: { success?: boolean; error?: string; message?: string; environment?: string; status?: number } & Record<string, unknown>;
};

/** A resposta de uma consulta, na forma nova ou na antiga (tudo dentro de `data`). */
type RespostaDaConsulta = {
    ok?: boolean; mensagem?: string | null; http?: number | null; ms?: number | null; testado_em?: string | null;
    data?: unknown;
};

export type ResultadoDaLigacao = { ok: boolean; mensagem: string | null; ambiente: string | null; http: number | null };
export type ResultadoDaConsulta = { ok: boolean; mensagem: string | null; http: number | null; ms: number | null; testado_em: string | null; dados: unknown };

const texto = (v: unknown): string | null => (typeof v === 'string' && v.trim() !== '' ? v : null);
const numero = (v: unknown): number | null => (typeof v === 'number' && Number.isFinite(v) ? v : null);

/**
 * AS DUAS FORMAS DA RESPOSTA NUMA SÓ.
 *
 * O servidor passou a responder `{ok, mensagem, ambiente, http}` e antes
 * respondia `{data: {success, error}}`. O ecrã lê uma forma só; quem traduz é
 * isto, e o dia em que a antiga desaparecer apaga-se um ramo aqui.
 */
export function lerLigacao(r: RespostaDaLigacao | null | undefined): ResultadoDaLigacao {
    const antiga = r?.data ?? {};

    return {
        ok: typeof r?.ok === 'boolean' ? r.ok : Boolean(antiga.success),
        mensagem: texto(r?.mensagem) ?? texto(r?.message) ?? texto(antiga.error) ?? texto(antiga.message),
        ambiente: texto(r?.ambiente) ?? texto(antiga.environment),
        http: numero(r?.http) ?? numero(antiga.status),
    };
}

export function lerConsulta(r: RespostaDaConsulta | null | undefined): ResultadoDaConsulta {
    if (r && typeof r.ok === 'boolean') {
        return { ok: r.ok, mensagem: texto(r.mensagem), http: numero(r.http), ms: numero(r.ms), testado_em: texto(r.testado_em), dados: r.data ?? null };
    }

    const a = (r?.data ?? {}) as Record<string, unknown>;

    return {
        ok: Boolean(a.success),
        mensagem: texto(a.error) ?? texto(a.message),
        http: numero(a.status),
        ms: numero(a.elapsed_ms),
        testado_em: texto(a.tested_at),
        dados: a.data ?? null,
    };
}

/** A empresa só viaja quando foi escolhida (super admin); os outros ficam na sua. */
const com = <T extends object>(empresa: number | undefined, resto: T = {} as T): T & { tenant?: number } => (empresa ? { tenant: empresa, ...resto } : resto);

export const agt = {
    opcoes: () => api.ler<OpcoesDaAgt>('/agt/opcoes'),
    estado: (ambiente: Ambiente | null, empresa?: number) => api.ler<EstadoDaAgt>('/agt/estado', com(empresa, ambiente ? { ambiente } : {})),
    guardar: (corpo: Record<string, unknown>, empresa?: number) => api.criar<Resposta>('/agt/definicoes', com(empresa, corpo)),

    /*
     * AS ACÇÕES QUE NÃO SE DESFAZEM LEVAM `confirmar`.
     *
     * Mudar o ambiente que emite e apagar chaves: o servidor recusa-as sem a
     * confirmação, e só a manda o botão de dentro do modal. Um clique perdido
     * (ou um pedido forjado sem ela) não põe uma empresa a emitir com valor
     * fiscal nem a deixa sem assinar.
     */
    activarAmbiente: (ambiente: Ambiente, empresa?: number) =>
        api.criar<Resposta & { tipo: string; ambiente_activo: Ambiente; pendentes_por_ambiente?: PendentesPorAmbiente }>('/agt/ambiente', com(empresa, { ambiente, confirmar: true })),
    /** `confirmar` só depois da pergunta: substituir o par que ASSINA no ambiente activo pede um sim. */
    guardarChaves: (corpo: { ambiente: Ambiente; contributorPublicKey: string; contributorPrivateKey: string; confirmar?: boolean }, empresa?: number) => api.criar<Resposta>('/agt/chaves', com(empresa, corpo)),
    removerChaves: (ambiente: Ambiente, empresa?: number) => api.criar<Resposta>('/agt/chaves/remover', com(empresa, { ambiente, confirmar: true })),
    testarLigacao: (ambiente: Ambiente, empresa?: number) => api.criar<RespostaDaLigacao>('/agt/ligacao', com(empresa, { ambiente })),
    sincronizarSeries: (ambiente: Ambiente, empresa?: number) =>
        api.criar<Resposta & { data?: Record<string, unknown>; details?: DetalheDaSincronizacao[] }>('/agt/series/sincronizar', com(empresa, { ambiente })),
    actualizarEstados: (empresa?: number) => api.criar<Resposta>('/agt/submissoes/actualizar', com(empresa)),
    reenviar: (id: number, ambiente: Ambiente, repor: boolean, empresa?: number) => api.criar<Resposta>(`/agt/submissoes/${id}/reenviar`, com(empresa, { ambiente, repor })),
    consultar: (corpo: Record<string, unknown>, empresa?: number) => api.criar<RespostaDaConsulta>('/agt/consulta', com(empresa, corpo)),
    contribuinte: (empresa?: number) => api.ler<{ data: Contribuinte; permissoes: { pode_editar: boolean } }>('/agt/contribuinte', com(empresa)),
    guardarContribuinte: (corpo: Record<string, unknown>, empresa?: number) => api.criar<Resposta & { data: Contribuinte }>('/agt/contribuinte', com(empresa, corpo)),

    /*
     * A chave privada «do modo antigo». Sobe, nunca desce: a resposta traz a
     * ficha do contribuinte, onde `chave_legado` diz apenas se está instalada.
     */
    guardarChaveLegado: (pem: string, empresa?: number) => api.criar<Resposta & { data: Contribuinte }>('/agt/contribuinte/chave', com(empresa, { contributor_private_key: pem })),
    removerChaveLegado: (empresa?: number) => api.criar<Resposta & { data: Contribuinte }>('/agt/contribuinte/chave/remover', com(empresa, { confirmar: true })),
};
