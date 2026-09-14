import { useEffect, useMemo, useRef, useState, type ChangeEvent, type CSSProperties, type DragEvent, type ReactNode } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';

import { ErroDaApi } from '@/api/cliente';
import { produtos, type Artigo } from '@/api/produtos';
import { avisar } from '@/casca/avisos';
import { Rotulo } from '@/ui/Campo';
import { ImagemRecusada, prepararImagem, tamanhoLegivel } from '@/ui/prepararImagem';
import { FOCO, RAIO, TRANSICAO, cls } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * A IMAGEM DE DESTAQUE E A GALERIA DE UM ARTIGO.
 *
 * O QUE CUSTAVA (pedido de 2026-09-14, «estou a ter dificuldade em enviar a
 * imagem de destaque e a galeria»):
 *  • uma fotografia de telemóvel (3–8 MB) passava do tecto e era recusada;
 *  • nada subia ao escolher — só no «Guardar», e se falhasse a janela fechava
 *    e a fotografia perdia-se;
 *  • a galeria subia tudo num pedido só, e várias fotografias juntas passavam
 *    o limite do servidor;
 *  • a pré-visualização era a fotografia inteira (4000 píxeis), que o browser
 *    descodifica em diferido — e dentro do `<dialog>` o Chromium pinta-a de
 *    BRANCO. Provado no ensaio: com `decoding="async"` a pré-visualização
 *    ficava branca com a imagem carregada. Por isso nenhuma imagem daqui leva
 *    `decoding`/`loading`, e a pré-visualização é já a imagem reduzida;
 *  • fechar o seletor de ficheiros sem escolher fechava a ficha (ver ui/Modal).
 *
 * COMO FICA:
 *  • cada ficheiro é PREPARADO no browser — recusado se não for imagem,
 *    reduzido a 1600 píxeis se for grande (ui/prepararImagem);
 *  • A EDITAR, sobe logo ao escolher, uma imagem por pedido, com o estado à
 *    vista e o aviso no canto; A CRIAR, fica à espera do «Guardar» (antes disso
 *    o artigo não tem número) e sobe depois, também uma a uma;
 *  • arrastar e largar, além de clicar.
 */

export const MAXIMO_DA_GALERIA = 10;

const mensagemDe = (e: unknown): string =>
    e instanceof ImagemRecusada
        ? e.message
        : e instanceof ErroDaApi
          ? (e.erros.imagem?.[0] ?? e.erros.imagens?.[0] ?? Object.values(e.erros).flat()[0] ?? e.message)
          : t('Não foi possível enviar a imagem.');

/** Os ficheiros de uma largada ou de uma escolha (quem os recusa é o `prepararImagem`). */
const ficheirosDe = (lista: FileList | null | undefined): File[] => Array.from(lista ?? []);

function ZonaDeLargar({ aoLargar, activa, children, className }: { aoLargar: (fs: File[]) => void; activa: boolean; children: (aPairar: boolean) => ReactNode; className?: string }) {
    const [aPairar, porAPairar] = useState(false);
    const profundidade = useRef(0);

    const entrar = (e: DragEvent) => {
        if (!activa || !e.dataTransfer.types.includes('Files')) return;
        e.preventDefault();
        profundidade.current += 1;
        porAPairar(true);
    };
    const sair = () => {
        profundidade.current = Math.max(0, profundidade.current - 1);
        if (profundidade.current === 0) porAPairar(false);
    };

    return (
        <div
            onDragEnter={entrar}
            onDragOver={(e) => {
                if (activa && e.dataTransfer.types.includes('Files')) e.preventDefault();
            }}
            onDragLeave={sair}
            onDrop={(e) => {
                e.preventDefault();
                profundidade.current = 0;
                porAPairar(false);
                if (activa) aoLargar(ficheirosDe(e.dataTransfer.files));
            }}
            className={className}
        >
            {children(aPairar)}
        </div>
    );
}

