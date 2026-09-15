import type { ReactNode } from 'react';

import { t, tPartes } from '@/i18n';
import { FOCO, TRANSICAO, cls } from '@/ui/tokens';

import { BotaoDeEnviar, CampoDeEntrada, Formulario, Moldura, type PropsDeEntrada } from './comum';

/**
 * O CONVITE PARA ENTRAR NUMA EMPRESA — as quatro páginas numa.
 *
 * Eram quatro Blade quase iguais (por aceitar, expirado, já aceite, cancelado).
 * O `InvitationController` continua a decidir o estado; aqui escolhe-se a cor,
 * o ícone e o que se diz.
 */
type Estado = 'pendente' | 'expirado' | 'aceite' | 'cancelado';

type Props = PropsDeEntrada & {
    estado: Estado;
    login: string;
    acao: string;
    convite: {
        nome: string;
        email: string;
        empresa: string;
        convidou: string;
        funcao: string | null;
        expira: string | null;
        expirou_em: string | null;
        aceite_em: string | null;
    };
};

const FORMA: Record<Estado, { icone: string; cabeca: string; tom: 'azul' | 'vermelho' | 'verde' | 'cinza'; caixa: string }> = {
    pendente: { icone: 'fa-user-check', cabeca: 'from-blue-600 to-purple-600', tom: 'azul', caixa: 'border-blue-200 from-blue-50 to-purple-50' },
    expirado: { icone: 'fa-clock', cabeca: 'from-red-600 to-orange-600', tom: 'vermelho', caixa: 'border-orange-200 from-orange-50 to-orange-50' },
    aceite: { icone: 'fa-check-circle', cabeca: 'from-green-600 to-blue-600', tom: 'verde', caixa: 'border-green-200 from-green-50 to-green-50' },
    cancelado: { icone: 'fa-ban', cabeca: 'from-gray-600 to-gray-800', tom: 'cinza', caixa: 'border-gray-200 from-gray-50 to-gray-50' },
};

function Linha({ icone, rotulo, children, i }: { icone: string; rotulo: string; children: ReactNode; i: number }) {
    return (
        <div className="entra flex items-center gap-2" style={{ ['--i' as string]: i }}>
            <i className={cls('fas w-6 text-blue-600', icone)} aria-hidden="true" />
            <span className="text-gray-700"><strong>{rotulo}:</strong> {children}</span>
        </div>
    );
}

export default function Convite(p: Props) {
    const f = FORMA[p.estado];
    const c = p.convite;

    const titulo = {
        pendente: t('Você Foi Convidado!'),
        expirado: t('Convite Expirado'),
        aceite: t('Convite Já Aceito'),
        cancelado: t('Convite Cancelado'),
    }[p.estado];

    const irAoLogin = (
        <a href={p.login} className={cls('btn-press inline-flex items-center rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg hover:shadow-xl', TRANSICAO, FOCO)}>
            <i className="fas fa-sign-in-alt mr-2" aria-hidden="true" />
            {p.estado === 'aceite' ? t('Fazer Login') : t('Ir para Login')}
        </a>
    );

    return (
        <Moldura p={p} tom={f.tom} cabeca={f.cabeca} icone={f.icone} titulo={titulo}
            subtitulo={p.estado === 'pendente' ? t('Complete seu cadastro para aceitar') : undefined}>

            {p.estado !== 'pendente' && (
                <p className="mb-4 text-center text-gray-600">
                    {p.estado === 'expirado' && t('Infelizmente, este convite expirou em :data.', { data: c.expirou_em ?? '—' })}
                    {p.estado === 'aceite' && t('Este convite já foi aceito em :data.', { data: c.aceite_em ?? '—' })}
                    {p.estado === 'cancelado' && t('Este convite foi cancelado e não está mais disponível.')}
                </p>
            )}

            <div className={cls('mb-6 space-y-2 rounded-xl border-2 bg-gradient-to-r p-4 text-sm', f.caixa)}>
                {p.estado === 'pendente' && <Linha i={0} icone="fa-user" rotulo={t('Nome')}>{c.nome}</Linha>}
                <Linha i={1} icone="fa-envelope" rotulo={t('Email')}>{c.email}</Linha>
                <Linha i={2} icone="fa-building" rotulo={t('Empresa')}>{c.empresa}</Linha>
                {p.estado === 'pendente' && <Linha i={3} icone="fa-user-tag" rotulo={t('Convidado por')}>{c.convidou}</Linha>}
                {p.estado === 'pendente' && c.funcao && <Linha i={4} icone="fa-id-badge" rotulo={t('Função')}>{c.funcao}</Linha>}
            </div>

            {p.estado === 'pendente' ? (
                <>
                    <Formulario acao={p.acao} csrf={p.csrf} className="space-y-4">
                        {(aEnviar) => (
                            <>
                                <CampoDeEntrada nome="password" tipo="password" rotulo={`${t('Criar Senha')} *`} icone="fa-lock" minimo={8}
                                    erro={p.erros?.password} dica={t('Mínimo 8 caracteres, com letras e números.')} autoComplete="new-password" focar />
                                <CampoDeEntrada nome="password_confirmation" tipo="password" rotulo={`${t('Confirmar Senha')} *`} icone="fa-lock" minimo={8}
                                    dica={t('Digite a senha novamente')} autoComplete="new-password" />
                                <p className="flex items-start gap-2 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-900">
                                    <i className="fas fa-shield-halved mt-0.5 text-blue-500" aria-hidden="true" />
                                    <span>
                                        {t('Ao criar a conta aceita os')}{' '}
                                        <a href="/termos" target="_blank" rel="noreferrer" className="font-semibold underline">{t('Termos de Serviço')}</a>{' '}
                                        {t('e declara ter lido a')}{' '}
                                        <a href="/privacidade" target="_blank" rel="noreferrer" className="font-semibold underline">{t('Política de Privacidade')}</a>.
                                    </span>
                                </p>
                                <div className="pt-2">
                                    <BotaoDeEnviar aEnviar={aEnviar} icone="fa-check-circle">{t('Aceitar Convite e Criar Conta')}</BotaoDeEnviar>
                                </div>
                            </>
                        )}
                    </Formulario>

                    <div className="mt-6 text-center text-xs text-gray-500">
                        <p>{t('Ao aceitar, você concorda com os termos de uso')}</p>
                        {c.expira && <p className="mt-1">{t('Este convite expira :quando', { quando: c.expira })}</p>}
                    </div>
                </>
            ) : (
                <>
                    <div className="mb-6 border-l-4 border-blue-500 bg-blue-50 p-4 text-left text-sm text-blue-800">
                        {p.estado === 'aceite' ? (
                            <>
                                <p className="mb-1 font-semibold">✅ {t('Conta já criada!')}</p>
                                <p>{t('Se você esqueceu sua senha, use a opção "Esqueci minha senha" na tela de login.')}</p>
                            </>
                        ) : (
                            <>
                                <p className="mb-1 font-semibold">💡 {t('O que fazer?')}</p>
                                <p>
                                    {tPartes(p.estado === 'expirado'
                                        ? 'Entre em contato com :nome para solicitar um novo convite.'
                                        : 'Se você acredita que isso foi um erro, entre em contato com :nome.', { nome: <strong key="n">{c.convidou}</strong> })}
                                </p>
                            </>
                        )}
                    </div>
                    <div className="text-center">{irAoLogin}</div>
                </>
            )}
        </Moldura>
    );
}
