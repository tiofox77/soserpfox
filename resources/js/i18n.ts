/**
 * AS TRÊS LÍNGUAS, NOS ECRÃS EM REACT.
 *
 * O `t()` é o `__()` do Blade: a chave é a frase em PORTUGUÊS, e o dicionário
 * (`lang/en.json`, `lang/fr.json`) traduz. Sem tradução, sai a frase de origem
 * — exactamente como o Laravel faz. É isso que permite embrulhar os ecrãs aos
 * poucos sem nunca piorar o que está lá.
 *
 * Em português não se descarrega dicionário nenhum: a chave já é a resposta.
 *
 * ORDEM QUE TEM DE SE MANTER: o `react.tsx` carrega o dicionário ANTES de
 * montar, e cada ecrã só é importado (`import()` preguiçoso) dentro do montar.
 * É isso que deixa um `t()` correr à cabeça de um módulo de ecrã — numa lista
 * de separadores, por exemplo — e ainda assim apanhar a tradução. Importar um
 * ecrã de forma imediata partia essa garantia: os primeiros `t()` corriam sem
 * dicionário e ficavam em português para sempre naquela página.
 */

import { Fragment, createElement, type ReactNode } from 'react';

type Dicionario = Record<string, string>;

let dicionario: Dicionario = {};

declare global {
    interface Window {
        __reactLingua?: string;
        __reactDicionarioUrl?: string;
    }
}

export function lingua(): string {
    return (typeof window === 'undefined' ? undefined : window.__reactLingua) ?? 'pt';
}

/**
 * A ETIQUETA QUE O `Intl` ENTENDE, para a língua em que se está a trabalhar.
 *
 * O dicionário traduz as frases; os números, as datas e os nomes dos meses não
 * passam por ele — saem do `Intl` do navegador, e o `Intl` precisa de uma
 * etiqueta BCP-47, não de «pt». Esta tabela é a mesma que o painel em Blade
 * tinha, e existia lá porque sem ela a página ficava em inglês com o gráfico
 * por baixo a dizer «ago.» e a separar os decimais por vírgula. É a
 * meia-tradução que passa despercebida a quem revê o texto e salta à vista a
 * quem usa.
 *
 * Fica AQUI, e não no `tokens.ts`, porque quem sabe a língua é o `i18n`: os
 * formatadores só a consomem.
 */
const ETIQUETAS_INTL: Record<string, string> = {
    pt: 'pt-PT',
    en: 'en-GB',
    fr: 'fr-FR',
};

export function etiquetaIntl(): string {
    return ETIQUETAS_INTL[lingua()] ?? 'pt-PT';
}

/**
 * Traduz, e substitui os `:atributos` como o Laravel.
 *
 *   t('Emitir :documento', { documento: 'factura' })
 */
export function t(frase: string, substituicoes?: Record<string, string | number>): string {
    let saida = dicionario[frase] ?? frase;

    if (substituicoes) {
        for (const [chave, valor] of porComprimento(substituicoes)) {
            saida = saida.split(`:${chave}`).join(String(valor));
        }
    }

    return saida;
}

/**
 * O MAIS COMPRIDO PRIMEIRO — é o que o Laravel faz, e por boa razão.
 *
 * Com `:pagina` e `:paginas` na mesma frase, substituir pela ordem em que
 * foram escritos deixava «Página 3 des» — o `:pagina` comeu o princípio do
 * `:paginas` e sobrou o «s».
 */
function porComprimento<T>(substituicoes: Record<string, T>): Array<[string, T]> {
    return Object.entries(substituicoes).sort(([a], [b]) => b.length - a.length);
}

/**
 * Como o `t()`, mas o que substitui pode ser JSX.
 *
 * «Vai apagar o lote **L-2026-04**. Não há volta.» — a frase é UMA chave (que
 * é o que o dicionário precisa), e o valor continua a poder vir em negrito.
 * Sem isto, embrulhar uma frase obrigava a escolher entre traduzi-la e
 * destacar o nome lá dentro.
 *
 * Devolve os pedaços; usa-se dentro de JSX como qualquer lista.
 */
export function tPartes(
    frase: string,
    substituicoes: Record<string, ReactNode>,
): ReactNode[] {
    let pedacos: ReactNode[] = [t(frase)];

    for (const [chave, valor] of porComprimento(substituicoes)) {
        const marca = `:${chave}`;

        pedacos = pedacos.flatMap((pedaco) => {
            if (typeof pedaco !== 'string' || !pedaco.includes(marca)) {
                return [pedaco];
            }

            const partes = pedaco.split(marca);

            return partes.flatMap((p, i) => (i === 0 ? [p] : [valor, p]));
        });
    }

    /*
     * A CHAVE DE CADA PEDAÇO.
     *
     * É uma lista dentro de JSX: sem `key`, o React avisa na consola por cada
     * frase destas — e os ensaios do browser exigem consola limpa. A posição
     * serve de chave porque a lista só muda quando a frase muda.
     */
    return pedacos
        .filter((p) => p !== '')
        .map((p, i) => (typeof p === 'string' ? p : createElement(Fragment, { key: i }, p)));
}

/**
 * Vai buscar o dicionário uma vez, antes de os ecrãs montarem.
 *
 * Guarda-se no `localStorage` pela marca da versão: numa rede fraca, 200 KB a
 * cada página era mais tempo de espera do que o ecrã inteiro. A marca muda
 * quando o ficheiro muda, portanto uma tradução nova aparece no primeiro
 * carregamento a seguir ao deploy.
 */
export async function carregarDicionario(): Promise<void> {
    const url = window.__reactDicionarioUrl;

    if (!url || lingua() === 'pt') {
        return;
    }

    const chave = `sos.dicionario.${url}`;

    try {
        const guardado = localStorage.getItem(chave);

        if (guardado) {
            dicionario = JSON.parse(guardado) as Dicionario;

            return;
        }
    } catch {
        // localStorage cheio, privado ou desligado: segue-se pela rede.
    }

    try {
        const resposta = await fetch(url, { headers: { Accept: 'application/json' } });

        if (!resposta.ok) return;

        dicionario = (await resposta.json()) as Dicionario;

        try {
            // Só esta versão fica: as antigas não servem para nada.
            for (const k of Object.keys(localStorage)) {
                if (k.startsWith('sos.dicionario.') && k !== chave) localStorage.removeItem(k);
            }

            localStorage.setItem(chave, JSON.stringify(dicionario));
        } catch {
            // Não caber é feio, não é grave: traduz-se na mesma nesta página.
        }
    } catch {
        // Sem rede fica o português, que é melhor do que um ecrã em branco.
    }
}
