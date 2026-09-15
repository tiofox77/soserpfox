import { forwardRef, useEffect, useImperativeHandle, useRef, useState, type PointerEvent as EventoDoPonteiro } from 'react';

import { t } from '@/i18n';
import { RAIO_GRANDE, cls } from './tokens';

/**
 * O QUADRO DE ASSINAR — com o dedo, a caneta ou o rato (15/09/2026).
 *
 * Nasceu no check-in da viatura e passou a peça comum quando o orçamento da
 * oficina também se assina. Quem o usa pede a imagem com `ref.imagem()` (um PNG
 * de fundo branco — um PNG transparente sai preto em alguns leitores de PDF) e
 * sabe se já há risco por `aoRiscar`.
 */
export type QuadroDeAssinaturaRef = { imagem: () => string; limpar: () => void };

export const QuadroDeAssinatura = forwardRef<QuadroDeAssinaturaRef, { aoRiscar: (riscado: boolean) => void; altura?: string }>(
    function QuadroDeAssinatura({ aoRiscar, altura = 'h-48' }, ref) {
        const tela = useRef<HTMLCanvasElement>(null);
        const aDesenhar = useRef(false);
        const ultimo = useRef<{ x: number; y: number } | null>(null);
        const [riscado, porRiscado] = useState(false);

        const preparar = () => {
            const c = tela.current;
            if (!c) return;
            const r = c.getBoundingClientRect();
            if (r.width === 0) return;
            const escala = window.devicePixelRatio || 1;
            c.width = Math.round(r.width * escala);
            c.height = Math.round(r.height * escala);
            const g = c.getContext('2d');
            if (!g) return;
            g.scale(escala, escala);
            g.lineCap = 'round';
            g.lineJoin = 'round';
            g.lineWidth = 2.4;
            g.strokeStyle = '#0f172a';
        };

        useEffect(() => {
            // Dentro de um <dialog> a janela anima a entrada: mede-se o quadro depois de ele ter o tamanho final.
            const tempo = window.setTimeout(preparar, 260);
            return () => window.clearTimeout(tempo);
        }, []);

        const mudarRiscado = (valor: boolean) => { porRiscado(valor); aoRiscar(valor); };

        useImperativeHandle(ref, () => ({
            imagem: () => {
                const origem = tela.current as HTMLCanvasElement;
                const copia = document.createElement('canvas');
                copia.width = origem.width;
                copia.height = origem.height;
                const g = copia.getContext('2d') as CanvasRenderingContext2D;
                g.fillStyle = '#ffffff';
                g.fillRect(0, 0, copia.width, copia.height);
                g.drawImage(origem, 0, 0);
                return copia.toDataURL('image/png');
            },
            limpar: () => {
                const c = tela.current;
                c?.getContext('2d')?.clearRect(0, 0, c.width, c.height);
                mudarRiscado(false);
            },
        }));

        const ponto = (e: EventoDoPonteiro<HTMLCanvasElement>) => {
            const r = e.currentTarget.getBoundingClientRect();
            return { x: e.clientX - r.left, y: e.clientY - r.top };
        };

        const comecar = (e: EventoDoPonteiro<HTMLCanvasElement>) => {
            if (tela.current && tela.current.width === 0) preparar();
            e.currentTarget.setPointerCapture(e.pointerId);
            aDesenhar.current = true;
            ultimo.current = ponto(e);
        };
        const mover = (e: EventoDoPonteiro<HTMLCanvasElement>) => {
            if (!aDesenhar.current || !ultimo.current) return;
            const g = e.currentTarget.getContext('2d');
            const p = ponto(e);
            if (!g) return;
            g.beginPath();
            g.moveTo(ultimo.current.x, ultimo.current.y);
            g.lineTo(p.x, p.y);
            g.stroke();
            ultimo.current = p;
            if (!riscado) mudarRiscado(true);
        };
        const parar = () => { aDesenhar.current = false; ultimo.current = null; };

        return (
            <div className="relative">
                <canvas ref={tela} onPointerDown={comecar} onPointerMove={mover} onPointerUp={parar} onPointerLeave={parar} onPointerCancel={parar}
                    aria-label={t('Quadro para assinar')}
                    className={cls('block w-full touch-none border-2 border-dashed bg-white transition-colors duration-300', altura, RAIO_GRANDE, riscado ? 'border-emerald-300' : 'border-slate-300')} />
                {!riscado && (
                    <span className="pointer-events-none absolute inset-0 grid place-items-center text-sm text-slate-400">
                        <span><i className="fas fa-pen-nib mr-1.5" aria-hidden="true" />{t('Assine aqui com o dedo ou com o rato')}</span>
                    </span>
                )}
                <span className="pointer-events-none absolute inset-x-6 bottom-9 border-b border-slate-300" aria-hidden="true" />
            </div>
        );
    },
);
