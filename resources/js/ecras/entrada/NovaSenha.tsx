import { t } from '@/i18n';

import { BotaoDeEnviar, CampoDeEntrada, Formulario, Moldura, texto, type PropsDeEntrada } from './comum';

/**
 * DEFINIR A SENHA NOVA, a partir do link do e-mail.
 *
 * O token viaja escondido no formulário, como sempre; o `ResetPasswordController`
 * valida-o, grava e entra.
 */
type Props = PropsDeEntrada & { acao: string; token: string; email: string | null; login: string };

export default function NovaSenha(p: Props) {
    return (
        <Moldura p={p} icone="fa-lock" tom="verde" titulo={t('Definir nova palavra-passe')} subtitulo={t('Escolha uma palavra-passe segura para a sua conta.')}>
            <Formulario acao={p.acao} csrf={p.csrf} escondidos={{ token: p.token }} className="space-y-5">
                {(aEnviar) => (
                    <>
                        <CampoDeEntrada nome="email" tipo="email" rotulo={t('E-mail')} icone="fa-envelope" valor={p.email ?? texto(p.antigos?.email)}
                            erro={p.erros?.email} dica="seu@email.com" autoComplete="email" focar={!p.email} />

                        <CampoDeEntrada nome="password" tipo="password" rotulo={t('Nova palavra-passe')} icone="fa-lock"
                            erro={p.erros?.password} dica={t('Mínimo 8 caracteres')} autoComplete="new-password" focar={!!p.email} />

                        <CampoDeEntrada nome="password_confirmation" tipo="password" rotulo={t('Confirmar palavra-passe')} icone="fa-lock"
                            dica={t('Repita a palavra-passe')} autoComplete="new-password" />

                        <BotaoDeEnviar aEnviar={aEnviar} icone="fa-check" cor="from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700">
                            {t('Redefinir palavra-passe')}
                        </BotaoDeEnviar>
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
