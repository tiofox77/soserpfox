import { useMutation, useQuery } from '@tanstack/react-query';
import { useCallback, useRef, useState } from 'react';

import { casca } from '@/api/casca';
import { t } from '@/i18n';
import { FOCO, TRANSICAO, cls } from '@/ui/tokens';

import { useFecharFora } from './comum';

/**
 * A EMPRESA ACTIVA E A TROCA DE EMPRESA — no topo de todas as páginas.
 *
 * A troca TAPA o ecrã no clique e fica tapada até a página nova chegar: com a
 * página anterior à vista e os botões vivos, um clique nessa meia janela
 * pedia ao servidor um registo que já não é da empresa activa. E aterra em
 * casa, com uma página nova — recarregar a mesma podia cair num registo que
 * na empresa nova não existe.
 */
export default function Empresa() {
    const [aberto, porAberto] = useState(false);
    const [aTrocar, porATrocar] = useState(false);
    const caixa = useRef<HTMLDivElement>(null);
    const fechar = useCallback(() => porAberto(false), []);

    useFecharFora(caixa, aberto, fechar);

    const topo = useQuery({ queryKey: ['casca', 'topo'], queryFn: casca.topo, refetchInterval: 60_000, staleTime: 30_000 });

    const entrar = useMutation({
        mutationFn: (id: number) => casca.entrarNaEmpresa(id),
        onMutate: () => { porATrocar(true); porAberto(false); },
        onSuccess: (r) => { window.location.assign(r.ir_para); },
        onError: () => porATrocar(false),
    });

    const e = topo.data?.empresa;

    if (!e?.activa) return null;

    return (
        <div ref={caixa} className="relative">
            {aTrocar && (
                <div className="fixed inset-0 z-[9999] flex cursor-wait items-center justify-center bg-white/85 backdrop-blur-sm" role="status">
                    <div className="text-center">
                        <i className="fas fa-circle-notch fa-spin text-3xl text-indigo-600" aria-hidden="true" />
                        <p className="mt-3 text-sm font-semibold text-gray-700">{t('A mudar de empresa…')}</p>
                    </div>
                </div>
            )}

            <button
                type="button"
                onClick={() => porAberto((a) => !a)}
                aria-expanded={aberto}
                aria-haspopup="menu"
                className={cls('relative flex items-center gap-3 rounded-xl border-2 bg-white px-4 py-2.5 hover:border-blue-400 hover:shadow-md', TRANSICAO, FOCO,
                    e.excedido ? 'border-red-400 ring-2 ring-red-200' : 'border-gray-200')}
            >
                {e.excedido && (
                    <span className="absolute -right-2 -top-2 flex h-6 w-6 animate-pulse items-center justify-center rounded-full bg-red-500 shadow-lg">
                        <i className="fas fa-exclamation text-xs text-white" aria-hidden="true" />
                    </span>
                )}
                <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-purple-600">
                    <i className="fas fa-building text-lg text-white" aria-hidden="true" />
                </span>
                <span className="text-left">
                    <span className={cls('block text-xs font-medium', e.excedido ? 'text-red-600' : 'text-gray-500')}>
                        {e.excedido ? t('Limite Excedido') : t('Empresa Ativa')}
                    </span>
                    <span className="block max-w-[14rem] truncate text-sm font-bold text-gray-900">{e.activa.nome}</span>
                </span>
                <i className={cls('fas fa-chevron-down text-gray-400 transition-transform', aberto && 'rotate-180')} aria-hidden="true" />
            </button>

            {aberto && (
                <div role="menu" className="animate-scale-in absolute right-0 z-50 mt-2 w-80 origin-top-right overflow-hidden rounded-2xl border-2 border-gray-200 bg-white shadow-2xl">
                    {e.excedido && (
                        <div className="border-b-2 border-red-600 bg-gradient-to-r from-red-500 to-orange-500 px-4 py-3 text-white">
                            <p className="mb-1 text-sm font-bold"><i className="fas fa-triangle-exclamation mr-2 animate-pulse" aria-hidden="true" />{t('Limite Excedido')}</p>
                            <p className="text-xs leading-relaxed">
                                {t('Você está gerenciando :n empresas, mas seu plano permite apenas :max.', { n: e.contagem, max: e.maximo ?? 0 })}
                            </p>
                            <p className="mt-2 text-xs text-yellow-100"><i className="fas fa-circle-info mr-1" aria-hidden="true" />{t('Faça upgrade do plano para continuar usando todas as empresas.')}</p>
                        </div>
                    )}

                    <div className="bg-gradient-to-r from-blue-500 to-purple-600 px-4 py-3 text-white">
                        <p className="text-xs font-medium opacity-90">{e.empresas.length > 1 ? t('Suas Empresas') : t('Empresa Ativa')}</p>
                        <p className="text-sm font-bold">
                            {e.empresas.length > 1 ? t(':n empresa(s) disponível(is)', { n: e.empresas.length }) : t(':n empresa(s) cadastrada', { n: e.empresas.length })}
                        </p>
                    </div>

                    <div className="max-h-80 overflow-y-auto">
                        {e.empresas.map((x) => {
                            const activa = x.id === e.activa?.id;

                            return (
                                <button
                                    key={x.id}
                                    type="button"
                                    role="menuitem"
                                    disabled={entrar.isPending}
                                    onClick={() => (activa ? porAberto(false) : entrar.mutate(x.id))}
                                    className={cls('flex w-full items-center gap-3 border-b border-gray-100 px-4 py-4 text-left last:border-b-0', TRANSICAO, activa ? 'bg-blue-50' : 'hover:bg-gray-50')}
                                >
                                    <span className={cls('flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br', activa ? 'from-blue-500 to-purple-600' : 'from-gray-400 to-gray-500')}>
                                        <i className="fas fa-building text-white" aria-hidden="true" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-bold text-gray-900">{x.nome}</span>
                                        <span className="block text-xs text-gray-500">NIF: {x.nif ?? 'N/A'}</span>
                                        {x.papel && <span className="mt-1 block text-xs text-blue-600"><i className="fas fa-user-tag mr-1" aria-hidden="true" />{x.papel}</span>}
                                    </span>
                                    <i className={cls('fas flex-shrink-0', activa ? 'fa-circle-check text-2xl text-blue-500' : 'fa-arrow-right text-lg text-gray-300')} aria-hidden="true" />
                                </button>
                            );
                        })}
                    </div>

                    {entrar.error && <p role="alert" className="bg-red-50 px-4 py-2 text-xs font-semibold text-red-700">{entrar.error.message}</p>}

                    <div className="border-t border-gray-200 bg-gray-50 px-4 py-3">
                        {e.empresas.length > 1 ? (
                            <p className="text-center text-xs text-gray-600"><i className="fas fa-circle-info mr-1" aria-hidden="true" />{t('Clique em uma empresa para alternar')}</p>
                        ) : (
                            <a href="/my-account?tab=companies" className="block text-center text-xs font-medium text-blue-600 hover:text-blue-700">
                                <i className="fas fa-circle-plus mr-1" aria-hidden="true" />{t('Adicionar mais empresas')}
                            </a>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
