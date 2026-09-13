import { t } from '@/i18n';

/**
 * O dinheiro como a carta o escreve: «5.500,00». Fixo, e não pela língua do
 * browser — o mesmo número tem de aparecer no ecrã e na mensagem que chega ao
 * restaurante, seja qual for o telemóvel de quem pede.
 */
export function kwanzasDaCarta(valor: number): string {
    const [inteiro = '0', decimal = '00'] = Math.abs(valor).toFixed(2).split('.');

    return `${valor < 0 ? '-' : ''}${inteiro.replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${decimal}`;
}

export type LinhaEscolhida = { nome: string; quantidade: number; preco: number | null };

/**
 * A MENSAGEM DE WHATSAPP, pronta a enviar.
 *
 * Leva a MESA em primeiro lugar. É a informação que mais falta nestes pedidos —
 * quem recebe fica com uma lista de pratos e ninguém sabe para onde vão — e é
 * precisamente a que o QR da mesa já sabe.
 */
export function mensagemDoWhatsapp({ numero, casa, mesa, linhas, comPrecos }: {
    numero: string;
    casa: string | null;
    mesa: string | null;
    linhas: LinhaEscolhida[];
    comPrecos: boolean;
}): string {
    const texto = [t('Pedido por :casa', { casa: casa || t('Restaurante') })];

    if (mesa) texto.push(mesa);

    texto.push('');

    let total = 0;

    for (const l of linhas) {
        if (comPrecos && l.preco !== null) {
            total += l.preco * l.quantidade;
            texto.push(`${l.quantidade}x ${l.nome} — ${kwanzasDaCarta(l.preco)} Kz`);
        } else {
            texto.push(`${l.quantidade}x ${l.nome}`);
        }
    }

    if (comPrecos && total > 0) {
        texto.push('', t('Total: :valor Kz', { valor: kwanzasDaCarta(total) }));
    }

    return `https://wa.me/${numero}?text=${encodeURIComponent(texto.join('\n'))}`;
}
