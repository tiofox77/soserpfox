import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { api, ErroDaApi } from '@/api/cliente';
import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { Paginacao } from '@/ui/Paginacao';
import { CORES_DE_ECRA, FOCO, RAIO, cls, data as fmtData, kz } from '@/ui/tokens';

/**
 * O MAPA DE VENDAS DO BALCÃO.
 *
 * A consulta é a do `PosSalesReportQuery` — o MESMO serviço que o PDF e o
 * Excel já usavam. Este ecrã não monta consulta nenhuma: se montasse, seriam
 * quatro relatórios a dizer números diferentes sobre o mesmo dia.
 *
 * A NOTA DE CRÉDITO NÃO SE EMITE AQUI. O ecrã em Livewire criava-a à mão
 * (`CreditNote::create`, 170 linhas) enquanto o `EmissorDeNotas` fazia o
 * mesmo do outro lado — duas maneiras de anular uma venda. Aqui o botão leva
 * ao editor de notas de crédito, que é onde isso se faz, com a factura já
 * escolhida.
 */

type LinhaDoMapa = {
    tipo: string;
    subtipo: string;
    id: number;
    numero: string;
    numero_interno: string;
    data: string;
    cliente: string;
    cliente_nif: string | null;
    subtotal: number;
    imposto: number;
    desconto: number;
    total: number;
    forma: string | null;
    estado: string;
    motivo: string | null;
    factura_origem: string | null;
    /** O selo da AGT, ja decidido pelo servidor. */
    agt: { estado: string; rotulo: string; cor: 'primaria' | 'neutra' | 'bom' | 'aviso' | 'perigo'; razao: string | null };
    papeis: { talao: string; a4: string } | null;
};

type Totais = {
    facturas_n: number;
    anuladas_n: number;
    anulado: number;
    bruto: number;
    imposto: number;
    desconto: number;
    notas_n: number;
    devolvido: number;
    liquido: number;
};

type Filtros = {
    start_date: string;
    end_date: string;
    search: string;
    status: string;
    payment_method: string;
    document_type: string;
    /** O módulo de origem: vazio na facturação, 'restaurant' no restaurante. */
    source_module: string;
    /**
     * O OPERADOR. Só quem tem `invoicing.pos.reports.all` o pode escolher —
     * e é o SERVIDOR que o impõe: a quem não tem, o mapa fica preso às suas
     * vendas, escolha ele o que escolher.
     */
    user_id: string;
    por_pagina: number;
    page: number;
};

/**
 * A DATA DE HOJE, no fuso de quem está a olhar.
 *
 * `toISOString()` converte para UTC, e a Angola está em UTC+1: o primeiro dia
 * do mês saía 31 do mês anterior, e «hoje» saía ontem depois das 23h. Um
 * relatório que começa no dia errado conta o dia errado.
 */
function dia(d: Date): string {
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const j = String(d.getDate()).padStart(2, '0');

    return `${d.getFullYear()}-${m}-${j}`;
}

/** O mês corrente, que é o período que se olha quase sempre. */
function inicioDoMes(): string {
    const h = new Date();

    return dia(new Date(h.getFullYear(), h.getMonth(), 1));
}

const VAZIOS: Filtros = {
    start_date: inicioDoMes(),
    end_date: dia(new Date()),
    search: '',
    status: '',
    payment_method: '',
    document_type: '',
    source_module: '',
    user_id: '',
    por_pagina: 20,
    page: 1,
};

