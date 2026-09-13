import { useEffect, useRef, useState } from 'react';

import { t } from '@/i18n';

import { avisar } from '../../ui/Dialogos';
import { Camada } from './Camada';
import { FORMATOS_DE_CODIGO, vibrar } from './comum';

/**
 * O LEITOR DE CÓDIGO DE BARRAS — pela câmara e pela pistola.
 *
 * A câmara usa o `BarcodeDetector` do navegador (Chrome no Android). Não há
 * biblioteca de reserva: offline não há onde a ir buscar, e uma de 300 KB no
 * pacote pesava em todos os aparelhos por causa dos poucos que não o têm.
 * Onde não existe, diz-se isso — e a pistola (que escreve como um teclado)
 * continua a funcionar.
 */

interface DetectorDeCodigos {
    detect(fonte: CanvasImageSource): Promise<Array<{ rawValue: string }>>;
}

type ConstrutorDoDetector = new (opcoes?: { formats?: string[] }) => DetectorDeCodigos;

function construtorDoDetector(): ConstrutorDoDetector | null {
    return (window as unknown as { BarcodeDetector?: ConstrutorDoDetector }).BarcodeDetector ?? null;
}

export function camaraSuportada(): boolean {
    return construtorDoDetector() !== null && !!navigator.mediaDevices?.getUserMedia;
}

/** O aviso de quando o aparelho não lê pela câmara — com o que fazer, e não só o «não dá». */
export function avisarCamaraSemSuporte(): void {
    avisar(t('Use Chrome no Android para esta funcionalidade.'), 'aviso', {
        titulo: t('Leitor de código de barras por câmara não suportado neste dispositivo/navegador.'),
        duracao: 8000,
    });
}

/**
 * A câmara aberta por cima de tudo, até ler um código.
 *
 * Mostra-se o próprio vídeo (o ecrã antigo copiava cada fotograma para um
 * canvas de 320×240, que se via aos saltos) e procura-se de 90 em 90 ms, e não
 * a cada fotograma: a 60 procuras por segundo um telemóvel barato aquece e a
 * bateria vai-se num turno.
 */
