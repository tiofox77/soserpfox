import { useState } from 'react';

import { t } from '@/i18n';

import { dinheiro, useAssentado, useBaseViva } from '../../ganchos';
import type { Produto } from '../../motor/base';
import { getProducts } from '../../motor/vendas';
import { CAMPO, Folha } from '../../ui/Folha';

/**
 * A FOLHA DE ESCOLHER O ARTIGO DE UMA LINHA.
 *
 * A pesquisa é a do motor (`getProducts`): nome, SKU, código de barras em
 * qualquer das formas (GS1/EAN — o leitor tanto manda uma como a outra), e a
 * substância ou o tamanho nos catálogos de farmácia e de vestuário. O Blade
 * tinha a sua própria, só por nome/SKU/código exacto, e um código lido com o
 * envelope GS1 não achava o artigo.
 */
export function FolhaDoProduto({
    aberta,
    aoFechar,
    aoEscolher,
}: {
    aberta: boolean;
    aoFechar: () => void;
    aoEscolher: (p: Produto) => void;
}) {
    const [pesquisa, setPesquisa] = useState('');
    const termo = useAssentado(pesquisa.trim(), 150);

    // `null` enquanto a primeira leitura não volta: sem isto o «Nenhum produto
    // encontrado» piscava ao abrir a folha.
    const produtos = useBaseViva<Produto[] | null>(async () => (await getProducts({ search: termo })).slice(0, 100), [termo], null);

    const escolher = (p: Produto) => {
        aoEscolher(p);
        aoFechar();
    };

    return (
        <Folha aberta={aberta} aoFechar={aoFechar} titulo={t('Adicionar')} subtitulo={t('Pesquisar produto...')}
               icone="fa-box-open" cor="from-emerald-500 to-teal-600" largura="sm:max-w-md" data-ensaio="folha-produto">
            <div className="relative mb-3">
                <i className="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true" />
                <input type="search" name="pesquisa-produto" value={pesquisa} onChange={(e) => setPesquisa(e.target.value)}
                       placeholder={t('Pesquisar produto...')} aria-label={t('Pesquisar produto...')}
                       autoFocus autoComplete="off" className={`${CAMPO} pl-9`} />
            </div>

            {produtos === null && (
                <div className="py-8 text-center text-slate-400"><i className="fas fa-spinner fa-spin text-2xl" aria-hidden="true" /></div>
            )}

            <div className="space-y-1.5">
                {produtos?.map((p) => (
                    <button key={String(p.id)} type="button" onClick={() => escolher(p)} data-ensaio="produto"
                            className="pwa-toque pwa-aparece w-full text-left p-3 rounded-xl border border-slate-100 hover:border-emerald-200 hover:bg-emerald-50 flex items-center gap-3 transition">
                        <span className="w-9 h-9 shrink-0 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                            <i className={`fas ${p.type === 'servico' ? 'fa-screwdriver-wrench' : 'fa-box'}`} aria-hidden="true" />
                        </span>
                        <span className="flex-1 min-w-0">
                            <span className="block font-semibold text-sm text-slate-800 truncate">{p.name}</span>
                            {p.sku && <span className="block text-xs text-slate-500">{t('SKU: :sku', { sku: p.sku })}</span>}
                        </span>
                        <span className="font-bold text-blue-700 text-sm whitespace-nowrap">{dinheiro(p.price)}</span>
                    </button>
                ))}

                {produtos !== null && !produtos.length && (
                    <div className="pwa-aparece text-center py-6 text-slate-400 text-sm italic">
                        <i className="fas fa-box-open block text-2xl mb-2 not-italic" aria-hidden="true" />
                        {t('Nenhum produto encontrado')}
                    </div>
                )}
            </div>
        </Folha>
    );
}
