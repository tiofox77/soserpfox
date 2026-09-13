import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { folha, type LinhaDaFolha, type LinhaDoTrabalhador, type OpcoesDaFolha } from '@/api/ponto';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { CartaoNumero, type TomDoCartao } from '@/ui/CartaoNumero';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { ACCAO_DA_FAIXA, Faixa } from '@/ecras/facturacao/faixa';
import { CARTAO, FOCO, RAIO, cls, data, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';
import { etiquetaIntl, t } from '@/i18n';

/**
 * A FOLHA DE PAGAMENTO — o ecrã onde um erro custa dinheiro a alguém.
 *
 * Por isso não faz conta nenhuma. Cada passo é uma chamada ao
 * `PayrollService`: é lá que vivem o IRT por escalões, o INSS, as horas
 * extras, os subsídios e o abate dos adiantamentos.
 *
 * O CICLO ESTÁ SEMPRE À VISTA, e cada botão diz o que faz:
 *
 *   CRIAR → APROVAR → PAGAR
 *
 * O CRIAR já calcula a linha de cada trabalhador — a folha nasce feita, para
 * se conferir. «Refazer as contas» é para quando muda um salário ou entra uma
 * hora extra depois disso.
 *
 * E é o PAGAR que dispara as deduções — a prestação do adiantamento e a do
 * desconto salarial são abatidas nesse passo, e não antes. O ecrã escreve-o
 * no botão, porque é a diferença entre uma folha aprovada e o dinheiro a sair.
 */

const TOM: Record<string, TomDoCartao> = {
    neutra: 'cinza', primaria: 'indigo', bom: 'verde', aviso: 'ambar', perigo: 'vermelho',
};

const SINAL: Record<string, string> = {
    neutra: 'fa-file', primaria: 'fa-gears', bom: 'fa-circle-check', aviso: 'fa-clock', perigo: 'fa-ban',
};

export default function Folha() {
    const cache = useQueryClient();
    const anoAgora = new Date().getFullYear();

    const [filtros, porFiltros] = useState<{ ano?: number; estado?: string; page?: number }>({ ano: anoAgora, page: 1 });
    const [aCriar, porACriar] = useState(false);
    const [aVer, porAVer] = useState<number | null>(null);
    const [aApagar, porAApagar] = useState<LinhaDaFolha | null>(null);
    const [recado, porRecado] = useRecadoNoCanto('');

    const opcoes = useQuery({ queryKey: ['rh', 'folha', 'opcoes'], queryFn: folha.opcoes, staleTime: 5 * 60_000 });
    const lista = useQuery({
        queryKey: ['rh', 'folha', filtros],
        queryFn: () => folha.lista(filtros),
        placeholderData: keepPreviousData,
    });

    const invalidar = () => void cache.invalidateQueries({ queryKey: ['rh', 'folha'] });

    const apagar = useMutation({
        mutationFn: (l: LinhaDaFolha) => folha.eliminar(l.id),
        onSuccess: (r) => { invalidar(); porAApagar(null); porRecado(r.message); },
    });

    if (opcoes.isPending) return <Carregando linhas={8} />;
    if (opcoes.isError || lista.isError) return <Falhou erro={opcoes.error ?? lista.error} />;

    const o = opcoes.data;
    const linhas = lista.data?.data ?? [];
    const resumo = lista.data?.resumo;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Folha de Pagamento')}
                subtitulo={t('Criar, conferir, aprovar e pagar — e é o pagar que desconta')}
                icone="fa-money-check-dollar"
                cor="bom"
                accoes={
                    o.permissoes.pode_processar && (
                        <button type="button" onClick={() => porACriar(true)} className={cls(ACCAO_DA_FAIXA, 'group')}>
                            <i className="fas fa-plus transition-transform duration-300 group-hover:rotate-90" aria-hidden="true" />
                            {t('Nova Folha')}
                        </button>
                    )
                }
            />

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}><i className="fas fa-times" aria-hidden="true" /></button>
                </div>
            )}

            <AvisoDeErro erro={apagar.error} />

            <div className={cls('grid gap-3 sm:grid-cols-2 lg:grid-cols-4', lista.isFetching && 'opacity-70')}>
                <CartaoNumero aspecto="claro" rotulo={t('Folhas')} tom="indigo" icone="fa-file-invoice-dollar"
                    nota={t('nesta empresa')} valor={resumo === undefined ? '—' : resumo.total.toLocaleString(etiquetaIntl())} />
                <CartaoNumero aspecto="claro" rotulo={t('Rascunhos')} tom="cinza" icone="fa-file"
                    nota={t('por aprovar')} valor={resumo === undefined ? '—' : resumo.rascunhos.toLocaleString(etiquetaIntl())} />
                {/* O NÚMERO QUE DIZ QUE HÁ TRABALHO À ESPERA: aprovadas e por
                    pagar é dinheiro decidido que ainda não saiu. */}
                <CartaoNumero aspecto="claro" rotulo={t('Aprovadas por pagar')} tom={resumo && resumo.por_pagar > 0 ? 'ambar' : 'cinza'}
                    icone="fa-hourglass-half" valor={resumo === undefined ? '—' : resumo.por_pagar.toLocaleString(etiquetaIntl())} />
                <CartaoNumero aspecto="claro" rotulo={t('Líquido do ano')} tom="verde" icone="fa-money-bill-wave"
                    sufixo="Kz" nota={String(filtros.ano ?? anoAgora)} valor={resumo === undefined ? '—' : kz(resumo.liquido_do_ano)} />
            </div>

            <Cartao titulo={t('Filtros')} icone="fa-filter">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Ano')}</span>
                        <input type="number" min="2000" max="2100" value={filtros.ano ?? ''}
                            onChange={(e) => porFiltros((f) => ({ ...f, ano: e.target.value ? Number(e.target.value) : undefined, page: 1 }))}
                            className={cls(entrada, 'tabular-nums')} />
                    </label>
                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Estado')}</span>
                        <select value={filtros.estado ?? ''} onChange={(e) => porFiltros((f) => ({ ...f, estado: e.target.value, page: 1 }))} className={entrada}>
                            <option value="">{t('Todos')}</option>
                            {o.estados.map((e) => <option key={e.valor} value={e.valor}>{e.rotulo}</option>)}
                        </select>
                    </label>
                    <div className="flex items-end">
                        <Botao altura="pequeno" icone="fa-eraser" onClick={() => porFiltros({ ano: anoAgora, page: 1 })}>{t('Limpar')}</Botao>
                    </div>
                </div>
            </Cartao>

            {lista.isPending ? (
                <Carregando />
            ) : linhas.length === 0 ? (
                <div className={cls(CARTAO, 'animate-fade-in px-6 py-16 text-center')}>
                    <div className="mx-auto mb-4 grid h-20 w-20 place-items-center rounded-full bg-slate-100">
                        <i className="fas fa-file-invoice-dollar text-4xl text-slate-300" aria-hidden="true" />
                    </div>
                    <p className="text-lg font-bold text-slate-800">{t('Nenhuma folha neste ano')}</p>
                    <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">{t('Crie a folha do mês: as linhas nascem calculadas, para conferir antes de aprovar.')}</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {linhas.map((l, i) => (
                        <FolhaDoMes
                            key={l.id}
                            i={i}
                            l={l}
                            o={o}
                            aoVer={() => porAVer(l.id)}
                            aoApagar={() => porAApagar(l)}
                            aoFeito={(m) => { porRecado(m); invalidar(); }}
                        />
                    ))}
                </div>
            )}

            {aCriar && (
                <NovaFolha
                    o={o}
                    aoFechar={() => porACriar(false)}
                    aoCriar={(m, id) => { porACriar(false); porRecado(m); invalidar(); porAVer(id); }}
                />
            )}

            {aVer !== null && (
                <Detalhe id={aVer} o={o} aoFechar={() => porAVer(null)} aoFeito={(m) => { porRecado(m); invalidar(); }} />
            )}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Eliminar folha')}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAApagar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar)}>{t('Eliminar')}</Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">{t('Vai eliminar a folha :numero e todas as suas linhas.', { numero: aApagar?.numero ?? '' })}</p>
                <p className="mt-2 text-sm text-slate-500">{t('Só uma folha por aprovar se elimina — uma aprovada ou paga é o registo de uma decisão.')}</p>
            </Modal>
        </div>
    );
}

