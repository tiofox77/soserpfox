import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { recomendacoesAdiadas, type LinhaDaOrdem, type RecomendacaoAdiada } from '@/api/oficina';
import { t, tn } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Modal } from '@/ui/Modal';
import { cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, data, kz } from '@/ui/tokens';

/**
 * AS RECOMENDAÇÕES ADIADAS NA ORDEM (15/09/2026, OF-12).
 *
 * Quando o carro volta, o que ficou por fazer da última vez aparece em cima das
 * linhas — marcado, com um clique entra na ordem (à espera do cliente ou já
 * aprovado). E cada linha pode ser ADIADA: sai desta ordem e fica guardada.
 */

export const TOM_DA_GRAVIDADE = {
    urgente: 'bg-red-100 text-red-700',
    atencao: 'bg-amber-100 text-amber-800',
} as const;

export function RecomendacoesDaViatura({ id, podeEditar, aoMudar }: { id: number; podeEditar: boolean; aoMudar: (m: string) => void }) {
    const cache = useQueryClient();
    const q = useQuery({ queryKey: ['oficina', 'ordens', 'recomendacoes', id], queryFn: () => recomendacoesAdiadas.daOrdem(id) });
    const [escolhidas, porEscolhidas] = useState<number[] | null>(null);
    const [aprovacao, porAprovacao] = useState(true);
    const [aberto, porAberto] = useState(true);

    const juntar = useMutation({
        mutationFn: (ids: number[]) => recomendacoesAdiadas.juntar(id, ids, aprovacao),
        onSuccess: (r) => { porEscolhidas(null); void cache.invalidateQueries({ queryKey: ['oficina', 'ordens'] }); aoMudar(r.message); },
    });

    const lista = q.data?.data ?? [];
    if (lista.length === 0) return null;

    const marcadas = escolhidas ?? lista.map((r) => r.id);
    const valor = lista.filter((r) => marcadas.includes(r.id)).reduce((s, r) => s + r.valor, 0);
    const alternar = (rid: number) => porEscolhidas(marcadas.includes(rid) ? marcadas.filter((x) => x !== rid) : [...marcadas, rid]);

    return (
        <section className={cls('entra overflow-hidden border border-rose-200 bg-gradient-to-br from-rose-50 to-white shadow-sm', RAIO_GRANDE)}>
            <button type="button" onClick={() => porAberto(!aberto)} aria-expanded={aberto}
                className={cls('flex w-full items-center gap-3 px-4 py-3 text-left', FOCO)}>
                <span className="grid h-9 w-9 flex-none place-items-center rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 text-white shadow">
                    <i className="fas fa-hourglass-half icon-float" aria-hidden="true" />
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block text-sm font-bold text-rose-900">
                        {tn('Esta viatura tem :n trabalho adiado|Esta viatura tem :n trabalhos adiados', lista.length, { n: lista.length })}
                    </span>
                    <span className="block text-xs text-rose-700/80">{t('Ficaram por fazer em visitas anteriores — proponha-os outra vez.')}</span>
                </span>
                <i className={cls('fas fa-chevron-down text-rose-400 transition-transform duration-300', aberto && 'rotate-180')} aria-hidden="true" />
            </button>

            {aberto && (
                <div className="space-y-3 border-t border-rose-100 px-4 py-3">
                    <ul className="space-y-1.5">
                        {lista.map((r, i) => (
                            <li key={r.id} style={cascata(i)} className="entra">
                                <label className={cls('flex cursor-pointer items-start gap-3 border bg-white px-3 py-2', RAIO, TRANSICAO,
                                    marcadas.includes(r.id) ? 'border-rose-300 shadow-sm' : 'border-slate-200 opacity-70')}>
                                    {podeEditar && <input type="checkbox" checked={marcadas.includes(r.id)} onChange={() => alternar(r.id)} className="mt-1 h-4 w-4 rounded border-slate-300 text-rose-600" />}
                                    <span className="min-w-0 flex-1">
                                        <span className="flex flex-wrap items-center gap-1.5">
                                            <i className={cls('fas text-xs', r.tipo === 'service' ? 'fa-screwdriver-wrench text-indigo-400' : 'fa-gear text-emerald-500')} aria-hidden="true" />
                                            <span className="text-sm font-semibold text-slate-800">{r.nome}</span>
                                            {r.gravidade && <span className={cls('rounded-full px-1.5 py-0.5 text-[10px] font-bold uppercase', TOM_DA_GRAVIDADE[r.gravidade])}>{r.gravidade === 'urgente' ? t('Urgente') : t('Atenção')}</span>}
                                        </span>
                                        <span className="block text-xs text-slate-500">
                                            {r.origem_rotulo}{r.ordem && ` · ${r.ordem}`}{r.criada_em && ` · ${data(r.criada_em)}`}{r.nota && ` · ${r.nota}`}
                                        </span>
                                    </span>
                                    <span className="flex-none text-right text-sm font-bold tabular-nums text-slate-800">{r.valor > 0 ? kz(r.valor) : '—'}</span>
                                </label>
                            </li>
                        ))}
                    </ul>
                    {podeEditar && (
                        <div className="flex flex-wrap items-center gap-3">
                            <label className="inline-flex items-center gap-2 text-xs text-slate-600">
                                <input type="checkbox" checked={aprovacao} onChange={(e) => porAprovacao(e.target.checked)} className="h-4 w-4 rounded border-slate-300 text-rose-600" />
                                {t('Pedir a aprovação do cliente')}
                            </label>
                            <Botao cor="primaria" tom="solida" icone="fa-arrow-rotate-left" className="sm:ml-auto" disabled={marcadas.length === 0}
                                aTrabalhar={juntar.isPending} onClick={() => juntar.mutate(marcadas)}>
                                {tn('Juntar :n à ordem|Juntar :n à ordem', marcadas.length, { n: marcadas.length })}{valor > 0 ? ` · ${kz(valor)}` : ''}
                            </Botao>
                        </div>
                    )}
                </div>
            )}
        </section>
    );
}

