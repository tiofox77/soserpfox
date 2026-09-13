import { useMemo, useState } from 'react';

import { t, tn } from '@/i18n';

import { usePwa } from '../contexto';
import { useAssentado, useBaseViva, useEstadoDoMotor } from '../ganchos';
import { db, type Cliente } from '../motor/base';

/**
 * OS CLIENTES DO APARELHO — os que vieram do servidor e os criados aqui sem rede.
 *
 * Consulta viva à base local: um cliente criado sem rede aparece com
 * «PENDENTE» e perde a marca sozinho quando sobe (o `adoptarIdDoServidor` do
 * motor escreve `_synced`). O Alpine relia a tabela a cada `pwa:synced`.
 */

/** Quantos se desenham de uma vez — uma empresa com milhares de clientes não pode travar o telemóvel. */
const LIMITE = 200;

/** Aceita valores canónicos (pessoa_juridica) e legados (empresa). */
export function eEmpresa(c: Cliente): boolean {
    return c.type === 'pessoa_juridica' || c.type === 'empresa' || c.type === 'juridica';
}

/** «Maria dos Santos» → «MD». */
export function iniciais(nome: unknown): string {
    return String(nome || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0] ?? '').join('').toUpperCase() || '?';
}

export function Clientes() {
    const { rotas } = usePwa();
    const motor = useEstadoDoMotor();

    // `null` até a primeira leitura voltar: «ainda a ler» não é «não há clientes».
    const clientes = useBaseViva<Cliente[] | null>(() => db.clients.toArray(), [], null);

    const [pesquisa, setPesquisa] = useState('');
    const assentada = useAssentado(pesquisa, 150);

    const lista = clientes ?? [];
    const porSincronizar = useMemo(() => lista.filter((c) => !c._synced).length, [lista]);

    const encontrados = useMemo(() => {
        const s = assentada.toLowerCase().trim();
        if (!s) return lista;

        return lista.filter((c) => String(c.name || '').toLowerCase().includes(s) || String(c.nif || '').toLowerCase().includes(s));
    }, [lista, assentada]);

    const visiveis = encontrados.slice(0, LIMITE);

    return (
        <div className="-mx-1">
            {/* Cabeçalho */}
            <div className="pwa-entra relative overflow-hidden bg-gradient-to-br from-cyan-600 to-blue-700 text-white rounded-2xl shadow-lg p-4 mb-3">
                <i className="fas fa-address-book absolute -right-2 -bottom-4 text-7xl opacity-10 pwa-flutua" aria-hidden="true" />
                <div className="relative flex items-center justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-xl font-bold flex items-center gap-2">
                            <i className="fas fa-users" aria-hidden="true" />{t('Clientes')}
                        </h1>
                        <p className="text-xs opacity-90 mt-0.5">
                            {tn(':n cliente neste aparelho|:n clientes neste aparelho', lista.length, { n: lista.length })}
                            {' · '}
                            <span className="font-bold">{t(':n por sincronizar', { n: porSincronizar })}</span>
                            {motor.syncing && porSincronizar > 0 && (
                                <span className="ml-1.5">
                                    <i className="fas fa-rotate fa-spin" aria-hidden="true" />
                                    <span className="sr-only">{t('A sincronizar…')}</span>
                                </span>
                            )}
                        </p>
                    </div>
                    <a href={rotas.novoCliente}
                       className="pwa-toque shrink-0 bg-white/20 hover:bg-white/30 backdrop-blur px-3 py-2 rounded-xl text-sm font-bold transition">
                        <i className="fas fa-plus mr-1" aria-hidden="true" />{t('Novo')}
                    </a>
                </div>
            </div>

            {/* Pesquisa */}
            <div className="sticky top-[60px] z-30 bg-slate-50/95 backdrop-blur py-2 mb-1 px-1">
                <label htmlFor="clientes-pesquisa" className="sr-only">{t('Pesquisar por nome ou NIF…')}</label>
                <div className="flex items-center gap-2 bg-white rounded-2xl shadow-sm px-3 h-12 border-2 border-transparent focus-within:border-cyan-500 transition">
                    <i className="fas fa-magnifying-glass text-slate-400" aria-hidden="true" />
                    <input id="clientes-pesquisa" name="pesquisa" type="search" value={pesquisa} onChange={(e) => setPesquisa(e.target.value)}
                           placeholder={t('Pesquisar por nome ou NIF…')} autoComplete="off"
                           className="flex-1 min-w-0 bg-transparent text-sm focus:outline-none" />
                    {pesquisa && (
                        <button type="button" onClick={() => setPesquisa('')} aria-label={t('Limpar pesquisa')}
                                className="pwa-toque text-slate-400 hover:text-slate-600 text-xl leading-none px-1">&times;</button>
                    )}
                </div>
            </div>

            {/* Lista */}
            {clientes === null ? (
                <div className="space-y-2 px-1" aria-busy="true">
                    {[0, 1, 2, 3].map((i) => (
                        <div key={i} className="bg-white rounded-2xl shadow-sm p-3 h-[68px] animate-pulse" />
                    ))}
                </div>
            ) : (
                <div className="space-y-2 px-1">
                    {visiveis.map((c, i) => {
                        const empresa = eEmpresa(c);
                        const contacto = c.email || c.phone || c.mobile;

                        return (
                            <div key={String(c.id)}
                                 className="pwa-entra pwa-cartao bg-white rounded-2xl shadow-sm p-3 flex items-center gap-3 border border-slate-100"
                                 style={{ animationDelay: `${Math.min(i, 12) * 25}ms` }}>
                                <div className={`w-11 h-11 rounded-full flex items-center justify-center text-white font-bold text-sm shrink-0 bg-gradient-to-br shadow-sm ${empresa ? 'from-blue-500 to-blue-600' : 'from-purple-500 to-fuchsia-600'}`}
                                     aria-hidden="true">
                                    {iniciais(c.name)}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2">
                                        <p className="font-semibold text-sm truncate text-slate-800">{c.name}</p>
                                        {!c._synced && (
                                            <span className="inline-flex items-center gap-1 text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-bold whitespace-nowrap uppercase">
                                                <i className={`fas ${motor.syncing ? 'fa-rotate fa-spin' : 'fa-clock'}`} aria-hidden="true" />{t('Pendente')}
                                            </span>
                                        )}
                                    </div>
                                    {c.nif && <p className="text-xs text-slate-500">{t('NIF:')} <strong>{c.nif}</strong></p>}
                                    {contacto && (
                                        <p className="text-xs text-slate-400 truncate">
                                            <i className="fas fa-circle-info mr-0.5 opacity-60" aria-hidden="true" />{contacto}
                                        </p>
                                    )}
                                </div>
                                <span className={`text-[10px] font-bold px-2 py-1 rounded-full whitespace-nowrap inline-flex items-center gap-1 ${empresa ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'}`}>
                                    <i className={`fas ${empresa ? 'fa-building' : 'fa-user'}`} aria-hidden="true" />
                                    {empresa ? t('Empresa') : t('Singular')}
                                </span>
                            </div>
                        );
                    })}

                    {encontrados.length > LIMITE && (
                        <p className="text-center text-[11px] text-slate-400 py-2">
                            <i className="fas fa-filter mr-1" aria-hidden="true" />
                            {t('A mostrar :n de :total. Refine a pesquisa.', { n: LIMITE, total: encontrados.length })}
                        </p>
                    )}

                    {!encontrados.length && (
                        <div className="pwa-entra text-center py-16 text-slate-400">
                            <i className="fas fa-users-slash text-5xl mb-3 block opacity-40 pwa-flutua" aria-hidden="true" />
                            <p className="text-sm font-medium">
                                {pesquisa ? t('Nenhum cliente encontrado') : t('Ainda não há clientes neste aparelho')}
                            </p>
                            <a href={rotas.novoCliente}
                               className="pwa-toque inline-block mt-3 text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-bold shadow-md shadow-emerald-600/20">
                                <i className="fas fa-user-plus mr-1" aria-hidden="true" />{t('Criar cliente')}
                            </a>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
