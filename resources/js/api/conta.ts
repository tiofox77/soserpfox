/**
 * A PONTE DO ECRÃ DA MINHA CONTA.
 *
 * Fala com `/api/v1/invoicing/react/conta/*`. O logótipo, a fotografia e o
 * comprovativo de pagamento vão em multipart; o resto é JSON.
 */

import { api } from './cliente';

type Recado = { message: string };

export type EmpresaDaConta = {
    id: number;
    nome: string;
    designacao: string | null;
    nif: string | null;
    email: string | null;
    telefone: string | null;
    morada: string | null;
    regime: string;
    logo: string | null;
    activa: boolean;
    utilizadores: number;
    modulos: number;
    plano: string | null;
    papel: string;
    /** O direito de editar e remover — pelo NOME do papel, não por um número. */
    sou_dono: boolean;
    desde: string | null;
};

export type PlanoActual = {
    nome: string;
    preco: number;
    max_utilizadores: number | null;
    max_empresas: number | null;
    funcionalidades: string[];
    estado: string;
    em_teste: boolean;
    ciclo: string;
    ciclo_rotulo: string;
    termina_em: string | null;
    dias_que_faltam: number | null;
    a_terminar: boolean;
    dias_de_teste: number | null;
};

export type PlanoDisponivel = {
    id: number;
    nome: string;
    descricao: string | null;
    destaque: boolean;
    precos: { monthly: number; quarterly: number; semiannual: number; yearly: number };
    max_utilizadores: number | null;
    max_empresas: number | null;
    funcionalidades: string[];
    modulos: string[];
    dias_de_teste: number;
    auto_activa: boolean;
    actual: boolean;
    /** Porque é que não se pode escolher — ou null se se puder. */
    recusa: string | null;
};

/* ─── Privacidade (RGPD / LGPD / Lei 22/11) ─────────────────────────── */

export type CategoriaDeDados = {
    chave: string; icone: string; titulo: string; dados: string[];
    finalidade: string; base_legal: string; retencao: string; destinatarios: string; onde: string[];
};

export type DireitoDoTitular = { chave: string; icone: string; nome: string; descricao: string; artigos: string };

export type EstadoDoConsentimento = { aceite: boolean; versao: string; quando: string; origem: string } | null;

export type Privacidade = {
    versao: string;
    actualizada_em: string;
    responsavel: { nome: string; morada: string; email: string };
    prazo_de_resposta_dias: number;
    politica: string;
    cookies: string;
    inventario: CategoriaDeDados[];
    direitos: DireitoDoTitular[];
    escolha_neste_browser: { estatisticas: boolean; marketing: boolean } | null;
    tipos_de_pedido: Array<{ valor: string; rotulo: string }>;
    dados: {
        perfil: { id: number; nome: string; email: string; telefone: string | null; lingua: string | null; criada_em: string | null; ultima_entrada: string | null; senha_mudada_em: string | null; tem_pin_de_turno: boolean };
        empresas: Array<{ id: number; nome: string; nif: string | null; morada: string | null; telefone: string | null; email: string | null; entrou_em: string | null; activo: boolean; ultimo_acesso: string | null }>;
        sessoes: Array<{ esta: boolean; ip: string | null; aparelho: string; browser: string; ultima_actividade: string }>;
        entradas: Array<{ evento: string; ip: string | null; aparelho: string; quando: string }>;
        aparelhos: Array<{ plataforma: string | null; aparelho: string; visto_pela_primeira_vez: string | null; visto_pela_ultima_vez: string | null }>;
        estatisticas: { eventos: number; primeiro?: string | null; ultimo?: string | null; paises?: string[]; cidades?: string[] };
        consentimentos: Record<'termos' | 'privacidade' | 'estatisticas' | 'marketing', EstadoDoConsentimento>;
        pedidos: Array<{ id: number; tipo: string; mensagem: string | null; estado: string; resposta: string | null; prazo_em: string | null; respondido_em: string | null; created_at: string }>;
    };
};

export type FacturaDaConta = {
    id: number;
    numero: string;
    descricao: string;
    total: number;
    dia: string | null;
    vence_em: string | null;
    estado: string;
    vencida: boolean;
};

