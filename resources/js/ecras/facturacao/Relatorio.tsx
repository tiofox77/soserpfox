import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { relatorios, type Coluna, type Dados, type Esquema, type Filtro, type Formato, type Tabela } from '@/api/relatorios';
import { ErroDaApi } from '@/api/cliente';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * UM RELATÓRIO QUALQUER DA FACTURAÇÃO.
 *
 * Vinte e tantos mapas Livewire com a mesma forma — filtros em cima,
 * cartões com os totais, uma ou duas tabelas — saem todos deste ecrã. O
 * que muda vem no esquema, do servidor (`Relatorios\Catalogo`), e os
 * números são os do mesmo serviço que o ecrã de sempre usa.
 */
const ESTADOS: Record<string, { rotulo: string; cor: 'neutra' | 'primaria' | 'bom' | 'aviso' | 'perigo' }> = {
    draft: { rotulo: 'Rascunho', cor: 'neutra' },
    pending: { rotulo: 'Pendente', cor: 'aviso' },
    issued: { rotulo: 'Emitida', cor: 'primaria' },
    sent: { rotulo: 'Enviada', cor: 'primaria' },
    partial: { rotulo: 'Parcial', cor: 'aviso' },
    partially_paid: { rotulo: 'Parcial', cor: 'aviso' },
    paid: { rotulo: 'Paga', cor: 'bom' },
    overdue: { rotulo: 'Vencida', cor: 'perigo' },
    cancelled: { rotulo: 'Anulada', cor: 'perigo' },
    credited: { rotulo: 'Creditada', cor: 'neutra' },
    received: { rotulo: 'Recebida', cor: 'bom' },
    active: { rotulo: 'Activo', cor: 'bom' },
    inactive: { rotulo: 'Inactivo', cor: 'neutra' },
    expired: { rotulo: 'Expirado', cor: 'perigo' },
    expiring_soon: { rotulo: 'A expirar', cor: 'aviso' },
    depleted: { rotulo: 'Esgotado', cor: 'neutra' },
    in: { rotulo: 'Entrada', cor: 'bom' },
    out: { rotulo: 'Saída', cor: 'perigo' },
    adjustment: { rotulo: 'Ajuste', cor: 'aviso' },
    transfer: { rotulo: 'Transferência', cor: 'primaria' },
};

const CORES: Record<string, string> = {
    blue: 'border-blue-500 text-blue-700', gray: 'border-slate-500 text-slate-700', purple: 'border-purple-500 text-purple-700',
    green: 'border-emerald-500 text-emerald-700', red: 'border-red-500 text-red-700', orange: 'border-orange-500 text-orange-700',
    yellow: 'border-amber-500 text-amber-700', pink: 'border-pink-500 text-pink-700', indigo: 'border-indigo-500 text-indigo-700',
};

const numero = (v: unknown, casas: number) => new Intl.NumberFormat('pt-PT', { minimumFractionDigits: casas, maximumFractionDigits: casas }).format(Number(v) || 0);

/** O valor num caminho com pontos: `client.name`, `totals.total`. */
export function valor(obj: unknown, caminho: string): unknown {
    return caminho.split('.').reduce<unknown>((acc, parte) => (acc && typeof acc === 'object' ? (acc as Record<string, unknown>)[parte] : undefined), obj);
}

export function formatar(v: unknown, formato?: Formato): string {
    if (v === null || v === undefined || v === '') return formato === 'dinheiro' || formato === 'inteiro' || formato === 'numero' ? (formato === 'inteiro' ? '0' : numero(0, 2)) : '—';
    switch (formato) {
        case 'dinheiro': return numero(v, 2);
        case 'numero': return numero(v, 2);
        case 'inteiro': return numero(Math.round(Number(v) || 0), 0);
        case 'dias': return `${Math.round(Number(v) || 0)} d`;
        case 'percentagem': return `${numero(v, 1)}%`;
        case 'data': {
            const s = String(v);
            const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s);
            return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
        }
        default: return String(v);
    }
}

