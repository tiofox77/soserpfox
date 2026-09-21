/**
 * O PROGRAMA DE REVENDEDORES (16/09/2026).
 *
 * Três portas: a pública (o pedido, o código no registo), a do portal do
 * revendedor (guard `revendedor`, em `/revendedor/api`) e a do super admin
 * (`/api/v1/plataforma/react/revendedores`).
 */

import { criarApi } from './cliente';

type Recado = { message: string };

const publica = criarApi('');
const doPortal = criarApi('/revendedor/api');
const entrada = criarApi('/revendedor');
const daPlataforma = criarApi('/api/v1/plataforma/react/revendedores');

export type Paginacao = { pagina: number; ultima: number; total: number; de: number; ate: number };

export type EstadoDaEmpresa = {
    chave: 'teste' | 'activa' | 'vencida' | 'sem_plano' | 'suspensa';
    rotulo: string;
    detalhe: string;
    ate: string | null;
    dias: number | null;
    a_vencer: boolean;
    por_pagar: boolean;
};

export type EmpresaDoRevendedor = {
    id: number;
    nome: string;
    nif: string | null;
    email: string | null;
    telefone: string | null;
    plano: string | null;
    ciclo: string | null;
    valor: number | null;
    estado: EstadoDaEmpresa;
    utilizadores: number;
    ultima_entrada: string | null;
    ultima_entrada_ha: string | null;
    via: string | null;
    via_rotulo: string | null;
    ligada_em: string | null;
    criada_em: string | null;
};

export type Comissao = {
    id: number;
    empresa_id: number;
    empresa: string | null;
    plano: string | null;
    origem: 'order' | 'invoice';
    origem_rotulo: string;
    base: number;
    valor: number;
    regra: string;
    estado: 'por_pagar' | 'paga' | 'anulada' | 'compensada';
    estado_rotulo: string;
    motivo: string | null;
    pagamento: { id: number; data: string | null; referencia: string | null } | null;
    criada_em: string | null;
};

export type TotaisDeComissoes = { por_pagar: number; por_pagar_n: number; pago: number; descontado: number; anulado: number; do_mes: number };

export type Contagens = Record<'todas' | 'por_pagar' | 'a_vencer' | EstadoDaEmpresa['chave'], number>;

export type PlanoParaEscolher = {
    id: number;
    nome: string;
    descricao: string | null;
    utilizadores: number;
    dias_de_teste: number;
    destaque: boolean;
    precos: Record<'monthly' | 'quarterly' | 'semiannual' | 'yearly', number>;
    /**
     * O PREÇO DE REVENDEDOR: o de tabela menos a comissão que ganharia, pela
     * regra dele. É o que transfere quando é ele a pagar pelo cliente.
     */
    precos_revendedor?: Record<'monthly' | 'quarterly' | 'semiannual' | 'yearly', number>;
};

export type OpcoesDoPortal = {
    planos: PlanoParaEscolher[];
    ciclos: Array<{ valor: keyof PlanoParaEscolher['precos']; rotulo: string }>;
    regimes: Array<{ valor: string; rotulo: string; descricao: string }>;
    regime_padrao: string;
    conta: { banco: string; titular: string; iban: string };
};

export type FichaDaEmpresa = {
    /**
     * O preço de revendedor DESTA empresa, por plano (id) e ciclo. Não é o do
     * catálogo: aqui conta se já houve comissão e há quanto tempo a empresa é
     * dele — é o que o pedido vai cobrar.
     */
    precos_revendedor?: Record<number, Record<string, number>>;
    empresa: EmpresaDoRevendedor & {
        razao_social: string | null;
        morada: string | null;
        regime: string | null;
        dono: { nome: string; email: string } | null;
    };
    subscricao: { plano_id: number; plano: string | null; ciclo: string; ciclo_rotulo: string; valor: number; inicio: string | null; fim: string | null } | null;
    pedidos: Array<{ id: number; plano: string | null; ciclo: string | null; valor: number; estado: 'pending' | 'approved' | 'rejected'; estado_rotulo: string; referencia: string | null; comprovativo: string | null; motivo: string | null; data: string | null }>;
    facturas: Array<{
        id: number; numero: string; descricao: string | null; data: string | null; vencimento: string | null; total: number; estado: string; estado_rotulo: string; referencia: string | null;
        /** O revendedor já enviou o pagamento e espera a confirmação. */
        pagamento_enviado: boolean; comprovativo: string | null; motivo_recusa: string | null; pode_pagar: boolean;
    }>;
    comissoes: Comissao[];
};

