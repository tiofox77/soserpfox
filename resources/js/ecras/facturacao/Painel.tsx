import { useQuery } from '@tanstack/react-query';

import { painel, type NumerosDoPainel } from '@/api/painel';
import { ErroDaApi } from '@/api/cliente';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { CARTAO, RAIO, cls, data, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O PDF e o CSV vivem em `/js/painel-facturacao.js`, carregado pelo layout.
 *
 * Não se refez a mecânica: ela já lá está, já foi corrigida uma vez (um
 * `<script>` em linha não volta a correr quando se chega aqui pela barra
 * lateral) e continua a servir. O que mudou foi quem lhe põe a mesa — era o
 * Blade, é agora este ecrã.
 */
declare global {
    interface Window {
        exportToPDF?: () => void | Promise<void>;
        exportToExcel?: () => void;
    }
}

/**
 * O PAINEL DA FACTURAÇÃO.
 *
 * Não faz contas nenhumas: os números vêm do `PainelDaFacturacao`, o mesmo
 * serviço que o painel em Blade usa. Era essa a condição para os dois
 * existirem ao mesmo tempo sem se contradizerem.
 *
 * E a regra que esses números guardam: conta-se pelo SALDO, nunca pelo nome do
 * estado. Nomear os estados que contam foi o que partiu o painel de origem —
 * dizia 76 mil por cobrar quando havia 15 milhões.
 */
export default function Painel() {
    const numeros = useQuery({
        queryKey: ['painel'],
        queryFn: painel.numeros,
        staleTime: 60_000,
    });

    if (numeros.isPending) {
        return <Carregando linhas={8} />;
    }

    if (numeros.isError) {
        return <Falhou erro={numeros.error} />;
    }

    const d = numeros.data;
    const anoActual = new Date().getFullYear();

    return (
        <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Numero
                    rotulo={t('Faturação do Mês')}
                    valor={d.stats.total_invoiced}
                    variacao={d.stats.growth}
                />
                <Numero
                    rotulo={t('Recebimentos')}
                    valor={d.stats.total_received}
                    nota={t('Pagamentos recebidos')}
                    cor="bom"
                />
                <Numero
                    rotulo={t('Valores Pendentes')}
                    valor={d.stats.total_pending}
                    nota={t('Aguardando pagamento')}
                    cor="aviso"
                />
                <Numero
                    rotulo={t('Valores Vencidos')}
                    valor={d.stats.total_overdue}
                    nota={t('Requer atenção')}
                    cor="perigo"
                />
            </div>

            {/* ANO A ANO, ATÉ AO MESMO DIA. Comparar um ano a meio com um ano
                inteiro daria sempre uma queda que não existe. */}
            <Cartao
                titulo={t('Evolução de Vendas - :periodo', { periodo: t('Este Ano') })}
                accoes={<Exportar />}
            >
                <div className="mb-5 flex flex-wrap items-baseline gap-x-6 gap-y-2">
                    <div>
                        <span className="text-2xl font-bold tabular-nums text-slate-900">
                            {kz(d.stats.year_invoiced)}
                        </span>
                        <span className="ml-1 text-sm text-slate-500">Kz · {anoActual}</span>
                    </div>
                    <div className="text-sm text-slate-500">
                        {t('Comparação Ano a Ano')}:{' '}
                        <span className="tabular-nums">{kz(d.stats.year_invoiced_previous)}</span>{' '}
                        ({t('até hoje')})
                    </div>
                    <Variacao valor={d.stats.year_growth} />
                </div>

                <GraficoDeBarras dados={d.por_mes} titulo={t('Vendas (AOA)')} />
            </Cartao>

            <div className="grid gap-4 lg:grid-cols-2">
                <Cartao titulo={t('Estado das Faturas')}>
                    {/* Não se sobrepõem: uma factura cai numa caixa e numa só. */}
                    <p className="mb-3 text-xs text-slate-500">{t('Vencidas contadas à parte')}</p>
                    <div className="grid grid-cols-2 gap-3">
                        <Caixa rotulo={t('Pagas')} valor={d.estado_das_facturas.paid} cor="bom" />
                        <Caixa rotulo={t('Pendentes')} valor={d.estado_das_facturas.pending} cor="primaria" />
                        <Caixa
                            rotulo={t('Parc. Pagas')}
                            valor={d.estado_das_facturas.partially_paid}
                            cor="aviso"
                        />
                        <Caixa rotulo={t('Vencidas')} valor={d.estado_das_facturas.overdue} cor="perigo" />
                    </div>

                    <div className="mt-4 flex flex-wrap gap-x-5 gap-y-1 border-t border-slate-100 pt-4 text-sm text-slate-600">
                        <Conta rotulo={t('Faturas')} valor={d.documentos.invoices} />
                        <Conta rotulo={t('Recibos')} valor={d.documentos.receipts} />
                        <Conta rotulo={t('Notas Crédito')} valor={d.documentos.credit_notes} />
                        <Conta rotulo={t('Notas Débito')} valor={d.documentos.debit_notes} />
                        <Conta rotulo={t('Adiantamentos')} valor={d.documentos.advances} />
                    </div>
                </Cartao>

                <Cartao titulo={t('Top 5 Clientes')}>
                    {d.melhores_clientes.length === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-400">
                            {t('Sem dados de clientes')}
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {d.melhores_clientes.map((c, i) => (
                                <li key={i} className="flex items-center justify-between gap-4 py-2.5">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-slate-800">
                                            {c.cliente}
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            {t(':quantos documento(s)', { quantos: c.documentos })}
                                        </p>
                                    </div>
                                    <span className="shrink-0 text-sm font-bold tabular-nums text-slate-900">
                                        {kz(c.total)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Cartao>
            </div>

            <Cartao titulo={t('Faturas Pendentes')} semPadding>
                {d.por_cobrar.length === 0 ? (
                    <p className="px-5 py-10 text-center text-sm text-slate-400">
                        {t('Nenhuma fatura pendente')}
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <th className="px-4 py-3 font-semibold">{t('Número')}</th>
                                    <th className="px-4 py-3 font-semibold">{t('Cliente')}</th>
                                    <th className="px-4 py-3 font-semibold">{t('Vencimento')}</th>
                                    <th className="px-4 py-3 text-right font-semibold">{t('Saldo')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.por_cobrar.map((f) => (
                                    <tr key={f.id} className="transition hover:bg-slate-50">
                                        <td className="px-4 py-3">
                                            <a
                                                href={`/invoicing/sales/invoices/${f.id}`}
                                                className="font-semibold text-indigo-700 hover:underline"
                                            >
                                                {f.numero}
                                            </a>
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">{f.cliente}</td>
                                        <td className="px-4 py-3">
                                            <span className="tabular-nums text-slate-600">
                                                {data(f.vencimento)}
                                            </span>
                                            {f.vencida && (
                                                <Etiqueta cor="perigo">
                                                    <span className="ml-2">{t('Vencida')}</span>
                                                </Etiqueta>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right font-bold tabular-nums text-slate-900">
                                            {kz(f.saldo)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Cartao>

            <TextosDaExportacao numeros={d} />
        </div>
    );
}

/* ─── Exportar ────────────────────────────────────────────────────────── */

/**
 * OS DOIS BOTÕES QUE O PAINEL EM BLADE TINHA, e que a migração perdeu.
 *
 * Chamam o que já existe em `/js/painel-facturacao.js`. Sair daqui a construir
 * outro PDF era ter duas mecânicas a fazer a mesma coisa — e um dos dois a
 * ficar para trás no primeiro ajuste.
 */
function Exportar() {
    return (
        <>
            <Botao
                cor="perigo"
                altura="pequeno"
                icone="fa-file-pdf"
                onClick={() => void window.exportToPDF?.()}
            >
                {t('Exportar PDF')}
            </Botao>
            {/* «Excel» fica fora do t(): é nome de produto, como FOX Friendly.
                Escreve-se igual nas três línguas. */}
            <Botao cor="bom" altura="pequeno" icone="fa-file-excel" onClick={() => window.exportToExcel?.()}>
                Excel
            </Botao>
        </>
    );
}

/**
 * A MESA POSTA PARA O EXPORTADOR: os mesmos nós, com os mesmos nomes.
 *
 * O `painel-facturacao.js` lê tudo do DOM e não de dentro de si — foi assim
 * que sobreviveu a uma navegação que não recarrega a página. Portanto o ecrã
 * não lhe passa nada por argumento: escreve os nós e ele encontra-os.
 *
 *   `textosPainel` → a etiqueta do `Intl`, as frases já traduzidas e os
 *                    valores (formatados para o PDF, CRUS para o CSV).
 *   `dadosVendas`  → as linhas do CSV, uma por mês.
 *
 * O `dadosPainel` de propósito NÃO se escreve: era só para as roscas em
 * `<canvas>` que este ecrã não tem, e a sua simples presença fazia o
 * `painel-facturacao.js` descarregar o Chart.js inteiro para não desenhar
 * nada.
 *
 * OS VALORES DO CSV NÃO LEVAM SEPARADOR DE MILHARES. Um `1.234,56` dentro de
 * um ficheiro separado por vírgulas abre na folha de cálculo com uma coluna a
 * mais e o valor partido em dois — daí o `toFixed`, que escreve sempre
 * `1234.56` seja qual for a língua.
 */
function TextosDaExportacao({ numeros }: { numeros: NumerosDoPainel }) {
    const s = numeros.stats;
    const cru = (v: number) => (Number.isFinite(v) ? v : 0).toFixed(2);

    const textos = {
        intl: etiquetaIntl(),
        t: {
            vendasAoa: t('Vendas (AOA)'),
            vendas: t('Vendas'),
            compras: t('Compras'),
            titulo: t('Dashboard de Faturação'),
            // Sem substituição: o `:data` é o exportador que o preenche, com
            // a data do dia em que se carregou no botão.
            geradoEm: t('Gerado em: :data'),
            facturado: t('Faturação do Mês'),
            recebido: t('Recebimentos'),
            pendente: t('Valores Pendentes'),
            vencido: t('Valores Vencidos'),
            data: t('Data'),
            valorAoa: t('Valor (AOA)'),
            estatisticas: t('Estatísticas'),
        },
        valores: {
            facturado: kz(s.total_invoiced),
            recebido: kz(s.total_received),
            pendente: kz(s.total_pending),
            vencido: kz(s.total_overdue),
            facturadoCru: cru(s.total_invoiced),
            recebidoCru: cru(s.total_received),
            pendenteCru: cru(s.total_pending),
            vencidoCru: cru(s.total_overdue),
        },
    };

    /*
     * O ano mês a mês, na forma que o exportador já sabe ler: uma data e um
     * total. A hora vai escrita para a data ser lida na zona de quem está a
     * olhar — sem ela, `2026-01-01` é meia-noite em UTC e a Ocidente do
     * meridiano lê-se 31 de Dezembro.
     */
    const linhas = numeros.por_mes.map((m, i) => ({
        date: `${new Date().getFullYear()}-${String(i + 1).padStart(2, '0')}-01T00:00:00`,
        total: m.valor,
        rotulo: m.rotulo,
    }));

    return (
        <>
            <script type="application/json" id="textosPainel" dangerouslySetInnerHTML={emJson(textos)} />
            <script type="application/json" id="dadosVendas" dangerouslySetInnerHTML={emJson(linhas)} />
        </>
    );
}

/** JSON dentro de `<script>`: um `</script>` no meio dos dados fechava a etiqueta. */
function emJson(valor: unknown): { __html: string } {
    return { __html: JSON.stringify(valor).replace(/</g, '\\u003c') };
}

/* ─── Peças ───────────────────────────────────────────────────────────── */

function Numero({
    rotulo,
    valor,
    variacao,
    nota,
    cor = 'primaria',
}: {
    rotulo: string;
    valor: number;
    variacao?: number;
    nota?: string;
    cor?: 'primaria' | 'bom' | 'aviso' | 'perigo';
}) {
    const risca = {
        primaria: 'border-l-indigo-500',
        bom: 'border-l-emerald-500',
        aviso: 'border-l-amber-500',
        perigo: 'border-l-red-500',
    } as const;

    return (
        <div className={cls(CARTAO, 'border-l-4 p-4', risca[cor])}>
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{rotulo}</p>
            <p className="mt-1.5 text-2xl font-bold tabular-nums text-slate-900">
                {kz(valor)}
                <span className="ml-1 text-sm font-normal text-slate-400">Kz</span>
            </p>
            {variacao !== undefined && (
                <p className="mt-1 flex items-center gap-1.5 text-xs">
                    <Variacao valor={variacao} frase="mes" />
                </p>
            )}
            {nota !== undefined && <p className="mt-1 text-xs text-slate-400">{nota}</p>}
        </div>
    );
}

/**
 * A percentagem e a frase que a acompanha SÃO UMA CHAVE SÓ.
 *
 * «12,3% vs mês anterior» partido em dois pedaços obrigava o tradutor a
 * adivinhar a ordem das palavras, e há línguas onde ela não é a portuguesa.
 * Por isso a frase inteira vai ao dicionário com o `:pct` lá dentro.
 */
function Variacao({ valor, frase }: { valor: number; frase?: 'mes' }) {
    // Zero não é subida nem descida, e pintá-lo de verde seria dizer que sim.
    if (Math.abs(valor) < 0.05) {
        return <span className="text-xs font-semibold text-slate-400">{t('Sem alteração')}</span>;
    }

    const sobe = valor > 0;
    const pct = Math.abs(valor).toFixed(1);

    return (
        <span
            className={cls(
                'text-xs font-semibold tabular-nums',
                sobe ? 'text-emerald-600' : 'text-red-600',
            )}
        >
            <i className={`fas fa-arrow-${sobe ? 'up' : 'down'} mr-1`} aria-hidden="true" />
            {frase === 'mes' ? t(':pct% vs mês anterior', { pct }) : `${pct}%`}
        </span>
    );
}

function Caixa({
    rotulo,
    valor,
    cor,
}: {
    rotulo: string;
    valor: number;
    cor: 'primaria' | 'bom' | 'aviso' | 'perigo';
}) {
    const fundo = {
        primaria: 'bg-indigo-50 text-indigo-800',
        bom: 'bg-emerald-50 text-emerald-800',
        aviso: 'bg-amber-50 text-amber-800',
        perigo: 'bg-red-50 text-red-800',
    } as const;

    return (
        <div className={cls('px-4 py-3', RAIO, fundo[cor])}>
            <p className="text-2xl font-bold tabular-nums">{valor}</p>
            <p className="text-xs font-medium">{rotulo}</p>
        </div>
    );
}

function Conta({ rotulo, valor }: { rotulo: string; valor: number }) {
    return (
        <span>
            <strong className="tabular-nums text-slate-900">{valor}</strong>{' '}
            <span className="text-slate-500">{rotulo}</span>
        </span>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    if (daApi?.eSessaoMorta) {
        return (
            <div className={cls('border border-amber-200 bg-amber-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-amber-900">{t('A sessão expirou')}</h2>
                <p className="mb-4 text-sm text-amber-900">{t('Entre outra vez para continuar.')}</p>
                <Botao cor="primaria" tom="solida" onClick={() => window.location.reload()}>
                    {t('Voltar a entrar')}
                </Botao>
            </div>
        );
    }

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível carregar o painel')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