const daquiA = (dias: number) => {
    const d = new Date();
    d.setDate(d.getDate() + dias);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

export function AdiarLinha({ id, linha, aoFechar, aoGravar }: { id: number; linha: LinhaDaOrdem; aoFechar: () => void; aoGravar: (m: string) => void }) {
    const [voltar, porVoltar] = useState(daquiA(30));
    const [nota, porNota] = useState('');
    const adiar = useMutation({
        mutationFn: () => recomendacoesAdiadas.adiarLinha(id, linha.id, { voltar_em: voltar || null, nota }),
        onSuccess: (r) => aoGravar(r.message),
    });
    const erros = adiar.error instanceof ErroDaApi ? adiar.error.erros : {};

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Adiar para outra visita')} subtitulo={linha.nome} icone="fa-hourglass-half" cor="rosa"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-hourglass-half" aTrabalhar={adiar.isPending} onClick={() => adiar.mutate()}>{t('Adiar')}</Botao></>}>
            <div className="space-y-4">
                <p className={cls('flex items-start gap-2 bg-rose-50 px-3 py-2 text-xs text-rose-800', RAIO)}>
                    <i className="fas fa-circle-info mt-0.5" aria-hidden="true" />
                    {t('A linha sai desta ordem e fica guardada na viatura. Volta a aparecer na próxima ordem e nos Lembretes na data escolhida.')}
                </p>
                <div className="flex flex-wrap gap-1.5">
                    {[7, 30, 90, 180].map((n) => (
                        <button key={n} type="button" onClick={() => porVoltar(daquiA(n))}
                            className={cls('px-3 py-1.5 text-xs font-semibold', RAIO, TRANSICAO, FOCO, voltar === daquiA(n) ? 'bg-rose-600 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200')}>
                            {tn(':n dia|:n dias', n, { n })}
                        </button>
                    ))}
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <Campo etiqueta={t('Voltar a propor a')} erro={erros.voltar_em}>
                        <input type="date" value={voltar} min={daquiA(0)} onChange={(e) => porVoltar(e.target.value)} className={entrada} />
                    </Campo>
                    <Campo etiqueta={t('Porquê')} erro={erros.nota}>
                        <input value={nota} maxLength={500} onChange={(e) => porNota(e.target.value)} placeholder={t('Ex.: O cliente prefere fazer no próximo mês.')} className={entrada} />
                    </Campo>
                </div>
            </div>
        </Modal>
    );
}