/* ─── Uma folha, com o ciclo à vista ────────────────────────────────── */

function FolhaDoMes({ i, l, o, aoVer, aoApagar, aoFeito }: {
    i: number;
    l: LinhaDaFolha;
    o: OpcoesDaFolha;
    aoVer: () => void;
    aoApagar: () => void;
    aoFeito: (m: string) => void;
}) {
    const [aConfirmar, porAConfirmar] = useState<'pagar' | null>(null);

    const passo = useMutation({
        mutationFn: (qual: 'processar' | 'aprovar' | 'pagar' | 'recalcular') => folha[qual](l.id),
        onSuccess: (r) => { porAConfirmar(null); aoFeito(r.message); },
    });

    const estado = o.estados.find((e) => e.valor === l.estado);
    const rascunho = l.estado === 'draft' || l.estado === 'processing';
    const aprovada = l.estado === 'approved';
    const paga = l.estado === 'paid';

    return (
        <div className={cls(CARTAO, 'entra overflow-hidden')} style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
            <div className="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3">
                <span className={cls(
                    'grid h-11 w-11 flex-none place-items-center rounded-xl text-white',
                    paga ? 'bg-gradient-to-br from-emerald-500 to-teal-600' : aprovada ? 'bg-gradient-to-br from-amber-500 to-orange-600' : 'bg-gradient-to-br from-slate-400 to-slate-500',
                )}>
                    <i className="fas fa-file-invoice-dollar" aria-hidden="true" />
                </span>

                <div className="min-w-0">
                    <p className="font-bold capitalize text-slate-900">{l.mes} {l.year}</p>
                    <p className="font-mono text-xs text-slate-400">{l.numero}</p>
                </div>

                <span className="ml-auto flex flex-wrap items-center gap-2">
                    <Etiqueta cor={estado?.cor ?? 'neutra'} icone={SINAL[estado?.cor ?? 'neutra']}>
                        {estado?.rotulo ?? l.estado}
                    </Etiqueta>
                    <span className="text-sm text-slate-500">
                        {t(':quantos de :total processado(s)', { quantos: l.processados, total: l.funcionarios })}
                    </span>
                </span>
            </div>

            <div className="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
                <Numero rotulo={t('Bruto')} valor={l.totais.bruto} />
                <Numero rotulo={t('IRT')} valor={l.totais.irt} tom="text-rose-700" />
                <Numero rotulo={t('INSS (trabalhador)')} valor={l.totais.inss_trabalhador} tom="text-rose-700" />
                <Numero rotulo={t('Líquido')} valor={l.totais.liquido} tom="text-emerald-700" forte />
            </div>

            <div className="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
                <Botao altura="pequeno" icone="fa-eye" onClick={aoVer}>{t('Ver linhas')}</Botao>

                {/* A FOLHA NASCE CALCULADA — o `createPayroll` já monta a linha
                    de cada trabalhador. Este botão é para REFAZER a conta
                    quando muda um salário, entra uma hora extra ou se corrige
                    um ponto depois de ela estar feita. */}
                {o.permissoes.pode_processar && rascunho && (
                    <Botao altura="pequeno" icone="fa-gears" aTrabalhar={passo.isPending && passo.variables === 'processar'}
                        onClick={() => passo.mutate('processar')}>{t('Refazer as contas')}</Botao>
                )}

                {o.permissoes.pode_processar && rascunho && l.processados > 0 && (
                    <Botao altura="pequeno" cor="bom" tom="solida" icone="fa-check" aTrabalhar={passo.isPending && passo.variables === 'aprovar'}
                        onClick={() => passo.mutate('aprovar')}>{t('Aprovar')}</Botao>
                )}

                {o.permissoes.pode_processar && aprovada && (
                    <>
                        <Botao altura="pequeno" icone="fa-rotate" aTrabalhar={passo.isPending && passo.variables === 'recalcular'}
                            onClick={() => passo.mutate('recalcular')}>{t('Recalcular')}</Botao>
                        {/* PAGAR PERGUNTA ANTES: é o passo que desconta os
                            adiantamentos e não se desfaz. */}
                        <Botao altura="pequeno" cor="primaria" tom="solida" icone="fa-money-bill-wave"
                            onClick={() => porAConfirmar('pagar')}>{t('Marcar paga')}</Botao>
                    </>
                )}

                <span className="ml-auto flex flex-wrap items-center gap-2">
                    <a href={`/hr/payroll/${l.id}/payslips-pdf`} target="_blank" rel="noreferrer"
                        className={cls('inline-flex items-center gap-1.5 border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 transition-all hover:-translate-y-0.5 hover:text-red-600', RAIO, FOCO)}>
                        <i className="fas fa-file-pdf" aria-hidden="true" />
                        {t('Recibos')}
                    </a>
                    <a href={`/hr/payroll/${l.id}/excel`}
                        className={cls('inline-flex items-center gap-1.5 border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 transition-all hover:-translate-y-0.5 hover:text-emerald-700', RAIO, FOCO)}>
                        <i className="fas fa-file-excel" aria-hidden="true" />
                        {t('Excel')}
                    </a>
                    {o.permissoes.pode_processar && rascunho && (
                        <button type="button" onClick={aoApagar} title={t('Eliminar')} aria-label={t('Eliminar a folha :numero', { numero: l.numero })}
                            className={cls('p-2 text-red-500 transition-all hover:scale-110 hover:bg-red-50', RAIO, FOCO)}>
                            <i className="fas fa-trash text-xs" aria-hidden="true" />
                        </button>
                    )}
                </span>
            </div>

            <AvisoDeErro erro={passo.error} />

            <Modal
                aberto={aConfirmar === 'pagar'}
                aoFechar={() => porAConfirmar(null)}
                titulo={t('Marcar a folha como paga')}
                icone="fa-money-bill-wave"
                cor="primaria"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAConfirmar(null)}>{t('Cancelar')}</Botao>
                        <Botao cor="primaria" tom="solida" icone="fa-check" aTrabalhar={passo.isPending} onClick={() => passo.mutate('pagar')}>
                            {t('Marcar paga')}
                        </Botao>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    {t('Vai marcar a folha de :mes como paga, no valor líquido de :valor Kz.', { mes: l.mes, valor: kz(l.totais.liquido) })}
                </p>
                {/* O QUE MAIS ACONTECE, dito antes de acontecer. */}
                <div className={cls('mt-3 border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900', RAIO)}>
                    <i className="fas fa-triangle-exclamation mr-1.5" aria-hidden="true" />
                    {t('É este passo que abate a prestação dos adiantamentos e dos descontos salariais. Não se desfaz.')}
                </div>
            </Modal>
        </div>
    );
}

