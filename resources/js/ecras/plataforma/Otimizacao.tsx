import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type ValoresDoIni, sistema } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Modal } from '@/ui/Modal';
import { cascata } from '@/ui/SemNada';
import { CARTAO, FOCO, RAIO, RAIO_GRANDE, TOQUE, TRANSICAO, cls } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { Confirmar, ErroDoEcra, Recado } from './comum';

/**
 * A OPTIMIZAÇÃO DO SISTEMA — OPcache, caches do Laravel e o `.user.ini`.
 *
 * As três acções grandes são cartões com gradiente, como no Blade. Optimizar
 * pede confirmação: grava caches de config e rotas que, com um `.env` a meio
 * de mudar, ficam presos até alguém os limpar.
 */
export default function Otimizacao() {
    const fila = useQueryClient();
    const [recado, porRecado] = useState<{ texto: string; aviso?: boolean } | null>(null);
    const [aConfigurar, porAConfigurar] = useState(false);
    const [aOtimizar, porAOtimizar] = useState(false);

    const dados = useQuery({ queryKey: ['plataforma', 'otimizacao'], queryFn: sistema.otimizacao.ler });

    const feito = (texto: string) => { porRecado({ texto }); void fila.invalidateQueries({ queryKey: ['plataforma', 'otimizacao'] }); };

    const opcache = useMutation({ mutationFn: sistema.otimizacao.limparOpcache, onSuccess: (r) => feito(r.message) });
    const caches = useMutation({ mutationFn: sistema.otimizacao.limparCaches, onSuccess: (r) => feito(r.message) });
    const otimizar = useMutation({ mutationFn: sistema.otimizacao.otimizar, onSuccess: (r) => { porAOtimizar(false); feito(r.message); } });

    if (dados.isPending) return <Carregando linhas={8} />;
    if (dados.isError) return <ErroDoEcra titulo={t('Não foi possível abrir a otimização do sistema')} erro={dados.error} />;

    const d = dados.data;
    const o = d.opcache;
    const activo = o.available && o.enabled;
    const corDaSaude = { success: 'border-emerald-200 bg-emerald-50 text-emerald-900', warning: 'border-orange-200 bg-orange-50 text-orange-900', info: 'border-blue-200 bg-blue-50 text-blue-900', error: 'border-red-200 bg-red-50 text-red-900' }[d.saude.status];
    const taxa = o.stats?.hit_rate ?? 0;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Otimização do Sistema')}
                subtitulo={t('OPcache, caches do Laravel e configuração do PHP')}
                icone="fa-rocket"
                cor="roxo"
                accoes={
                    <button type="button" className={ACCAO_DA_FAIXA} onClick={() => void dados.refetch()} disabled={dados.isFetching}>
                        <i className={cls('fas fa-rotate', dados.isFetching && 'fa-spin')} aria-hidden="true" />{t('Atualizar')}
                    </button>
                }
            >
                <EstadoNaFaixa icone={activo ? 'fa-bolt' : 'fa-circle-xmark'}>{activo ? t('OPcache ativo') : t('OPcache inativo')}</EstadoNaFaixa>
                <EstadoNaFaixa icone="fa-code">PHP {d.php.version}</EstadoNaFaixa>
            </Faixa>

            <Recado texto={recado?.texto ?? null} aviso={recado?.aviso} aoFechar={() => porRecado(null)} />
            <AvisoDeErro erro={opcache.error ?? caches.error} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Accao i={0} gradiente="from-purple-600 to-indigo-600" icone="fa-bolt" titulo={t('Limpar OPcache')} nota={t('Reseta bytecode cache')} aTrabalhar={opcache.isPending} onClick={() => opcache.mutate()} />
                <Accao i={1} gradiente="from-orange-600 to-red-600" icone="fa-trash-can" titulo={t('Limpar Todos Caches')} nota={t('Laravel + OPcache')} aTrabalhar={caches.isPending} onClick={() => caches.mutate()} />
                <Accao i={2} gradiente="from-green-600 to-teal-600" icone="fa-gauge-high" titulo={t('Otimizar Sistema')} nota={t('Cache config, routes, views')} aTrabalhar={otimizar.isPending} onClick={() => porAOtimizar(true)} />
                <Accao i={3} gradiente="from-blue-600 to-cyan-600" icone="fa-gear" titulo={t('Configurações')} nota={t('Gerar .user.ini para cPanel')} onClick={() => porAConfigurar(true)} />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <section className={cls(CARTAO, 'card-hover p-5')}>
                    <h3 className="mb-4 flex items-center justify-between text-lg font-bold text-slate-900">
                        <span><i className="fas fa-microchip icon-float mr-2 text-purple-600" aria-hidden="true" />OPcache</span>
                        <span className={cls('rounded-full px-3 py-1 text-xs font-bold', activo ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700')}>
                            {!o.available ? t('Indisponível') : o.enabled ? t('Ativo') : t('Inativo')}
                        </span>
                    </h3>

                    <div className={cls('mb-4 border-2 p-4', RAIO, corDaSaude)}>
                        <p className="font-bold">{d.saude.message}</p>
                        {(d.saude.issues ?? []).map((x) => <p key={x} className="mt-1 text-sm"><i className="fas fa-circle-exclamation mr-2" aria-hidden="true" />{x}</p>)}
                        {(d.saude.warnings ?? []).map((x) => <p key={x} className="mt-1 text-sm"><i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />{x}</p>)}
                    </div>

                    {activo && o.stats && o.memory && (
                        <div className="space-y-4">
                            <Barra rotulo={t('Hit Rate')} valor={`${taxa}%`} percentagem={taxa} cor={taxa >= 95 ? 'bg-emerald-500' : taxa >= 80 ? 'bg-orange-500' : 'bg-red-500'} />
                            <div className="grid grid-cols-2 gap-3">
                                <Numero rotulo={t('Hits')} valor={o.stats.hits} cor="bg-blue-50 text-blue-900" />
                                <Numero rotulo={t('Misses')} valor={o.stats.misses} cor="bg-red-50 text-red-900" />
                                <Numero rotulo={t('Scripts em cache')} valor={`${o.stats.cached_scripts} / ${o.stats.max_scripts}`} cor="bg-purple-50 text-purple-900" />
                                <Numero rotulo={t('Memória livre')} valor={`${o.memory.free_mb} MB`} cor="bg-emerald-50 text-emerald-900" />
                            </div>
                            <Barra
                                rotulo={t('Memória')}
                                valor={`${o.memory.used_mb} MB / ${o.memory.total_mb} MB`}
                                percentagem={o.memory.usage_percentage}
                                cor="bg-gradient-to-r from-purple-500 to-indigo-500"
                                nota={`${t(':n% usado', { n: o.memory.usage_percentage })}${o.memory.wasted_mb > 0 ? ` • ${t(':n MB desperdiçados', { n: o.memory.wasted_mb })}` : ''}`}
                            />
                        </div>
                    )}
                </section>

                <section className={cls(CARTAO, 'card-hover p-5')}>
                    <h3 className="mb-4 text-lg font-bold text-slate-900"><i className="fab fa-php icon-float mr-2 text-indigo-600" aria-hidden="true" />{t('Configuração PHP')}</h3>
                    <div className={cls('mb-4 flex items-center justify-between bg-indigo-50 px-4 py-3', RAIO)}>
                        <span className="text-sm font-semibold text-indigo-900">{t('Versão PHP')}</span>
                        <span className="text-lg font-bold text-indigo-700">{d.php.version}</span>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Numero rotulo={t('Memory Limit')} valor={d.php.memory_limit} cor="bg-slate-50 text-slate-900" />
                        <Numero rotulo={t('Max Execution')} valor={`${d.php.max_execution_time}s`} cor="bg-slate-50 text-slate-900" />
                        <Numero rotulo={t('Upload Max')} valor={d.php.upload_max_filesize} cor="bg-slate-50 text-slate-900" />
                        <Numero rotulo={t('Post Max')} valor={d.php.post_max_size} cor="bg-slate-50 text-slate-900" />
                        <Numero rotulo={t('Max Input Vars')} valor={d.php.max_input_vars} cor="bg-slate-50 text-slate-900" />
                    </div>

                    {activo && o.config && (
                        <div className="mt-4 border-t border-slate-200 pt-4">
                            <h4 className="mb-3 font-bold text-slate-900">{t('Configuração OPcache')}</h4>
                            <dl className="space-y-2 text-sm">
                                {([
                                    [t('Memória'), o.config.memory_consumption],
                                    [t('Máx. ficheiros'), o.config.max_files],
                                    [t('Validar timestamps'), o.config.validate_timestamps],
                                    [t('Frequência de revalidação'), o.config.revalidate_freq],
                                    [t('CLI ativo'), o.config.cli_enabled],
                                ] as Array<[string, string]>).map(([r, v]) => (
                                    <div key={r} className="flex justify-between"><dt className="text-slate-600">{r}</dt><dd className="font-bold text-slate-900">{v}</dd></div>
                                ))}
                            </dl>
                        </div>
                    )}
                </section>
            </div>

            <section className={cls('border border-blue-200 bg-blue-50 p-5', RAIO_GRANDE)}>
                <h3 className="mb-3 font-bold text-blue-900"><i className="fas fa-lightbulb mr-2" aria-hidden="true" />{t('Quando usar cada acção')}</h3>
                <div className="grid gap-3 md:grid-cols-3 text-sm">
                    <p className="rounded-xl bg-white p-3"><b className="block text-purple-900">{t('Limpar OPcache')}</b>{t('Após deploy ou mudanças no código PHP. Reseta bytecode compilado.')}</p>
                    <p className="rounded-xl bg-white p-3"><b className="block text-orange-900">{t('Limpar Todos Caches')}</b>{t('Quando houver problemas ou mudanças em configs/rotas/views.')}</p>
                    <p className="rounded-xl bg-white p-3"><b className="block text-emerald-900">{t('Otimizar Sistema')}</b>{t('Antes de ir para produção. Cria cache de config/rotas/views.')}</p>
                </div>
            </section>

            {aConfigurar && <Configurar actuais={d.actuais} perfis={d.perfis} aoFechar={() => porAConfigurar(false)} aoGerar={(m) => { porAConfigurar(false); feito(m); }} />}

            <Confirmar
                aberto={aOtimizar}
                titulo={t('Otimizar o sistema?')}
                rotulo={t('Otimizar')}
                icone="fa-gauge-high"
                cor="aviso"
                aTrabalhar={otimizar.isPending}
                erro={otimizar.error}
                aoConfirmar={() => otimizar.mutate()}
                aoFechar={() => porAOtimizar(false)}
            >
                <p>{t('Grava em cache a configuração, as rotas e as views. Uma mudança no .env depois disto só vale depois de limpar os caches.')}</p>
            </Confirmar>
        </div>
    );
}

function Accao({ i, gradiente, icone, titulo, nota, aTrabalhar = false, onClick }: {
    i: number; gradiente: string; icone: string; titulo: string; nota: string; aTrabalhar?: boolean; onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={aTrabalhar}
            className={cls('cascata group bg-gradient-to-br p-6 text-left text-white shadow-lg disabled:opacity-70', gradiente, RAIO_GRANDE, TRANSICAO, TOQUE, FOCO)}
            style={cascata(i)}
        >
            <i className={cls('fas mb-3 block text-3xl icon-float', aTrabalhar ? 'fa-circle-notch fa-spin' : icone)} aria-hidden="true" />
            <span className="block text-lg font-bold">{titulo}</span>
            <span className="block text-sm opacity-90">{nota}</span>
        </button>
    );
}

function Numero({ rotulo, valor, cor }: { rotulo: string; valor: string; cor: string }) {
    return (
        <div className={cls('p-3', RAIO, cor)}>
            <p className="text-xs font-semibold opacity-70">{rotulo}</p>
            <p className="text-lg font-bold tabular-nums">{valor}</p>
        </div>
    );
}

function Barra({ rotulo, valor, percentagem, cor, nota }: { rotulo: string; valor: string; percentagem: number; cor: string; nota?: string }) {
    return (
        <div>
            <div className="mb-1 flex justify-between text-sm"><span className="font-semibold text-slate-700">{rotulo}</span><span className="font-bold tabular-nums text-slate-800">{valor}</span></div>
            <div className="h-3 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-valuenow={percentagem} aria-valuemin={0} aria-valuemax={100} aria-label={rotulo}>
                <div className={cls('h-3 rounded-full transition-all duration-700', cor)} style={{ width: `${Math.min(100, percentagem)}%` }} />
            </div>
            {nota && <p className="mt-1 text-xs text-slate-500">{nota}</p>}
        </div>
    );
}

function Configurar({ actuais, perfis, aoFechar, aoGerar }: {
    actuais: ValoresDoIni;
    perfis: Record<'production' | 'development', Omit<ValoresDoIni, 'environment'>>;
    aoFechar: () => void;
    aoGerar: (m: string) => void;
}) {
    const [v, porV] = useState<ValoresDoIni>(actuais);
    const gerar = useMutation({ mutationFn: () => sistema.otimizacao.gerarIni(v), onSuccess: (r) => aoGerar(r.message) });
    const erros = gerar.error instanceof ErroDaApi ? gerar.error.erros : {};
    const descarga = `/superadmin/system-optimization/user-ini?${new URLSearchParams(Object.entries(v).map(([k, x]) => [k, String(x)])).toString()}`;

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Configurações PHP (.user.ini)')}
            subtitulo={t('Gerar .user.ini para cPanel')}
            icone="fa-gear"
            cor="primaria"
            largura="xl"
            rodape={
                <div className="flex flex-wrap justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <a href={descarga} className={cls('inline-flex h-10 items-center gap-2 bg-gradient-to-r from-blue-600 to-cyan-600 px-4 text-sm font-semibold text-white shadow-md', RAIO, TRANSICAO, TOQUE, FOCO)}>
                        <i className="fas fa-download" aria-hidden="true" />{t('Download .user.ini')}
                    </a>
                    <Botao cor="bom" tom="solida" icone="fa-file-circle-check" aTrabalhar={gerar.isPending} onClick={() => gerar.mutate()}>{t('Gerar no servidor')}</Botao>
                </div>
            }
        >
            <div className="space-y-5">
                <AvisoDeErro erro={Object.keys(erros).length ? null : gerar.error} />

                <div>
                    <h4 className="mb-3 font-bold text-slate-900">{t('Perfis de Configuração')}</h4>
                    <div className="grid gap-3 md:grid-cols-2">
                        {(['production', 'development'] as const).map((p) => (
                            <button key={p} type="button" onClick={() => porV({ environment: p, ...perfis[p] })}
                                className={cls('border-2 p-4 text-left', RAIO, TRANSICAO, TOQUE, FOCO, v.environment === p ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:border-indigo-300')}>
                                <span className="block font-bold text-slate-900"><i className={cls('fas mr-2', p === 'production' ? 'fa-rocket text-emerald-600' : 'fa-code text-blue-600')} aria-hidden="true" />{p === 'production' ? t('Produção') : t('Desenvolvimento')}</span>
                                <span className="text-sm text-slate-600">{p === 'production' ? t('Máxima performance, sem validação') : t('Validação ativa, vê mudanças')}</span>
                            </button>
                        ))}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Campo etiqueta="opcache.validate_timestamps" erro={erros.validate_timestamps}>
                        <select className={entrada} value={v.validate_timestamps} onChange={(e) => porV({ ...v, validate_timestamps: Number(e.target.value) })}>
                            <option value={0}>{t('0 - Desabilitado (Produção)')}</option>
                            <option value={1}>{t('1 - Habilitado (Desenvolvimento)')}</option>
                        </select>
                    </Campo>
                    <Campo etiqueta="opcache.revalidate_freq" erro={erros.revalidate_freq} ajuda={t('Segundos entre verificações')}>
                        <input type="number" min={0} className={entrada} value={v.revalidate_freq} onChange={(e) => porV({ ...v, revalidate_freq: Number(e.target.value) })} />
                    </Campo>
                    <Campo etiqueta="max_input_vars" erro={erros.max_input_vars}>
                        <input type="number" min={100} className={entrada} value={v.max_input_vars} onChange={(e) => porV({ ...v, max_input_vars: Number(e.target.value) })} />
                    </Campo>
                    <Campo etiqueta="memory_limit" erro={erros.memory_limit}>
                        <input className={entrada} value={v.memory_limit} placeholder="512M" onChange={(e) => porV({ ...v, memory_limit: e.target.value })} />
                    </Campo>
                    <Campo etiqueta="max_execution_time" erro={erros.max_execution_time} ajuda={t('Segundos')}>
                        <input type="number" min={0} className={entrada} value={v.max_execution_time} onChange={(e) => porV({ ...v, max_execution_time: Number(e.target.value) })} />
                    </Campo>
                </div>

                <div className={cls('border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900', RAIO)}>
                    <h4 className="mb-2 font-bold"><i className="fas fa-list-ol mr-2" aria-hidden="true" />{t('Como aplicar no cPanel')}</h4>
                    <ol className="list-decimal space-y-1 pl-5">
                        <li>{t('Clique em "Download .user.ini" abaixo')}</li>
                        <li>{t('No cPanel, vá em File Manager')}</li>
                        <li>{t('Navegue até a pasta public_html')}</li>
                        <li>{t('Faça upload do arquivo .user.ini')}</li>
                        <li>{t('Aguarde 5 minutos para o servidor aplicar as mudanças')}</li>
                        <li>{t('Volte aqui e clique em "Limpar OPcache"')}</li>
                    </ol>
                </div>
            </div>
        </Modal>
    );
}
