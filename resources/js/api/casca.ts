import { criarApi } from './cliente';

/**
 * A forma do menu que o `App\Support\MenuDaCasca` monta no servidor e entrega
 * ao ecrã pelas props. Não há pedido à API: o menu já vem decidido — quem o
 * vê, o que está activo — porque isso é do servidor e de mais ninguém.
 */

export type Ligacao = {
    rotulo: string;
    prefixo: string | null;
    forte: boolean;
    icone: string;
    marca: boolean;
    cor: string;
    barra: string;
    hover: string;
    url: string;
    activo: boolean;
    topo: boolean;
    relatorio: boolean;
};

export type Entrada = Ligacao | { separador: true } | { titulo: string } | { sub: Grupo };

export type Grupo = {
    chave: string;
    rotulo: string;
    prefixo: string | null;
    leve: boolean;
    icone: string;
    cor: string;
    simples: boolean;
    url: string | null;
    activo: boolean;
    aberto: boolean;
    entradas: Entrada[];
};

export type MenuDaCasca = {
    principal: Ligacao[];
    grupos: Grupo[];
    superadmin: Array<{ titulo: string; entradas: Ligacao[] }>;
    fox: boolean;
    suporte: { url: string; activo: boolean; rotulo: string; extra: string };
    utilizador: {
        nome: string;
        papel: string;
        ligacoes: Array<{ url: string; rotulo: string; icone: string; cor: string }>;
        atualizacoes: { url: string; rotulo: string; versao: string };
        sair: string;
    };
};

export type PropsDaCasca = {
    menu: MenuDaCasca;
    logo: string | null;
    nome: string;
    csrf: string;
    voltar: string;
};

export const eLigacao = (e: Entrada): e is Ligacao => 'url' in e && 'rotulo' in e;
export const eSeparador = (e: Entrada): e is { separador: true } => 'separador' in e;
export const eTitulo = (e: Entrada): e is { titulo: string } => 'titulo' in e;
export const eSub = (e: Entrada): e is { sub: Grupo } => 'sub' in e;

/* ─── O topo de todas as páginas ──────────────────────────────────────────
 *
 * A empresa activa, o contador da subscrição, o sino e as mensagens da
 * plataforma. Eram componentes Livewire no layout; falam agora com
 * `/api/v1/casca`, que só pede sessão.
 */


export const apiDaCasca = criarApi('/api/v1/casca');

type Recado = { message: string };

export type PrazoDaSubscricao = {
    expirado: boolean;
    dias: number;
    horas: number;
    minutos: number;
    resumo: string;
    estado: 'expired' | 'critical' | 'warning' | 'attention' | 'good';
    cor: 'red' | 'orange' | 'yellow' | 'green';
    termina_em: string;
    plano: string;
    ciclo: string | null;
    tipo: 'trial' | 'plan' | 'licenca';
    estado_da_subscricao?: string;
};

export type TopoDaCasca = {
    empresa: {
        activa: { id: number; nome: string } | null;
        empresas: Array<{ id: number; nome: string; nif: string | null; papel: string | null }>;
        contagem: number;
        maximo: number | null;
        excedido: boolean;
    };
    prazo: PrazoDaSubscricao | null;
};

export type NotificacaoDoSino = {
    id: string | null;
    tipo: string;
    icone: string;
    cor: string;
    titulo: string;
    mensagem: string;
    quando: string;
    ligacao: string | null;
    lida: boolean;
    da_base: boolean;
};

export type MensagemDaPlataforma = {
    id: number;
    titulo: string;
    corpo: string;
    nivel: string;
    cor: string;
    icone: string;
    forma: 'popup' | 'barra';
    dispensavel: boolean;
    ligacao: string | null;
    texto_da_ligacao: string | null;
    termina: string | null;
    dispensada: boolean;
};

export const casca = {
    topo: () => apiDaCasca.ler<TopoDaCasca>('/topo'),
    entrarNaEmpresa: (id: number) => apiDaCasca.criar<Recado & { ir_para: string }>(`/empresas/${id}/entrar`, {}),
    notificacoes: (soPorLer: boolean) =>
        apiDaCasca.ler<{ notificacoes: NotificacaoDoSino[]; por_ler: number }>('/notificacoes', { so_por_ler: soPorLer ? 1 : 0 }),
    marcarComoLida: (id: string) => apiDaCasca.criar<Recado>(`/notificacoes/${id}/lida`, {}),
    marcarTodasComoLidas: () => apiDaCasca.criar<Recado>('/notificacoes/lidas', {}),
    apagarNotificacao: (id: string) => apiDaCasca.apagar<Recado>(`/notificacoes/${id}`),
    limparNotificacoes: () => apiDaCasca.apagar<Recado>('/notificacoes'),
    mensagens: () => apiDaCasca.ler<{ mensagens: MensagemDaPlataforma[] }>('/mensagens'),
    dispensar: (id: number) => apiDaCasca.criar<Recado>(`/mensagens/${id}/dispensar`, {}),
    avisos: () => apiDaCasca.ler<{ avisos: MensagemDaPlataforma[] }>('/avisos'),
};
