import { ErroDaApi } from '@/api/cliente';
import { RAIO, cls } from './tokens';

/**
 * O QUE CORREU MAL AO GRAVAR, DITO SEMPRE.
 *
 * Existe por causa de um caso concreto: o servidor devolveu 500 («Column
 * 'cost' cannot be null») e o ecrã mostrava NADA — quem carregava em Guardar
 * via a janela ficar exactamente na mesma, sem uma palavra. Os erros de campo
 * já apareciam ao lado do campo; tudo o resto desaparecia em silêncio.
 *
 * Aqui aparece a mensagem do servidor, seja ela qual for. Um 500 com um texto
 * técnico é feio — mas é infinitamente melhor do que um botão que não faz nada.
 */
export function AvisoDeErro({ erro }: { erro: unknown }) {
    if (!erro) {
        return null;
    }

    const daApi = erro instanceof ErroDaApi ? erro : null;

    // Os erros de campo já se mostram junto ao campo; repeti-los aqui seria
    // dizer duas vezes a mesma coisa.
    if (daApi && Object.keys(daApi.erros).length > 0) {
        return (
            <div
                role="alert"
                className={cls('mb-4 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900', RAIO)}
            >
                <i className="fas fa-triangle-exclamation mr-2" aria-hidden="true" />
                Há campos por corrigir — estão assinalados a vermelho.
            </div>
        );
    }

    return (
        <div
            role="alert"
            className={cls('mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900', RAIO)}
        >
            <p className="font-semibold">
                <i className="fas fa-circle-exclamation mr-2" aria-hidden="true" />
                Não foi possível gravar
            </p>
            <p className="mt-1 break-words">
                {daApi?.message ?? 'O servidor não respondeu. Verifique a ligação e tente outra vez.'}
            </p>
        </div>
    );
}
