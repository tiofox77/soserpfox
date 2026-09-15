import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useRef, useState } from 'react';

import { inspeccoes, type EstadoDoPonto, type InspeccaoDaOrdem as Inspeccao, type PontoDaInspeccao, type RespostaDasInspeccoes } from '@/api/oficina';
import { avisar } from '@/casca/avisos';
import { t, tn } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { ImagemRecusada, prepararImagem } from '@/ui/prepararImagem';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, dataHora } from '@/ui/tokens';

/**
 * A INSPECÇÃO DIGITAL COM SEMÁFORO (15/09/2026, OF-02).
 *
 * Os pontos do modelo agrupados por secção; cada um recebe OK, Atenção,
 * Urgente ou Não se aplica com um toque, uma nota e uma fotografia tirada ali
 * mesmo. No fim conclui-se, e os pontos a amarelo e vermelho passam às
 * recomendações da ordem. A inspecção concluída aparece ao cliente no portal.
 */

/** O aspecto de cada estado do semáforo — partilhado com o portal. */
export const SEMAFORO: Record<EstadoDoPonto, { icone: string; activo: string; suave: string; ponto: string }> = {
    ok: { icone: 'fa-check', activo: 'bg-emerald-500 text-white ring-emerald-500 shadow-emerald-500/40', suave: 'bg-emerald-50 text-emerald-800 ring-emerald-200', ponto: 'bg-emerald-500' },
    atencao: { icone: 'fa-exclamation', activo: 'bg-amber-400 text-amber-950 ring-amber-400 shadow-amber-400/40', suave: 'bg-amber-50 text-amber-900 ring-amber-200', ponto: 'bg-amber-400' },
    urgente: { icone: 'fa-xmark', activo: 'bg-red-600 text-white ring-red-600 shadow-red-600/40', suave: 'bg-red-50 text-red-800 ring-red-200', ponto: 'bg-red-600' },
    na: { icone: 'fa-minus', activo: 'bg-slate-500 text-white ring-slate-500 shadow-slate-500/30', suave: 'bg-slate-100 text-slate-600 ring-slate-200', ponto: 'bg-slate-400' },
};

type Local = Array<{ estado: EstadoDoPonto | null; nota: string }>;

const local = (i: Inspeccao): Local => i.pontos.map((p) => ({ estado: p.estado, nota: p.nota ?? '' }));

