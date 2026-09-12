import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { ErroDaApi } from '@/api/cliente';
import { type FichaDoCorreio, type ServidorDeCorreio, definicoes } from '@/api/plataforma';
import { t } from '@/i18n';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { Modal } from '@/ui/Modal';
import { SemNada, cascata } from '@/ui/SemNada';
import { CARTAO, RAIO, cls } from '@/ui/tokens';

import { ACCAO_DA_FAIXA, EstadoNaFaixa, Faixa } from '../facturacao/faixa';
import { ErroDoEcra, Interruptor, Recado, SegredoGuardado } from './comum';

/**
 * OS SERVIDORES DE CORREIO — o da plataforma e os de cada empresa.
 *
 * O QUE MELHOROU: editar já não obriga a reescrever a palavra-passe; marcar
 * «padrão» tira a marca à outra do mesmo âmbito (e só dela); e a única
 * configuração activa da plataforma não se apaga sem haver outra — era ela que
 * mandava os avisos de suspensão e as credenciais das contas novas.
 */
export default function Correio() {
    const fila = useQueryClient();
    const [recado, porRecado] = useState<{ texto: string; aviso?: boolean } | null>(null);
    const [aEditar, porAEditar] = useState<number | 'novo' | null>(null);
    const [aTestar, porATestar] = useState<ServidorDeCorreio | null>(null);
    const [aApagar, porAApagar] = useState<ServidorDeCorreio | null>(null);

    const dados = useQuery({ queryKey: ['plataforma', 'correio'], queryFn: definicoes.correio.ler });

    const feito = (texto: string, aviso = false) => {
        porRecado({ texto, aviso });
        void fila.invalidateQueries({ queryKey: ['plataforma', 'correio'] });
    };

    const alternar = useMutation({ mutationFn: definicoes.correio.alternar, onSuccess: (r) => feito(r.message) });
    const padrao = useMutation({ mutationFn: definicoes.correio.padrao, onSuccess: (r) => feito(r.message) });
    const testar = useMutation({
        mutationFn: definicoes.correio.testar,
        onSuccess: (r) => feito(r.message),
        onError: (e) => feito(e instanceof ErroDaApi ? e.message : t('A ligação falhou.'), true),
    });
    const apagar = useMutation({ mutationFn: definicoes.correio.apagar, onSuccess: (r) => { porAApagar(null); feito(r.message); } });

    if (dados.isPending) return <Carregando linhas={8} />;
    if (dados.isError) return <ErroDoEcra titulo={t('Não foi possível abrir os servidores de correio')} erro={dados.error} />;

    const d = dados.data;

    return (
        <div className="space-y-4">
            <Faixa
                titulo={t('Servidores de correio')}
                subtitulo={t('Por onde saem os emails da plataforma e das empresas')}
                icone="fa-envelope-open-text"
                cor="primaria"
                accoes={
                    <button type="button" className={ACCAO_DA_FAIXA} onClick={() => porAEditar('novo')}>
                        <i className="fas fa-plus" aria-hidden="true" />{t('Novo servidor')}
                    </button>
                }
            >
                <EstadoNaFaixa icone={d.plataforma_tem_correio ? 'fa-circle-check' : 'fa-triangle-exclamation'}>
                    {d.plataforma_tem_correio ? t('A plataforma tem correio activo') : t('A plataforma não tem correio padrão activo')}
                </EstadoNaFaixa>
            </Faixa>

            <Recado texto={recado?.texto ?? null} aviso={recado?.aviso} aoFechar={() => porRecado(null)} />
            <AvisoDeErro erro={alternar.error ?? padrao.error ?? apagar.error} />

            {!d.plataforma_tem_correio && (
                <div className={cls('border-2 border-amber-300 bg-amber-50 p-4 text-sm text-amber-900', RAIO)}>
                    <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                    {t('Sem um servidor padrão activo da plataforma, os avisos de suspensão, de reactivação e as credenciais de contas novas não saem.')}
                </div>
            )}

            {d.configuracoes.length === 0 ? (
                <SemNada icone="fa-envelope" titulo={t('Ainda não há servidores de correio')} frase={t('Configure o servidor da plataforma para os emails começarem a sair.')} />
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    {d.configuracoes.map((s, i) => (
                        <article key={s.id} className={cls(CARTAO, 'card-hover cascata flex flex-col', !s.activa && 'opacity-75')} style={cascata(i)}>
                            <header className="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                                <div className="flex min-w-0 items-center gap-3">
                                    <span className={cls('grid h-11 w-11 shrink-0 place-items-center rounded-xl text-white', s.padrao ? 'bg-gradient-to-br from-indigo-500 to-blue-600' : 'bg-slate-400')}>
                                        <i className="fas fa-server" aria-hidden="true" />
                                    </span>
                                    <div className="min-w-0">
                                        <h3 className="truncate font-bold text-slate-900">{s.host}:{s.porta}</h3>
                                        <p className="truncate text-xs text-slate-500">{s.empresa ?? t('Plataforma')}</p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap justify-end gap-1.5">
                                    {s.padrao && <Etiqueta cor="primaria" icone="fa-star">{t('Padrão')}</Etiqueta>}
                                    <Etiqueta cor={s.activa ? 'bom' : 'neutra'} ponto>{s.activa ? t('Activo') : t('Desligado')}</Etiqueta>
                                </div>
                            </header>
                            <dl className="grid flex-1 grid-cols-2 gap-3 px-5 py-4 text-sm">
                                <div><dt className="text-xs text-slate-500">{t('Remetente')}</dt><dd className="truncate font-medium text-slate-800">{s.nome_do_remetente} &lt;{s.remetente}&gt;</dd></div>
                                <div><dt className="text-xs text-slate-500">{t('Utilizador')}</dt><dd className="truncate font-medium text-slate-800">{s.utilizador}</dd></div>
                                <div><dt className="text-xs text-slate-500">{t('Encriptação')}</dt><dd className="font-medium uppercase text-slate-800">{s.encriptacao}</dd></div>
                                <div><dt className="text-xs text-slate-500">{t('Último teste')}</dt><dd className="font-medium text-slate-800">{s.testada_em ?? '—'}</dd></div>
                                <div className="col-span-2 flex gap-3 text-xs">
                                    <span className="text-emerald-700"><i className="fas fa-paper-plane mr-1" aria-hidden="true" />{t(':n enviado(s)', { n: s.enviados })}</span>
                                    {s.falhados > 0 && <span className="text-red-700"><i className="fas fa-circle-xmark mr-1" aria-hidden="true" />{t(':n falhado(s)', { n: s.falhados })}</span>}
                                </div>
                            </dl>
                            <footer className="flex flex-wrap gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3">
                                <Botao cor="primaria" tom="suave" altura="pequeno" icone="fa-plug" aTrabalhar={testar.isPending && testar.variables === s.id} onClick={() => testar.mutate(s.id)}>{t('Testar ligação')}</Botao>
                                <Botao cor="bom" tom="suave" altura="pequeno" icone="fa-paper-plane" onClick={() => porATestar(s)}>{t('Enviar teste')}</Botao>
                                <Botao cor="neutra" tom="suave" altura="pequeno" icone="fa-pen" onClick={() => porAEditar(s.id)}>{t('Editar')}</Botao>
                                {!s.padrao && <Botao cor="primaria" tom="suave" altura="pequeno" icone="fa-star" onClick={() => padrao.mutate(s.id)}>{t('Tornar padrão')}</Botao>}
                                <Botao cor={s.activa ? 'aviso' : 'bom'} tom="suave" altura="pequeno" icone={s.activa ? 'fa-pause' : 'fa-play'} onClick={() => alternar.mutate(s.id)}>{s.activa ? t('Desligar') : t('Ligar')}</Botao>
                                <Botao cor="perigo" tom="suave" altura="pequeno" icone="fa-trash" onClick={() => porAApagar(s)}><span className="sr-only">{t('Apagar')}</span></Botao>
                            </footer>
                        </article>
                    ))}
                </div>
            )}

            {aEditar !== null && (
                <Formulario id={aEditar === 'novo' ? null : aEditar} empresas={d.empresas} aoFechar={() => porAEditar(null)} aoGuardar={(m) => { porAEditar(null); feito(m); }} />
            )}

            {aTestar && <EnviarTeste servidor={aTestar} emailPorOmissao={d.o_meu_email ?? ''} aoFechar={() => porATestar(null)} aoEnviar={(m) => { porATestar(null); feito(m); }} />}

            <Modal
                aberto={aApagar !== null}
                aoFechar={() => porAApagar(null)}
                titulo={t('Apagar o servidor de correio?')}
                subtitulo={aApagar ? `${aApagar.host}:${aApagar.porta}` : undefined}
                icone="fa-trash"
                cor="perigo"
                largura="sm"
                rodape={
                    <div className="flex justify-end gap-2">
                        <Botao cor="neutra" onClick={() => porAApagar(null)}>{t('Deixar estar')}</Botao>
                        <Botao cor="perigo" tom="solida" icone="fa-trash" aTrabalhar={apagar.isPending} onClick={() => aApagar && apagar.mutate(aApagar.id)}>{t('Apagar')}</Botao>
                    </div>
                }
            >
                <p className="text-sm text-slate-700">{t('Os emails que usavam este servidor passam a usar o padrão da plataforma.')}</p>
            </Modal>
        </div>
    );
}

