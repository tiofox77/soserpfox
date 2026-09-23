import type { CSSProperties, ReactNode } from 'react';

import { Etiqueta } from '@/ui/Etiqueta';
import { FOCO, GRADIENTES, RAIO, RAIO_GRANDE, TRANSICAO, cls } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * AS PEÇAS QUE OS SETE EDITORES DE DOCUMENTO PARTILHAM.
 *
 * Factura de venda, factura de compra, proposta, notas, recibo, adiantamento
 * e o modal de pagamento faziam todos as mesmas quatro coisas — o cartão dos
 * totais, o painel de quem acabou de emitir, a faixa que diz em que estado
 * está o documento aberto, e a tabela das linhas — e faziam-nas cada um à sua
 * maneira. Em Blade eram sete cópias do mesmo bloco; ao passar para React
 * ficaram sete tabelinhas planas.
 *
 * Aqui são UMA de cada, e por isso o que se corrige num corrige-se em todos.
 * Nada disto muda comportamento: não há um pedido, um campo ou um `data-*`
 * que passe por este ficheiro.
 */

/* ─── O cartão dos totais ─────────────────────────────────────────────── */

/**
 * O CARTÃO DOS TOTAIS — o «Resumo» de sempre.
 *
 * É o número que a pessoa confere antes de emitir, e tem de saltar à vista.
 * Os três ecrãs em Blade (venda, compra e proposta) tinham exactamente este
 * bloco: cabeçalho verde com a máquina de calcular, as parcelas por baixo, e
 * o total num tamanho que se lê do outro lado do balcão. Em React tinha ficado
 * uma lista de pares indistinguível do cartão das observações ao lado.
 *
 * O verde é o `bom` da casa (`GRADIENTES.bom`) e não uma cor nova: aqui quer
 * dizer «a conta está feita», e o ícone diz o mesmo a quem não a distingue.
 */
export function CartaoDeTotais({
    titulo,
    aContar = false,
    children,
}: {
    titulo: string;
    /** Enquanto o servidor não responde, o cartão diz que está a contar. */
    aContar?: boolean;
    children: ReactNode;
}) {
    return (
        <section
            className={cls(
                'flex flex-col overflow-hidden border border-slate-200 bg-white shadow-lg',
                RAIO_GRANDE,
                TRANSICAO,
                'hover:shadow-xl',
            )}
        >
            <header className={cls('flex items-center gap-3 px-5 py-4 text-white', GRADIENTES.bom)}>
                <span className="grid h-10 w-10 flex-none place-items-center rounded-xl bg-white/20 text-lg">
                    <i className="fas fa-calculator" aria-hidden="true" />
                </span>

                <h2 className="min-w-0 flex-1 truncate text-base font-bold tracking-tight">{titulo}</h2>

                {/* A conta está a ser refeita: diz-se, em vez de deixar os
                    números antigos a fingir que já são os novos. */}
                {aContar && (
                    <span role="status" className="flex flex-none items-center gap-1.5 text-xs font-medium text-white/90">
                        <i className="fas fa-spinner fa-spin" aria-hidden="true" />
                        {t('a actualizar…')}
                    </span>
                )}
            </header>

            {children}
        </section>
    );
}

/** Uma parcela do resumo: o rótulo à esquerda, o valor à direita. */
export function ParcelaDoTotal({
    rotulo,
    valor,
    icone,
    realce,
}: {
    rotulo: string;
    valor: ReactNode;
    icone?: string;
    /**
     * O IMPOSTO e a RETENÇÃO destacam-se das outras parcelas — são as duas
     * que quem confere procura primeiro. Cor e ícone, nunca cor sozinha.
     */
    realce?: 'imposto' | 'retencao';
}) {
    const tinta =
        realce === 'imposto'
            ? 'bg-indigo-50 text-indigo-800'
            : realce === 'retencao'
              ? 'bg-red-50 text-red-800'
              : 'text-slate-700';

    return (
        <div
            className={cls(
                'flex items-baseline justify-between gap-3 border-b border-slate-100 py-2 text-sm last:border-b-0',
                realce && cls('my-0.5 rounded-lg border-b-0 px-2', tinta),
                !realce && tinta,
            )}
        >
            <dt className="flex min-w-0 items-center gap-1.5 font-semibold">
                {icone && <i className={`fas ${icone} flex-none text-xs opacity-70`} aria-hidden="true" />}
                <span className="truncate">{rotulo}</span>
            </dt>
            <dd className="flex-none font-bold tabular-nums">{valor}</dd>
        </div>
    );
}

