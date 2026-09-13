import { useEffect, useMemo, useRef, useState } from 'react';

import { etiquetaIntl, t, tn } from '@/i18n';

import { dinheiro, useAssentado, useBaseViva, useEstadoDoMotor } from '../ganchos';
import { db, type Produto } from '../motor/base';
import { sync } from '../motor/sincronizar';
import { formasDeCodigo } from '../motor/util';

/**
 * O CATÁLOGO DO APARELHO — o que se pode vender sem rede.
 *
 * Só leitura: os artigos vêm do servidor na sincronização. A lista é uma
 * consulta viva à base local (`useBaseViva`): quando a sincronização escreve,
 * os preços e o stock mudam aqui sozinhos — o Alpine ouvia `pwa:synced` e
 * relia a tabela à mão.
 */

type FiltroDoTipo = 'all' | 'produto' | 'servico';

/** Quantos se desenham de uma vez: um catálogo de farmácia tem milhares, e um telemóvel de balcão engasga-se. */
const LIMITE = 200;

/**
 * A pesquisa do catálogo — a mesma régua do `getProducts` do motor.
 *
 * O Blade só procurava por nome, SKU e código tal como escrito. O leitor de
 * códigos manda umas vezes o envelope GS1 («01» + GTIN-14) e outras só o
 * EAN-13 de dentro; o artigo está guardado de uma das formas. Procurar pela
 * forma exacta deixava o artigo «inexistente» ao balcão, e ele estava lá.
 * Numa farmácia pergunta-se pela substância; numa loja de roupa, pelo tamanho.
 */
function corresponde(p: Produto, s: string, formas: string[]): boolean {
    if (!s) return true;

    return (!!p.barcode && formas.includes(String(p.barcode).trim()))
        || String(p.name || '').toLowerCase().includes(s)
        || String(p.sku || '').toLowerCase().includes(s)
        || String(p.barcode || '').toLowerCase().includes(s)
        || String(p.active_ingredient || '').toLowerCase().includes(s)
        || String(p.size || '').toLowerCase().includes(s);
}

/** 12.5 → «12,5»; o stock pode ser fraccionado (kg, litros) e «12.500» lia-se doze mil. */
function quantidade(v: unknown): string {
    const n = Number(v);

    return new Intl.NumberFormat(etiquetaIntl(), { maximumFractionDigits: 3 }).format(Number.isFinite(n) ? n : 0);
}