const VAZIO: FichaDoCorreio = {
    id: null, tenant_id: null, host: '', port: 587, username: '', password: '',
    encryption: 'tls', from_email: '', from_name: '', is_default: false, is_active: true,
};

function Formulario({ id, empresas, aoFechar, aoGuardar }: {
    id: number | null;
    empresas: Array<{ valor: string; rotulo: string }>;
    aoFechar: () => void;
    aoGuardar: (m: string) => void;
}) {
    const [f, porF] = useState<FichaDoCorreio>(VAZIO);
    const ficha = useQuery({ queryKey: ['plataforma', 'correio', 'ficha', id], queryFn: () => definicoes.correio.ficha(id!), enabled: id !== null });

    useEffect(() => { if (ficha.data) porF(ficha.data.ficha); }, [ficha.data]);

    const guardar = useMutation({ mutationFn: () => definicoes.correio.guardar(id, { ...f }), onSuccess: (r) => aoGuardar(r.message) });
    const erros = guardar.error instanceof ErroDaApi ? guardar.error.erros : {};
    const mexer = <K extends keyof FichaDoCorreio>(k: K, v: FichaDoCorreio[K]) => porF((a) => ({ ...a, [k]: v }));

    // A porta mais comum de cada encriptação: quem troca TLS por SSL quase
    // sempre troca também 587 por 465, e esquecia-se.
    const mudarEncriptacao = (v: string) => porF((a) => ({
        ...a,
        encryption: v,
        port: a.port === 587 || a.port === 465 || a.port === 25 ? (v === 'ssl' ? 465 : v === 'tls' ? 587 : 25) : a.port,
    }));

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={id ? t('Editar servidor de correio') : t('Novo servidor de correio')}
            icone="fa-server"
            cor="primaria"
            largura="lg"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardar.isPending} onClick={() => guardar.mutate()}>{t('Guardar')}</Botao>
                </div>
            }
        >
            {id !== null && ficha.isPending ? <Carregando linhas={6} /> : (
                <div className="space-y-4">
                    <AvisoDeErro erro={guardar.error} />
                    <Campo etiqueta={t('Empresa')} erro={erros.tenant_id} ajuda={t('Vazio: é o servidor da plataforma.')}>
                        <select className={entrada} value={f.tenant_id ?? ''} onChange={(e) => mexer('tenant_id', e.target.value ? Number(e.target.value) : null)}>
                            <option value="">{t('Plataforma (todas as empresas sem servidor próprio)')}</option>
                            {empresas.map((o) => <option key={o.valor} value={o.valor}>{o.rotulo}</option>)}
                        </select>
                    </Campo>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Campo etiqueta={t('Servidor')} obrigatorio erro={erros.host} className="sm:col-span-2">
                            <input className={entrada} value={f.host} placeholder="smtp.exemplo.ao" onChange={(e) => mexer('host', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Encriptação')} obrigatorio erro={erros.encryption}>
                            <select className={entrada} value={f.encryption} onChange={(e) => mudarEncriptacao(e.target.value)}>
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="none">{t('Nenhuma')}</option>
                            </select>
                        </Campo>
                        <Campo etiqueta={t('Porta')} obrigatorio erro={erros.port}>
                            <input type="number" min="1" max="65535" className={entrada} value={f.port} onChange={(e) => mexer('port', Number(e.target.value))} />
                        </Campo>
                        <Campo etiqueta={t('Utilizador')} obrigatorio erro={erros.username} className="sm:col-span-2">
                            <input className={entrada} autoComplete="off" value={f.username} onChange={(e) => mexer('username', e.target.value)} />
                        </Campo>
                    </div>
                    <Campo
                        etiqueta={t('Palavra-passe')}
                        obrigatorio={!f.tem_password}
                        erro={erros.password}
                        ajuda={id ? <SegredoGuardado guardado={Boolean(f.tem_password)} /> : undefined}
                    >
                        <input type="password" autoComplete="new-password" className={entrada} value={f.password} onChange={(e) => mexer('password', e.target.value)} />
                    </Campo>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo etiqueta={t('Email do remetente')} obrigatorio erro={erros.from_email}>
                            <input type="email" className={entrada} value={f.from_email} onChange={(e) => mexer('from_email', e.target.value)} />
                        </Campo>
                        <Campo etiqueta={t('Nome do remetente')} obrigatorio erro={erros.from_name}>
                            <input className={entrada} value={f.from_name} onChange={(e) => mexer('from_name', e.target.value)} />
                        </Campo>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <Interruptor rotulo={t('Padrão')} nota={t('Tira a marca ao outro servidor do mesmo âmbito.')} valor={f.is_default} aoMudar={(v) => mexer('is_default', v)} cor="indigo" />
                        <Interruptor rotulo={t('Activo')} nota={t('Desligado, não se usa para enviar.')} valor={f.is_active} aoMudar={(v) => mexer('is_active', v)} />
                    </div>
                </div>
            )}
        </Modal>
    );
}