/**
 * O TOTAL, em grande.
 *
 * Numa faixa própria no fundo do cartão: é o único número da página que se lê
 * sem procurar, e era assim que o Blade o dava (`text-3xl font-bold
 * text-green-600` sobre a linha de separação).
 */
export function TotalGrande({ rotulo, valor, nota }: { rotulo: string; valor: ReactNode; nota?: string }) {
    return (
        <div className="mt-auto border-t-2 border-emerald-200 bg-emerald-50/70 px-5 py-4">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <dt className="text-base font-bold text-slate-700">{rotulo}</dt>
                <dd className="text-2xl font-bold tabular-nums text-emerald-700 sm:text-3xl">{valor}</dd>
            </div>
            {nota && <p className="mt-1 text-xs text-emerald-800/70">{nota}</p>}
        </div>
    );
}

/* ─── Depois de emitir ────────────────────────────────────────────────── */

/**
 * O PAINEL DE QUEM ACABOU DE EMITIR.
 *
 * Emitir uma factura é o acto mais importante que se faz nesta aplicação, e
 * o ecrã respondia com um cartão branco e um visto pequeno. Aqui volta a ser
 * uma peça celebrada — o ícone grande num quadrado translúcido, o número do
 * documento em destaque, e o estado da AGT logo por baixo, que é a segunda
 * pergunta de quem acabou de emitir.
 *
 * A entrada usa a `animate-scale-in` que a aplicação já tinha; quem pediu
 * menos movimento não a leva, e a guarda está no layout.
 */
export function PainelDeSucesso({
    numero,
    mensagem,
    agt,
    icone = 'fa-circle-check',
    aviso,
    children,
}: {
    numero: string;
    mensagem: string;
    /** O que a AGT respondeu, quando o documento passa por lá. */
    agt?: string | null;
    icone?: string;
    /**
     * O que correu mal DEPOIS de gravar — hoje, a janela do PDF que o browser
     * não deixou abrir. Fica por cima dos botões, que é onde está o remédio.
     */
    aviso?: ReactNode;
    /** Os botões do que se faz a seguir. */
    children: ReactNode;
}) {
    return (
        <div
            className={cls(
                'mx-auto max-w-xl overflow-hidden border border-emerald-100 bg-white shadow-xl',
                RAIO_GRANDE,
                'animate-scale-in',
            )}
        >
            <div className={cls('px-6 py-8 text-center text-white', GRADIENTES.bom)}>
                <span className="mx-auto grid h-20 w-20 place-items-center rounded-2xl bg-white/20 text-4xl">
                    <i className={`fas ${icone}`} aria-hidden="true" />
                </span>

                <h2 className="mt-4 break-words text-2xl font-bold tracking-tight sm:text-3xl">{numero}</h2>
                <p className="mt-1 text-sm text-white/85">{mensagem}</p>
            </div>

            {agt && (
                <p className="border-b border-slate-100 bg-slate-50 px-6 py-3 text-center text-xs font-medium text-slate-600">
                    <i className="fas fa-shield-halved mr-1.5 text-emerald-600" aria-hidden="true" />
                    {agt}
                </p>
            )}

            {aviso && <div className="px-6 pt-5">{aviso}</div>}

            <div className="flex flex-wrap justify-center gap-2 px-6 py-5">{children}</div>
        </div>
    );
}

