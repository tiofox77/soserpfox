import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { plataforma } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, TRANSICAO, cls, kz } from '@/ui/tokens';

const CICLOS = () => [
    { valor: 'monthly', rotulo: t('Mensal'), nota: t('Pagamento todos os meses'), icone: 'fa-calendar-day' },
    { valor: 'quarterly', rotulo: t('Trimestral'), nota: t('De três em três meses'), icone: 'fa-calendar-week' },
    { valor: 'semiannual', rotulo: t('Semestral'), nota: t('De seis em seis meses'), icone: 'fa-calendar' },
    { valor: 'yearly', rotulo: t('Anual'), nota: t('Economize mais'), icone: 'fa-calendar-check' },
];

/**
 * MUDAR O PLANO DE UMA EMPRESA — com o acordo e o que ele dá à vista.
 *
 * O RESUMO VEM DO SERVIDOR, pela regra que grava: um pedido a cada pausa na
 * escrita. Uma cópia da conta em JavaScript prometia uma coisa e o servidor
 * gravava outra à primeira regra nova.
 */
export function PlanoDaEmpresa({ id, aoFechar, aoGuardar }: { id: number; aoFechar: () => void; aoGuardar: (recado: string) => void }) {
    const dados = useQuery({
        queryKey: ['plataforma', 'empresas', 'plano', id],
        queryFn: () => plataforma.empresas.plano(id),
    });

    const [plano, porPlano] = useState<number | null>(null);
    const [ciclo, porCiclo] = useState('monthly');
    const [comOferta, porComOferta] = useState(true);
    const [dias, porDias] = useState('');
    const [precoPorUtilizador, porPrecoPorUtilizador] = useState('');
    const [utilizadores, porUtilizadores] = useState('');
    const [maxDocumentos, porMaxDocumentos] = useState('');

    // O ACORDO ACTUAL VEM PARA O ECRÃ TAL COMO ESTÁ: quem abre para «ver» não
    // pode ficar com valores diferentes dos que a empresa tem.
    useEffect(() => {
        const a = dados.data?.actual;

        if (!a) return;

        porPlano(a.plano_id);
        porCiclo(a.ciclo);
        porComOferta(a.com_oferta);
        porDias(a.dias_personalizados ? String(a.dias_personalizados) : '');
        porPrecoPorUtilizador(a.preco_por_utilizador !== null ? String(a.preco_por_utilizador) : '');
        porUtilizadores(a.utilizadores_cobrados ? String(a.utilizadores_cobrados) : '');
        porMaxDocumentos(a.max_documentos !== null ? String(a.max_documentos) : '');
    }, [dados.data]);

    const escolha = useMemo(() => ({
        plano,
        ciclo,
        com_oferta: comOferta,
        dias: dias === '' ? null : Number(dias),
        preco_por_utilizador: precoPorUtilizador === '' ? null : Number(precoPorUtilizador),
        utilizadores: utilizadores === '' ? null : Number(utilizadores),
        max_documentos: maxDocumentos === '' ? null : Number(maxDocumentos),
    }), [plano, ciclo, comOferta, dias, precoPorUtilizador, utilizadores, maxDocumentos]);

    // Um resumo por pausa na escrita, e não um por tecla.
    const [atrasada, porAtrasada] = useState(escolha);

    useEffect(() => {
        const relogio = window.setTimeout(() => porAtrasada(escolha), 400);

        return () => window.clearTimeout(relogio);
    }, [escolha]);

    const resumo = useQuery({
        queryKey: ['plataforma', 'empresas', 'plano', id, 'resumo', atrasada],
        queryFn: () => plataforma.empresas.resumo(id, atrasada),
        enabled: atrasada.plano !== null,
        placeholderData: keepPreviousData,
        retry: false,
    });

    const guardar = useMutation({
        mutationFn: () => plataforma.empresas.mudarPlano(id, escolha),
        onSuccess: (r) => aoGuardar(r.message),
    });

    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const d = dados.data;
    const novo = d?.planos.find((p) => p.id === plano) ?? null;
    const actual = d?.actual ?? null;
    const r = resumo.data;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Alterar o plano')}
            subtitulo={d?.empresa.nome}
            icone="fa-crown"
            cor="roxo"
            largura="xl"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" disabled={!plano} aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>
                        {t('Alterar plano')}
                    </Botao>
                </div>
            }
        >
            {dados.isPending || !d ? (
                <Carregando linhas={10} />
            ) : (
                <div className="space-y-5">
                    <AvisoDeErro erro={guardar.error} />

                    {/* O PLANO ACTUAL — o acordo como está, não só o nome. */}
                    {actual && (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border-2 border-blue-200 bg-blue-50 p-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-blue-800">{t('Plano actual')}</p>
                                <p className="text-2xl font-bold text-blue-700">{actual.plano ?? '—'}</p>
                                <p className="mt-1 text-xs text-blue-700">
                                    {actual.termina_em
                                        ? t('termina a :dia · faltam :n dias', { dia: actual.termina_em, n: actual.faltam ?? 0 })
                                        : t('sem data de fim')}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="text-sm text-blue-700">
                                    {actual.ciclo_nome}
                                    {actual.dias_personalizados
                                        ? ` · ${t(':n dias à medida', { n: actual.dias_personalizados })}`
                                        : actual.ciclo === 'yearly' ? ` · ${actual.com_oferta ? t('com 2 meses de oferta') : t('sem oferta')}` : ''}
                                </p>
                                <p className="text-xl font-bold text-blue-800">{kz(actual.valor)} Kz</p>
                                {actual.preco_por_utilizador !== null && (
                                    <p className="text-xs text-blue-700">
                                        {t(':n utilizador(es) × :p Kz', { n: actual.utilizadores_cobrados ?? 0, p: kz(actual.preco_por_utilizador) })}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    <fieldset>
                        <legend className="mb-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-box mr-1.5 text-purple-500" aria-hidden="true" />{t('Escolha o plano')}
                        </legend>
                        {erros.plano && <p className="mb-2 text-xs text-red-600">{erros.plano[0]}</p>}
                        <div className="grid gap-3 md:grid-cols-2" role="radiogroup">
                            {d.planos.map((p) => (
                                <button
                                    key={p.id}
                                    type="button"
                                    role="radio"
                                    aria-checked={plano === p.id}
                                    onClick={() => porPlano(p.id)}
                                    className={cls(
                                        'border-2 p-4 text-left', RAIO, TRANSICAO, FOCO,
                                        plano === p.id ? 'border-purple-600 bg-purple-50 shadow-lg' : 'border-slate-200 hover:border-purple-300 hover:shadow',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="font-bold text-slate-900">{p.nome}</p>
                                            {p.descricao && <p className="mt-0.5 line-clamp-2 text-xs text-slate-500">{p.descricao}</p>}
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-1">
                                            {p.destacado && <Etiqueta cor="aviso" icone="fa-star">{t('Popular')}</Etiqueta>}
                                            {!p.na_montra && <Etiqueta cor="neutra" icone="fa-eye-slash">{t('À medida')}</Etiqueta>}
                                        </div>
                                    </div>
                                    <p className="mt-2 text-xs text-slate-600">
                                        <i className="fas fa-users mr-1 text-purple-500" aria-hidden="true" />{t(':n utilizadores', { n: p.max_utilizadores })}
                                        <i className="fas fa-database ml-3 mr-1 text-purple-500" aria-hidden="true" />{t(':n MB', { n: p.max_espaco_mb })}
                                    </p>
                                    <p className="mt-2 border-t border-slate-200 pt-2">
                                        <span className="text-xl font-bold text-purple-700">{kz(p.preco_mensal)}</span>
                                        <span className="ml-1 text-xs text-slate-500">Kz/{t('mês')}</span>
                                        <span className="ml-2 text-xs text-slate-500">{t('ou :n Kz/ano', { n: kz(p.preco_anual) })}</span>
                                    </p>
                                </button>
                            ))}
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend className="mb-2 text-sm font-bold text-slate-800">
                            <i className="fas fa-rotate mr-1.5 text-indigo-500" aria-hidden="true" />{t('Ciclo de facturação')}
                        </legend>
                        <div className="grid grid-cols-2 gap-3 md:grid-cols-4" role="radiogroup">
                            {CICLOS().map((c) => (
                                <button
                                    key={c.valor}
                                    type="button"
                                    role="radio"
                                    aria-checked={ciclo === c.valor}
                                    onClick={() => porCiclo(c.valor)}
                                    className={cls(
                                        'flex items-center justify-between gap-2 border-2 p-3 text-left', RAIO, TRANSICAO, FOCO,
                                        ciclo === c.valor ? 'border-indigo-600 bg-indigo-50' : 'border-slate-200 hover:border-indigo-300',
                                    )}
                                >
                                    <span>
                                        <span className="block text-sm font-bold text-slate-900">{c.rotulo}</span>
                                        <span className="block text-xs text-slate-500">{c.nota}</span>
                                    </span>
                                    <i className={cls('fas text-indigo-500', c.icone)} aria-hidden="true" />
                                </button>
                            ))}
                        </div>
                    </fieldset>

                    {/* AS CONDIÇÕES DO ACORDO: oferta, dias à medida, preço por utilizador. */}
                    <fieldset className="space-y-4 rounded-xl border-2 border-slate-200 bg-slate-50 p-4">
                        <legend className="px-1 text-sm font-bold text-slate-800">
                            <i className="fas fa-handshake mr-1.5 text-slate-500" aria-hidden="true" />{t('Condições do acordo')}
                        </legend>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Campo etiqueta={t('Período em dias')} erro={erros.dias ?? resumoErro(resumo.error, 'dias')} ajuda={t('Se preencher, ganha ao ciclo: o período acaba daqui a N dias.')}>
                                <input type="number" min="1" max="3660" className={entrada} value={dias} placeholder={t('ex.: 364')} onChange={(e) => porDias(e.target.value)} />
                            </Campo>
                            <Campo etiqueta={t('Preço por utilizador (Kz)')} erro={erros.preco_por_utilizador} ajuda={t('Se preencher, o valor passa a ser N utilizadores × este preço.')}>
                                <input type="number" min="0" step="0.01" className={entrada} value={precoPorUtilizador} onChange={(e) => porPrecoPorUtilizador(e.target.value)} />
                            </Campo>
                            <Campo etiqueta={t('Utilizadores a cobrar')} erro={erros.utilizadores} ajuda={t('Vazio: os utilizadores do plano escolhido.')}>
                                <input type="number" min="1" className={entrada} value={utilizadores} placeholder={t('os do plano')} onChange={(e) => porUtilizadores(e.target.value)} />
                            </Campo>
                        </div>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Campo etiqueta={t('Documentos incluídos')} erro={erros.max_documentos} ajuda={t('Vazio: o tecto do plano. Sem tecto no plano, sem limite.')}>
                                <input type="number" min="1" max="1000000" className={entrada} value={maxDocumentos} placeholder={t('o que o plano der')} onChange={(e) => porMaxDocumentos(e.target.value)} />
                            </Campo>
                            <p className="self-end pb-2 text-xs text-slate-600 md:col-span-2">
                                <i className="fas fa-file-invoice mr-1 text-slate-400" aria-hidden="true" />
                                {t('Esta empresa já emitiu :n documentos fiscais', { n: kz(d.documentos.emitidos, 0) })}
                                {d.documentos.tecto !== null ? (
                                    <>
                                        {' '}{t('de :t', { t: kz(d.documentos.tecto, 0) })}
                                        {d.documentos.emitidos >= d.documentos.tecto
                                            ? <b className="ml-1 text-red-600">· {t('esgotado, não consegue facturar')}</b>
                                            : d.documentos.emitidos >= d.documentos.tecto * 0.9
                                                ? <b className="ml-1 text-orange-600">· {t('quase no fim')}</b>
                                                : null}
                                    </>
                                ) : <span className="ml-1 text-slate-500">· {t('sem tecto')}</span>}
                            </p>
                        </div>
                        {ciclo === 'yearly' && dias === '' && (
                            <label className="flex cursor-pointer items-start gap-2">
                                <input type="checkbox" className="mt-0.5 h-4 w-4 rounded border-slate-300 text-purple-600" checked={comOferta} onChange={(e) => porComOferta(e.target.checked)} />
                                <span className="text-sm text-slate-700">
                                    <b>{t('Oferecer os 2 meses do anual')}</b> — {t('14 meses pelo preço de 12.')}
                                    <span className="block text-xs text-slate-500">{t('Desligado, o anual dá exactamente 12 meses. Fica gravado no acordo: a renovação repete-o.')}</span>
                                </span>
                            </label>
                        )}
                    </fieldset>

                    {/* O RESUMO — calculado pela regra que grava. */}
                    {novo && r && (
                        <div className={cls('entra space-y-3 bg-gradient-to-br from-purple-600 to-indigo-700 p-5 text-white shadow-lg', RAIO, resumo.isFetching && 'opacity-80')}>
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wider text-purple-200">{t('Total a pagar')}</p>
                                    <p className="text-sm text-purple-100">{novo.nome} · {r.ciclo_nome}</p>
                                    <p className="mt-1 text-xs text-purple-200">{r.base}</p>
                                </div>
                                <p className="text-3xl font-bold tabular-nums">{kz(r.valor)} <span className="text-lg">Kz</span></p>
                            </div>
                            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-white/20 pt-3 text-xs">
                                <span className="text-purple-100">
                                    <i className="fas fa-calendar-check mr-1" aria-hidden="true" />
                                    {t('Período: hoje → :fim (:n dias)', { fim: r.fim, n: r.dias })}
                                </span>
                                {r.oferta_aplicavel && (
                                    <span className={cls('rounded-full px-2 py-0.5 font-semibold', r.com_oferta ? 'bg-emerald-500/30 text-emerald-100' : 'bg-white/20')}>
                                        {r.com_oferta ? t('+2 meses de oferta') : t('sem oferta')}
                                    </span>
                                )}
                            </div>
                            {actual && (
                                <div className="flex flex-wrap items-center justify-between gap-2 border-t border-white/20 pt-3 text-xs">
                                    <span className="text-purple-100">{t('Valor anterior: :n Kz', { n: kz(actual.valor) })}</span>
                                    <Diferenca valor={r.valor - actual.valor} />
                                </div>
                            )}
                        </div>
                    )}

                    {novo && (
                        <div className="rounded-xl border-2 border-yellow-300 bg-gradient-to-br from-yellow-50 to-orange-50 p-4">
                            <h4 className="mb-2 font-bold text-yellow-900">
                                <i className="fas fa-circle-info mr-2 text-yellow-600" aria-hidden="true" />{t('O que vai acontecer')}
                            </h4>
                            <ul className="space-y-1.5 text-sm text-yellow-900">
                                <li><i className="fas fa-circle-check mr-2 text-emerald-600" aria-hidden="true" />{t('A subscrição actual é cancelada e nasce uma nova, com dias novos.')}</li>
                                <li>
                                    <i className="fas fa-users mr-2 text-blue-600" aria-hidden="true" />
                                    {t('Utilizadores: :n', { n: novo.max_utilizadores })}
                                    {actual && <Variacao de={actual.max_utilizadores} para={novo.max_utilizadores} />}
                                </li>
                                <li>
                                    <i className="fas fa-database mr-2 text-cyan-600" aria-hidden="true" />
                                    {t('Espaço: :n MB', { n: novo.max_espaco_mb })}
                                    {actual && <Variacao de={actual.max_espaco_mb} para={novo.max_espaco_mb} />}
                                </li>
                                <li><i className="fas fa-puzzle-piece mr-2 text-orange-600" aria-hidden="true" />{t('Os módulos passam a ser os do plano, com as dependências.')}</li>
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </Modal>
    );
}

function resumoErro(erro: unknown, campo: string): string[] | undefined {
    return erro instanceof ErroDaApi ? erro.erros[campo] : undefined;
}

function Diferenca({ valor }: { valor: number }) {
    if (Math.abs(valor) < 0.005) {
        return <span className="rounded-full bg-white/20 px-2 py-0.5 font-semibold">{t('Sem alteração')}</span>;
    }

    return (
        <span className={cls('rounded-full px-2 py-0.5 font-semibold', valor > 0 ? 'bg-red-500/30 text-red-100' : 'bg-emerald-500/30 text-emerald-100')}>
            {valor > 0 ? '+' : ''}{kz(valor)} Kz
        </span>
    );
}

function Variacao({ de, para }: { de: number; para: number }) {
    const diff = para - de;

    if (diff === 0) return <span className="ml-1.5 text-slate-500">({t('sem alteração')})</span>;

    return (
        <span className={cls('ml-1.5 font-semibold', diff > 0 ? 'text-emerald-700' : 'text-red-700')}>
            ({diff > 0 ? '+' : ''}{kz(diff, 0)})
            <i className={cls('fas ml-1', diff > 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down')} aria-hidden="true" />
        </span>
    );
}
