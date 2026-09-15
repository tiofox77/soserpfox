import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { inqueritoDaOrdem } from '@/api/oficina';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, dataHora } from '@/ui/tokens';

import { Estrelas } from './AvaliarServico';

/**
 * A AVALIAÇÃO DO CLIENTE NA ORDEM (15/09/2026, OF-14).
 *
 * Respondida, mostra as estrelas e o comentário; por responder, o link para
 * copiar ou mandar por WhatsApp (o SMS/email saem sozinhos com o módulo
 * Notificações).
 */
export function AvaliacaoNaOrdem({ id, telefone, dono }: { id: number; telefone?: string | null; dono?: string | null }) {
    const cache = useQueryClient();
    const q = useQuery({ queryKey: ['oficina', 'ordens', 'inquerito', id], queryFn: () => inqueritoDaOrdem.ler(id) });
    const [copiado, porCopiado] = useState(false);
    const criar = useMutation({
        mutationFn: () => inqueritoDaOrdem.criar(id),
        onSuccess: (r) => cache.setQueryData(['oficina', 'ordens', 'inquerito', id], r),
    });

    if (!q.data || (!q.data.data && !q.data.pode_criar)) return null;
    const s = q.data.data;

    if (s?.respondido_em && s.nota) {
        return (
            <section className={cls('animate-fade-in flex flex-wrap items-center gap-4 border border-amber-200 bg-gradient-to-r from-amber-50 to-white p-4', RAIO_GRANDE)}>
                <span className="grid h-12 w-12 flex-none place-items-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-xl text-white shadow">
                    <i className="fas fa-star icon-float" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-2 text-sm font-bold text-slate-800">
                        {t('Avaliação do cliente')} <Estrelas nota={s.nota} />
                        {s.recomenda !== null && (
                            <span className={cls('rounded-full px-2 py-0.5 text-[11px] font-bold', s.recomenda ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700')}>
                                <i className={cls('fas mr-1', s.recomenda ? 'fa-thumbs-up' : 'fa-thumbs-down')} aria-hidden="true" />{s.recomenda ? t('Recomenda') : t('Não recomenda')}
                            </span>
                        )}
                    </p>
                    {s.comentario && <p className="mt-1 text-sm italic text-slate-600">«{s.comentario}»</p>}
                    <p className="text-xs text-slate-400">{dataHora(s.respondido_em)}</p>
                </div>
            </section>
        );
    }

    const digitos = (telefone ?? '').replace(/\D/g, '');
    const whatsapp = digitos.length === 9 && digitos.startsWith('9') ? `244${digitos}` : digitos.length >= 11 ? digitos : '';
    const mensagem = s ? t('Olá :dono, obrigado pela confiança! Pode avaliar o nosso serviço em 30 segundos? :link', { dono: dono ?? '', link: s.link }) : '';

    return (
        <section className={cls('flex flex-wrap items-center gap-3 border border-dashed border-amber-300 bg-amber-50/50 p-4', RAIO_GRANDE)}>
            <span className="grid h-10 w-10 flex-none place-items-center rounded-xl bg-amber-100 text-amber-600"><i className="fas fa-star-half-stroke" aria-hidden="true" /></span>
            <span className="min-w-0 flex-1 text-sm">
                <span className="block font-semibold text-slate-800">{s ? t('À espera da avaliação do cliente') : t('Pedir a avaliação do cliente')}</span>
                <span className="block text-xs text-slate-500">
                    {s?.enviado_em ? t('Link enviado a :quando por SMS/email.', { quando: dataHora(s.enviado_em) }) : t('O cliente dá a nota e um comentário, sem conta.')}
                </span>
            </span>
            {s ? (
                <span className="flex flex-wrap gap-2">
                    <button type="button" onClick={() => { void navigator.clipboard?.writeText(s.link); porCopiado(true); window.setTimeout(() => porCopiado(false), 2000); }}
                        className={cls('inline-flex h-8 items-center gap-1.5 border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50', RAIO, TRANSICAO, FOCO)}>
                        <i className={cls('fas', copiado ? 'fa-check text-emerald-600' : 'fa-copy')} aria-hidden="true" />{copiado ? t('Copiado') : t('Copiar link')}
                    </button>
                    {whatsapp && (
                        <a href={`https://wa.me/${whatsapp}?text=${encodeURIComponent(mensagem)}`} target="_blank" rel="noopener noreferrer"
                            className={cls('inline-flex h-8 items-center gap-1.5 border border-emerald-200 bg-emerald-50 px-2.5 text-xs font-semibold text-emerald-700 hover:-translate-y-0.5 hover:bg-emerald-100', RAIO, TRANSICAO, FOCO)}>
                            <i className="fab fa-whatsapp" aria-hidden="true" />WhatsApp
                        </a>
                    )}
                </span>
            ) : (
                <Botao cor="aviso" tom="solida" altura="pequeno" icone="fa-link" aTrabalhar={criar.isPending} onClick={() => criar.mutate()}>{t('Criar link de avaliação')}</Botao>
            )}
        </section>
    );
}
