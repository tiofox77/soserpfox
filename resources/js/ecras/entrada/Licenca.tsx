import { useState } from 'react';

import { t } from '@/i18n';
import { FOCO, TRANSICAO, cls } from '@/ui/tokens';

import { BotaoDeEnviar, Formulario, Recados, texto, type PropsDeEntrada } from './comum';

/**
 * A LICENÇA DA INSTALAÇÃO (build offline).
 *
 * É a única porta que responde com o sistema bloqueado — o middleware da
 * licença deixa `licenca*` passar, e o pacote do React é um ficheiro estático
 * que não passa por middleware nenhum. Os formulários são POST para o
 * `LicencaController` de sempre; os dois separadores eram um `<script>` com
 * `onclick`.
 */
type Props = PropsDeEntrada & {
    estado: {
        nome: string;
        tom: 'ativa' | 'aviso' | 'bloqueada';
        motivo: string;
        empresa: string | null;
        plano: string | null;
        modulos: string[] | null;
        dias_para_expirar: number | null;
        dias_offline: number | null;
        graca_dias: number | null;
    };
    maquina: string;
    ligacao: { ok: boolean; estado: string; mensagem: string; host: string | null };
    pedido: { codigo?: string | null; pacote?: string | null; motivo?: string | null } | null;
    boa: boolean;
    tem_servidor: boolean;
    rotas: { verificar: string; solicitar: string; sincronizar: string; guardar: string; login: string };
    /** O token colado que não passou: volta ao campo (os `antigos` nunca levam tokens). */
    token_antigo?: string | null;
};

const TOM = {
    ativa: 'bg-green-100 text-green-800',
    aviso: 'bg-yellow-100 text-yellow-800',
    bloqueada: 'bg-red-100 text-red-800',
};

function Etiqueta({ tom, children }: { tom: keyof typeof TOM; children: string }) {
    return <span className={cls('inline-block rounded-full px-2.5 py-0.5 text-xs font-bold uppercase', TOM[tom])}>{children}</span>;
}

function Campo({ nome, rotulo, tipo = 'text', valor, erro, obrigatorio = false, largo = false, minimo }: {
    nome: string; rotulo: string; tipo?: string; valor?: string; erro?: string; obrigatorio?: boolean; largo?: boolean; minimo?: number;
}) {
    return (
        <div className={cls(largo && 'sm:col-span-2')}>
            <label htmlFor={`licenca-${nome}`} className="mb-1.5 block text-sm font-semibold text-gray-700">{rotulo}{obrigatorio && ' *'}</label>
            <input id={`licenca-${nome}`} name={nome} type={tipo} defaultValue={valor} required={obrigatorio} min={minimo}
                className={cls('w-full rounded-xl border-2 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-100', erro ? 'border-red-400' : 'border-gray-200', TRANSICAO)} />
            {erro && <p role="alert" className="mt-1 text-xs text-red-700">{erro}</p>}
        </div>
    );
}

