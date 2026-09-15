import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { avaliacaoPeloLink } from '@/api/oficina';
import { t } from '@/i18n';
import { Carregando } from '@/ui/Carregando';
import { cascata } from '@/ui/SemNada';
import { FOCO, RAIO, RAIO_GRANDE, TRANSICAO, cls, data } from '@/ui/tokens';

import { ChapaDaMatricula } from './ChapaDaMatricula';

/**
 * A AVALIAÇÃO DO SERVIÇO, PELO CLIENTE (15/09/2026, OF-14).
 *
 * A página do link que chega depois da entrega: sem conta, trinta segundos —
 * as estrelas, se recomendaria e uma frase. Uma resposta só.
 */

const PALAVRAS = ['', 'Muito mau', 'Mau', 'Razoável', 'Bom', 'Excelente'];

export function Estrelas({ nota, tamanho = 'text-base' }: { nota: number; tamanho?: string }) {
    return (
        <span className={cls('inline-flex gap-0.5', tamanho)} role="img" aria-label={t(':n de 5 estrelas', { n: nota })}>
            {[1, 2, 3, 4, 5].map((e) => <i key={e} className={cls('fas fa-star', e <= nota ? 'text-amber-400' : 'text-slate-200')} aria-hidden="true" />)}
        </span>
    );
}

