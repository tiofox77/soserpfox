import { describe, expect, it } from 'vitest';

import { decidir, medidasReduzidas, prepararImagem, TECTO_SEM_REDUZIR } from './prepararImagem';

describe('preparar a imagem antes de subir', () => {
    it('o que é pequeno e de um formato aceite sobe tal como está', () => {
        expect(decidir('image/jpeg', 300_000)).toBe('tal-como-esta');
        expect(decidir('image/png', TECTO_SEM_REDUZIR)).toBe('tal-como-esta');
        expect(decidir('image/gif', 900_000)).toBe('tal-como-esta');
        expect(decidir('image/webp', 100_000)).toBe('tal-como-esta');
    });

    it('a fotografia do telemóvel reduz-se, e o HEIC tenta-se converter', () => {
        expect(decidir('image/jpeg', 6 * 1024 * 1024)).toBe('reduzir');
        expect(decidir('image/heic', 2_000_000)).toBe('reduzir');
        expect(decidir('', 2_000_000, 'IMG_0001.HEIC')).toBe('reduzir');
        expect(decidir('image/bmp', 50_000)).toBe('reduzir');
    });

    it('o que não é imagem (e o SVG) é recusado', () => {
        expect(decidir('application/pdf', 1000)).toBe('recusar');
        expect(decidir('image/svg+xml', 1000)).toBe('recusar');
        expect(decidir('', 1000, 'lista.xlsx')).toBe('recusar');
    });

    it('reduz para 1600 no lado maior, com a proporção de sempre', () => {
        expect(medidasReduzidas(4000, 3000)).toEqual({ largura: 1600, altura: 1200 });
        expect(medidasReduzidas(3000, 4000)).toEqual({ largura: 1200, altura: 1600 });
        expect(medidasReduzidas(800, 600)).toEqual({ largura: 800, altura: 600 });
    });

    it('um PDF é recusado com uma frase que se percebe', async () => {
        await expect(prepararImagem(new File(['x'], 'factura.pdf', { type: 'application/pdf' }))).rejects.toThrow(/não é uma imagem/);
    });

    it('uma imagem pequena sai igual, sem passar pela tela', async () => {
        const f = new File([new Uint8Array(1000)], 'foto.png', { type: 'image/png' });
        expect(await prepararImagem(f)).toBe(f);
    });
});
