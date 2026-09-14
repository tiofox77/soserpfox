import { useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { relatorios, type Coluna, type Dados, type Esquema, type Filtro, type Formato, type Tabela } from '@/api/relatorios';
import { ErroDaApi } from '@/api/cliente';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { CORES, FOCO, RAIO, cls, type Cor } from '@/ui/tokens';
import { t } from '@/i18n';
import { ACCAO_DA_FAIXA, Faixa, SemNada, cascata } from './faixa';

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

/**
 * O TOM DE CADA CARTÃO DE TOPO — pelo SIGNIFICADO, que já vem do servidor.
 *
 * Cada mapa diz a cor dos seus números (`Relatorios\Base::cartao`), e é uma
 * escolha com sentido: o pendente é vermelho, o lucro é verde, o subtotal é
 * cinzento porque não é conclusão nenhuma. Aqui só se traduz o nome que o
 * servidor usa para o tom do `CartaoNumero` — a paleta é a da casa, e não há
 * uma segunda.
 */
const TONS: Record<string, TomDoCartao> = {
    blue: 'azul', gray: 'cinza', purple: 'roxo', green: 'verde', red: 'vermelho',
    orange: 'laranja', yellow: 'ambar', pink: 'roxo', indigo: 'indigo',
};

/**
 * E O ÍCONE, PELO FORMATO.
 *
 * Vai porque a cor não pode andar sozinha: quem não distingue o âmbar do
 * verde tem de conseguir ler o cartão à mesma. O formato é o que se sabe de
 * cada número sem saber de que mapa ele é.
 */
const ICONES: Record<string, string> = {
    dinheiro: 'fa-money-bill-wave',
    inteiro: 'fa-hashtag',
    numero: 'fa-calculator',
    percentagem: 'fa-percent',
    dias: 'fa-clock',
    data: 'fa-calendar-day',
};

/** Um cartão de topo de um mapa — o mesmo nos 23 e nos gráficos. */
export function CartaoDoMapa({ rotulo, cor, formato, valor: v }: { rotulo: string; cor?: string; formato?: Formato; valor: unknown }) {
    return (
        <CartaoNumero
            rotulo={rotulo}
            tom={TONS[cor ?? 'gray'] ?? 'cinza'}
            icone={ICONES[formato ?? 'dinheiro'] ?? 'fa-chart-simple'}
            valor={formatar(v, formato ?? 'dinheiro')}
        />
    );
}

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

    const { esquema: e, dados, atalhos, csv, pdf } = q.data;
    const mudar = (nome: string, v: string) => porFiltros((f) => ({ ...f, [nome]: v }));
    const intervalo = dados.intervalo;
    const csvUrl = csv ? `${csv}?${new URLSearchParams(filtros).toString()}` : null;

    return (
        <div className={cls('space-y-4', q.isFetching && 'opacity-70')} data-relatorio={e.slug}>
            <Faixa
                icone="fa-chart-bar"
                titulo={e.titulo}
                subtitulo={e.descricao}
                accoes={
                    <>
                        <a href="/invoicing/reports" className={ACCAO_DA_FAIXA}><i className="fas fa-arrow-left" aria-hidden="true" />{t('Todos os relatórios')}</a>
                        {csvUrl && <a href={csvUrl} className={ACCAO_DA_FAIXA} data-csv><i className="fas fa-file-csv" aria-hidden="true" />CSV</a>}
                        {/* O PAPEL PRÓPRIO DO MAPA — o extracto de conta com
                            cabeçalho e saldo transportado. A morada vem do
                            servidor já com os filtros traduzidos, e só quando
                            há o que imprimir (um titular escolhido): o
                            controlador existia e nenhum ecrã o oferecia. */}
                        {pdf && <a href={pdf} target="_blank" rel="noreferrer" className={ACCAO_DA_FAIXA} data-pdf-do-mapa><i className="fas fa-file-pdf" aria-hidden="true" />PDF</a>}
                        <button type="button" onClick={() => window.print()} className={ACCAO_DA_FAIXA}><i className="fas fa-print" aria-hidden="true" />{t('Imprimir')}</button>
                    </>
                }
            />

            <Cartao titulo={t('Filtros')} icone="fa-filter">
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
                    {e.cartoes.map((c, i) => (
                        <div key={c.chave + c.rotulo} className="entra" style={cascata(i)}>
                            <CartaoDoMapa rotulo={c.rotulo} cor={c.cor} formato={c.formato} valor={valor(dados, c.chave)} />
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

/** O quadrado de uma acção de linha: fundo suave da cor do que ela faz. */
const accao = (cor: Cor) =>
    cls('grid h-9 w-9 flex-none place-items-center rounded-lg transition-all duration-200 hover:scale-110', CORES[cor].suave, FOCO);

/**
 * UMA CÉLULA QUE LEVA AO DOCUMENTO DA LINHA (formato `ligacao`).
 *
 * O texto é o mesmo que o CSV exporta; ao lado, os quadrados de sempre — o
 * verde pré-visualiza e imprime, o vermelho é o PDF —, como na lista das
 * transferências. As moradas são campos DA LINHA, e uma linha que não tem
 * morada (um movimento sem lote) mostra só o texto: um ícone que dá 404 é
 * pior do que nenhum.
 */
function CelulaDeLigacao({ linha, coluna }: { linha: unknown; coluna: Coluna }) {
    const texto = formatar(valor(linha, coluna.chave));
    const morada = (caminho?: string): string | null => {
        const m = caminho ? valor(linha, caminho) : null;
        return typeof m === 'string' && m !== '' ? m : null;
    };
    const abrir = morada(coluna.ligacao?.abrir);
    const previsao = morada(coluna.ligacao?.previsao);
    const pdf = morada(coluna.ligacao?.pdf);

    return (
        <span className="inline-flex items-center gap-1.5 whitespace-nowrap">
            {abrir ? (
                <a href={abrir} target="_blank" rel="noreferrer" className={cls('font-semibold text-indigo-600 hover:underline', FOCO)}>{texto}</a>
            ) : (
                <span>{texto}</span>
            )}
            {previsao && (
                <a href={previsao} target="_blank" rel="noreferrer" title={t('Pré-visualizar / Imprimir')} aria-label={t('Pré-visualizar :referencia', { referencia: texto })} className={accao('bom')} data-ligacao-previsao>
                    <i className="fas fa-print" aria-hidden="true" />
                </a>
            )}
            {pdf && (
                <a href={pdf} target="_blank" rel="noreferrer" title={t('PDF')} aria-label={t('PDF de :referencia', { referencia: texto })} className={accao('perigo')} data-ligacao-pdf>
                    <i className="fas fa-file-pdf" aria-hidden="true" />
                </a>
            )}
        </span>
    );
}

function TabelaDoMapa({ tabela, dados, csv }: { tabela: Tabela; dados: Dados; csv?: string | null }) {
    const linhas = linhasDe(dados, tabela.chave);
    const alinhar = (c: Coluna) => (c.alinhar === 'direita' ? 'text-right' : c.alinhar === 'centro' ? 'text-center' : 'text-left');

    return (
        <Cartao
            titulo={tabela.titulo}
            icone="fa-list"
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
                        {linhas.length === 0 && (
                            <tr>
                                <td colSpan={tabela.colunas.length + (tabela.numerada ? 1 : 0)}>
                                    <SemNada icone="fa-table-list" titulo={tabela.vazio ?? t('Nada a mostrar neste período.')} frase={t('Alargue as datas ou mude os filtros lá em cima.')} />
                                </td>
                            </tr>
                        )}
                        {linhas.map((l, i) => (
                            /* A linha entra um instante depois da anterior, e
                               acende ao passar o rato: é o que o mapa em Blade
                               fazia, e é o que diz onde está o cursor numa
                               tabela de trinta colunas. */
                            <tr key={i} className="entra transition-all duration-200 hover:bg-indigo-50/60" style={cascata(i)}>
                                {tabela.numerada && <td className="px-3 py-2 text-slate-400">{i + 1}</td>}
                                {tabela.colunas.map((c) => <td key={c.chave + c.rotulo} className={cls('px-3 py-2', alinhar(c), (c.formato === 'dinheiro' || c.formato === 'inteiro' || c.formato === 'numero' || c.formato === 'percentagem') && 'tabular-nums')}>{c.formato === 'ligacao' ? <CelulaDeLigacao linha={l} coluna={c} /> : <Valor v={valor(l, c.chave)} formato={c.formato} />}</td>)}
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
