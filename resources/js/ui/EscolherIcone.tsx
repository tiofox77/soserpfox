import { useEffect, useMemo, useRef, useState } from 'react';

import type { GrupoDeIcones } from '@/api/catalogos';
import { t } from '@/i18n';
import { FOCO, RAIO, cls } from './tokens';

/**
 * ESCOLHER UM ÍCONE — de uma galeria, e não escrevendo o código.
 *
 * Os formulários pediam `fa-money-bill` numa caixa de texto. Quem não sabe o
 * Font Awesome de cor não tinha por onde começar, e um código mal escrito não
 * dá erro nenhum: dá um quadrado vazio na lista, que só se descobre depois de
 * gravado. Aqui vê-se o que se escolhe antes de escolher.
 *
 * A GALERIA VEM DO SERVIDOR (`App\Support\GaleriaDeIcones`) e não daqui: há
 * formulários em React e formulários em Blade, e duas listas em dois sítios
 * divergem à primeira adição.
 *
 * ABRE PARA BAIXO e não noutra janela. Isto vive dentro de um formulário que
 * já está num `<dialog>`, e uma janela dentro de outra rouba o foco e o
 * Escape à de fora — fecha-se a errada.
 *
 * O QUE ESTÁ GRAVADO NUNCA SE PERDE. Se o registo tiver um ícone que não está
 * na galeria — os antigos têm, `fa-tshirt` e companhia — ele aparece à mesma,
 * marcado, e continua lá se ninguém lhe tocar. Um selector que apaga em
 * silêncio o valor que não reconhece é a mesma armadilha das listas de
 * escolha que não continham o valor do próprio registo.
 */
export function EscolherIcone({
    valor,
    aoMudar,
    etiqueta,
    galeria,
}: {
    valor: string;
    aoMudar: (v: string) => void;
    /** Para o nome acessível do botão que abre a galeria. */
    etiqueta: string;
    galeria: GrupoDeIcones[];
}) {
    const [aberto, porAberto] = useState(false);
    const [procura, porProcura] = useState('');
    const caixa = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (aberto) caixa.current?.focus();
    }, [aberto]);

    const daCasa = useMemo(
        () => new Set(galeria.flatMap((g) => g.icones.map((i) => i.codigo))),
        [galeria],
    );

    const grupos = useMemo(() => {
        /*
         * O QUE ESTÁ GRAVADO E NÃO ESTÁ NA GALERIA ganha um grupo só para
         * ele. Sem isto, abrir a ficha de uma categoria antiga mostrava a
         * grelha sem nada marcado — e a primeira escolha apagava o que lá
         * estava sem ninguém dar por isso.
         */
        const proprio = valor && !daCasa.has(valor)
            ? [{ nome: t('O que está guardado'), icones: [{ codigo: valor, nome: t('actual') }] }]
            : [];

        const todos = [...proprio, ...galeria];
        const p = procura.trim().toLowerCase();

        if (!p) return todos;

        return todos
            .map((g) => ({ ...g, icones: g.icones.filter((i) => i.codigo.includes(p) || i.nome.toLowerCase().includes(p)) }))
            .filter((g) => g.icones.length > 0);
    }, [procura, valor, daCasa, galeria]);

    const total = grupos.reduce((s, g) => s + g.icones.length, 0);

    return (
        <div>
            {/* O BOTÃO MOSTRA O QUE ESTÁ ESCOLHIDO — em tamanho de se ver, e
                não só o código escrito. */}
            <button
                type="button"
                onClick={() => porAberto((a) => !a)}
                aria-expanded={aberto}
                aria-label={`${etiqueta}: ${valor || t('nenhum')}`}
                className={cls(
                    'flex w-full items-center gap-3 border border-slate-300 bg-white px-3 py-2 text-left text-sm',
                    'transition-colors hover:border-indigo-400',
                    RAIO,
                    FOCO,
                )}
            >
                <span className="grid h-9 w-9 flex-none place-items-center rounded-lg bg-indigo-50 text-lg text-indigo-600">
                    {valor
                        ? <i className={cls('fas', valor)} aria-hidden="true" />
                        : <i className="fas fa-icons text-slate-300" aria-hidden="true" />}
                </span>
                <span className="min-w-0 flex-1 truncate font-mono text-xs text-slate-600">
                    {valor || t('Escolher um ícone…')}
                </span>
                <i className={cls('fas fa-chevron-down text-xs text-slate-400 transition-transform', aberto && 'rotate-180')} aria-hidden="true" />
            </button>

            {aberto && (
                <div className={cls('mt-2 border border-slate-200 bg-white p-3 shadow-lg', RAIO)}>
                    <div className="mb-3 flex items-center gap-2">
                        <input
                            ref={caixa}
                            type="search"
                            value={procura}
                            onChange={(e) => porProcura(e.target.value)}
                            onKeyDown={(e) => e.key === 'Escape' && porAberto(false)}
                            placeholder={t('Procurar: carrinho, banco, café…')}
                            aria-label={t('Procurar ícone')}
                            className={cls('h-9 flex-1 rounded-lg border border-slate-300 px-3 text-sm', FOCO)}
                        />
                        {valor && (
                            <button
                                type="button"
                                onClick={() => aoMudar('')}
                                className={cls('h-9 rounded-lg px-3 text-xs font-semibold text-slate-500 hover:bg-slate-100', FOCO)}
                            >
                                {t('Sem ícone')}
                            </button>
                        )}
                    </div>

                    <div className="max-h-64 overflow-y-auto pr-1">
                        {total === 0 && (
                            <p className="py-6 text-center text-sm text-slate-400">
                                {t('Nada com esse nome. Experimente outra palavra.')}
                            </p>
                        )}

                        {grupos.map((g) => (
                            <div key={g.nome} className="mb-3 last:mb-0">
                                <p className="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-400">{g.nome}</p>
                                <div className="grid grid-cols-6 gap-1 sm:grid-cols-8">
                                    {g.icones.map((i) => {
                                        const escolhido = valor === i.codigo;

                                        return (
                                            <button
                                                key={i.codigo}
                                                type="button"
                                                onClick={() => {
                                                    aoMudar(i.codigo);
                                                    porAberto(false);
                                                }}
                                                title={`${i.nome} · ${i.codigo}`}
                                                // O NOME POR EXTENSO para quem ouve: «carrinho»
                                                // diz mais do que `fa-cart-shopping`.
                                                aria-label={i.nome}
                                                aria-pressed={escolhido}
                                                className={cls(
                                                    'grid aspect-square place-items-center rounded-lg text-lg transition-all duration-150',
                                                    FOCO,
                                                    escolhido
                                                        ? 'bg-indigo-600 text-white shadow-md'
                                                        : 'text-slate-600 hover:-translate-y-0.5 hover:bg-indigo-50 hover:text-indigo-600',
                                                )}
                                            >
                                                <i className={cls('fas', i.codigo)} aria-hidden="true" />
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
