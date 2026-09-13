import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import type { PeriodoContabilistico } from '@/api/contabilidade';
import { contabilidade } from '@/api/contabilidade';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, cls, kz } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';

/**
 * OS PERÍODOS CONTABILÍSTICOS.
 *
 * O QUE FALTAVA POR COMPLETO: criar um. O ecrã antigo só sabia fechar e
 * reabrir — os períodos nasciam de um seeder que alguém corria na consola, e
 * uma empresa nova abria os Lançamentos, lia «não há períodos abertos» e não
 * tinha por onde resolver. Agora gera-se o exercício inteiro num botão, e um
 * período fora do calendário (o de apuramento) escreve-se à mão.
 *
 * E O QUE SE FECHAVA ÀS ESCURAS: o fecho recusa um período com rascunhos ou
 * com o balancete desequilibrado, e o ecrã não dizia nem quantos rascunhos
 * havia nem de quanto era a diferença — carregava-se em Fechar para descobrir.
 * Cada linha traz agora o seu balancete e o que a segura.
 */
export default function Periodos() {
    const cache = useQueryClient();

    const [ano, porAno] = useState<number>(new Date().getFullYear());
    const [recado, porRecado] = useRecadoNoCanto('');
    const [aCriar, porACriar] = useState(false);
    const [aGerar, porAGerar] = useState(false);
    const [aFechar, porAFechar] = useState<PeriodoContabilistico | null>(null);
    const [aReabrir, porAReabrir] = useState<PeriodoContabilistico | null>(null);

    const lista = useQuery({
        queryKey: ['contabilidade', 'periodos', ano],
        queryFn: () => contabilidade.periodos.listar(ano),
    });

    function feito(mensagem: string) {
        porRecado(mensagem);
        void cache.invalidateQueries({ queryKey: ['contabilidade'] });
    }

    const fechar = useMutation({
        mutationFn: (p: PeriodoContabilistico) => contabilidade.periodos.fechar(p.id),
        onSuccess: (r) => {
            porAFechar(null);
            feito(r.message);
        },
    });

    const reabrir = useMutation({
        mutationFn: (p: PeriodoContabilistico) => contabilidade.periodos.reabrir(p.id),
        onSuccess: (r) => {
            porAReabrir(null);
            feito(r.message);
        },
    });

    if (lista.isPending) return <Carregando linhas={10} />;

    if (lista.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir os períodos')}</h2>
                <p className="text-sm text-red-800">
                    {lista.error instanceof ErroDaApi ? lista.error.message : t('Verifique a ligação.')}
                </p>
            </div>
        );
    }

    const p = lista.data;
    // O ano corrente e os dois seguintes entram sempre: é o que se gera.
    const anos = Array.from(new Set([...p.anos, new Date().getFullYear(), new Date().getFullYear() + 1]))
        .sort((a, b) => b - a);

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Períodos Contabilísticos')}
                subtitulo={t('O que está aberto, o que está fechado, e o que falta para fechar')}
                icone="fa-calendar-check"
                cor="bom"
                accoes={
                    p.permissoes.gerir && (
                        <>
                            <button type="button" onClick={() => porAGerar(true)} className={ACCAO_DA_FAIXA}>
                                <i className="fas fa-wand-magic-sparkles" aria-hidden="true" />
                                {t('Gerar o exercício')}
                            </button>
                            <button type="button" onClick={() => porACriar(true)} className={ACCAO_DA_FAIXA}>
                                <i className="fas fa-plus" aria-hidden="true" />
                                {t('Novo Período')}
                            </button>
                        </>
                    )
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <label className="sr-only" htmlFor="per-ano">{t('Exercício')}</label>
                    <select
                        id="per-ano"
                        value={ano}
                        onChange={(e) => porAno(Number(e.target.value))}
                        className={cls(
                            'h-9 rounded-xl border border-white/30 bg-white/20 px-3 text-sm font-semibold text-white',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70',
                        )}
                    >
                        {anos.map((a) => <option key={a} value={a} className="text-slate-900">{a}</option>)}
                    </select>

                    <EstadoNaFaixa icone="fa-lock-open">
                        {t(':n aberto(s) · :f fechado(s)', { n: p.resumo.abertos, f: p.resumo.fechados })}
                    </EstadoNaFaixa>
                </div>
            </Faixa>

            {recado && (
                <div role="status" className={cls('flex items-center justify-between gap-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}>
                    <span><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</span>
                    <button type="button" onClick={() => porRecado('')} aria-label={t('Fechar')} className={cls('p-1 text-emerald-700', FOCO, RAIO)}>
                        <i className="fas fa-times" aria-hidden="true" />
                    </button>
                </div>
            )}

            <ErroDaAccao erro={fechar.error ?? reabrir.error} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <CartaoNumero rotulo={t('Períodos do exercício')} valor={p.resumo.total.toLocaleString('pt-PT')} icone="fa-calendar" tom="azul" aspecto="claro" />
                <CartaoNumero rotulo={t('Abertos')} valor={p.resumo.abertos.toLocaleString('pt-PT')} icone="fa-lock-open" tom="verde" aspecto="claro" nota={t('Recebem lançamentos')} />
                <CartaoNumero rotulo={t('Fechados')} valor={p.resumo.fechados.toLocaleString('pt-PT')} icone="fa-lock" tom="cinza" aspecto="claro" nota={t('Já não recebem nada')} />
                <CartaoNumero
                    rotulo={t('Rascunhos do exercício')}
                    valor={p.resumo.rascunhos.toLocaleString('pt-PT')}
                    icone="fa-pen-to-square"
                    tom={p.resumo.rascunhos > 0 ? 'ambar' : 'verde'}
                    aspecto="claro"
                    nota={t('É o que impede fechar')}
                />
            </div>

            {p.data.length === 0 ? (
                <section className={cls(CARTAO, 'overflow-hidden')}>
                    <SemNada
                        icone="fa-calendar-plus"
                        titulo={t('O exercício de :ano não está montado', { ano: p.ano })}
                        frase={p.permissoes.gerir
                            ? t('Carregue em «Gerar o exercício» para criar os doze meses de uma vez.')
                            : t('Peça a quem gere a contabilidade para montar o exercício.')}
                    />
                </section>
            ) : (
                <section className={cls(CARTAO, 'overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-gradient-to-r from-emerald-50 to-green-50">
                                <tr>
                                    {[t('Período'), t('Intervalo')].map((c) => (
                                        <th key={c} scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-emerald-700">{c}</th>
                                    ))}
                                    {[t('Lançamentos'), t('Débito'), t('Crédito'), t('Diferença')].map((c) => (
                                        <th key={c} scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-emerald-700">{c}</th>
                                    ))}
                                    <th scope="col" className="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Estado')}</th>
                                    <th scope="col" className="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-emerald-700">{t('Ações')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {p.data.map((x, i) => (
                                    <tr
                                        key={x.id}
                                        style={cascata(i)}
                                        className={cls('entra transition-colors hover:bg-emerald-50/60', x.e_o_de_hoje && 'bg-emerald-50/40')}
                                    >
                                        <td className="px-4 py-3">
                                            <p className="font-semibold text-slate-900">
                                                {x.nome}
                                                {/* O DE HOJE marca-se: é o que se usa ao lançar. */}
                                                {x.e_o_de_hoje && (
                                                    <span className="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-700">
                                                        {t('é o de hoje')}
                                                    </span>
                                                )}
                                            </p>
                                            <p className="font-mono text-xs text-slate-400">{x.codigo}</p>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-600">
                                            {x.de} → {x.ate}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700">
                                            {x.lancamentos.toLocaleString('pt-PT')}
                                            {x.rascunhos > 0 && (
                                                <span className="ml-1.5 rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-700" title={t('Em rascunho')}>
                                                    +{x.rascunhos}
                                                </span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right tabular-nums text-emerald-700">{kz(x.debito)}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right tabular-nums text-red-700">{kz(x.credito)}</td>
                                        <td className={cls('whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums',
                                            x.equilibrado ? 'text-slate-400' : 'text-red-700')}>
                                            {x.equilibrado ? '—' : kz(x.diferenca)}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-center">
                                            <Etiqueta cor={x.estado === 'open' ? 'bom' : 'neutra'} ponto>{x.estado_rotulo}</Etiqueta>
                                            {x.fechado_em && (
                                                <p className="mt-0.5 text-[11px] text-slate-400">
                                                    {x.fechado_em}{x.fechado_por ? ` · ${x.fechado_por}` : ''}
                                                </p>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            {!p.permissoes.gerir ? (
                                                <span className="text-xs text-slate-400">—</span>
                                            ) : x.pode_reabrir ? (
                                                <button
                                                    type="button"
                                                    onClick={() => porAReabrir(x)}
                                                    className={cls(BOTAO_DE_ACCAO, 'border-amber-200 bg-amber-50 text-amber-700')}
                                                >
                                                    <i className="fas fa-lock-open" aria-hidden="true" />
                                                    {t('Reabrir')}
                                                </button>
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => porAFechar(x)}
                                                    disabled={!x.pode_fechar}
                                                    title={x.porque_nao_fecha ?? undefined}
                                                    className={cls(BOTAO_DE_ACCAO, 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                                        'disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0 disabled:hover:shadow-none')}
                                                >
                                                    <i className="fas fa-lock" aria-hidden="true" />
                                                    {t('Fechar')}
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* O QUE SEGURA CADA FECHO, escrito e não só num `title`. */}
                    {p.data.some((x) => x.porque_nao_fecha) && (
                        <div className={cls('m-4 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)} role="status">
                            <p className="mb-1 font-semibold">
                                <i className="fas fa-circle-info mr-2" aria-hidden="true" />
                                {t('Períodos que ainda não fecham')}
                            </p>
                            <ul className="space-y-0.5">
                                {p.data.filter((x) => x.porque_nao_fecha).map((x) => (
                                    <li key={x.id}><strong>{x.nome}</strong>: {x.porque_nao_fecha}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </section>
            )}

            <ModalDeGerar
                aberto={aGerar}
                ano={ano}
                aoFechar={() => porAGerar(false)}
                aoGravar={(mensagem, anoGerado) => {
                    porAGerar(false);
                    porAno(anoGerado);
                    feito(mensagem);
                }}
            />

            <ModalDoPeriodo
                aberto={aCriar}
                ano={ano}
                aoFechar={() => porACriar(false)}
                aoGravar={(mensagem) => {
                    porACriar(false);
                    feito(mensagem);
                }}
            />

            {/* FECHAR — o que deixa de se poder fazer. */}
            <Modal
                aberto={aFechar !== null}
                aoFechar={() => porAFechar(null)}
                titulo={t('Fechar o período')}
                subtitulo={aFechar?.nome}
                icone="fa-lock"
                cor="bom"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAFechar(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="bom"
                            tom="solida"
                            icone="fa-lock"
                            aTrabalhar={fechar.isPending}
                            onClick={() => aFechar && fechar.mutate(aFechar)}
                        >
                            {t('Fechar o período')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={fechar.error} />

                <p className="text-sm text-slate-700">
                    {t('O período deixa de receber lançamentos — nem novos nem a confirmação de rascunhos antigos. Reabre-se, mas só se nenhum período posterior tiver sido fechado depois.')}
                </p>

                {aFechar && (
                    <dl className={cls('mt-3 grid gap-2 border border-slate-200 bg-slate-50 px-4 py-3 text-sm sm:grid-cols-3', RAIO)}>
                        <div><dt className="text-xs uppercase tracking-wider text-slate-500">{t('Lançamentos')}</dt><dd className="font-semibold tabular-nums text-slate-900">{aFechar.lancamentos}</dd></div>
                        <div><dt className="text-xs uppercase tracking-wider text-slate-500">{t('Débito')}</dt><dd className="font-semibold tabular-nums text-emerald-700">{kz(aFechar.debito)}</dd></div>
                        <div><dt className="text-xs uppercase tracking-wider text-slate-500">{t('Crédito')}</dt><dd className="font-semibold tabular-nums text-red-700">{kz(aFechar.credito)}</dd></div>
                    </dl>
                )}
            </Modal>

            {/* REABRIR */}
            <Modal
                aberto={aReabrir !== null}
                aoFechar={() => porAReabrir(null)}
                titulo={t('Reabrir o período')}
                subtitulo={aReabrir?.nome}
                icone="fa-lock-open"
                cor="aviso"
                largura="sm"
                rodape={
                    <>
                        <Botao onClick={() => porAReabrir(null)}>{t('Cancelar')}</Botao>
                        <Botao
                            cor="aviso"
                            tom="solida"
                            icone="fa-lock-open"
                            aTrabalhar={reabrir.isPending}
                            onClick={() => aReabrir && reabrir.mutate(aReabrir)}
                        >
                            {t('Reabrir')}
                        </Botao>
                    </>
                }
            >
                <AvisoDeErro erro={reabrir.error} />

                <p className="text-sm text-slate-700">
                    {t('O período volta a receber lançamentos. Só se reabre de trás para a frente: se houver períodos posteriores fechados, reabra-os primeiro, do mais recente para o mais antigo.')}
                </p>
            </Modal>
        </div>
    );
}

/* ─── Gerar o exercício ───────────────────────────────────────────────── */

/**
 * OS DOZE MESES DE UMA VEZ.
 *
 * É INCREMENTAL, e diz-se: só cria os meses que faltam e nunca toca num
 * período existente — que pode estar fechado, e reabri-lo em silêncio
 * corromperia a contabilidade.
 */
function ModalDeGerar({
    aberto,
    ano,
    aoFechar,
    aoGravar,
}: {
    aberto: boolean;
    ano: number;
    aoFechar: () => void;
    aoGravar: (mensagem: string, ano: number) => void;
}) {
    const [escolhido, porEscolhido] = useState(ano);

    const gerar = useMutation({
        mutationFn: () => contabilidade.periodos.gerar(escolhido),
        onSuccess: (r) => aoGravar(r.message, escolhido),
    });

    const agora = new Date().getFullYear();
    const anos = [agora - 2, agora - 1, agora, agora + 1, agora + 2];

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={t('Gerar o exercício')}
            subtitulo={t('Os doze meses do ano, de uma vez')}
            icone="fa-wand-magic-sparkles"
            cor="bom"
            largura="sm"
            rodape={
                <>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-wand-magic-sparkles" aTrabalhar={gerar.isPending} onClick={() => gerar.mutate()}>
                        {t('Gerar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={gerar.error} />

            <Campo etiqueta={t('Exercício')} obrigatorio>
                <select value={escolhido} onChange={(e) => porEscolhido(Number(e.target.value))} className={entrada}>
                    {anos.map((a) => <option key={a} value={a}>{a}</option>)}
                </select>
            </Campo>

            <p className={cls('mt-3 border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)}>
                <i className="fas fa-circle-info mr-2 text-blue-600" aria-hidden="true" />
                {t('Cria Janeiro a Dezembro com as datas certas de cada mês. Os que já existirem ficam como estão — um período fechado nunca é reaberto por aqui.')}
            </p>
        </Modal>
    );
}

/* ─── Um período à mão ────────────────────────────────────────────────── */

/**
 * PARA OS QUE NÃO SÃO MESES: o período de apuramento no fim do exercício, ou
 * um exercício que não começa em Janeiro. O servidor recusa um intervalo que se
 * sobreponha a outro — dois períodos a cobrir o mesmo dia fazem um lançamento
 * cair num ou noutro por sorte.
 */
function ModalDoPeriodo({
    aberto,
    ano,
    aoFechar,
    aoGravar,
}: {
    aberto: boolean;
    ano: number;
    aoFechar: () => void;
    aoGravar: (mensagem: string) => void;
}) {
    const [f, porF] = useState({ code: '', name: '', date_start: '', date_end: '' });

    const gravar = useMutation({
        mutationFn: () => contabilidade.periodos.criar(f),
        onSuccess: (r) => {
            porF({ code: '', name: '', date_start: '', date_end: '' });
            aoGravar(r.message);
        },
    });

    const erros = gravar.error instanceof ErroDaApi ? gravar.error.erros : {};

    return (
        <Modal
            aberto={aberto}
            aoFechar={aoFechar}
            titulo={t('Novo Período')}
            subtitulo={t('Um período fora do calendário dos meses')}
            icone="fa-calendar-plus"
            cor="bom"
            largura="md"
            rodape={
                <>
                    <Botao onClick={aoFechar} icone="fa-times">{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-save" aTrabalhar={gravar.isPending} onClick={() => gravar.mutate()}>
                        {t('Guardar')}
                    </Botao>
                </>
            }
        >
            <AvisoDeErro erro={gravar.error} />

            <div className="grid gap-4 sm:grid-cols-2">
                <Campo etiqueta={t('Código')} obrigatorio erro={erros.code} ajuda={t('Ex.: APU/:ano', { ano })}>
                    <input
                        type="text"
                        value={f.code}
                        onChange={(e) => porF((x) => ({ ...x, code: e.target.value }))}
                        className={cls(entrada, 'font-mono')}
                    />
                </Campo>

                <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                    <input
                        type="text"
                        value={f.name}
                        onChange={(e) => porF((x) => ({ ...x, name: e.target.value }))}
                        placeholder={t('Ex.: Apuramento :ano', { ano })}
                        className={entrada}
                    />
                </Campo>

                <Campo etiqueta={t('Início')} obrigatorio erro={erros.date_start}>
                    <input
                        type="date"
                        value={f.date_start}
                        max={f.date_end || undefined}
                        onChange={(e) => porF((x) => ({ ...x, date_start: e.target.value }))}
                        className={entrada}
                    />
                </Campo>

                <Campo etiqueta={t('Fim')} obrigatorio erro={erros.date_end}>
                    <input
                        type="date"
                        value={f.date_end}
                        min={f.date_start || undefined}
                        onChange={(e) => porF((x) => ({ ...x, date_end: e.target.value }))}
                        className={entrada}
                    />
                </Campo>
            </div>

            <p className={cls('mt-3 border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600', RAIO)}>
                <i className="fas fa-circle-info mr-2 text-blue-600" aria-hidden="true" />
                {t('O intervalo não pode sobrepor-se a outro período: dois a cobrir o mesmo dia fazem um lançamento cair num ou noutro por sorte.')}
            </p>
        </Modal>
    );
}

/* ─── As peças pequenas ───────────────────────────────────────────────── */

const BOTAO_DE_ACCAO = cls(
    'inline-flex items-center gap-1.5 border px-2.5 py-1.5 text-xs font-semibold',
    'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm',
    RAIO,
    FOCO,
);

function ErroDaAccao({ erro }: { erro: unknown }) {
    if (!erro) return null;

    const daApi = erro instanceof ErroDaApi ? erro : null;
    const geral = daApi?.erros.geral?.[0];

    return (
        <div role="alert" className={cls('border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800', RAIO)}>
            <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
            {geral ?? daApi?.message ?? t('A operação não foi concluída.')}
        </div>
    );
}