export type NovaEmpresa = {
    company_name: string;
    company_nif: string;
    company_regime: string;
    company_address: string;
    company_phone: string;
    company_email: string;
    name: string;
    email: string;
    selected_plan_id: number | '';
    payment_reference: string;
};

/** Um pagamento do revendedor pelo cliente: um pedido de plano ou uma factura de renovação. */
export type PagamentoDoRevendedor = {
    tipo: 'pedido' | 'factura';
    id: number;
    empresa_id: number;
    empresa: string | null;
    descricao: string;
    /** O que transfere. Numa renovação, já com o desconto de revendedor. */
    valor: number;
    /** O total da factura (documento fiscal, fica ao preço de tabela). */
    valor_tabela?: number;
    desconto?: number;
    referencia: string | null;
    comprovativo: string | null;
    estado: 'por_pagar' | 'vencida' | 'por_confirmar' | 'confirmado' | 'recusado';
    motivo: string | null;
    vence: string | null;
    data: string | null;
};

export type Pagamento = { id: number; valor: number; data: string | null; forma: string; referencia: string | null; comissoes: number; notas?: string | null; por?: string | null };

export type PerfilDoRevendedor = {
    name: string; company_name: string | null; nif: string | null; email: string; phone: string | null;
    province: string | null; city: string | null; website: string | null; bank_name: string | null; iban: string | null;
    codigo: string | null; link: string | null; regra: string;
};

function formulario(dados: Record<string, unknown>, ficheiros: Record<string, File | null>): FormData {
    const f = new FormData();
    for (const [k, v] of Object.entries(dados)) {
        if (v !== null && v !== undefined && v !== '') f.append(k, String(v));
    }
    for (const [k, v] of Object.entries(ficheiros)) {
        if (v) f.append(k, v);
    }

    return f;
}

export const revenda = {
    /* ─── Público ─── */
    pedir: (dados: Record<string, unknown>) => publica.criar<Recado>('/revendedores/pedido', dados),
    verificarCodigo: (codigo: string) => publica.criar<{ codigo: string; nome: string }>('/register/revendedor', { codigo }),

    /* ─── Entrada ─── */
    entrar: (dados: { email: string; password: string; remember: boolean }) => entrada.criar<Recado & { ir_para: string }>('/entrar', dados),
    esqueci: (email: string) => entrada.criar<Recado>('/esqueci-a-senha', { email }),
    novaSenha: (dados: { token: string; email: string; password: string; password_confirmation: string }) =>
        entrada.criar<Recado & { ir_para: string }>('/nova-senha', dados),

    /* ─── Portal ─── */
    painel: () => doPortal.ler<{
        revendedor: { nome: string; empresa: string | null; codigo: string | null; link: string | null; regra: string; desde: string | null };
        contagens: Contagens;
        atencao: EmpresaDoRevendedor[];
        recentes: EmpresaDoRevendedor[];
        comissoes: TotaisDeComissoes;
        ultimas_comissoes: Comissao[];
    }>('/painel'),
    opcoes: () => doPortal.ler<OpcoesDoPortal>('/opcoes'),
    empresas: (f: { procura?: string; estado?: string; pagina?: number; por_pagina?: number }) =>
        doPortal.ler<{ empresas: EmpresaDoRevendedor[]; paginacao: Paginacao & { por_pagina: number }; contagens: Contagens }>('/empresas', f),
    empresa: (id: number) => doPortal.ler<FichaDaEmpresa>(`/empresas/${id}`),
    criarEmpresa: (dados: NovaEmpresa, comprovativo: File | null) =>
        doPortal.enviar<Recado & { id: number; email_enviado: boolean; senha: string }>('/empresas', formulario(dados, { payment_proof: comprovativo })),
    pedirPlano: (id: number, dados: { plan_id: number; ciclo: string; referencia: string }, comprovativo: File | null) =>
        doPortal.enviar<Recado & { pedido: number }>(`/empresas/${id}/pedidos`, formulario(dados, { comprovativo })),
    comprovativo: (id: number, pedido: number, ficheiro: File, referencia: string) =>
        doPortal.enviar<Recado>(`/empresas/${id}/pedidos/${pedido}/comprovativo`, formulario({ referencia }, { comprovativo: ficheiro })),
    pagamentos: () => doPortal.ler<{
        por_pagar: PagamentoDoRevendedor[];
        por_confirmar: PagamentoDoRevendedor[];
        historico: PagamentoDoRevendedor[];
        totais: { por_pagar: number; por_pagar_n: number; por_confirmar: number; por_confirmar_n: number };
        conta: OpcoesDoPortal['conta'];
    }>('/pagamentos'),
    pagarFactura: (empresa: number, factura: number, ficheiro: File, referencia: string) =>
        doPortal.enviar<Recado>(`/empresas/${empresa}/facturas/${factura}/pagamento`, formulario({ referencia }, { comprovativo: ficheiro })),
    comissoes: (f: { estado?: string; pagina?: number }) => doPortal.ler<{
        comissoes: Comissao[]; paginacao: Paginacao; totais: TotaisDeComissoes; regra: string; pagamentos: Pagamento[];
    }>('/comissoes', f),
    perfil: () => doPortal.ler<{ perfil: PerfilDoRevendedor }>('/perfil'),
    guardarPerfil: (dados: Partial<PerfilDoRevendedor>) => doPortal.guardar<Recado>('/perfil', dados),
    senha: (dados: { actual: string; nova: string; nova_confirmation: string }) => doPortal.guardar<Recado>('/senha', dados),
};

