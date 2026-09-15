import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useRef, useState, type PointerEvent as EventoDoPonteiro, type MouseEvent as EventoDoRato } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { checkin, type CheckinDaOrdem, type CheckinParaGravar, type DanoDoCheckin, type RespostaDoCheckin } from '@/api/oficina';
import { t, tn } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, dataHora } from '@/ui/tokens';

/**
 * O CHECK-IN DA VIATURA — como o carro chegou à oficina (15/09/2026, OF-01).
 *
 * O papel que as boas oficinas enchem ao balcão, no ecrã: os km e o combustível,
 * os danos marcados no desenho do carro (carrega-se onde está o risco), os
 * acessórios que vêm no carro, as luzes acesas no painel, as chaves e os
 * objectos deixados — e o cliente assina com o dedo. A assinatura fica presa ao
 * que se assinou: mudar um dano depois deixa-a sem valor e o ecrã diz isso.
 */

/** A cor de cada tipo de dano, em classes que o Tailwind conhece. */
const COR_DO_DANO: Record<string, { ponto: string; suave: string }> = {
    ambar: { ponto: 'bg-amber-500 text-white', suave: 'bg-amber-50 text-amber-800 ring-amber-200' },
    laranja: { ponto: 'bg-orange-500 text-white', suave: 'bg-orange-50 text-orange-800 ring-orange-200' },
    vermelho: { ponto: 'bg-red-600 text-white', suave: 'bg-red-50 text-red-800 ring-red-200' },
    castanho: { ponto: 'bg-amber-900 text-white', suave: 'bg-amber-100 text-amber-900 ring-amber-300' },
    roxo: { ponto: 'bg-purple-600 text-white', suave: 'bg-purple-50 text-purple-800 ring-purple-200' },
};

const ICONE_DA_LUZ: Record<string, string> = {
    motor: 'fa-gear', abs: 'fa-circle-exclamation', airbag: 'fa-person-falling-burst', oleo: 'fa-oil-can',
    bateria: 'fa-car-battery', temperatura: 'fa-temperature-high', travoes: 'fa-triangle-exclamation', pneus: 'fa-life-ring',
};

const MARCAS_DO_DEPOSITO = ['E', '', '¼', '', '½', '', '¾', '', 'F'];

const doServidor = (c: CheckinDaOrdem): CheckinParaGravar => ({
    km: c.km || null,
    combustivel: c.combustivel,
    danos: c.danos.map((d) => ({ ...d, nota: d.nota ?? '' })),
    acessorios: [...c.acessorios],
    luzes: [...c.luzes],
    chaves: c.chaves,
    objectos: c.objectos ?? '',
    notas: c.notas ?? '',
});

