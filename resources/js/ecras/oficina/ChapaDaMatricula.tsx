import { t } from '@/i18n';
import { cls } from '@/ui/tokens';

/**
 * A CHAPA DA MATRÍCULA — desenhada como a matrícula nova de Angola (15/09/2026).
 *
 * Pedido: «melhora para se parecer com a nova matrícula de Angola». A chapa é
 * branca com rebordo preto; à esquerda a bandeira e «AO»; depois o código da
 * província (LDA), o selo e os números em letra estreita e alta: «LDA 28-62-RP».
 *
 * O que se escreveu na matrícula lê-se sempre igual, com ou sem traços: tanto
 * «LDA2862RP» como «LD-42-11-AB» (o formato antigo) saem arrumados. O que não
 * encaixa em nenhum formato sai tal como está escrito.
 */
export function partesDaMatricula(texto: string): { prefixo: string; resto: string } | null {
    const limpo = texto.toUpperCase().replace(/[^A-Z0-9]/g, '');
    const m = limpo.match(/^([A-Z]{2,3})(\d{2})(\d{2})([A-Z]{2})$/);

    return m ? { prefixo: m[1] ?? '', resto: `${m[2]}-${m[3]}-${m[4]}` } : null;
}

/** A bandeira de Angola: vermelho em cima, preto em baixo, a meia roda dentada, a catana e a estrela a amarelo. */
function Bandeira({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 30 20" className={className} aria-hidden="true">
            <rect width="30" height="10" fill="#CC092F" />
            <rect y="10" width="30" height="10" fill="#000" />
            <g fill="none" stroke="#FFCB00" strokeLinecap="round">
                {/* a meia roda dentada */}
                <path d="M11.2 7.4 A5 5 0 1 0 19.6 7.6" strokeWidth="1.3" strokeDasharray="1.1 0.55" />
                {/* a catana */}
                <path d="M11.8 14.2 L18.6 6.4" strokeWidth="1.2" />
                <path d="M18.6 6.4 q1.6 -0.4 1.9 1.3" strokeWidth="0.9" />
            </g>
            {/* a estrela */}
            <path fill="#FFCB00" d="M13.6 5.2l.45 1.1 1.2.05-.93.75.32 1.15-1.04-.66-1 .66.32-1.15-.93-.75 1.2-.05z" />
        </svg>
    );
}

export function ChapaDaMatricula({ matricula, tamanho = 'normal', className }: {
    matricula: string;
    tamanho?: 'pequeno' | 'normal';
    className?: string;
}) {
    const vazia = matricula.trim() === '';
    const partes = partesDaMatricula(vazia ? 'LDA0000AA' : matricula);
    const pequeno = tamanho === 'pequeno';

    return (
        <span
            role="img"
            aria-label={vazia ? t('Matrícula por escrever') : t('Matrícula :m', { m: matricula.toUpperCase() })}
            className={cls(
                'group/chapa relative inline-flex select-none items-stretch overflow-hidden bg-gradient-to-b from-white via-white to-slate-100 text-black',
                'shadow-[0_2px_6px_rgba(0,0,0,.35),inset_0_1px_0_rgba(255,255,255,.9)] ring-1 ring-black/60',
                'transition-transform duration-300 hover:-translate-y-0.5 hover:scale-[1.02]',
                pequeno ? 'rounded-[5px] p-[2px]' : 'rounded-lg p-[3px]',
                vazia && 'opacity-60',
                className,
            )}
        >
            {/* O rebordo preto por dentro, como a chapa verdadeira. */}
            <span className={cls('flex items-stretch border-black', pequeno ? 'rounded-[3px] border' : 'rounded-[5px] border-2')}>
                {/* A faixa da esquerda: bandeira e AO. */}
                <span className={cls('flex flex-col items-center justify-center gap-0.5', pequeno ? 'px-1' : 'px-1.5')}>
                    <Bandeira className={cls('rounded-[1px] shadow-sm', pequeno ? 'h-2.5 w-[15px]' : 'h-[14px] w-[21px]')} />
                    <span className={cls('font-bold leading-none tracking-tight', pequeno ? 'text-[6px]' : 'text-[9px]')}>AO</span>
                </span>

                <span
                    className={cls('flex items-center whitespace-nowrap font-bold leading-none', pequeno ? 'gap-1 pl-0.5 pr-1.5 text-[15px]' : 'gap-1.5 py-1 pl-1 pr-2.5 text-[27px]')}
                    style={{ fontFamily: "'DIN Condensed', 'Bahnschrift SemiCondensed', 'Roboto Condensed', 'Arial Narrow', 'Helvetica Neue', Arial, sans-serif", fontStretch: 'condensed' }}
                >
                    {partes ? (
                        <>
                            <span className="inline-block origin-center scale-y-[1.12]">{partes.prefixo}</span>
                            {/* O selo de segurança entre a província e os números. */}
                            <span aria-hidden="true" className={cls(
                                'grid place-items-center rounded-[2px] bg-gradient-to-br from-slate-200 via-slate-300 to-slate-100 ring-1 ring-inset ring-slate-400/60',
                                'transition-all duration-500 group-hover/chapa:from-sky-100 group-hover/chapa:via-violet-200 group-hover/chapa:to-amber-100',
                                pequeno ? 'h-2.5 w-2' : 'h-[15px] w-[12px]',
                            )}>
                                <span className={cls('rounded-full ring-1 ring-slate-500/50', pequeno ? 'h-1 w-1' : 'h-1.5 w-1.5')} />
                            </span>
                            <span className="inline-block origin-center scale-y-[1.12]">{partes.resto}</span>
                        </>
                    ) : (
                        <span className="inline-block origin-center scale-y-[1.12] tracking-wide">{matricula.toUpperCase()}</span>
                    )}
                </span>
            </span>
        </span>
    );
}
