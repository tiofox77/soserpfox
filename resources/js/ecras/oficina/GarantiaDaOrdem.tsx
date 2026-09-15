import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { ordens, type GarantiaDaFicha } from '@/api/oficina';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, data } from '@/ui/tokens';

/**
 * A GARANTIA NA ORDEM (15/09/2026, OF-19).
 *
 * Numa ordem de garantia: de que ordem é, a causa e o motivo — e que não se
 * factura. Numa ordem fechada: até quando dura a garantia, os retrabalhos que
 * já teve e o botão para abrir mais um.
 */
export function GarantiaDaOrdem({ id, g, podeCriar, aoMudar }: { id: number; g: GarantiaDaFicha; podeCriar: boolean; aoMudar: (m: string) => void }) {
    const [aAbrir, porAAbrir] = useState(false);

    if (g.e_garantia) {
        return (
            <div className={cls('animate-fade-in flex flex-wrap items-center gap-3 border border-purple-200 bg-gradient-to-r from-purple-50 to-white p-4', RAIO_GRANDE)}>
                <span className="grid h-11 w-11 flex-none place-items-center rounded-xl bg-gradient-to-br from-purple-500 to-fuchsia-600 text-lg text-white shadow"><i className="fas fa-shield-heart icon-float" aria-hidden="true" /></span>
                <span className="min-w-0 flex-1 text-sm">
                    <span className="block font-bold text-purple-900">{t('Retrabalho em garantia')} · {g.causa_rotulo}</span>
                    {g.motivo && <span className="block text-purple-800">{g.motivo}</span>}
                    <span className="block text-xs text-purple-700">{t('Por conta da oficina: não se factura ao cliente.')}</span>
                </span>
                {g.de_ordem && (
                    <a href={`/workshop/work-orders?ordem=${g.de_ordem.id}`} className={cls('inline-flex items-center gap-1.5 text-sm font-semibold text-purple-800 hover:underline', FOCO)}>
                        {t('Ordem original :numero', { numero: g.de_ordem.numero })}<i className="fas fa-arrow-right text-xs" aria-hidden="true" />
                    </a>
                )}
            </div>
        );
    }

    if (!g.garantia_ate && g.retrabalhos.length === 0 && !g.pode_abrir) return null;

    return (
        <div className={cls('flex flex-wrap items-center gap-3 border p-3', RAIO_GRANDE, g.expirou ? 'border-slate-200 bg-slate-50' : 'border-emerald-200 bg-emerald-50/60')}>
            <span className={cls('grid h-10 w-10 flex-none place-items-center rounded-xl text-white shadow', g.expirou ? 'bg-slate-400' : 'bg-gradient-to-br from-emerald-500 to-teal-600')}>
                <i className="fas fa-shield-halved" aria-hidden="true" />
            </span>
            <span className="min-w-0 flex-1 text-sm">
                <span className={cls('block font-semibold', g.expirou ? 'text-slate-700' : 'text-emerald-900')}>
                    {g.garantia_ate ? (g.expirou ? t('Garantia terminou a :data', { data: data(g.garantia_ate) }) : t('Em garantia até :data', { data: data(g.garantia_ate) })) : t('Garantia')}
                </span>
                {g.retrabalhos.length > 0 ? (
                    <span className="mt-0.5 flex flex-wrap gap-1.5">
                        {g.retrabalhos.map((r) => (
                            <a key={r.id} href={`/workshop/work-orders?ordem=${r.id}`} className={cls('rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-semibold text-purple-800 hover:bg-purple-200', TRANSICAO, FOCO)}>
                                <i className="fas fa-shield-heart mr-1" aria-hidden="true" />{r.numero} · {r.causa_rotulo} · {r.estado_rotulo}
                            </a>
                        ))}
                    </span>
                ) : (
                    <span className="block text-xs text-slate-500">{t('Se o problema voltar, abra um retrabalho: fica ligado a esta ordem e não se factura.')}</span>
                )}
            </span>
            {g.pode_abrir && podeCriar && <Botao cor="primaria" icone="fa-shield-heart" altura="pequeno" onClick={() => porAAbrir(true)}>{t('Abrir retrabalho em garantia')}</Botao>}

            {aAbrir && <AbrirGarantia id={id} g={g} aoFechar={() => porAAbrir(false)} aoAbrir={(m, novo) => { porAAbrir(false); aoMudar(m); window.location.assign(`/workshop/work-orders?ordem=${novo}`); }} />}
        </div>
    );
}

function AbrirGarantia({ id, g, aoFechar, aoAbrir }: { id: number; g: GarantiaDaFicha; aoFechar: () => void; aoAbrir: (m: string, novo: number) => void }) {
    const [causa, porCausa] = useState(g.causas[0]?.valor ?? 'mao_de_obra');
    const [motivo, porMotivo] = useState('');
    const [foraDoPrazo, porForaDoPrazo] = useState(false);
    const abrir = useMutation({
        mutationFn: () => ordens.abrirGarantia(id, { causa, motivo, fora_do_prazo: foraDoPrazo }),
        onSuccess: (r) => aoAbrir(r.message, r.ordem_id),
    });
    const erros = abrir.error instanceof ErroDaApi ? abrir.error.erros : {};

    return (
        <Modal aberto aoFechar={aoFechar} titulo={t('Abrir retrabalho em garantia')} subtitulo={t('Nova ordem ligada a esta, por conta da oficina')} icone="fa-shield-heart" cor="roxo"
            rodape={<><Botao onClick={aoFechar}>{t('Cancelar')}</Botao><Botao cor="primaria" tom="solida" icone="fa-shield-heart" aTrabalhar={abrir.isPending} disabled={g.expirou && !foraDoPrazo} onClick={() => abrir.mutate()}>{t('Abrir')}</Botao></>}>
            <div className="space-y-4">
                <div className="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label={t('Causa')}>
                    {g.causas.map((c) => (
                        <button key={c.valor} type="button" role="radio" aria-checked={causa === c.valor} onClick={() => porCausa(c.valor)}
                            className={cls('border px-3 py-2 text-left text-sm font-semibold', RAIO, TRANSICAO, FOCO, causa === c.valor ? 'border-purple-400 bg-purple-50 text-purple-900 ring-2 ring-purple-100' : 'border-slate-200 text-slate-600 hover:bg-slate-50')}>
                            {c.rotulo}
                        </button>
                    ))}
                </div>
                <Campo etiqueta={t('O que voltou a acontecer')} erro={erros.motivo} obrigatorio>
                    <textarea rows={3} maxLength={500} value={motivo} onChange={(e) => porMotivo(e.target.value)} placeholder={t('Ex.: A embraiagem volta a patinar.')} className={entrada} />
                </Campo>
                {g.expirou && (
                    <label className={cls('flex items-start gap-2 border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900', RAIO)}>
                        <input type="checkbox" checked={foraDoPrazo} onChange={(e) => porForaDoPrazo(e.target.checked)} className="mt-0.5 h-4 w-4 rounded border-amber-400 text-purple-600" />
                        {t('A garantia terminou a :data. A oficina assume o retrabalho na mesma.', { data: data(g.garantia_ate) })}
                    </label>
                )}
                {abrir.error && !(abrir.error instanceof ErroDaApi && Object.keys(erros).length) && <p className="text-sm text-red-700">{abrir.error.message}</p>}
            </div>
        </Modal>
    );
}
