import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type ConfiguracaoSms, type ModeloSms, definicoes } from '@/api/plataforma';
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
import { CARTAO, RAIO, cls, kz } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { ErroDoEcra, Interruptor, Paginas, Recado, SegredoGuardado } from './comum';

const URL_TELCO = 'https://www.telcosms.co.ao/api/v2/send_message';
const URL_D7 = 'https://api.d7networks.com/messages/v1/send';

/**
 * O SMS DA PLATAFORMA — o fornecedor, os modelos e o histórico de envios.
 *
 * As chaves nunca voltam ao ecrã: diz-se só se estão guardadas. E o teste usa o
 * que está no formulário, gravando-o primeiro — escolher a TelcoSMS e testar
 * sem carregar em «Guardar» ainda mandava pela D7.
 */
export default function Sms() {
    const fila = useQueryClient();
    const [aba, porAba] = useState('configuracao');
    const [recado, porRecado] = useState<{ texto: string; aviso?: boolean } | null>(null);
    const [f, porF] = useState<(ConfiguracaoSms & { api_token: string; telco_api_key_qas: string }) | null>(null);
    const [aTestar, porATestar] = useState(false);
    const [aEditar, porAEditar] = useState<ModeloSms | null>(null);

    const dados = useQuery({ queryKey: ['plataforma', 'sms'], queryFn: definicoes.sms.ler });

    useEffect(() => {
        if (dados.data) porF({ ...dados.data.configuracao, api_token: '', telco_api_key_qas: '' });
    }, [dados.data]);

    const feito = (texto: string, aviso = false) => {
        porRecado({ texto, aviso });
        void fila.invalidateQueries({ queryKey: ['plataforma', 'sms'] });
    };

    const guardar = useMutation({ mutationFn: () => definicoes.sms.guardar({ ...f }), onSuccess: (r) => feito(r.message) });
    const saldo = useMutation({ mutationFn: () => definicoes.sms.saldo({ telco_application: f?.telco_application }), onSuccess: (r) => porRecado({ texto: r.message, aviso: r.aviso }) });

    if (dados.isPending || !f) return <Carregando linhas={8} />;
    if (dados.isError) return <ErroDoEcra titulo={t('Não foi possível abrir o SMS')} erro={dados.error} />;

    const d = dados.data;
    const telco = f.provider === 'telcosms';
    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};

    const mudarFornecedor = (p: string) => porF((a) => a && ({
        ...a,
        provider: p,
        api_url: p === 'telcosms' ? URL_TELCO : URL_D7,
        sender_id: p === 'telcosms' ? 'SOSERP' : (a.sender_id && a.sender_id !== 'SOSERP' ? a.sender_id : 'SOS ERP'),
        telco_application: p === 'telcosms' ? 'soserp_prd' : a.telco_application,
        report_url: p === 'telcosms' ? '' : a.report_url,
    }));

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('SMS')}
                subtitulo={t('O fornecedor, os modelos de mensagem e o histórico de envios')}
                icone="fa-comment-sms"
                cor="bom"
                accoes={
                    <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porATestar(true)}>
                        <i className="fas fa-paper-plane" aria-hidden="true" />{t('Enviar SMS de teste')}
                    </button>
                }
            >
                <EstadoNaFaixa icone="fa-tower-broadcast">
                    {t('Fornecedor: :f', { f: telco ? 'TelcoSMS Angola' : 'D7 Networks' })}
                </EstadoNaFaixa>
            </Faixa>

            <Recado texto={recado?.texto ?? null} aviso={recado?.aviso} aoFechar={() => porRecado(null)} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <CartaoNumero rotulo={t('Total')} valor={kz(d.numeros.total, 0)} icone="fa-comment-sms" tom="azul" aspecto="claro" />
                <CartaoNumero rotulo={t('Enviados')} valor={kz(d.numeros.enviados, 0)} icone="fa-circle-check" tom="verde" aspecto="claro" />
                <CartaoNumero rotulo={t('Falhados')} valor={kz(d.numeros.falhados, 0)} icone="fa-circle-xmark" tom={d.numeros.falhados > 0 ? 'vermelho' : 'cinza'} aspecto="claro" />
                <CartaoNumero rotulo={t('Hoje')} valor={kz(d.numeros.hoje, 0)} icone="fa-calendar-day" tom="roxo" aspecto="claro" />
            </div>

            <div className={cls(CARTAO, 'p-4')}>
                <Separadores
                    abas={[
                        { chave: 'configuracao', rotulo: t('Configuração'), icone: 'fa-gear' },
                        { chave: 'modelos', rotulo: t('Modelos (:n)', { n: d.modelos.length }), icone: 'fa-file-lines' },
                        { chave: 'historico', rotulo: t('Histórico'), icone: 'fa-clock-rotate-left' },
                    ]}
                    activa={aba}
                    aoMudar={porAba}
                />

                <div className="pt-4">
                    <PainelDoSeparador chave="configuracao" activa={aba}>
                        <div className="space-y-4">
                            <AvisoDeErro erro={guardar.error} />

                            <div className={cls('border px-4 py-3 text-sm', RAIO, telco ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-blue-200 bg-blue-50 text-blue-900')}>
                                <p className="font-bold"><i className="fas fa-circle-check mr-2" aria-hidden="true" />{t('Gateway padrão: :g', { g: telco ? 'TelcoSMS Angola' : 'D7 Networks' })}</p>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <Campo etiqueta={t('Fornecedor')} obrigatorio erro={erros.provider}>
                                    <select className={entrada} value={f.provider} onChange={(e) => mudarFornecedor(e.target.value)}>
                                        <option value="d7networks">D7 Networks</option>
                                        <option value="telcosms">TelcoSMS Angola</option>
                                    </select>
                                </Campo>
                                <Campo etiqueta={t('Endereço da API')} obrigatorio erro={erros.api_url}>
                                    <input className={cls(entrada, 'font-mono text-xs')} value={f.api_url} onChange={(e) => porF({ ...f, api_url: e.target.value })} />
                                </Campo>

                                {telco && (
                                    <Campo etiqueta={t('Aplicação TelcoSMS')} obrigatorio erro={erros.telco_application}>
                                        <select className={entrada} value={f.telco_application} onChange={(e) => porF({ ...f, telco_application: e.target.value })}>
                                            <option value="soserp_prd">{t('soserp_prd — produção')}</option>
                                            <option value="soserp_qas">{t('soserp_qas — testes')}</option>
                                        </select>
                                    </Campo>
                                )}

                                <Campo
                                    etiqueta={telco ? t('Chave api_key_app (produção)') : t('Token da D7')}
                                    erro={erros.api_token}
                                    ajuda={<SegredoGuardado guardado={d.configuracao.token_guardado} />}
                                >
                                    <input type="password" autoComplete="new-password" className={entrada} value={f.api_token} onChange={(e) => porF({ ...f, api_token: e.target.value })} />
                                </Campo>

                                {telco && f.telco_application === 'soserp_qas' && (
                                    <Campo etiqueta={t('Chave QAS (testes)')} obrigatorio={!d.configuracao.chave_qas_guardada} erro={erros.telco_api_key_qas} ajuda={<SegredoGuardado guardado={d.configuracao.chave_qas_guardada} />}>
                                        <input type="password" autoComplete="new-password" className={entrada} value={f.telco_api_key_qas} onChange={(e) => porF({ ...f, telco_api_key_qas: e.target.value })} />
                                    </Campo>
                                )}

                                {telco ? (
                                    <p className={cls('border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 md:col-span-2', RAIO)}>
                                        {t('Na TelcoSMS o remetente é definido pela aplicação associada à chave (SOSERP): não se envia como campo separado, e o relatório de entrega não se configura aqui.')}
                                    </p>
                                ) : (
                                    <>
                                        <Campo etiqueta={t('Remetente')} erro={erros.sender_id} ajuda={t('Até 11 caracteres.')}>
                                            <input className={entrada} maxLength={11} value={f.sender_id} onChange={(e) => porF({ ...f, sender_id: e.target.value })} />
                                        </Campo>
                                        <Campo etiqueta={t('Endereço do relatório de entrega')} erro={erros.report_url}>
                                            <input className={entrada} value={f.report_url} onChange={(e) => porF({ ...f, report_url: e.target.value })} />
                                        </Campo>
                                    </>
                                )}
                            </div>

                            <Interruptor rotulo={t('SMS activo')} nota={t('Desligado, nenhum SMS da plataforma sai.')} valor={f.is_active} aoMudar={(v) => porF({ ...f, is_active: v })} />

                            <div className="flex flex-wrap justify-end gap-2">
                                {telco && (
                                    <Botao cor="bom" tom="suave" icone="fa-wallet" aTrabalhar={saldo.isPending} onClick={() => saldo.mutate()}>{t('Consultar saldo')}</Botao>
                                )}
                                <Botao cor="bom" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>{t('Guardar')}</Botao>
                            </div>
                            <AvisoDeErro erro={saldo.error} />
                        </div>
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="modelos" activa={aba}>
                        {d.modelos.length === 0 ? <SemNada icone="fa-file-lines" frase={t('Não há modelos de SMS.')} /> : (
                            <div className="grid gap-3 md:grid-cols-2">
                                {d.modelos.map((m, i) => (
                                    <article key={m.id} className={cls(CARTAO, 'card-hover cascata p-4', !m.activo && 'opacity-70')} style={cascata(i)}>
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <h4 className="truncate font-bold text-slate-900">{m.nome}</h4>
                                                <code className="text-xs text-slate-500">{m.slug}</code>
                                            </div>
                                            <Etiqueta cor={m.activo ? 'bom' : 'neutra'} ponto>{m.activo ? t('Activo') : t('Desligado')}</Etiqueta>
                                        </div>
                                        {m.descricao && <p className="mt-2 text-xs text-slate-500">{m.descricao}</p>}
                                        <p className="mt-2 whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{m.conteudo}</p>
                                        <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex flex-wrap gap-1">
                                                {m.variaveis.map((v) => (
                                                    <span key={v.nome} title={v.descricao ?? undefined} className="rounded bg-indigo-50 px-1.5 py-0.5 font-mono text-[10px] text-indigo-700">{`{${v.nome}}`}</span>
                                                ))}
                                            </div>
                                            <span className={cls('text-xs', m.caracteres > 160 ? 'font-semibold text-amber-700' : 'text-slate-400')}>
                                                {t(':n caracteres', { n: m.caracteres })}
                                            </span>
                                        </div>
                                        <div className="mt-3 flex justify-end">
                                            <Botao cor="primaria" tom="suave" altura="pequeno" icone="fa-pen" onClick={() => porAEditar(m)}>{t('Editar')}</Botao>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </PainelDoSeparador>

                    <PainelDoSeparador chave="historico" activa={aba}>
                        {aba === 'historico' && <Historico tipos={d.tipos} />}
                    </PainelDoSeparador>
                </div>
            </div>

            {aTestar && <Teste formulario={f} modelos={d.modelos} aoFechar={() => porATestar(false)} aoEnviar={(m) => { porATestar(false); feito(m); }} />}
            {aEditar && <EditarModelo modelo={aEditar} aoFechar={() => porAEditar(null)} aoGuardar={(m) => { porAEditar(null); feito(m); }} />}
        </div>
    );
}

function Teste({ formulario, modelos, aoFechar, aoEnviar }: {
    formulario: Record<string, unknown>;
    modelos: ModeloSms[];
    aoFechar: () => void;
    aoEnviar: (m: string) => void;
}) {
    const inicial = modelos.find((m) => m.slug === 'test' && m.activo);
    const [modelo, porModelo] = useState<string>(inicial ? String(inicial.id) : '');
    const [telefone, porTelefone] = useState('');
    const [mensagem, porMensagem] = useState('');

    const previa = useQuery({
        queryKey: ['plataforma', 'sms', 'previa', modelo],
        queryFn: () => definicoes.sms.previsualizar(Number(modelo)),
        enabled: modelo !== '',
    });

    useEffect(() => {
        if (previa.data) porMensagem(previa.data.mensagem);
    }, [previa.data]);

    const enviar = useMutation({
        mutationFn: () => definicoes.sms.testar({ ...formulario, test_phone: telefone, test_message: mensagem }),
        onSuccess: (r) => aoEnviar(r.message),
    });
    const erros = enviar.error instanceof ErroDaApi ? enviar.error.erros : {};

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Enviar SMS de teste')}
            subtitulo={t('Grava a configuração do formulário e envia por ela')}
            icone="fa-paper-plane"
            cor="bom"
            largura="md"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-paper-plane" aTrabalhar={enviar.isPending} onClick={() => enviar.mutate()}>{t('Enviar')}</Botao>
                </div>
            }
        >
            <div className="space-y-4">
                <AvisoDeErro erro={enviar.error} />
                <Campo etiqueta={t('Modelo')}>
                    <select className={entrada} value={modelo} onChange={(e) => porModelo(e.target.value)}>
                        <option value="">{t('Escrever à mão')}</option>
                        {modelos.filter((m) => m.activo).map((m) => <option key={m.id} value={m.id}>{m.nome}</option>)}
                    </select>
                </Campo>
                <Campo etiqueta={t('Telefone')} obrigatorio erro={erros.test_phone}>
                    <input className={entrada} value={telefone} placeholder="+244 9XX XXX XXX" onChange={(e) => porTelefone(e.target.value)} />
                </Campo>
                <Campo etiqueta={t('Mensagem')} obrigatorio erro={erros.test_message} ajuda={t(':n caracteres', { n: mensagem.length })}>
                    <textarea rows={4} className={cls(entrada, 'h-auto py-2')} value={mensagem} onChange={(e) => porMensagem(e.target.value)} />
                </Campo>
            </div>
        </Modal>
    );
}

function EditarModelo({ modelo, aoFechar, aoGuardar }: { modelo: ModeloSms; aoFechar: () => void; aoGuardar: (m: string) => void }) {
    const [f, porF] = useState({ name: modelo.nome, content: modelo.conteudo, description: modelo.descricao ?? '', is_active: modelo.activo });
    const guardar = useMutation({ mutationFn: () => definicoes.sms.guardarModelo(modelo.id, f), onSuccess: (r) => aoGuardar(r.message) });
    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Editar modelo de SMS')}
            subtitulo={modelo.slug}
            icone="fa-file-lines"
            cor="primaria"
            largura="lg"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>{t('Guardar')}</Botao>
                </div>
            }
        >
            <div className="space-y-4">
                <AvisoDeErro erro={guardar.error} />
                <Campo etiqueta={t('Identificador')} ajuda={t('Não se muda: é por ele que o sistema pede o modelo.')}>
                    <input className={cls(entrada, 'bg-slate-50 font-mono')} value={modelo.slug} readOnly />
                </Campo>
                <Campo etiqueta={t('Nome')} obrigatorio erro={erros.name}>
                    <input className={entrada} value={f.name} onChange={(e) => porF({ ...f, name: e.target.value })} />
                </Campo>
                <Campo etiqueta={t('Conteúdo')} obrigatorio erro={erros.content} ajuda={t(':n caracteres — acima de 160 vai em mais do que uma mensagem.', { n: f.content.length })}>
                    <textarea rows={5} className={cls(entrada, 'h-auto py-2')} value={f.content} onChange={(e) => porF({ ...f, content: e.target.value })} />
                </Campo>
                {modelo.variaveis.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                        {modelo.variaveis.map((v) => (
                            <button key={v.nome} type="button" title={v.descricao ?? undefined} onClick={() => porF({ ...f, content: `${f.content}{${v.nome}}` })}
                                className="rounded bg-indigo-50 px-2 py-1 font-mono text-xs text-indigo-700 hover:bg-indigo-100">
                                {`{${v.nome}}`}
                            </button>
                        ))}
                    </div>
                )}
                <Campo etiqueta={t('Descrição')} erro={erros.description}>
                    <input className={entrada} value={f.description} onChange={(e) => porF({ ...f, description: e.target.value })} />
                </Campo>
                <Interruptor rotulo={t('Activo')} valor={f.is_active} aoMudar={(v) => porF({ ...f, is_active: v })} />
            </div>
        </Modal>
    );
}

