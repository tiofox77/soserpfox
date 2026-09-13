import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useRef, useState } from 'react';

import { type NotificacaoDoSino, casca } from '@/api/casca';
import { t } from '@/i18n';
import { FOCO, TRANSICAO, cls } from '@/ui/tokens';

import { tom, useFecharFora } from './comum';

/**
 * O SINO — as notificações guardadas e as que o sistema calcula na hora.
 *
 * O número e a lista vêm no mesmo pedido, de minuto a minuto — o polling de
 * 60 s do componente. As notificações do sistema contam-se pelo saldo e pelo
 * mesmo prazo do contador (ver NotificacoesDoSistema).
 *
 * «Limpar todas» pergunta antes, como no Blade; e há o filtro «só por ler»,
 * que o componente tinha e o ecrã nunca mostrou.
 */
export default function Notificacoes() {
    const fila = useQueryClient();
    const [aberto, porAberto] = useState(false);
    const [soPorLer, porSoPorLer] = useState(true);
    const [aConfirmar, porAConfirmar] = useState(false);
    const caixa = useRef<HTMLDivElement>(null);
    const fechar = useCallback(() => { porAberto(false); porAConfirmar(false); }, []);

    useFecharFora(caixa, aberto, fechar);

    const lista = useQuery({
        queryKey: ['casca', 'notificacoes', soPorLer],
        queryFn: () => casca.notificacoes(soPorLer),
        refetchInterval: 60_000,
        staleTime: 30_000,
    });

    const refrescar = () => void fila.invalidateQueries({ queryKey: ['casca', 'notificacoes'] });

    const lida = useMutation({ mutationFn: (id: string) => casca.marcarComoLida(id), onSuccess: refrescar });
    const todasLidas = useMutation({ mutationFn: casca.marcarTodasComoLidas, onSuccess: refrescar });
    const apagar = useMutation({ mutationFn: (id: string) => casca.apagarNotificacao(id), onSuccess: refrescar });
    const limpar = useMutation({ mutationFn: casca.limparNotificacoes, onSuccess: () => { porAConfirmar(false); refrescar(); } });

    const porLer = lista.data?.por_ler ?? 0;
    const itens = lista.data?.notificacoes ?? [];

    const abrirLigacao = (n: NotificacaoDoSino) => {
        if (n.da_base && !n.lida && n.id) lida.mutate(n.id);
        porAberto(false);
    };

    return (
        <div ref={caixa} className="relative">
            <button
                type="button"
                onClick={() => porAberto((a) => !a)}
                aria-expanded={aberto}
                aria-haspopup="dialog"
                className={cls('relative grid h-10 w-10 place-items-center rounded-xl text-gray-600 hover:bg-gray-100 hover:text-gray-900', TRANSICAO, FOCO)}
            >
                <i className="fas fa-bell text-xl" aria-hidden="true" />
                <span className="sr-only">{t('Notificações')}</span>
                {porLer > 0 && (
                    <span className="absolute -right-1 -top-1 flex h-5 w-5 animate-pulse items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">
                        {porLer > 9 ? '9+' : porLer}
                    </span>
                )}
            </button>

            {aberto && (
                <div role="dialog" aria-label={t('Notificações')} className="animate-scale-in absolute right-0 z-50 mt-2 w-96 max-w-[calc(100vw-1.5rem)] origin-top-right overflow-hidden rounded-2xl border-2 border-gray-200 bg-white shadow-2xl">
                    <div className="bg-gradient-to-r from-blue-600 to-purple-600 px-4 py-3">
                        <div className="mb-2 flex items-center justify-between">
                            <h3 className="text-lg font-bold text-white"><i className="fas fa-bell mr-2" aria-hidden="true" />{t('Notificações')}</h3>
                            <div className="flex items-center gap-2">
                                <span className="rounded-full bg-white/20 px-2 py-1 text-sm text-white tabular-nums">{porLer}</span>
                                <button type="button" onClick={() => void lista.refetch()} title={t('Atualizar')} className={cls('text-white/80 hover:text-white', FOCO)}>
                                    <i className={cls('fas fa-rotate', lista.isFetching && 'fa-spin')} aria-hidden="true" />
                                    <span className="sr-only">{t('Atualizar')}</span>
                                </button>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-3 text-xs">
                            <button type="button" onClick={() => porSoPorLer((v) => !v)} className={cls('flex items-center text-white/80 hover:text-white', FOCO)} aria-pressed={soPorLer}>
                                <i className={cls('fas mr-1', soPorLer ? 'fa-filter' : 'fa-list')} aria-hidden="true" />{soPorLer ? t('Só por ler') : t('Todas')}
                            </button>
                            {porLer > 0 && (
                                <button type="button" onClick={() => todasLidas.mutate()} disabled={todasLidas.isPending} className={cls('flex items-center text-white/80 hover:text-white', FOCO)}>
                                    <i className="fas fa-check-double mr-1" aria-hidden="true" />{t('Marcar como lidas')}
                                </button>
                            )}
                            {itens.length > 0 && (
                                aConfirmar ? (
                                    <span className="flex items-center gap-2 rounded-lg bg-white/15 px-2 py-1 text-white">
                                        {t('Limpar todas as notificações?')}
                                        <button type="button" onClick={() => limpar.mutate()} className="font-bold underline">{t('Sim')}</button>
                                        <button type="button" onClick={() => porAConfirmar(false)} className="opacity-80">{t('Não')}</button>
                                    </span>
                                ) : (
                                    <button type="button" onClick={() => porAConfirmar(true)} className={cls('flex items-center text-white/80 hover:text-white', FOCO)}>
                                        <i className="fas fa-trash-can mr-1" aria-hidden="true" />{t('Limpar todas')}
                                    </button>
                                )
                            )}
                        </div>
                    </div>

                    <div className="max-h-96 overflow-y-auto">
                        {lista.isPending ? (
                            <div className="space-y-2 p-4">{[0, 1, 2].map((i) => <div key={i} className="h-14 animate-pulse rounded-xl bg-slate-100" />)}</div>
                        ) : itens.length === 0 ? (
                            <div className="p-8 text-center">
                                <i className="fas fa-bell-slash mb-3 text-5xl text-gray-300" aria-hidden="true" />
                                <p className="font-medium text-gray-500">{t('Sem notificações')}</p>
                                <p className="text-sm text-gray-400">{t('Você está em dia!')}</p>
                            </div>
                        ) : itens.map((n, i) => {
                            const c = tom(n.cor);

                            return (
                                <div key={n.id ?? `s-${i}`} className={cls('entra border-b border-gray-100 p-4', c.fundo, TRANSICAO, n.lida && 'opacity-70')}>
                                    <div className="flex items-start gap-3">
                                        <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-white shadow-sm">
                                            <i className={cls('fas text-lg', n.icone, c.icone)} aria-hidden="true" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <a href={n.ligacao ?? '#'} onClick={() => abrirLigacao(n)} className="block hover:underline">
                                                <span className="mb-1 flex items-center justify-between">
                                                    <span className="truncate pr-2 text-sm font-bold text-gray-900">
                                                        {n.titulo}
                                                        {!n.lida && <span className="ml-1 inline-block h-2 w-2 rounded-full bg-blue-500" aria-label={t('Por ler')} />}
                                                    </span>
                                                    <span className="whitespace-nowrap text-xs text-gray-500">{n.quando}</span>
                                                </span>
                                                <span className="line-clamp-2 block text-sm text-gray-700">{n.mensagem}</span>
                                            </a>
                                            {n.da_base && n.id && (
                                                <div className="mt-2 flex items-center gap-3">
                                                    {!n.lida && (
                                                        <button type="button" onClick={() => lida.mutate(n.id as string)} className="flex items-center text-xs text-gray-600 hover:text-blue-600">
                                                            <i className="fas fa-check mr-1" aria-hidden="true" />{t('Marcar como lida')}
                                                        </button>
                                                    )}
                                                    <button type="button" onClick={() => apagar.mutate(n.id as string)} className="flex items-center text-xs text-gray-600 hover:text-red-600">
                                                        <i className="fas fa-trash mr-1" aria-hidden="true" />{t('Excluir')}
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {itens.length > 0 && (
                        <div className="border-t border-gray-200 bg-gray-50 px-4 py-3">
                            <a href="/my-account" className="block text-center text-sm font-semibold text-blue-600 hover:text-blue-700" onClick={() => porAberto(false)}>
                                <i className="fas fa-gear mr-2" aria-hidden="true" />{t('Ver Configurações')}
                            </a>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