export function InspeccaoDaOrdem({ id }: { id: number }) {
    const cache = useQueryClient();
    const chave = ['oficina', 'ordens', 'inspeccoes', id];
    const q = useQuery({ queryKey: chave, queryFn: () => inspeccoes.ler(id) });

    const [activa, porActiva] = useState<number | null>(null);
    const [modelo, porModelo] = useState('');
    const [aComecar, porAComecar] = useState(false);

    const aoResponder = (r: RespostaDasInspeccoes) => {
        cache.setQueryData(chave, r);
        void cache.invalidateQueries({ queryKey: ['oficina', 'ordens', 'ficha', id] });
    };

    const comecar = useMutation({
        mutationFn: () => inspeccoes.comecar(id, modelo || String(q.data?.modelos[0]?.valor ?? '')),
        onSuccess: (r) => { aoResponder(r); porActiva(r.criada ?? null); porAComecar(false); },
    });

    if (q.isPending) return <Carregando linhas={6} />;
    if (q.isError) return <AvisoDeErro erro={q.error} />;

    const d = q.data;
    const escolhida = d.inspeccoes.find((i) => i.id === activa) ?? d.inspeccoes[0] ?? null;

    const escolherModelo = (
        <div className={cls('flex flex-wrap items-end gap-2', RAIO)}>
            <label className="block min-w-0 flex-1 basis-56 text-sm">
                <span className="mb-1 block font-medium text-slate-700">{t('Modelo de inspecção')}</span>
                <select value={modelo || String(d.modelos[0]?.valor ?? '')} onChange={(e) => porModelo(e.target.value)} className={cls('w-full border border-slate-300 bg-white px-3 py-2 text-sm', RAIO, FOCO)}>
                    {d.modelos.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo} · {tn(':n ponto|:n pontos', m.pontos, { n: m.pontos })}</option>)}
                </select>
            </label>
            <Botao cor="bom" tom="solida" icone="fa-play" aTrabalhar={comecar.isPending} disabled={d.modelos.length === 0} onClick={() => comecar.mutate()}>{t('Começar inspecção')}</Botao>
            <a href="/workshop/inspection-templates" className={cls('inline-flex h-10 items-center gap-1.5 px-2 text-sm font-semibold text-indigo-700 hover:underline', FOCO, RAIO)}>
                <i className="fas fa-list-check" aria-hidden="true" />{t('Gerir modelos')}
            </a>
        </div>
    );

    if (!escolhida) {
        return (
            <div className="space-y-4">
                <SemNada icone="fa-list-check" titulo={t('Sem inspecção')} frase={t('Confira o carro ponto a ponto — travões, pneus, suspensão, motor — com verde, amarelo ou vermelho, fotografia e nota. O cliente vê o resultado no portal.')} />
                {d.pode_editar && <div className={cls('border border-slate-200 bg-slate-50 p-4', RAIO_GRANDE)}>{escolherModelo}<AvisoDeErro erro={comecar.error} /></div>}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {(d.inspeccoes.length > 1 || d.pode_editar) && (
                <div className="flex flex-wrap items-center gap-2">
                    {d.inspeccoes.map((i) => (
                        <button key={i.id} type="button" onClick={() => porActiva(i.id)} aria-pressed={i.id === escolhida.id}
                            className={cls('inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset', TRANSICAO, FOCO,
                                i.id === escolhida.id ? 'bg-slate-800 text-white ring-slate-800' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50')}>
                            <i className={cls('fas', i.concluida_em ? 'fa-circle-check text-emerald-400' : 'fa-hourglass-half text-amber-400')} aria-hidden="true" />
                            {i.nome}
                            <span className="opacity-70">{dataHora(i.em)}</span>
                        </button>
                    ))}
                    {d.pode_editar && (
                        <button type="button" onClick={() => porAComecar(true)}
                            className={cls('inline-flex items-center gap-1.5 rounded-full border border-dashed border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50', TRANSICAO, FOCO)}>
                            <i className="fas fa-plus" aria-hidden="true" />{t('Nova inspecção')}
                        </button>
                    )}
                </div>
            )}

            <Folha key={escolhida.id} id={id} i={escolhida} d={d} aoResponder={aoResponder} />

            <Modal aberto={aComecar} aoFechar={() => porAComecar(false)} titulo={t('Nova inspecção')} icone="fa-list-check" cor="bom" largura="md">
                {escolherModelo}
                <AvisoDeErro erro={comecar.error} />
            </Modal>
        </div>
    );
}

function Folha({ id, i, d, aoResponder }: { id: number; i: Inspeccao; d: RespostaDasInspeccoes; aoResponder: (r: RespostaDasInspeccoes) => void }) {
    const [res, porRes] = useState<Local>(() => local(i));
    const [filtro, porFiltro] = useState<'todos' | 'por_ver' | 'problemas'>('todos');
    const [notaAberta, porNotaAberta] = useState<Set<number>>(new Set());
    const [aApagar, porAApagar] = useState(false);
    const [fotoAVer, porFotoAVer] = useState<PontoDaInspeccao | null>(null);
    const [aSubir, porASubir] = useState<number | null>(null);
    const ficheiro = useRef<HTMLInputElement>(null);
    const pontoDaFoto = useRef<number | null>(null);

    const original = JSON.stringify(local(i));
    // Chegou uma versão nova do servidor (gravar, concluir): começa-se dela.
    useEffect(() => { porRes(JSON.parse(original) as Local); }, [original]);

    const mexido = JSON.stringify(res) !== original;
    const concluida = Boolean(i.concluida_em);
    const podeMexer = d.pode_editar && !concluida;

    const paraEnviar = () => res.map((r) => ({ estado: r.estado, nota: r.nota.trim() || null }));
    const gravar = useMutation({
        mutationFn: (extra: { concluir?: boolean; reabrir?: boolean }) => inspeccoes.gravar(id, i.id, paraEnviar(), extra),
        onSuccess: aoResponder,
    });
    const recomendar = useMutation({ mutationFn: () => inspeccoes.recomendar(id, i.id), onSuccess: aoResponder });
    const apagar = useMutation({ mutationFn: () => inspeccoes.apagar(id, i.id), onSuccess: (r) => { porAApagar(false); aoResponder(r); } });
    const tirarFoto = useMutation({ mutationFn: (n: number) => inspeccoes.tirarFoto(id, i.id, n), onSuccess: aoResponder });

    const contas = useMemo(() => {
        const c = { ok: 0, atencao: 0, urgente: 0, na: 0, por_ver: 0 };
        res.forEach((r) => { c[r.estado ?? 'por_ver']++; });
        return c;
    }, [res]);
    const vistos = res.length - contas.por_ver;

    const seccoes = useMemo(() => {
        const mapa = new Map<string, number[]>();
        i.pontos.forEach((p, n) => mapa.set(p.seccao, [...(mapa.get(p.seccao) ?? []), n]));
        return [...mapa.entries()];
    }, [i.pontos]);

    const mostrar = (n: number) => filtro === 'todos' || (filtro === 'por_ver' ? res[n]?.estado === null : ['atencao', 'urgente'].includes(res[n]?.estado ?? ''));

    const marcar = (n: number, estado: EstadoDoPonto) => {
        porRes(res.map((r, k) => (k === n ? { ...r, estado: r.estado === estado ? null : estado } : r)));
        if (estado === 'atencao' || estado === 'urgente') porNotaAberta(new Set(notaAberta).add(n));
    };

    const subirFoto = async (lista: FileList | null) => {
        const n = pontoDaFoto.current;
        const f = lista?.[0];
        if (n === null || !f) return;
        porASubir(n);
        try {
            const pronta = await prepararImagem(f);
            const r = await inspeccoes.foto(id, i.id, n, pronta);
            // Só a fotografia vem do servidor: os semáforos por gravar ficam como estão.
            const nova = r.inspeccoes.find((x) => x.id === i.id);
            const guardados = res;
            aoResponder(r);
            if (nova) window.setTimeout(() => porRes(guardados), 0);
            avisar(r.message ?? t('Fotografia do ponto juntada.'), 'ok');
        } catch (e) {
            avisar(e instanceof ImagemRecusada || e instanceof Error ? e.message : t('Não foi possível enviar a fotografia.'), 'erro');
        } finally {
            porASubir(null);
        }
    };

    const problemas = contas.atencao + contas.urgente;

    return (
        <div className="space-y-4">
            {/* O RESUMO — o progresso e o semáforo em números. */}
            <section className={cls('overflow-hidden border border-slate-200 bg-white', RAIO_GRANDE)}>
                <div className="flex flex-wrap items-center gap-3 p-4">
                    <span className={cls('grid h-11 w-11 place-items-center rounded-xl text-lg text-white shadow', concluida ? 'bg-gradient-to-br from-emerald-500 to-teal-600' : 'bg-gradient-to-br from-amber-400 to-orange-500')}>
                        <i className={cls('fas', concluida ? 'fa-clipboard-check' : 'fa-list-check')} aria-hidden="true" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="font-bold text-slate-900">{i.nome}</p>
                        <p className="text-xs text-slate-500">
                            {concluida ? t('Concluída a :quando', { quando: dataHora(i.concluida_em) }) : t('Em curso')}
                            {i.por && ` · ${i.por}`}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-1.5">
                        {(['ok', 'atencao', 'urgente', 'na'] as const).map((e) => (
                            <span key={e} className={cls('inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset tabular-nums', SEMAFORO[e].suave)}>
                                <span className={cls('h-2 w-2 rounded-full', SEMAFORO[e].ponto)} aria-hidden="true" />
                                {d.estados.find((x) => x.valor === e)?.rotulo} {contas[e]}
                            </span>
                        ))}
                    </div>
                </div>
                <div className="flex h-2 bg-slate-100" role="img" aria-label={t(':vistos de :total pontos vistos', { vistos, total: res.length })}>
                    {(['ok', 'atencao', 'urgente', 'na'] as const).map((e) => (
                        <span key={e} className={cls('h-full transition-all duration-500', SEMAFORO[e].ponto)} style={{ width: `${(contas[e] / Math.max(1, res.length)) * 100}%` }} />
                    ))}
                </div>
            </section>

            <div className="flex flex-wrap items-center gap-2">
                {([['todos', t('Todos'), res.length], ['por_ver', t('Por ver'), contas.por_ver], ['problemas', t('Com problemas'), problemas]] as const).map(([v, nome, n]) => (
                    <button key={v} type="button" aria-pressed={filtro === v} onClick={() => porFiltro(v)}
                        className={cls('rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset', TRANSICAO, FOCO, filtro === v ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50')}>
                        {nome} <b className="tabular-nums">{n}</b>
                    </button>
                ))}
                {podeMexer && contas.por_ver > 0 && (
                    <button type="button" onClick={() => porRes(res.map((r) => (r.estado === null ? { ...r, estado: 'ok' } : r)))}
                        className={cls('ml-auto inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200 hover:bg-emerald-50', TRANSICAO, FOCO)}>
                        <i className="fas fa-check-double" aria-hidden="true" />{tn('Marcar :n por ver como OK|Marcar os :n por ver como OK', contas.por_ver, { n: contas.por_ver })}
                    </button>
                )}
            </div>

            <input ref={ficheiro} type="file" accept="image/*" capture="environment" className="hidden" onChange={(e) => { void subirFoto(e.target.files); e.target.value = ''; }} />

            {seccoes.map(([seccao, indices]) => {
                const aVer = indices.filter(mostrar);
                if (aVer.length === 0) return null;
                const vistosDaSeccao = indices.filter((n) => res[n]?.estado !== null).length;

                return (
                    <section key={seccao} className={cls('overflow-hidden border border-slate-200 bg-white', RAIO_GRANDE)}>
                        <header className="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-4 py-2">
                            <h3 className="text-sm font-bold text-slate-800">{seccao}</h3>
                            <span className="ml-auto text-xs tabular-nums text-slate-500">{vistosDaSeccao}/{indices.length}</span>
                        </header>
                        <ul className="divide-y divide-slate-100">
                            {aVer.map((n, k) => {
                                const p = i.pontos[n] as PontoDaInspeccao;
                                const r = res[n] ?? { estado: null, nota: '' };
                                const comNota = notaAberta.has(n) || r.nota !== '';

                                return (
                                    <li key={n} style={cascata(k)} className={cls('entra px-4 py-2.5', r.estado === 'urgente' && 'bg-red-50/40', r.estado === 'atencao' && 'bg-amber-50/40')}>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className={cls('h-2.5 w-2.5 flex-none rounded-full ring-2 ring-white', r.estado ? SEMAFORO[r.estado].ponto : 'bg-slate-200')} aria-hidden="true" />
                                            <span className="min-w-0 flex-1 basis-40 text-sm font-medium text-slate-800">{p.ponto}</span>

                                            <span role="radiogroup" aria-label={p.ponto} className="flex gap-1">
                                                {(['ok', 'atencao', 'urgente', 'na'] as const).map((e) => {
                                                    const activo = r.estado === e;
                                                    return (
                                                        <button key={e} type="button" role="radio" aria-checked={activo} disabled={!podeMexer}
                                                            aria-label={d.estados.find((x) => x.valor === e)?.rotulo} title={d.estados.find((x) => x.valor === e)?.rotulo}
                                                            onClick={() => marcar(n, e)}
                                                            className={cls('grid h-9 w-9 place-items-center rounded-full text-sm ring-1 ring-inset', TRANSICAO, FOCO,
                                                                activo ? cls(SEMAFORO[e].activo, 'scale-110 shadow-lg') : 'bg-white text-slate-400 ring-slate-200 hover:scale-105 hover:bg-slate-50',
                                                                !podeMexer && !activo && 'opacity-40')}>
                                                            <i className={cls('fas', SEMAFORO[e].icone)} aria-hidden="true" />
                                                        </button>
                                                    );
                                                })}
                                            </span>

                                            <span className="flex gap-1">
                                                {p.foto ? (
                                                    <button type="button" onClick={() => porFotoAVer(p)} aria-label={t('Ver a fotografia de :ponto', { ponto: p.ponto })}
                                                        className={cls('h-9 w-9 overflow-hidden rounded-lg ring-2 ring-indigo-200 hover:ring-indigo-400', TRANSICAO, FOCO)}>
                                                        <img src={p.foto} alt="" className="h-full w-full object-cover" />
                                                    </button>
                                                ) : d.pode_editar && (
                                                    <button type="button" disabled={aSubir !== null} onClick={() => { pontoDaFoto.current = n; ficheiro.current?.click(); }}
                                                        aria-label={t('Fotografar :ponto', { ponto: p.ponto })} title={t('Fotografia')}
                                                        className={cls('grid h-9 w-9 place-items-center rounded-lg text-slate-400 ring-1 ring-inset ring-slate-200 hover:bg-indigo-50 hover:text-indigo-600', TRANSICAO, FOCO)}>
                                                        <i className={cls('fas', aSubir === n ? 'fa-spinner fa-spin' : 'fa-camera')} aria-hidden="true" />
                                                    </button>
                                                )}
                                                {podeMexer && !comNota && (
                                                    <button type="button" onClick={() => porNotaAberta(new Set(notaAberta).add(n))} aria-label={t('Nota para :ponto', { ponto: p.ponto })} title={t('Nota')}
                                                        className={cls('grid h-9 w-9 place-items-center rounded-lg text-slate-400 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 hover:text-slate-700', TRANSICAO, FOCO)}>
                                                        <i className="fas fa-comment-dots" aria-hidden="true" />
                                                    </button>
                                                )}
                                            </span>
                                        </div>
                                        {comNota && (
                                            <input value={r.nota} maxLength={500} disabled={!podeMexer} autoFocus={notaAberta.has(n) && r.nota === ''}
                                                onChange={(e) => porRes(res.map((x, j) => (j === n ? { ...x, nota: e.target.value } : x)))}
                                                placeholder={t('O que se viu (ex.: pastilhas a 2 mm)')} aria-label={t('Nota para :ponto', { ponto: p.ponto })}
                                                className={cls('animate-fade-in mt-2 w-full border border-slate-200 bg-white px-3 py-1.5 text-sm disabled:bg-slate-50', RAIO, FOCO)} />
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                );
            })}

            <AvisoDeErro erro={gravar.error ?? recomendar.error} />

            {d.pode_editar && (
                <div className={cls('bottom-0 z-10 flex flex-wrap items-center gap-2 sm:sticky border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur', RAIO_GRANDE)}>
                    <span className="mr-auto text-xs text-slate-500">
                        {mexido ? <><i className="fas fa-circle mr-1 animate-pulse text-[8px] text-amber-500" aria-hidden="true" />{t('Alterações por gravar')}</>
                            : t(':vistos de :total pontos vistos', { vistos, total: res.length })}
                    </span>
                    <Botao cor="perigo" altura="pequeno" icone="fa-trash" onClick={() => porAApagar(true)}>{t('Apagar')}</Botao>
                    {concluida ? (
                        <>
                            {problemas > 0 && (
                                <Botao cor="aviso" tom="solida" icone="fa-lightbulb" aTrabalhar={recomendar.isPending} onClick={() => recomendar.mutate()}>
                                    {tn('Passar :n ponto às recomendações|Passar :n pontos às recomendações', problemas, { n: problemas })}
                                </Botao>
                            )}
                            <Botao icone="fa-lock-open" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate({ reabrir: true })}>{t('Reabrir')}</Botao>
                        </>
                    ) : (
                        <>
                            <Botao cor="primaria" icone="fa-floppy-disk" aTrabalhar={gravar.isPending && !gravar.variables?.concluir} disabled={!mexido} onClick={() => gravar.mutate({})}>{t('Gravar')}</Botao>
                            <Botao cor="bom" tom="solida" icone="fa-clipboard-check" aTrabalhar={gravar.isPending && Boolean(gravar.variables?.concluir)} disabled={contas.por_ver > 0}
                                title={contas.por_ver > 0 ? tn('Falta ver :n ponto.|Faltam ver :n pontos.', contas.por_ver, { n: contas.por_ver }) : undefined}
                                onClick={() => gravar.mutate({ concluir: true })}>
                                {t('Concluir inspecção')}
                            </Botao>
                        </>
                    )}
                </div>
            )}

            <Modal aberto={aApagar} aoFechar={() => porAApagar(false)} titulo={t('Apagar esta inspecção?')} icone="fa-trash" cor="perigo" largura="sm"
                rodape={<><Botao onClick={() => porAApagar(false)}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => apagar.mutate()}>{t('Apagar')}</Botao></>}>
                <p className="text-sm text-slate-600">{t('Os semáforos, as notas e as fotografias de «:nome» são apagados. Não há volta.', { nome: i.nome })}</p>
            </Modal>

            <Modal aberto={fotoAVer !== null} aoFechar={() => porFotoAVer(null)} titulo={fotoAVer?.ponto ?? ''} subtitulo={fotoAVer?.seccao} icone="fa-image" cor="neutra" largura="lg"
                rodape={
                    <>
                        {fotoAVer && d.pode_editar && (
                            <Botao cor="perigo" icone="fa-trash" aTrabalhar={tirarFoto.isPending}
                                onClick={() => { const n = i.pontos.indexOf(fotoAVer); porFotoAVer(null); tirarFoto.mutate(n); }}>
                                {t('Remover fotografia')}
                            </Botao>
                        )}
                        <Botao onClick={() => porFotoAVer(null)}>{t('Fechar')}</Botao>
                    </>
                }>
                {fotoAVer?.foto && <img src={fotoAVer.foto} alt={fotoAVer.ponto} className="mx-auto max-h-[65vh] rounded-xl object-contain" />}
                {fotoAVer?.nota && <p className="mt-3 text-sm text-slate-700">{fotoAVer.nota}</p>}
            </Modal>
        </div>
    );
}
