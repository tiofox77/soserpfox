/**
 * Legendas visuais para os controlos de acao dos ecra React.
 *
 * O nome acessivel continua no `aria-label`; esta camada mostra o mesmo texto
 * por cima do controlo quando o utilizador passa o rato ou navega por teclado.
 * Funciona por delegacao para tambem cobrir linhas e modais criados depois de
 * uma consulta à API, sem obrigar cada tabela a manter uma implementacao sua.
 */
let iniciado = false;

type Alvo = HTMLElement & { dataset: DOMStringMap };

function alvoDe(origem: EventTarget | null): Alvo | null {
    if (!(origem instanceof Element)) return null;

    const alvo = origem.closest<Alvo>('.ecra-react button, .ecra-react a, .ecra-react [role="button"]');
    if (!alvo || alvo.matches(':disabled, [aria-disabled="true"]')) return null;

    const temIcone = Boolean(alvo.querySelector('i, svg'));
    const temTextoVisivel = Array.from(alvo.childNodes).some(
        (no) => no.nodeType === Node.TEXT_NODE && Boolean(no.textContent?.trim()),
    );

    // Uma acao que ja escreve o seu nome nao precisa de repetir a frase.
    return temIcone && !temTextoVisivel ? alvo : null;
}

function descricao(alvo: Alvo): string {
    return (alvo.dataset.dica || alvo.getAttribute('aria-label') || alvo.getAttribute('title') || '').trim();
}

export function activarDicasDeAccao(): void {
    if (iniciado || typeof document === 'undefined') return;
    iniciado = true;

    // As accoes de tabela eram apenas o tamanho do glifo (algumas tinham
    // 27 px de largura). No telemovel isso torna o PDF, editar ou apagar
    // dificil de acertar. Mantemos o desenho compacto, mas damos a cada
    // icone uma area tactil minima de 36 x 36 px em todos os ecras React.
    const estilo = document.createElement('style');
    estilo.id = 'accoes-react-estilo-global';
    estilo.textContent = `
        .ecra-react :is(button, a)[aria-label]:has(> i:only-child),
        .ecra-react :is(button, a)[title]:has(> i:only-child) {
            min-width: 36px;
            min-height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    `;
    document.head.appendChild(estilo);

    const dica = document.createElement('div');
    dica.id = 'dica-global-react';
    dica.setAttribute('role', 'tooltip');
    dica.setAttribute('aria-hidden', 'true');
    Object.assign(dica.style, {
        position: 'fixed',
        zIndex: '100000',
        maxWidth: '260px',
        padding: '7px 10px',
        borderRadius: '9px',
        background: 'rgba(15, 23, 42, .96)',
        color: '#fff',
        boxShadow: '0 10px 30px rgba(15, 23, 42, .24)',
        fontSize: '12px',
        fontWeight: '600',
        lineHeight: '1.25',
        textAlign: 'center',
        pointerEvents: 'none',
        opacity: '0',
        transform: 'translate(-50%, 5px) scale(.96)',
        transition: 'opacity 140ms ease, transform 140ms ease',
    });
    document.body.appendChild(dica);

    let actual: Alvo | null = null;
    let temporizador: number | null = null;

    const esconder = () => {
        if (temporizador !== null) window.clearTimeout(temporizador);
        temporizador = null;
        actual = null;
        dica.style.opacity = '0';
        dica.style.transform = 'translate(-50%, 5px) scale(.96)';
        dica.setAttribute('aria-hidden', 'true');
    };

    const mostrar = (alvo: Alvo, imediato = false) => {
        const texto = descricao(alvo);
        if (!texto) return;

        esconder();
        actual = alvo;
        temporizador = window.setTimeout(() => {
            if (actual !== alvo || !alvo.isConnected) return;

            dica.textContent = texto;
            dica.style.left = '0px';
            dica.style.top = '0px';
            dica.style.opacity = '0';
            dica.setAttribute('aria-hidden', 'false');

            const rect = alvo.getBoundingClientRect();
            const caixa = dica.getBoundingClientRect();
            const centro = Math.min(window.innerWidth - caixa.width / 2 - 8, Math.max(caixa.width / 2 + 8, rect.left + rect.width / 2));
            const cabeEmCima = rect.top >= caixa.height + 12;

            dica.style.left = `${centro}px`;
            dica.style.top = `${cabeEmCima ? rect.top - caixa.height - 8 : rect.bottom + 8}px`;
            dica.style.opacity = '1';
            dica.style.transform = 'translate(-50%, 0) scale(1)';
        }, imediato ? 0 : 260);
    };

    document.addEventListener('pointerover', (evento) => {
        const alvo = alvoDe(evento.target);
        if (alvo && alvo !== actual) mostrar(alvo);
    });
    document.addEventListener('pointerout', (evento) => {
        if (
            actual
            && evento.target instanceof Node
            && actual.contains(evento.target)
            && !(evento.relatedTarget instanceof Node && actual.contains(evento.relatedTarget))
        ) esconder();
    });
    document.addEventListener('focusin', (evento) => {
        const alvo = alvoDe(evento.target);
        if (alvo) mostrar(alvo, true);
    });
    document.addEventListener('focusout', esconder);
    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') esconder();
    });
    window.addEventListener('scroll', esconder, true);
    window.addEventListener('resize', esconder);
}
