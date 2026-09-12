import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type ComandoDoSistema, type ExecucaoDoHistorico, type LinhaDaConsola, type SeederDoSistema, sistema } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { SemNada, cascata } from '@/ui/SemNada';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { CARTAO, FOCO, RAIO, TRANSICAO, cls, dataHora } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { Confirmar, Consola, ErroDoEcra, Recado } from './comum';

const TONS: Record<string, string> = {
    laranja: 'bg-orange-100 text-orange-600', aviso: 'bg-amber-100 text-amber-600', neutra: 'bg-slate-100 text-slate-600',
    perigo: 'bg-red-100 text-red-600', bom: 'bg-emerald-100 text-emerald-600', primaria: 'bg-indigo-100 text-indigo-600',
    roxo: 'bg-purple-100 text-purple-600', teal: 'bg-teal-100 text-teal-600', rosa: 'bg-rose-100 text-rose-600',
};

/** Os que só lêem ou limpam caches correm logo; os outros mexem na base ou nas permissões e perguntam antes. */
const SEM_PERGUNTA = ['cache_clear', 'config_clear', 'view_clear', 'migrate_status', 'seeders_status'];

/**
 * OS COMANDOS E OS SEEDERS DO SISTEMA.
 *
 * A consola fica por cima dos separadores e mostra o que o último comando
 * disse. Um comando que mexe na base, nas permissões ou no deploy pede
 * confirmação com o comando exacto à vista — no Blade corria ao primeiro
 * clique, incluindo o `migrate --force` e o `patch:deploy`.
 */