function EnviarTeste({ servidor, emailPorOmissao, aoFechar, aoEnviar }: {
    servidor: ServidorDeCorreio;
    emailPorOmissao: string;
    aoFechar: () => void;
    aoEnviar: (m: string) => void;
}) {
    const [email, porEmail] = useState(emailPorOmissao);
    const enviar = useMutation({ mutationFn: () => definicoes.correio.enviarTeste(servidor.id, email), onSuccess: (r) => aoEnviar(r.message) });
    const erros = enviar.error instanceof ErroDaApi ? enviar.error.erros : {};

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Enviar email de teste')}
            subtitulo={`${servidor.host}:${servidor.porta}`}
            icone="fa-paper-plane"
            cor="bom"
            largura="sm"
            rodape={
                <div className="flex justify-end gap-2">
                    <Botao cor="neutra" onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="bom" tom="solida" icone="fa-paper-plane" aTrabalhar={enviar.isPending} onClick={() => enviar.mutate()}>{t('Enviar')}</Botao>
                </div>
            }
        >
            <Campo etiqueta={t('Para')} obrigatorio erro={erros.email} ajuda={t('O envio fica no histórico de emails.')}>
                <input type="email" className={entrada} value={email} onChange={(e) => porEmail(e.target.value)} />
            </Campo>
        </Modal>
    );
}
