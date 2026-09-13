/**
 * A PONTE DO PORTAL DO CLIENTE.
 *
 * Outra porta, outra guarda: o cliente entra pelo guard `client`, e a API vive
 * em `/client/api`, ao lado das páginas do portal. Não partilha nada com a API
 * da empresa — um cliente nunca bate na `/api/v1/invoicing`.
 */

import { criarApi } from './cliente';

export const apiDoPortal = criarApi('/client/api');
const entrada = criarApi('/client');

type Recado = { message: string };

export type Paginacao = { pagina: number; ultima: number; total: number };

export type FacturaDoCliente = {
    id: number;
    numero: string;
    data: string | null;
    vencimento: string | null;
    total: number;
    pago: number;
    saldo: number;
    estado: string;
    estado_rotulo: string;
    atrasada: boolean;
};

export type EventoDoCliente = {
    id: number;
    numero: string;
    nome: string;
    descricao: string | null;
    inicio: string | null;
    fim: string | null;
    local: string | null;
    tipo: string | null;
    participantes: number | null;
    estado: string;
    estado_rotulo: string;
    fase: string | null;
    fase_rotulo: string | null;
    fase_icone: string | null;
    progresso: number;
    valor: number | null;
};

export type ProformaDoCliente = {
    id: number;
    numero: string;
    data: string | null;
    valida_ate: string | null;
    expirada: boolean;
    total: number;
    estado: string;
};

type Filtros = Record<string, string | number | undefined>;

export const portal = {
    entrar: (dados: { email: string; password: string; remember: boolean }) =>
        entrada.criar<Recado & { ir_para: string }>('/login', dados),
    painel: () => apiDoPortal.ler<{
        cliente: { nome: string };
        numeros: { facturas: number; pendentes: number; pagas: number; facturado: number; eventos: number; proximos_eventos: number };
        ultimas_facturas: FacturaDoCliente[];
        proximos_eventos: EventoDoCliente[];
    }>('/painel'),
    extrato: (f: Filtros) => apiDoPortal.ler<{
        numeros: { saldo_devedor: number; atrasadas: number; valor_atrasado: number; parciais_total: number; parciais_pago: number; recebido: number; facturado: number };
        meses: Array<{ mes: string; recebido: number; em_aberto: number }>;
        facturas: FacturaDoCliente[];
        paginacao: Paginacao;
    }>('/extrato', f),
    facturas: (f: Filtros) => apiDoPortal.ler<{ facturas: FacturaDoCliente[]; paginacao: Paginacao }>('/facturas', f),
    proformas: (f: Filtros) => apiDoPortal.ler<{
        numeros: { total: number; convertidas: number; em_aberto: number };
        proformas: ProformaDoCliente[];
        paginacao: Paginacao;
    }>('/proformas', f),
    eventos: (f: Filtros) => apiDoPortal.ler<{
        numeros: { total: number; confirmados: number; em_andamento: number; concluidos: number };
        eventos: EventoDoCliente[];
        paginacao: Paginacao;
    }>('/eventos', f),
    perfil: () => apiDoPortal.ler<{ perfil: { name: string; email: string; phone: string } }>('/perfil'),
    guardarPerfil: (dados: { name: string; email: string; phone: string }) => apiDoPortal.guardar<Recado>('/perfil', dados),
    mudarSenha: (dados: { current_password: string; new_password: string; new_password_confirmation: string }) =>
        apiDoPortal.guardar<Recado>('/senha', dados),
};
