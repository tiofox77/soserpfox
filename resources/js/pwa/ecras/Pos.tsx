import { useEffect } from 'react';

import { t } from '@/i18n';
import { AProcessar } from '@/ui/AProcessar';

import { kz } from '../ganchos';
import { FolhasDoTurno } from '../turno/Turno';
import { Camada, useEcraLargo } from './pos/Camada';
import { Carrinho } from './pos/Carrinho';
import { FolhaClienteRapido, FolhaEscolherCliente } from './pos/Clientes';
import { LeitorPorCamara } from './pos/Leitor';
import { GavetaDePendentes } from './pos/Pendentes';
import { ColunaDosProdutos } from './pos/Produtos';
import { ReciboDaVenda } from './pos/Recibo';
import { usePos, type ControloDoPos } from './pos/usePos';

/**
 * O POS DE BALCÃO SEM REDE — Fatura-Recibo feita no aparelho.
 *
 * Era o `invoicing/offline/pos.blade.php` (Alpine). A venda é a do motor
 * (`createPosSaleOffline`): número provisório, stock local baixado, trabalho na
 * fila e — havendo rede — o número fiscal logo a seguir. O turno é o partilhado
 * com o restaurante (`turno/Turno.tsx`).
 *
 * As peças estão em `ecras/pos/`: o estado (`usePos`), a coluna dos artigos, o
 * carrinho, os clientes, o recibo, a gaveta dos pendentes e o leitor.
 */
export function Pos() {
    const pos = usePos();
    const largo = useEcraLargo();

    return (
        /*
         * As margens negativas desfazem o `px-4 py-4` do `<main>` da casca: o POS
         * vai de ponta a ponta. No ecrã largo desfaz-se também o `pb-24` — ali a
         * altura da grelha já desconta o menu de baixo, e o que sobrasse era a
         * página inteira a rolar três píxeis.
         */
        <div className="-mx-4 -mt-4 -mb-4 lg:-mb-24">
            {/*
              Altura em `dvh` e não `vh`, e a descontar o cromado REAL.

              `100vh` no telemóvel conta a barra do browser como se não existisse, e
              o fundo do ecrã fica escondido por baixo dela; `dvh` acompanha-a. E os
              116px eram um número mágico que já não batia: o cabeçalho tem 60 e a
              barra de navegação de baixo 77, portanto os últimos 21px da coluna
              ficavam por baixo dela e não se chegava lá. O cabeçalho já não tem
              altura fixa (a faixa do estado empurra-o): lê-se de `--pwa-topo`.

              O carrinho tem largura própria (22 a 28 rem) e os artigos ficam com o
              resto, em colunas que saem do espaço: em 12 colunas fixas, a 2560 px
              o carrinho ocupava 820 px e os artigos ficavam em cinco cartões pequenos.
            */}
            <div className="lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(22rem,26rem)] 2xl:grid-cols-[minmax(0,1fr)_28rem] lg:h-[calc(100dvh_-_var(--pwa-topo,60px)_-_77px)]">
                <ColunaDosProdutos pos={pos} />

                {/* No ecrã largo o carrinho é a coluna da direita. Se não couber na
                    altura (portátil de 768px com a divisão aberta), rola a coluna — o
                    «Finalizar» nunca fica cortado. */}
                {largo && (
                    <aside aria-label={t('Carrinho')}
                           className="h-full min-h-0 bg-white flex flex-col border-l border-gray-200 overflow-y-auto">
                        <Carrinho pos={pos} />
                    </aside>
                )}
            </div>

            {!largo && <CarrinhoNoTelemovel pos={pos} />}

            {/*
              Aviso de receita médica — apaga-se sozinho ao fim de alguns segundos.
              Fica por cima do carrinho (z-55) porque no telemóvel o carrinho sobe em
              folha quase inteira, e um aviso escondido por trás dele não é aviso.
            */}
            {pos.avisoReceita && (
                <Camada>
                    <button type="button" role="status" onClick={pos.fecharAvisoReceita}
                            className="pwa-desce fixed top-16 inset-x-3 sm:left-auto sm:w-[28rem] z-[55] bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-2xl shadow-2xl px-4 py-3 flex items-start gap-3 text-left">
                        <span className="w-8 h-8 shrink-0 rounded-xl bg-white/20 flex items-center justify-center">
                            <i className="fas fa-prescription text-lg" aria-hidden="true" />
                        </span>
                        <span className="flex-1 text-sm font-bold leading-snug">{pos.avisoReceita}</span>
                        <span className="text-white/70 text-xl leading-none" aria-hidden="true">&times;</span>
                    </button>
                </Camada>
            )}

            <FolhaEscolherCliente pos={pos} />
            <FolhaClienteRapido pos={pos} />
            <ReciboDaVenda pos={pos} />
            <GavetaDePendentes pos={pos} />

            {/* As folhas de abrir e fechar o turno — as mesmas do restaurante. */}
            <Camada><FolhasDoTurno c={pos.turno} /></Camada>

            {pos.lerCamara && <LeitorPorCamara aoLer={(codigo) => pos.lerCodigo(codigo, true)} aoFechar={pos.fecharCamara} />}
        </div>
    );
}