export function CheckinDaViatura({ id, dono }: { id: number; dono?: string | null }) {
    const cache = useQueryClient();
    const chave = ['oficina', 'ordens', 'checkin', id];

    const q = useQuery({ queryKey: chave, queryFn: () => checkin.ler(id) });
    const [form, porForm] = useState<CheckinParaGravar | null>(null);
    const [tipo, porTipo] = useState('risco');
    const [escolhido, porEscolhido] = useState<number | null>(null);
    const [aAssinar, porAAssinar] = useState(false);

    // O formulário nasce do que o servidor tem, e renasce a cada gravação.
    useEffect(() => { if (q.data) porForm(doServidor(q.data.data)); }, [q.data]);

    const aoResponder = (r: RespostaDoCheckin) => {
        cache.setQueryData(chave, r);
        void cache.invalidateQueries({ queryKey: ['oficina', 'ordens', 'ficha', id] });
    };

    const gravar = useMutation({ mutationFn: (dados: CheckinParaGravar) => checkin.gravar(id, dados), onSuccess: aoResponder });
    const tirarAssinatura = useMutation({ mutationFn: () => checkin.tirarAssinatura(id), onSuccess: aoResponder });

    const original = q.data ? JSON.stringify(doServidor(q.data.data)) : '';
    const mexido = form !== null && JSON.stringify(form) !== original;

    if (q.isPending || !form) return <Carregando linhas={6} />;
    if (q.isError) return <AvisoDeErro erro={q.error} />;

    const { data: c, listas, pode_editar: podeEditar } = q.data;
    const tipoDe = (valor: string) => listas.tipos_de_dano.find((x) => x.valor === valor);
    const mudar = (novo: Partial<CheckinParaGravar>) => porForm({ ...form, ...novo });

    const marcar = (e: EventoDoRato<HTMLButtonElement>) => {
        if (!podeEditar) return;
        const caixa = e.currentTarget.getBoundingClientRect();
        const x = Math.round(((e.clientX - caixa.left) / caixa.width) * 1000) / 10;
        const y = Math.round(((e.clientY - caixa.top) / caixa.height) * 1000) / 10;
        const danos = [...form.danos, { x, y, tipo, nota: '' }];
        mudar({ danos });
        porEscolhido(danos.length - 1);
    };

    const mudarDano = (i: number, novo: Partial<DanoDoCheckin>) => mudar({ danos: form.danos.map((d, n) => (n === i ? { ...d, ...novo } : d)) });
    const tirarDano = (i: number) => { mudar({ danos: form.danos.filter((_, n) => n !== i) }); porEscolhido(null); };
    const alternar = (lista: string[], valor: string) => (lista.includes(valor) ? lista.filter((v) => v !== valor) : [...lista, valor]);

    const caixaDeTexto = cls('w-full border border-slate-300 bg-white px-3 py-2 text-sm disabled:bg-slate-50', RAIO, FOCO);

    return (
        <div className="space-y-4">
            {/* A ASSINATURA, lá em cima: é o que diz se o check-in está fechado. */}
            <EstadoDaAssinatura c={c} mexido={mexido} podeEditar={podeEditar}
                aoPedir={() => porAAssinar(true)} aoTirar={() => tirarAssinatura.mutate()} aTirar={tirarAssinatura.isPending} />

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                {/* ─── Os danos no desenho ─── */}
                <section className={cls('border border-slate-200 bg-white p-4', RAIO_GRANDE)} aria-label={t('Danos à entrada')}>
                    <h3 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-800">
                        <i className="fas fa-car-burst text-red-500" aria-hidden="true" />{t('Danos à entrada')}
                        <span className="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{form.danos.length}</span>
                    </h3>

                    {podeEditar && (
                        <div role="radiogroup" aria-label={t('Tipo de dano a marcar')} className="mb-3 flex flex-wrap gap-1.5">
                            {listas.tipos_de_dano.map((d) => {
                                const activo = tipo === d.valor;
                                return (
                                    <button key={d.valor} type="button" role="radio" aria-checked={activo} onClick={() => porTipo(d.valor)}
                                        className={cls('inline-flex items-center gap-1.5 rounded-full py-1 pl-1 pr-2.5 text-xs font-semibold ring-1 ring-inset', TRANSICAO, FOCO,
                                            activo ? 'bg-slate-800 text-white ring-slate-800 shadow-md' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50')}>
                                        <span className={cls('grid h-5 w-5 place-items-center rounded-full text-[10px] font-black', COR_DO_DANO[d.cor]?.ponto)}>{d.letra}</span>
                                        {d.rotulo}
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    <div className="mx-auto w-full max-w-[17rem]">
                        <p className="mb-1 text-center text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">{t('Frente')}</p>
                        <div className="flex items-center gap-1">
                            <span className="w-4 text-center text-[10px] font-bold uppercase text-slate-400 [writing-mode:vertical-rl] rotate-180">{t('Esquerda')}</span>
                            <div className="relative flex-1">
                                <img src="/img/oficina/carro-planta.svg" alt="" className="pointer-events-none block w-full select-none" draggable={false} />
                                <button type="button" onClick={marcar} disabled={!podeEditar}
                                    aria-label={t('Carregue no desenho para marcar um dano')}
                                    className={cls('absolute inset-0 rounded-xl', podeEditar ? 'cursor-crosshair' : 'cursor-default', FOCO)} />
                                {form.danos.map((d, i) => {
                                    const tp = tipoDe(d.tipo);
                                    return (
                                        <button key={i} type="button" onClick={() => porEscolhido(i === escolhido ? null : i)}
                                            style={{ left: `${d.x}%`, top: `${d.y}%` }}
                                            aria-label={`${i + 1}. ${tp?.rotulo ?? d.tipo}${d.nota ? ` — ${d.nota}` : ''}`}
                                            className={cls('animate-scale-in absolute grid h-6 w-6 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full text-[10px] font-black shadow-lg ring-2 ring-white', TRANSICAO, FOCO,
                                                COR_DO_DANO[tp?.cor ?? 'ambar']?.ponto, i === escolhido && 'scale-125 ring-4 ring-indigo-400')}>
                                            {tp?.letra ?? '?'}
                                        </button>
                                    );
                                })}
                            </div>
                            <span className="w-4 text-center text-[10px] font-bold uppercase text-slate-400 [writing-mode:vertical-rl]">{t('Direita')}</span>
                        </div>
                        <p className="mt-1 text-center text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">{t('Traseira')}</p>
                    </div>

                    {form.danos.length === 0 ? (
                        <p className="mt-3 text-center text-xs text-slate-500">
                            <i className="fas fa-hand-pointer mr-1 text-indigo-400" aria-hidden="true" />
                            {podeEditar ? t('Escolha o tipo e carregue no desenho onde está o dano.') : t('Sem danos marcados.')}
                        </p>
                    ) : (
                        <ol className="mt-3 space-y-1.5">
                            {form.danos.map((d, i) => {
                                const tp = tipoDe(d.tipo);
                                return (
                                    <li key={i} style={cascata(i)} onClick={() => porEscolhido(i)}
                                        className={cls('entra flex flex-wrap items-center gap-2 border p-1.5', RAIO, TRANSICAO, i === escolhido ? 'border-indigo-300 bg-indigo-50/60' : 'border-slate-100')}>
                                        <span className={cls('grid h-6 w-6 flex-none place-items-center rounded-full text-[10px] font-black', COR_DO_DANO[tp?.cor ?? 'ambar']?.ponto)}>{tp?.letra}</span>
                                        <select value={d.tipo} disabled={!podeEditar} onChange={(e) => mudarDano(i, { tipo: e.target.value })} aria-label={t('Tipo do dano :n', { n: i + 1 })}
                                            className={cls('border border-slate-200 bg-white px-2 py-1 text-xs', RAIO, FOCO)}>
                                            {listas.tipos_de_dano.map((x) => <option key={x.valor} value={x.valor}>{x.rotulo}</option>)}
                                        </select>
                                        <input value={d.nota ?? ''} disabled={!podeEditar} maxLength={200} onChange={(e) => mudarDano(i, { nota: e.target.value })}
                                            placeholder={t('Nota (ex.: porta traseira)')} aria-label={t('Nota do dano :n', { n: i + 1 })}
                                            className={cls('min-w-0 flex-1 basis-32 border border-slate-200 bg-white px-2 py-1 text-xs', RAIO, FOCO)} />
                                        {podeEditar && (
                                            <button type="button" onClick={(e) => { e.stopPropagation(); tirarDano(i); }} aria-label={t('Tirar o dano :n', { n: i + 1 })}
                                                className={cls('grid h-7 w-7 place-items-center rounded-full text-slate-400 hover:bg-red-50 hover:text-red-600', TRANSICAO, FOCO)}>
                                                <i className="fas fa-xmark" aria-hidden="true" />
                                            </button>
                                        )}
                                    </li>
                                );
                            })}
                        </ol>
                    )}
                </section>

                {/* ─── O resto do papel ─── */}
                <div className="space-y-4">
                    <section className={cls('border border-slate-200 bg-white p-4', RAIO_GRANDE)}>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="block text-sm">
                                <span className="mb-1 block font-medium text-slate-700"><i className="fas fa-gauge-high mr-1.5 text-slate-400" aria-hidden="true" />{t('Quilómetros à entrada')}</span>
                                <input type="number" min={0} inputMode="numeric" value={form.km ?? ''} disabled={!podeEditar}
                                    onChange={(e) => mudar({ km: e.target.value === '' ? null : Number(e.target.value) })} className={cls(caixaDeTexto, 'tabular-nums')} />
                            </label>
                            <label className="block text-sm">
                                <span className="mb-1 block font-medium text-slate-700"><i className="fas fa-key mr-1.5 text-slate-400" aria-hidden="true" />{t('Chaves entregues')}</span>
                                <input type="number" min={0} max={20} inputMode="numeric" value={form.chaves ?? ''} disabled={!podeEditar}
                                    onChange={(e) => mudar({ chaves: e.target.value === '' ? null : Number(e.target.value) })} className={cls(caixaDeTexto, 'tabular-nums')} />
                            </label>
                        </div>

                        {/* O DEPÓSITO — nove marcas, do vazio ao cheio. */}
                        <p className="mb-1.5 mt-4 flex items-center gap-1.5 text-sm font-medium text-slate-700">
                            <i className="fas fa-gas-pump text-slate-400" aria-hidden="true" />{t('Combustível')}
                            <span className="ml-auto text-xs text-slate-500">{form.combustivel === null ? t('Por registar') : t(':n de 8', { n: form.combustivel })}</span>
                        </p>
                        <div role="radiogroup" aria-label={t('Combustível')} className="grid grid-cols-9 gap-1">
                            {MARCAS_DO_DEPOSITO.map((marca, n) => {
                                const cheio = form.combustivel !== null && n <= form.combustivel && n > 0;
                                const cor = n <= 2 ? 'bg-red-500' : n <= 4 ? 'bg-amber-400' : 'bg-emerald-500';
                                return (
                                    <button key={n} type="button" role="radio" aria-checked={form.combustivel === n} disabled={!podeEditar}
                                        aria-label={n === 0 ? t('Vazio') : n === 8 ? t('Cheio') : t(':n de 8', { n })}
                                        onClick={() => mudar({ combustivel: form.combustivel === n ? null : n })}
                                        className={cls('group flex flex-col items-center gap-1', FOCO, RAIO)}>
                                        <span className={cls('h-7 w-full rounded-md ring-1 ring-inset transition-all duration-300', cheio ? cls(cor, 'ring-transparent shadow-sm') : 'bg-slate-100 ring-slate-200 group-hover:bg-slate-200',
                                            form.combustivel === n && 'ring-2 ring-indigo-500', n === 0 && form.combustivel === 0 && 'bg-red-100')} />
                                        <span className="h-3 text-[10px] font-bold text-slate-400">{marca}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </section>

                    <section className={cls('border border-slate-200 bg-white p-4', RAIO_GRANDE)}>
                        <h3 className="mb-2 flex items-center gap-2 text-sm font-bold text-slate-800"><i className="fas fa-triangle-exclamation text-amber-500" aria-hidden="true" />{t('Luzes acesas no painel')}</h3>
                        <div className="flex flex-wrap gap-1.5">
                            {listas.luzes.map((l) => {
                                const acesa = form.luzes.includes(l.valor);
                                return (
                                    <button key={l.valor} type="button" aria-pressed={acesa} disabled={!podeEditar} onClick={() => mudar({ luzes: alternar(form.luzes, l.valor) })}
                                        className={cls('inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', TRANSICAO, FOCO,
                                            acesa ? 'bg-amber-400 text-amber-950 ring-amber-500 shadow-md shadow-amber-400/40' : 'bg-white text-slate-500 ring-slate-200 hover:bg-slate-50')}>
                                        <i className={cls('fas', ICONE_DA_LUZ[l.valor] ?? 'fa-circle', acesa && 'animate-pulse')} aria-hidden="true" />{l.rotulo}
                                    </button>
                                );
                            })}
                        </div>

                        <h3 className="mb-2 mt-4 flex items-center gap-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-toolbox text-indigo-500" aria-hidden="true" />{t('Vem no carro')}
                            <span className="ml-auto text-xs font-normal text-slate-500">{tn(':n de :total|:n de :total', form.acessorios.length, { n: form.acessorios.length, total: listas.acessorios.length })}</span>
                        </h3>
                        <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                            {listas.acessorios.map((a) => {
                                const tem = form.acessorios.includes(a.valor);
                                return (
                                    <label key={a.valor} className={cls('flex cursor-pointer items-center gap-2 border px-2.5 py-1.5 text-xs', RAIO, TRANSICAO,
                                        tem ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 text-slate-600 hover:bg-slate-50', !podeEditar && 'cursor-default')}>
                                        <input type="checkbox" checked={tem} disabled={!podeEditar} onChange={() => mudar({ acessorios: alternar(form.acessorios, a.valor) })}
                                            className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                        {a.rotulo}
                                    </label>
                                );
                            })}
                        </div>
                    </section>

                    <section className={cls('grid gap-3 border border-slate-200 bg-white p-4 sm:grid-cols-2', RAIO_GRANDE)}>
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium text-slate-700"><i className="fas fa-suitcase mr-1.5 text-slate-400" aria-hidden="true" />{t('Objectos deixados no carro')}</span>
                            <textarea rows={3} maxLength={2000} value={form.objectos} disabled={!podeEditar} onChange={(e) => mudar({ objectos: e.target.value })}
                                placeholder={t('Ex.: óculos de sol no porta-luvas')} className={caixaDeTexto} />
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium text-slate-700"><i className="fas fa-note-sticky mr-1.5 text-slate-400" aria-hidden="true" />{t('Notas internas')}</span>
                            <textarea rows={3} maxLength={2000} value={form.notas} disabled={!podeEditar} onChange={(e) => mudar({ notas: e.target.value })}
                                placeholder={t('Não aparecem ao cliente')} className={caixaDeTexto} />
                        </label>
                    </section>
                </div>
            </div>

            <AvisoDeErro erro={gravar.error} />

            {podeEditar && (
                <div className={cls('bottom-0 z-10 flex flex-wrap items-center gap-2 sm:sticky border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur', RAIO_GRANDE)}>
                    <span className="mr-auto text-xs text-slate-500">
                        {mexido
                            ? <><i className="fas fa-circle mr-1 animate-pulse text-[8px] text-amber-500" aria-hidden="true" />{t('Alterações por gravar')}</>
                            : c.existe ? <><i className="fas fa-check mr-1 text-emerald-500" aria-hidden="true" />{t('Gravado :quando', { quando: dataHora(c.actualizado_em) })}{c.registado_por ? ` · ${c.registado_por}` : ''}</>
                                : t('Ainda sem check-in')}
                    </span>
                    <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending} disabled={!mexido && c.existe} onClick={() => gravar.mutate(form)}>
                        {t('Gravar check-in')}
                    </Botao>
                    <Botao cor="bom" icone="fa-signature" disabled={gravar.isPending} onClick={() => porAAssinar(true)}>
                        {t('Assinatura do cliente')}
                    </Botao>
                </div>
            )}

            {aAssinar && (
                <Assinar id={id} nomeInicial={c.assinado_por ?? dono ?? ''} porGravar={mexido || !c.existe ? form : null}
                    aoFechar={() => porAAssinar(false)}
                    aoAssinar={(r) => { aoResponder(r); porAAssinar(false); }} />
            )}
        </div>
    );
}

function EstadoDaAssinatura({ c, mexido, podeEditar, aoPedir, aoTirar, aTirar }: {
    c: CheckinDaOrdem; mexido: boolean; podeEditar: boolean; aoPedir: () => void; aoTirar: () => void; aTirar: boolean;
}) {
    if (!c.assinatura) {
        return (
            <div className={cls('flex flex-wrap items-center gap-3 border border-dashed border-slate-300 bg-slate-50 p-3', RAIO_GRANDE)}>
                <span className="grid h-10 w-10 place-items-center rounded-xl bg-slate-200 text-slate-500"><i className="fas fa-signature" aria-hidden="true" /></span>
                <span className="min-w-0 flex-1 text-sm">
                    <span className="block font-semibold text-slate-700">{t('Por assinar')}</span>
                    <span className="block text-xs text-slate-500">{t('Registe como o carro chegou e peça ao cliente para assinar no ecrã.')}</span>
                </span>
            </div>
        );
    }

    const valida = c.assinatura_valida && !mexido;

    return (
        <div className={cls('animate-fade-in flex flex-wrap items-center gap-3 border p-3', RAIO_GRANDE, valida ? 'border-emerald-200 bg-emerald-50' : 'border-amber-300 bg-amber-50')}>
            <img src={c.assinatura} alt={t('Assinatura de :nome', { nome: c.assinado_por ?? '' })} className="h-14 w-36 rounded-lg border border-white bg-white object-contain shadow-sm" />
            <span className="min-w-0 flex-1 text-sm">
                <span className={cls('flex items-center gap-1.5 font-semibold', valida ? 'text-emerald-800' : 'text-amber-900')}>
                    <i className={cls('fas', valida ? 'fa-circle-check' : 'fa-triangle-exclamation')} aria-hidden="true" />
                    {valida ? t('Assinado por :nome', { nome: c.assinado_por ?? '' }) : t('Alterado depois de assinado')}
                </span>
                <span className="block text-xs text-slate-600">
                    {valida ? dataHora(c.assinado_em) : t(':nome assinou a :quando, mas o check-in mudou desde então. Peça nova assinatura.', { nome: c.assinado_por ?? '', quando: dataHora(c.assinado_em) })}
                </span>
            </span>
            {podeEditar && (
                <span className="flex flex-wrap gap-2">
                    {!valida && <Botao cor="aviso" tom="solida" altura="pequeno" icone="fa-signature" onClick={aoPedir}>{t('Pedir nova assinatura')}</Botao>}
                    <Botao altura="pequeno" icone="fa-eraser" aTrabalhar={aTirar} onClick={aoTirar}>{t('Remover assinatura')}</Botao>
                </span>
            )}
        </div>
    );
}

/**
 * O QUADRO DE ASSINAR — o cliente assina com o dedo ou com o rato.
 *
 * Havendo alterações por gravar, grava-as primeiro: o cliente assina o que está
 * no ecrã, e não o que estava gravado antes.
 */
function Assinar({ id, nomeInicial, porGravar, aoFechar, aoAssinar }: {
    id: number; nomeInicial: string; porGravar: CheckinParaGravar | null;
    aoFechar: () => void; aoAssinar: (r: RespostaDoCheckin) => void;
}) {
    const tela = useRef<HTMLCanvasElement>(null);
    const aDesenhar = useRef(false);
    const ultimo = useRef<{ x: number; y: number } | null>(null);
    const [riscado, porRiscado] = useState(false);
    const [nome, porNome] = useState(nomeInicial);

    const preparar = useMemo(() => () => {
        const c = tela.current;
        if (!c) return;
        const r = c.getBoundingClientRect();
        const escala = window.devicePixelRatio || 1;
        c.width = Math.round(r.width * escala);
        c.height = Math.round(r.height * escala);
        const g = c.getContext('2d');
        if (!g) return;
        g.scale(escala, escala);
        g.lineCap = 'round';
        g.lineJoin = 'round';
        g.lineWidth = 2.4;
        g.strokeStyle = '#0f172a';
    }, []);

    useEffect(() => {
        // O <dialog> anima a entrada: mede-se o quadro depois de ele ter o tamanho final.
        const tempo = window.setTimeout(preparar, 260);
        return () => window.clearTimeout(tempo);
    }, [preparar]);

    const ponto = (e: EventoDoPonteiro<HTMLCanvasElement>) => {
        const r = e.currentTarget.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    };

    const comecar = (e: EventoDoPonteiro<HTMLCanvasElement>) => {
        e.currentTarget.setPointerCapture(e.pointerId);
        aDesenhar.current = true;
        ultimo.current = ponto(e);
    };
    const mover = (e: EventoDoPonteiro<HTMLCanvasElement>) => {
        if (!aDesenhar.current || !ultimo.current) return;
        const g = e.currentTarget.getContext('2d');
        const p = ponto(e);
        if (!g) return;
        g.beginPath();
        g.moveTo(ultimo.current.x, ultimo.current.y);
        g.lineTo(p.x, p.y);
        g.stroke();
        ultimo.current = p;
        if (!riscado) porRiscado(true);
    };
    const parar = () => { aDesenhar.current = false; ultimo.current = null; };

    const limpar = () => {
        const c = tela.current;
        c?.getContext('2d')?.clearRect(0, 0, c.width, c.height);
        porRiscado(false);
    };

    const assinar = useMutation({
        mutationFn: async () => {
            if (porGravar) await checkin.gravar(id, porGravar);
            // Fundo branco: um PNG transparente aparece preto em alguns visualizadores de PDF.
            const origem = tela.current as HTMLCanvasElement;
            const copia = document.createElement('canvas');
            copia.width = origem.width;
            copia.height = origem.height;
            const g = copia.getContext('2d') as CanvasRenderingContext2D;
            g.fillStyle = '#ffffff';
            g.fillRect(0, 0, copia.width, copia.height);
            g.drawImage(origem, 0, 0);

            return checkin.assinar(id, copia.toDataURL('image/png'), nome);
        },
        onSuccess: aoAssinar,
    });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Assinatura do cliente')} subtitulo={t('Confirma o estado da viatura à entrada')} icone="fa-signature" cor="bom" largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao icone="fa-eraser" onClick={limpar} disabled={!riscado || assinar.isPending}>{t('Limpar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-pen-nib" aTrabalhar={assinar.isPending} disabled={!riscado || nome.trim() === ''} onClick={() => assinar.mutate()}>
                        {t('Confirmar assinatura')}
                    </Botao>
                </>
            }>
            <div className="space-y-3">
                {porGravar && (
                    <p className={cls('border border-indigo-200 bg-indigo-50 p-2.5 text-xs text-indigo-900', RAIO)}>
                        <i className="fas fa-circle-info mr-1.5" aria-hidden="true" />{t('As alterações ao check-in são gravadas antes da assinatura.')}
                    </p>
                )}
                <AvisoDeErro erro={assinar.error} />
                {assinar.error instanceof ErroDaApi && Object.values(assinar.error.erros ?? {})[0]?.[0] && (
                    <p className="text-sm text-red-700">{Object.values(assinar.error.erros)[0]?.[0]}</p>
                )}
                <label className="block text-sm">
                    <span className="mb-1 block font-medium text-slate-700">{t('Nome de quem assina')}</span>
                    <input value={nome} maxLength={150} onChange={(e) => porNome(e.target.value)} className={cls('w-full border border-slate-300 px-3 py-2 text-sm', RAIO, FOCO)} />
                </label>
                <div className="relative">
                    <canvas ref={tela} onPointerDown={comecar} onPointerMove={mover} onPointerUp={parar} onPointerLeave={parar} onPointerCancel={parar}
                        aria-label={t('Quadro para assinar')}
                        className={cls('block h-48 w-full touch-none border-2 border-dashed bg-white', RAIO_GRANDE, riscado ? 'border-emerald-300' : 'border-slate-300')} />
                    {!riscado && (
                        <span className="pointer-events-none absolute inset-0 grid place-items-center text-sm text-slate-400">
                            <span><i className="fas fa-pen-nib mr-1.5" aria-hidden="true" />{t('Assine aqui com o dedo ou com o rato')}</span>
                        </span>
                    )}
                    <span className="pointer-events-none absolute inset-x-6 bottom-9 border-b border-slate-300" aria-hidden="true" />
                </div>
                <p className="text-xs text-slate-500">{t('Ao assinar, o cliente confirma os km, o combustível, os danos, os acessórios e os objectos registados neste check-in.')}</p>
            </div>
        </Modal>
    );
}
