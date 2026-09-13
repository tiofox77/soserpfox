import { MutationCache, type Mutation } from '@tanstack/react-query';

import { ErroDaApi } from '@/api/cliente';
import { t } from '@/i18n';

import { avisar, EVENTO_DE_AVISO } from './avisos';

/**
 * TODO O CRUD AVISA NO CANTO — gravar, alterar, apagar, e quando corre mal.
 *
 * Dos 138 ecrãs com operações, 80 não davam aviso nenhum: guardava-se e a
 * única pista era a lista mudar (ou um recado dentro da página, fora da vista
 * de quem estava no fundo de um formulário comprido). Pôr um `avisar()` em
 * cada `onSuccess` era esquecer-se dele no ecrã seguinte. Fica aqui, no
 * cliente de consultas, para TODAS as mutações:
 *
 *  • sucesso → a mensagem que o servidor mandou (`message`/`mensagem`), ou
 *    «Guardado com sucesso.» quando não mandou nenhuma;
 *  • erro → a mensagem do erro. A sessão morta (401/419) não avisa: a casca
 *    já mostra o ecrã de voltar a entrar.
 *
 * SEM AVISOS REPETIDOS. Os ecrãs que já avisavam continuam a avisar e este não
 * se sobrepõe: se durante a mutação saiu um aviso do mesmo lado (sucesso ou
 * erro), este cala-se. Uma mutação que não é gravar nada (uma
 * pré-visualização) marca `meta: { aviso: false }`; uma que corre a cada toque
 * (o editor de modelos, a contagem linha a linha) marca
 * `meta: { aviso: 'so-com-mensagem' }` e só avisa quando o servidor tem algo a dizer.
 */

type Lado = 'ok' | 'erro';

const avisosEmitidos: Array<{ quando: number; lado: Lado }> = [];
let aOuvir = false;

function ouvir(): void {
    if (aOuvir || typeof window === 'undefined') return;
    aOuvir = true;

    window.addEventListener(EVENTO_DE_AVISO, (e) => {
        const tipo = (e as CustomEvent<{ tipo?: string }>).detail?.tipo;
        avisosEmitidos.push({ quando: Date.now(), lado: tipo === 'erro' ? 'erro' : 'ok' });
        // Só interessa o que é recente.
        while (avisosEmitidos.length > 50) avisosEmitidos.shift();
    });
}

function houveAvisoDesde(inicio: number, lado: Lado): boolean {
    return avisosEmitidos.some((a) => a.quando >= inicio && a.lado === lado);
}

/** A mensagem que o servidor pôs na resposta, se pôs. */
export function mensagemDaResposta(dados: unknown): string | null {
    if (!dados || typeof dados !== 'object') return null;
    const d = dados as Record<string, unknown>;
    const m = d.message ?? d.mensagem;

    return typeof m === 'string' && m.trim() !== '' ? m : null;
}

export function mensagemDoErro(erro: unknown): string | null {
    if (erro instanceof ErroDaApi) {
        if (erro.eSessaoMorta) return null;

        return erro.message || t('Não foi possível concluir a operação.');
    }

    if (erro instanceof Error && erro.name === 'AbortError') return null;

    return erro instanceof Error && erro.message ? erro.message : t('Não foi possível concluir a operação.');
}

const inicioDe = new WeakMap<object, number>();

type MetaDoAviso = { aviso?: boolean | 'so-com-mensagem' } | undefined;

const modoDoAviso = (m: Mutation<unknown, unknown, unknown, unknown>) => (m.options.meta as MetaDoAviso)?.aviso;

/**
 * O cache de mutações com os avisos ligados. O aviso espera um instante: o
 * `onSuccess` do próprio ecrã corre depois deste, e é aí que um ecrã que já
 * avisava o faz — só depois se sabe se é preciso avisar por ele.
 */
export function cacheDeMutacoesComAvisos(esperaMs = 30): MutationCache {
    ouvir();

    return new MutationCache({
        onMutate: (_v, m) => {
            inicioDe.set(m, Date.now());
        },
        onSuccess: (dados, _v, _c, m) => {
            const modo = modoDoAviso(m);
            if (modo === false) return;
            const mensagem = mensagemDaResposta(dados);
            if (modo === 'so-com-mensagem' && !mensagem) return;
            const inicio = inicioDe.get(m) ?? Date.now();

            setTimeout(() => {
                if (houveAvisoDesde(inicio, 'ok')) return;
                avisar(mensagem ?? t('Guardado com sucesso.'), 'ok');
            }, esperaMs);
        },
        onError: (erro, _v, _c, m) => {
            if (modoDoAviso(m) === false) return;
            const inicio = inicioDe.get(m) ?? Date.now();
            const texto = mensagemDoErro(erro);
            if (!texto) return;

            setTimeout(() => {
                if (houveAvisoDesde(inicio, 'erro')) return;
                avisar(texto, 'erro', { duracao: 6000 });
            }, esperaMs);
        },
    });
}