function Historico({ tipos }: { tipos: string[] }) {
    const [f, porF] = useState<Record<string, string | number | undefined>>({ pagina: 1 });

    const lista = useQuery({
        queryKey: ['plataforma', 'sms', 'historico', f],
        queryFn: () => definicoes.sms.historico(f),
        placeholderData: keepPreviousData,
    });

    const mexer = (k: string, v: string) => porF((a) => ({ ...a, [k]: v || undefined, pagina: 1 }));
    const temFiltros = Object.entries(f).some(([k, v]) => k !== 'pagina' && v);

    return (
        <div className="space-y-3">
            <div className="grid gap-3 md:grid-cols-6">
                <label className="block md:col-span-2"><Rotulo>{t('Procurar')}</Rotulo>
                    <input type="search" className={entrada} placeholder={t('Número ou texto…')} value={String(f.procura ?? '')} onChange={(e) => mexer('procura', e.target.value)} />
                </label>
                <label className="block"><Rotulo>{t('Gateway')}</Rotulo>
                    <select className={entrada} value={String(f.gateway ?? '')} onChange={(e) => mexer('gateway', e.target.value)}>
                        <option value="">{t('Todos')}</option><option value="telcosms">TelcoSMS</option><option value="d7networks">D7 Networks</option>
                    </select>
                </label>
                <label className="block"><Rotulo>{t('Estado')}</Rotulo>
                    <select className={entrada} value={String(f.estado ?? '')} onChange={(e) => mexer('estado', e.target.value)}>
                        <option value="">{t('Todos')}</option><option value="sent">{t('Enviado')}</option><option value="failed">{t('Falhado')}</option><option value="pending">{t('Pendente')}</option>
                    </select>
                </label>
                <label className="block"><Rotulo>{t('Tipo')}</Rotulo>
                    <select className={entrada} value={String(f.tipo ?? '')} onChange={(e) => mexer('tipo', e.target.value)}>
                        <option value="">{t('Todos')}</option>{tipos.map((x) => <option key={x} value={x}>{x}</option>)}
                    </select>
                </label>
                <div className="grid grid-cols-2 gap-2">
                    <label className="block"><Rotulo>{t('De')}</Rotulo><input type="date" className={entrada} value={String(f.de ?? '')} onChange={(e) => mexer('de', e.target.value)} /></label>
                    <label className="block"><Rotulo>{t('Até')}</Rotulo><input type="date" className={entrada} value={String(f.ate ?? '')} onChange={(e) => mexer('ate', e.target.value)} /></label>
                </div>
            </div>
            {temFiltros && (
                <div className="flex justify-end"><Botao cor="neutra" altura="pequeno" icone="fa-rotate-left" onClick={() => porF({ pagina: 1 })}>{t('Limpar filtros')}</Botao></div>
            )}

            {lista.isPending ? <Carregando linhas={5} /> : !lista.data || lista.data.registos.length === 0 ? (
                <SemNada icone="fa-clock-rotate-left" frase={t('Nenhum SMS com estes filtros.')} />
            ) : (
                <>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50/70 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                    <th className="px-3 py-2">{t('Data')}</th><th className="px-3 py-2">{t('Destino')}</th><th className="px-3 py-2">{t('Mensagem')}</th>
                                    <th className="px-3 py-2">{t('Gateway')}</th><th className="px-3 py-2">{t('Tipo')}</th><th className="px-3 py-2">{t('Estado')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {lista.data.registos.map((r, i) => (
                                    <tr key={r.id} className="entra hover:bg-slate-50" style={cascata(i)}>
                                        <td className="whitespace-nowrap px-3 py-2 text-xs text-slate-500">{r.enviado_em}</td>
                                        <td className="whitespace-nowrap px-3 py-2 font-mono text-xs">{r.destino}</td>
                                        <td className="max-w-md px-3 py-2">
                                            <p className="line-clamp-2 text-xs text-slate-700" title={r.mensagem}>{r.mensagem}</p>
                                            {r.erro && <p className="mt-0.5 text-[11px] text-red-600">{r.erro}</p>}
                                            {(r.empresa || r.quem) && <p className="mt-0.5 text-[10px] text-slate-400">{[r.empresa, r.quem].filter(Boolean).join(' · ')}</p>}
                                        </td>
                                        <td className="px-3 py-2 text-xs">{r.gateway === 'telcosms' ? 'TelcoSMS' : 'D7'}</td>
                                        <td className="px-3 py-2 text-xs text-slate-600">{r.tipo ?? '—'}</td>
                                        <td className="px-3 py-2">
                                            <Etiqueta cor={r.estado === 'sent' || r.estado === 'delivered' ? 'bom' : r.estado === 'failed' ? 'perigo' : 'aviso'} ponto>
                                                {r.estado === 'sent' ? t('Enviado') : r.estado === 'failed' ? t('Falhado') : r.estado === 'delivered' ? t('Entregue') : t('Pendente')}
                                            </Etiqueta>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Paginas pagina={lista.data.paginacao.pagina} ultima={lista.data.paginacao.ultima} aMudar={(p) => porF((a) => ({ ...a, pagina: p }))} />
                </>
            )}
        </div>
    );
}
