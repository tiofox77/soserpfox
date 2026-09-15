import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { sinistroDaOrdem, type RespostaDoSinistro, type SinistroParaGravar } from '@/api/oficina';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, kz } from '@/ui/tokens';

/**
 * O SINISTRO DA ORDEM (15/09/2026, OF-15).
 *
 * A seguradora, o processo, o perito e a franquia — e a conta à vista de quem
 * paga o quê. Ao facturar saem duas facturas: a da seguradora e a da franquia.
 */

const ICONE_DO_ESTADO: Record<string, string> = {
    aberto: 'fa-file-signature', peritagem: 'fa-magnifying-glass', aprovado: 'fa-circle-check', recusado: 'fa-circle-xmark', pago: 'fa-sack-dollar',
};

const vazio = (): SinistroParaGravar => ({
    seguradora_id: '', processo: '', apolice: '', data_sinistro: '', perito: '', perito_telefone: '', perito_email: '', data_peritagem: '', valor_aprovado: '', franquia: '', estado: 'aberto', notas: '',
});

const doServidor = (r: RespostaDoSinistro): SinistroParaGravar => (r.data ? {
    seguradora_id: r.data.seguradora_id ? String(r.data.seguradora_id) : '',
    processo: r.data.processo ?? '',
    apolice: r.data.apolice ?? '',
    data_sinistro: r.data.data_sinistro ?? '',
    perito: r.data.perito ?? '',
    perito_telefone: r.data.perito_telefone ?? '',
    perito_email: r.data.perito_email ?? '',
    data_peritagem: r.data.data_peritagem ?? '',
    valor_aprovado: r.data.valor_aprovado !== null ? String(r.data.valor_aprovado) : '',
    franquia: r.data.franquia ? String(r.data.franquia) : '',
    estado: r.data.estado,
    notas: r.data.notas ?? '',
} : vazio());

