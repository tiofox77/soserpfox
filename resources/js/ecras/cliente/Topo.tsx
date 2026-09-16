import { useCallback, useRef, useState } from 'react';

import { t } from '@/i18n';
import { FOCO, TRANSICAO, cls } from '@/ui/tokens';

import { useFecharFora } from '../casca/comum';

/**
 * A BARRA DO TOPO DO PORTAL DO CLIENTE.
 *
 * Era Blade com um `<script>` em linha para abrir os dois menus (o do
 * utilizador e o do telemóvel). As ligações e o «está activo» continuam a vir
 * do servidor; aqui só a forma: abrir, fechar ao clicar fora ou com Escape.
 */
type Ligacao = { url: string; icone: string; rotulo: string; activo: boolean };

type Props = {
    inicio: string;
    logo: string | null;
    nome: string;
    ligacoes: Ligacao[];
    perfil: string;
    sair: string;
    csrf: string;
    /** O nome do portal ao lado do logótipo — o do revendedor usa esta mesma barra. */
    titulo?: string;
    /** O nome de quem está dentro, no menu. */
    quem?: string;
};

function Sair({ sair, csrf, className }: { sair: string; csrf: string; className: string }) {
    return (
        <form method="POST" action={sair}>
            <input type="hidden" name="_token" value={csrf} />
            <button type="submit" className={className}>
                <i className="fas fa-right-from-bracket mr-2 w-5" aria-hidden="true" />{t('Sair')}
            </button>
        </form>
    );
}

export default function Topo(p: Props) {
    const [menu, porMenu] = useState<'utilizador' | 'telemovel' | null>(null);
    const utilizador = useRef<HTMLDivElement>(null);
    const telemovel = useRef<HTMLDivElement>(null);
    const fechar = useCallback(() => porMenu(null), []);

    useFecharFora(utilizador, menu === 'utilizador', fechar);
    useFecharFora(telemovel, menu === 'telemovel', fechar);

    return (
        <nav className="relative bg-white">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex h-16 justify-between">
                    <a href={p.inicio} className={cls('group flex items-center rounded-lg', FOCO)}>
                        {p.logo ? (
                            <img src={p.logo} alt={p.nome} className="mr-3 h-12 w-auto object-contain" />
                        ) : (
                            <span className="mr-3 flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 transition-transform duration-300 group-hover:rotate-6">
                                <i className="fas fa-users text-white" aria-hidden="true" />
                            </span>
                        )}
                        <span className="text-xl font-bold text-gray-900">{p.titulo ?? t('Portal do Cliente')}</span>
                    </a>

                    <div className="hidden items-center space-x-1 md:flex">
                        {p.ligacoes.map((l) => (
                            <a key={l.url} href={l.url} aria-current={l.activo ? 'page' : undefined}
                                className={cls('rounded-md px-3 py-2 text-sm font-medium', TRANSICAO, l.activo ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50 hover:text-blue-600')}>
                                <i className={cls('fas mr-1', l.icone)} aria-hidden="true" />{l.rotulo}
                            </a>
                        ))}

                        <div ref={utilizador} className="relative ml-2" data-menu>
                            <button type="button" onClick={() => porMenu((m) => (m === 'utilizador' ? null : 'utilizador'))} aria-expanded={menu === 'utilizador'} aria-label={t('Menu do utilizador')}
                                className={cls('flex items-center rounded-full text-gray-700 hover:text-blue-600', FOCO)}>
                                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100"><i className="fas fa-user text-blue-600" aria-hidden="true" /></span>
                                <i className={cls('fas fa-chevron-down ml-2 text-sm transition-transform duration-200', menu === 'utilizador' && 'rotate-180')} aria-hidden="true" />
                            </button>
                            {menu === 'utilizador' && (
                                <div className="animate-scale-in absolute right-0 z-20 mt-2 w-56 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5">
                                    {p.quem && <p className="truncate border-b border-gray-100 px-4 py-2 text-xs font-semibold text-gray-500">{p.quem}</p>}
                                    <a href={p.perfil} className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i className="fas fa-circle-user mr-2" aria-hidden="true" />{t('Meu Perfil')}
                                    </a>
                                    <Sair sair={p.sair} csrf={p.csrf} className="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50" />
                                </div>
                            )}
                        </div>
                    </div>

                    <div ref={telemovel} className="flex items-center md:hidden" data-menu>
                        <button type="button" onClick={() => porMenu((m) => (m === 'telemovel' ? null : 'telemovel'))} aria-expanded={menu === 'telemovel'} aria-label={t('Menu')}
                            className={cls('rounded-lg p-2 text-gray-700 hover:text-blue-600', FOCO)}>
                            <i className={cls('fas text-xl', menu === 'telemovel' ? 'fa-xmark' : 'fa-bars')} aria-hidden="true" />
                        </button>
                        {menu === 'telemovel' && (
                            <div className="animate-fade-in absolute left-0 right-0 top-16 z-20 border-t border-gray-100 bg-white py-2 shadow-lg">
                                {p.ligacoes.map((l) => (
                                    <a key={l.url} href={l.url} aria-current={l.activo ? 'page' : undefined}
                                        className={cls('block px-6 py-3 text-sm font-medium', l.activo ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50')}>
                                        <i className={cls('fas mr-2 w-5', l.icone)} aria-hidden="true" />{l.rotulo}
                                    </a>
                                ))}
                                <a href={p.perfil} className="block border-t border-gray-100 px-6 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <i className="fas fa-circle-user mr-2 w-5" aria-hidden="true" />{t('Meu Perfil')}
                                </a>
                                <Sair sair={p.sair} csrf={p.csrf} className="block w-full px-6 py-3 text-left text-sm font-medium text-red-600 hover:bg-red-50" />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </nav>
    );
}
