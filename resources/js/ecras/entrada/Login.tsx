import { t } from '@/i18n';
import { FOCO, TRANSICAO, cls } from '@/ui/tokens';

import { BotaoDeEnviar, CampoDeEntrada, Formulario, Moldura, texto, type PropsDeEntrada } from './comum';

/**
 * ENTRAR NO SISTEMA.
 *
 * O POST vai para o `LoginController` de sempre: a limitação de tentativas, o
 * «para onde ia» e o sinal do PWA continuam lá. Os campos mantêm os nomes
 * (`email`, `password`, `remember`) de que os ensaios e os gestores de senhas
 * dependem.
 */
type Props = PropsDeEntrada & { acao: string; recuperar: string | null; registo: string };

export default function Login(p: Props) {
    const erros = p.erros ?? {};
    const antigos = p.antigos ?? {};

    return (
        <Moldura
            p={p}
            icone="fa-right-to-bracket"
            titulo={t('Bem-vindo de volta')}
            subtitulo={t('Entre com suas credenciais para continuar')}
            rodape={
                <a href={p.site} className="text-sm font-medium text-gray-600 transition hover:text-gray-900">
                    <i className="fas fa-arrow-left mr-2" aria-hidden="true" />{t('Voltar para o site')}
                </a>
            }
        >
            <Formulario acao={p.acao} csrf={p.csrf} className="space-y-6">
                {(aEnviar) => (
                    <>
                        <CampoDeEntrada nome="email" tipo="email" rotulo={t('Email')} icone="fa-envelope" valor={texto(antigos.email)}
                            erro={erros.email} dica="seu@email.com" autoComplete="email" focar />

                        <CampoDeEntrada nome="password" tipo="password" rotulo={t('Senha')} icone="fa-lock"
                            erro={erros.password} dica="••••••••" autoComplete="current-password" />

                        <div className="flex items-center justify-between">
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" name="remember" defaultChecked={!!antigos.remember} className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                {t('Lembrar-me')}
                            </label>

                            {p.recuperar && (
                                <a href={p.recuperar} className="text-sm font-medium text-blue-600 transition hover:text-blue-800">{t('Esqueceu a senha?')}</a>
                            )}
                        </div>

                        <BotaoDeEnviar aEnviar={aEnviar} icone="fa-sign-in-alt">{aEnviar ? t('A entrar…') : t('Entrar')}</BotaoDeEnviar>
                    </>
                )}
            </Formulario>

            <div className="relative my-6">
                <div className="absolute inset-0 flex items-center"><div className="w-full border-t border-gray-300" /></div>
                <div className="relative flex justify-center text-sm"><span className="bg-white px-4 text-gray-500">{t('Não tem conta?')}</span></div>
            </div>

            <a href={p.registo} className={cls('btn-press block w-full rounded-xl bg-gray-100 py-3 text-center font-semibold text-gray-900 hover:bg-gray-200', TRANSICAO, FOCO)}>
                <i className="fas fa-user-plus mr-2" aria-hidden="true" />{t('Criar Conta')}
            </a>
        </Moldura>
    );
}