/**
 * A IMPRESSÃO AO GRAVAR FOI BLOQUEADA.
 *
 * A empresa pediu o PDF aberto sozinho depois de gravar, e o browser recusou
 * a janela — ver `imprimirAoGravar.ts`. Dizê-lo é metade do remédio; a outra
 * metade é o botão «PDF» logo abaixo, que abre a mesma morada com um clique
 * que o bloqueador já não trava.
 */
export function PapelBloqueado() {
    return (
        <Aviso icone="fa-print">
            <strong className="block">{t('A impressão foi bloqueada')}</strong>
            {t('O browser não deixou abrir a janela do PDF. Carregue em «PDF» para o abrir, ou permita pop-ups para este site.')}
        </Aviso>
    );
}

/* ─── As faixas que dizem em que pé está o documento ──────────────────── */

/**
 * A FAIXA DO DOCUMENTO ABERTO: rascunho que se altera, ou emitido só de ler.
 *
 * É a primeira coisa que se lê ao abrir um documento existente, e a diferença
 * entre os dois estados não pode ficar só na cor de fundo: o cadeado e a
 * caneta dizem-no a quem não a distingue.
 */
export function FaixaDoDocumento({
    soLeitura,
    numero,
    estado,
    accao,
    children,
}: {
    soLeitura: boolean;
    numero: string;
    estado: string;
    /** O botão do PDF, quando o ecrã o tem. */
    accao?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div
            data-documento-aberto
            className={cls(
                'flex flex-wrap items-center justify-between gap-3 border px-4 py-3 text-sm shadow-sm',
                RAIO,
                TRANSICAO,
                soLeitura
                    ? 'border-slate-200 bg-slate-50 text-slate-700'
                    : 'border-amber-200 bg-amber-50 text-amber-900',
            )}
        >
            <span className="flex min-w-0 flex-wrap items-center gap-2">
                <span
                    className={cls(
                        'grid h-8 w-8 flex-none place-items-center rounded-lg',
                        soLeitura ? 'bg-slate-200 text-slate-600' : 'bg-amber-100 text-amber-700',
                    )}
                >
                    <i className={`fas ${soLeitura ? 'fa-lock' : 'fa-pen-to-square'}`} aria-hidden="true" />
                </span>

                <strong className="font-bold">{numero}</strong>
                <Etiqueta cor={soLeitura ? 'neutra' : 'aviso'} ponto>
                    {estado}
                </Etiqueta>
                <span className="min-w-0">{children}</span>
            </span>

            {accao}
        </div>
    );
}

/**
 * A FAIXA DO DUPLICADO.
 *
 * Um formulário que aparece cheio sem explicação faz quem o vê pensar que
 * está a editar o original — e a hesitar em gravar. Duas linhas resolvem-no:
 * de onde veio, e que isto é um documento novo.
 */
export function FaixaDeDuplicado({ numeroDaOrigem, children }: { numeroDaOrigem: string; children: ReactNode }) {
    return (
        <div
            data-duplicado-de={numeroDaOrigem}
            className={cls(
                'flex items-start gap-3 border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900 shadow-sm',
                RAIO,
            )}
        >
            <span className="grid h-8 w-8 flex-none place-items-center rounded-lg bg-teal-100 text-teal-700">
                <i className="fas fa-copy" aria-hidden="true" />
            </span>
            <span className="min-w-0 leading-relaxed">{children}</span>
        </div>
    );
}

/**
 * O AVISO DE UMA LINHA — o que o Blade punha em faixa âmbar com o triângulo.
 *
 * Serve o «esta factura já está toda creditada» e o «este adiantamento já foi
 * usado»: casos em que o ecrã abre e não deixa fazer nada, e em que dizer
 * porquê é a outra metade do trabalho.
 */
