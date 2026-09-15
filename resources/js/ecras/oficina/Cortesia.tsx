import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { cortesia, type EmprestimoDeCortesia, type QuadroDeCortesia, type ViaturaDeCortesia } from '@/api/oficina';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, data, dataHora, haQuanto } from '@/ui/tokens';

import { ChapaDaMatricula } from './ChapaDaMatricula';

/**
 * AS VIATURAS DE CORTESIA (15/09/2026, OF-17).
 *
 * O quadro das viaturas que a oficina empresta: livre, emprestada (a quem, até
 * quando, ligada a que ordem), atrasada, em manutenção. Emprestar regista os km
 * e o combustível à saída; receber, os da entrada e os danos.
 */

const MARCAS = ['E', '', '¼', '', '½', '', '¾', '', 'F'];

const TOM: Record<ViaturaDeCortesia['estado'], { faixa: string; icone: string; chip: string }> = {
    disponivel: { faixa: 'from-emerald-500 to-teal-600', icone: 'fa-circle-check', chip: 'bg-emerald-100 text-emerald-700' },
    emprestada: { faixa: 'from-amber-500 to-orange-500', icone: 'fa-key', chip: 'bg-amber-100 text-amber-800' },
    manutencao: { faixa: 'from-slate-500 to-slate-700', icone: 'fa-screwdriver-wrench', chip: 'bg-slate-200 text-slate-700' },
    inactiva: { faixa: 'from-slate-300 to-slate-400', icone: 'fa-ban', chip: 'bg-slate-100 text-slate-500' },
};

const milhares = (n: number) => n.toLocaleString('pt-PT');

function Deposito({ valor, aoMudar, rotulo }: { valor: number | null; aoMudar?: (n: number | null) => void; rotulo: string }) {
    return (
        <div role="radiogroup" aria-label={rotulo} className="grid grid-cols-9 gap-1">
            {MARCAS.map((marca, n) => {
                const cheio = valor !== null && n <= valor && n > 0;
                const cor = n <= 2 ? 'bg-red-500' : n <= 4 ? 'bg-amber-400' : 'bg-emerald-500';
                return (
                    <button key={n} type="button" role="radio" aria-checked={valor === n} disabled={!aoMudar} aria-label={t(':n de 8', { n })}
                        onClick={() => aoMudar?.(valor === n ? null : n)} className={cls('group flex flex-col items-center gap-1', FOCO, RAIO)}>
                        <span className={cls('h-6 w-full rounded-md ring-1 ring-inset transition-all duration-300', cheio ? cls(cor, 'ring-transparent') : 'bg-slate-100 ring-slate-200', valor === n && 'ring-2 ring-indigo-500')} />
                        <span className="h-3 text-[10px] font-bold text-slate-400">{marca}</span>
                    </button>
                );
            })}
        </div>
    );
}

