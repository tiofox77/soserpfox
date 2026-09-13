import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type ReactNode, useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type InstalacaoOffline, type PedidoDeLicenca, sistema } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { CartaoNumero } from '@/ui/CartaoNumero';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { CARTAO, RAIO, TRANSICAO, cls, data, dataHora, haQuanto } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { Confirmar, Dado, ErroDoEcra, Interruptor, Recado } from './comum';

type Dados = Awaited<ReturnType<typeof sistema.licenciamento.ler>>;

const SITUACAO = (s: InstalacaoOffline['situacao']) => ({
    activa: { rotulo: t('activa'), cor: 'bom' as const },
    silenciosa: { rotulo: t('silenciosa'), cor: 'aviso' as const },
    expirada: { rotulo: t('expirada'), cor: 'perigo' as const },
    nunca_ligou: { rotulo: t('nunca ligou'), cor: 'neutra' as const },
}[s]);

/**
 * O LICENCIAMENTO OFFLINE — as instalações on-premise, os pedidos de licença,
 * emitir, e as versões com o rollout por empresa.
 *
 * O ecrã nunca vê a chave privada: o servidor diz só se está configurada e,
 * não servindo, porquê. O token de uma licença aparece — é assinado e preso à
 * máquina a que se destina, e é para ser enviado ao cliente.
 */
