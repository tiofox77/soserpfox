import { t } from '@/i18n';

/**
 * UMA FOTOGRAFIA DE TELEMÓVEL NÃO CABE NUM ARTIGO — e não tem de caber.
 *
 * A câmara de um telemóvel faz ficheiros de 3 a 8 MB, com 4000 píxeis de
 * lado. O servidor aceita até 5 MB, mas o PHP do alojamento pode cortar antes
 * (o `upload_max_filesize` de fábrica é 2 MB) e a imagem morria sem se perceber
 * porquê. Para uma ficha de artigo, 1600 píxeis chegam e sobram: reduz-se AQUI,
 * no browser, antes de subir — fica com umas centenas de KB e sobe depressa
 * mesmo com rede fraca.
 *
 * O que já é pequeno e de um formato que o servidor aceita vai como está (um
 * GIF animado continua animado).
 */

/** Os formatos que o servidor guarda. */
export const TIPOS_ACEITES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

/** Até aqui um ficheiro sobe tal como está. */
export const TECTO_SEM_REDUZIR = 1.5 * 1024 * 1024;

/** O lado maior de uma imagem reduzida. */
export const LADO_MAXIMO = 1600;

export class ImagemRecusada extends Error {}

export type Decisao = 'tal-como-esta' | 'reduzir' | 'recusar';

/** O que fazer com um ficheiro, só pelo tipo e pelo tamanho. */
export function decidir(tipo: string, tamanho: number, nome = ''): Decisao {
    const tipoCerto = tipo.toLowerCase();
    const heic = /heic|heif/.test(tipoCerto) || /\.(heic|heif)$/i.test(nome);

    if (!heic && !tipoCerto.startsWith('image/')) return 'recusar';
    if (tipoCerto === 'image/svg+xml') return 'recusar';
    if (TIPOS_ACEITES.includes(tipoCerto) && tamanho <= TECTO_SEM_REDUZIR) return 'tal-como-esta';

    return 'reduzir';
}

/** As medidas reduzidas, com o lado maior em `lado` e a proporção de sempre. */
export function medidasReduzidas(largura: number, altura: number, lado = LADO_MAXIMO): { largura: number; altura: number } {
    const maior = Math.max(largura, altura);
    if (maior <= lado) return { largura, altura };
    const f = lado / maior;

    return { largura: Math.round(largura * f), altura: Math.round(altura * f) };
}

export const tamanhoLegivel = (bytes: number) =>
    bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toLocaleString('pt-PT', { maximumFractionDigits: 1 })} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;

async function abrir(f: File): Promise<{ largura: number; altura: number; desenhar: (c: CanvasRenderingContext2D, l: number, a: number) => void; fechar: () => void }> {
    if (typeof createImageBitmap === 'function') {
        try {
            const b = await createImageBitmap(f);
            return { largura: b.width, altura: b.height, desenhar: (c, l, a) => c.drawImage(b, 0, 0, l, a), fechar: () => b.close() };
        } catch {
            /* tenta pelo <img> */
        }
    }

    const url = URL.createObjectURL(f);
    try {
        const img = await new Promise<HTMLImageElement>((ok, falha) => {
            const i = new Image();
            i.onload = () => ok(i);
            i.onerror = () => falha(new Error('decode'));
            i.src = url;
        });
        return { largura: img.naturalWidth, altura: img.naturalHeight, desenhar: (c, l, a) => c.drawImage(img, 0, 0, l, a), fechar: () => URL.revokeObjectURL(url) };
    } catch {
        URL.revokeObjectURL(url);
        throw new ImagemRecusada(
            /\.(heic|heif)$/i.test(f.name) || /heic|heif/i.test(f.type)
                ? t('As fotografias HEIC do iPhone não abrem neste browser. No iPhone, em Definições › Câmara › Formatos, escolha «Mais compatível», ou envie a fotografia em JPG.')
                : t('Não foi possível abrir «:nome» como imagem.', { nome: f.name }),
        );
    }
}

/** Prepara um ficheiro para subir: recusa o que não é imagem e reduz o que é grande. */
export async function prepararImagem(f: File): Promise<File> {
    const decisao = decidir(f.type, f.size, f.name);

    if (decisao === 'recusar') {
        throw new ImagemRecusada(t('«:nome» não é uma imagem. Use JPG, PNG, GIF ou WebP.', { nome: f.name }));
    }
    if (decisao === 'tal-como-esta') return f;

    const imagem = await abrir(f);
    try {
        const { largura, altura } = medidasReduzidas(imagem.largura, imagem.altura);
        const tela = document.createElement('canvas');
        tela.width = largura;
        tela.height = altura;
        const c = tela.getContext('2d');
        if (!c) return f;

        // Fundo branco: um PNG transparente passado a JPEG ficava com fundo preto.
        c.fillStyle = '#ffffff';
        c.fillRect(0, 0, largura, altura);
        imagem.desenhar(c, largura, altura);

        const blob = await new Promise<Blob | null>((ok) => tela.toBlob(ok, 'image/jpeg', 0.85));
        if (!blob) return f;

        const nome = f.name.replace(/\.[^.]+$/, '') + '.jpg';

        return new File([blob], nome, { type: 'image/jpeg', lastModified: Date.now() });
    } finally {
        imagem.fechar();
    }
}
