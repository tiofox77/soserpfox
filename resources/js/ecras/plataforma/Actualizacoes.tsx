import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type LinhaDoRegisto, type ReleaseDoGithub, sistema } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, RAIO, RAIO_GRANDE, TOQUE, TRANSICAO, cls, dataHora } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { Confirmar, Consola, ErroDoEcra, Recado } from './comum';

/**
 * AS ACTUALIZAÇÕES DO SISTEMA PELAS RELEASES DO GITHUB.
 *
 * Não se vai ao GitHub só por abrir a página: a lista pede-se no botão. Instalar
 * pede confirmação — substitui o código deste servidor, corre as migrations e
 * reconstrói os caches.
 */
export default function Actualizacoes() {
    const [recado, porRecado] = useState<{ texto: string; aviso?: boolean } | null>(null);
    const [aInstalar, porAInstalar] = useState<ReleaseDoGithub | null>(null);
    const [verTudo, porVerTudo] = useState<string | null>(null);

    const dados = useQuery({ queryKey: ['plataforma', 'actualizacoes'], queryFn: sistema.actualizacoes.ler });
    const releases = useMutation({ mutationFn: sistema.actualizacoes.releases, onSuccess: (r) => porRecado({ texto: r.message }) });
    const instalar = useMutation({
        mutationFn: (versao: string) => sistema.actualizacoes.instalar(versao),
        onMutate: () => porAInstalar(null),
        onSuccess: (r) => { porRecado({ texto: r.message }); void dados.refetch(); },
    });

    if (dados.isPending) return <Carregando linhas={6} />;
    if (dados.isError) return <ErroDoEcra titulo={t('Não foi possível abrir as atualizações')} erro={dados.error} />;

    const d = dados.data;
    const lista = releases.data?.releases ?? [];
    // Uma instalação que falha a meio devolve o registo até onde chegou: é
    // isso que diz o que ficou feito e o que não.
    const registo = instalar.data?.registo
        ?? (instalar.error instanceof ErroDaApi ? ((instalar.error.corpo.registo as LinhaDoRegisto[] | undefined) ?? []) : []);

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Atualizações do Sistema')}
                subtitulo={t('Gerenciar updates via GitHub Releases')}
                icone="fa-cloud-arrow-down"
                cor="primaria"
                accoes={
                    <button type="button" className={ACCAO_DA_FAIXA} onClick={() => releases.mutate()} disabled={releases.isPending}>
                        <i className={cls('fas fa-rotate', releases.isPending && 'fa-spin')} aria-hidden="true" />{t('Atualizar Lista')}
                    </button>
                }
            >
                <EstadoNaFaixa icone="fa-code-branch">v{d.versao_actual}</EstadoNaFaixa>
            </Faixa>

            <Recado texto={recado?.texto ?? null} aviso={recado?.aviso} aoFechar={() => porRecado(null)} />
            <AvisoDeErro erro={releases.error} />

            <a href="/superadmin/system-commands" className={cls('group block bg-gradient-to-r from-green-500 via-teal-500 to-blue-500 p-6 text-white shadow-xl', RAIO_GRANDE, TRANSICAO, TOQUE)}>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <span className="grid h-16 w-16 place-items-center rounded-2xl bg-white/20 backdrop-blur-sm">
                            <i className="fas fa-terminal icon-float text-3xl" aria-hidden="true" />
                        </span>
                        <div>
                            <h3 className="mb-1 text-2xl font-bold">{t('Comandos do Sistema')}</h3>
                            <p className="text-white/90">{t('Execute comandos artisan através da interface web com logs em tempo real')}</p>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {[t('Sincronizar Módulos'), t('Migrations'), t('Cache')].map((x) => <span key={x} className="rounded-full bg-white/20 px-3 py-1 text-xs font-semibold">{x}</span>)}
                            </div>
                        </div>
                    </div>
                    <i className="fas fa-chevron-right text-3xl opacity-50 transition-transform group-hover:translate-x-1" aria-hidden="true" />
                </div>
            </a>

            <div className="grid gap-4 lg:grid-cols-3">
                <section className={cls(CARTAO, 'space-y-4 p-5')}>
                    <h3 className="font-bold text-slate-800"><i className="fas fa-circle-info mr-2 text-blue-600" aria-hidden="true" />{t('Informações do Sistema')}</h3>
                    <div className={cls('bg-gradient-to-br from-blue-500 to-cyan-600 p-4 text-white', RAIO)}>
                        <p className="text-sm text-blue-100">{t('Versão Atual')}</p>
                        <p className="text-4xl font-bold">v{d.versao_actual}</p>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className={cls('bg-slate-50 p-3', RAIO)}><p className="text-xs text-slate-500">{t('Releases')}</p><p className="text-2xl font-bold">{lista.length}</p></div>
                        <div className={cls('bg-emerald-50 p-3', RAIO)}><p className="text-xs text-emerald-700">{t('Disponíveis')}</p><p className="text-2xl font-bold text-emerald-700">{lista.filter((r) => r.is_newer).length}</p></div>
                    </div>
                    <div className={cls('bg-slate-50 p-3 text-sm', RAIO)}>
                        <p className="text-xs text-slate-500">{t('Repositório GitHub')}</p>
                        <a className="break-all font-mono text-indigo-700 hover:underline" href={`https://github.com/${d.repositorio}`} target="_blank" rel="noreferrer">{d.repositorio}</a>
                    </div>
                </section>

                <section className={cls(CARTAO, 'p-5 lg:col-span-2')}>
                    <h3 className="mb-4 font-bold text-slate-800"><i className="fas fa-tags mr-2 text-indigo-600" aria-hidden="true" />{t('Releases')}</h3>

                    {releases.isPending ? (
                        <div className="py-12 text-center text-slate-500">
                            <i className="fas fa-circle-notch fa-spin mb-3 text-3xl text-indigo-500" aria-hidden="true" />
                            <p className="font-semibold">{t('Buscando releases do GitHub...')}</p>
                            <p className="text-sm">{t('Aguarde, pode levar até 30 segundos...')}</p>
                        </div>
                    ) : lista.length === 0 ? (
                        <SemNada icone="fa-tags" titulo={t('Nenhuma release encontrada')} frase={t('Clique em "Atualizar Lista" para buscar releases do GitHub')}
                            accao={<Botao cor="primaria" tom="solida" icone="fa-rotate" onClick={() => releases.mutate()}>{t('Atualizar Lista')}</Botao>} />
                    ) : (
                        <ul className="space-y-3">
                            {lista.map((r, i) => (
                                <li key={r.tag_name} className={cls('cascata border-2 p-4', RAIO, r.is_newer ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-slate-50')} style={cascata(i)}>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="flex flex-wrap items-center gap-2 font-bold text-slate-900">
                                                {r.name} <code className="text-xs text-slate-500">{r.tag_name}</code>
                                                {r.is_newer && <Etiqueta cor="bom" icone="fa-star">{t('NOVA')}</Etiqueta>}
                                                {r.is_current && <Etiqueta cor="primaria" icone="fa-check">{t('ATUAL')}</Etiqueta>}
                                                {!r.is_newer && !r.is_current && <Etiqueta>{t('ANTIGA')}</Etiqueta>}
                                                {r.prerelease && <Etiqueta cor="aviso">{t('Pré-release')}</Etiqueta>}
                                            </p>
                                            <p className="text-xs text-slate-500">{dataHora(r.published_at)}</p>
                                        </div>
                                        <Botao
                                            altura="pequeno"
                                            tom={r.is_newer ? 'solida' : 'suave'}
                                            cor={r.is_newer ? 'bom' : r.is_current ? 'primaria' : 'aviso'}
                                            icone={r.is_newer ? 'fa-download' : r.is_current ? 'fa-rotate' : 'fa-clock-rotate-left'}
                                            disabled={instalar.isPending}
                                            onClick={() => porAInstalar(r)}
                                        >
                                            {r.is_newer ? t('Instalar versão mais recente') : r.is_current ? t('Reinstalar mesma versão') : t('Reverter para versão anterior')}
                                        </Botao>
                                    </div>
                                    {r.body && (
                                        <div className="mt-3 rounded-lg bg-white p-3 text-sm text-slate-700">
                                            <p className="whitespace-pre-wrap">{verTudo === r.tag_name || r.body.length <= 500 ? r.body : `${r.body.slice(0, 500)}…`}</p>
                                            {r.body.length > 500 && (
                                                <button type="button" className="mt-1 text-xs font-semibold text-indigo-700 hover:underline" onClick={() => porVerTudo(verTudo === r.tag_name ? null : r.tag_name)}>
                                                    {verTudo === r.tag_name ? t('Ver menos') : t('Ver mais')}
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            {(instalar.isPending || registo.length > 0) && (
                <section className={cls(CARTAO, 'entra p-5')}>
                    <h3 className="mb-4 font-bold text-slate-800"><i className="fas fa-list-check mr-2 text-blue-600" aria-hidden="true" />{t('Progresso da Atualização')}</h3>
                    <AvisoDeErro erro={instalar.error} />
                    <Consola linhas={registo.map((l) => ({ tipo: l.tipo, texto: `[${l.hora}] ${l.mensagem}` }))} vazio="" aTrabalhar={instalar.isPending} altura="h-96" />
                </section>
            )}

            <section className={cls('border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900', RAIO_GRANDE)}>
                <h3 className="mb-2 font-bold"><i className="fas fa-lightbulb mr-2" aria-hidden="true" />{t('Como publicar uma release')}</h3>
                <ol className="list-decimal space-y-1 pl-5">
                    <li>{t('Acesse seu repositório no GitHub')}</li>
                    <li>{t('Clique em "Releases" → "Create a new release"')}</li>
                    <li>{t('Defina uma tag (ex: v5.0.1, v5.1.0)')}</li>
                    <li>{t('Adicione título e descrição (changelog)')}</li>
                    <li>{t('Click em "Publish release"')}</li>
                </ol>
            </section>

            <Confirmar
                aberto={aInstalar !== null}
                titulo={t('Instalar a versão :versao?', { versao: aInstalar?.tag_name ?? '' })}
                subtitulo={aInstalar?.name}
                rotulo={t('Instalar')}
                icone="fa-download"
                cor="aviso"
                aoConfirmar={() => aInstalar && instalar.mutate(aInstalar.tag_name)}
                aoFechar={() => porAInstalar(null)}
            >
                <p>{t('Faz um backup da pasta app, substitui os ficheiros deste servidor pelos da release, corre as migrations e reconstrói os caches.')}</p>
                <p className="font-semibold text-amber-800">{t('Não se desfaz sozinho. Durante a instalação o sistema pode ficar indisponível.')}</p>
            </Confirmar>
        </div>
    );
}
