import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';

import { api } from '@/api/cliente';
import { etiquetaIntl, t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { Modal } from '@/ui/Modal';
import { RAIO, cls, data, kz, type CorDeEcra } from '@/ui/tokens';

/**
 * O EXTRATO DE UM CLIENTE OU DE UM FORNECEDOR.
 *
 * O modal de ver que o ecrã em Blade tinha (361 e 351 linhas) e que a migração
 * não trouxe: a ficha, as contas, as últimas facturas, os artigos que mais leva
 * e a frequência mês a mês. São as duas perguntas que se fazem antes de dar
 * crédito ou negociar um preço — quanto já comprou, e de quanto em quanto tempo
 * volta.
 *
 * CLIENTE E FORNECEDOR SÃO A MESMA JANELA vista dos dois lados: um compra-nos e
 * o outro vende-nos, e as contas do servidor já vêm com a mesma forma. Escrita
 * duas vezes, a segunda ficava para trás à primeira correcção — foi o que
 * aconteceu ao estado vazio, que existiu em duas versões no mesmo dia.
 */

export type ExtratoDeParte = {
    resumo: {
        documentos: number;
        facturado: number;
        pago: number;
        pendente: number;
        ticket_medio: number;
        primeira: string | null;
        ultima: string | null;
        dias_entre: number;
    };
    documentos: Array<{
        id: number;
        numero: string;
        data: string | null;
        vencimento: string | null;
        total: number;
        pago: number;
        saldo: number;
        estado: string;
        estado_rotulo: string;
        estado_cor: string;
    }>;
    artigos: Array<{ nome: string; quantidade: number; total: number; documentos: number }>;
    frequencia: Array<{ periodo: string; quantos: number; total: number }>;
    extras: Array<{ chave: string; rotulo: string; quantos: number; total: number }>;
};

/** Uma linha da ficha: rótulo à esquerda, valor à direita. */
export function Dado({ rotulo, valor }: { rotulo: string; valor?: string | number | null }) {
    return (
        <div className="flex items-start gap-2 text-sm">
            <span className="w-28 flex-none text-slate-500">{rotulo}</span>
            <span className="min-w-0 break-words font-semibold text-slate-900">
                {valor === null || valor === undefined || valor === '' ? '—' : valor}
            </span>
        </div>
    );
}

/** Uma secção da ficha, com título e ícone. */
export function Seccao({
    titulo,
    icone,
    children,
}: {
    titulo: string;
    icone: string;
    children: React.ReactNode;
}) {
    return (
        <section className={cls('border border-slate-200 bg-slate-50 p-4', RAIO)}>
            <h4 className="mb-3 flex items-center gap-2 text-sm font-bold text-slate-800">
                <i className={`fas ${icone} text-slate-400`} aria-hidden="true" />
                {titulo}
            </h4>
            <div className="space-y-1.5">{children}</div>
        </section>
    );
}

/**
 * A JANELA INTEIRA.
 *
 * `ficha` é o que muda entre um cliente e um fornecedor — os campos da
 * identificação — e vem de fora: o resto (contas, documentos, artigos,
 * frequência) é igual e desenha-se aqui.
 */
export function JanelaDoExtrato({
    aberto,
    aoFechar,
    titulo,
    subtitulo,
    icone,
    cor,
    caminho,
    chave,
    ficha,
    rotulos,
    moradaDoDocumento,
    podeEditar,
    aoEditar,
}: {
    aberto: boolean;
    aoFechar: () => void;
    titulo: string;
    subtitulo?: string;
    icone: string;
    cor: CorDeEcra;
    /** O endereço do extrato — `/clients/12/extrato`, `/catalogos/…/extrato`. */
    caminho: string;
    /** A chave da cache: muda com a parte que está aberta. */
    chave: unknown[];
    /** Os campos da identificação, que são o que difere entre os dois. */
    ficha: React.ReactNode;
    /** As palavras do lado certo: um cliente COMPRA, um fornecedor VENDE. */
    rotulos: { facturado: string; documentos: string; artigos: string; semDocumentos: string };
    /** Para onde vai o número do documento quando se carrega nele. */
    moradaDoDocumento: (id: number) => string;
    podeEditar?: boolean;
    aoEditar?: () => void;
}) {
    const [separador, porSeparador] = useState<'ficha' | 'extrato' | 'artigos' | 'frequencia'>('ficha');

    const q = useQuery({
        queryKey: chave,
        queryFn: () => api.ler<ExtratoDeParte>(caminho),
        enabled: aberto,
    });

    if (!aberto) {
        return null;
    }

    const e = q.data;

    const separadores = [
        { chave: 'ficha', rotulo: t('Ficha'), icone: 'fa-id-card' },
        { chave: 'extrato', rotulo: t('Extrato'), icone: 'fa-file-invoice' },
        { chave: 'artigos', rotulo: t('Produtos'), icone: 'fa-box' },
        { chave: 'frequencia', rotulo: t('Frequência'), icone: 'fa-chart-column' },
    ] as const;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            largura="xl"
            icone={icone}
            cor={cor}
            titulo={titulo}
            subtitulo={subtitulo}
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Fechar')}</Botao>
                    {podeEditar && aoEditar && (
                        <Botao cor="primaria" tom="solida" icone="fa-pen" onClick={aoEditar}>
                            {t('Editar')}
                        </Botao>
                    )}
                </>
            }
        >
            {/* OS SEPARADORES do ecrã de sempre. São quatro assuntos
                diferentes: quem quer o contacto não quer percorrer 20 facturas
                para lá chegar. */}
            <div role="tablist" className="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
                {separadores.map((s) => (
                    <button
                        key={s.chave}
                        type="button"
                        role="tab"
                        aria-selected={separador === s.chave}
                        onClick={() => porSeparador(s.chave)}
                        className={cls(
                            'flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-semibold transition-all duration-200',
                            separador === s.chave
                                ? 'border-indigo-600 text-indigo-700'
                                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700',
                        )}
                    >
                        <i className={`fas ${s.icone}`} aria-hidden="true" />
                        {s.rotulo}
                    </button>
                ))}
            </div>

            {separador === 'ficha' && <div className="grid gap-4 md:grid-cols-2">{ficha}</div>}

            {separador !== 'ficha' &&
                (q.isPending || !e ? (
                    <Carregando />
                ) : separador === 'extrato' ? (
                    <div className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <CartaoNumero
                                aspecto="claro"
                                rotulo={rotulos.documentos}
                                tom="indigo"
                                icone="fa-file-invoice"
                                valor={e.resumo.documentos.toLocaleString(etiquetaIntl())}
                                nota={t('Documentos emitidos')}
                            />
                            <CartaoNumero
                                aspecto="claro"
                                rotulo={rotulos.facturado}
                                tom="azul"
                                icone="fa-coins"
                                sufixo="Kz"
                                valor={kz(e.resumo.facturado)}
                                nota={t('Valor total bruto')}
                            />
                            <CartaoNumero
                                aspecto="claro"
                                rotulo={t('Pago')}
                                tom="verde"
                                icone="fa-circle-check"
                                sufixo="Kz"
                                valor={kz(e.resumo.pago)}
                                nota={t('Total recebido')}
                            />
                            <CartaoNumero
                                aspecto="claro"
                                rotulo={t('Em Dívida')}
                                tom={e.resumo.pendente > 0 ? 'vermelho' : 'cinza'}
                                icone="fa-triangle-exclamation"
                                sufixo="Kz"
                                valor={kz(e.resumo.pendente)}
                                nota={t('Valor pendente')}
                            />
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <Miudo rotulo={t('Ticket Médio')} valor={`${kz(e.resumo.ticket_medio)} Kz`} nota={t('Por fatura')} />
                            <Miudo
                                rotulo={t('Frequência')}
                                valor={t(':n dias', { n: e.resumo.dias_entre })}
                                nota={t('Média entre compras')}
                            />
                            <Miudo rotulo={t('Primeira Compra')} valor={data(e.resumo.primeira)} />
                            <Miudo rotulo={t('Última Compra')} valor={data(e.resumo.ultima)} />
                        </div>

                        {e.extras.length > 0 && (
                            <div className="grid gap-3 sm:grid-cols-2">
                                {e.extras.map((x) => (
                                    <Miudo
                                        key={x.chave}
                                        rotulo={x.rotulo}
                                        valor={`${kz(x.total)} Kz`}
                                        nota={t(':quantos documento(s)', { quantos: x.quantos })}
                                    />
                                ))}
                            </div>
                        )}

                        <section>
                            <h4 className="mb-2 text-sm font-bold text-slate-700">
                                <i className="fas fa-list mr-1.5 text-slate-400" aria-hidden="true" />
                                {t('Últimas :n facturas', { n: e.documentos.length })}
                            </h4>
                            <div className={cls('overflow-x-auto border border-slate-200', RAIO)}>
                                <table className="w-full min-w-[720px] text-sm">
                                    <thead className="bg-slate-50 text-xs text-slate-600">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold">{t('Nº Fatura')}</th>
                                            <th className="px-3 py-2 text-left font-semibold">{t('Data')}</th>
                                            <th className="px-3 py-2 text-left font-semibold">{t('Vencimento')}</th>
                                            <th className="px-3 py-2 text-right font-semibold">{t('Total')}</th>
                                            <th className="px-3 py-2 text-right font-semibold">{t('Pago')}</th>
                                            <th className="px-3 py-2 text-right font-semibold">{t('Saldo')}</th>
                                            <th className="px-3 py-2 text-left font-semibold">{t('Estado')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {e.documentos.length === 0 ? (
                                            <tr>
                                                <td colSpan={7} className="px-3 py-6 text-center text-slate-400">
                                                    {rotulos.semDocumentos}
                                                </td>
                                            </tr>
                                        ) : (
                                            e.documentos.map((d) => (
                                                <tr key={d.id} className="transition-colors hover:bg-indigo-50/50">
                                                    <td className="px-3 py-2 font-semibold">
                                                        <a
                                                            href={moradaDoDocumento(d.id)}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="text-indigo-600 hover:underline"
                                                        >
                                                            {d.numero}
                                                        </a>
                                                    </td>
                                                    <td className="whitespace-nowrap px-3 py-2 text-slate-500">{data(d.data)}</td>
                                                    <td className="whitespace-nowrap px-3 py-2 text-slate-500">{data(d.vencimento)}</td>
                                                    <td className="px-3 py-2 text-right tabular-nums">{kz(d.total)}</td>
                                                    <td className="px-3 py-2 text-right tabular-nums text-emerald-700">{kz(d.pago)}</td>
                                                    <td
                                                        className={cls(
                                                            'px-3 py-2 text-right font-bold tabular-nums',
                                                            d.saldo > 0.01 ? 'text-red-600' : 'text-slate-500',
                                                        )}
                                                    >
                                                        {kz(d.saldo)}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <Etiqueta cor={corDoEstado(d.estado_cor)} ponto>
                                                            {d.estado_rotulo}
                                                        </Etiqueta>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                ) : separador === 'artigos' ? (
                    <section>
                        <h4 className="mb-2 text-sm font-bold text-slate-700">
                            <i className="fas fa-star mr-1.5 text-slate-400" aria-hidden="true" />
                            {rotulos.artigos}
                        </h4>
                        <div className={cls('overflow-x-auto border border-slate-200', RAIO)}>
                            <table className="w-full min-w-[520px] text-sm">
                                <thead className="bg-slate-50 text-xs text-slate-600">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-semibold">{t('Produto')}</th>
                                        <th className="px-3 py-2 text-right font-semibold">{t('Qtd')}</th>
                                        <th className="px-3 py-2 text-right font-semibold">{t('Faturas')}</th>
                                        <th className="px-3 py-2 text-right font-semibold">{t('Total')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {e.artigos.length === 0 ? (
                                        <tr>
                                            <td colSpan={4} className="px-3 py-6 text-center text-slate-400">
                                                {t('Sem produtos comprados')}
                                            </td>
                                        </tr>
                                    ) : (
                                        e.artigos.map((p, i) => (
                                            <tr
                                                key={i}
                                                className="entra transition-colors hover:bg-indigo-50/50"
                                                style={{ '--i': Math.min(i, 12) } as React.CSSProperties}
                                            >
                                                <td className="px-3 py-2 font-medium text-slate-800">{p.nome}</td>
                                                <td className="px-3 py-2 text-right font-semibold tabular-nums">
                                                    {p.quantidade.toLocaleString(etiquetaIntl(), { maximumFractionDigits: 3 })}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums text-slate-500">{p.documentos}</td>
                                                <td className="px-3 py-2 text-right font-bold tabular-nums">{kz(p.total)}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ) : (
                    <section>
                        <h4 className="mb-3 text-sm font-bold text-slate-700">
                            <i className="fas fa-chart-column mr-1.5 text-slate-400" aria-hidden="true" />
                            {t('Frequência de Compra (últimos 12 meses)')}
                        </h4>
                        {e.frequencia.length === 0 ? (
                            <p className="py-10 text-center text-sm text-slate-400">{t('Sem dados de frequência')}</p>
                        ) : (
                            <GraficoDeBarras
                                dados={e.frequencia.map((f) => ({ rotulo: mes(f.periodo), valor: f.total }))}
                                titulo={t('Facturado por mês')}
                                altura={220}
                            />
                        )}
                    </section>
                ))}
        </Modal>
    );
}

/** Um número pequeno com o seu rótulo — não é um cartão, é uma nota. */
function Miudo({ rotulo, valor, nota }: { rotulo: string; valor: string; nota?: string }) {
    return (
        <div className={cls('border border-slate-200 bg-white p-3', RAIO)}>
            <p className="text-xs font-semibold text-slate-500">{rotulo}</p>
            <p className="text-lg font-bold tabular-nums text-slate-900">{valor}</p>
            {nota && <p className="text-xs text-slate-400">{nota}</p>}
        </div>
    );
}

/**
 * `2026-09` lê-se «set/26».
 *
 * O eixo de um gráfico de doze meses não tem espaço para «Setembro de 2026», e
 * o nome do mês vem na língua de quem está a ver.
 */
function mes(periodo: string): string {
    const [ano, m] = periodo.split('-');
    const d = new Date(Number(ano), Number(m) - 1, 1);

    return d.toLocaleDateString(etiquetaIntl(), { month: 'short', year: '2-digit' });
}

/**
 * A cor do estado, do servidor para a paleta das etiquetas.
 *
 * O modelo devolve nomes do Tailwind («yellow», «indigo») porque era assim que
 * o Blade os interpolava numa classe. Sem build não se interpola: traduz-se, e
 * um tom desconhecido cai no neutro em vez de sair sem cor nenhuma.
 */
function corDoEstado(cor: string): 'bom' | 'aviso' | 'perigo' | 'primaria' | 'neutra' {
    switch (cor) {
        case 'green':
            return 'bom';
        case 'yellow':
            return 'aviso';
        case 'red':
            return 'perigo';
        case 'blue':
        case 'indigo':
        case 'purple':
            return 'primaria';
        default:
            return 'neutra';
    }
}
