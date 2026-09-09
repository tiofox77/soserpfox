import { useQuery } from '@tanstack/react-query';

import { painel, type Aviso, type Serie } from '@/api/rh';
import { ErroDaApi } from '@/api/cliente';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { GraficoDeBarras } from '@/ui/GraficoDeBarras';
import { GraficoHorizontal } from '@/ui/GraficoHorizontal';
import { Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { etiquetaIntl, t } from '@/i18n';

/**
 * O PAINEL DO RH — o que está à espera de decisão, e o que a equipa custa.
 *
 * Um painel de RH não é uma lista de números bonitos: é a resposta a «o que é
 * que eu tenho de fazer hoje». Por isso OS AVISOS VÊM PRIMEIRO, cada um com o
 * botão que leva ao ecrã onde se resolve — e o servidor só manda os que quem
 * está a ver tem permissão para resolver. Mandar alguém para um 403 é pior do
 * que não avisar: parece uma avaria quando é a guarda a funcionar.
 */

const TOM: Record<string, TomDoCartao> = {
    bom: 'verde', aviso: 'ambar', perigo: 'vermelho', primaria: 'indigo', roxo: 'roxo', neutra: 'cinza',
};

const ESTILO_DO_AVISO: Record<string, string> = {
    bom: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    aviso: 'border-amber-200 bg-amber-50 text-amber-900',
    perigo: 'border-red-200 bg-red-50 text-red-900',
    primaria: 'border-indigo-200 bg-indigo-50 text-indigo-900',
    neutra: 'border-slate-200 bg-slate-50 text-slate-800',
};

const ESTADO_DA_FOLHA: Record<string, { rotulo: string; cor: string }> = {
    draft: { rotulo: 'Rascunho', cor: 'neutra' },
    processing: { rotulo: 'Em processamento', cor: 'primaria' },
    approved: { rotulo: 'Aprovada', cor: 'bom' },
    paid: { rotulo: 'Paga', cor: 'primaria' },
    cancelled: { rotulo: 'Anulada', cor: 'perigo' },
};

/** Uma série do servidor no formato que os gráficos pedem. */
const paraGrafico = (s: Serie) => s.etiquetas.map((rotulo, i) => ({ rotulo, valor: s.valores[i] ?? 0 }));

export default function Painel() {
    const q = useQuery({ queryKey: ['rh', 'painel'], queryFn: painel.ler, staleTime: 60_000 });

    if (q.isPending) return <Carregando linhas={10} />;
    if (q.isError) return <Falhou erro={q.error} />;

    const d = q.data;
    const numero = (n: number) => n.toLocaleString(etiquetaIntl());

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Recursos Humanos')}
                subtitulo={t('Quem está na casa, o que espera decisão, e o que a equipa custa')}
                icone="fa-id-badge"
                cor="roxo"
            />

            {/* O QUE ESTÁ À ESPERA DE ALGUÉM — antes dos números, porque é o
                que faz alguém abrir este ecrã. */}
            {d.avisos.length > 0 && (
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {d.avisos.map((a, i) => <AvisoDoPainel key={i} a={a} i={i} />)}
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero aspecto="claro" rotulo={t('Funcionários')} tom="indigo" icone="fa-users"
                    nota={t('nesta empresa')} valor={numero(d.cartoes.funcionarios)} />
                <CartaoNumero aspecto="claro" rotulo={t('Activos')} tom="verde" icone="fa-user-check"
                    valor={numero(d.cartoes.activos)} />
                <CartaoNumero aspecto="claro" rotulo={t('De férias hoje')} tom={d.cartoes.de_ferias > 0 ? 'ambar' : 'cinza'}
                    icone="fa-umbrella-beach" valor={numero(d.cartoes.de_ferias)} />
                <CartaoNumero aspecto="claro" rotulo={t('Picaram hoje')} tom="azul" icone="fa-clock"
                    nota={t(':n por marcar', { n: numero(Math.max(d.cartoes.activos - d.cartoes.presentes_hoje, 0)) })}
                    valor={numero(d.cartoes.presentes_hoje)} />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                {/* O CUSTO DA FOLHA, MÊS A MÊS: é a pergunta que a direcção
                    faz, e um número sozinho não diz se está a crescer. */}
                <div className="lg:col-span-2">
                    <Cartao titulo={t('Custo da folha, mês a mês')} icone="fa-chart-column">
                        <GraficoDeBarras dados={paraGrafico(d.graficos.custo_mensal)} titulo={t('Líquido pago (Kz)')} />
                    </Cartao>
                </div>

                <Cartao titulo={t('A última folha')} icone="fa-file-invoice-dollar">
                    {d.folha.existe ? (
                        <div className="space-y-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-bold capitalize text-slate-900">{d.folha.mes}</span>
                                {d.folha.estado && (
                                    <Etiqueta cor={(ESTADO_DA_FOLHA[d.folha.estado]?.cor ?? 'neutra') as never}>
                                        {t(ESTADO_DA_FOLHA[d.folha.estado]?.rotulo ?? d.folha.estado)}
                                    </Etiqueta>
                                )}
                            </div>

                            <ValorDaFolha rotulo={t('Bruto')} valor={d.folha.bruto} />
                            <ValorDaFolha rotulo={t('Descontos')} valor={d.folha.descontos} tom="text-rose-700" />
                            <ValorDaFolha rotulo={t('Líquido')} valor={d.folha.liquido} tom="text-emerald-700" forte />

                            <p className="border-t border-slate-100 pt-3 text-xs text-slate-500">
                                <i className="fas fa-users mr-1.5" aria-hidden="true" />
                                {t(':n trabalhador(es) processado(s)', { n: numero(d.folha.trabalhadores) })}
                            </p>
                        </div>
                    ) : (
                        <p className="py-8 text-center text-sm text-slate-400">{t('Ainda não há nenhuma folha processada.')}</p>
                    )}
                </Cartao>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Cartao titulo={t('Presenças da semana')} icone="fa-calendar-week">
                    <GraficoDeBarras dados={paraGrafico(d.graficos.presenca_da_semana)} titulo={t('Quem picou')} />
                </Cartao>

                <Cartao titulo={t('Pessoas por departamento')} icone="fa-sitemap">
                    <GraficoHorizontal dados={paraGrafico(d.graficos.por_departamento)} titulo={t('Pessoas por departamento')}
                        unidade="" vazio={t('Ninguém está atribuído a um departamento.')} />
                </Cartao>

                <Cartao titulo={t('Como está composta a equipa')} icone="fa-user-tag">
                    <GraficoHorizontal dados={paraGrafico(d.graficos.por_vinculo)} titulo={t('Pessoas por vínculo')}
                        unidade="" vazio={t('Nenhum vínculo registado nas fichas.')} />
                </Cartao>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Lista
                    titulo={t('Aniversários deste mês')}
                    icone="fa-cake-candles"
                    vazio={t('Ninguém faz anos este mês.')}
                    linhas={d.listas.aniversarios.map((a) => ({ chave: a.id, nome: a.nome, nota: a.numero, direita: a.dia ?? '—' }))}
                />
                <Lista
                    titulo={t('Últimas admissões')}
                    icone="fa-user-plus"
                    vazio={t('Ainda não há admissões registadas.')}
                    linhas={d.listas.admissoes.map((a) => ({ chave: a.id, nome: a.nome, nota: a.numero, direita: a.quando ? data(a.quando) : '—' }))}
                />
                <Lista
                    titulo={t('Próximas férias')}
                    icone="fa-plane-departure"
                    vazio={t('Ninguém tem férias marcadas para os próximos dias.')}
                    linhas={d.listas.proximas_ferias.map((f) => ({
                        chave: f.id,
                        nome: f.nome,
                        nota: f.de ? `${data(f.de)} → ${f.ate ? data(f.ate) : '—'}` : null,
                        direita: t(':n dias', { n: f.dias }),
                    }))}
                />
            </div>
        </div>
    );
}

/* ─── As peças ──────────────────────────────────────────────────────── */

function AvisoDoPainel({ a, i }: { a: Aviso; i: number }) {
    return (
        <div
            className={cls('entra flex items-start gap-3 border p-4', RAIO, ESTILO_DO_AVISO[a.tom] ?? ESTILO_DO_AVISO.neutra)}
            style={{ '--i': Math.min(i, 12) } as React.CSSProperties}
        >
            <span className="grid h-10 w-10 flex-none place-items-center rounded-xl bg-white/70">
                <i className={cls('fas', a.icone)} aria-hidden="true" />
            </span>

            <div className="min-w-0 flex-1">
                <p className="font-bold">{a.titulo}</p>
                <p className="mt-0.5 text-sm opacity-90">{a.texto}</p>

                <a href={a.morada} className={cls(
                    'mt-2 inline-flex items-center gap-1.5 text-sm font-semibold underline-offset-4 transition-all hover:underline',
                    FOCO, RAIO,
                )}>
                    {a.accao}
                    <i className="fas fa-arrow-right text-xs transition-transform duration-200 group-hover:translate-x-0.5" aria-hidden="true" />
                </a>
            </div>
        </div>
    );
}

function ValorDaFolha({ rotulo, valor, tom, forte }: { rotulo: string; valor: number; tom?: string; forte?: boolean }) {
    return (
        <div className="flex items-baseline justify-between">
            <span className="text-sm text-slate-500">{rotulo}</span>
            <span className={cls('tabular-nums', forte ? 'text-lg font-bold' : 'font-semibold', tom ?? 'text-slate-800')}>
                {kz(valor)} <span className="text-xs font-normal text-slate-400">Kz</span>
            </span>
        </div>
    );
}

function Lista({ titulo, icone, vazio, linhas }: {
    titulo: string;
    icone: string;
    vazio: string;
    linhas: Array<{ chave: number; nome: string; nota: string | null; direita: string }>;
}) {
    return (
        <Cartao titulo={titulo} icone={icone}>
            {linhas.length === 0 ? (
                <p className="py-6 text-center text-sm text-slate-400">{vazio}</p>
            ) : (
                <ul className="divide-y divide-slate-100">
                    {linhas.map((l, i) => (
                        <li key={l.chave} className="entra flex items-center justify-between gap-3 py-2"
                            style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
                            <span className="min-w-0">
                                <span className="block truncate font-semibold text-slate-800">{l.nome}</span>
                                {l.nota && <span className="block truncate font-mono text-xs text-slate-400">{l.nota}</span>}
                            </span>
                            <span className="flex-none text-sm font-semibold tabular-nums text-slate-600">{l.direita}</span>
                        </li>
                    ))}
                </ul>
            )}
        </Cartao>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls(CARTAO, 'border border-red-200 bg-red-50 p-6')} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o painel de RH')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