export function Catalogo() {
    const motor = useEstadoDoMotor();

    // `null` até a primeira leitura voltar: «ainda a ler» não é «catálogo vazio».
    const produtos = useBaseViva<Produto[] | null>(() => db.products.toArray(), [], null);

    const [pesquisa, setPesquisa] = useState('');
    const [tipo, setTipo] = useState<FiltroDoTipo>('all');
    const assentada = useAssentado(pesquisa, 150);

    /*
     * REDE DE SEGURANÇA: catálogo vazio mas online → força a sincronização.
     *
     * Um aparelho acabado de instalar abria o catálogo antes da primeira
     * sincronização acabar e ficava a olhar para uma lista vazia, convencido
     * de que a empresa não tinha artigos. Uma vez só por visita — se o
     * servidor devolver mesmo zero, não se fica em ciclo a pedir.
     */
    const jaPediu = useRef(false);
    const [aTrazer, setATrazer] = useState(false);

    useEffect(() => {
        if (produtos === null || produtos.length || jaPediu.current || !navigator.onLine) return;
        jaPediu.current = true;
        setATrazer(true);
        sync(true)
            .catch((e) => console.error('[Catálogo] sync inicial falhou', e))
            .finally(() => setATrazer(false));
    }, [produtos]);

    const lista = produtos ?? [];

    const contagens = useMemo(() => ({
        all: lista.length,
        produto: lista.filter((p) => p.type === 'produto').length,
        servico: lista.filter((p) => p.type === 'servico').length,
    }), [lista]);

    const encontrados = useMemo(() => {
        const s = assentada.toLowerCase().trim();
        const formas = s ? formasDeCodigo(assentada) : [];

        return lista.filter((p) => (tipo === 'all' || p.type === tipo) && corresponde(p, s, formas));
    }, [lista, assentada, tipo]);

    const visiveis = encontrados.slice(0, LIMITE);

    const FILTROS: { chave: FiltroDoTipo; rotulo: string; icone: string; activo: string }[] = [
        { chave: 'all', rotulo: t('Todos'), icone: 'fa-layer-group', activo: 'bg-blue-600 text-white shadow-blue-600/30' },
        { chave: 'produto', rotulo: t('Produtos'), icone: 'fa-box', activo: 'bg-blue-600 text-white shadow-blue-600/30' },
        { chave: 'servico', rotulo: t('Serviços'), icone: 'fa-screwdriver-wrench', activo: 'bg-purple-600 text-white shadow-purple-600/30' },
    ];

    return (
        <div>
            {/* Cabeçalho */}
            <div className="pwa-entra relative overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-700 text-white rounded-2xl shadow-lg p-4 mb-3">
                <i className="fas fa-boxes-stacked absolute -right-3 -bottom-4 text-7xl opacity-10 pwa-flutua" aria-hidden="true" />
                <div className="relative flex items-center gap-3">
                    <span className="w-11 h-11 shrink-0 rounded-xl bg-white/20 flex items-center justify-center">
                        <i className="fas fa-box text-lg" aria-hidden="true" />
                    </span>
                    <div className="min-w-0">
                        <h1 className="text-xl font-bold leading-tight">{t('Catálogo')}</h1>
                        {/* "Em cache" é palavra de quem escreve o programa. Ao balcão a
                            pergunta é outra: tenho aqui os artigos para vender sem rede? */}
                        <p className="text-xs opacity-90 mt-0.5">
                            {tn(':n artigo guardado neste aparelho|:n artigos guardados neste aparelho', lista.length, { n: lista.length })}
                            {(motor.syncing || aTrazer) && (
                                <span className="ml-1.5 inline-flex items-center gap-1 font-semibold">
                                    <i className="fas fa-rotate fa-spin" aria-hidden="true" />{t('a sincronizar…')}
                                </span>
                            )}
                        </p>
                    </div>
                </div>
            </div>

            {/* Pesquisa e filtros — ficam à mão enquanto se desce a lista. */}
            <div className="sticky top-[var(--pwa-topo,60px)] z-30 bg-slate-50/95 backdrop-blur py-2 mb-1 -mx-1 px-1">
                <label htmlFor="catalogo-pesquisa" className="sr-only">{t('Pesquisar por nome, SKU, código...')}</label>
                <div className="flex items-center gap-2 bg-white rounded-2xl shadow-sm px-3 h-12 border-2 border-transparent focus-within:border-blue-500 transition">
                    <i className="fas fa-magnifying-glass text-slate-400" aria-hidden="true" />
                    <input id="catalogo-pesquisa" name="pesquisa" type="search" value={pesquisa} onChange={(e) => setPesquisa(e.target.value)}
                           placeholder={t('Pesquisar por nome, SKU, código...')} autoComplete="off"
                           className="flex-1 min-w-0 bg-transparent text-sm focus:outline-none" />
                    {pesquisa && (
                        <button type="button" onClick={() => setPesquisa('')} aria-label={t('Limpar pesquisa')}
                                className="pwa-toque text-slate-400 hover:text-slate-600 text-xl leading-none px-1">&times;</button>
                    )}
                </div>

                <div className="flex gap-2 mt-2 text-xs overflow-x-auto no-scrollbar" role="group" aria-label={t('Tipo')}>
                    {FILTROS.map((f) => (
                        <button key={f.chave} type="button" onClick={() => setTipo(f.chave)} aria-pressed={tipo === f.chave}
                                className={`pwa-toque px-3.5 py-2 rounded-full font-semibold shadow-sm whitespace-nowrap transition-colors inline-flex items-center gap-1.5 ${tipo === f.chave ? `${f.activo} shadow-md` : 'bg-white text-slate-600 hover:bg-slate-100'}`}>
                            <i className={`fas ${f.icone}`} aria-hidden="true" />
                            {f.rotulo}
                            <span className={`px-1.5 rounded-full text-[10px] font-bold ${tipo === f.chave ? 'bg-white/25' : 'bg-slate-100 text-slate-500'}`}>
                                {contagens[f.chave]}
                            </span>
                        </button>
                    ))}
                </div>
            </div>

            {/* Lista */}
            {produtos === null ? (
                <div className="space-y-2" aria-busy="true">
                    {[0, 1, 2, 3].map((i) => (
                        <div key={i} className="bg-white rounded-xl shadow-sm p-3 h-16 animate-pulse" />
                    ))}
                </div>
            ) : (
                <div className="space-y-2">
                    {visiveis.map((p, i) => {
                        const servico = p.type === 'servico';
                        const semStockGerido = !servico && p.manage_stock === false;
                        const esgotado = !servico && !semStockGerido && Number(p.stock_quantity) <= 0;

                        return (
                            <div key={p.id}
                                 className="pwa-entra pwa-cartao bg-white rounded-xl shadow-sm border border-slate-100 p-3 flex items-center gap-3"
                                 style={{ animationDelay: `${Math.min(i, 12) * 25}ms` }}>
                                <span className={`w-10 h-10 shrink-0 rounded-xl flex items-center justify-center ${servico ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600'}`}>
                                    <i className={`fas ${servico ? 'fa-screwdriver-wrench' : 'fa-box'}`} aria-hidden="true" />
                                </span>
                                <div className="flex-1 min-w-0">
                                    <p className="font-semibold text-sm truncate text-slate-800">{p.name}</p>
                                    <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-slate-500 mt-0.5">
                                        {p.sku && <span><i className="fas fa-barcode mr-1" aria-hidden="true" />{p.sku}</span>}
                                        {servico && <span className="text-purple-600 font-semibold">{t('Serviço')}</span>}
                                        {/* "Stock: 0" num artigo que NÃO controla stock —
                                            um prato de restaurante, por exemplo — lê-se como
                                            esgotado, e não é: vende-se sempre. O POS já fazia
                                            esta distinção; a lista dizia o contrário. */}
                                        {!servico && !semStockGerido && (
                                            <span className={esgotado ? 'text-red-600' : ''}>
                                                {t('Stock:')} <strong>{quantidade(p.stock_quantity)}</strong>
                                            </span>
                                        )}
                                        {semStockGerido && <span className="text-sky-600">{t('Sem stock gerido')}</span>}
                                    </div>
                                </div>
                                <div className="text-right shrink-0">
                                    <p className="font-bold text-blue-700 whitespace-nowrap">
                                        {dinheiro(p.price)} <span className="text-xs font-semibold opacity-70">Kz</span>
                                    </p>
                                    <p className="text-xs text-slate-500">{t('IVA :taxa%', { taxa: String(p.tax_rate ?? 0) })}</p>
                                </div>
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
                        <div className="pwa-entra text-center py-14 text-slate-400">
                            <i className={`fas ${aTrazer ? 'fa-rotate fa-spin' : 'fa-box-open'} text-5xl mb-3 block opacity-40 ${aTrazer ? '' : 'pwa-flutua'}`} aria-hidden="true" />
                            <p className="text-sm font-medium italic">{t('Nenhum produto encontrado')}</p>
                            {!lista.length && !aTrazer && (
                                <p className="text-xs mt-1">{t('Ligue-se à internet e sincronize para trazer o catálogo.')}</p>
                            )}
                            {pesquisa && (
                                <button type="button" onClick={() => setPesquisa('')}
                                        className="pwa-toque mt-3 inline-flex items-center gap-1 text-xs bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg font-bold">
                                    <i className="fas fa-xmark" aria-hidden="true" />{t('Limpar pesquisa')}
                                </button>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
