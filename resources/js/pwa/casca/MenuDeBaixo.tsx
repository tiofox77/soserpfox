import { usePwa } from '../contexto';

/**
 * O MENU DE BAIXO — sai do servidor já decidido (`MenuDoPwa`: módulo,
 * permissão e escolha da empresa) e fica assim na cópia guardada: é o que se
 * vê sem rede. O activo sai do endereço, e não do servidor, porque a página
 * servida sem rede pode ser a de outro endereço.
 */
export function MenuDeBaixo({ activa }: { activa: string | null }) {
    const { menu = [] } = usePwa();

    return (
        <nav className="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur border-t border-gray-200 shadow-2xl z-40" style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}>
            <div className="grid text-center" style={{ gridTemplateColumns: `repeat(${Math.max(1, menu.length)}, minmax(0, 1fr))` }}>
                {menu.map((m) => {
                    if (m.destaque) {
                        // O botão redondo do meio: já é o elemento mais visível do ecrã.
                        return (
                            <a key={m.chave} href={m.url} className="py-2 -mt-4 group" aria-current={activa === m.chave ? 'page' : undefined}>
                                <span className={`w-12 h-12 mx-auto bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center shadow-lg text-white transition group-active:scale-95 ${activa === m.chave ? 'ring-4 ring-orange-200' : 'pwa-pulsa'}`}>
                                    <i className={`fas ${m.icone} text-lg`} aria-hidden="true" />
                                </span>
                                <span className="text-[10px] font-semibold text-orange-700 block mt-0.5">{m.etiqueta}</span>
                            </a>
                        );
                    }

                    const restaurante = m.chave === 'restaurante';
                    const e = activa === m.chave;
                    const cor = e
                        ? (restaurante ? 'text-orange-700 bg-orange-50' : 'text-blue-700 bg-blue-50')
                        : 'text-gray-600';

                    return (
                        <a key={m.chave} href={m.url} aria-current={e ? 'page' : undefined}
                           className={`relative py-3 transition-colors ${restaurante ? 'hover:bg-orange-50' : 'hover:bg-blue-50'} ${cor}`}>
                            {e && <span className={`absolute top-0 left-1/2 -translate-x-1/2 h-0.5 w-8 rounded-full ${restaurante ? 'bg-orange-500' : 'bg-blue-600'}`} aria-hidden="true" />}
                            <i className={`fas ${m.icone} block text-lg transition-transform ${e ? 'scale-110' : ''}`} aria-hidden="true" />
                            <span className="text-[10px] font-semibold">{m.etiqueta}</span>
                        </a>
                    );
                })}
            </div>
        </nav>
    );
}
