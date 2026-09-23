/**
 * A DESCRIÇÃO FORMATADA DAS LINHAS DE PROFORMAS E ORÇAMENTOS — as contas
 * pequenas que o ecrã faz sem carregar o editor.
 *
 * A mesma regra do servidor (App\Services\Invoicing\DescricaoRica): uma
 * descrição com etiquetas de formatação é HTML; sem elas é o texto de sempre,
 * e as mudanças de linha contam.
 */

// Sem espaço depois do «<»: numa etiqueta nunca há, e aceitá-lo fazia de «A < B» um <b>.
const ETIQUETAS = /<\/?(p|br|strong|b|em|i|u|s|h[1-6]|ul|ol|li|blockquote|hr|table|div|span)(\s|\/?>)/i;

export function eRica(texto: string | null | undefined): boolean {
    return !!texto && ETIQUETAS.test(texto);
}

/**
 * O texto que se vê na linha, sem formatação. O HTML é lido por um
 * DOMParser (que não corre scripts nem pede imagens) e nunca é inserido na
 * página.
 */
export function textoDaDescricao(texto: string | null | undefined): string {
    if (!texto) return '';
    if (!eRica(texto)) return texto.trim();

    const doc = new DOMParser().parseFromString(texto, 'text/html');
    doc.querySelectorAll('br').forEach((br) => br.replaceWith('\n'));
    doc.querySelectorAll('p, h2, h3, h4, li, blockquote, tr').forEach((el) => el.append('\n'));
    doc.querySelectorAll('li').forEach((el) => el.prepend('• '));

    return (doc.body.textContent ?? '')
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean)
        .join('\n');
}

/** Um texto antigo passa a parágrafos, para o editor o abrir como foi escrito. */
export function paraHtml(texto: string | null | undefined): string {
    if (!texto) return '';
    if (eRica(texto)) return texto;

    const escapar = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    return texto
        .split(/\r?\n/)
        .map((l) => `<p>${escapar(l)}</p>`)
        .join('');
}