export type PedidoDaConta = {
    id: number;
    numero: string;
    empresa: string | null;
    plano: string | null;
    ciclo: string;
    ciclo_rotulo: string;
    valor: number;
    estado: string;
    estado_rotulo: string;
    tem_comprovativo: boolean;
    comprovativo: string | null;
    referencia: string;
    quando: string | null;
};

export type MinhaConta = {
    perfil: {
        nome: string;
        email: string;
        telefone: string | null;
        bio: string | null;
        avatar: string | null;
        super_admin: boolean;
        ultimo_acesso: string | null;
        senha_mudada_em: string | null;
    };
    limite: { usadas: number; maximo: number | null; excedido: boolean; cabe_mais: boolean };
    empresas: EmpresaDaConta[];
    plano: PlanoActual | null;
    planos: PlanoDisponivel[];
    facturas: FacturaDaConta[];
    pedidos: PedidoDaConta[];
    conta_da_plataforma: { banco: string; titular: string; iban: string } | null;
    permissoes: { gerir_conta: boolean };
};

export type PodeArquivar = {
    pode: boolean;
    razoes: string[];
    nome: string;
    clientes: number;
};

const C = '/conta';

function comFicheiro(campo: string, ficheiro: File): FormData {
    const corpo = new FormData();

    corpo.append(campo, ficheiro);

    return corpo;
}

export const conta = {
    ler: () => api.ler<MinhaConta>(C),

    empresas: {
        criar: (dados: Record<string, unknown>) =>
            api.criar<Recado & { data: { id: number; nome: string } }>(`${C}/empresas`, dados),
        editar: (id: number, dados: Record<string, unknown>) =>
            api.guardar<Recado>(`${C}/empresas/${id}`, dados),
        logotipo: (id: number, ficheiro: File) =>
            api.enviar<Recado & { logo: string }>(`${C}/empresas/${id}/logotipo`, comFicheiro('logo', ficheiro)),
        apagarLogotipo: (id: number) => api.apagar<Recado>(`${C}/empresas/${id}/logotipo`),
        podeArquivar: (id: number) => api.ler<PodeArquivar>(`${C}/empresas/${id}/pode-arquivar`),
        arquivar: (id: number, confirmacao: string) =>
            api.apagar<Recado & { trocou_para: number | null }>(`${C}/empresas/${id}`, { confirmacao }),
        activar: (id: number) => api.criar<Recado>(`${C}/empresas/${id}/activar`, {}),
    },

    perfil: (dados: Record<string, unknown>) => api.guardar<Recado>(`${C}/perfil`, dados),
    avatar: (ficheiro: File) =>
        api.enviar<Recado & { avatar: string }>(`${C}/avatar`, comFicheiro('avatar', ficheiro)),
    apagarAvatar: () => api.apagar<Recado>(`${C}/avatar`),
    senha: (dados: Record<string, unknown>) => api.guardar<Recado>(`${C}/senha`, dados),

    contratar: (plan_id: number, ciclo: string, comprovativo: File | null) => {
        const corpo = new FormData();

        corpo.append('plan_id', String(plan_id));
        corpo.append('ciclo', ciclo);

        if (comprovativo) corpo.append('comprovativo', comprovativo);

        return api.enviar<Recado & { activado: boolean; pedido?: number }>(`${C}/contratar`, corpo);
    },

    privacidade: {
        ler: () => api.ler<Privacidade>(`${C}/privacidade`),
        /** Descarrega-se por ligação normal: a resposta traz Content-Disposition. */
        exportar: `/api/v1/invoicing/react${C}/privacidade/exportar`,
        consentimentos: (dados: { estatisticas: boolean; marketing: boolean }) =>
            api.guardar<Recado>(`${C}/privacidade/consentimentos`, dados),
        terminarSessoes: (incluir_aplicacao: boolean) =>
            api.criar<Recado & { sessoes: number; tokens: number }>(`${C}/privacidade/sessoes/terminar`, { incluir_aplicacao }),
        pedir: (dados: { tipo: string; mensagem: string }) =>
            api.criar<Recado & { id: number }>(`${C}/privacidade/pedidos`, dados),
    },

    comprovativo: (pedido: number, ficheiro: File) =>
        api.enviar<Recado>(`${C}/pedidos/${pedido}/comprovativo`, comFicheiro('comprovativo', ficheiro)),
};
