import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { pecasDaOrdem, type PecasDaOrdem } from '@/api/oficina';
import { t, tn } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, dataHora } from '@/ui/tokens';

/**
 * AS PEÇAS DA ORDEM E O QUE FALTA (15/09/2026, OF-08).
 *
 * Por baixo da aprovação: cada peça do catálogo com o que há no armazém, as
 * que faltam a vermelho e o botão que as pede às Compras numa requisição — que
 * fica ligada à ordem e a traz de volta a «Em curso» quando a encomenda chega.
 */

const numero = (n: number) => n.toLocaleString(undefined, { maximumFractionDigits: 3 });

const COR_DA_REQUISICAO: Record<string, string> = {
    rascunho: 'bg-slate-100 text-slate-700', submetida: 'bg-blue-50 text-blue-700', aprovada: 'bg-emerald-50 text-emerald-700',
    rejeitada: 'bg-red-50 text-red-700', encomendada: 'bg-violet-50 text-violet-700', cancelada: 'bg-slate-100 text-slate-400',
};

export function PecasEmFalta({ id, aoMudar }: { id: number; aoMudar: (m: string) => void }) {
    const cache = useQueryClient();
    const chave = ['oficina', 'ordens', 'pecas', id];
    const q = useQuery({ queryKey: chave, queryFn: () => pecasDaOrdem.ler(id) });
    const [aPedir, porAPedir] = useState(false);

    if (!q.data || q.data.pecas.filter((p) => p.do_catalogo).length === 0) return null;

    const d = q.data;
    const emFalta = d.pecas.filter((p) => p.falta > 0);

    return (
        <section className={cls('border bg-white p-4', RAIO_GRANDE, emFalta.length > 0 ? 'border-red-200' : 'border-slate-200')} aria-label={t('Peças no armazém')}>
            <div className="flex flex-wrap items-center gap-2">
                <span className={cls('grid h-9 w-9 place-items-center rounded-xl text-white shadow', emFalta.length > 0 ? 'bg-gradient-to-br from-red-500 to-rose-600' : 'bg-gradient-to-br from-emerald-500 to-teal-600')}>
                    <i className={cls('fas', emFalta.length > 0 ? 'fa-triangle-exclamation' : 'fa-boxes-stacked')} aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-bold text-slate-900">
                        {emFalta.length > 0 ? tn(':n peça em falta no armazém|:n peças em falta no armazém', emFalta.length, { n: emFalta.length }) : t('Todas as peças estão no armazém')}
                    </p>
                    {d.armazem && <p className="text-xs text-slate-500"><i className="fas fa-warehouse mr-1" aria-hidden="true" />{d.armazem}</p>}
                </div>
                {emFalta.length > 0 && d.pode_pedir && d.tem_compras && (
                    <Botao cor="perigo" tom="solida" icone="fa-cart-plus" onClick={() => porAPedir(true)}>{t('Pedir as peças em falta')}</Botao>
                )}
                {emFalta.length > 0 && !d.tem_compras && <span className="text-xs text-slate-500">{t('Pedir peças às compras precisa do módulo Compras.')}</span>}
            </div>

            <ul className="mt-3 flex flex-wrap gap-2">
                {d.pecas.filter((p) => p.do_catalogo).map((p) => (
                    <li key={p.id} className={cls('inline-flex items-center gap-2 rounded-lg px-2.5 py-1 text-xs ring-1 ring-inset', p.falta > 0 ? 'bg-red-50 text-red-800 ring-red-200' : 'bg-emerald-50 text-emerald-800 ring-emerald-200')}>
                        <i className={cls('fas', p.falta > 0 ? 'fa-circle-xmark' : 'fa-circle-check')} aria-hidden="true" />
                        <span className="font-semibold">{p.nome}</span>
                        <span className="tabular-nums opacity-80">{t('precisa :q · há :s', { q: numero(p.quantidade), s: numero(p.em_stock ?? 0) })}</span>
                        {p.falta > 0 && <span className="rounded bg-red-600 px-1.5 font-bold text-white">{t('falta :n', { n: numero(p.falta) })}</span>}
                    </li>
                ))}
            </ul>

            {d.requisicoes.length > 0 && (
                <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-dashed border-slate-200 pt-3 text-xs">
                    <span className="font-semibold text-slate-500"><i className="fas fa-file-lines mr-1" aria-hidden="true" />{t('Requisições desta ordem')}</span>
                    {d.requisicoes.map((r) => (
                        <a key={r.id} href="/compras/requisicoes" className={cls('inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-semibold hover:underline', COR_DA_REQUISICAO[r.estado] ?? 'bg-slate-100', FOCO)} title={dataHora(r.em)}>
                            <span className="font-mono">{r.numero}</span> · {r.estado_rotulo}
                        </a>
                    ))}
                </div>
            )}

            {aPedir && (
                <PedirPecas id={id} d={d} aoFechar={() => porAPedir(false)}
                    aoGravar={(r) => { cache.setQueryData(chave, r); void cache.invalidateQueries({ queryKey: ['oficina', 'ordens'] }); porAPedir(false); aoMudar(r.message ?? ''); }} />
            )}
        </section>
    );
}