export function Aviso({ icone = 'fa-triangle-exclamation', children }: { icone?: string; children: ReactNode }) {
    return (
        <div
            role="alert"
            className={cls('flex items-start gap-3 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm', RAIO)}
        >
            <span className="grid h-8 w-8 flex-none place-items-center rounded-lg bg-amber-100 text-amber-700">
                <i className={`fas ${icone}`} aria-hidden="true" />
            </span>
            <span className="min-w-0 leading-relaxed">{children}</span>
        </div>
    );
}

/* ─── A tabela das linhas ─────────────────────────────────────────────── */

/**
 * O CABEÇALHO DA TABELA: fundo cinzento claro e maiúsculas pequenas.
 *
 * Sem fundo, o cabeçalho lia-se como mais uma linha da tabela — e numa tabela
 * de lançamento, em que todas as células são caixas de escrever, isso conta.
 */
export const CABECALHO_DA_TABELA = 'bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600';

/**
 * O CAMPO DO PREÇO DE UMA LINHA: mostra o número inteiro.
 *
 * A coluna tinha 8rem e as setas do campo numérico comiam o resto: um preço de
 * 1 200 000 lia-se «120(» (pedido de 23/09/2026). Largura para onze algarismos,
 * e sem as setas, que num preço não servem para nada.
 */
export const CAMPO_DE_PRECO = 'min-w-[8.5rem] text-right tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none';

/** Uma célula do cabeçalho. */
export const CELULA_DO_CABECALHO = 'px-4 py-3 font-bold';

/**
 * UMA LINHA DA TABELA.
 *
 * Realça ao passar o rato — é o que diz onde está o cursor numa grelha de
 * vinte caixas iguais — e entra em cascata quando nasce (`entra`, com o
 * atraso na variável `--i`).
 */
export const LINHA_DA_TABELA = 'group entra transition-all duration-200 hover:bg-indigo-50/60';


/**
 * O BOTÃO DE APAGAR A LINHA: discreto até se passar por cima.
 *
 * Numa tabela de dez linhas, dez caixotes vermelhos são dez avisos de perigo
 * a competir com o trabalho. Fica cinzento e acende ao chegar lá — mas NUNCA
 * invisível: quem navega por teclado tem de o encontrar, e num ecrã táctil
 * não há «passar por cima».
 */
export function ApagarLinha({ aoCarregar, rotulo }: { aoCarregar: () => void; rotulo: string }) {
    return (
        <button
            type="button"
            onClick={aoCarregar}
            aria-label={rotulo}
            title={rotulo}
            className={cls(
                'p-2 text-slate-300 group-hover:text-red-400',
                'hover:bg-red-50 hover:text-red-600 hover:scale-110',
                TRANSICAO,
                RAIO,
                FOCO,
            )}
        >
            <i className="fas fa-trash" aria-hidden="true" />
        </button>
    );
}


/**
 * O QUE CORREU MAL AO ABRIR O ECRÃ.
 *
 * Existia copiado em seis ficheiros, sempre igual e sempre plano. Aqui leva o
 * ícone e o peso de um cartão: um ecrã que não abriu tem de se ver como tal.
 */
export function NaoAbriu({ titulo, mensagem }: { titulo: string; mensagem: string }) {
    return (
        <div
            role="alert"
            className={cls('flex items-start gap-4 border border-red-200 bg-red-50 p-6 shadow-sm', RAIO_GRANDE)}
        >
            <span className="grid h-12 w-12 flex-none place-items-center rounded-xl bg-red-100 text-xl text-red-600">
                <i className="fas fa-circle-exclamation" aria-hidden="true" />
            </span>
            <div className="min-w-0">
                <h2 className="mb-1 text-lg font-bold text-red-900">{titulo}</h2>
                <p className="text-sm text-red-800">{mensagem}</p>
            </div>
        </div>
    );
}

/* A peça e o atraso vivem em `@/ui/SemNada` — nasceram aqui duas vezes,
   no mesmo dia, e uma delas tinha de sair. Reexportam-se para os ecrãs que
   as importam daqui não terem de mudar de porta. */
export { SemNada, cascata } from '@/ui/SemNada';
