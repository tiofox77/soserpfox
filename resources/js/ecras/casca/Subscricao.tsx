import { useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { type PrazoDaSubscricao, casca } from '@/api/casca';
import { t } from '@/i18n';
import { FOCO, TRANSICAO, cls, dataHora } from '@/ui/tokens';

import { tom } from './comum';

/**
 * O CONTADOR DA SUBSCRIÇÃO — quanto falta, no topo de todas as páginas.
 *
 * O servidor diz quando acaba; o ecrã conta os minutos sozinho, e volta a
 * perguntar de minuto a minuto (o polling de 60 s do componente). Passar o
 * rato ou dar foco abre o detalhe — no Blade era só com o rato, e num tablet
 * não havia forma de o ver.
 */
export default function Subscricao() {
    const topo = useQuery({ queryKey: ['casca', 'topo'], queryFn: casca.topo, refetchInterval: 60_000, staleTime: 30_000 });
    const [, porTique] = useState(0);
    const [aberto, porAberto] = useState(false);

    useEffect(() => {
        const relogio = window.setInterval(() => porTique((n) => n + 1), 30_000);
        return () => window.clearInterval(relogio);
    }, []);

    const base = topo.data?.prazo;

    if (!base) return null;

    const p = recontar(base);
    const cor = tom(p.cor);
    const trial = p.tipo === 'trial';
    const rotulo = p.expirado ? t('Expirado!') : trial ? t('Trial') : p.tipo === 'licenca' ? t('Licença') : t('Plano');

    return (
        <div
            className="relative"
            onMouseEnter={() => porAberto(true)}
            onMouseLeave={() => porAberto(false)}
            onFocus={() => porAberto(true)}
            // Fecha só quando o foco sai da caixa: ir do botão para «Renovar»
            // não pode fechar o detalhe antes de a ligação receber o clique.
            onBlur={(e) => { if (!e.currentTarget.contains(e.relatedTarget as Node | null)) porAberto(false); }}
            onKeyDown={(e) => { if (e.key === 'Escape') porAberto(false); }}
        >
            <button
                type="button"
                onClick={() => porAberto(true)}
                aria-expanded={aberto}
                className={cls('flex items-center gap-2 rounded-xl border-2 bg-gradient-to-r px-3 py-2 text-white shadow-lg hover:scale-105', cor.gradiente, cor.borda, TRANSICAO, FOCO)}
            >
                <span className={cls('flex h-8 w-8 items-center justify-center rounded-lg bg-white/20 backdrop-blur-sm', p.expirado && 'animate-pulse')}>
                    <i className={cls('fas text-sm', p.expirado ? 'fa-triangle-exclamation' : trial ? 'fa-gift' : 'fa-clock')} aria-hidden="true" />
                </span>
                <span className="text-left">
                    <span className="block text-xs font-medium opacity-90">{rotulo}</span>
                    <span className="block text-lg font-bold leading-none tabular-nums">{p.expirado ? '0d' : p.resumo}</span>
                </span>
            </button>

            {aberto && (
                <div className="animate-scale-in absolute right-0 top-full z-50 w-80 origin-top-right pt-2">
                    <div className={cls('overflow-hidden rounded-2xl border-2 bg-white shadow-2xl', cor.borda)}>
                        <div className={cls('flex items-center justify-between bg-gradient-to-r px-4 py-3 text-white', cor.gradiente)}>
                            <span className="font-bold"><i className="fas fa-crown mr-2" aria-hidden="true" />{p.plano}</span>
                            <span className="text-xs uppercase opacity-75">{p.ciclo ?? 'monthly'}</span>
                        </div>
                        <div className={cls('p-4', cor.fundo)}>
                            {p.expirado ? (
                                <div className="py-4 text-center">
                                    <i className="fas fa-triangle-exclamation mb-3 text-4xl text-red-500" aria-hidden="true" />
                                    <p className="mb-2 text-lg font-bold text-red-700">{t('Subscription Expirada!')}</p>
                                    <p className="mb-4 text-sm text-red-600">{t('Renove seu plano para continuar usando o sistema.')}</p>
                                    <a href="/my-account?tab=plan" className="inline-block rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                        <i className="fas fa-arrow-up mr-2" aria-hidden="true" />{t('Renovar Agora')}
                                    </a>
                                </div>
                            ) : (
                                <>
                                    <div className="mb-4 text-center">
                                        <div className="mb-3 flex items-center justify-center gap-4">
                                            <Bloco valor={p.dias} rotulo={p.dias === 1 ? t('Dia') : t('Dias')} cor={cor.icone} />
                                            <Bloco valor={p.horas} rotulo={p.horas === 1 ? t('Hora') : t('Horas')} cor={cor.icone} />
                                            {p.dias === 0 && <Bloco valor={p.minutos} rotulo={t('Min.')} cor={cor.icone} />}
                                        </div>
                                        <p className="text-xs text-gray-600">
                                            <i className="fas fa-calendar mr-1" aria-hidden="true" />
                                            {trial ? <span className="font-semibold text-purple-600">{t('Trial termina em:')}</span> : t('Expira em:')}{' '}
                                            <strong>{dataHora(p.termina_em)}</strong>
                                        </p>
                                    </div>

                                    <div className={cls('mb-3 rounded-lg border-2 bg-white p-3', cor.borda)}>
                                        <p className={cls('text-sm font-semibold', cor.icone)}>
                                            {p.estado === 'critical' ? t('Atenção Urgente!') : p.estado === 'warning' ? t('Renovação Próxima') : p.estado === 'attention' ? t('Lembre-se de Renovar') : t('Tudo em Ordem')}
                                        </p>
                                        <p className="mt-1 text-xs text-gray-600">{frase(p.dias, trial)}</p>
                                    </div>

                                    {p.dias <= 15 && (
                                        <a href="/my-account?tab=plan" className={cls('block w-full rounded-lg bg-gradient-to-r px-4 py-2 text-center text-sm font-semibold text-white shadow-lg hover:scale-105', cor.gradiente, TRANSICAO)}>
                                            <i className={cls('fas mr-2', trial ? 'fa-star' : 'fa-arrow-up')} aria-hidden="true" />
                                            {trial ? t('Assinar Plano') : t('Renovar Subscription')}
                                        </a>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function Bloco({ valor, rotulo, cor }: { valor: number; rotulo: string; cor: string }) {
    return (
        <div className="min-w-[80px] rounded-lg bg-white p-3 shadow-md">
            <div className={cls('text-3xl font-bold tabular-nums', cor)}>{valor}</div>
            <div className="text-xs font-medium text-gray-500">{rotulo}</div>
        </div>
    );
}

function frase(dias: number, trial: boolean): string {
    if (dias <= 3) return trial ? t('Seu trial termina em breve! Assine um plano para continuar.') : t('Sua subscription expira em breve! Renove agora para evitar interrupções.');
    if (dias <= 7) return trial ? t('Seu trial está terminando em breve. Aproveite para conhecer todos os recursos!') : t('Planeje renovar sua subscription nos próximos dias.');
    if (dias <= 15) return trial ? t('Você está em período de trial. Explore todas as funcionalidades!') : t('Sua subscription está se aproximando da data de renovação.');
    return trial ? t('Aproveite seu período de trial grátis!') : t('Sua subscription está ativa e válida.');
}

/**
 * Refaz a conta com o relógio do aparelho, entre duas perguntas ao servidor:
 * no último dia o contador desce minuto a minuto em vez de ficar parado.
 */
function recontar(p: PrazoDaSubscricao): PrazoDaSubscricao {
    const ms = new Date(p.termina_em).getTime() - Date.now();

    if (Number.isNaN(ms) || p.dias >= 999) return p;
    if (ms <= 0) return { ...p, expirado: true, dias: 0, horas: 0, minutos: 0, resumo: '0d', estado: 'expired', cor: 'red' };

    const minutosTotais = Math.floor(ms / 60_000);
    const dias = Math.floor(minutosTotais / 1440);
    const horas = Math.floor((minutosTotais % 1440) / 60);
    const minutos = minutosTotais % 60;
    const resumo = dias >= 1 ? `${dias}d ${horas}h` : horas >= 1 ? `${horas}h ${minutos}m` : `${minutos}m`;
    const [cor, estado] = dias <= 3 ? ['red', 'critical'] as const : dias <= 7 ? ['orange', 'warning'] as const : dias <= 15 ? ['yellow', 'attention'] as const : ['green', 'good'] as const;

    return { ...p, dias, horas, minutos, resumo, cor, estado };
}
