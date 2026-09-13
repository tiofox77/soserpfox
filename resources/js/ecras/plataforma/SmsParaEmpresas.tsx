import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { ferramentas } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, Rotulo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { CARTAO, RAIO, RAIO_GRANDE, TRANSICAO, cls } from '@/ui/tokens';
import { useRecadoNoCanto } from '@/ui/useRecadoNoCanto';

import { EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { ErroDoEcra, Recado } from './comum';

type Publico = 'todas' | 'empresas' | 'planos';

/**
 * EM QUANTAS PARTES O SMS VAI — a mesma conta do servidor (`PartesDeSms`),
 * para se ver enquanto se escreve. Um acento leva a mensagem para UCS-2 e cada
 * parte encolhe de 160 para 70 caracteres.
 */
export function partesDeSms(texto: string): number {
    const limpo = texto.trim();
    const tamanho = [...limpo].length;

    if (tamanho === 0) return 0;

    const unicode = new TextEncoder().encode(limpo).length !== tamanho;
    const [uma, varias] = unicode ? [70, 67] : [160, 153];

    return tamanho <= uma ? 1 : Math.ceil(tamanho / varias);
}

/**
 * ENVIAR UM SMS ÀS EMPRESAS — a todas, ou só às escolhidas.
 *
 * O SMS custa por mensagem e um engano multiplica-se por todas as empresas:
 * primeiro revê-se (a quem vai, quem fica de fora por não ter telefone, quantas
 * partes custa), depois confirma-se. Mudar o que quer que seja depois de rever
 * desfaz a revisão — a confirmação é sobre AQUELES destinatários e AQUELE texto.
 */
export default function SmsParaEmpresas() {
    const [mensagem, porMensagem] = useState('');
    const [modelo, porModelo] = useState('');
    const [publico, porPublico] = useState<Publico>('todas');
    const [empresaIds, porEmpresaIds] = useState<number[]>([]);
    const [planoIds, porPlanoIds] = useState<number[]>([]);
    const [procura, porProcura] = useState('');
    const [recado, porRecado] = useRecadoNoCanto(null);

    const dados = useQuery({ queryKey: ['plataforma', 'sms-empresas'], queryFn: ferramentas.smsEmpresas.ler });

    const pedido = () => ({ mensagem, publico, empresa_ids: empresaIds, plano_ids: planoIds });

    const rever = useMutation({ mutationFn: () => ferramentas.smsEmpresas.rever(pedido()) });
    const enviar = useMutation({
        mutationFn: () => ferramentas.smsEmpresas.enviar({ ...pedido(), assinatura: rever.data?.assinatura }),
        onSuccess: (r) => { porRecado(r.message); porMensagem(''); porModelo(''); rever.reset(); },
    });

    // Qualquer mudança depois de rever desfaz a revisão.
    const mudou = <T,>(porValor: (v: T) => void) => (v: T) => { porValor(v); rever.reset(); enviar.reset(); };

    if (dados.isPending) return <Carregando linhas={8} />;
    if (dados.isError) return <ErroDoEcra titulo={t('Não foi possível abrir o SMS às empresas')} erro={dados.error} />;

    const d = dados.data;
    const partes = partesDeSms(mensagem);
    const erro = rever.error ?? enviar.error;
    const erros = erro instanceof ErroDaApi ? erro.erros : {};
    const escolhidas = publico === 'empresas' ? d.empresas.filter((e) => empresaIds.includes(e.id)) : [];
    const empresasVisiveis = d.empresas.filter((e) => e.nome.toLowerCase().includes(procura.trim().toLowerCase()));
    const resultado = enviar.data?.resultado;

    const alternar = (lista: number[], valor: number) => (lista.includes(valor) ? lista.filter((x) => x !== valor) : [...lista, valor]);

    return (
        <div className="space-y-4">
            <Faixa titulo={t('SMS às empresas')} subtitulo={t('Uma mensagem para todas as empresas, ou só para as escolhidas')} icone="fa-comment-sms" cor="bom">
                <EstadoNaFaixa icone={d.configurado ? 'fa-tower-broadcast' : 'fa-triangle-exclamation'}>
                    {d.configurado ? t('Fornecedor: :f', { f: d.gateway === 'telcosms' ? 'TelcoSMS Angola' : 'D7 Networks' }) : t('SMS não configurado')}
                </EstadoNaFaixa>
            </Faixa>

            {!d.configurado && (
                <div role="alert" className={cls('border border-amber-200 bg-amber-50 p-4 text-sm', RAIO)}>
                    <p className="font-bold text-amber-900"><i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />{t('SMS não configurado')}</p>
                    <p className="mt-1 text-amber-800">
                        {t('Não há configuração de SMS activa na plataforma. Configure-a antes de enviar.')}{' '}
                        <a href="/superadmin/sms-settings" className="font-semibold underline">{t('Abrir definições de SMS')}</a>
                    </p>
                </div>
            )}

            <Recado texto={recado} aoFechar={() => porRecado(null)} />

            {resultado && resultado.falhados.length > 0 && (
                <div role="alert" className={cls('border border-red-200 bg-red-50 p-4 text-sm text-red-800', RAIO)}>
                    <p className="font-bold">{t(':n parte(s) de SMS', { n: resultado.partes })}</p>
                    <p>{t('Falharam:')} {resultado.falhados.join(', ')}</p>
                </div>
            )}

            <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <section className={cls(CARTAO, 'card-hover space-y-4 p-5')}>
                    {d.modelos.length > 0 && (
                        <Campo etiqueta={t('Usar template existente')} ajuda={t('Enche a caixa de texto; o que sai é o que lá estiver escrito.')}>
                            <select className={entrada} value={modelo} onChange={(e) => {
                                const m = d.modelos.find((x) => String(x.id) === e.target.value);
                                porModelo(e.target.value);
                                if (m) mudou(porMensagem)(m.conteudo);
                            }}>
                                <option value="">{t('Mensagem manual')}</option>
                                {d.modelos.map((m) => <option key={m.id} value={m.id}>{m.nome}</option>)}
                            </select>
                        </Campo>
                    )}

                    <Campo etiqueta={t('Mensagem')} obrigatorio erro={erros.mensagem}>
                        <textarea rows={5} maxLength={640} className={entrada} value={mensagem} onChange={(e) => mudou(porMensagem)(e.target.value)}
                            placeholder={t('Escreva o que quer dizer às empresas...')} />
                    </Campo>

                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span className="text-slate-500">{t(':n caracteres', { n: [...mensagem.trim()].length })}</span>
                        <span className={cls('rounded-full px-2.5 py-1 font-semibold', partes > 1 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600')}>
                            <i className="fas fa-layer-group mr-1" aria-hidden="true" />{t(':n parte(s) por destinatário', { n: partes })}
                        </span>
                    </div>

                    <div className="flex flex-wrap gap-1.5 text-[11px]">
                        {d.variaveis.map((v) => (
                            <button key={v} type="button" onClick={() => mudou(porMensagem)(`${mensagem}{{${v}}}`)}
                                className={cls('rounded-md bg-emerald-50 px-2 py-0.5 font-mono text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100', TRANSICAO)}>
                                {`{{${v}}}`}
                            </button>
                        ))}
                    </div>

                    <fieldset>
                        <legend className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{t('Quem recebe')}</legend>
                        <div className="grid gap-2 sm:grid-cols-3">
                            {([['todas', t('Todas as empresas'), 'fa-globe'], ['empresas', t('Empresas escolhidas'), 'fa-building'], ['planos', t('Por plano')    , 'fa-layer-group']] as Array<[Publico, string, string]>).map(([valor, rotulo, icone]) => (
                                <label key={valor} className={cls('flex cursor-pointer items-center gap-2 border px-3 py-2.5 text-sm', RAIO, TRANSICAO,
                                    publico === valor ? 'border-emerald-400 bg-emerald-50 font-semibold text-emerald-900' : 'border-slate-200 hover:bg-slate-50')}>
                                    <input type="radio" name="publico" className="text-emerald-600" checked={publico === valor} onChange={() => mudou(porPublico)(valor)} />
                                    <i className={`fas ${icone} text-slate-400`} aria-hidden="true" />{rotulo}
                                </label>
                            ))}
                        </div>
                    </fieldset>

                    {publico === 'empresas' && (
                        <div>
                            <Rotulo>{t('Empresas')} ({empresaIds.length})</Rotulo>
                            <input type="search" className={cls(entrada, 'mb-2')} placeholder={t('Procurar empresa…')} value={procura} onChange={(e) => porProcura(e.target.value)} />
                            <div className={cls('max-h-64 space-y-1 overflow-y-auto border border-slate-200 p-2', RAIO)}>
                                {empresasVisiveis.map((e) => (
                                    <label key={e.id} className={cls('flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50', empresaIds.includes(e.id) && 'bg-emerald-50')}>
                                        <input type="checkbox" className="rounded text-emerald-600" checked={empresaIds.includes(e.id)} onChange={() => mudou(porEmpresaIds)(alternar(empresaIds, e.id))} />
                                        <span className="min-w-0 flex-1 truncate text-sm text-slate-800">{e.nome}</span>
                                        {e.telefone
                                            ? <span className="shrink-0 text-xs text-slate-400">{e.telefone}</span>
                                            : <span className="shrink-0 text-xs font-semibold text-amber-600">{t('sem telefone')}</span>}
                                    </label>
                                ))}
                            </div>
                            {erros.empresa_ids && <p role="alert" className="mt-1 text-xs font-medium text-red-600">{erros.empresa_ids[0]}</p>}
                        </div>
                    )}

                    {publico === 'planos' && (
                        <div>
                            <Rotulo>{t('Planos')}</Rotulo>
                            <div className="flex flex-wrap gap-2">
                                {d.planos.map((p) => (
                                    <label key={p.id} className={cls('flex cursor-pointer items-center gap-2 border px-3 py-2 text-sm', RAIO, TRANSICAO,
                                        planoIds.includes(p.id) ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200 hover:bg-slate-50')}>
                                        <input type="checkbox" className="rounded text-emerald-600" checked={planoIds.includes(p.id)} onChange={() => mudou(porPlanoIds)(alternar(planoIds, p.id))} />
                                        <span className="font-semibold text-slate-700">{p.nome}</span>
                                    </label>
                                ))}
                            </div>
                            {erros.plano_ids && <p role="alert" className="mt-1 text-xs font-medium text-red-600">{erros.plano_ids[0]}</p>}
                        </div>
                    )}

                    <AvisoDeErro erro={Object.keys(erros).length ? null : erro} />
                    {erros.assinatura && <p role="alert" className="text-sm font-semibold text-red-600">{erros.assinatura[0]}</p>}
                </section>

                <aside className="space-y-4">
                    <section className={cls('overflow-hidden border border-slate-200 bg-white shadow-sm', RAIO_GRANDE)}>
                        <header className="bg-gradient-to-r from-emerald-500 to-teal-600 px-5 py-3 font-bold text-white">
                            <i className="fas fa-clipboard-check icon-float mr-2" aria-hidden="true" />{t('Antes de enviar')}
                        </header>
                        <div className="space-y-3 p-5">
                            {publico === 'empresas' && <p className="text-sm text-slate-600">{t('Empresas escolhidas')}: <b>{escolhidas.length}</b> · {t('Com telefone')}: <b>{escolhidas.filter((e) => e.telefone).length}</b></p>}

                            {!rever.data ? (
                                <Botao className="w-full" cor="bom" tom="solida" icone="fa-eye" disabled={!d.configurado} aTrabalhar={rever.isPending} onClick={() => rever.mutate()}>
                                    {t('Rever antes de enviar')}
                                </Botao>
                            ) : (
                                <div className="entra space-y-3">
                                    <dl className="grid grid-cols-2 gap-2 text-center">
                                        <div className="rounded-xl bg-slate-50 p-3"><dt className="text-xs text-slate-500">{t('Empresas escolhidas')}</dt><dd className="text-2xl font-black tabular-nums">{rever.data.alvo}</dd></div>
                                        <div className="rounded-xl bg-emerald-50 p-3"><dt className="text-xs text-emerald-700">{t('Com telefone')}</dt><dd className="text-2xl font-black tabular-nums text-emerald-700">{rever.data.com_telefone}</dd></div>
                                    </dl>
                                    {rever.data.sem_telefone.length > 0 && (
                                        <p className="rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                                            {t(':n não recebem por não terem telefone registado.', { n: rever.data.sem_telefone.length })}
                                            <span className="mt-1 block font-semibold">{rever.data.sem_telefone.join(', ')}</span>
                                        </p>
                                    )}
                                    <div className={cls('border border-amber-300 bg-amber-50 p-3', RAIO)}>
                                        <p className="font-bold text-amber-900">
                                            {t('Enviar a :empresas empresa(s), num total de :partes parte(s) de SMS?', { empresas: rever.data.com_telefone, partes: rever.data.total_de_partes })}
                                        </p>
                                        <p className="mt-1 text-sm text-amber-800">{t('Isto não se pode desfazer.')}</p>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        <Botao cor="neutra" onClick={() => rever.reset()} disabled={enviar.isPending}>{t('Cancelar')}</Botao>
                                        <Botao cor="bom" tom="solida" icone="fa-paper-plane" aTrabalhar={enviar.isPending} onClick={() => enviar.mutate()}>{t('Enviar agora')}</Botao>
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    );
}
