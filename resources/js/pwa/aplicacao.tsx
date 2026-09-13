import { Component, type ComponentType, type ErrorInfo, type ReactNode } from 'react';

import { t } from '@/i18n';

import { ContextoDoPwa, ENTRADA_DO_ECRA } from './contexto';
import { Casca } from './casca/Casca';
import type { NomeDoEcra, PropsDoPwa } from './tipos';
import { Catalogo } from './ecras/Catalogo';
import { Clientes } from './ecras/Clientes';
import { Documentos } from './ecras/Documentos';
import { Entrada } from './ecras/Entrada';
import { Inicio } from './ecras/Inicio';
import { NovoCliente } from './ecras/NovoCliente';
import { NovoDocumento } from './ecras/NovoDocumento';
import { PinEsquecido } from './ecras/PinEsquecido';
import { Pos } from './ecras/Pos';
import { Restaurante } from './ecras/Restaurante';
import { SemAcesso } from './ecras/SemAcesso';

/**
 * OS ECRÃS DO PWA, todos no mesmo pacote e sem `import()` preguiçoso: sem rede
 * não há onde ir buscar um pedaço que falte.
 */
const ECRAS: Record<NomeDoEcra, ComponentType> = {
    entrada: Entrada,
    'pin-esquecido': PinEsquecido,
    inicio: Inicio,
    catalogo: Catalogo,
    clientes: Clientes,
    'novo-cliente': NovoCliente,
    documentos: Documentos,
    'novo-documento': NovoDocumento,
    pos: Pos,
    restaurante: Restaurante,
    'sem-acesso': SemAcesso,
};

/**
 * O ECRÃ SAI DO ENDEREÇO.
 *
 * Sem rede, o service worker pode servir a página guardada de OUTRO endereço
 * (o POS no lugar dos clientes, quando os clientes nunca foram abertos com
 * rede). A casca é a mesma para todos, portanto desenha-se o ecrã que o
 * endereço pede — e não o que calhou ficar guardado.
 */
export function ecraDoEndereco(caminho: string, rotas: PropsDoPwa['rotas']): NomeDoEcra | null {
    const limpo = caminho.replace(/\/+$/, '') || '/';
    const mapa: [string, NomeDoEcra][] = [
        [rotas.entrada, 'entrada'],
        [rotas.pinEsquecido, 'pin-esquecido'],
        [rotas.inicio, 'inicio'],
        [rotas.catalogo, 'catalogo'],
        [rotas.novoCliente, 'novo-cliente'],
        [rotas.clientes, 'clientes'],
        [rotas.novoDocumento, 'novo-documento'],
        [rotas.documentos, 'documentos'],
        [rotas.pos, 'pos'],
        [rotas.restaurante, 'restaurante'],
    ];

    for (const [rota, ecra] of mapa) {
        if (limpo === rota.replace(/\/+$/, '')) return ecra;
    }

    // `/invoicing/offline/index` é um recurso antigo do service worker.
    if (limpo === `${rotas.inicio.replace(/\/+$/, '')}/index`) return 'inicio';

    return null;
}

/** O ecrã a desenhar: o do endereço, salvo quando o servidor já disse que não há acesso. */
export function escolherEcra(props: PropsDoPwa, caminho: string): { ecra: NomeDoEcra; semAcessoLocal: boolean } {
    if (props.ecra === 'sem-acesso') return { ecra: 'sem-acesso', semAcessoLocal: false };

    const doEndereco = ecraDoEndereco(caminho, props.rotas) ?? props.ecra;

    // Uma página pública nunca serve de recurso a uma da aplicação, e vice-versa.
    const publicas: NomeDoEcra[] = ['entrada', 'pin-esquecido'];
    if (publicas.includes(doEndereco) !== publicas.includes(props.ecra)) {
        return { ecra: props.ecra, semAcessoLocal: false };
    }

    // O ecrã servido de recurso: só se desenha se a entrada for deste utilizador.
    const chave = ENTRADA_DO_ECRA[doEndereco];
    if (chave && props.menu && !props.menu.some((m) => m.chave === chave)) {
        return { ecra: 'sem-acesso', semAcessoLocal: true };
    }

    return { ecra: doEndereco, semAcessoLocal: false };
}

class LimiteDoEcra extends Component<{ children: ReactNode }, { erro: Error | null }> {
    override state = { erro: null as Error | null };

    static getDerivedStateFromError(erro: Error) {
        return { erro };
    }

    override componentDidCatch(erro: Error, info: ErrorInfo) {
        console.error('[PWA] o ecrã rebentou', erro, info.componentStack);
    }

    override render() {
        if (!this.state.erro) return this.props.children;

        return (
            <div role="alert" className="pwa-entra max-w-md mx-auto bg-white rounded-2xl shadow-sm p-6 text-center mt-6">
                <div className="w-14 h-14 mx-auto rounded-2xl bg-red-100 text-red-600 flex items-center justify-center mb-3">
                    <i className="fas fa-bug text-xl" aria-hidden="true" />
                </div>
                <p className="font-bold text-slate-800">{t('Este ecrã encontrou um erro.')}</p>
                <p className="text-sm text-slate-500 mt-1">{t('O que está por enviar continua guardado neste aparelho.')}</p>
                <p className="text-[11px] text-slate-400 mt-2 font-mono break-all">{this.state.erro.message}</p>
                <button type="button" onClick={() => window.location.reload()}
                        className="pwa-toque mt-4 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold">
                    <i className="fas fa-rotate-right mr-1" aria-hidden="true" />{t('Recarregar')}
                </button>
            </div>
        );
    }
}

export function Aplicacao({ props }: { props: PropsDoPwa }) {
    const { ecra, semAcessoLocal } = escolherEcra(props, window.location.pathname);
    const Ecra = ECRAS[ecra];
    const valor: PropsDoPwa = semAcessoLocal ? { ...props, semAcesso: undefined } : props;

    if (ecra === 'entrada' || ecra === 'pin-esquecido') {
        return (
            <ContextoDoPwa.Provider value={valor}>
                <LimiteDoEcra><Ecra /></LimiteDoEcra>
            </ContextoDoPwa.Provider>
        );
    }

    return (
        <ContextoDoPwa.Provider value={valor}>
            <Casca activa={ENTRADA_DO_ECRA[ecra] ?? null}>
                <LimiteDoEcra><Ecra /></LimiteDoEcra>
            </Casca>
        </ContextoDoPwa.Provider>
    );
}
