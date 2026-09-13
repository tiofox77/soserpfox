import type { ReactNode } from 'react';

import { Dialogos } from '../ui/Dialogos';
import { Cabecalho } from './Cabecalho';
import { ConviteInstalar } from './ConviteInstalar';
import { FaixaDoEstado } from './FaixaDoEstado';
import { MenuDeBaixo } from './MenuDeBaixo';
import { AtivarLoginOffline, PortaoOffline } from './PortaoOffline';

/**
 * A CASCA DOS ECRÃS COM SESSÃO — o que era o `layouts/pwa.blade.php`.
 *
 * A faixa do estado, o cabeçalho, o ecrã, o menu de baixo, o convite a
 * instalar, e as duas peças da entrada sem rede (o portão e o «activar login
 * offline»). Os avisos e as perguntas montam aqui uma vez.
 */
export function Casca({ activa, children }: { activa: string | null; children: ReactNode }) {
    return (
        <>
            <FaixaDoEstado />
            <Cabecalho />
            <main className="px-4 py-4 pb-24">
                {/* Só opacidade: um transform aqui fazia das folhas e da barra do carrinho (fixed) filhas desta caixa durante a animação. */}
                <div className="pwa-aparece">{children}</div>
            </main>
            <MenuDeBaixo activa={activa} />
            <ConviteInstalar />
            <AtivarLoginOffline />
            <PortaoOffline />
            <Dialogos />
        </>
    );
}
