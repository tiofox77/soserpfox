import { t } from '@/i18n';

import { BotaoDeEnviar, CampoDeEntrada, Formulario, Moldura, type PropsDeEntrada } from './comum';

/**
 * CONFIRMAR A SENHA ANTES DE UMA ZONA SENSÍVEL (`password.confirm`).
 *
 * Era a vista do Bootstrap que veio com o Laravel UI, com classes que este
 * projecto nunca carregou: abria sem estilo nenhum.
 */
type Props = PropsDeEntrada & { acao: string; recuperar: string | null };

export default function ConfirmarSenha(p: Props) {
    return (
        <Moldura p={p} icone="fa-shield-halved" titulo={t('Confirmar palavra-passe')} subtitulo={t('Por segurança, confirme a sua palavra-passe antes de continuar.')}>
            <Formulario acao={p.acao} csrf={p.csrf} className="space-y-5">
                {(aEnviar) => (
                    <>
                        <CampoDeEntrada nome="password" tipo="password" rotulo={t('Palavra-passe')} icone="fa-lock"
                            erro={p.erros?.password} dica="••••••••" autoComplete="current-password" focar />

                        <BotaoDeEnviar aEnviar={aEnviar} icone="fa-check">{t('Confirmar palavra-passe')}</BotaoDeEnviar>
                    </>
                )}
            </Formulario>

            {p.recuperar && (
                <div className="mt-6 text-center">
                    <a href={p.recuperar} className="text-sm font-medium text-blue-600 transition hover:text-blue-800">{t('Esqueceu a senha?')}</a>
                </div>
            )}
        </Moldura>
    );
}