export default function RelatorioDoPos({ sourceModule }: { sourceModule?: string }) {
    const [filtros, porFiltros] = useState<Filtros>({ ...VAZIOS, source_module: sourceModule ?? '' });
    const [aVer, porAVer] = useState<LinhaDoMapa | null>(null);

    const mapa = useQuery({
        queryKey: ['pos', 'relatorio', filtros],
        queryFn: () =>
            api.ler<{
                data: LinhaDoMapa[];
                meta: {
                    total: number; pagina: number; paginas: number; totais: Totais;
                    formato: 'talao' | 'a4';
                    pode_ver_todas: boolean;
                    operadores: Array<{ valor: string; rotulo: string }>;
                };
            }>(
                '/pos/relatorio',
                filtros as unknown as Record<string, string | number>,
            ),
        placeholderData: keepPreviousData,
    });

    function mudar<K extends keyof Filtros>(chave: K, valor: Filtros[K]) {
        porFiltros((f) => ({ ...f, [chave]: valor, page: 1 }));
    }

    if (mapa.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o relatório')}</h2>
                <p className="text-sm text-red-800">
                    {mapa.error instanceof ErroDaApi ? mapa.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const linhas = mapa.data?.data ?? [];
    const totais = mapa.data?.meta.totais;
    // O papel configurado pela empresa: e nele que o botao imprime.
    const formato = mapa.data?.meta.formato ?? 'talao';

    return (
        <div className="space-y-4">
            <div className={cls('flex flex-wrap items-center justify-between gap-3 px-5 py-4 text-white shadow-lg', CORES_DE_ECRA.roxo, RAIO)}>
                <div className="flex items-center gap-3">
                    <div className="grid h-11 w-11 place-items-center rounded-xl bg-white/20 backdrop-blur-sm">
                        <i className="fas fa-chart-line icon-float text-xl" aria-hidden="true" />
                    </div>
                    <div>
                        <h1 className="text-lg font-bold leading-tight">{t('Relatórios do POS')}</h1>
                        <p className="text-xs text-white/80">{t('O que o balcão vendeu, e o que voltou para trás')}</p>
                    </div>
                </div>

                {/* O PDF e o Excel são os do servidor: os mesmos ficheiros que o
                    ecrã de sempre gerava, com os filtros que estão à vista. */}
                <div className="flex gap-2">
                    <a
                        href={`/invoicing/pos/export/sales-report/pdf?${new URLSearchParams(filtros as unknown as Record<string, string>)}`}
                        target="_blank"
                        rel="noreferrer"
                        className={cls('inline-flex items-center gap-2 bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur-sm transition-colors hover:bg-white/25', RAIO, FOCO)}
                    >
                        <i className="fas fa-file-pdf" aria-hidden="true" />
                        PDF
                    </a>
                    <a
                        href={`/invoicing/pos/export/sales-report/excel?${new URLSearchParams(filtros as unknown as Record<string, string>)}`}
                        target="_blank"
                        rel="noreferrer"
                        className={cls('inline-flex items-center gap-2 bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur-sm transition-colors hover:bg-white/25', RAIO, FOCO)}
                    >
                        <i className="fas fa-file-excel" aria-hidden="true" />
                        Excel
                    </a>
                </div>
            </div>

            {/* OS CARTÕES CONTAM O PERÍODO, não a página: uma soma que mudasse
                ao carregar em «Seguinte» não é uma soma. */}
            {totais && (
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <CartaoNumero
                        aspecto="claro"
                        icone="fa-receipt"
                        tom="indigo"
                        rotulo={t('Facturado')}
                        valor={kz(totais.bruto)}
                        nota={t(':n documentos', { n: totais.facturas_n })}
                    />
                    <CartaoNumero
                        aspecto="claro"
                        icone="fa-rotate-left"
                        tom="ambar"
                        rotulo={t('Devolvido')}
                        valor={kz(totais.devolvido)}
                        nota={t(':n notas de crédito', { n: totais.notas_n })}
                    />
                    <CartaoNumero
                        aspecto="claro"
                        icone="fa-ban"
                        tom="vermelho"
                        rotulo={t('Anulado')}
                        valor={kz(totais.anulado)}
                        nota={t(':n anuladas', { n: totais.anuladas_n })}
                    />
                    <CartaoNumero
                        aspecto="claro"
                        icone="fa-sack-dollar"
                        tom="verde"
                        rotulo={t('Líquido')}
                        valor={kz(totais.liquido)}
                        nota={t('IVA :valor', { valor: kz(totais.imposto) })}
                    />
                </div>
            )}

            <Cartao titulo={t('Filtros')} icone="fa-filter">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <Campo etiqueta={t('De')}>
                        <input type="date" value={filtros.start_date} onChange={(e) => mudar('start_date', e.target.value)} className={entrada} />
                    </Campo>
                    <Campo etiqueta={t('Até')}>
                        <input type="date" value={filtros.end_date} onChange={(e) => mudar('end_date', e.target.value)} className={entrada} />
                    </Campo>
                    <Campo etiqueta={t('Procurar')}>
                        <input
                            type="search"
                            value={filtros.search}
                            onChange={(e) => mudar('search', e.target.value)}
                            placeholder={t('Número ou cliente')}
                            className={entrada}
                        />
                    </Campo>
                    <Campo etiqueta={t('Documento')}>
                        <select value={filtros.document_type} onChange={(e) => mudar('document_type', e.target.value)} className={entrada}>
                            <option value="">{t('Facturas e notas')}</option>
                            {/* OS CÓDIGOS SÃO `FR` E `NC` — é o que o
                                `PosSalesReportQuery` compara. Com 'factura' e
                                'nota' o filtro não batia com nenhum dos dois e
                                não filtrava nada: as duas opções mostravam a
                                mesma lista. */}
                            <option value="FR">{t('Só facturas')}</option>
                            <option value="NC">{t('Só notas de crédito')}</option>
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Estado')}>
                        <select value={filtros.status} onChange={(e) => mudar('status', e.target.value)} className={entrada}>
                            <option value="">{t('Todos')}</option>
                            <option value="paid">{t('Paga')}</option>
                            <option value="pending">{t('Pendente')}</option>
                            <option value="cancelled">{t('Anulada')}</option>
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Forma de pagamento')}>
                        <input
                            value={filtros.payment_method}
                            onChange={(e) => mudar('payment_method', e.target.value)}
                            placeholder={t('cash, multicaixa…')}
                            className={entrada}
                        />
                    </Campo>

                    {/* Sem o direito de ver todas, este selector não aparece:
                        oferecer uma escolha que o servidor ignora fazia o mapa
                        dizer «filtrado por Maria» a mostrar as do próprio. */}
                    {mapa.data?.meta.pode_ver_todas && (
                        <Campo etiqueta={t('Operador')}>
                            <select value={filtros.user_id} onChange={(e) => mudar('user_id', e.target.value)} className={entrada}>
                                <option value="">{t('Todos')}</option>
                                {mapa.data.meta.operadores.map((u) => (
                                    <option key={u.valor} value={u.valor}>{u.rotulo}</option>
                                ))}
                            </select>
                        </Campo>
                    )}
                </div>

                <div className="mt-3 flex items-center justify-between">
                    <p className="text-sm text-slate-500">
                        {t(':n documentos no período', { n: mapa.data?.meta.total ?? 0 })}
                    </p>
                    <Botao icone="fa-eraser" onClick={() => porFiltros(VAZIOS)}>{t('Limpar')}</Botao>
                </div>
            </Cartao>

            <Cartao semPadding>
                {mapa.isPending ? (
                    <Carregando linhas={8} />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50">
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-600">
                                    <th className="px-4 py-3 font-bold">{t('Documento')}</th>
                                    <th className="px-4 py-3 font-bold">{t('Cliente')}</th>
                                    <th className="px-4 py-3 font-bold">{t('Data')}</th>
                                    <th className="px-4 py-3 font-bold">{t('Forma')}</th>
                                    <th className="px-4 py-3 font-bold">{t('Estado')}</th>
                                    <th className="px-3 py-3 text-center font-bold" title={t('Estado no Portal AGT')}>AGT</th>
                                    <th className="px-4 py-3 text-right font-bold">{t('Total')}</th>
                                    <th className="px-4 py-3 text-right font-bold">{t('Acções')}</th>
                                </tr>
                            </thead>
                            <tbody className={cls('divide-y divide-slate-100', mapa.isFetching && 'opacity-60')}>
                                {linhas.length === 0 && (
                                    <tr>
                                        <td colSpan={8} className="px-6 py-16 text-center">
                                            <div className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                                                <i className="fas fa-chart-simple text-3xl text-slate-400" aria-hidden="true" />
                                            </div>
                                            <p className="text-lg font-semibold text-slate-500">{t('Nada neste período')}</p>
                                            <p className="mt-1 text-sm text-slate-400">{t('Alargue as datas ou limpe os filtros.')}</p>
                                        </td>
                                    </tr>
                                )}

                                {linhas.map((d, i) => (
                                    <tr
                                        key={`${d.tipo}-${d.id}`}
                                        style={{ '--i': i } as React.CSSProperties}
                                        className="entra transition-colors hover:bg-indigo-50/50"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <Etiqueta cor={d.tipo === 'nota' ? 'aviso' : 'primaria'}>{d.subtipo}</Etiqueta>
                                                <div>
                                                    <p className="font-semibold text-indigo-700">{d.numero_interno}</p>
                                                    {d.numero_interno !== d.numero && (
                                                        <p className="font-mono text-[11px] text-slate-400">{d.numero}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="text-slate-800">{d.cliente}</p>
                                            {d.cliente_nif && <p className="text-xs text-slate-400">NIF: {d.cliente_nif}</p>}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums text-slate-600">{fmtData(d.data)}</td>
                                        <td className="px-4 py-3 text-slate-600">{d.forma ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <Etiqueta cor={d.estado === 'cancelled' ? 'perigo' : d.estado === 'paid' ? 'bom' : 'neutra'}>
                                                {d.estado}
                                            </Etiqueta>
                                        </td>
                                        {/* O SELO DA AGT. Coluna estreita de
                                            propósito: o que se lê de relance é
                                            a cor, e a razão da recusa está no
                                            título para quem parar em cima. */}
                                        <td className="px-3 py-3 text-center" title={d.agt.razao ?? d.agt.rotulo}>
                                            {d.agt.estado === 'nao-aplica' ? (
                                                <span className="text-slate-300">—</span>
                                            ) : (
                                                <span
                                                    className={cls(
                                                        'inline-grid h-6 w-6 place-items-center rounded-full text-[11px]',
                                                        d.agt.cor === 'bom' && 'bg-emerald-100 text-emerald-700',
                                                        d.agt.cor === 'primaria' && 'bg-indigo-100 text-indigo-700',
                                                        d.agt.cor === 'aviso' && 'bg-amber-100 text-amber-700',
                                                        d.agt.cor === 'perigo' && 'bg-red-100 text-red-700',
                                                        d.agt.cor === 'neutra' && 'bg-slate-100 text-slate-500',
                                                    )}
                                                    aria-label={d.agt.razao ? `${d.agt.rotulo}: ${d.agt.razao}` : d.agt.rotulo}
                                                >
                                                    <i
                                                        className={cls(
                                                            'fas',
                                                            d.agt.estado === 'validado' && 'fa-check',
                                                            d.agt.estado === 'enviado' && 'fa-paper-plane',
                                                            d.agt.estado === 'recusado' && 'fa-xmark',
                                                            d.agt.estado === 'em-fila' && 'fa-hourglass-half',
                                                            d.agt.estado === 'por-comunicar' && 'fa-minus',
                                                        )}
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                            )}
                                        </td>
                                        <td
                                            className={cls(
                                                'px-4 py-3 text-right font-bold tabular-nums',
                                                d.tipo === 'nota' ? 'text-amber-600' : 'text-slate-900',
                                            )}
                                        >
                                            {d.tipo === 'nota' ? `− ${kz(d.total)}` : kz(d.total)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => porAVer(d)}
                                                    title={t('Ver detalhes')}
                                                    aria-label={t('Ver :numero', { numero: d.numero_interno })}
                                                    className={cls('p-2 text-slate-500 transition-all duration-200 hover:scale-110 hover:bg-slate-100', RAIO, FOCO)}
                                                >
                                                    <i className="fas fa-eye" aria-hidden="true" />
                                                </button>
                                                {/* IMPRIMIR. Abre o papel que a empresa
                                                    configurou com a caixa de impressão já
                                                    pronta — a página manda-se imprimir
                                                    sozinha depois de as imagens carregarem.
                                                    Reimprimir um talão passa a ser um clique. */}
                                                {d.papeis && (
                                                    <a
                                                        href={`${d.papeis[formato]}?imprimir=1`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        aria-label={t('Imprimir :numero', { numero: d.numero_interno })}
                                                        className={cls(
                                                            'inline-flex items-center gap-1.5 border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700',
                                                            'transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:text-indigo-600 hover:shadow-sm',
                                                            RAIO,
                                                            FOCO,
                                                        )}
                                                    >
                                                        <i className="fas fa-print text-indigo-500" aria-hidden="true" />
                                                        {t('Imprimir')}
                                                    </a>
                                                )}

                                                {/* CREDITAR leva ao editor de notas de crédito
                                                    com a factura já escolhida. O ecrã de sempre
                                                    criava a nota aqui à mão, ao lado de um
                                                    `EmissorDeNotas` que fazia o mesmo — uma venda
                                                    anula-se num sítio só. */}
                                                {d.tipo === 'factura' && d.estado !== 'cancelled' && (
                                                    <a
                                                        href={`/invoicing/credit-notes/create?invoice=${d.id}`}
                                                        aria-label={t('Creditar :numero', { numero: d.numero_interno })}
                                                        className={cls(
                                                            'inline-flex items-center gap-1.5 border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-700',
                                                            'transition-all duration-200 hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-sm',
                                                            RAIO,
                                                            FOCO,
                                                        )}
                                                    >
                                                        <i className="fas fa-rotate-left" aria-hidden="true" />
                                                        {t('Creditar')}
                                                    </a>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Paginacao
                    pagina={filtros.page}
                    ultima={mapa.data?.meta.paginas ?? 1}
                    total={mapa.data?.meta.total}
                    aCarregar={mapa.isFetching}
                    aMudar={(p) => porFiltros((f) => ({ ...f, page: p }))}
                />
            </Cartao>

            <Modal
                aberto={aVer !== null}
                aoFechar={() => porAVer(null)}
                titulo={aVer?.numero_interno ?? ''}
                subtitulo={aVer?.cliente}
                icone="fa-file-invoice"
                cor="primaria"
            >
                {aVer && (
                    <dl className="space-y-2 text-sm">
                        {[
                            [t('Documento'), `${aVer.subtipo} · ${aVer.numero}`],
                            [t('Data'), fmtData(aVer.data)],
                            [t('Cliente'), aVer.cliente_nif ? `${aVer.cliente} (${aVer.cliente_nif})` : aVer.cliente],
                            [t('Forma de pagamento'), aVer.forma ?? '—'],
                            [t('Estado'), aVer.estado],
                            [t('Subtotal'), kz(aVer.subtotal)],
                            [t('Desconto'), kz(aVer.desconto)],
                            [t('IVA'), kz(aVer.imposto)],
                            [t('Total'), kz(aVer.total)],
                            ...(aVer.motivo ? [[t('Motivo'), aVer.motivo]] : []),
                            ...(aVer.factura_origem ? [[t('Factura de origem'), aVer.factura_origem]] : []),
                        ].map(([r, v]) => (
                            <div key={r} className="flex justify-between gap-4 border-b border-slate-100 pb-1.5">
                                <dt className="text-slate-500">{r}</dt>
                                <dd className="text-right font-semibold text-slate-800">{v}</dd>
                            </div>
                        ))}
                    </dl>
                )}
            </Modal>
        </div>
    );
}
