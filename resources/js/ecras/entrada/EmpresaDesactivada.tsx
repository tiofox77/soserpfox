import { t } from '@/i18n';
import { FOCO, TRANSICAO, cls } from '@/ui/tokens';

import { Moldura, type PropsDeEntrada } from './comum';

/**
 * A EMPRESA FOI DESACTIVADA PELA PLATAFORMA.
 *
 * O `CheckTenantActive` desliga a sessão e deixa aqui o nome, a data e o
 * motivo. O contacto é o das definições da plataforma — o Blade tinha um
 * endereço escrito à mão, diferente do da página de subscrição.
 */
type Props = PropsDeEntrada & {
    login: string;
    empresa: { nome: string | null; em: string | null; motivo: string | null } | null;
};

function Lista({ itens }: { itens: Array<[string, string, string]> }) {
    return (
        <ul className="space-y-2 text-sm">
            {itens.map(([icone, cor, texto], i) => (
                <li key={i} className="entra flex items-start gap-2" style={{ ['--i' as string]: i }}>
                    <i className={cls('fas mt-0.5', icone, cor)} aria-hidden="true" />
                    <span>{texto}</span>
                </li>
            ))}
        </ul>
    );
}

export default function EmpresaDesactivada(p: Props) {
    const contacto = p.contacto ?? { email: '', telefone: '' };

    return (
        <Moldura p={p} tom="vermelho" largura="max-w-2xl" cabeca="from-red-600 to-orange-600" icone="fa-ban"
            titulo={t('Acesso Bloqueado')} subtitulo={t('Sua conta foi temporariamente desativada')}>

            {p.empresa?.nome && (
                <div className="animate-fade-in mb-6 flex items-start gap-3 rounded-xl border-2 border-red-200 bg-red-50 p-4">
                    <i className="fas fa-building mt-1 text-2xl text-red-600" aria-hidden="true" />
                    <div>
                        <h2 className="mb-1 font-bold text-red-900">{t('Empresa Desativada')}</h2>
                        <p className="text-sm font-semibold text-red-800">{p.empresa.nome}</p>
                        {p.empresa.em && <p className="mt-1 text-xs text-red-600"><i className="fas fa-clock mr-1" aria-hidden="true" />{t('Desativado em: :data', { data: p.empresa.em })}</p>}
                    </div>
                </div>
            )}

            {p.empresa?.motivo && (
                <div className="animate-fade-in mb-6 flex items-start gap-3 rounded-xl border-2 border-yellow-300 bg-yellow-50 p-4">
                    <i className="fas fa-comment-dots mt-1 text-2xl text-yellow-600" aria-hidden="true" />
                    <div>
                        <h2 className="mb-2 font-bold text-yellow-900">{t('Motivo da Desativação')}</h2>
                        <p className="text-sm leading-relaxed text-yellow-800">{p.empresa.motivo}</p>
                    </div>
                </div>
            )}

            <div className="mb-6 rounded-xl border-2 border-gray-200 bg-gray-50 p-4 text-gray-700">
                <h2 className="mb-3 flex items-center font-bold text-gray-900"><i className="fas fa-info-circle mr-2 text-blue-600" aria-hidden="true" />{t('O que aconteceu?')}</h2>
                <Lista itens={[
                    ['fa-times-circle', 'text-red-600', t('Sua empresa foi desativada pelo administrador do sistema')],
                    ['fa-times-circle', 'text-red-600', t('Você foi desconectado automaticamente por segurança')],
                    ['fa-times-circle', 'text-red-600', t('Todos os módulos e funcionalidades estão bloqueados')],
                    ['fa-check-circle', 'text-green-600', t('Seus dados estão seguros e não foram excluídos')],
                ]} />
            </div>

            <div className="mb-6 rounded-xl border-2 border-blue-200 bg-blue-50 p-4 text-blue-800">
                <h2 className="mb-3 flex items-center font-bold text-blue-900"><i className="fas fa-lightbulb mr-2 text-yellow-500" aria-hidden="true" />{t('O que fazer agora?')}</h2>
                <Lista itens={[
                    ['fa-phone', 'text-blue-600', t('Entre em contato com o administrador do sistema')],
                    ['fa-envelope', 'text-blue-600', t('Envie um e-mail para :email', { email: contacto.email })],
                    ['fa-comments', 'text-blue-600', t('Resolva a situação descrita acima')],
                    ['fa-check-circle', 'text-green-600', t('Após a reativação, você poderá acessar normalmente')],
                ]} />
            </div>

            <div className="flex flex-col gap-3 sm:flex-row">
                <a href={p.login} className={cls('btn-press inline-flex flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-3 font-semibold text-white shadow-lg hover:shadow-xl', TRANSICAO, FOCO)}>
                    <i className="fas fa-sign-in-alt mr-2" aria-hidden="true" />{t('Voltar ao Login')}
                </a>
                <a href={`mailto:${contacto.email}`} className={cls('btn-press inline-flex flex-1 items-center justify-center rounded-xl border-2 border-gray-300 px-6 py-3 font-semibold text-gray-700 hover:bg-gray-50', TRANSICAO, FOCO)}>
                    <i className="fas fa-envelope mr-2" aria-hidden="true" />{t('Contatar Suporte')}
                </a>
            </div>
        </Moldura>
    );
}
