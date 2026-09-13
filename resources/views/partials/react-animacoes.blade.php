{{--
    AS ANIMAÇÕES DOS ECRÃS REACT — num sítio só.

    Estavam escritas dentro do `layouts/app`. O painel da plataforma e o portal do
    cliente têm layout próprio e não as tinham: as linhas das tabelas não entravam
    em cascata e os modais abriam de repente, sem ninguém dar por isso. Os ecrãs
    usam `.entra` (com `--i` para o atraso), `.animate-fade-in` e
    `.animate-scale-in`.
--}}
<style>
        /* Modal — Fade In simples (backdrop) */
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        .animate-fade-in { animation: modalFadeIn .18s ease-out both; }

        /* Modal — Scale In (conteúdo) */
        @keyframes modalScaleIn {
            from { opacity: 0; transform: scale(.94) translateY(8px); }
            to   { opacity: 1; transform: scale(1)   translateY(0);   }
        }
        .animate-scale-in { animation: modalScaleIn .22s cubic-bezier(.2,.9,.3,1.2) both; }

        /* Botão — pressão táctil + ripple suave */
        .btn-press { transition: transform .12s ease, box-shadow .15s ease, filter .15s ease; }
        .btn-press:hover { filter: brightness(1.04); }
        .btn-press:active { transform: scale(.96); }

        /* A casca (barra lateral, progresso, avisos) é a mesma nos layouts da
           aplicação e da plataforma: o CSS dela vive aqui, com as animações. */
        /* O LUGAR DA BARRA LATERAL enquanto o React não a desenha. */
        @media (min-width: 768px) {
            .casca-lugar:not(:has(aside)) { width: 5rem; background: linear-gradient(to bottom, #1e3a8a, #1e40af); }
        }
        @media (min-width: 1024px) {
            html:not([data-casca="fechada"]) .casca-lugar:not(:has(aside)) { width: 16rem; }
        }

        /* A barra de progresso de quem muda de página (a peça casca/sistema). */
        .spa-progress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899);
            z-index: 99999;
            transition: width 0.3s ease;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.7);
        }
        .spa-progress.done {
            transition: width 0.1s ease, opacity 0.4s ease 0.1s;
            opacity: 0;
        }

        /* Aviso de canto — a barrinha que se gasta até o aviso sair. */
        @keyframes sosAvisoGasta {
            from { width: 100%; }
            to   { width: 0; }
        }

        /* Linhas de tabela e cartões: entram em cascata, não de repente.
           O atraso vem de uma variável posta na linha (--i), para a segunda
           linha entrar um instante depois da primeira. */
        @keyframes entradaSuave {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0);   }
        }
        .entra {
            animation: entradaSuave .22s ease-out both;
            animation-delay: calc(var(--i, 0) * 22ms);
        }

        /*
           QUEM PEDIU MENOS MOVIMENTO NÃO O LEVA.
           Há quem sinta náuseas com interfaces que saltam, e o sistema
           operativo tem uma definição para o dizer. Isto respeita-a: o ecrã
           continua a funcionar exactamente igual, só que quieto.
        */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
                scroll-behavior: auto !important;
            }
            .entra { animation: none; opacity: 1; transform: none; }
        }
</style>
