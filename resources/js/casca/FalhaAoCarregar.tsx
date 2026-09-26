/**
 * QUANDO O PEDAÇO DE UM ECRÃ NÃO CHEGA (26/09/2026).
 *
 * Cada ecrã é um `import()` à parte. Se a rede falha a meio (ligação lenta,
 * dados móveis a cair, o service worker a responder 503 sem rede), a promessa
 * rejeita — e não havia `.catch`: a página ficava no esqueleto para sempre, e
 * quem se estava a registar ia-se embora. Foi o que o registo relatou três
 * vezes («Failed to fetch dynamically imported module»).
 *
 * A RECUPERAÇÃO, por esta ordem e sem ciclos:
 *  1. Tenta outra vez na mesma página, duas vezes, com espera a crescer — a
 *     rede muitas vezes volta em segundos.
 *  2. Recarrega a página UMA vez sozinha, e só se não o fez para este ecrã no
 *     último minuto (a marca fica no sessionStorage). Uma versão nova
 *     publicada entretanto resolve-se assim: a página nova traz os pedaços
 *     novos. Os pedaços antigos continuam no servidor, por isso uma página
 *     aberta antes do envio também os encontra.
 *  3. Depois disso, mostra o que se passa e um botão — nunca recarrega em
 *     ciclo.
 *
 * O que já se escreveu num formulário não se perde com o recarregamento: o
 * registo guarda os campos no servidor a cada alteração. A palavra-passe NÃO
 * se guarda em lado nenhum do browser — volta a pedir-se.
 */

import { relatarErro } from '@/casca/relatarErro';
import { t } from '@/i18n';

const ESPERAS_MS = [800, 2000];
const UM_MINUTO = 60_000;

const chaveDaRecarga = (ecra: string) => `sos-recarga:${ecra}`;

const esperar = (ms: number) => new Promise((resolver) => window.setTimeout(resolver, ms));

/** Lê a marca da última recarga automática; um browser sem sessionStorage conta como «já recarregou». */
function recarregouHaPouco(ecra: string): boolean {
    try {
        const marca = Number(window.sessionStorage.getItem(chaveDaRecarga(ecra)) ?? 0);

        return Date.now() - marca < UM_MINUTO;
    } catch {
        return true;
    }
}

function marcarRecarga(ecra: string): boolean {
    try {
        window.sessionStorage.setItem(chaveDaRecarga(ecra), String(Date.now()));

        return true;
    } catch {
        return false;
    }
}

/**
 * Carrega o pedaço de um ecrã com novas tentativas. Se nada resultar, pode
 * recarregar a página uma vez (`recarregar`), e depois rejeita.
 */
export async function carregarComRecuperacao<T>(
    ecra: string,
    carregar: () => Promise<T>,
    opcoes: { esperas?: number[]; recarregar?: () => void } = {},
): Promise<T> {
    const esperas = opcoes.esperas ?? ESPERAS_MS;
    let ultimo: unknown;

    for (let tentativa = 0; tentativa <= esperas.length; tentativa++) {
        try {
            const modulo = await carregar();

            // Correu bem: a próxima falha deste ecrã volta a poder recarregar.
            try {
                window.sessionStorage.removeItem(chaveDaRecarga(ecra));
            } catch {
                // sem sessionStorage não há marca a limpar
            }

            return modulo;
        } catch (erro) {
            ultimo = erro;

            if (tentativa < esperas.length) {
                await esperar(esperas[tentativa] ?? 0);
            }
        }
    }

    const mensagem = ultimo instanceof Error ? ultimo.message : String(ultimo);
    relatarErro({ mensagem: `Pedaço do ecrã não carregou: ${mensagem}`, pilha: ultimo instanceof Error ? ultimo.stack : null, ecra, origem: 'promessa' });

    // Uma recarga automática, e só uma por minuto: nunca em ciclo.
    if (!recarregouHaPouco(ecra) && marcarRecarga(ecra)) {
        (opcoes.recarregar ?? (() => window.location.reload()))();
    }

    throw ultimo;
}

/** O que se mostra quando o ecrã não chegou mesmo assim. */
export function FalhaAoCarregar({ discreto = false, aoTentar }: { discreto?: boolean; aoTentar?: () => void }) {
    const tentar = aoTentar ?? (() => window.location.reload());

    if (discreto) {
        return (
            <button
                type="button"
                onClick={tentar}
                title={t('Não carregou. Tocar para tentar de novo.')}
                aria-label={t('Não carregou. Tocar para tentar de novo.')}
                className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
            >
                <i className="fas fa-rotate-right" aria-hidden="true" />
            </button>
        );
    }

    return (
        <div role="alert" className="mx-auto my-8 max-w-md rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center">
            <i className="fas fa-wifi mb-3 text-2xl text-amber-600" aria-hidden="true" />
            <h2 className="mb-2 text-lg font-bold text-amber-900">
                <span>{t('Esta página não carregou')}</span>
            </h2>
            <p className="mb-4 text-sm text-amber-800">
                <span>
                    {t('A ligação pode estar lenta, ou saiu entretanto uma versão nova. O que já escreveu fica guardado — só a palavra-passe, se a tinha escrito, volta a ser pedida.')}
                </span>
            </p>
            <button
                type="button"
                onClick={tentar}
                className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-700"
            >
                <i className="fas fa-rotate-right" aria-hidden="true" />
                <span>{t('Tentar de novo')}</span>
            </button>
        </div>
    );
}