export function LeitorPorCamara({ aoLer, aoFechar }: { aoLer: (codigo: string) => void; aoFechar: () => void }) {
    const video = useRef<HTMLVideoElement>(null);
    const lerActual = useRef(aoLer);
    lerActual.current = aoLer;
    const fecharActual = useRef(aoFechar);
    fecharActual.current = aoFechar;
    const [pronto, setPronto] = useState(false);

    useEffect(() => {
        const Detector = construtorDoDetector();

        if (!Detector) {
            avisarCamaraSemSuporte();
            fecharActual.current();

            return;
        }

        let vivo = true;
        let fluxo: MediaStream | null = null;
        let temporizador: ReturnType<typeof setTimeout> | undefined;

        const parar = () => {
            fluxo?.getTracks().forEach((faixa) => faixa.stop());
            fluxo = null;
        };

        void (async () => {
            try {
                fluxo = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
                });

                const v = video.current;
                if (!vivo || !v) { parar(); return; }

                v.srcObject = fluxo;
                v.setAttribute('playsinline', 'true');
                await v.play();
                if (!vivo) return;
                setPronto(true);

                const detector = new Detector({ formats: FORMATOS_DE_CODIGO });

                const procurar = async () => {
                    if (!vivo) return;

                    try {
                        const achados = await detector.detect(v);
                        const codigo = achados[0]?.rawValue;

                        if (codigo && vivo) {
                            vivo = false;
                            parar();
                            vibrar(100);
                            lerActual.current(codigo);
                            fecharActual.current();

                            return;
                        }
                    } catch {
                        // Um fotograma ilegível não é erro: tenta-se o seguinte.
                    }

                    temporizador = setTimeout(() => requestAnimationFrame(() => void procurar()), 90);
                };

                void procurar();
            } catch (err) {
                parar();
                if (!vivo) return;
                console.error('[POS] Barcode scanner:', err);
                avisar(t('Não foi possível aceder à câmara: :erro', { erro: (err as Error).message }), 'erro');
                fecharActual.current();
            }
        })();

        const tecla = (e: KeyboardEvent) => { if (e.key === 'Escape') fecharActual.current(); };
        window.addEventListener('keydown', tecla);

        return () => {
            vivo = false;
            clearTimeout(temporizador);
            window.removeEventListener('keydown', tecla);
            parar();
        };
    }, []);

    return (
        <Camada>
            <div role="dialog" aria-modal="true" aria-label={t('Ler código de barras com câmara')}
                 className="pwa-fundo fixed inset-0 z-[150] bg-slate-950/90 backdrop-blur-sm flex flex-col items-center justify-center p-4">
                <p className="pwa-desce text-white text-sm font-bold mb-3 flex items-center gap-2">
                    <i className="fas fa-barcode text-emerald-400" aria-hidden="true" />
                    {t('Aponte para o código de barras')}
                </p>

                <div className="pwa-cresce relative w-[320px] max-w-[88vw] aspect-[4/3] rounded-2xl overflow-hidden border-4 border-emerald-500 shadow-2xl bg-black">
                    <video ref={video} muted playsInline className="w-full h-full object-cover" />

                    {/* A mira: os quatro cantos e a linha que corre — diz «a procurar» sem texto. */}
                    <span className="absolute top-3 left-3 w-6 h-6 border-t-4 border-l-4 border-white/80 rounded-tl-lg" aria-hidden="true" />
                    <span className="absolute top-3 right-3 w-6 h-6 border-t-4 border-r-4 border-white/80 rounded-tr-lg" aria-hidden="true" />
                    <span className="absolute bottom-3 left-3 w-6 h-6 border-b-4 border-l-4 border-white/80 rounded-bl-lg" aria-hidden="true" />
                    <span className="absolute bottom-3 right-3 w-6 h-6 border-b-4 border-r-4 border-white/80 rounded-br-lg" aria-hidden="true" />
                    <span className="absolute inset-x-6 top-1/2 h-0.5 bg-red-500 shadow-[0_0_12px_2px_rgba(239,68,68,.7)] animate-pulse" aria-hidden="true" />

                    {!pronto && (
                        <span className="absolute inset-0 flex items-center justify-center text-white/80">
                            <i className="fas fa-spinner fa-spin text-2xl" aria-hidden="true" />
                        </span>
                    )}
                </div>

                <button type="button" onClick={() => fecharActual.current()}
                        className="pwa-toque mt-5 px-7 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl font-bold text-sm shadow-lg">
                    <i className="fas fa-xmark mr-1.5" aria-hidden="true" />{t('Cancelar')}
                </button>
            </div>
        </Camada>
    );
}

/**
 * A PISTOLA DE CÓDIGO DE BARRAS SEM TER DE TOCAR NA PESQUISA.
 *
 * A pistola escreve como um teclado e acaba com Enter. No ecrã antigo só
 * funcionava com o cursor na caixa de pesquisa — e depois de tocar num cartão
 * o cursor já lá não está: o código lido perdia-se, ou pior, o Enter carregava
 * outra vez no último cartão tocado e o artigo entrava a dobrar.
 *
 * Reconhece-se a pistola pela VELOCIDADE: quatro ou mais teclas a menos de
 * 80 ms umas das outras, acabadas em Enter. Ninguém escreve assim à mão. Com um
 * campo em foco ou uma janela aberta não se faz nada — aí as teclas são de quem
 * está a escrever.
 */
export function useLeitorDeTeclado(aoLer: (codigo: string) => void, ligado = true): void {
    const actual = useRef(aoLer);
    actual.current = aoLer;

    useEffect(() => {
        if (!ligado) return;

        let buffer = '';
        let ultima = 0;

        const tecla = (e: KeyboardEvent) => {
            if (e.ctrlKey || e.altKey || e.metaKey) return;

            const alvo = e.target as HTMLElement | null;
            if (alvo && (alvo.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(alvo.tagName))) return;
            if (document.querySelector('[role="dialog"], [role="alertdialog"]')) return;

            const agora = e.timeStamp || performance.now();
            if (agora - ultima > 80) buffer = '';
            ultima = agora;

            if (e.key === 'Enter') {
                if (buffer.length >= 4) {
                    // Sem isto o Enter da pistola carregava no botão que tivesse o foco.
                    e.preventDefault();
                    actual.current(buffer);
                }
                buffer = '';

                return;
            }

            if (e.key.length === 1) buffer += e.key;
        };

        window.addEventListener('keydown', tecla);

        return () => window.removeEventListener('keydown', tecla);
    }, [ligado]);
}
