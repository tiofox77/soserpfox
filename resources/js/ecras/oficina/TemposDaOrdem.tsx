import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { temposDaOrdem, type RegistoDeTempo, type TemposDaOrdem as Dados } from '@/api/oficina';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, dataHora } from '@/ui/tokens';

/**
 * O REGISTO DE TEMPOS DE UMA ORDEM (15/09/2026, OF-06).
 *
 * Em cima, os relógios a correr (a contar ao segundo) com o botão de parar;
 * a seguir, começar um relógio novo (mecânico e, se quiser, a linha de
 * serviço); as horas vendidas contra as trabalhadas, linha a linha; e a lista
 * dos períodos, que se corrigem quando alguém se esquece de parar.
 */

const horas = (h: number) => `${Math.floor(h)}h${String(Math.round((h % 1) * 60)).padStart(2, '0')}`;

function Relogio({ desde }: { desde: string }) {
    const [agora, porAgora] = useState(() => Date.now());
    useEffect(() => { const i = window.setInterval(() => porAgora(Date.now()), 1000); return () => window.clearInterval(i); }, []);
    const s = Math.max(0, Math.floor((agora - new Date(desde).getTime()) / 1000));
    const hh = Math.floor(s / 3600);
    const mm = Math.floor((s % 3600) / 60);
    const ss = s % 60;

    return <span className="font-mono text-2xl font-black tabular-nums">{hh}:{String(mm).padStart(2, '0')}:<span className="text-lg opacity-70">{String(ss).padStart(2, '0')}</span></span>;
}

