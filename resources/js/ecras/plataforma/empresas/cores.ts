import type { Cor } from '@/ui/tokens';

/**
 * A cor do estado da subscrição, tal como `EstadoDaSubscricao` a devolve.
 *
 * O servidor fala nomes de paleta do Tailwind (`emerald`, `amber`…), porque o
 * Blade os usava para montar classes. O React fala papéis: esta é a tradução,
 * num sítio só, para os cinco nomes que o serviço sabe devolver.
 */
export function corDaSubscricao(cor: string): Cor {
    switch (cor) {
        case 'emerald':
        case 'green':
            return 'bom';
        case 'amber':
        case 'orange':
            return 'aviso';
        case 'red':
            return 'perigo';
        case 'blue':
            return 'primaria';
        default:
            return 'neutra';
    }
}