export default function Licenciamento() {
    const fila = useQueryClient();
    const [aba, porAba] = useState('instalacoes');
    const [recado, porRecado] = useRecadoNoCanto(null);
    const [aVer, porAVer] = useState<number | null>(null);
    const [aAprovar, porAAprovar] = useState<PedidoDeLicenca | null>(null);

    const dados = useQuery({ queryKey: ['plataforma', 'licenciamento'], queryFn: sistema.licenciamento.ler });
    const feito = (m: string) => { porRecado(m); void fila.invalidateQueries({ queryKey: ['plataforma', 'licenciamento'] }); };

    const renovarRapido = useMutation({ mutationFn: (id: number) => sistema.licenciamento.renovar(id, 365), onSuccess: (r) => feito(r.message) });

    if (dados.isPending) return <Carregando linhas={8} />;
    if (dados.isError) return <ErroDoEcra titulo={t('Não foi possível abrir o licenciamento')} erro={dados.error} />;

    const d = dados.data;
    const pendentes = d.pedidos.filter((p) => p.estado === 'pendente').length;

    return (
        <div className="space-y-4">
            <Faixa titulo={t('Licenciamento Offline')} subtitulo={t('Emitir licenças, publicar versões e rollout por-tenant')} icone="fa-key" cor="roxo">
                <EstadoNaFaixa icone={d.estado.problema_da_chave ? 'fa-triangle-exclamation' : 'fa-shield-halved'}>
                    {d.estado.problema_da_chave ? t('Chave por corrigir') : t('Chave de assinatura pronta')}
                </EstadoNaFaixa>
                {pendentes > 0 && <EstadoNaFaixa icone="fa-bell">{t(':n por aprovar', { n: pendentes })}</EstadoNaFaixa>}
            </Faixa>

            {!d.estado.cripto && (
                <Alerta cor="perigo" icone="fa-circle-exclamation">
                    <strong>{t('Cripto indisponível neste servidor.')}</strong> {t('Falta a extensão PHP sodium (ou o pacote paragonie/sodium_compat). Sem ela não é possível assinar licenças.')}
                </Alerta>
            )}
            {d.estado.problema_da_chave && (
                <Alerta cor="perigo" icone="fa-key"><strong>{t('Chave de assinatura por corrigir:')}</strong> {d.estado.problema_da_chave}</Alerta>
            )}
            {(!d.estado.chave_das_licencas || !d.estado.chave_das_versoes) && (
                <Alerta cor="aviso" icone="fa-triangle-exclamation">
                    {t('Chave(s) privada(s) por configurar no .env:')}{' '}
                    {!d.estado.chave_das_licencas && <code className="font-mono">LICENSE_SIGNING_KEY</code>}{' '}
                    {!d.estado.chave_das_versoes && <code className="font-mono">LICENSE_UPDATE_SIGNING_KEY</code>}
                </Alerta>
            )}

            <Recado texto={recado} aoFechar={() => porRecado(null)} />
            <AvisoDeErro erro={renovarRapido.error} />

            <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                <CartaoNumero rotulo={t('Instalações')} valor={d.resumo.total} icone="fa-server" tom="indigo" aspecto="claro" />
                <CartaoNumero rotulo={t('Activas')} valor={d.resumo.activas} icone="fa-circle-check" tom="verde" aspecto="claro" />
                <CartaoNumero rotulo={t('Silenciosas')} valor={d.resumo.silenciosas} icone="fa-volume-xmark" tom="ambar" aspecto="claro" />
                <CartaoNumero rotulo={t('Expiradas')} valor={d.resumo.expiradas} icone="fa-hourglass-end" tom="vermelho" aspecto="claro" />
                <CartaoNumero rotulo={t('Por ligar')} valor={d.resumo.por_ligar} icone="fa-plug-circle-xmark" tom="cinza" aspecto="claro" />
            </div>

            <div className={cls(CARTAO, 'p-4')}>
                <Separadores
                    abas={[
                        { chave: 'instalacoes', rotulo: t('Clientes offline'), icone: 'fa-server' },
                        { chave: 'pedidos', rotulo: pendentes ? t('Pedidos de licença (:n)', { n: pendentes }) : t('Pedidos de licença'), icone: 'fa-inbox' },
                        { chave: 'emitir', rotulo: t('Emitir licença'), icone: 'fa-signature' },
                        { chave: 'versoes', rotulo: t('Versões'), icone: 'fa-cloud-arrow-up' },
                    ]}
                    activa={aba}
                    aoMudar={porAba}
                />

                <div className="pt-4">
                    <PainelDoSeparador chave="instalacoes" activa={aba}>
                        {d.instalacoes.length === 0 ? <SemNada icone="fa-server" frase={t('Ainda não há instalações offline conhecidas.')} /> : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                        <tr>
                                            <th className="px-4 py-3">{t('Empresa')}</th><th className="px-4 py-3">{t('Plano / Módulos')}</th><th className="px-4 py-3">{t('Util.')}</th>
                                            <th className="px-4 py-3">{t('Versão')}</th><th className="px-4 py-3">{t('Último contacto')}</th><th className="px-4 py-3">{t('Licença')}</th><th className="px-4 py-3" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {d.instalacoes.map((i, n) => {
                                            const s = SITUACAO(i.situacao);

                                            return (
                                                <tr key={i.id} className={cls('entra hover:bg-slate-50', TRANSICAO)} style={cascata(n)}>
                                                    <td className="px-4 py-3">
                                                        <p className="font-semibold text-slate-900">{i.empresa}</p>
                                                        <p className="font-mono text-xs text-slate-400" title={i.maquina ?? undefined}>{i.maquina ? `${i.maquina.slice(0, 16)}…` : t('licença flutuante')}</p>
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-700">{i.plano ?? '—'}<p className="text-xs text-slate-400">{i.todos_os_modulos ? t('todos os módulos') : t(':n módulo(s)', { n: i.modulos })}</p></td>
                                                    <td className="px-4 py-3 text-slate-700">{i.max_utilizadores ?? '∞'}</td>
                                                    <td className="px-4 py-3 text-slate-600">{i.versao ?? '—'}</td>
                                                    <td className="px-4 py-3">
                                                        {i.ultimo_contacto ? <><span className="text-slate-700">{haQuanto(i.ultimo_contacto)}</span><p className="text-xs text-slate-400">{dataHora(i.ultimo_contacto)}</p></> : <span className="text-slate-400">{t('nunca ligou')}</span>}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Etiqueta cor={s.cor} ponto>{s.rotulo}</Etiqueta>
                                                        {i.expira_em && <p className="mt-1 text-xs text-slate-400">{t('expira :data', { data: data(i.expira_em) })}</p>}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                                        <div className="flex justify-end gap-2">
                                                            <Botao altura="pequeno" cor="primaria" icone="fa-eye" onClick={() => porAVer(i.id)}>{t('Detalhes')}</Botao>
                                                            <Botao altura="pequeno" cor="bom" icone="fa-rotate" aTrabalhar={renovarRapido.isPending && renovarRapido.variables === i.id} onClick={() => renovarRapido.mutate(i.id)}>{t('Renovar 1 ano')}</Botao>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="pedidos" activa={aba}>
                        {d.pedidos.length === 0 ? <SemNada icone="fa-inbox" frase={t('Nenhum pedido de licença.')} /> : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                        <tr><th className="px-4 py-3">{t('Empresa')}</th><th className="px-4 py-3">{t('Responsável')}</th><th className="px-4 py-3">{t('Util.')}</th><th className="px-4 py-3">{t('Máquina')}</th><th className="px-4 py-3">{t('Estado')}</th><th className="px-4 py-3" /></tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {d.pedidos.map((p, n) => (
                                            <tr key={p.id} className={cls('entra hover:bg-slate-50', TRANSICAO, p.estado === 'pendente' && 'bg-amber-50/40')} style={cascata(n)}>
                                                <td className="px-4 py-3"><p className="font-semibold text-slate-900">{p.empresa}</p><p className="text-xs text-slate-500">NIF {p.nif ?? '—'} · {dataHora(p.pedido_em)}</p></td>
                                                <td className="px-4 py-3 text-slate-700">{p.responsavel ?? '—'}<p className="text-xs text-slate-400">{p.email ?? p.telefone ?? ''}</p></td>
                                                <td className="px-4 py-3 text-slate-700">{p.utilizadores ?? '—'}</td>
                                                <td className="px-4 py-3"><span className="font-mono text-xs text-slate-400" title={p.maquina ?? undefined}>{p.maquina ? `${p.maquina.slice(0, 12)}…` : '—'}</span></td>
                                                <td className="px-4 py-3">
                                                    <Etiqueta cor={p.estado === 'pendente' ? 'aviso' : p.estado === 'aprovado' ? 'bom' : 'perigo'} ponto>
                                                        {p.estado === 'pendente' ? t('pendente') : p.estado === 'aprovado' ? t('aprovado') : t('recusado')}
                                                    </Etiqueta>
                                                    {p.estado === 'aprovado' && p.entregue && <p className="mt-1 text-xs text-slate-400">{t('entregue')}</p>}
                                                    {p.estado === 'recusado' && p.motivo_recusa && <p className="mt-1 max-w-xs text-xs text-slate-500">{p.motivo_recusa}</p>}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {p.estado === 'pendente' && <Botao altura="pequeno" cor="primaria" tom="solida" icone="fa-gavel" onClick={() => porAAprovar(p)}>{t('Analisar')}</Botao>}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="emitir" activa={aba}>
                        <Emitir d={d} aoEmitir={() => void fila.invalidateQueries({ queryKey: ['plataforma', 'licenciamento'] })} />
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="versoes" activa={aba}>
                        <Versoes d={d} aoMudar={feito} />
                    </PainelDoSeparador>
                </div>
            </div>

            {aVer !== null && <Instalacao id={aVer} aoFechar={() => porAVer(null)} aoMudar={feito} />}
            {aAprovar && <Aprovar pedido={aAprovar} d={d} aoFechar={() => porAAprovar(null)} aoDecidir={(m) => { porAAprovar(null); feito(m); }} />}
        </div>
    );
}

function Alerta({ cor, icone, children }: { cor: 'perigo' | 'aviso'; icone: string; children: ReactNode }) {
    return (
        <div role="alert" className={cls('entra border px-4 py-3 text-sm', RAIO, cor === 'perigo' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-800')}>
            <i className={cls('fas mr-2', icone)} aria-hidden="true" />{children}
        </div>
    );
}

function Token({ token, rotulo }: { token: string; rotulo: string }) {
    const [copiado, porCopiado] = useState(false);

    return (
        <div className="entra">
            <div className="mb-1 flex items-center justify-between">
                <Rotulo>{rotulo}</Rotulo>
                <Botao altura="pequeno" icone={copiado ? 'fa-check' : 'fa-copy'} cor={copiado ? 'bom' : 'neutra'}
                    onClick={() => void navigator.clipboard?.writeText(token).then(() => { porCopiado(true); setTimeout(() => porCopiado(false), 2000); })}>
                    {copiado ? t('Copiado') : t('Copiar')}
                </Botao>
            </div>
            <textarea readOnly rows={3} value={token} onFocus={(e) => e.target.select()} className={cls('w-full border border-slate-700 bg-slate-900 px-4 py-3 font-mono text-xs text-emerald-300', RAIO)} />
        </div>
    );
}

function Modulos({ modulos, todos, escolhidos, aoMudarTodos, aoMudar }: {
    modulos: Dados['opcoes']['modulos']; todos: boolean; escolhidos: string[]; aoMudarTodos: (v: boolean) => void; aoMudar: (v: string[]) => void;
}) {
    return (
        <div>
            <Interruptor cor="indigo" rotulo={t('Todos os módulos')} valor={todos} aoMudar={aoMudarTodos} />
            {!todos && (
                <div className={cls('entra mt-2 grid max-h-48 gap-1 overflow-y-auto border border-slate-200 p-2 sm:grid-cols-2 lg:grid-cols-3', RAIO)}>
                    {modulos.map((m) => (
                        <label key={m.slug} className="flex items-center gap-2 rounded-lg px-2 py-1 text-sm text-slate-700 hover:bg-slate-50">
                            <input type="checkbox" className="rounded text-indigo-600" checked={escolhidos.includes(m.slug)}
                                onChange={(e) => aoMudar(e.target.checked ? [...escolhidos, m.slug] : escolhidos.filter((x) => x !== m.slug))} />
                            {m.nome}
                        </label>
                    ))}
                </div>
            )}
        </div>
    );
}

function Emitir({ d, aoEmitir }: { d: Dados; aoEmitir: () => void }) {
    const [f, porF] = useState({ tenant_id: '', dias: 365, graca: '', fingerprint: '', max_utilizadores: '', todos_os_modulos: true, modulos: [] as string[] });
    const emitir = useMutation({
        mutationFn: () => sistema.licenciamento.emitir({
            ...f,
            tenant_id: f.tenant_id ? Number(f.tenant_id) : null,
            graca: f.graca === '' ? null : Number(f.graca),
            max_utilizadores: f.max_utilizadores === '' ? null : Number(f.max_utilizadores),
        }),
        onSuccess: aoEmitir,
    });
    const erros = emitir.error instanceof ErroDaApi ? emitir.error.erros : {};

    return (
        <div className="space-y-4">
            {emitir.data && <Recado texto={emitir.data.message} aoFechar={() => emitir.reset()} />}
            <AvisoDeErro erro={Object.keys(erros).length ? null : emitir.error} />

            <div className="grid gap-4 md:grid-cols-4">
                <Campo etiqueta={t('Empresa')} obrigatorio erro={erros.tenant_id} className="md:col-span-2">
                    <select className={entrada} value={f.tenant_id} onChange={(e) => porF({ ...f, tenant_id: e.target.value })}>
                        <option value="">{t('Escolha…')}</option>
                        {d.opcoes.empresas.map((e) => <option key={e.id} value={e.id}>{e.nome} (#{e.id})</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Validade (dias)')} obrigatorio erro={erros.dias}>
                    <input type="number" min={1} max={3650} className={entrada} value={f.dias} onChange={(e) => porF({ ...f, dias: Number(e.target.value) })} />
                </Campo>
                <Campo etiqueta={t('Graça offline (dias)')} erro={erros.graca}>
                    <input type="number" min={1} max={365} className={entrada} placeholder={t('padrão')} value={f.graca} onChange={(e) => porF({ ...f, graca: e.target.value })} />
                </Campo>
                <Campo etiqueta={t('Máx. utilizadores')} erro={erros.max_utilizadores}>
                    <input type="number" min={1} className={entrada} placeholder={t('sem limite')} value={f.max_utilizadores} onChange={(e) => porF({ ...f, max_utilizadores: e.target.value })} />
                </Campo>
                <Campo etiqueta={t('Prender à máquina (fingerprint) — opcional')} erro={erros.fingerprint} className="md:col-span-3">
                    <input className={cls(entrada, 'font-mono')} maxLength={64} placeholder={t('deixe vazio para licença flutuante')} value={f.fingerprint} onChange={(e) => porF({ ...f, fingerprint: e.target.value })} />
                </Campo>
            </div>

            <Modulos modulos={d.opcoes.modulos} todos={f.todos_os_modulos} escolhidos={f.modulos}
                aoMudarTodos={(v) => porF({ ...f, todos_os_modulos: v })} aoMudar={(v) => porF({ ...f, modulos: v })} />
            {erros.modulos && <p role="alert" className="text-xs font-medium text-red-600">{erros.modulos[0]}</p>}

            <div className="flex justify-end">
                <Botao cor="primaria" tom="solida" icone="fa-signature" aTrabalhar={emitir.isPending} disabled={Boolean(d.estado.problema_da_chave)} onClick={() => emitir.mutate()}>{t('Emitir')}</Botao>
            </div>

            {emitir.data?.token && <Token token={emitir.data.token} rotulo={t('Token da licença — copie e envie ao cliente')} />}
        </div>
    );
}

function Versoes({ d, aoMudar }: { d: Dados; aoMudar: (m: string) => void }) {
    const vazio = { versao: '', min_versao: '', pacote_url: '', pacote_sha256: '', notas: '', obrigatorio: false, rollout: 'none' as 'none' | 'all' };
    const [f, porF] = useState(vazio);
    const [alvo, porAlvo] = useState({ versao: '', tenant_id: '' });

    const publicar = useMutation({ mutationFn: () => sistema.licenciamento.publicarVersao(f), onSuccess: (r) => { porF(vazio); aoMudar(r.message); } });
    const rollout = useMutation({ mutationFn: (x: { id: number; rollout: 'none' | 'all' }) => sistema.licenciamento.definirRollout(x.id, x.rollout), onSuccess: (r) => aoMudar(r.message) });
    const adicionar = useMutation({ mutationFn: () => sistema.licenciamento.adicionarAlvo(Number(alvo.tenant_id), alvo.versao), onSuccess: (r) => { porAlvo({ versao: '', tenant_id: '' }); aoMudar(r.message); } });
    const remover = useMutation({ mutationFn: (id: number) => sistema.licenciamento.removerAlvo(id), onSuccess: (r) => aoMudar(r.message) });

    const erros = publicar.error instanceof ErroDaApi ? publicar.error.erros : {};
    const errosDoAlvo = adicionar.error instanceof ErroDaApi ? adicionar.error.erros : {};

    return (
        <div className="space-y-6">
            <section>
                <h3 className="mb-3 font-bold text-slate-900"><i className="fas fa-cloud-arrow-up icon-float mr-2 text-cyan-600" aria-hidden="true" />{t('Publicar versão')}</h3>
                <AvisoDeErro erro={Object.keys(erros).length ? null : publicar.error} />
                <div className="grid gap-4 md:grid-cols-4">
                    <Campo etiqueta={t('Versão')} obrigatorio erro={erros.versao}>
                        <input className={entrada} placeholder="1.2.0" value={f.versao} onChange={(e) => porF({ ...f, versao: e.target.value })} />
                    </Campo>
                    <Campo etiqueta={t('Versão mínima')} erro={erros.min_versao}>
                        <input className={entrada} placeholder="1.0.0" value={f.min_versao} onChange={(e) => porF({ ...f, min_versao: e.target.value })} />
                    </Campo>
                    <Campo etiqueta={t('URL do pacote')} obrigatorio erro={erros.pacote_url} className="md:col-span-2">
                        <input type="url" className={entrada} value={f.pacote_url} onChange={(e) => porF({ ...f, pacote_url: e.target.value })} />
                    </Campo>
                    <Campo etiqueta={t('SHA-256 do pacote')} obrigatorio erro={erros.pacote_sha256} className="md:col-span-2" ajuda={t(':n de 64 caracteres', { n: f.pacote_sha256.length })}>
                        <input className={cls(entrada, 'font-mono')} maxLength={64} value={f.pacote_sha256} onChange={(e) => porF({ ...f, pacote_sha256: e.target.value.trim() })} />
                    </Campo>
                    <Campo etiqueta={t('Rollout')} erro={erros.rollout}>
                        <select className={entrada} value={f.rollout} onChange={(e) => porF({ ...f, rollout: e.target.value as 'none' | 'all' })}>
                            <option value="none">{t('none (só alvos)')}</option>
                            <option value="all">{t('all (toda a gente)')}</option>
                        </select>
                    </Campo>
                    <div className="flex items-end"><Interruptor cor="amber" rotulo={t('Obrigatória')} valor={f.obrigatorio} aoMudar={(v) => porF({ ...f, obrigatorio: v })} /></div>
                    <Campo etiqueta={t('Notas')} erro={erros.notas} className="md:col-span-4">
                        <textarea rows={2} className={entrada} value={f.notas} onChange={(e) => porF({ ...f, notas: e.target.value })} />
                    </Campo>
                </div>
                <div className="mt-3 flex justify-end">
                    <Botao cor="primaria" tom="solida" icone="fa-upload" aTrabalhar={publicar.isPending} onClick={() => publicar.mutate()}>{t('Publicar')}</Botao>
                </div>
            </section>

            <section className="border-t border-slate-200 pt-5">
                <h3 className="mb-3 font-bold text-slate-900"><i className="fas fa-list-check icon-float mr-2 text-emerald-600" aria-hidden="true" />{t('Versões & rollout por-tenant')}</h3>
                <AvisoDeErro erro={rollout.error ?? remover.error ?? (Object.keys(errosDoAlvo).length ? null : adicionar.error)} />
                <div className="mb-4 grid gap-3 md:grid-cols-[1fr_2fr_auto]">
                    <Campo etiqueta={t('Versão')} erro={errosDoAlvo.versao}>
                        <select className={entrada} value={alvo.versao} onChange={(e) => porAlvo({ ...alvo, versao: e.target.value })}>
                            <option value="">{t('Escolha…')}</option>
                            {d.versoes.map((v) => <option key={v.id} value={v.versao}>{v.versao}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Empresa')} erro={errosDoAlvo.tenant_id}>
                        <select className={entrada} value={alvo.tenant_id} onChange={(e) => porAlvo({ ...alvo, tenant_id: e.target.value })}>
                            <option value="">{t('Escolha…')}</option>
                            {d.opcoes.empresas.map((e) => <option key={e.id} value={e.id}>{e.nome} (#{e.id})</option>)}
                        </select>
                    </Campo>
                    <div className="flex items-end"><Botao cor="bom" tom="solida" icone="fa-plus" aTrabalhar={adicionar.isPending} onClick={() => adicionar.mutate()}>{t('Adicionar alvo')}</Botao></div>
                </div>

                {d.versoes.length === 0 ? <SemNada icone="fa-inbox" frase={t('Nenhuma versão publicada.')} /> : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                <tr><th className="px-4 py-3">{t('Versão')}</th><th className="px-4 py-3">{t('Rollout')}</th><th className="px-4 py-3">{t('Tenants (canary)')}</th><th className="px-4 py-3">{t('Obrig.')}</th></tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {d.versoes.map((v) => (
                                    <tr key={v.id}>
                                        <td className="px-4 py-3 font-mono text-slate-900">{v.versao}<p className="text-xs text-slate-400">min {v.min_versao ?? '—'}</p></td>
                                        <td className="px-4 py-3">
                                            <div className="inline-flex overflow-hidden rounded-lg border border-slate-200" role="group" aria-label={t('Rollout')}>
                                                {(['none', 'all'] as const).map((r) => (
                                                    <button key={r} type="button" aria-pressed={v.rollout === r} disabled={rollout.isPending}
                                                        onClick={() => v.rollout !== r && rollout.mutate({ id: v.id, rollout: r })}
                                                        className={cls('px-3 py-1.5 text-xs font-semibold', TRANSICAO, v.rollout === r ? (r === 'all' ? 'bg-emerald-600 text-white' : 'bg-slate-700 text-white') : 'bg-white text-slate-500 hover:bg-slate-50')}>
                                                        {r}
                                                    </button>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1.5">
                                                {v.alvos.length === 0 && <span className="text-xs text-slate-400">{v.rollout === 'all' ? t('todos') : '—'}</span>}
                                                {v.alvos.map((a) => (
                                                    <span key={a.id} className="entra inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200">
                                                        {a.empresa}
                                                        <button type="button" className="text-red-500 hover:text-red-700" onClick={() => remover.mutate(a.id)} aria-label={t('Remover :empresa', { empresa: a.empresa })}>
                                                            <i className="fas fa-xmark" aria-hidden="true" />
                                                        </button>
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">{v.obrigatorio ? <span className="font-semibold text-amber-600">{t('sim')}</span> : <span className="text-slate-400">{t('não')}</span>}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </div>
    );
}

function Instalacao({ id, aoFechar, aoMudar }: { id: number; aoFechar: () => void; aoMudar: (m: string) => void }) {
    const fila = useQueryClient();
    const pedido = useQuery({ queryKey: ['plataforma', 'licenciamento', 'instalacao', id], queryFn: () => sistema.licenciamento.instalacao(id) });
    const i = pedido.data?.instalacao;

    const [empresa, porEmpresa] = useState({ nome: '', nif: '', email: '', telefone: '' });
    const [dias, porDias] = useState(365);
    const [mensagem, porMensagem] = useState('');
    const [aSuspender, porASuspender] = useState(false);
    const [local, porLocal] = useState<string | null>(null);

    useEffect(() => {
        if (!i) return;
        porEmpresa({ nome: i.empresa?.nome ?? '', nif: i.empresa?.nif ?? '', email: i.empresa?.email ?? '', telefone: i.empresa?.telefone ?? '' });
        porDias(i.dias_sugeridos);
    }, [i?.id]); // eslint-disable-line react-hooks/exhaustive-deps

    const depois = (m: string) => { porLocal(m); aoMudar(m); void fila.invalidateQueries({ queryKey: ['plataforma', 'licenciamento', 'instalacao', id] }); };

    const guardar = useMutation({ mutationFn: () => sistema.licenciamento.guardarEmpresa(id, empresa), onSuccess: (r) => depois(r.message) });
    const renovar = useMutation({ mutationFn: (n: number) => sistema.licenciamento.renovar(id, n), onSuccess: (r) => depois(r.message) });
    const avisar = useMutation({ mutationFn: () => sistema.licenciamento.avisar(id, mensagem), onSuccess: (r) => { porMensagem(''); depois(r.message); } });
    const suspender = useMutation({ mutationFn: () => sistema.licenciamento.alternarSuspensao(id), onSuccess: (r) => { porASuspender(false); depois(r.message); } });

    const errosEmpresa = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const errosRenovar = renovar.error instanceof ErroDaApi ? renovar.error.erros : {};
    const errosAviso = avisar.error instanceof ErroDaApi ? avisar.error.erros : {};

    const faltam = () => {
        if (!i?.expira_em) return null;
        const ms = new Date(i.expira_em).getTime() - Date.now();
        if (ms <= 0) return t('expirada :quando', { quando: haQuanto(i.expira_em) });
        const h = Math.floor(ms / 3_600_000);
        return h >= 24 ? t('faltam :d d :h h', { d: Math.floor(h / 24), h: h % 24 }) : h >= 1 ? t('faltam :h h :m m', { h, m: Math.floor((ms % 3_600_000) / 60_000) }) : t('faltam :m m', { m: Math.floor(ms / 60_000) });
    };

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={i?.empresa?.nome ?? t('Instalação')}
            subtitulo={t('Licença, máquina, contactos e histórico')}
            icone="fa-server"
            cor="roxo"
            largura="xl"
            rodape={i && (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    {i.empresa && (i.empresa.activa
                        ? <Botao cor="perigo" icone="fa-ban" onClick={() => porASuspender(true)}>{t('Suspender empresa')}</Botao>
                        : <Botao cor="bom" icone="fa-circle-play" aTrabalhar={suspender.isPending} onClick={() => suspender.mutate()}>{t('Reactivar empresa')}</Botao>)}
                    <Botao cor="neutra" onClick={aoFechar}>{t('Fechar')}</Botao>
                </div>
            )}
        >
            {pedido.isPending && <Carregando linhas={6} />}
            <AvisoDeErro erro={pedido.error} />
            {i && (
                <div className="space-y-5">
                    <Recado texto={local} aoFechar={() => porLocal(null)} />
                    <AvisoDeErro erro={suspender.error} />

                    <div className="grid gap-4 lg:grid-cols-2">
                        <section className={cls('border border-slate-200 p-4', RAIO)}>
                            <div className="mb-3 flex items-center justify-between">
                                <h4 className="text-xs font-bold uppercase text-slate-500">{t('Empresa')}</h4>
                                {i.empresa && (i.empresa.activa ? <Etiqueta cor="bom" ponto>{t('Activa')}</Etiqueta> : <Etiqueta cor="perigo" ponto>{t('Suspensa')}</Etiqueta>)}
                            </div>
                            {!i.empresa ? <p className="text-sm text-red-700">{t('A empresa já não existe.')}</p> : (
                                <div className="space-y-3">
                                    <AvisoDeErro erro={Object.keys(errosEmpresa).length ? null : guardar.error} />
                                    <Campo etiqueta={t('Nome')} obrigatorio erro={errosEmpresa.nome}><input className={entrada} value={empresa.nome} onChange={(e) => porEmpresa({ ...empresa, nome: e.target.value })} /></Campo>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Campo etiqueta={t('NIF')} erro={errosEmpresa.nif}><input className={entrada} value={empresa.nif} onChange={(e) => porEmpresa({ ...empresa, nif: e.target.value })} /></Campo>
                                        <Campo etiqueta={t('Telefone')} erro={errosEmpresa.telefone}><input className={entrada} value={empresa.telefone} onChange={(e) => porEmpresa({ ...empresa, telefone: e.target.value })} /></Campo>
                                    </div>
                                    <Campo etiqueta={t('Email')} erro={errosEmpresa.email}><input type="email" className={entrada} value={empresa.email} onChange={(e) => porEmpresa({ ...empresa, email: e.target.value })} /></Campo>
                                    <div className="flex justify-end"><Botao altura="pequeno" cor="primaria" icone="fa-floppy-disk" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>{t('Guardar')}</Botao></div>
                                </div>
                            )}
                        </section>

                        <section className={cls('border border-slate-200 p-4', RAIO)}>
                            <h4 className="mb-3 text-xs font-bold uppercase text-slate-500">{t('Licença')}</h4>
                            <dl className="grid grid-cols-2 gap-3 text-sm">
                                <Dado rotulo={t('Plano')}>{i.plano ?? '—'}</Dado>
                                <Dado rotulo={t('Máx. utilizadores')}>{i.max_utilizadores ?? t('sem limite')}</Dado>
                                <Dado rotulo={t('Emitida')}>{data(i.emitida_em)}</Dado>
                                <Dado rotulo={t('Expira')}>{dataHora(i.expira_em)}{faltam() && <span className={cls('block text-xs font-semibold', i.situacao === 'expirada' ? 'text-red-600' : 'text-emerald-700')}>{faltam()}</span>}</Dado>
                                <Dado rotulo={t('Módulos')} className="col-span-2">
                                    {i.modulos.includes('*') ? <Etiqueta cor="primaria">{t('todos os módulos')}</Etiqueta> : (
                                        <span className="flex flex-wrap gap-1">{i.modulos.map((m) => <Etiqueta key={m}>{m}</Etiqueta>)}</span>
                                    )}
                                </Dado>
                            </dl>

                            <div className="mt-4 border-t border-slate-200 pt-4">
                                <Rotulo>{t('Renovar por')}</Rotulo>
                                <div className="flex flex-wrap items-end gap-2">
                                    <input type="number" min={1} max={3650} className={cls(entrada, 'w-24')} value={dias} onChange={(e) => porDias(Number(e.target.value))} aria-label={t('dias')} />
                                    <Botao cor="bom" tom="solida" icone="fa-rotate" aTrabalhar={renovar.isPending && renovar.variables === dias} onClick={() => renovar.mutate(dias)}>{t('Renovar')}</Botao>
                                    <span className="text-slate-300">|</span>
                                    {([[365, t('1 ano')], [30, t('30 dias')], [7, t('7 dias')], [1, t('1 dia')]] as Array<[number, string]>).map(([n, r]) => (
                                        <Botao key={n} altura="pequeno" cor="bom" disabled={renovar.isPending} onClick={() => renovar.mutate(n)}>{r}</Botao>
                                    ))}
                                </div>
                                {errosRenovar.dias && <p role="alert" className="mt-1 text-xs font-medium text-red-600">{errosRenovar.dias[0]}</p>}
                                <AvisoDeErro erro={Object.keys(errosRenovar).length ? null : renovar.error} />
                            </div>
                        </section>
                    </div>

                    <section className={cls('border border-slate-200 p-4', RAIO)}>
                        <h4 className="mb-3 text-xs font-bold uppercase text-slate-500">{t('Máquina e comunicação')}</h4>
                        <dl className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <Dado rotulo={t('Máquina')}><span className="break-all font-mono text-xs">{i.maquina ?? t('licença flutuante')}</span></Dado>
                            <Dado rotulo={t('Versão')}>{i.versao ?? '—'}</Dado>
                            <Dado rotulo={t('Último IP')}>{i.ultimo_ip ?? '—'}</Dado>
                            <Dado rotulo={t('Último contacto')}>{i.ultimo_contacto ? <>{dataHora(i.ultimo_contacto)} <span className="text-xs text-slate-400">({haQuanto(i.ultimo_contacto)})</span></> : t('nunca ligou')}</Dado>
                        </dl>
                        {i.pedido && <p className="mt-3 text-xs text-slate-500">{t('Pedido')}: <span className="font-mono">{i.pedido.codigo}</span>{i.pedido.responsavel && ` · ${i.pedido.responsavel}`}</p>}
                    </section>

                    {(renovar.data?.token ?? i.token) && <Token token={(renovar.data?.token ?? i.token) as string} rotulo={t('Token da licença (reenviar ao cliente)')} />}

                    <section className={cls('border border-slate-200 p-4', RAIO)}>
                        <h4 className="mb-2 text-xs font-bold uppercase text-slate-500">{t('Avisar o cliente (SMS)')}</h4>
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <input className={entrada} maxLength={300} value={mensagem} onChange={(e) => porMensagem(e.target.value)}
                                placeholder={i.empresa?.telefone ? t('Mensagem para :telefone', { telefone: i.empresa.telefone }) : t('Esta empresa não tem telefone na ficha')}
                                disabled={!i.empresa?.telefone} aria-label={t('Avisar o cliente (SMS)')} />
                            <Botao cor="primaria" tom="solida" icone="fa-paper-plane" aTrabalhar={avisar.isPending} disabled={!i.empresa?.telefone} onClick={() => avisar.mutate()}>{t('Enviar')}</Botao>
                        </div>
                        {errosAviso.mensagem && <p role="alert" className="mt-1 text-xs font-medium text-red-600">{errosAviso.mensagem[0]}</p>}
                        <AvisoDeErro erro={Object.keys(errosAviso).length ? null : avisar.error} />
                    </section>
                </div>
            )}

            <Confirmar
                aberto={aSuspender}
                titulo={t('Suspender a empresa?')}
                subtitulo={i?.empresa?.nome}
                rotulo={t('Suspender')}
                icone="fa-ban"
                aTrabalhar={suspender.isPending}
                aoConfirmar={() => suspender.mutate()}
                aoFechar={() => porASuspender(false)}
            >
                <p>{t('Suspender corta o acesso a TODA a gente desta empresa no próximo check-in. Continuar?')}</p>
            </Confirmar>
        </Modal>
    );
}

function Aprovar({ pedido, d, aoFechar, aoDecidir }: { pedido: PedidoDeLicenca; d: Dados; aoFechar: () => void; aoDecidir: (m: string) => void }) {
    const [f, porF] = useState({
        plano_id: String(d.opcoes.planos[0]?.id ?? ''),
        dias: 365,
        max_utilizadores: pedido.utilizadores ? String(pedido.utilizadores) : '',
        todos_os_modulos: true,
        modulos: [] as string[],
        prender_a_maquina: true,
    });
    const [motivo, porMotivo] = useState('');

    const aprovar = useMutation({
        mutationFn: () => sistema.licenciamento.aprovarPedido(pedido.id, { ...f, plano_id: Number(f.plano_id), max_utilizadores: f.max_utilizadores === '' ? null : Number(f.max_utilizadores) }),
        onSuccess: (r) => aoDecidir(r.message),
    });
    const recusar = useMutation({ mutationFn: () => sistema.licenciamento.recusarPedido(pedido.id, motivo), onSuccess: (r) => aoDecidir(r.message) });

    const erro = aprovar.error ?? recusar.error;
    const erros = erro instanceof ErroDaApi ? erro.erros : {};

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Aprovar pedido — :empresa', { empresa: pedido.empresa })}
            subtitulo={pedido.codigo}
            icone="fa-gavel"
            cor="primaria"
            largura="lg"
            rodape={
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Botao cor="perigo" icone="fa-ban" aTrabalhar={recusar.isPending} disabled={aprovar.isPending} onClick={() => recusar.mutate()}>{t('Recusar')}</Botao>
                    <div className="flex gap-2">
                        <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                        <Botao cor="bom" tom="solida" icone="fa-check" aTrabalhar={aprovar.isPending} disabled={recusar.isPending} onClick={() => aprovar.mutate()}>{t('Aprovar e emitir')}</Botao>
                    </div>
                </div>
            }
        >
            <div className="space-y-4">
                <div className={cls('bg-slate-50 p-4 text-sm text-slate-700', RAIO)}>
                    NIF {pedido.nif ?? '—'} · {pedido.email ?? '—'} · {pedido.telefone ?? '—'}<br />
                    {t('Máquina')}: <span className="font-mono text-xs">{pedido.maquina ?? '—'}</span>
                    {pedido.observacoes && <p className="mt-2 italic">“{pedido.observacoes}”</p>}
                </div>

                <AvisoDeErro erro={Object.keys(erros).length ? null : erro} />
                {erros.pedido && <p role="alert" className="text-sm font-semibold text-red-600">{erros.pedido[0]}</p>}

                <div className="grid gap-4 md:grid-cols-3">
                    <Campo etiqueta={t('Plano')} obrigatorio erro={erros.plano_id}>
                        <select className={entrada} value={f.plano_id} onChange={(e) => porF({ ...f, plano_id: e.target.value })}>
                            {d.opcoes.planos.map((p) => <option key={p.id} value={p.id}>{p.nome}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta={t('Validade (dias)')} obrigatorio erro={erros.dias}>
                        <input type="number" min={1} max={3650} className={entrada} value={f.dias} onChange={(e) => porF({ ...f, dias: Number(e.target.value) })} />
                    </Campo>
                    <Campo etiqueta={t('Máx. utilizadores')} erro={erros.max_utilizadores}>
                        <input type="number" min={1} max={500} className={entrada} placeholder={t('sem limite')} value={f.max_utilizadores} onChange={(e) => porF({ ...f, max_utilizadores: e.target.value })} />
                    </Campo>
                </div>

                <Modulos modulos={d.opcoes.modulos} todos={f.todos_os_modulos} escolhidos={f.modulos}
                    aoMudarTodos={(v) => porF({ ...f, todos_os_modulos: v })} aoMudar={(v) => porF({ ...f, modulos: v })} />
                {erros.modulos && <p role="alert" className="text-xs font-medium text-red-600">{erros.modulos[0]}</p>}

                <Interruptor rotulo={t('Prender à máquina que fez o pedido')} nota={t('A licença só vale na máquina que pediu.')} valor={f.prender_a_maquina} aoMudar={(v) => porF({ ...f, prender_a_maquina: v })} />

                <Campo etiqueta={t('Motivo da recusa')} erro={erros.motivo} ajuda={t('Só para recusar.')}>
                    <input className={entrada} value={motivo} onChange={(e) => porMotivo(e.target.value)} placeholder={t('Motivo da recusa...')} />
                </Campo>
            </div>
        </Modal>
    );
}