export function TemposDaOrdem({ id }: { id: number }) {
    const cache = useQueryClient();
    const chave = ['oficina', 'ordens', 'tempos', id];
    const q = useQuery({ queryKey: chave, queryFn: () => temposDaOrdem.ler(id), refetchInterval: 60_000 });
    const [mecanico, porMecanico] = useState('');
    const [linha, porLinha] = useState('');
    const [aCorrigir, porACorrigir] = useState<RegistoDeTempo | null>(null);

    useEffect(() => { if (q.data && mecanico === '') porMecanico(q.data.mecanico_da_ordem ?? ''); }, [q.data, mecanico]);

    const aoResponder = (r: Dados) => {
        cache.setQueryData(chave, r);
        void cache.invalidateQueries({ queryKey: ['oficina', 'ordens', 'ficha', id] });
        void cache.invalidateQueries({ queryKey: ['oficina', 'quadro'] });
    };

    const comecar = useMutation({ mutationFn: () => temposDaOrdem.comecar(id, mecanico, linha), onSuccess: aoResponder });
    const parar = useMutation({ mutationFn: (r: RegistoDeTempo) => temposDaOrdem.parar(id, r.id), onSuccess: aoResponder });
    const apagar = useMutation({ mutationFn: (r: RegistoDeTempo) => temposDaOrdem.apagar(id, r.id), onSuccess: aoResponder });

    if (q.isPending) return <Carregando linhas={5} />;
    if (q.isError) return <AvisoDeErro erro={q.error} />;

    const d = q.data;
    const aCorrer = d.registos.filter((r) => r.a_correr);
    const podeMexer = d.pode_editar && d.aberta;

    return (
        <div className="space-y-4">
            {/* OS RELÓGIOS A CORRER */}
            {aCorrer.length > 0 && (
                <div className="grid gap-3 sm:grid-cols-2">
                    {aCorrer.map((r) => (
                        <div key={r.id} className={cls('animate-scale-in relative overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-700 p-4 text-white shadow-lg', RAIO_GRANDE)}>
                            <span className="pointer-events-none absolute -right-6 -top-6 h-24 w-24 animate-pulse rounded-full bg-white/10" aria-hidden="true" />
                            <p className="flex items-center gap-2 text-sm font-semibold text-blue-100">
                                <span className="h-2 w-2 animate-ping rounded-full bg-emerald-300" aria-hidden="true" />{r.mecanico}
                            </p>
                            <Relogio desde={r.inicio_iso} />
                            <p className="truncate text-xs text-blue-100">{r.linha ?? t('Trabalho geral na ordem')}</p>
                            {d.pode_editar && (
                                <Botao altura="pequeno" icone="fa-stop" aTrabalhar={parar.isPending && parar.variables?.id === r.id} onClick={() => parar.mutate(r)}
                                    className="absolute bottom-3 right-3 bg-white/90 text-red-600 hover:bg-white">
                                    {t('Parar')}
                                </Botao>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {/* COMEÇAR UM RELÓGIO */}
            {podeMexer && (
                <section className={cls('flex flex-wrap items-end gap-2 border border-slate-200 bg-slate-50 p-3', RAIO_GRANDE)} aria-label={t('Começar a trabalhar')}>
                    <label className="block min-w-0 flex-1 basis-40 text-sm">
                        <span className="mb-1 block font-medium text-slate-700">{t('Mecânico')}</span>
                        <select value={mecanico} onChange={(e) => porMecanico(e.target.value)} className={cls('w-full border border-slate-300 bg-white px-3 py-2 text-sm', RAIO, FOCO)}>
                            <option value="">—</option>
                            {d.mecanicos.map((m) => <option key={m.valor} value={m.valor}>{m.rotulo}</option>)}
                        </select>
                    </label>
                    <label className="block min-w-0 flex-1 basis-56 text-sm">
                        <span className="mb-1 block font-medium text-slate-700">{t('Tarefa')}</span>
                        <select value={linha} onChange={(e) => porLinha(e.target.value)} className={cls('w-full border border-slate-300 bg-white px-3 py-2 text-sm', RAIO, FOCO)}>
                            <option value="">{t('Trabalho geral na ordem')}</option>
                            {d.linhas.map((l) => <option key={l.id} value={l.id}>{l.nome}</option>)}
                        </select>
                    </label>
                    <Botao cor="bom" tom="solida" icone="fa-play" aTrabalhar={comecar.isPending} disabled={!mecanico} onClick={() => comecar.mutate()}>{t('Começar')}</Botao>
                    <AvisoDeErro erro={comecar.error} />
                </section>
            )}

            {/* VENDIDAS CONTRA TRABALHADAS */}
            <section className={cls('border border-slate-200 bg-white p-4', RAIO_GRANDE)}>
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <h3 className="text-sm font-bold text-slate-800"><i className="fas fa-scale-balanced mr-1.5 text-indigo-500" aria-hidden="true" />{t('Horas vendidas e trabalhadas')}</h3>
                    <span className="ml-auto flex flex-wrap gap-2 text-xs">
                        <span className="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700">{t('Vendidas')} {horas(d.contas.vendidas)}</span>
                        <span className="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-700">{t('Trabalhadas')} {horas(d.contas.trabalhadas)}</span>
                        {d.contas.eficiencia !== null && (
                            <span className={cls('rounded-full px-2.5 py-1 font-bold', d.contas.eficiencia >= 100 ? 'bg-emerald-50 text-emerald-700' : d.contas.eficiencia >= 80 ? 'bg-amber-50 text-amber-800' : 'bg-red-50 text-red-700')}>
                                {t('Eficiência :p%', { p: d.contas.eficiencia })}
                            </span>
                        )}
                    </span>
                </div>
                {d.linhas.length === 0 ? (
                    <p className="text-sm text-slate-500">{t('Esta ordem ainda não tem linhas de serviço aprovadas.')}</p>
                ) : (
                    <ul className="space-y-2.5">
                        {d.linhas.map((l) => {
                            const topo = Math.max(l.vendidas, l.trabalhadas, 0.01);
                            const passou = l.vendidas > 0 && l.trabalhadas > l.vendidas;
                            return (
                                <li key={l.id}>
                                    <p className="flex items-center gap-2 text-sm">
                                        <span className="min-w-0 flex-1 truncate font-medium text-slate-800">{l.nome}</span>
                                        <span className={cls('text-xs tabular-nums', passou ? 'font-bold text-red-600' : 'text-slate-500')}>{horas(l.trabalhadas)} / {horas(l.vendidas)}</span>
                                    </p>
                                    <div className="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div className={cls('h-full rounded-full transition-all duration-700', passou ? 'bg-red-500' : 'bg-emerald-500')} style={{ width: `${(l.trabalhadas / topo) * 100}%` }} />
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </section>

            {/* OS PERÍODOS */}
            {d.registos.length === 0 ? (
                <SemNada icone="fa-stopwatch" frase={t('Ainda não se registou tempo nesta ordem.')} />
            ) : (
                <div className={cls('overflow-x-auto border border-slate-200 bg-white', RAIO_GRANDE)}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                <th scope="col" className="px-3 py-2 text-left">{t('Mecânico')}</th>
                                <th scope="col" className="px-3 py-2 text-left">{t('Tarefa')}</th>
                                <th scope="col" className="px-3 py-2 text-left">{t('Começou')}</th>
                                <th scope="col" className="px-3 py-2 text-left">{t('Acabou')}</th>
                                <th scope="col" className="px-3 py-2 text-right">{t('Tempo')}</th>
                                {d.pode_editar && <th scope="col" className="px-3 py-2" />}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {d.registos.map((r, i) => (
                                <tr key={r.id} style={cascata(i)} className={cls('entra', r.a_correr && 'bg-blue-50/50')}>
                                    <td className="px-3 py-2 font-medium text-slate-800">{r.mecanico}</td>
                                    <td className="px-3 py-2 text-slate-600">{r.linha ?? t('Geral')}</td>
                                    <td className="px-3 py-2 tabular-nums text-slate-600">{dataHora(r.inicio_iso)}</td>
                                    <td className="px-3 py-2 tabular-nums text-slate-600">{r.a_correr ? <span className="inline-flex items-center gap-1 font-semibold text-blue-700"><span className="h-1.5 w-1.5 animate-ping rounded-full bg-blue-500" />{t('A correr')}</span> : dataHora(r.fim)}</td>
                                    <td className="px-3 py-2 text-right font-bold tabular-nums text-slate-900">{horas(r.minutos / 60)}</td>
                                    {d.pode_editar && (
                                        <td className="whitespace-nowrap px-3 py-2 text-right">
                                            {!r.a_correr && (
                                                <button type="button" onClick={() => porACorrigir(r)} aria-label={t('Corrigir')} title={t('Corrigir')}
                                                    className={cls('p-1.5 text-slate-400 hover:scale-110 hover:text-indigo-600', RAIO, TRANSICAO, FOCO)}><i className="fas fa-pen" aria-hidden="true" /></button>
                                            )}
                                            <button type="button" onClick={() => apagar.mutate(r)} aria-label={t('Apagar')} title={t('Apagar')}
                                                className={cls('p-1.5 text-slate-400 hover:scale-110 hover:text-red-600', RAIO, TRANSICAO, FOCO)}><i className="fas fa-trash" aria-hidden="true" /></button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {aCorrigir && <Corrigir id={id} r={aCorrigir} aoFechar={() => porACorrigir(null)} aoGravar={(r) => { aoResponder(r); porACorrigir(null); }} />}
        </div>
    );
}

function Corrigir({ id, r, aoFechar, aoGravar }: { id: number; r: RegistoDeTempo; aoFechar: () => void; aoGravar: (d: Dados) => void }) {
    const [inicio, porInicio] = useState(r.inicio);
    const [fim, porFim] = useState(r.fim ?? r.inicio);
    const gravar = useMutation({ mutationFn: () => temposDaOrdem.corrigir(id, r.id, inicio, fim), onSuccess: aoGravar });
    const caixa = cls('w-full border border-slate-300 px-3 py-2 text-sm', RAIO, FOCO);

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Corrigir tempo')} subtitulo={r.mecanico ?? undefined} icone="fa-stopwatch" largura="sm"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Guardar')}</Botao></>}>
            <div className="space-y-3">
                <AvisoDeErro erro={gravar.error} />
                <label className="block text-sm"><span className="mb-1 block font-medium text-slate-700">{t('Começou')}</span><input type="datetime-local" value={inicio} onChange={(e) => porInicio(e.target.value)} className={caixa} /></label>
                <label className="block text-sm"><span className="mb-1 block font-medium text-slate-700">{t('Acabou')}</span><input type="datetime-local" value={fim} onChange={(e) => porFim(e.target.value)} className={caixa} /></label>
                <p className="text-xs text-slate-500">{t('A correcção fica escrita no histórico da ordem.')}</p>
            </div>
        </Modal>
    );
}
