import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { type MensagemDaPlataforma, casca } from '@/api/casca';
import { t } from '@/i18n';
import { FOCO, TRANSICAO, cls } from '@/ui/tokens';

import { tom } from './comum';

/**
 * AS MENSAGENS DO DONO DA PLATAFORMA, em todas as páginas.
 *
 * Duas formas, e a diferença importa: a BARRA fica no topo e deixa trabalhar;
 * o POP-UP interrompe, e só um de cada vez — duas caixas a interromper ao mesmo
 * tempo não se leem, fecham-se ambas. Uma mensagem que não se dispensa não tem
 * cruz, nem fecha com Escape, nem clicando fora.
 *
 * Dispensar some logo do ecrã, sem esperar pelo servidor.
 */
export default function Mensagens() {
    const fila = useQueryClient();
    const [fora, porFora] = useState<number[]>([]);

    const pedido = useQuery({ queryKey: ['casca', 'mensagens'], queryFn: casca.mensagens, staleTime: 60_000 });

    const dispensar = useMutation({
        mutationFn: (id: number) => casca.dispensar(id),
        onMutate: (id) => porFora((f) => [...f, id]),
        onSettled: () => void fila.invalidateQueries({ queryKey: ['casca', 'avisos'] }),
    });

    const mensagens = (pedido.data?.mensagens ?? []).filter((m) => !fora.includes(m.id));
    const barras = mensagens.filter((m) => m.forma === 'barra');
    const popup = mensagens.find((m) => m.forma === 'popup');

    if (mensagens.length === 0) return null;

    return (
        <>
            {barras.map((m) => <Barra key={m.id} m={m} aoDispensar={() => dispensar.mutate(m.id)} />)}
            {popup && <Popup m={popup} aoDispensar={() => dispensar.mutate(popup.id)} />}
        </>
    );
}

function Barra({ m, aoDispensar }: { m: MensagemDaPlataforma; aoDispensar: () => void }) {
    const c = tom(m.cor);

    return (
        <div role="status" className={cls('entra mb-3 flex items-start gap-3 rounded-xl border-l-4 px-4 py-3 shadow-sm', c.borda, c.fundo)}>
            <i className={cls('fas mt-0.5 icon-float', m.icone, c.icone)} aria-hidden="true" />
            <div className="min-w-0 flex-1">
                <p className={cls('font-bold', c.texto)}>{m.titulo}</p>
                <p className={cls('whitespace-pre-line text-sm opacity-90', c.texto)}>{m.corpo}</p>
                {m.ligacao && (
                    <a href={m.ligacao} target="_blank" rel="noopener noreferrer" className={cls('mt-1 inline-block text-sm font-semibold underline', c.icone)}>
                        {m.texto_da_ligacao || t('Saber mais')}
                    </a>
                )}
            </div>
            {m.dispensavel && (
                <button type="button" onClick={aoDispensar} title={t('Dispensar')} className={cls('shrink-0 hover:scale-110', c.icone, TRANSICAO, FOCO)}>
                    <i className="fas fa-xmark" aria-hidden="true" />
                    <span className="sr-only">{t('Dispensar')}</span>
                </button>
            )}
        </div>
    );
}

function Popup({ m, aoDispensar }: { m: MensagemDaPlataforma; aoDispensar: () => void }) {
    const c = tom(m.cor);

    useEffect(() => {
        if (!m.dispensavel) return;
        const tecla = (e: KeyboardEvent) => { if (e.key === 'Escape') aoDispensar(); };
        document.addEventListener('keydown', tecla);
        return () => document.removeEventListener('keydown', tecla);
    }, [m.dispensavel, aoDispensar]);

    return (
        <div className="fixed inset-0 z-[60] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby={`mensagem-${m.id}`}>
            <div className="flex min-h-screen items-center justify-center px-4 py-6">
                <div className="animate-fade-in fixed inset-0 bg-black/50 backdrop-blur-sm" onClick={m.dispensavel ? aoDispensar : undefined} aria-hidden="true" />
                <div className="animate-scale-in relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div className={cls('flex items-center justify-between px-6 py-4', c.solido.split(' ')[0])}>
                        <h3 id={`mensagem-${m.id}`} className="flex items-center gap-2 text-lg font-bold text-white">
                            <i className={cls('fas icon-float', m.icone)} aria-hidden="true" />{m.titulo}
                        </h3>
                        {m.dispensavel && (
                            <button type="button" onClick={aoDispensar} title={t('Fechar')} className={cls('text-white/80 hover:text-white', FOCO)}>
                                <i className="fas fa-xmark text-xl" aria-hidden="true" />
                                <span className="sr-only">{t('Fechar')}</span>
                            </button>
                        )}
                    </div>
                    <div className="p-6">
                        <p className="whitespace-pre-line leading-relaxed text-gray-700">{m.corpo}</p>
                    </div>
                    <div className="flex items-center justify-end gap-3 bg-gray-50 px-6 py-4">
                        {m.ligacao && (
                            <a href={m.ligacao} target="_blank" rel="noopener noreferrer" className={cls('rounded-xl px-5 py-2.5 font-semibold text-white', c.solido, TRANSICAO)}>
                                {m.texto_da_ligacao || t('Saber mais')}
                            </a>
                        )}
                        {m.dispensavel ? (
                            <button type="button" onClick={aoDispensar} className={cls('rounded-xl border-2 border-gray-300 px-5 py-2.5 font-semibold text-gray-700 hover:bg-gray-100', TRANSICAO, FOCO)}>
                                {t('Entendido')}
                            </button>
                        ) : (
                            <span className="text-xs text-gray-500"><i className="fas fa-lock mr-1" aria-hidden="true" />{t('Esta mensagem não pode ser dispensada.')}</span>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