/**
 * O carrinho no telemóvel: uma folha que sobe do fundo, a barra «Ver carrinho»
 * que fica a flutuar por cima do menu, e o fundo escurecido.
 *
 * A folha fica SEMPRE montada (escondida para baixo do ecrã), para subir e
 * descer com transição; fechada leva `inert`, para o Tab e o leitor de ecrã não
 * irem parar a campos que não se vêem.
 */
function CarrinhoNoTelemovel({ pos }: { pos: ControloDoPos }) {
    const aberto = pos.mostrarCarrinho;
    const fechar = pos.setMostrarCarrinho;

    useEffect(() => {
        if (!aberto) return;
        const tecla = (e: KeyboardEvent) => { if (e.key === 'Escape') fechar(false); };
        window.addEventListener('keydown', tecla);
        // Com a folha aberta a grelha por trás não rola: o dedo que desliza no
        // carrinho não pode ir mexer nos artigos.
        const antes = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            window.removeEventListener('keydown', tecla);
            document.body.style.overflow = antes;
        };
    }, [aberto, fechar]);

    return (
        <Camada>
            {/* Fundo da folha */}
            {aberto && <div className="pwa-fundo lg:hidden fixed inset-0 bg-black/50 backdrop-blur-[1px] z-40" onClick={() => fechar(false)} aria-hidden="true" />}

            <aside aria-label={t('Carrinho')} inert={!aberto}
                   className={`lg:hidden bg-white flex flex-col fixed inset-x-0 bottom-0 z-50 rounded-t-3xl shadow-2xl max-h-[92dvh] transition-transform duration-300 ease-out ${aberto ? 'translate-y-0' : 'translate-y-full'}`}
                   style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}>
                {/* A pega: diz, sem palavras, que isto se fecha para baixo. */}
                <button type="button" onClick={() => fechar(false)} aria-label={t('Fechar')}
                        className="shrink-0 pt-2 pb-0.5 flex justify-center">
                    <span className="w-10 h-1.5 rounded-full bg-gray-300" aria-hidden="true" />
                </button>
                <Carrinho pos={pos} folha />
            </aside>

            {/* Barra flutuante «Ver carrinho» — por cima do menu de baixo, sem o tapar. */}
            {pos.carrinho.length > 0 && !aberto && (
                <div className="pwa-sobe lg:hidden fixed bottom-[calc(84px+env(safe-area-inset-bottom))] inset-x-3 z-40">
                    <button type="button" onClick={() => fechar(true)}
                            className="pwa-toque w-full bg-gradient-to-r from-emerald-600 to-green-700 text-white rounded-2xl shadow-2xl shadow-emerald-900/30 px-4 py-3.5 flex items-center justify-between">
                        <span className="flex items-center gap-2.5 font-bold text-sm">
                            <span key={pos.contagem} className="pwa-cresce bg-white/25 rounded-full min-w-[28px] h-7 px-1 flex items-center justify-center">{pos.contagem}</span>
                            <i className="fas fa-cart-shopping" aria-hidden="true" />
                            {t('Ver carrinho')}
                        </span>
                        <span className="font-extrabold text-base tabular-nums">{kz(pos.totais.total)}</span>
                    </button>
                </div>
            )}

            {/* A gravar a venda, nada mais se toca: nem o carrinho, nem o menu. Uma vez só (o carrinho desenha-se em dois sítios). */}
            <AProcessar activo={pos.aGuardar} />
        </Camada>
    );
}
