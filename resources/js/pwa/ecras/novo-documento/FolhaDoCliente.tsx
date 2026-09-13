import { useState } from 'react';

import { t } from '@/i18n';

import { useAssentado, useBaseViva } from '../../ganchos';
import type { Cliente } from '../../motor/base';
import { getClients } from '../../motor/vendas';
import { CAMPO, Folha } from '../../ui/Folha';

/**
 * A FOLHA DE ESCOLHER O CLIENTE DO DOCUMENTO.
 *
 * A lista é uma consulta viva à base do aparelho: um cliente criado sem rede
 * aparece logo, e quando a sincronização o troca pelo do servidor a lista
 * acompanha sem recarregar a página (o Blade ouvia `pwa:synced` e relia tudo).
 * A pesquisa guarda-se entre aberturas, como no Blade.
 */
export function FolhaDoCliente({
    aberta,
    aoFechar,
    aoEscolher,
    rotaNovoCliente,
}: {
    aberta: boolean;
    aoFechar: () => void;
    /** `null` = Consumidor Final. */
    aoEscolher: (c: Cliente | null) => void;
    rotaNovoCliente: string;
}) {
    const [pesquisa, setPesquisa] = useState('');
    const termo = useAssentado(pesquisa.trim(), 150);

    // Cem chegam para escolher; a pesquisa encontra os outros.
    const clientes = useBaseViva(async () => (await getClients({ search: termo })).slice(0, 100), [termo], [] as Cliente[]);

    const escolher = (c: Cliente | null) => {
        aoEscolher(c);
        aoFechar();
    };

    return (
        <Folha aberta={aberta} aoFechar={aoFechar} titulo={t('Selecionar cliente')} subtitulo={t('Cliente é opcional para Consumidor Final')}
               icone="fa-user" cor="from-blue-600 to-indigo-700" largura="sm:max-w-md" data-ensaio="folha-cliente"
               rodape={(
                   <a href={rotaNovoCliente}
                      className="pwa-toque block w-full text-center py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold text-sm shadow-lg shadow-emerald-500/20">
                       <i className="fas fa-user-plus mr-1" aria-hidden="true" />{t('Criar novo cliente')}
                   </a>
               )}>
            <div className="relative mb-3">
                <i className="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true" />
                <input type="search" name="pesquisa-cliente" value={pesquisa} onChange={(e) => setPesquisa(e.target.value)}
                       placeholder={t('Pesquisar cliente...')} aria-label={t('Pesquisar cliente...')}
                       autoFocus autoComplete="off" className={`${CAMPO} pl-9`} />
            </div>

            <div className="space-y-1.5">
                <button type="button" onClick={() => escolher(null)} data-ensaio="consumidor-final"
                        className="pwa-toque w-full text-left p-3 rounded-xl bg-slate-50 hover:bg-blue-50 flex items-center gap-3 transition">
                    <span className="w-9 h-9 shrink-0 rounded-xl bg-slate-200 text-slate-600 flex items-center justify-center">
                        <i className="fas fa-user-tag" aria-hidden="true" />
                    </span>
                    <span className="min-w-0">
                        <span className="block font-semibold text-sm text-slate-800">{t('Consumidor Final')}</span>
                        <span className="block text-xs text-slate-500">{t('Sem cliente identificado')}</span>
                    </span>
                </button>

                {clientes.map((c) => (
                    <button key={String(c.id)} type="button" onClick={() => escolher(c)} data-ensaio="cliente"
                            className="pwa-toque pwa-aparece w-full text-left p-3 rounded-xl border border-slate-100 hover:border-blue-200 hover:bg-blue-50 flex items-center gap-3 transition">
                        <span className="w-9 h-9 shrink-0 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm uppercase">
                            {(c.name || '?').trim().charAt(0) || '?'}
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block font-semibold text-sm text-slate-800 truncate">{c.name}</span>
                            {c.nif && <span className="block text-xs text-slate-500">{t('NIF: :nif', { nif: c.nif })}</span>}
                        </span>
                        {/* Criado neste aparelho e ainda por subir: sai emitido com ele assim que houver rede. */}
                        {!Number.isInteger(c.id) && (
                            <span className="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800"
                                  title={t('Pendente')}>
                                <i className="fas fa-clock mr-1" aria-hidden="true" />{t('Pendente')}
                            </span>
                        )}
                    </button>
                ))}

                {!clientes.length && pesquisa.trim() !== '' && (
                    <div className="pwa-aparece text-center py-6 text-slate-400 text-sm italic">
                        <i className="fas fa-user-slash block text-2xl mb-2 not-italic" aria-hidden="true" />
                        {t('Nenhum encontrado')}
                    </div>
                )}
            </div>
        </Folha>
    );
}