export default function Licenca(p: Props) {
    const [separador, porSeparador] = useState<'pedir' | 'instalar'>(p.boa ? 'instalar' : 'pedir');
    const e = p.estado;
    const a = p.antigos ?? {};
    const botaoSky = 'from-sky-500 to-sky-600 hover:from-sky-600 hover:to-sky-700';

    const linhas: Array<[string, React.ReactNode]> = [
        [t('Estado'), <Etiqueta key="e" tom={e.tom}>{e.nome}</Etiqueta>],
        [t('Detalhe'), e.motivo],
        ...(e.empresa !== null || e.plano !== null ? [
            [t('Empresa'), e.empresa ?? '—'],
            [t('Plano'), e.plano ?? '—'],
            [t('Módulos'), e.modulos?.length ? e.modulos.join(', ') : '—'],
            [t('Dias para expirar'), e.dias_para_expirar ?? '—'],
            [t('Dias offline'), `${e.dias_offline ?? '—'} / ${e.graca_dias ?? '—'}`],
        ] as Array<[string, React.ReactNode]> : []),
        [t('Esta máquina'), <code key="m" className="rounded-lg bg-slate-100 px-2.5 py-1 font-mono text-xs">{p.maquina}</code>],
        [t('Ligação ao fornecedor'), (
            <div key="l">
                {p.ligacao.ok ? <Etiqueta tom="ativa">{t('Ligado')}</Etiqueta>
                    : p.ligacao.estado === 'sem_configuracao' ? <Etiqueta tom="bloqueada">{t('Não configurado')}</Etiqueta>
                        : <Etiqueta tom="aviso">{t('Sem ligação')}</Etiqueta>}
                <p className="mt-1 text-xs text-gray-500">{p.ligacao.mensagem}</p>
                {p.ligacao.host && <code className="mt-1 inline-block rounded bg-slate-100 px-2 py-0.5 font-mono text-[11px]">{p.ligacao.host}</code>}
            </div>
        )],
    ];

    return (
        <div className="min-h-screen bg-slate-50 px-4 py-[6vh]">
            <div className="animate-scale-in mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-7 shadow-xl">
                <div className="mb-5 flex items-center gap-3">
                    <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-sky-600 shadow-lg">
                        <i className="fas fa-key icon-float text-xl text-white" aria-hidden="true" />
                    </span>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">{t('Licença do soserp')}</h1>
                        <p className="text-sm text-slate-500">{t('Estado da instalação e activação de licença.')}</p>
                    </div>
                </div>

                <Recados recados={p.recados} />

                <table className="mb-5 w-full border-collapse text-sm">
                    <tbody>
                        {linhas.map(([rotulo, valor], i) => (
                            <tr key={rotulo} className="entra border-b border-slate-100" style={{ ['--i' as string]: i }}>
                                <td className="w-2/5 px-2 py-2.5 align-top text-slate-500">{rotulo}</td>
                                <td className="px-2 py-2.5">{valor}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {p.pedido && (p.pedido.codigo || p.pedido.pacote) && (
                    <div className="mb-4 rounded-xl border border-slate-200 bg-slate-100 p-4 text-sm">
                        {p.pedido.codigo ? (
                            <>
                                <p><strong>{t('Pedido enviado ao fornecedor.')}</strong><br />{t('Código')}: <code className="font-mono text-xs">{p.pedido.codigo}</code></p>
                                <Formulario acao={p.rotas.verificar} csrf={p.csrf} className="mt-3">
                                    {(aEnviar) => <BotaoDeEnviar aEnviar={aEnviar} icone="fa-rotate" cor={botaoSky}>{t('Já foi aprovado? Verificar agora')}</BotaoDeEnviar>}
                                </Formulario>
                            </>
                        ) : (
                            <>
                                <p><strong>{t('O pedido não foi enviado automaticamente.')}</strong></p>
                                {p.pedido.motivo && <p className="mt-1 text-yellow-800">{t('Motivo')}: {p.pedido.motivo}</p>}
                                <p className="mt-1">{t('Envie este código por email ou WhatsApp para receber a licença:')}</p>
                                <textarea readOnly onFocus={(ev) => ev.currentTarget.select()} value={p.pedido.pacote ?? ''}
                                    className="mt-2 min-h-[80px] w-full rounded-xl border border-slate-200 p-3 font-mono text-xs" />
                            </>
                        )}
                    </div>
                )}

                {p.boa && (
                    <div className="animate-fade-in mb-4 rounded-xl border border-green-200 bg-green-50 p-4 text-sm">
                        <p><strong>{t('Esta instalação está licenciada.')}</strong> {t('Pode entrar no sistema — este ecrã serve agora só para substituir a licença.')}</p>
                        <a href={p.rotas.login} className={cls('btn-press mt-3 inline-flex items-center gap-2 rounded-xl bg-sky-500 px-4 py-2.5 font-bold text-white hover:bg-sky-600', FOCO)}>
                            <i className="fas fa-right-to-bracket" aria-hidden="true" />{t('Entrar no soserp')}
                        </a>
                    </div>
                )}

                <div className="mb-4 mt-6 flex gap-6 border-b border-slate-200" role="tablist">
                    {!p.boa && (
                        <button type="button" role="tab" aria-selected={separador === 'pedir'} onClick={() => porSeparador('pedir')}
                            className={cls('-mb-px border-b-2 px-1 py-2.5 text-sm font-semibold', separador === 'pedir' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700', TRANSICAO)}>
                            {t('Solicitar licença')}
                        </button>
                    )}
                    <button type="button" role="tab" aria-selected={separador === 'instalar'} onClick={() => porSeparador('instalar')}
                        className={cls('-mb-px border-b-2 px-1 py-2.5 text-sm font-semibold', separador === 'instalar' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700', TRANSICAO)}>
                        {p.boa ? t('Substituir licença') : t('Já tenho uma licença')}
                    </button>
                </div>

                {separador === 'pedir' && !p.boa && (
                    <div key="pedir" className="animate-fade-in" role="tabpanel">
                        <Formulario acao={p.rotas.solicitar} csrf={p.csrf}>
                            {(aEnviar) => (
                                <>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <Campo nome="empresa" rotulo={t('Nome da empresa')} valor={texto(a.empresa)} erro={p.erros?.empresa} obrigatorio largo />
                                        <Campo nome="nif" rotulo={t('NIF')} valor={texto(a.nif)} erro={p.erros?.nif} />
                                        <Campo nome="utilizadores" rotulo={t('Nº de utilizadores')} tipo="number" minimo={1} valor={texto(a.utilizadores ?? 5)} erro={p.erros?.utilizadores} />
                                        <Campo nome="responsavel" rotulo={t('Responsável')} valor={texto(a.responsavel)} erro={p.erros?.responsavel} />
                                        <Campo nome="telefone" rotulo={t('Telefone')} valor={texto(a.telefone)} erro={p.erros?.telefone} />
                                        <Campo nome="email" rotulo={t('Email')} tipo="email" valor={texto(a.email)} erro={p.erros?.email} largo />
                                        <div className="sm:col-span-2">
                                            <label htmlFor="licenca-observacoes" className="mb-1.5 block text-sm font-semibold text-gray-700">{t('Observações')}</label>
                                            <textarea id="licenca-observacoes" name="observacoes" defaultValue={texto(a.observacoes)}
                                                className="min-h-[60px] w-full rounded-xl border-2 border-gray-200 p-3 text-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-100" />
                                        </div>
                                    </div>
                                    <div className="mt-4"><BotaoDeEnviar aEnviar={aEnviar} icone="fa-paper-plane" cor={botaoSky}>{t('Solicitar licença ao fornecedor')}</BotaoDeEnviar></div>
                                    <p className="mt-2 text-xs text-slate-500">
                                        {p.tem_servidor
                                            ? t('O pedido segue para o fornecedor com a impressão desta máquina. Se não houver internet, é gerado um código para enviar por outra via.')
                                            : t('Sem servidor configurado: será gerado um código para enviar ao fornecedor.')}
                                    </p>
                                </>
                            )}
                        </Formulario>
                    </div>
                )}

                {separador === 'instalar' && (
                    <div key="instalar" className="animate-fade-in space-y-5" role="tabpanel">
                        {p.tem_servidor && (
                            <Formulario acao={p.rotas.sincronizar} csrf={p.csrf}>
                                {(aEnviar) => (
                                    <>
                                        <BotaoDeEnviar aEnviar={aEnviar} icone="fa-arrows-rotate" cor={botaoSky}>{t('Sincronizar com o fornecedor agora')}</BotaoDeEnviar>
                                        <p className="mt-2 text-xs text-slate-500">{t('Vai buscar a licença mais recente (prazo, módulos, bloqueios). É o mesmo que a instalação faz sozinha de tempos a tempos — este botão não espera.')}</p>
                                    </>
                                )}
                            </Formulario>
                        )}

                        <Formulario acao={p.rotas.guardar} csrf={p.csrf}>
                            {(aEnviar) => (
                                <>
                                    <label htmlFor="licenca-token" className="mb-1.5 block text-sm font-semibold text-gray-700">{t('Instalar / substituir licença')}</label>
                                    <textarea id="licenca-token" name="token" defaultValue={p.token_antigo ?? ''} placeholder={t('Cole aqui o token da licença (SOSERP-LIC.v1....)')}
                                        className={cls('min-h-[120px] w-full rounded-xl border-2 p-3 font-mono text-xs outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-100', p.erros?.token ? 'border-red-400' : 'border-gray-200')} />
                                    {p.erros?.token && <p role="alert" className="mt-1 text-xs text-red-700">{p.erros.token}</p>}
                                    <div className="mt-3"><BotaoDeEnviar aEnviar={aEnviar} icone="fa-download" cor={botaoSky}>{t('Instalar licença')}</BotaoDeEnviar></div>
                                    <p className="mt-2 text-xs text-slate-500">{t('A licença é verificada localmente, sem internet.')}</p>
                                </>
                            )}
                        </Formulario>
                    </div>
                )}
            </div>
        </div>
    );
}