export default function AvaliarServico({ token }: { token: string }) {
    const q = useQuery({ queryKey: ['avaliacao-pelo-link', token], queryFn: () => avaliacaoPeloLink.ver(token), retry: false });
    const [nota, porNota] = useState(0);
    const [sobre, porSobre] = useState(0);
    const [recomenda, porRecomenda] = useState<boolean | null>(null);
    const [comentario, porComentario] = useState('');

    const enviar = useMutation({
        mutationFn: () => avaliacaoPeloLink.responder(token, { nota, recomenda, comentario }),
        meta: { aviso: false },
    });

    const casca = (conteudo: React.ReactNode) => (
        <div className="min-h-screen bg-gradient-to-b from-amber-50 via-white to-slate-50 px-4 py-6 sm:py-10">
            <div className="mx-auto w-full max-w-lg space-y-4">{conteudo}</div>
            <p className="mt-8 text-center text-xs text-slate-400"><i className="fas fa-lock mr-1" aria-hidden="true" />{t('Página segura da oficina · soserp')}</p>
        </div>
    );

    if (q.isPending) return casca(<Carregando linhas={5} />);
    if (q.isError) {
        return casca(
            <Cartaz icone="fa-link-slash" tom="from-red-500 to-rose-600" titulo={t('Link inválido')}
                frase={q.error instanceof ErroDaApi && q.error.estado === 429 ? t('Demasiadas tentativas. Espere um minuto e volte a abrir o link.') : t('Este link não existe. Peça um novo à oficina.')} />,
        );
    }

    const d = q.data;
    const feita = enviar.isSuccess ? { nota, recomenda, comentario } : d.resposta;

    if (feita) {
        return casca(
            <Cartaz icone="fa-heart" tom="from-amber-400 to-orange-500" titulo={t('Obrigado pela sua avaliação!')}
                frase={t('A sua opinião ajuda a :empresa a fazer melhor.', { empresa: d.empresa.nome ?? '' })}>
                <div className="mt-4 flex justify-center"><Estrelas nota={feita.nota} tamanho="text-3xl" /></div>
                {feita.comentario && <p className="mt-3 text-sm italic text-slate-600">«{feita.comentario}»</p>}
                {d.empresa.telefone && <p className="mt-4 text-sm text-slate-500">{d.empresa.nome} · <a href={`tel:${d.empresa.telefone}`} className="font-semibold text-indigo-700">{d.empresa.telefone}</a></p>}
            </Cartaz>,
        );
    }

    const acesa = sobre || nota;
    const erros = enviar.error instanceof ErroDaApi ? Object.values(enviar.error.erros ?? {}).flat() : [];

    return casca(
        <>
            <header className={cls('animate-fade-in overflow-hidden bg-gradient-to-br from-slate-800 via-slate-900 to-indigo-950 p-5 text-white shadow-xl', RAIO_GRANDE)}>
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-amber-200">{t('Avalie o serviço')}</p>
                <h1 className="mt-1 text-2xl font-bold">{d.empresa.nome}</h1>
                <div className="mt-4 flex flex-wrap items-center gap-4">
                    {d.ordem.matricula && <ChapaDaMatricula matricula={d.ordem.matricula} />}
                    <div className="min-w-0">
                        <p className="font-semibold">{d.ordem.viatura}</p>
                        <p className="text-sm text-white/70">
                            <span className="font-mono">{d.ordem.numero}</span>
                            {d.ordem.entregue_em && ` · ${t('entregue a :data', { data: data(d.ordem.entregue_em) })}`}
                        </p>
                    </div>
                </div>
                {d.ordem.servicos.length > 0 && (
                    <ul className="mt-4 flex flex-wrap gap-1.5">
                        {d.ordem.servicos.slice(0, 6).map((s) => (
                            <li key={s} className="rounded-full bg-white/10 px-2.5 py-1 text-xs text-white/90"><i className="fas fa-screwdriver-wrench mr-1 text-white/50" aria-hidden="true" />{s}</li>
                        ))}
                    </ul>
                )}
            </header>

            <section style={cascata(1)} className={cls('entra border border-slate-200 bg-white p-5 text-center shadow-sm', RAIO_GRANDE)}>
                <h2 className="text-lg font-bold text-slate-900">
                    {d.ordem.mecanico ? t('Como foi o trabalho do :mecanico e da equipa?', { mecanico: d.ordem.mecanico }) : t('Como correu o serviço?')}
                </h2>
                <div className="mt-4 flex justify-center gap-2" role="radiogroup" aria-label={t('Nota de 1 a 5')} onMouseLeave={() => porSobre(0)}>
                    {[1, 2, 3, 4, 5].map((e) => (
                        <button key={e} type="button" role="radio" aria-checked={nota === e} aria-label={t(PALAVRAS[e] ?? '')}
                            onMouseEnter={() => porSobre(e)} onFocus={() => porSobre(e)} onBlur={() => porSobre(0)} onClick={() => porNota(e)}
                            className={cls('grid h-12 w-12 place-items-center text-4xl transition-all duration-200 sm:h-14 sm:w-14 sm:text-5xl', FOCO, RAIO,
                                e <= acesa ? 'scale-110 text-amber-400 drop-shadow-[0_4px_8px_rgba(251,191,36,0.45)]' : 'text-slate-200 hover:text-amber-200',
                                nota === e && 'scale-125')}>
                            <i className="fas fa-star" aria-hidden="true" />
                        </button>
                    ))}
                </div>
                <p className={cls('mt-2 h-6 text-sm font-bold transition-colors', acesa >= 4 ? 'text-emerald-600' : acesa >= 3 ? 'text-amber-600' : acesa ? 'text-red-600' : 'text-slate-400')}>
                    {acesa ? t(PALAVRAS[acesa] ?? '') : t('Toque nas estrelas')}
                </p>
            </section>

            <section style={cascata(2)} className={cls('entra border border-slate-200 bg-white p-5 shadow-sm', RAIO_GRANDE)}>
                <h2 className="text-center text-sm font-bold text-slate-800">{t('Recomendaria a oficina a um amigo?')}</h2>
                <div className="mt-3 grid grid-cols-2 gap-3">
                    {([[true, t('Sim'), 'fa-thumbs-up', 'border-emerald-400 bg-emerald-50 text-emerald-700'], [false, t('Não'), 'fa-thumbs-down', 'border-red-300 bg-red-50 text-red-700']] as const).map(([valor, rotulo, icone, tom]) => (
                        <button key={rotulo} type="button" aria-pressed={recomenda === valor} onClick={() => porRecomenda(recomenda === valor ? null : valor)}
                            className={cls('flex items-center justify-center gap-2 border-2 px-4 py-3 text-sm font-bold', RAIO, TRANSICAO, FOCO,
                                recomenda === valor ? cls(tom, 'scale-[1.02] shadow') : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50')}>
                            <i className={cls('fas text-lg', icone, recomenda === valor && 'animate-pulse')} aria-hidden="true" />{rotulo}
                        </button>
                    ))}
                </div>
                <label className="mt-4 block text-sm">
                    <span className="mb-1 block font-medium text-slate-700">{t('Quer dizer mais alguma coisa? (opcional)')}</span>
                    <textarea rows={3} maxLength={1000} value={comentario} onChange={(ev) => porComentario(ev.target.value)}
                        placeholder={t('O que correu bem, o que podemos melhorar…')} className={cls('w-full border border-slate-300 px-3 py-2 text-sm', RAIO, FOCO)} />
                </label>
            </section>

            {erros.length > 0 && <p className="text-center text-sm text-red-700">{erros[0]}</p>}
            {enviar.error && !(enviar.error instanceof ErroDaApi && erros.length) && <p className="text-center text-sm text-red-700">{enviar.error.message}</p>}

            <button type="button" disabled={nota === 0 || enviar.isPending} onClick={() => enviar.mutate()}
                className={cls('flex w-full items-center justify-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 px-5 py-4 text-base font-bold text-white shadow-lg', RAIO_GRANDE, TRANSICAO, FOCO,
                    'hover:-translate-y-0.5 hover:shadow-xl active:translate-y-0 disabled:translate-y-0 disabled:opacity-50 disabled:shadow-none')}>
                <i className={cls('fas', enviar.isPending ? 'fa-spinner fa-spin' : 'fa-paper-plane')} aria-hidden="true" />
                {t('Enviar avaliação')}
            </button>
        </>,
    );
}

function Cartaz({ icone, tom, titulo, frase, children }: { icone: string; tom: string; titulo: string; frase: string; children?: React.ReactNode }) {
    return (
        <div className={cls('animate-scale-in border border-slate-200 bg-white p-8 text-center shadow-lg', RAIO_GRANDE)}>
            <span className={cls('mx-auto grid h-16 w-16 place-items-center rounded-full bg-gradient-to-br text-2xl text-white shadow-lg', tom)}>
                <i className={cls('fas', icone)} aria-hidden="true" />
            </span>
            <h1 className="mt-4 text-xl font-bold text-slate-900">{titulo}</h1>
            <p className="mt-1 text-slate-600">{frase}</p>
            {children}
        </div>
    );
}