function Valor({ v, formato }: { v: unknown; formato?: Formato }) {
    if (formato === 'estado') {
        // O rótulo do mapa é escrito aqui, e traduz-se; o que vier de fora
        // dele é o que o servidor mandou, e esse mostra-se tal e qual.
        const e = ESTADOS[String(v)];
        if (e) return <Etiqueta cor={e.cor}>{t(e.rotulo)}</Etiqueta>;
        return <Etiqueta cor="neutra">{String(v ?? '—')}</Etiqueta>;
    }
    const n = formato === 'dinheiro' || formato === 'numero' || formato === 'percentagem' ? Number(v) : NaN;
    return <span className={cls(!Number.isNaN(n) && n < 0 && 'text-red-700')}>{formatar(v, formato)}</span>;
}

/** Uma tabela do esquema pode apontar a uma lista ou a um mapa (id => linha). */
function linhasDe(dados: Dados, chave: string): unknown[] {
    const bruto = valor(dados, chave);
    if (Array.isArray(bruto)) return bruto;
    if (bruto && typeof bruto === 'object') {
        const o = bruto as Record<string, unknown>;
        return Array.isArray(o.data) ? o.data : Object.values(o);
    }
    return [];
}

export default function Relatorio({ slug, filtrosIniciais }: { slug: string; filtrosIniciais?: Record<string, string> }) {
    // O que vem no endereço abre o mapa já filtrado — é assim que o aviso de
    // validade («ACÇÃO URGENTE») leva a quem o abre a lista dos JÁ EXPIRADOS.
    const [filtros, porFiltros] = useState<Record<string, string>>(filtrosIniciais ?? {});
    const [procura, porProcura] = useState('');

    const q = useQuery({ queryKey: ['relatorio', slug, filtros], queryFn: () => relatorios.ler(slug, filtros), placeholderData: keepPreviousData });
    const filtroEntidade = q.data?.esquema.filtros.find((f) => f.tipo === 'entidade');
    const sugestoes = useQuery({
        queryKey: ['relatorio', slug, 'entidades', filtros.entidade ?? 'cliente', procura],
        queryFn: () => relatorios.entidades(slug, filtros.entidade ?? 'cliente', procura),
        enabled: Boolean(filtroEntidade) && procura.trim().length >= 2,
    });

    if (q.isPending) return <Carregando linhas={8} />;
    if (q.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o relatório')}</h2>
                <p className="text-sm text-red-800">{q.error instanceof ErroDaApi ? q.error.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const { esquema: e, dados, atalhos, csv } = q.data;
    const mudar = (nome: string, v: string) => porFiltros((f) => ({ ...f, [nome]: v }));
    const intervalo = dados.intervalo;
    const csvUrl = csv ? `${csv}?${new URLSearchParams(filtros).toString()}` : null;

    return (
        <div className={cls('space-y-4', q.isFetching && 'opacity-70')} data-relatorio={e.slug}>
            <Cartao
                titulo={<span className="flex items-center gap-2"><i className="fas fa-chart-bar text-slate-400" aria-hidden="true" />{e.titulo}</span>}
                accoes={
                    <span className="flex flex-wrap gap-2">
                        <a href="/invoicing/reports" className={cls('inline-flex items-center gap-2 border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50', RAIO)}><i className="fas fa-arrow-left" aria-hidden="true" />{t('Todos os relatórios')}</a>
                        {csvUrl && <a href={csvUrl} className={cls('inline-flex items-center gap-2 border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50', RAIO)} data-csv><i className="fas fa-file-csv" aria-hidden="true" />CSV</a>}
                        <Botao icone="fa-print" onClick={() => window.print()}>{t('Imprimir')}</Botao>
                    </span>
                }
            >
                {e.descricao && <p className="mb-4 text-sm text-slate-500">{e.descricao}</p>}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {e.periodo && (
                        <>
                            <Campo etiqueta={t('Período')}>
                                <select value={filtros.period ?? e.periodo.omissao} onChange={(ev) => porFiltros((f) => { const { dateFrom: _de, dateTo: _ate, ...resto } = f; return { ...resto, period: ev.target.value }; })} className={entrada}>
                                    {atalhos.map((a) => <option key={a.valor} value={a.valor}>{a.rotulo}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta={t('De')}><input type="date" value={filtros.dateFrom ?? intervalo?.de ?? ''} onChange={(ev) => porFiltros((f) => ({ ...f, period: 'custom', dateFrom: ev.target.value, dateTo: f.dateTo ?? intervalo?.ate ?? '' }))} className={entrada} /></Campo>
                            <Campo etiqueta={t('Até')}><input type="date" value={filtros.dateTo ?? intervalo?.ate ?? ''} onChange={(ev) => porFiltros((f) => ({ ...f, period: 'custom', dateTo: ev.target.value, dateFrom: f.dateFrom ?? intervalo?.de ?? '' }))} className={entrada} /></Campo>
                        </>
                    )}
                    {e.filtros.map((f) => <CampoDeFiltro key={f.nome} f={f} valor={filtros[f.nome] ?? (f.omissao === null || f.omissao === undefined ? '' : String(f.omissao))} aoMudar={(v) => mudar(f.nome, v)} procura={procura} porProcura={porProcura} sugestoes={sugestoes.data?.data ?? []} escolhido={filtros.entidadeNome} aoEscolher={(id, nome) => { porFiltros((x) => ({ ...x, entidadeId: String(id), entidadeNome: nome })); porProcura(''); }} aoLimpar={() => porFiltros((x) => { const { entidadeId: _id, entidadeNome: _n, ...resto } = x; return resto; })} />)}
                </div>
            </Cartao>

            {e.cartoes.length > 0 && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-cartoes>
                    {e.cartoes.map((c) => (
                        <div key={c.chave + c.rotulo} className={cls('border-l-4 bg-white p-4 shadow-sm', RAIO, CORES[c.cor ?? 'gray'] ?? CORES.gray)}>
                            <p className="text-xs font-bold uppercase tracking-wider">{c.rotulo}</p>
                            <p className="mt-1 text-xl font-bold tabular-nums text-slate-900">{formatar(valor(dados, c.chave), c.formato ?? 'dinheiro')}</p>
                        </div>
                    ))}
                </div>
            )}

            {/* Com mais do que uma tabela, cada uma leva o seu CSV: o de cima
                exporta a primeira, e antes disto não havia como levar as
                outras — nos ajustes de stock, era o mapa de movimentos que
                ficava de fora. */}
            {e.tabelas.map((tabela) => (
                <TabelaDoMapa
                    key={tabela.chave + (tabela.titulo ?? '')}
                    tabela={tabela}
                    dados={dados}
                    csv={csv && e.tabelas.length > 1 ? `${csv}?${new URLSearchParams({ ...filtros, tabela: tabela.chave }).toString()}` : null}
                />
            ))}
        </div>
    );
}

function CampoDeFiltro({ f, valor: v, aoMudar, procura, porProcura, sugestoes, escolhido, aoEscolher, aoLimpar }: {
    f: Filtro; valor: string; aoMudar: (v: string) => void; procura: string; porProcura: (v: string) => void;
    sugestoes: Array<{ id: number; name: string; nif: string | null }>; escolhido?: string; aoEscolher: (id: number, nome: string) => void; aoLimpar: () => void;
}) {
    if (f.tipo === 'entidade') {
        return (
            <Campo etiqueta={f.rotulo} className="sm:col-span-2">
                {escolhido ? (
                    <span className="flex items-center gap-2"><Etiqueta cor="primaria" icone="fa-user">{escolhido}</Etiqueta><button type="button" onClick={aoLimpar} className="text-xs text-slate-500 underline">{t('trocar')}</button></span>
                ) : (
                    <span className="relative block">
                        <input value={procura} onChange={(ev) => porProcura(ev.target.value)} placeholder={t('Nome ou NIF')} className={entrada} aria-autocomplete="list" />
                        {sugestoes.length > 0 && (
                            <ul className={cls('absolute z-10 mt-1 max-h-60 w-full overflow-auto border border-slate-200 bg-white shadow-lg', RAIO)} role="listbox">
                                {sugestoes.map((s) => <li key={s.id}><button type="button" role="option" aria-selected={false} onClick={() => aoEscolher(s.id, s.name)} className="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50">{s.name}{s.nif && <span className="ml-2 font-mono text-xs text-slate-400">{s.nif}</span>}</button></li>)}
                            </ul>
                        )}
                    </span>
                )}
            </Campo>
        );
    }
    if (f.tipo === 'select') {
        return (
            <Campo etiqueta={f.rotulo}>
                <select value={v} onChange={(ev) => aoMudar(ev.target.value)} className={entrada}>
                    {(f.opcoes ?? []).every((o) => o.valor !== '') && <option value="">{t('Todos')}</option>}
                    {(f.opcoes ?? []).map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                </select>
            </Campo>
        );
    }
    return (
        <Campo etiqueta={f.rotulo}>
            <input type={f.tipo === 'date' ? 'date' : f.tipo === 'number' ? 'number' : 'text'} value={v} onChange={(ev) => aoMudar(ev.target.value)} className={entrada} />
        </Campo>
    );
}

function TabelaDoMapa({ tabela, dados, csv }: { tabela: Tabela; dados: Dados; csv?: string | null }) {
    const linhas = linhasDe(dados, tabela.chave);
    const alinhar = (c: Coluna) => (c.alinhar === 'direita' ? 'text-right' : c.alinhar === 'centro' ? 'text-center' : 'text-left');

    return (
        <Cartao
            titulo={tabela.titulo}
            semPadding
            accoes={csv ? (
                <a
                    href={csv}
                    data-csv-tabela={tabela.chave}
                    className={cls('inline-flex items-center gap-2 border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50', RAIO)}
                >
                    <i className="fas fa-file-csv" aria-hidden="true" />
                    CSV
                </a>
            ) : undefined}
        >
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-600">
                            {tabela.numerada && <th className="px-3 py-2 font-semibold">#</th>}
                            {tabela.colunas.map((c) => <th key={c.chave + c.rotulo} className={cls('px-3 py-2 font-semibold', alinhar(c))}>{c.rotulo}</th>)}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {linhas.length === 0 && <tr><td colSpan={tabela.colunas.length + (tabela.numerada ? 1 : 0)} className="px-3 py-8 text-center text-slate-400">{tabela.vazio ?? t('Nada a mostrar neste período.')}</td></tr>}
                        {linhas.map((l, i) => (
                            <tr key={i}>
                                {tabela.numerada && <td className="px-3 py-2 text-slate-400">{i + 1}</td>}
                                {tabela.colunas.map((c) => <td key={c.chave + c.rotulo} className={cls('px-3 py-2', alinhar(c), (c.formato === 'dinheiro' || c.formato === 'inteiro' || c.formato === 'numero' || c.formato === 'percentagem') && 'tabular-nums')}><Valor v={valor(l, c.chave)} formato={c.formato} /></td>)}
                            </tr>
                        ))}
                    </tbody>
                    {tabela.rodape && linhas.length > 0 && (
                        <tfoot>
                            <tr className="border-t-2 border-slate-300 bg-slate-50 font-bold">
                                {tabela.numerada && <td className="px-3 py-2"></td>}
                                {tabela.colunas.map((c, i) => {
                                    const caminho = tabela.rodape?.[c.chave];
                                    return <td key={c.chave + c.rotulo} className={cls('px-3 py-2 tabular-nums', alinhar(c))}>{caminho ? formatar(valor(dados, caminho), c.formato ?? 'dinheiro') : i === 0 ? t('Total') : ''}</td>;
                                })}
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </Cartao>
    );
}
