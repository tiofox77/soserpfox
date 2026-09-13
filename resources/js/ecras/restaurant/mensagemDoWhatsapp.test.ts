import { describe, expect, it } from 'vitest';

import { kwanzasDaCarta, mensagemDoWhatsapp } from './mensagemDoWhatsapp';

/**
 * A MENSAGEM QUE A CARTA MANDA PELO WHATSAPP.
 *
 * Era um ensaio do componente Livewire; a mensagem passou a ser montada no
 * telemóvel, e as regras vieram com ela.
 */
describe('a mensagem de WhatsApp da carta', () => {
    const texto = (link: string) => decodeURIComponent(link.split('?text=')[1] ?? '');

    it('vai para o número da casa, com a mesa à cabeça e os artigos', () => {
        const link = mensagemDoWhatsapp({
            numero: '244900111222',
            casa: 'Casa de Teste',
            mesa: 'Mesa 12',
            linhas: [{ nome: 'Muamba de Galinha', quantidade: 2, preco: 5500 }],
            comPrecos: true,
        });

        expect(link.startsWith('https://wa.me/244900111222?text=')).toBe(true);

        const linhas = texto(link).split('\n');
        expect(linhas[0]).toBe('Pedido por Casa de Teste');
        expect(linhas[1]).toBe('Mesa 12');
        expect(texto(link)).toContain('2x Muamba de Galinha — 5.500,00 Kz');
        expect(texto(link)).toContain('Total: 11.000,00 Kz');
    });

    it('com os preços escondidos não fala de dinheiro', () => {
        const link = mensagemDoWhatsapp({
            numero: '244900111222',
            casa: null,
            mesa: null,
            linhas: [{ nome: 'Muamba de Galinha', quantidade: 1, preco: null }],
            comPrecos: false,
        });

        expect(texto(link)).toBe('Pedido por Restaurante\n\n1x Muamba de Galinha');
    });

    it('escreve o dinheiro como a carta: ponto nos milhares, vírgula nas casas', () => {
        expect(kwanzasDaCarta(5500)).toBe('5.500,00');
        expect(kwanzasDaCarta(1234567.5)).toBe('1.234.567,50');
        expect(kwanzasDaCarta(99)).toBe('99,00');
    });
});