export function ImagensDoArtigo({
    artigo,
    destaque,
    galeria,
    aoEscolherDestaque,
    aoJuntarAGaleria,
    aoTirarDaGaleria,
}: {
    /** O artigo que se edita; `null` a criar. */
    artigo: Artigo | null;
    /** A criar: o destaque à espera do «Guardar». */
    destaque: File | null;
    /** A criar: as imagens da galeria à espera do «Guardar». */
    galeria: File[];
    aoEscolherDestaque: (f: File | null) => void;
    aoJuntarAGaleria: (fs: File[]) => void;
    aoTirarDaGaleria: (indice: number) => void;
}) {
    const cache = useQueryClient();
    const aEditar = artigo !== null;

    const [guardado, porGuardado] = useState({ imagem: artigo?.imagem ?? null, galeria: artigo?.galeria ?? [] });
    const [aPrepararDestaque, porAPrepararDestaque] = useState(false);
    const [aSubirNaGaleria, porASubirNaGaleria] = useState(0);
    const [problemaDoDestaque, porProblemaDoDestaque] = useState<string | null>(null);
    const [problemaDaGaleria, porProblemaDaGaleria] = useState<string | null>(null);
    /* O destaque acabado de guardar: o visto verde acende um instante. */
    const [acabadoDeGuardar, porAcabadoDeGuardar] = useState(false);

    const actualizar = (a: Artigo) => {
        porGuardado({ imagem: a.imagem, galeria: a.galeria });
        void cache.invalidateQueries({ queryKey: ['produtos'] });
    };

    useEffect(() => {
        if (!acabadoDeGuardar) return;
        const h = setTimeout(() => porAcabadoDeGuardar(false), 2200);
        return () => clearTimeout(h);
    }, [acabadoDeGuardar]);

    /* ─── A editar: sobe logo ─── */

    const subirDestaque = useMutation({
        mutationFn: (f: File) => produtos.imagem(artigo!.id, f),
        onSuccess: (r) => {
            actualizar(r.data);
            porAcabadoDeGuardar(true);
        },
        onError: (e) => porProblemaDoDestaque(mensagemDe(e)),
    });

    // A galeria sobe uma a uma e avisa UMA vez no fim: dez avisos seguidos no
    // canto eram ruído.
    const subirNaGaleria = useMutation({
        mutationFn: (f: File) => produtos.galeria(artigo!.id, [f]),
        meta: { aviso: false },
    });

    const remover = useMutation({
        mutationFn: (caminho: string | null) => (caminho === null ? produtos.apagarImagem(artigo!.id) : produtos.apagarDaGaleria(artigo!.id, caminho)),
        onSuccess: (r) => actualizar(r.data),
    });

    /* ─── Receber ficheiros ─── */

    const receberDestaque = async (ficheiros: File[]) => {
        const f = ficheiros[0];
        if (!f) return;
        porProblemaDoDestaque(null);
        porAPrepararDestaque(true);

        try {
            const pronto = await prepararImagem(f);

            if (aEditar) {
                await subirDestaque.mutateAsync(pronto).catch(() => undefined);
            } else {
                aoEscolherDestaque(pronto);
            }
        } catch (e) {
            porProblemaDoDestaque(mensagemDe(e));
            avisar(mensagemDe(e), 'erro');
        } finally {
            porAPrepararDestaque(false);
        }
    };

    const lugaresLivres = MAXIMO_DA_GALERIA - guardado.galeria.length - galeria.length;

    const receberNaGaleria = async (ficheiros: File[]) => {
        if (ficheiros.length === 0) return;
        porProblemaDaGaleria(null);

        const cabem = ficheiros.slice(0, Math.max(0, lugaresLivres));
        const falhas: string[] = [];

        if (cabem.length < ficheiros.length) {
            falhas.push(t('A galeria só leva :max imagens: :fora ficaram de fora.', { max: MAXIMO_DA_GALERIA, fora: ficheiros.length - cabem.length }));
        }

        porASubirNaGaleria(cabem.length);
        let subidas = 0;
        const prontas: File[] = [];

        for (const f of cabem) {
            try {
                const pronto = await prepararImagem(f);

                if (aEditar) {
                    const r = await subirNaGaleria.mutateAsync(pronto);
                    actualizar(r.data);
                    subidas += 1;
                } else {
                    prontas.push(pronto);
                }
            } catch (e) {
                falhas.push(`${f.name}: ${mensagemDe(e)}`);
            } finally {
                porASubirNaGaleria((n) => Math.max(0, n - 1));
            }
        }

        if (!aEditar && prontas.length > 0) aoJuntarAGaleria(prontas);

        if (aEditar && subidas > 0) {
            avisar(subidas === 1 ? t('Imagem juntada à galeria.') : t(':n imagens juntadas à galeria.', { n: subidas }), 'ok');
        }
        if (falhas.length > 0) {
            porProblemaDaGaleria(falhas.join(' · '));
            avisar(falhas[0] ?? '', 'erro');
        }
    };

    /* ─── As pré-visualizações do que ainda não subiu ─── */

    const previaDoDestaque = useMemo(() => (destaque ? URL.createObjectURL(destaque) : null), [destaque]);
    const previasDaGaleria = useMemo(() => galeria.map((f) => URL.createObjectURL(f)), [galeria]);

    // Cada `createObjectURL` segura o ficheiro em memória até ao `revoke`.
    useEffect(() => () => { if (previaDoDestaque) URL.revokeObjectURL(previaDoDestaque); }, [previaDoDestaque]);
    useEffect(() => () => previasDaGaleria.forEach((u) => URL.revokeObjectURL(u)), [previasDaGaleria]);

    const destaqueOcupado = aPrepararDestaque || subirDestaque.isPending;
    const imagemDoDestaque = previaDoDestaque ?? guardado.imagem;

    const escolher = (aoEscolher: (fs: File[]) => void) => (e: ChangeEvent<HTMLInputElement>) => {
        const fs = ficheirosDe(e.target.files);
        // Limpar já: escolher o MESMO ficheiro outra vez volta a disparar `change`.
        e.target.value = '';
        void aoEscolher(fs);
    };

    return (
        <div className="grid gap-5 sm:col-span-3 sm:grid-cols-2" data-imagens-do-artigo>
            {/* ─── Destaque ─── */}
            <div>
                <Rotulo>
                    <i className="fas fa-star mr-1.5 text-amber-500" aria-hidden="true" />
                    {t('Imagem de destaque')}
                </Rotulo>

                <ZonaDeLargar activa={!destaqueOcupado} aoLargar={(fs) => void receberDestaque(fs)}>
                    {(aPairar) => (
                        <div
                            className={cls(
                                'flex items-center gap-4 border-2 border-dashed p-3',
                                RAIO,
                                TRANSICAO,
                                aPairar ? 'scale-[1.01] border-violet-500 bg-violet-50' : 'border-slate-200 bg-slate-50/60 hover:border-violet-300',
                            )}
                        >
                            <div className="relative h-28 w-28 flex-none">
                                {imagemDoDestaque ? (
                                    <img
                                        src={imagemDoDestaque}
                                        alt={previaDoDestaque ? t('A imagem escolhida, ainda por enviar') : t('Imagem de :nome', { nome: artigo?.name ?? t('artigo') })}
                                       
                                        className={cls('animate-scale-in h-28 w-28 border bg-white object-cover shadow-sm', RAIO, previaDoDestaque ? 'border-emerald-300' : 'border-slate-200')}
                                    />
                                ) : (
                                    <div className={cls('grid h-28 w-28 place-items-center bg-white text-slate-300 shadow-inner', RAIO)} aria-hidden="true">
                                        <i className={cls('fas text-3xl', aPairar ? 'fa-cloud-arrow-up animate-bounce text-violet-500' : 'fa-image')} />
                                    </div>
                                )}

                                {destaqueOcupado && (
                                    <div className={cls('absolute inset-0 grid place-items-center bg-white/80 text-violet-700 backdrop-blur-[1px]', RAIO)} role="status">
                                        <span className="text-center text-[11px] font-semibold">
                                            <i className="fas fa-spinner fa-spin mb-1 block text-lg" aria-hidden="true" />
                                            {aPrepararDestaque ? t('A preparar…') : t('A enviar…')}
                                        </span>
                                    </div>
                                )}

                                {acabadoDeGuardar && !destaqueOcupado && (
                                    <span className="animate-scale-in absolute -right-2 -top-2 grid h-7 w-7 place-items-center rounded-full bg-emerald-500 text-white shadow-md" title={t('Guardada')}>
                                        <i className="fas fa-check text-xs" aria-hidden="true" />
                                    </span>
                                )}
                                {previaDoDestaque && (
                                    <span className="absolute inset-x-0 -bottom-2 mx-auto w-fit rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow">
                                        {t('por enviar')}
                                    </span>
                                )}
                            </div>

                            <div className="min-w-0 flex-1 space-y-2">
                                <label
                                    className={cls(
                                        'inline-flex cursor-pointer items-center gap-2 bg-gradient-to-r from-violet-600 to-fuchsia-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:-translate-y-0.5 hover:shadow-md focus-within:ring-2 focus-within:ring-violet-400',
                                        RAIO,
                                        TRANSICAO,
                                        destaqueOcupado && 'pointer-events-none opacity-60',
                                    )}
                                >
                                    <i className="fas fa-upload" aria-hidden="true" />
                                    {imagemDoDestaque ? t('Trocar imagem') : t('Escolher imagem')}
                                    <input type="file" accept="image/*" className="sr-only" disabled={destaqueOcupado} onChange={escolher((fs) => void receberDestaque(fs))} />
                                </label>

                                <p className="text-xs text-slate-500">
                                    {t('Ou arraste para aqui. JPG, PNG, GIF ou WebP — as fotografias grandes são reduzidas sozinhas.')}
                                </p>

                                {destaque && (
                                    <p className="text-xs text-emerald-700">
                                        <i className="fas fa-clock mr-1" aria-hidden="true" />
                                        {t('Sobe ao guardar (:tamanho).', { tamanho: tamanhoLegivel(destaque.size) })}{' '}
                                        <button type="button" onClick={() => aoEscolherDestaque(null)} className={cls('font-semibold text-red-600 underline decoration-dotted', FOCO)}>
                                            {t('Cancelar a escolha')}
                                        </button>
                                    </p>
                                )}

                                {!destaque && guardado.imagem && aEditar && (
                                    <button
                                        type="button"
                                        disabled={remover.isPending || destaqueOcupado}
                                        onClick={() => remover.mutate(null)}
                                        className={cls('inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 hover:text-red-700 disabled:opacity-50', FOCO)}
                                    >
                                        <i className={cls('fas', remover.isPending && remover.variables === null ? 'fa-spinner fa-spin' : 'fa-trash')} aria-hidden="true" />
                                        {t('Apagar a imagem')}
                                    </button>
                                )}

                                {problemaDoDestaque && (
                                    <p role="alert" className={cls('animate-scale-in flex items-start gap-1.5 border border-red-200 bg-red-50 px-2 py-1.5 text-xs text-red-700', RAIO)}>
                                        <i className="fas fa-circle-exclamation mt-0.5" aria-hidden="true" />
                                        {problemaDoDestaque}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                </ZonaDeLargar>
            </div>

            {/* ─── Galeria ─── */}
            <div>
                <Rotulo>
                    <i className="fas fa-images mr-1.5 text-violet-500" aria-hidden="true" />
                    {t('Galeria')}
                    <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 tabular-nums" data-contas-da-galeria>
                        {guardado.galeria.length + galeria.length}/{MAXIMO_DA_GALERIA}
                    </span>
                </Rotulo>

                <ZonaDeLargar activa={lugaresLivres > 0 && aSubirNaGaleria === 0} aoLargar={(fs) => void receberNaGaleria(fs)}>
                    {(aPairar) => (
                        <div
                            className={cls(
                                'border-2 border-dashed p-3',
                                RAIO,
                                TRANSICAO,
                                aPairar ? 'scale-[1.01] border-violet-500 bg-violet-50' : 'border-slate-200 bg-slate-50/60 hover:border-violet-300',
                            )}
                        >
                            <ul className="grid grid-cols-4 gap-2 sm:grid-cols-5">
                                {guardado.galeria.map((g, i) => (
                                    <li key={g.caminho} style={{ '--i': i } as CSSProperties} className="entra group relative">
                                        <img src={g.url} alt={t('Imagem :n da galeria', { n: i + 1 })} className={cls('aspect-square w-full border border-slate-200 bg-white object-cover shadow-sm transition-transform duration-200 group-hover:scale-105', RAIO)} />
                                        {aEditar && (
                                            <button
                                                type="button"
                                                disabled={remover.isPending}
                                                onClick={() => remover.mutate(g.caminho)}
                                                aria-label={t('Apagar imagem da galeria')}
                                                title={t('Apagar imagem da galeria')}
                                                className="absolute -right-1.5 -top-1.5 grid h-6 w-6 place-items-center rounded-full bg-red-600 text-[10px] text-white shadow-md transition-all duration-200 hover:scale-110 sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100"
                                            >
                                                <i className={cls('fas', remover.isPending && remover.variables === g.caminho ? 'fa-spinner fa-spin' : 'fa-times')} aria-hidden="true" />
                                            </button>
                                        )}
                                    </li>
                                ))}

                                {previasDaGaleria.map((u, i) => (
                                    <li key={u} className="animate-scale-in group relative">
                                        <img src={u} alt={t('Imagem escolhida, ainda por enviar')} className={cls('aspect-square w-full border border-emerald-300 bg-white object-cover shadow-sm', RAIO)} />
                                        <span className="absolute inset-x-1 bottom-1 truncate rounded bg-emerald-600/90 px-1 text-center text-[9px] font-bold uppercase text-white">{t('por enviar')}</span>
                                        <button
                                            type="button"
                                            onClick={() => aoTirarDaGaleria(i)}
                                            aria-label={t('Tirar a imagem escolhida')}
                                            className="absolute -right-1.5 -top-1.5 grid h-6 w-6 place-items-center rounded-full bg-slate-700 text-[10px] text-white shadow-md transition-transform duration-200 hover:scale-110"
                                        >
                                            <i className="fas fa-times" aria-hidden="true" />
                                        </button>
                                    </li>
                                ))}

                                {Array.from({ length: aSubirNaGaleria }, (_, i) => (
                                    <li key={`a-subir-${i}`} className={cls('grid aspect-square place-items-center border border-violet-200 bg-violet-50 text-violet-600', RAIO)} role="status">
                                        <i className="fas fa-spinner fa-spin" aria-hidden="true" />
                                        <span className="sr-only">{t('A enviar…')}</span>
                                    </li>
                                ))}

                                {lugaresLivres > 0 && (
                                    <li>
                                        <label
                                            className={cls(
                                                'grid aspect-square cursor-pointer place-items-center border-2 border-dashed border-violet-300 bg-white text-violet-600 hover:-translate-y-0.5 hover:border-violet-500 hover:bg-violet-50 hover:shadow-md focus-within:ring-2 focus-within:ring-violet-400',
                                                RAIO,
                                                TRANSICAO,
                                                aSubirNaGaleria > 0 && 'pointer-events-none opacity-50',
                                            )}
                                            title={t('Juntar à galeria')}
                                        >
                                            <span className="text-center text-[10px] font-semibold leading-tight">
                                                <i className={cls('fas mb-1 block text-lg', aPairar ? 'fa-cloud-arrow-up animate-bounce' : 'fa-plus')} aria-hidden="true" />
                                                {t('Juntar')}
                                            </span>
                                            <input type="file" accept="image/*" multiple className="sr-only" aria-label={t('Juntar à galeria')} disabled={aSubirNaGaleria > 0} onChange={escolher((fs) => void receberNaGaleria(fs))} />
                                        </label>
                                    </li>
                                )}
                            </ul>

                            <p className="mt-2 text-xs text-slate-500">
                                {lugaresLivres > 0
                                    ? t('Escolha várias de uma vez ou arraste-as para aqui. Sobem uma a uma.')
                                    : t('A galeria está cheia. Apague uma imagem para juntar outra.')}
                            </p>
                        </div>
                    )}
                </ZonaDeLargar>

                {(problemaDaGaleria || remover.isError) && (
                    <p role="alert" className={cls('animate-scale-in mt-2 flex items-start gap-1.5 border border-red-200 bg-red-50 px-2 py-1.5 text-xs text-red-700', RAIO)}>
                        <i className="fas fa-circle-exclamation mt-0.5" aria-hidden="true" />
                        {problemaDaGaleria ?? mensagemDe(remover.error)}
                    </p>
                )}
            </div>
        </div>
    );
}