export function SinistroDaOrdem({ id, aoMudar }: { id: number; aoMudar: (m: string) => void }) {
    const cache = useQueryClient();
    const q = useQuery({ queryKey: ['oficina', 'ordens', 'sinistro', id], queryFn: () => sinistroDaOrdem.ler(id) });
    const [form, porForm] = useState<SinistroParaGravar | null>(null);
    const [aCriar, porACriar] = useState(false);

    useEffect(() => { if (q.data) porForm(doServidor(q.data)); }, [q.data]);

    const responder = (r: RespostaDoSinistro) => {
        cache.setQueryData(['oficina', 'ordens', 'sinistro', id], r);
        void cache.invalidateQueries({ queryKey: ['oficina', 'ordens', 'ficha', id] });
        porACriar(false);
        if (r.message) aoMudar(r.message);
    };
    const gravar = useMutation({ mutationFn: () => sinistroDaOrdem.gravar(id, form as SinistroParaGravar), onSuccess: responder });
    const tirar = useMutation({ mutationFn: () => sinistroDaOrdem.tirar(id), onSuccess: responder });

    if (q.isPending || !form) return <Carregando linhas={6} />;
    if (q.isError) return <AvisoDeErro erro={q.error} />;

    const r = q.data;
    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};
    const mudar = (p: Partial<SinistroParaGravar>) => porForm({ ...form, ...p });
    const podeEditar = r.pode_editar;
    const franquia = Number(form.franquia || 0);
    const seguradoraPaga = Math.max(0, r.reparticao.total - franquia);

    if (!r.data && !aCriar) {
        return (
            <div className={cls('animate-fade-in flex flex-col items-center gap-3 border border-dashed border-slate-300 bg-slate-50 p-8 text-center', RAIO_GRANDE)}>
                <span className="grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-sky-500 to-indigo-600 text-2xl text-white shadow-lg">
                    <i className="fas fa-car-burst icon-float" aria-hidden="true" />
                </span>
                <p className="text-base font-bold text-slate-800">{t('Esta ordem é paga por uma seguradora?')}</p>
                <p className="max-w-md text-sm text-slate-500">{t('Registe o sinistro: a seguradora, o processo, o perito e a franquia. Ao facturar saem duas facturas — a da seguradora e a da franquia do cliente.')}</p>
                {podeEditar && <Botao cor="primaria" tom="solida" icone="fa-plus" onClick={() => porACriar(true)}>{t('Registar sinistro')}</Botao>}
            </div>
        );
    }

    const bloqueado = !podeEditar;
    const facturada = r.facturada;

    return (
        <div className="space-y-4">
            {/* QUEM PAGA O QUÊ */}
            <div className="grid gap-3 sm:grid-cols-3">
                {[
                    { rotulo: t('Total da ordem'), valor: r.reparticao.total, icone: 'fa-calculator', tom: 'from-slate-600 to-slate-800' },
                    { rotulo: t('Paga a seguradora'), valor: seguradoraPaga, icone: 'fa-building-shield', tom: 'from-sky-500 to-indigo-600' },
                    { rotulo: t('Franquia do cliente'), valor: franquia, icone: 'fa-user', tom: 'from-amber-500 to-orange-500' },
                ].map((c, i) => (
                    <div key={c.rotulo} style={cascata(i)} className={cls('entra card-hover flex items-center gap-3 border border-slate-200 bg-white p-3 shadow-sm', RAIO_GRANDE, TRANSICAO, 'hover:-translate-y-0.5 hover:shadow-md')}>
                        <span className={cls('grid h-10 w-10 flex-none place-items-center rounded-xl bg-gradient-to-br text-white shadow', c.tom)}><i className={cls('fas icon-float', c.icone)} aria-hidden="true" /></span>
                        <span>
                            <span className="block text-xs font-semibold text-slate-500">{c.rotulo}</span>
                            <span className="block text-lg font-extrabold tabular-nums text-slate-900">{kz(c.valor)} <span className="text-xs font-semibold text-slate-400">Kz</span></span>
                        </span>
                    </div>
                ))}
            </div>
            {r.reparticao.acima_do_aprovado && (
                <p className={cls('flex items-center gap-2 border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800', RAIO)}>
                    <i className="fas fa-triangle-exclamation" aria-hidden="true" />{t('A parte da seguradora passa o valor que ela aprovou.')}
                </p>
            )}
            {facturada && (
                <p className={cls('flex flex-wrap items-center gap-2 border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800', RAIO)}>
                    <i className="fas fa-lock" aria-hidden="true" />{t('Facturada: a seguradora e a franquia já estão nas facturas.')}
                    {r.data?.factura_franquia && (
                        <a href={r.data.factura_franquia.morada} className={cls('ml-auto font-semibold hover:underline', FOCO)}>
                            <i className="fas fa-file-invoice mr-1" aria-hidden="true" />{t('Franquia: :numero', { numero: r.data.factura_franquia.numero })}
                        </a>
                    )}
                </p>
            )}

            {/* O ESTADO DO PROCESSO */}
            <div className="flex flex-wrap gap-1.5" role="radiogroup" aria-label={t('Estado do sinistro')}>
                {r.estados.map((e) => (
                    <button key={e.valor} type="button" role="radio" aria-checked={form.estado === e.valor} disabled={bloqueado} onClick={() => mudar({ estado: e.valor })}
                        className={cls('inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold', RAIO, TRANSICAO, FOCO,
                            form.estado === e.valor ? (e.valor === 'recusado' ? 'bg-red-600 text-white shadow' : 'bg-indigo-600 text-white shadow') : 'bg-slate-100 text-slate-600 hover:bg-slate-200')}>
                        <i className={cls('fas', ICONE_DO_ESTADO[e.valor] ?? 'fa-circle')} aria-hidden="true" />{e.rotulo}
                    </button>
                ))}
            </div>

            <section className={cls('grid gap-3 border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3', RAIO_GRANDE)}>
                <Campo etiqueta={t('Seguradora')} erro={erros.seguradora_id} ajuda={t('É um cliente da casa (pessoa colectiva).')} className="lg:col-span-2">
                    <select value={form.seguradora_id} disabled={bloqueado || facturada} onChange={(e) => mudar({ seguradora_id: e.target.value })} className={entrada}>
                        <option value="">—</option>
                        {r.seguradoras.map((s) => <option key={s.valor} value={s.valor}>{s.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Nº do processo')} erro={erros.processo}>
                    <input value={form.processo} maxLength={60} disabled={bloqueado} onChange={(e) => mudar({ processo: e.target.value })} className={cls(entrada, 'font-mono')} />
                </Campo>
                <Campo etiqueta={t('Apólice')} erro={erros.apolice}>
                    <input value={form.apolice} maxLength={60} disabled={bloqueado} onChange={(e) => mudar({ apolice: e.target.value })} className={cls(entrada, 'font-mono')} />
                </Campo>
                <Campo etiqueta={t('Data do sinistro')} erro={erros.data_sinistro}>
                    <input type="date" value={form.data_sinistro} disabled={bloqueado} onChange={(e) => mudar({ data_sinistro: e.target.value })} className={entrada} />
                </Campo>
                <Campo etiqueta={t('Franquia do cliente (Kz)')} erro={erros.franquia} ajuda={t('Com imposto — o que o cliente paga.')}>
                    <input type="number" min={0} step="0.01" value={form.franquia} disabled={bloqueado || facturada} onChange={(e) => mudar({ franquia: e.target.value })} className={cls(entrada, 'tabular-nums')} />
                </Campo>
                <Campo etiqueta={t('Perito')} erro={erros.perito}>
                    <input value={form.perito} maxLength={150} disabled={bloqueado} onChange={(e) => mudar({ perito: e.target.value })} className={entrada} />
                </Campo>
                <Campo etiqueta={t('Telefone do perito')} erro={erros.perito_telefone}>
                    <input type="tel" value={form.perito_telefone} maxLength={30} disabled={bloqueado} onChange={(e) => mudar({ perito_telefone: e.target.value })} className={entrada} />
                </Campo>
                <Campo etiqueta={t('Email do perito')} erro={erros.perito_email}>
                    <input type="email" value={form.perito_email} maxLength={150} disabled={bloqueado} onChange={(e) => mudar({ perito_email: e.target.value })} className={entrada} />
                </Campo>
                <Campo etiqueta={t('Data da peritagem')} erro={erros.data_peritagem}>
                    <input type="date" value={form.data_peritagem} disabled={bloqueado} onChange={(e) => mudar({ data_peritagem: e.target.value })} className={entrada} />
                </Campo>
                <Campo etiqueta={t('Valor aprovado pela seguradora (Kz)')} erro={erros.valor_aprovado}>
                    <input type="number" min={0} step="0.01" value={form.valor_aprovado} disabled={bloqueado} onChange={(e) => mudar({ valor_aprovado: e.target.value })} className={cls(entrada, 'tabular-nums')} />
                </Campo>
                <Campo etiqueta={t('Notas')} erro={erros.notas} className="sm:col-span-2 lg:col-span-3">
                    <textarea rows={2} maxLength={2000} value={form.notas} disabled={bloqueado} onChange={(e) => mudar({ notas: e.target.value })} className={entrada} />
                </Campo>
            </section>

            {podeEditar && (
                <div className="flex flex-wrap items-center gap-2">
                    {r.data && !facturada && <Botao cor="perigo" icone="fa-trash" aTrabalhar={tirar.isPending} onClick={() => tirar.mutate()}>{t('Não é sinistro')}</Botao>}
                    {!r.data && <Botao onClick={() => { porACriar(false); porForm(vazio()); }}>{t('Cancelar')}</Botao>}
                    <Botao cor="primaria" tom="solida" icone="fa-floppy-disk" className="sm:ml-auto" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>{t('Guardar sinistro')}</Botao>
                </div>
            )}
        </div>
    );
}
