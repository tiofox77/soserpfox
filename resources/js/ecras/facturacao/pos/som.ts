/**
 * O SOM DO BALCÃO.
 *
 * Um bip a confirmar que o artigo entrou é o que permite ao operador não
 * olhar para o ecrã enquanto passa a mercadoria — e é a diferença entre
 * confiar no leitor de código de barras e ter de conferir cada leitura.
 *
 * Vem do ecrã de sempre (`partials/scripts.blade.php`), com a mesma chave de
 * localStorage: quem o desligou lá continua com ele desligado aqui.
 *
 * NÃO CARREGA FICHEIRO NENHUM. É um oscilador do próprio browser — um som de
 * balcão que dependesse da rede era um som que falta precisamente quando a
 * rede falta, que é quando mais faz falta.
 */

type Momento = 'juntar' | 'tirar' | 'erro' | 'venda';

/** As notas de cada momento, em hertz, e quanto duram. */
const NOTAS: Record<Momento, { hz: number[]; ms: number }> = {
    juntar: { hz: [880], ms: 60 },
    tirar: { hz: [440], ms: 60 },
    erro: { hz: [220, 180], ms: 140 },
    // A venda fechada merece três notas a subir: ouve-se do outro lado do balcão.
    venda: { hz: [660, 880, 1320], ms: 90 },
};

let contexto: AudioContext | null = null;

export function somLigado(): boolean {
    try {
        return localStorage.getItem('pos_sound_enabled') !== 'false';
    } catch {
        return true;
    }
}

export function alternarSom(): boolean {
    const novo = !somLigado();

    try {
        localStorage.setItem('pos_sound_enabled', novo ? 'true' : 'false');
    } catch {
        /* janela privada: fica ligado nesta sessão e mais nada */
    }

    return novo;
}

export function somDoBalcao(momento: Momento): void {
    if (!somLigado()) return;

    try {
        contexto ??= new (window.AudioContext ?? (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext)();

        const { hz, ms } = NOTAS[momento];

        hz.forEach((f, i) => {
            const osc = contexto!.createOscillator();
            const ganho = contexto!.createGain();

            osc.type = 'sine';
            osc.frequency.value = f;

            // Uma rampa em vez de um corte seco: um corte estala nas colunas
            // baratas que um balcão costuma ter.
            const inicio = contexto!.currentTime + (i * ms) / 1000;
            const fim = inicio + ms / 1000;

            ganho.gain.setValueAtTime(0.0001, inicio);
            ganho.gain.exponentialRampToValueAtTime(0.2, inicio + 0.01);
            ganho.gain.exponentialRampToValueAtTime(0.0001, fim);

            osc.connect(ganho).connect(contexto!.destination);
            osc.start(inicio);
            osc.stop(fim);
        });
    } catch {
        /* sem áudio no aparelho: a venda faz-se na mesma, em silêncio */
    }
}
