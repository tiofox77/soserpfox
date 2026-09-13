import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { sistema } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Carregando } from '@/ui/Carregando';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, RAIO, TRANSICAO, cls, dataHora } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { Confirmar, Consola, ErroDoEcra, Recado } from './comum';

type Script = { nome: string; tamanho: number; modificado_em: string; descricao: string | null };

const tamanho = (b: number) => (b >= 1048576 ? `${(b / 1048576).toFixed(2)} MB` : b >= 1024 ? `${(b / 1024).toFixed(2)} KB` : `${b} B`);

/**
 * EXECUTAR SCRIPTS — os PHP da pasta `scripts/`, e o fim do log.
 *
 * Correr um script pede confirmação com o nome à vista: são ferramentas de
 * manutenção que escrevem na base, e no Blade corriam ao primeiro clique.
 * Limpar o log também pergunta — o que se apaga não volta.
 */
export default function Scripts() {
    const fila = useQueryClient();
    const [recado, porRecado] = useState<{ texto: string; aviso?: boolean } | null>(null);
    const [aCorrer, porACorrer] = useState<Script | null>(null);
    const [escolhido, porEscolhido] = useState<string | null>(null);
    const [saida, porSaida] = useState<{ ok: boolean; texto: string } | null>(null);
    const [verLog, porVerLog] = useState(false);
    const [aLimparLog, porALimparLog] = useState(false);

    const dados = useQuery({ queryKey: ['plataforma', 'scripts'], queryFn: sistema.scripts.ler });

    const correr = useMutation({
        mutationFn: (s: Script) => sistema.scripts.correr(s.nome),
        onMutate: (s) => { porACorrer(null); porEscolhido(s.nome); porSaida(null); },
        onSuccess: (r) => {
            porSaida({ ok: r.ok, texto: r.saida });
            porRecado({ texto: r.message, aviso: !r.ok });
            fila.setQueryData(['plataforma', 'scripts'], (d: typeof dados.data) => (d ? { ...d, log: r.log } : d));
        },
    });
    const refrescarLog = useMutation({
        mutationFn: sistema.scripts.log,
        onSuccess: (r) => fila.setQueryData(['plataforma', 'scripts'], (d: typeof dados.data) => (d ? { ...d, log: r.log } : d)),
    });
    const limparLog = useMutation({
        mutationFn: sistema.scripts.limparLog,
        onSuccess: (r) => { porALimparLog(false); porRecado({ texto: r.message }); fila.setQueryData(['plataforma', 'scripts'], (d: typeof dados.data) => (d ? { ...d, log: [] } : d)); },
    });

    if (dados.isPending) return <Carregando linhas={8} />;
    if (dados.isError) return <ErroDoEcra titulo={t('Não foi possível abrir os scripts')} erro={dados.error} />;

    const d = dados.data;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Executar Scripts')}
                subtitulo={t('Gerencie e execute scripts do sistema')}
                icone="fa-code"
                cor="ciano"
                accoes={
                    <>
                        <button type="button" className={ACCAO_DA_FAIXA} onClick={() => void dados.refetch().then(() => porRecado({ texto: t('Lista de scripts atualizada!') }))}>
                            <i className={cls('fas fa-rotate', dados.isFetching && 'fa-spin')} aria-hidden="true" />{t('Atualizar')}
                        </button>
                        <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porVerLog((v) => !v)} aria-expanded={verLog}>
                            <i className="fas fa-file-lines" aria-hidden="true" />{verLog ? t('Ocultar Logs') : t('Ver Logs')}
                        </button>
                    </>
                }
            >
                <EstadoNaFaixa icone="fa-folder-open">{t(':n scripts em scripts/', { n: d.scripts.length })}</EstadoNaFaixa>
            </Faixa>

            <Recado texto={recado?.texto ?? null} aviso={recado?.aviso} aoFechar={() => porRecado(null)} />
            <AvisoDeErro erro={correr.error} />

            <div className="grid gap-4 lg:grid-cols-2">
                <section className={cls(CARTAO, 'p-5')}>
                    <h3 className="mb-4 font-bold text-slate-900"><i className="fas fa-list icon-float mr-2 text-cyan-600" aria-hidden="true" />{t('Scripts Disponíveis')}</h3>
                    {d.scripts.length === 0 ? (
                        <SemNada icone="fa-folder-open" frase={t('Nenhum script encontrado na pasta scripts/')} />
                    ) : (
                        <ul className="max-h-[36rem] space-y-2 overflow-y-auto pr-1">
                            {d.scripts.map((s, i) => (
                                <li key={s.nome} className={cls('entra border-2 p-3', RAIO, TRANSICAO, escolhido === s.nome ? 'border-cyan-500 bg-cyan-50' : 'border-slate-200 hover:border-cyan-300 hover:bg-slate-50')} style={cascata(i)}>
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate font-mono text-sm font-bold text-slate-900"><i className="fab fa-php mr-2 text-indigo-500" aria-hidden="true" />{s.nome}</p>
                                            <p className="mt-1 text-xs text-slate-600">{s.descricao ?? t('Sem descrição')}</p>
                                            <p className="mt-1 flex gap-3 text-[11px] text-slate-400">
                                                <span><i className="fas fa-weight-hanging mr-1" aria-hidden="true" />{tamanho(s.tamanho)}</span>
                                                <span><i className="fas fa-clock mr-1" aria-hidden="true" />{dataHora(s.modificado_em)}</span>
                                            </p>
                                        </div>
                                        <Botao altura="pequeno" cor="primaria" tom="solida" icone="fa-play" aTrabalhar={correr.isPending && correr.variables?.nome === s.nome}
                                            disabled={correr.isPending} onClick={() => porACorrer(s)}>
                                            {t('Executar')}
                                        </Botao>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className={cls(CARTAO, 'p-5')}>
                    <h3 className="mb-4 flex items-center justify-between font-bold text-slate-900">
                        <span><i className="fas fa-terminal icon-float mr-2 text-emerald-600" aria-hidden="true" />{t('Output')}{escolhido && <span className="ml-2 font-mono text-xs text-slate-500">{escolhido}</span>}</span>
                        {saida && <Botao altura="pequeno" icone="fa-eraser" onClick={() => { porSaida(null); porEscolhido(null); }}>{t('Limpar')}</Botao>}
                    </h3>
                    <Consola
                        linhas={saida ? [{ tipo: saida.ok ? 'saida' : 'erro', texto: saida.texto || t('(sem output)') }] : []}
                        vazio={t('Selecione um script para ver o output aqui')}
                        aTrabalhar={correr.isPending}
                        altura="h-[36rem]"
                    />
                </section>
            </div>

            {verLog && (
                <section className={cls(CARTAO, 'entra p-5')}>
                    <h3 className="mb-4 flex flex-wrap items-center justify-between gap-2 font-bold text-slate-900">
                        <span><i className="fas fa-file-lines mr-2 text-slate-600" aria-hidden="true" />{t('Logs Recentes (últimas 100 linhas)')}</span>
                        <span className="flex gap-2">
                            <Botao altura="pequeno" icone="fa-rotate" aTrabalhar={refrescarLog.isPending} onClick={() => refrescarLog.mutate()}>{t('Atualizar')}</Botao>
                            <Botao altura="pequeno" cor="perigo" icone="fa-trash" onClick={() => porALimparLog(true)}>{t('Limpar Logs')}</Botao>
                        </span>
                    </h3>
                    <Consola linhas={d.log.map((l) => ({ tipo: /\.(ERROR|CRITICAL|EMERGENCY|ALERT)/.test(l) ? 'erro' : /\.WARNING/.test(l) ? 'aviso' : 'saida', texto: l }))} vazio={t('Nenhum log disponível')} altura="h-96" />
                </section>
            )}

            <Confirmar
                aberto={aCorrer !== null}
                titulo={t('Executar o script?')}
                subtitulo={aCorrer?.nome}
                rotulo={t('Executar')}
                icone="fa-play"
                cor="aviso"
                aoConfirmar={() => aCorrer && correr.mutate(aCorrer)}
                aoFechar={() => porACorrer(null)}
            >
                <p>{aCorrer?.descricao ?? t('Sem descrição')}</p>
                <p className="font-semibold text-amber-800">{t('O script corre neste servidor, com acesso à base de dados, e não se desfaz.')}</p>
            </Confirmar>

            <Confirmar
                aberto={aLimparLog}
                titulo={t('Limpar Logs')}
                rotulo={t('Limpar')}
                aTrabalhar={limparLog.isPending}
                erro={limparLog.error}
                aoConfirmar={() => limparLog.mutate()}
                aoFechar={() => porALimparLog(false)}
            >
                <p>{t('O laravel.log fica vazio. O que lá estava não volta.')}</p>
            </Confirmar>
        </div>
    );
}