export default function Comandos() {
    const fila = useQueryClient();
    const [aba, porAba] = useState('comandos');
    const [linhas, porLinhas] = useState<LinhaDaConsola[]>([]);
    const [parametros, porParametros] = useState<Record<string, Record<string, unknown>>>({});
    const [aConfirmar, porAConfirmar] = useState<ComandoDoSistema | null>(null);
    const [categoria, porCategoria] = useState('all');
    const [seeder, porSeeder] = useState<SeederDoSistema | null>(null);
    const [aSemear, porASemear] = useState(false);
    const [aLimpar, porALimpar] = useState(false);
    const [recado, porRecado] = useState<string | null>(null);

    const dados = useQuery({ queryKey: ['plataforma', 'comandos'], queryFn: sistema.comandos.ler });

    const actualizarHistorico = (historico: ExecucaoDoHistorico[]) =>
        fila.setQueryData(['plataforma', 'comandos'], (d: typeof dados.data) => (d ? { ...d, historico } : d));

    const correr = useMutation({
        mutationFn: (c: ComandoDoSistema) => sistema.comandos.correr(c.chave, parametros[c.chave] ?? {}),
        onMutate: () => porLinhas([]),
        onSuccess: (r) => { porAConfirmar(null); porLinhas(r.linhas); actualizarHistorico(r.historico); },
    });
    const semear = useMutation({
        mutationFn: (s: SeederDoSistema) => sistema.comandos.semear(s.namespace),
        onMutate: () => porLinhas([]),
        onSuccess: (r) => { porASemear(false); porLinhas(r.linhas); void fila.invalidateQueries({ queryKey: ['plataforma', 'comandos'] }); },
    });
    const limpar = useMutation({
        mutationFn: sistema.comandos.limparHistorico,
        onSuccess: (r) => { porALimpar(false); porRecado(r.message); actualizarHistorico([]); },
    });

    if (dados.isPending) return <Carregando linhas={8} />;
    if (dados.isError) return <ErroDoEcra titulo={t('Não foi possível abrir os comandos do sistema')} erro={dados.error} />;

    const d = dados.data;
    const aTrabalhar = correr.isPending || semear.isPending;
    const grupos = d.comandos.reduce<Record<string, ComandoDoSistema[]>>((g, c) => ({ ...g, [c.grupo]: [...(g[c.grupo] ?? []), c] }), {});
    const categorias = ['all', ...new Set(d.seeders.map((s) => s.categoria))];
    const seedersVisiveis = categoria === 'all' ? d.seeders : d.seeders.filter((s) => s.categoria === categoria);
    const errosDoComando = correr.error instanceof ErroDaApi ? correr.error.erros : {};

    const pedir = (c: ComandoDoSistema) => (SEM_PERGUNTA.includes(c.chave) ? correr.mutate(c) : porAConfirmar(c));
    const mudarParametro = (chave: string, nome: string, valor: unknown) =>
        porParametros((p) => ({ ...p, [chave]: { ...(p[chave] ?? {}), [nome]: valor } }));

    const textoDoComando = (c: ComandoDoSistema) => {
        const p = parametros[c.chave] ?? {};
        const partes = c.parametros.map((x) => (x.tipo === 'checkbox' ? (p[x.nome] ? ` --${x.nome}` : '') : (p[x.nome] ? ` ${String(p[x.nome])}` : '')));
        return `php artisan ${c.comando}${partes.join('')}`;
    };

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Comandos & Seeders')}
                subtitulo={t('Execute comandos artisan e seeders atraves da interface')}
                icone="fa-terminal"
                cor="roxo"
                accoes={
                    <>
                        {linhas.length > 0 && (
                            <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porLinhas([])}>
                                <i className="fas fa-eraser" aria-hidden="true" />{t('Limpar Output')}
                            </button>
                        )}
                        {d.historico.length > 0 && (
                            <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porALimpar(true)}>
                                <i className="fas fa-trash" aria-hidden="true" />{t('Limpar Historico')}
                            </button>
                        )}
                    </>
                }
            >
                <EstadoNaFaixa icone="fa-seedling">{t(':n seeders pendentes', { n: d.numeros_dos_seeders.pendentes })}</EstadoNaFaixa>
            </Faixa>

            <Recado texto={recado} aoFechar={() => porRecado(null)} />
            {!aConfirmar && <AvisoDeErro erro={correr.error} />}

            {(linhas.length > 0 || aTrabalhar) && (
                <section className={cls('entra bg-slate-900 p-4 shadow-lg', RAIO)}>
                    <h3 className="mb-3 flex items-center justify-between font-bold text-white">
                        <span><i className="fas fa-terminal mr-2 text-emerald-400" aria-hidden="true" />{t('Terminal Output')}</span>
                        <button type="button" className={cls('text-slate-400 hover:text-white', FOCO)} onClick={() => porLinhas([])} aria-label={t('Limpar Output')}>
                            <i className="fas fa-xmark" aria-hidden="true" />
                        </button>
                    </h3>
                    <Consola linhas={linhas} vazio="" aTrabalhar={aTrabalhar} altura="max-h-[400px]" />
                </section>
            )}

            <div className={cls(CARTAO, 'p-4')}>
                <Separadores
                    abas={[
                        { chave: 'comandos', rotulo: t('Comandos'), icone: 'fa-terminal' },
                        { chave: 'seeders', rotulo: t('Seeders (:n)', { n: d.seeders.length }), icone: 'fa-seedling' },
                        { chave: 'historico', rotulo: t('Historico (:n)', { n: d.historico.length }), icone: 'fa-clock-rotate-left' },
                    ]}
                    activa={aba}
                    aoMudar={porAba}
                />

                <div className="pt-4">
                    <PainelDoSeparador chave="comandos" activa={aba}>
                        <div className="space-y-6">
                            {Object.entries(grupos).map(([grupo, comandos]) => (
                                <div key={grupo}>
                                    <h3 className="mb-3 text-lg font-bold text-slate-700">{grupo}</h3>
                                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        {comandos.map((c, i) => (
                                            <article key={c.chave} className={cls(CARTAO, 'card-hover cascata flex flex-col p-5')} style={cascata(i)}>
                                                <div className="mb-3 flex items-center">
                                                    <span className={cls('mr-3 grid h-10 w-10 shrink-0 place-items-center', RAIO, TONS[c.cor] ?? TONS.neutra)}>
                                                        <i className={cls('fas icon-float', c.icone)} aria-hidden="true" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <h4 className="text-sm font-semibold text-slate-800">{c.nome}</h4>
                                                        <code className="text-[10px] text-slate-400">{c.comando}</code>
                                                    </div>
                                                </div>
                                                <p className="mb-3 text-xs leading-relaxed text-slate-500">{c.descricao}</p>

                                                {c.parametros.length > 0 && (
                                                    <div className="mb-3 space-y-2 rounded-xl bg-slate-50 p-3">
                                                        {c.parametros.map((p) => (p.tipo === 'checkbox' ? (
                                                            <label key={p.nome} className="flex items-center gap-2 text-xs text-slate-700">
                                                                <input type="checkbox" className="rounded text-indigo-600" checked={Boolean(parametros[c.chave]?.[p.nome])}
                                                                    onChange={(e) => mudarParametro(c.chave, p.nome, e.target.checked)} />
                                                                {p.rotulo}
                                                            </label>
                                                        ) : (
                                                            <label key={p.nome} className="block">
                                                                <span className="text-[10px] font-semibold uppercase text-slate-500">{p.rotulo}{p.obrigatorio && <span className="text-red-500"> *</span>}</span>
                                                                <select className={cls(entrada, 'h-9 text-xs')} value={String(parametros[c.chave]?.[p.nome] ?? '')}
                                                                    onChange={(e) => mudarParametro(c.chave, p.nome, e.target.value)}>
                                                                    <option value="">{t('Selecione...')}</option>
                                                                    {(p.opcoes ? d.opcoes[p.opcoes] : []).map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                                                                </select>
                                                            </label>
                                                        )))}
                                                    </div>
                                                )}

                                                <Botao className="mt-auto w-full" cor="primaria" tom="solida" icone="fa-play" aTrabalhar={correr.isPending && correr.variables?.chave === c.chave}
                                                    disabled={aTrabalhar} onClick={() => pedir(c)}>
                                                    {t('Executar')}
                                                </Botao>
                                            </article>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="seeders" activa={aba}>
                        <div className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <CartaoNumero rotulo={t('Total')} valor={d.numeros_dos_seeders.total} icone="fa-seedling" tom="azul" aspecto="claro" />
                                <CartaoNumero rotulo={t('Executados')} valor={d.numeros_dos_seeders.executados} icone="fa-circle-check" tom="verde" aspecto="claro" />
                                <CartaoNumero rotulo={t('Pendentes')} valor={d.numeros_dos_seeders.pendentes} icone="fa-hourglass-half" tom="ambar" aspecto="claro" />
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                {categorias.map((c) => (
                                    <button key={c} type="button" onClick={() => porCategoria(c)} aria-pressed={categoria === c}
                                        className={cls('rounded-full px-3 py-1.5 text-xs font-semibold', TRANSICAO, FOCO, categoria === c ? 'bg-indigo-600 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200')}>
                                        {c === 'all' ? t('Todos') : c}
                                    </button>
                                ))}
                                <span className="flex-1" />
                                <Botao cor="bom" tom="solida" icone="fa-play" disabled={!seeder || aTrabalhar} aTrabalhar={semear.isPending} onClick={() => porASemear(true)}>
                                    {seeder ? t('Executar :nome', { nome: seeder.nome }) : t('Selecionar Seeder')}
                                </Botao>
                            </div>
                            <AvisoDeErro erro={semear.error} />

                            {seedersVisiveis.length === 0 ? <SemNada icone="fa-seedling" frase={d.seeders.length ? t('Nenhum seeder nesta categoria') : t('Nenhum seeder encontrado')} /> : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                            <tr><th className="px-4 py-3">{t('Categoria')}</th><th className="px-4 py-3">{t('Seeder')}</th><th className="px-4 py-3">{t('Classe')}</th><th className="px-4 py-3">{t('Status')}</th><th className="px-4 py-3">{t('Accao')}</th></tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {seedersVisiveis.map((s) => (
                                                <tr key={s.namespace} className={cls(TRANSICAO, seeder?.namespace === s.namespace ? 'bg-emerald-50' : 'hover:bg-slate-50')}>
                                                    <td className="px-4 py-2.5 text-xs text-slate-500">{s.categoria}</td>
                                                    <td className="px-4 py-2.5 font-medium text-slate-800">{s.nome}</td>
                                                    <td className="px-4 py-2.5"><code className="text-[11px] text-slate-500">{s.namespace}</code></td>
                                                    <td className="px-4 py-2.5">
                                                        {s.executado
                                                            ? <Etiqueta cor="bom" icone="fa-check">{s.executado_em ? dataHora(s.executado_em) : t('Executado')}</Etiqueta>
                                                            : <Etiqueta cor="aviso" ponto>{t('Pendente')}</Etiqueta>}
                                                    </td>
                                                    <td className="px-4 py-2.5">
                                                        <Botao altura="pequeno" cor={seeder?.namespace === s.namespace ? 'bom' : 'neutra'} icone={seeder?.namespace === s.namespace ? 'fa-circle-check' : 'fa-hand-pointer'} onClick={() => porSeeder(s)}>
                                                            {seeder?.namespace === s.namespace ? t('Selecionado') : t('Selecionar')}
                                                        </Botao>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="historico" activa={aba}>
                        {d.historico.length === 0 ? <SemNada icone="fa-clock-rotate-left" titulo={t('Nenhuma execucao registrada')} frase={t('Comece executando um comando ou seeder')} /> : (
                            <ul className="space-y-2">
                                {d.historico.map((h, i) => (
                                    <li key={`${h.executed_at}-${i}`} className={cls('cascata border p-3', RAIO, h.success ? 'border-emerald-200 bg-emerald-50/50' : 'border-red-200 bg-red-50/50')} style={cascata(i)}>
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="font-semibold text-slate-800">
                                                <i className={cls('fas mr-2', h.success ? 'fa-circle-check text-emerald-600' : 'fa-circle-xmark text-red-600')} aria-hidden="true" />{h.command_name}
                                            </span>
                                            <span className="text-xs text-slate-500">{h.executed_by} · {dataHora(h.executed_at.replace(' ', 'T'))}</span>
                                        </div>
                                        {h.output && <pre className="mt-2 max-h-32 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-900 p-2 font-mono text-[11px] text-slate-200">{h.output}</pre>}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </PainelDoSeparador>
                </div>
            </div>

            <Confirmar
                aberto={aConfirmar !== null}
                titulo={t('Executar este comando?')}
                subtitulo={aConfirmar?.nome}
                rotulo={t('Executar')}
                icone="fa-play"
                cor="aviso"
                aTrabalhar={correr.isPending}
                erro={Object.keys(errosDoComando).length ? null : correr.error}
                aoConfirmar={() => aConfirmar && correr.mutate(aConfirmar)}
                aoFechar={() => porAConfirmar(null)}
            >
                <p>{aConfirmar?.descricao}</p>
                {aConfirmar && <code className="block rounded-lg bg-slate-900 p-3 font-mono text-xs text-emerald-300">{textoDoComando(aConfirmar)}</code>}
                {Object.values(errosDoComando).map((e) => <p key={e[0]} role="alert" className="text-sm font-semibold text-red-600">{e[0]}</p>)}
                <p className="font-semibold text-amber-800">{t('Corre neste servidor, agora, e não se desfaz.')}</p>
            </Confirmar>

            <Confirmar
                aberto={aSemear}
                titulo={t('Executar o seeder?')}
                subtitulo={seeder?.nome}
                rotulo={t('Executar')}
                icone="fa-seedling"
                cor="aviso"
                aTrabalhar={semear.isPending}
                aoConfirmar={() => seeder && semear.mutate(seeder)}
                aoFechar={() => porASemear(false)}
            >
                {seeder && <code className="block rounded-lg bg-slate-900 p-3 font-mono text-xs text-emerald-300">php artisan db:seed --class={seeder.namespace}</code>}
                {seeder?.executado && <p className="font-semibold text-amber-800">{t('Este seeder já foi executado. Correr outra vez pode duplicar dados.')}</p>}
            </Confirmar>

            <Confirmar
                aberto={aLimpar}
                titulo={t('Limpar Historico')}
                rotulo={t('Limpar')}
                aTrabalhar={limpar.isPending}
                erro={limpar.error}
                aoConfirmar={() => limpar.mutate()}
                aoFechar={() => porALimpar(false)}
            >
                <p>{t('Tem certeza que deseja limpar o historico?')}</p>
            </Confirmar>
        </div>
    );
}
