import { t } from '@/i18n';

/**
 * «ESQUECEU A SENHA?» NO PORTAL DO CLIENTE.
 *
 * A ligação existia na entrada e apontava para uma vista que nunca foi escrita —
 * erro 500. A senha do portal é dada pela empresa (a ficha do cliente tem
 * «repor a senha», que manda uma nova por email), e é isso que esta página diz.
 */
export default function EsqueciASenha() {
    return (
        <div className="flex min-h-screen items-center justify-center px-4 py-12">
            <div className="animate-scale-in w-full max-w-md rounded-2xl border border-gray-100 bg-white p-8 text-center shadow-xl">
                <span className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 shadow-lg">
                    <i className="fas fa-key icon-float text-2xl text-white" aria-hidden="true" />
                </span>
                <h1 className="mb-2 text-2xl font-bold text-gray-900">{t('Esqueceu a senha?')}</h1>
                <p className="mb-6 text-gray-600">
                    {t('A senha do portal é dada pela empresa com quem trabalha. Peça-lhe que reponha o seu acesso: recebe uma senha nova por email.')}
                </p>
                <a href="/client/login" className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 px-5 py-3 font-semibold text-white shadow-md transition hover:scale-[1.02]">
                    <i className="fas fa-arrow-left" aria-hidden="true" />{t('Voltar à entrada')}
                </a>
            </div>
        </div>
    );
}
