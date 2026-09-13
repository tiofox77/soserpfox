import { t } from '@/i18n';

import { BotaoDeEnviar, CampoDeEntrada, Formulario, Moldura, texto, type PropsDeEntrada } from './comum';

/**
 * PEDIR O LINK PARA RECUPERAR A SENHA.
 *
 * O `status` («enviámos-lhe o link») chega nos recados da sessão, no topo do
 * cartão — o `ForgotPasswordController` de sempre é quem o escreve.
 */
type Props = PropsDeEntrada & { acao: string; login: string };

export default function RecuperarSenha(p: Props) {
    return (
        <Moldura
            p={p}
            icone="fa-key"
            titulo={t('Recuperar palavra-passe')}
            subtitulo={t('Indique o seu e-mail e enviaremos um link de recuperação.')}
        >
            <Formulario acao={p.acao} csrf={p.csrf} className="space-y-5">
                {(aEnviar) => (
                    <>
                        <CampoDeEntrada nome="email" tipo="email" rotulo={t('E-mail')} icone="fa-envelope" valor={texto(p.antigos?.email)}
                            erro={p.erros?.email} dica="seu@email.com" autoComplete="email" focar />

                        <BotaoDeEnviar aEnviar={aEnviar} icone="fa-paper-plane">{t('Enviar link de recuperação')}</BotaoDeEnviar>
                    </>
                )}
            </Formulario>

            <div className="mt-6 text-center">
                <a href={p.login} className="inline-flex items-center gap-2 text-sm text-gray-600 transition hover:text-blue-600">
                    <i className="fas fa-arrow-left" aria-hidden="true" />{t('Voltar ao login')}
                </a>
            </div>
        </Moldura>
    );
}