function PedirPecas({ id, d, aoFechar, aoGravar }: { id: number; d: PecasDaOrdem; aoFechar: () => void; aoGravar: (r: PecasDaOrdem) => void }) {
    const emFalta = d.pecas.filter((p) => p.falta > 0);
    const [quantidades, porQuantidades] = useState<Record<number, number>>(() => Object.fromEntries(emFalta.map((p) => [p.id, p.falta])));
    const [submeter, porSubmeter] = useState(true);
    const [esperar, porEsperar] = useState(!['waiting_parts', 'completed', 'delivered'].includes(d.estado_da_ordem));

    const pedir = useMutation({
        mutationFn: () => pecasDaOrdem.requisitar(id, Object.entries(quantidades).filter(([, q]) => q > 0).map(([linha, q]) => ({ linha_id: Number(linha), quantidade: q })), submeter, esperar),
        onSuccess: aoGravar,
    });

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Pedir as peças em falta')} subtitulo={t('Nasce uma requisição de compra ligada a esta ordem')} icone="fa-cart-plus" cor="perigo" largura="md"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="perigo" tom="solida" icone="fa-paper-plane" aTrabalhar={pedir.isPending} onClick={() => pedir.mutate()}>{t('Criar requisição')}</Botao></>}>
            <div className="space-y-3">
                <AvisoDeErro erro={pedir.error} />
                <ul className="divide-y divide-slate-100 border border-slate-200">
                    {emFalta.map((p) => (
                        <li key={p.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                            <span className="min-w-0 flex-1">
                                <span className="block truncate font-semibold text-slate-800">{p.nome}</span>
                                {p.codigo && <span className="block font-mono text-[11px] text-slate-400">{p.codigo}</span>}
                            </span>
                            <label className="flex items-center gap-2 text-xs text-slate-500">{t('Quantidade')}
                                <input type="number" min={0} step="0.001" value={quantidades[p.id] ?? 0} onChange={(e) => porQuantidades({ ...quantidades, [p.id]: Number(e.target.value) })}
                                    className={cls('w-24 border border-slate-300 px-2 py-1 text-right text-sm tabular-nums', RAIO, FOCO)} />
                            </label>
                        </li>
                    ))}
                </ul>
                <label className={cls('flex cursor-pointer items-center gap-3 border px-3 py-2 text-sm', RAIO, TRANSICAO, submeter ? 'border-blue-200 bg-blue-50' : 'border-slate-200')}>
                    <input type="checkbox" checked={submeter} onChange={(e) => porSubmeter(e.target.checked)} className="h-4 w-4 rounded border-slate-300 text-blue-600" />
                    {t('Submeter logo para aprovação (senão fica em rascunho)')}
                </label>
                <label className={cls('flex cursor-pointer items-center gap-3 border px-3 py-2 text-sm', RAIO, TRANSICAO, esperar ? 'border-amber-200 bg-amber-50' : 'border-slate-200')}>
                    <input type="checkbox" checked={esperar} onChange={(e) => porEsperar(e.target.checked)} className="h-4 w-4 rounded border-slate-300 text-amber-600" />
                    {t('Passar a ordem a «À espera de peças» (volta a «Em curso» quando a encomenda for recebida)')}
                </label>
            </div>
        </Modal>
    );
}
