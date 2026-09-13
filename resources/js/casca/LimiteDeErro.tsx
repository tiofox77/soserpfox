import { Component, type ErrorInfo, type ReactNode } from 'react';

import { relatarErro } from './relatarErro';

/**
 * Um ecrã que rebenta não leva a página com ele.
 *
 * Durante a migração isto vale mais do que no fim: se a lista de facturas em
 * React falhar, o menu, o cabeçalho e o resto do sistema continuam de pé, e
 * quem está a trabalhar sai dali para outro sítio em vez de ficar com a página
 * em branco.
 *
 * Mostra o erro por extenso de propósito. Isto vive num ERP usado por quem
 * factura: um «algo correu mal» obriga a telefonar; a mensagem verdadeira
 * chega numa fotografia do ecrã.
 */

type Props = { ecra: string; children: ReactNode; discreto?: boolean };
type Estado = { erro: Error | null };

export class LimiteDeErro extends Component<Props, Estado> {
    override state: Estado = { erro: null };

    static getDerivedStateFromError(erro: Error): Estado {
        return { erro };
    }

    override componentDidCatch(erro: Error, info: ErrorInfo): void {
        console.error(`[React] o ecrã "${this.props.ecra}" rebentou`, erro, info.componentStack);

        // E o servidor fica a saber — ver casca/relatarErro.ts.
        relatarErro({ mensagem: erro.message, pilha: erro.stack, pilhaDoComponente: info.componentStack, ecra: this.props.ecra, origem: 'ecra' });
    }

    override render(): ReactNode {
        const { erro } = this.state;

        if (!erro) {
            return this.props.children;
        }

        // Uma peça do topo que rebenta não pode empurrar o cabeçalho: fica um
        // ícone com o erro no título, e a consola tem o resto.
        if (this.props.discreto) {
            return (
                <span title={erro.message} className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-red-500">
                    <i className="fas fa-triangle-exclamation" aria-hidden="true" />
                </span>
            );
        }

        return (
            <div className="rounded-2xl border border-red-200 bg-red-50 p-6">
                <h2 className="mb-2 text-lg font-bold text-red-900">
                    Este ecrã não conseguiu abrir
                </h2>
                <p className="mb-4 text-sm text-red-800">
                    O resto do sistema continua a funcionar. Se isto se repetir, mostre esta
                    mensagem a quem trata do sistema.
                </p>
                <pre className="overflow-x-auto rounded-xl bg-white/70 p-3 text-xs text-red-900">
                    {erro.message}
                </pre>
                <button
                    type="button"
                    onClick={() => window.location.reload()}
                    className="mt-4 rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                >
                    Tentar outra vez
                </button>
            </div>
        );
    }
}
