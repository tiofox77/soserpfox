import { FOCO, RAIO, cls } from './tokens';

/**
 * SEPARADORES — as abas de um formulário longo.
 *
 * A ficha do funcionário tem cinquenta campos. Numa coluna só, quem a
 * preenche perde-se, e quem a revê não sabe onde parou; o ecrã em Blade
 * dividia-a em abas e é o que aqui volta.
 *
 * TRÊS COISAS QUE ESTA PEÇA FAZ E QUE UM `<div>` COM BOTÕES NÃO FAZ:
 *
 * 1. DIZ ONDE ESTÁ O ERRO. Um campo obrigatório por preencher numa aba que
 *    não está à frente é um formulário que se recusa a gravar sem explicar
 *    porquê — o defeito clássico. A aba com erros ganha um ponto vermelho com
 *    a contagem, e quem grava é levado à primeira.
 *
 * 2. É NAVEGÁVEL POR TECLADO com as setas, como um `tablist` deve ser.
 *
 * 3. NÃO DESMONTA O QUE ESTÁ ESCONDIDO. Trocar de aba não pode perder o que
 *    já se escreveu na outra — o conteúdo fica montado e apenas oculto.
 */
export type Separador = {
    chave: string;
    rotulo: string;
    icone: string;
    /** Quantos erros esta aba tem agora. Zero não desenha nada. */
    erros?: number;
};

export function Separadores({ abas, activa, aoMudar }: {
    abas: Separador[];
    activa: string;
    aoMudar: (chave: string) => void;
}) {
    const andar = (passo: number) => {
        const i = abas.findIndex((a) => a.chave === activa);
        const seguinte = abas[(i + passo + abas.length) % abas.length];

        if (seguinte) aoMudar(seguinte.chave);
    };

    return (
        <div role="tablist" className="-mx-1 flex flex-wrap gap-1 border-b border-slate-200 px-1 pb-px">
            {abas.map((a) => {
                const aberta = a.chave === activa;

                return (
                    <button
                        key={a.chave}
                        type="button"
                        role="tab"
                        aria-selected={aberta}
                        aria-controls={`painel-${a.chave}`}
                        tabIndex={aberta ? 0 : -1}
                        onClick={() => aoMudar(a.chave)}
                        onKeyDown={(e) => {
                            if (e.key === 'ArrowRight') { e.preventDefault(); andar(1); }
                            if (e.key === 'ArrowLeft') { e.preventDefault(); andar(-1); }
                        }}
                        className={cls(
                            'relative -mb-px inline-flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-semibold',
                            'transition-all duration-200',
                            RAIO,
                            FOCO,
                            aberta
                                ? 'border-indigo-600 text-indigo-700'
                                : 'border-transparent text-slate-500 hover:bg-slate-50 hover:text-slate-700',
                        )}
                    >
                        <i
                            className={cls(
                                'fas',
                                a.icone,
                                'text-xs transition-transform duration-200',
                                aberta && 'scale-110',
                            )}
                            aria-hidden="true"
                        />
                        {a.rotulo}

                        {/* O PONTO DO ERRO: a aba diz que tem alguma coisa por
                            corrigir, mesmo quando não está à frente. */}
                        {(a.erros ?? 0) > 0 && (
                            <span
                                className="grid h-4 min-w-4 place-items-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
                                aria-label={`${a.erros}`}
                            >
                                {a.erros}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}

/**
 * O painel de uma aba. Fica MONTADO quando escondido — trocar de aba não
 * pode perder o que já se escreveu na outra.
 */
export function PainelDoSeparador({ chave, activa, children }: {
    chave: string;
    activa: string;
    children: React.ReactNode;
}) {
    const aberto = chave === activa;

    return (
        <div
            id={`painel-${chave}`}
            role="tabpanel"
            hidden={!aberto}
            className={aberto ? 'entra' : undefined}
        >
            {children}
        </div>
    );
}