/* ─── O super admin ─── */

export type RegraDeComissao = {
    tipo: 'percentagem' | 'fixo';
    valor: number | string;
    aplica: 'sempre' | 'primeiro' | 'meses';
    meses: number | string | null;
    base: 'sem_iva' | 'com_iva';
    planos: Array<{ plan_id: number | string; tipo: 'percentagem' | 'fixo'; valor: number | string }>;
};

export type LinhaDeRevendedor = {
    id: number;
    nome: string;
    empresa: string | null;
    email: string;
    telefone: string | null;
    localidade: string | null;
    codigo: string | null;
    estado: 'pendente' | 'aprovado' | 'suspenso' | 'recusado';
    estado_rotulo: string;
    regra: string | null;
    empresas: number;
    por_pagar: number;
    pago: number;
    pedido_em: string | null;
    ultima_entrada: string | null;
};

export type FichaDeRevendedor = {
    revendedor: LinhaDeRevendedor & {
        nif: string | null; provincia: string | null; cidade: string | null; site: string | null;
        banco: string | null; iban: string | null; motivacao: string | null; notas: string | null;
        motivo_da_recusa: string | null; aprovado_em: string | null; suspenso_em: string | null;
        link: string | null; comissao: RegraDeComissao;
    };
    empresas: Array<{ id: number; nome: string; nif: string | null; plano: string | null; estado: string; cor: string; activa: boolean; via: string | null; ligada_em: string | null; ultima_entrada: string | null }>;
    comissoes: Comissao[];
    pagamentos: Pagamento[];
    totais: TotaisDeComissoes;
};

type Escolha = { valor: string; rotulo: string };

export type OpcoesDosRevendedores = {
    tipos: Escolha[];
    quando: Escolha[];
    bases: Escolha[];
    metodos: Escolha[];
    planos: Array<{ valor: number; rotulo: string; preco: number }>;
    padrao: RegraDeComissao;
};

export const revendedores = {
    lista: (f: { estado?: string; procura?: string; pagina?: number }) => daPlataforma.ler<{
        revendedores: LinhaDeRevendedor[];
        paginacao: Paginacao;
        contagens: Record<LinhaDeRevendedor['estado'], number>;
        totais: { empresas: number; por_pagar: number; pago: number };
    }>('', f),
    opcoes: () => daPlataforma.ler<OpcoesDosRevendedores>('/opcoes'),
    ver: (id: number) => daPlataforma.ler<FichaDeRevendedor>(`/${id}`),
    guardar: (id: number, dados: Record<string, unknown>) => daPlataforma.guardar<Recado>(`/${id}`, dados),
    aprovar: (id: number, dados: { codigo: string; comissao: RegraDeComissao }) => daPlataforma.criar<Recado>(`/${id}/aprovar`, dados),
    recusar: (id: number, motivo: string) => daPlataforma.criar<Recado>(`/${id}/recusar`, { motivo }),
    suspender: (id: number) => daPlataforma.criar<Recado>(`/${id}/suspender`, {}),
    reactivar: (id: number) => daPlataforma.criar<Recado>(`/${id}/reactivar`, {}),
    dadosDeAcesso: (id: number) => daPlataforma.criar<Recado>(`/${id}/dados-de-acesso`, {}),
    pagar: (id: number, dados: { comissoes: number[]; method: string; reference: string; paid_at: string; notes: string }) =>
        daPlataforma.criar<Recado>(`/${id}/pagamentos`, dados),
    anular: (id: number, comissao: number, motivo: string) => daPlataforma.criar<Recado>(`/${id}/comissoes/${comissao}/anular`, { motivo }),
};
