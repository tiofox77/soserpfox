/**
 * A LARGURA DO TALÃO NESTE APARELHO (28/09/2026).
 *
 * As impressoras de balcão usam rolo de 80 mm; as máquinas portáteis com
 * impressora embutida (a Sunmi V2s e semelhantes) usam 58 mm. Uma empresa pode
 * ter as duas ao mesmo tempo — por isso a largura escolhe-se POR APARELHO e
 * fica guardada nele; sem escolha, vale a da empresa (Definições › Ponto de
 * venda).
 *
 * O PDV e o PWA correm na mesma origem: uma escolha feita num vale para o
 * outro, no mesmo aparelho. O guardar pode falhar (janela privada, dados
 * limpos) — então vale a da empresa, e nada rebenta.
 */

export type LarguraDoTalao = 58 | 80;

const CHAVE = 'sos-largura-talao';

export function larguraDoAparelho(): LarguraDoTalao | null {
    try {
        const v = window.localStorage.getItem(CHAVE);

        return v === '58' ? 58 : v === '80' ? 80 : null;
    } catch {
        return null;
    }
}

export function guardarLarguraDoAparelho(largura: LarguraDoTalao): void {
    try {
        window.localStorage.setItem(CHAVE, String(largura));
    } catch {
        /* sem guardar: fica a da empresa */
    }
}

/** A que vale: a deste aparelho, senão a da empresa, senão 80 mm. */
export function larguraEfectiva(daEmpresa?: number | string | null): LarguraDoTalao {
    return larguraDoAparelho() ?? (String(daEmpresa) === '58' ? 58 : 80);
}

/** A morada de um talão do servidor com a largura pedida. */
export function comLargura(morada: string, largura: LarguraDoTalao): string {
    return `${morada}${morada.includes('?') ? '&' : '?'}largura=${largura}`;
}

/** Só quando o aparelho escolheu: sem escolha, o servidor usa a da empresa. */
export function comLarguraDoAparelho(morada: string): string {
    const l = larguraDoAparelho();

    return l ? comLargura(morada, l) : morada;
}
