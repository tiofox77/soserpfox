/**
 * OS AVISOS DE CANTO — «PDF gerado», «Sem ligação», «Ligação restaurada».
 *
 * Eram do `toastr` (e do jQuery que ele arrasta). Agora quem quer avisar
 * dispara um evento na janela e a peça `casca/sistema` desenha-o; assim um
 * módulo sem React (o gerador de PDF, o service worker) não precisa de saber
 * nada de componentes.
 */
export type TipoDeAviso = 'ok' | 'erro' | 'aviso' | 'info';

export type Aviso = { texto: string; tipo: TipoDeAviso; titulo?: string; duracao?: number };

export const EVENTO_DE_AVISO = 'casca:aviso';

export function avisar(texto: string, tipo: TipoDeAviso = 'info', opcoes: { titulo?: string; duracao?: number } = {}): void {
    window.dispatchEvent(new CustomEvent<Aviso>(EVENTO_DE_AVISO, { detail: { texto, tipo, ...opcoes } }));
}
