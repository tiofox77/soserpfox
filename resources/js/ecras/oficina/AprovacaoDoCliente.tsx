import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';

import { ordens, type FichaDaOrdem, type LinhaDaOrdem } from '@/api/oficina';
import { avisar } from '@/casca/avisos';
import { t, tn } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, dataHora, kz } from '@/ui/tokens';

/**
 * O ORÇAMENTO APROVADO PELO CLIENTE — do lado da oficina (15/09/2026, OF-03).
 *
 * Por cima das linhas da ordem: quantas estão à espera e quanto valem, o botão
 * que cria o link para o cliente (copiar, WhatsApp, abrir), e quem assinou.
 * Em cada linha, o selo do estado e as decisões registadas à mão.
 */

/** O número do dono em formato internacional para o WhatsApp — Angola por omissão. */
function numeroParaWhatsApp(telefone: string | null | undefined): string {
    const digitos = (telefone ?? '').replace(/\D/g, '');
    if (digitos.length === 9 && digitos.startsWith('9')) return `244${digitos}`;
    return digitos.length >= 11 ? digitos : '';
}

export function AprovacaoDoCliente({ id, ficha, podeEditar, aoMudar }: {
    id: number; ficha: FichaDaOrdem; podeEditar: boolean; aoMudar: (mensagem: string) => void;
}) {
    const a = ficha.aprovacao;
    const [copiado, porCopiado] = useState(false);

    const pedir = useMutation({ mutationFn: () => ordens.pedirAprovacao(id), onSuccess: (r) => aoMudar(r.message) });
    const cancelar = useMutation({ mutationFn: () => ordens.cancelarAprovacao(id), onSuccess: (r) => aoMudar(r.message) });

    if (!a || (a.a_espera === 0 && !a.link && !a.assinado_por && a.recusadas === 0)) return null;

    const link = pedir.data?.data.link ?? a.link;
    const mensagem = t('Olá :dono, o orçamento da viatura :matricula (:numero) está à espera da sua aprovação: :link', {
        dono: ficha.viatura_ficha?.dono ?? '', matricula: ficha.viatura_ficha?.matricula ?? '', numero: ficha.numero, link: link ?? '',
    });
    const whatsapp = `https://wa.me/${numeroParaWhatsApp(ficha.viatura_ficha?.telefone)}?text=${encodeURIComponent(mensagem)}`;

    const copiar = async () => {
        if (!link) return;
        try {
            await navigator.clipboard.writeText(link);
            porCopiado(true);
            avisar(t('Link copiado.'), 'ok');
            window.setTimeout(() => porCopiado(false), 2000);
        } catch {
            avisar(t('Não foi possível copiar. Seleccione o link e copie à mão.'), 'aviso');
        }
    };

    return (
        <section className={cls('animate-fade-in overflow-hidden border', RAIO_GRANDE, a.a_espera > 0 ? 'border-amber-300 bg-gradient-to-br from-amber-50 to-white' : 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-white')}
            aria-label={t('Aprovação do cliente')}>
            <div className="flex flex-wrap items-center gap-3 p-4">
                <span className={cls('grid h-11 w-11 place-items-center rounded-xl text-lg text-white shadow', a.a_espera > 0 ? 'bg-gradient-to-br from-amber-400 to-orange-500' : 'bg-gradient-to-br from-emerald-500 to-teal-600')}>
                    <i className={cls('fas', a.a_espera > 0 ? 'fa-hand' : 'fa-circle-check')} aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="font-bold text-slate-900">
                        {a.a_espera > 0
                            ? tn(':n linha à espera do cliente|:n linhas à espera do cliente', a.a_espera, { n: a.a_espera })
                            : t('Orçamento decidido pelo cliente')}
                    </p>
                    <p className="text-xs text-slate-600">
                        {a.a_espera > 0 && <>{t('Valem :v Kz, fora do total até serem aprovadas.', { v: kz(a.valor_a_espera) })}</>}
                        {a.recusadas > 0 && <> {tn(':n recusada.|:n recusadas.', a.recusadas, { n: a.recusadas })}</>}
                    </p>
                </div>
                {a.assinado_por && (
                    <span className="flex items-center gap-2 rounded-lg bg-white/80 p-1.5 pr-3 ring-1 ring-slate-200">
                        {a.assinatura && <img src={a.assinatura} alt={t('Assinatura de :nome', { nome: a.assinado_por })} className="h-9 w-20 rounded bg-white object-contain" />}
                        <span className="text-xs">
                            <span className="block font-semibold text-slate-800">{t('Assinado por :nome', { nome: a.assinado_por })}</span>
                            <span className="block text-slate-500">{dataHora(a.assinado_em)}</span>
                        </span>
                    </span>
                )}
                {podeEditar && a.a_espera > 0 && !link && (
                    <Botao cor="aviso" tom="solida" icone="fa-paper-plane" aTrabalhar={pedir.isPending} onClick={() => pedir.mutate()}>{t('Pedir aprovação ao cliente')}</Botao>
                )}
            </div>

            {link && a.a_espera > 0 && (
                <div className="animate-fade-in border-t border-amber-200 bg-white/70 p-4">
                    <p className="mb-2 text-xs text-slate-600">
                        <i className="fas fa-link mr-1.5 text-amber-500" aria-hidden="true" />
                        {t('O cliente abre o link, aprova ou recusa cada linha e assina — sem precisar de conta. Vale até :data.', { data: dataHora(pedir.data?.data.expira_em ?? a.expira_em) })}
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <input readOnly value={link} onFocus={(e) => e.currentTarget.select()} aria-label={t('Link de aprovação')}
                            className={cls('min-w-0 flex-1 basis-64 border border-slate-300 bg-white px-3 py-2 font-mono text-xs text-slate-700', RAIO, FOCO)} />
                        <Botao icone={copiado ? 'fa-check' : 'fa-copy'} onClick={() => void copiar()}>{copiado ? t('Copiado') : t('Copiar')}</Botao>
                        <a href={whatsapp} target="_blank" rel="noreferrer"
                            className={cls('inline-flex h-10 items-center gap-2 bg-[#25D366] px-4 text-sm font-semibold text-white shadow-sm hover:-translate-y-0.5 hover:shadow-md', RAIO, TRANSICAO, FOCO)}>
                            <i className="fab fa-whatsapp text-base" aria-hidden="true" />WhatsApp
                        </a>
                        <a href={link} target="_blank" rel="noreferrer" className={cls('inline-flex h-10 items-center gap-1.5 px-3 text-sm font-semibold text-indigo-700 hover:underline', RAIO, FOCO)}>
                            <i className="fas fa-up-right-from-square" aria-hidden="true" />{t('Abrir')}
                        </a>
                        {podeEditar && (
                            <Botao cor="perigo" altura="pequeno" icone="fa-link-slash" aTrabalhar={cancelar.isPending} onClick={() => cancelar.mutate()}>{t('Anular link')}</Botao>
                        )}
                    </div>
                </div>
            )}
            <AvisoDeErro erro={pedir.error ?? cancelar.error} />
        </section>
    );
}

/** O selo de cada linha — e, à oficina, os botões de registar a decisão à mão. */
export function SeloDaAprovacao({ l, id, podeEditar, aoMudar }: { l: LinhaDaOrdem; id: number; podeEditar: boolean; aoMudar: (m: string) => void }) {
    const decidir = useMutation({
        mutationFn: (decisao: LinhaDaOrdem['aprovacao']) => ordens.decidirLinha(id, l.id, decisao),
        onSuccess: (r) => aoMudar(r.message),
    });

    if (l.aprovacao === 'approved') {
        return l.aprovacao_por
            ? <span className="mt-0.5 block text-[11px] text-emerald-700"><i className="fas fa-circle-check mr-1" aria-hidden="true" />{t('Aprovada · :quem', { quem: l.aprovacao_por })}</span>
            : null;
    }

    const aEspera = l.aprovacao === 'pending';

    return (
        <span className="mt-1 flex flex-wrap items-center gap-1.5">
            <span className={cls('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset',
                aEspera ? 'bg-amber-100 text-amber-900 ring-amber-300' : 'bg-red-50 text-red-700 ring-red-200')}>
                <i className={cls('fas', aEspera ? 'fa-hourglass-half animate-pulse' : 'fa-ban')} aria-hidden="true" />
                {aEspera ? t('À espera do cliente') : t('Recusada')}
            </span>
            {!aEspera && l.aprovacao_por && <span className="text-[11px] text-slate-400">{l.aprovacao_por}</span>}
            {podeEditar && aEspera && (
                <>
                    <button type="button" disabled={decidir.isPending} onClick={() => decidir.mutate('approved')} title={t('Registar: o cliente aprovou')} aria-label={t('Registar: o cliente aprovou :linha', { linha: l.nome })}
                        className={cls('grid h-6 w-6 place-items-center rounded-full bg-emerald-500 text-[10px] text-white shadow hover:scale-110', TRANSICAO, FOCO)}>
                        <i className="fas fa-check" aria-hidden="true" />
                    </button>
                    <button type="button" disabled={decidir.isPending} onClick={() => decidir.mutate('declined')} title={t('Registar: o cliente recusou')} aria-label={t('Registar: o cliente recusou :linha', { linha: l.nome })}
                        className={cls('grid h-6 w-6 place-items-center rounded-full bg-red-500 text-[10px] text-white shadow hover:scale-110', TRANSICAO, FOCO)}>
                        <i className="fas fa-xmark" aria-hidden="true" />
                    </button>
                </>
            )}
            {podeEditar && !aEspera && (
                <button type="button" disabled={decidir.isPending} onClick={() => decidir.mutate('pending')} className={cls('text-[11px] font-semibold text-indigo-600 hover:underline', FOCO, RAIO)}>
                    {t('Voltar a propor')}
                </button>
            )}
        </span>
    );
}