export default function Cortesia() {
    const cache = useQueryClient();
    const q = useQuery({ queryKey: ['oficina', 'cortesia'], queryFn: cortesia.ler, refetchInterval: 120_000 });
    const [aEmprestar, porAEmprestar] = useState<ViaturaDeCortesia | null>(null);
    const [aReceber, porAReceber] = useState<EmprestimoDeCortesia | null>(null);

    if (q.isPending) return <Carregando linhas={8} />;
    if (q.isError) return <AvisoDeErro erro={q.error} />;

    const d = q.data;
    const refazer = () => void cache.invalidateQueries({ queryKey: ['oficina'] });

    return (
        <div className="space-y-4">
            <Faixa titulo={t('Viaturas de Cortesia')} subtitulo={t('Os carros que a oficina empresta enquanto o do cliente está a ser arranjado')} icone="fa-car-side" cor="teal"
                accoes={d.pode_gerir && (
                    <a href="/workshop/courtesy-fleet" className={cls(ACCAO_DA_FAIXA, 'group')}>
                        <i className="fas fa-gear transition-transform duration-500 group-hover:rotate-180" aria-hidden="true" />{t('Gerir viaturas')}
                    </a>
                )} />

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <CartaoNumero aspecto="claro" tom="teal" icone="fa-car-side" rotulo={t('Viaturas')} valor={d.contas.total} />
                <CartaoNumero aspecto="claro" tom="verde" icone="fa-circle-check" rotulo={t('Disponíveis')} valor={d.contas.disponiveis} />
                <CartaoNumero aspecto="claro" tom="ambar" icone="fa-key" rotulo={t('Emprestadas')} valor={d.contas.emprestadas} />
                <CartaoNumero aspecto="claro" tom={d.contas.atrasadas ? 'vermelho' : 'cinza'} icone="fa-clock" rotulo={t('Devolução atrasada')} valor={d.contas.atrasadas} />
            </div>

            {d.data.length === 0 ? (
                <SemNada icone="fa-car-side" titulo={t('Ainda sem viaturas de cortesia')} frase={t('Registe as viaturas que a oficina empresta aos clientes.')}
                    accao={d.pode_gerir ? <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => window.location.assign('/workshop/courtesy-fleet')}>{t('Nova Viatura de Cortesia')}</Botao> : undefined} />
            ) : (
                <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {d.data.map((v, i) => <CartaoDaViatura key={v.id} v={v} i={i} d={d} emprestar={() => porAEmprestar(v)} receber={() => v.emprestimo && porAReceber(v.emprestimo)} />)}
                </ul>
            )}

            {d.recentes.length > 0 && (
                <section className={cls('border border-slate-200 bg-white p-4 shadow-sm', RAIO_GRANDE)}>
                    <h2 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-800"><i className="fas fa-clock-rotate-left text-teal-500" aria-hidden="true" />{t('Últimos empréstimos')}</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <th scope="col" className="px-2 py-2">{t('Viatura')}</th>
                                    <th scope="col" className="px-2 py-2">{t('Condutor')}</th>
                                    <th scope="col" className="px-2 py-2">{t('Ordem')}</th>
                                    <th scope="col" className="px-2 py-2">{t('Saída')}</th>
                                    <th scope="col" className="px-2 py-2">{t('Devolução')}</th>
                                    <th scope="col" className="px-2 py-2 text-right">{t('Km')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.recentes.map((l) => (
                                    <tr key={l.id} className="hover:bg-slate-50">
                                        <td className="px-2 py-2">{l.matricula && <ChapaDaMatricula matricula={l.matricula} tamanho="pequeno" />}</td>
                                        <td className="px-2 py-2">{l.condutor}{l.danos && <span className="block text-[11px] text-red-600"><i className="fas fa-triangle-exclamation mr-1" aria-hidden="true" />{l.danos}</span>}</td>
                                        <td className="px-2 py-2 font-mono text-xs">{l.ordem ?? '—'}</td>
                                        <td className="px-2 py-2 text-xs tabular-nums">{dataHora(l.saida)}</td>
                                        <td className="px-2 py-2 text-xs tabular-nums">
                                            {l.devolvida ? dataHora(l.devolvida) : <span className={cls('rounded-full px-2 py-0.5 font-bold', l.atrasado ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800')}>{l.atrasado ? t('Atrasada') : t('Por devolver')}</span>}
                                        </td>
                                        <td className="px-2 py-2 text-right text-xs tabular-nums">{l.km_entrada !== null ? `+${milhares(l.km_entrada - l.km_saida)}` : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {aEmprestar && <Emprestar v={aEmprestar} d={d} aoFechar={() => porAEmprestar(null)} aoGravar={() => { porAEmprestar(null); refazer(); }} />}
            {aReceber && <Receber l={aReceber} aoFechar={() => porAReceber(null)} aoGravar={() => { porAReceber(null); refazer(); }} />}
        </div>
    );
}

function CartaoDaViatura({ v, i, d, emprestar, receber }: { v: ViaturaDeCortesia; i: number; d: QuadroDeCortesia; emprestar: () => void; receber: () => void }) {
    const tom = TOM[v.estado];
    const l = v.emprestimo;

    return (
        <li style={cascata(i)} className={cls('entra group card-hover flex flex-col overflow-hidden border bg-white shadow-sm', RAIO_GRANDE, TRANSICAO, 'hover:-translate-y-1 hover:shadow-xl', l?.atrasado ? 'border-red-300' : 'border-slate-200')}>
            <div className={cls('flex items-center gap-3 bg-gradient-to-r p-3 text-white', tom.faixa)}>
                <span className="grid h-10 w-10 place-items-center rounded-xl bg-white/20 text-lg"><i className={cls('fas icon-float', v.estado === 'emprestada' ? 'fa-car-side' : tom.icone)} aria-hidden="true" /></span>
                <span className="min-w-0 flex-1">
                    <span className="block truncate font-bold">{v.marca_modelo}</span>
                    <span className="block text-xs text-white/80">{[v.cor, v.ano].filter(Boolean).join(' · ') || '—'}</span>
                </span>
                <span className={cls('rounded-full px-2 py-0.5 text-[11px] font-bold', tom.chip)}>{v.estado_rotulo}</span>
            </div>
            <div className="flex-1 space-y-2 p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <ChapaDaMatricula matricula={v.matricula} tamanho="pequeno" />
                    <span className="text-xs tabular-nums text-slate-500"><i className="fas fa-gauge-high mr-1" aria-hidden="true" />{milhares(v.km)} km</span>
                </div>
                {v.combustivel !== null && <Deposito valor={v.combustivel} rotulo={t('Combustível')} />}
                {v.seguro_caducado && <p className="text-xs font-semibold text-red-600"><i className="fas fa-shield-halved mr-1" aria-hidden="true" />{t('Seguro caducado a :data', { data: data(v.seguro_ate) })}</p>}
                {l && (
                    <div className={cls('space-y-0.5 p-2 text-xs', RAIO, l.atrasado ? 'bg-red-50 text-red-900' : 'bg-amber-50 text-amber-900')}>
                        <p className="font-semibold"><i className="fas fa-user mr-1" aria-hidden="true" />{l.condutor}{l.telefone && ` · ${l.telefone}`}</p>
                        <p>{t('Saiu :quando', { quando: haQuanto(l.saida) })}{l.ordem && ` · ${l.ordem}`}</p>
                        {l.devolver_ate && <p className={cls(l.atrasado && 'font-bold')}><i className="fas fa-calendar-check mr-1" aria-hidden="true" />{l.atrasado ? t('Devia ter voltado a :data', { data: dataHora(l.devolver_ate) }) : t('Volta até :data', { data: dataHora(l.devolver_ate) })}</p>}
                    </div>
                )}
            </div>
            {d.pode_gerir && (v.estado === 'disponivel' || v.estado === 'emprestada') && (
                <div className="border-t border-slate-100 p-3">
                    {v.estado === 'disponivel'
                        ? <Botao cor="bom" tom="solida" icone="fa-key" className="w-full" onClick={emprestar}>{t('Emprestar')}</Botao>
                        : <Botao cor="aviso" tom="solida" icone="fa-right-to-bracket" className="w-full" onClick={receber}>{t('Receber a viatura')}</Botao>}
                </div>
            )}
        </li>
    );
}

function Emprestar({ v, d, aoFechar, aoGravar }: { v: ViaturaDeCortesia; d: QuadroDeCortesia; aoFechar: () => void; aoGravar: () => void }) {
    const [f, porF] = useState({ ordem_id: '', condutor: '', telefone: '', carta: '', km_saida: v.km, combustivel: v.combustivel, devolver_ate: '', notas: '' });
    const gravar = useMutation({ mutationFn: () => cortesia.emprestar(v.id, f), onSuccess: aoGravar });
    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    const escolherOrdem = (id: string) => {
        const o = d.ordens.find((x) => x.valor === id);
        porF({ ...f, ordem_id: id, condutor: f.condutor || o?.dono || '', telefone: f.telefone || o?.telefone || '' });
    };

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Emprestar a viatura')} subtitulo={`${v.matricula} · ${v.marca_modelo}`} icone="fa-key" cor="bom" largura="lg"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="bom" tom="solida" icone="fa-key" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Emprestar')}</Botao></>}>
            <div className="grid gap-3 sm:grid-cols-2">
                <Campo etiqueta={t('Ordem de serviço do cliente')} erro={erros.ordem_id} className="sm:col-span-2" ajuda={t('Escolher a ordem preenche o nome e o telefone.')}>
                    <select value={f.ordem_id} onChange={(e) => escolherOrdem(e.target.value)} className={entrada}>
                        <option value="">{t('Sem ordem')}</option>
                        {d.ordens.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Condutor')} erro={erros.condutor} obrigatorio><input value={f.condutor} maxLength={150} onChange={(e) => porF({ ...f, condutor: e.target.value })} className={entrada} /></Campo>
                <Campo etiqueta={t('Telefone')} erro={erros.telefone}><input type="tel" value={f.telefone} maxLength={30} onChange={(e) => porF({ ...f, telefone: e.target.value })} className={entrada} /></Campo>
                <Campo etiqueta={t('Carta de condução')} erro={erros.carta}><input value={f.carta} maxLength={40} onChange={(e) => porF({ ...f, carta: e.target.value })} className={cls(entrada, 'font-mono')} /></Campo>
                <Campo etiqueta={t('Devolver até')} erro={erros.devolver_ate}><input type="datetime-local" value={f.devolver_ate} onChange={(e) => porF({ ...f, devolver_ate: e.target.value })} className={entrada} /></Campo>
                <Campo etiqueta={t('Km à saída')} erro={erros.km_saida} obrigatorio><input type="number" min={v.km} value={f.km_saida} onChange={(e) => porF({ ...f, km_saida: Number(e.target.value || 0) })} className={cls(entrada, 'tabular-nums')} /></Campo>
                <div className="text-sm">
                    <span className="mb-1 block font-medium text-slate-700">{t('Combustível à saída')}</span>
                    <Deposito valor={f.combustivel} aoMudar={(n) => porF({ ...f, combustivel: n })} rotulo={t('Combustível à saída')} />
                </div>
                <Campo etiqueta={t('Notas')} erro={erros.notas} className="sm:col-span-2"><textarea rows={2} maxLength={2000} value={f.notas} onChange={(e) => porF({ ...f, notas: e.target.value })} placeholder={t('Ex.: Risco antigo na porta traseira.')} className={entrada} /></Campo>
            </div>
        </Modal>
    );
}

function Receber({ l, aoFechar, aoGravar }: { l: EmprestimoDeCortesia; aoFechar: () => void; aoGravar: () => void }) {
    const [f, porF] = useState({ km_entrada: l.km_saida, combustivel: l.combustivel_saida, danos: '' });
    const gravar = useMutation({ mutationFn: () => cortesia.devolver(l.id, f), onSuccess: aoGravar });
    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};
    const andados = f.km_entrada - l.km_saida;

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Receber a viatura')} subtitulo={`${l.matricula ?? ''} · ${l.condutor}`} icone="fa-right-to-bracket" cor="aviso"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Receber')}</Botao></>}>
            <div className="space-y-4">
                <p className={cls('grid grid-cols-2 gap-2 bg-slate-50 p-3 text-xs text-slate-600', RAIO)}>
                    <span><i className="fas fa-arrow-right-from-bracket mr-1" aria-hidden="true" />{t('Saiu :quando', { quando: dataHora(l.saida) })}</span>
                    <span className="text-right tabular-nums">{milhares(l.km_saida)} km · {l.combustivel_saida !== null ? t(':n de 8', { n: l.combustivel_saida }) : '—'}</span>
                    {l.notas && <span className="col-span-2 italic">«{l.notas}»</span>}
                </p>
                <div className="grid gap-3 sm:grid-cols-2">
                    <Campo etiqueta={t('Km à entrada')} erro={erros.km_entrada} obrigatorio>
                        <input type="number" min={l.km_saida} value={f.km_entrada} onChange={(e) => porF({ ...f, km_entrada: Number(e.target.value || 0) })} className={cls(entrada, 'tabular-nums')} />
                    </Campo>
                    <div className="flex items-end">
                        <span className={cls('w-full px-3 py-2 text-center text-sm font-bold tabular-nums', RAIO, andados > 500 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-50 text-emerald-700')}>
                            +{milhares(Math.max(0, andados))} km
                        </span>
                    </div>
                </div>
                <div className="text-sm">
                    <span className="mb-1 flex items-center justify-between font-medium text-slate-700">
                        {t('Combustível à entrada')}
                        {l.combustivel_saida !== null && f.combustivel !== null && f.combustivel < l.combustivel_saida && <span className="text-xs font-bold text-red-600">{t('Menos do que saiu')}</span>}
                    </span>
                    <Deposito valor={f.combustivel} aoMudar={(n) => porF({ ...f, combustivel: n })} rotulo={t('Combustível à entrada')} />
                </div>
                <Campo etiqueta={t('Danos ou observações')} erro={erros.danos}>
                    <textarea rows={2} maxLength={2000} value={f.danos} onChange={(e) => porF({ ...f, danos: e.target.value })} placeholder={t('Vazio se voltou como saiu.')} className={entrada} />
                </Campo>
            </div>
        </Modal>
    );
}