function Numero({ rotulo, valor, tom, forte }: { rotulo: string; valor: number; tom?: string; forte?: boolean }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">{rotulo}</p>
            <p className={cls('tabular-nums', forte ? 'text-xl font-bold' : 'text-lg font-semibold', tom ?? 'text-slate-800')}>
                {kz(valor)} <span className="text-xs font-normal text-slate-400">Kz</span>
            </p>
        </div>
    );
}

/* ─── Criar a folha de um mês ───────────────────────────────────────── */

function NovaFolha({ o, aoFechar, aoCriar }: {
    o: OpcoesDaFolha;
    aoFechar: () => void;
    aoCriar: (mensagem: string, id: number) => void;
}) {
    const agora = new Date();
    const [ano, porAno] = useState(agora.getFullYear());
    const [mes, porMes] = useState(agora.getMonth() + 1);
    const [erros, porErros] = useState<Record<string, string[]>>({});

    const criar = useMutation({
        mutationFn: () => folha.criar(ano, mes),
        onSuccess: (r) => aoCriar(r.message, r.documento.id),
        onError: (e) => porErros(e instanceof ErroDaApi ? e.erros : {}),
    });

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Nova folha')}
            subtitulo={t('Uma por mês e por empresa')}
            icone="fa-file-circle-plus"
            cor="bom"
            largura="sm"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={criar.isPending} onClick={() => { porErros({}); criar.mutate(); }}>
                        {t('Criar folha')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={criar.error} />

            {erros.regra?.[0] && (
                <div role="alert" className={cls('mb-3 border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900', RAIO)}>
                    {erros.regra[0]}
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta={t('Mês')} erro={erros.month} obrigatorio>
                    <select value={mes} onChange={(e) => porMes(Number(e.target.value))} className={entrada}>
                        {o.meses.map((m) => <option key={m.valor} value={m.valor} className="capitalize">{m.rotulo}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Ano')} erro={erros.year} obrigatorio>
                    <input type="number" min="2000" max="2100" value={ano} onChange={(e) => porAno(Number(e.target.value))} className={cls(entrada, 'tabular-nums')} />
                </Campo>
            </div>

            <p className="mt-4 text-xs text-slate-500">
                <i className="fas fa-circle-info mr-1" aria-hidden="true" />
                {t('A folha nasce com a linha de cada trabalhador já calculada. Confira antes de aprovar.')}
            </p>
        </Modal>
    );
}

/* ─── O detalhe: a linha de cada trabalhador ────────────────────────── */

/**
 * OS 49 CAMPOS DE UMA LINHA, agrupados como o recibo os mostra: Ganhos,
 * Impostos, Descontos e Tempo. Numa tabela só, ninguém encontra nada.
 */
function Detalhe({ id, o, aoFechar, aoFeito }: {
    id: number;
    o: OpcoesDaFolha;
    aoFechar: () => void;
    aoFeito: (m: string) => void;
}) {
    const cache = useQueryClient();
    const [aberta, porAberta] = useState<number | null>(null);

    const q = useQuery({ queryKey: ['rh', 'folha', 'ficha', id], queryFn: () => folha.ficha(id) });

    const acertar = useMutation({
        mutationFn: (linha: number) => folha.acertarLinha(id, linha),
        onSuccess: (r) => {
            void cache.invalidateQueries({ queryKey: ['rh', 'folha', 'ficha', id] });
            aoFeito(r.message);
        },
    });

    const f = q.data?.documento;
    const estado = f ? o.estados.find((e) => e.valor === f.estado) : undefined;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={f ? `${f.mes} ${f.year}` : t('Folha')}
            subtitulo={f?.numero}
            icone="fa-file-invoice-dollar"
            cor="bom"
            largura="xl"
            rodape={<Botao onClick={aoFechar}>{t('Fechar')}</Botao>}
        >
            {q.isPending || !f ? (
                <Carregando />
            ) : (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <Etiqueta cor={estado?.cor ?? 'neutra'} icone={SINAL[estado?.cor ?? 'neutra']}>{estado?.rotulo ?? f.estado}</Etiqueta>
                        <span className="text-sm text-slate-500">
                            {t('Período: :de a :ate', { de: f.periodo.de ? data(f.periodo.de) : '—', ate: f.periodo.ate ? data(f.periodo.ate) : '—' })}
                        </span>
                        {f.decisao.aprovado_em && (
                            <span className="ml-auto text-sm text-slate-500">
                                {t('Aprovada por :quem, em :quando.', { quem: f.decisao.aprovado_por ?? '—', quando: f.decisao.aprovado_em })}
                            </span>
                        )}
                    </div>

                    <AvisoDeErro erro={acertar.error} />

                    {f.linhas.length === 0 ? (
                        <p className="py-10 text-center text-sm text-slate-400">
                            {t('Esta folha não tem linhas — a empresa não tinha funcionários activos quando ela foi criada.')}
                        </p>
                    ) : (
                        <div className="space-y-2">
                            {f.linhas.map((linha, i) => (
                                <LinhaDoRecibo
                                    key={linha.id}
                                    i={i}
                                    linha={linha}
                                    folhaId={id}
                                    aberta={aberta === linha.id}
                                    podeAcertar={o.permissoes.pode_processar && f.estado !== 'paid'}
                                    aAcertar={acertar.isPending && acertar.variables === linha.id}
                                    aoAbrir={() => porAberta((a) => (a === linha.id ? null : linha.id))}
                                    aoAcertar={() => acertar.mutate(linha.id)}
                                />
                            ))}
                        </div>
                    )}
                </div>
            )}
        </Modal>
    );
}

function LinhaDoRecibo({ i, linha, folhaId, aberta, podeAcertar, aAcertar, aoAbrir, aoAcertar }: {
    i: number;
    linha: LinhaDoTrabalhador;
    folhaId: number;
    aberta: boolean;
    podeAcertar: boolean;
    aAcertar: boolean;
    aoAbrir: () => void;
    aoAcertar: () => void;
}) {
    const [aba, porAba] = useState('ganhos');

    const ROTULOS: Record<string, string> = {
        base_salary: 'Salário base', food_allowance: 'Subsídio de alimentação', transport_allowance: 'Subsídio de transporte',
        housing_allowance: 'Subsídio de habitação', overtime_pay: 'Horas extras', night_shift_pay: 'Turno nocturno',
        family_allowance: 'Abono de família', position_subsidy: 'Subsídio de cargo', performance_subsidy: 'Subsídio de desempenho',
        holiday_pay: 'Férias', commission: 'Comissão', bonus: 'Bónus',
        christmas_subsidy_amount: 'Subsídio de Natal', vacation_subsidy_amount: 'Subsídio de férias', other_earnings: 'Outros ganhos',
        irt_base: 'Incidência de IRT', irt_rate: 'Taxa de IRT', irt_amount: 'IRT retido',
        inss_base: 'Incidência de INSS', inss_employee: 'INSS do trabalhador', inss_employer: 'INSS da empresa',
        advance_payment: 'Adiantamento', loan_deduction: 'Empréstimo', discount_deduction: 'Desconto salarial',
        absence_deduction: 'Faltas', late_deduction: 'Atrasos', food_deduction: 'Alimentação', other_deductions: 'Outros descontos',
        worked_days: 'Dias trabalhados', present_days: 'Dias presentes', absence_days: 'Dias de falta',
        late_days: 'Dias com atraso', total_working_days: 'Dias úteis do mês', overtime_hours: 'Horas extras', night_hours: 'Horas nocturnas',
    };

    const bloco = (valores: Record<string, number>, dinheiro = true) => (
        <div className="grid gap-x-6 gap-y-1 sm:grid-cols-2">
            {Object.entries(valores).filter(([, v]) => v !== 0).map(([chave, v]) => (
                <div key={chave} className="flex items-baseline justify-between border-b border-slate-100 py-1 text-sm">
                    <span className="text-slate-600">{t(ROTULOS[chave] ?? chave)}</span>
                    <span className="font-semibold tabular-nums text-slate-900">
                        {dinheiro ? `${kz(v)} Kz` : v.toLocaleString(etiquetaIntl(), { maximumFractionDigits: 2 })}
                    </span>
                </div>
            ))}
            {Object.values(valores).every((v) => v === 0) && (
                <p className="py-2 text-sm text-slate-400">{t('Nada nesta secção.')}</p>
            )}
        </div>
    );

    return (
        <div className={cls('entra border border-slate-200 transition-colors', RAIO, aberta && 'border-indigo-300')} style={{ '--i': Math.min(i, 12) } as React.CSSProperties}>
            <button
                type="button"
                onClick={aoAbrir}
                aria-expanded={aberta}
                className={cls('flex w-full flex-wrap items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50', FOCO)}
            >
                <i className={cls('fas fa-chevron-right text-xs text-slate-400 transition-transform duration-200', aberta && 'rotate-90')} aria-hidden="true" />
                <span className="min-w-0">
                    <span className="block font-semibold text-slate-900">{linha.funcionario}</span>
                    {linha.numero && <span className="block font-mono text-xs text-slate-400">{linha.numero}</span>}
                </span>
                <span className="ml-auto flex flex-wrap items-center gap-4 text-sm">
                    <span className="text-slate-500">{t('Bruto')} <b className="tabular-nums text-slate-800">{kz(linha.bruto)}</b></span>
                    <span className="text-rose-600">− <b className="tabular-nums">{kz(linha.total_descontos)}</b></span>
                    <span className="text-base font-bold tabular-nums text-emerald-700">{kz(linha.liquido)} Kz</span>
                </span>
            </button>

            {aberta && (
                <div className="space-y-3 border-t border-slate-100 px-4 py-3">
                    <Separadores
                        abas={[
                            { chave: 'ganhos', rotulo: t('Ganhos'), icone: 'fa-plus' },
                            { chave: 'impostos', rotulo: t('Impostos'), icone: 'fa-landmark' },
                            { chave: 'descontos', rotulo: t('Descontos'), icone: 'fa-minus' },
                            { chave: 'tempo', rotulo: t('Tempo'), icone: 'fa-clock' },
                        ]}
                        activa={aba}
                        aoMudar={porAba}
                    />

                    <PainelDoSeparador chave="ganhos" activa={aba}>{bloco(linha.ganhos)}</PainelDoSeparador>
                    <PainelDoSeparador chave="impostos" activa={aba}>{bloco(linha.impostos)}</PainelDoSeparador>
                    <PainelDoSeparador chave="descontos" activa={aba}>{bloco(linha.descontos)}</PainelDoSeparador>
                    <PainelDoSeparador chave="tempo" activa={aba}>{bloco(linha.tempo, false)}</PainelDoSeparador>

                    <div className="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                        <a href={`/hr/payroll/payslip/${linha.id}/pdf`} target="_blank" rel="noreferrer"
                            className={cls('inline-flex items-center gap-1.5 border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 transition-all hover:-translate-y-0.5 hover:text-red-600', RAIO, FOCO)}>
                            <i className="fas fa-file-pdf" aria-hidden="true" />
                            {t('Recibo em PDF')}
                        </a>

                        {/* ACERTAR OS DESCONTOS: puxa as prestações que estão em
                            curso e manda o serviço recalcular o IRT, o INSS e o
                            líquido. Não se escrevem à mão — escrevê-los deixava
                            a folha a dizer uma coisa e o adiantamento outra. */}
                        {podeAcertar && (
                            <Botao altura="pequeno" icone="fa-rotate" aTrabalhar={aAcertar} onClick={aoAcertar}>
                                {t('Acertar descontos')}
                            </Botao>
                        )}

                        <span className="ml-auto text-xs text-slate-400">
                            {t('Empréstimos e descontos são puxados da base, não escritos.')}
                        </span>
                    </div>
                </div>
            )}
        </div>
    );
}

function Falhou({ erro }: { erro: unknown }) {
    const daApi = erro instanceof ErroDaApi ? erro : null;

    return (
        <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
            <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir a folha')}</h2>
            <p className="text-sm text-red-800">{daApi?.message ?? t('Verifique a ligação.')}</p>
        </div>
    );
}
