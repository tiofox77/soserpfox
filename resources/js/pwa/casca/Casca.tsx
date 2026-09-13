import { useLayoutEffect, useRef, type ReactNode } from 'react';

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
            <Topo />

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

/**
 * O TOPO: A FAIXA DO ESTADO E O CABEÇALHO, UM POR CIMA DO OUTRO.
 *
 * A faixa era `fixed` no topo e o cabeçalho `sticky` no topo — o mesmo sítio.
 * Sempre que havia faixa («Sem conexão», «Sincronizado») tapava metade do
 * cabeçalho: a empresa, a hora e os botões Instalar e Sair ficavam cortados,
 * precisamente quando se está sem rede e é preciso saber em que empresa se está.
 *
 * Agora vão juntos, presos ao topo, e a faixa empurra o cabeçalho. A altura do
 * bloco muda com a faixa, por isso mede-se e fica em `--pwa-topo`: é por ela
 * que as barras de procura dos ecrãs se prendem logo abaixo.
 */
function Topo() {
    const topo = useRef<HTMLDivElement>(null);

    useLayoutEffect(() => {
        const el = topo.current;
        if (!el) return;
        const medir = () => document.documentElement.style.setProperty('--pwa-topo', `${Math.round(el.getBoundingClientRect().height)}px`);
        medir();
        if (typeof ResizeObserver === 'undefined') return;
        const observador = new ResizeObserver(medir);
        observador.observe(el);
        return () => observador.disconnect();
    }, []);

    return (
        <div ref={topo} id="pwa-topo" className="sticky top-0 z-40">
            <FaixaDoEstado />
            <Cabecalho />
        </div>
    );
}
