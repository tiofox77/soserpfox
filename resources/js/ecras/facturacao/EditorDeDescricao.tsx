import { EditorContent, useEditor, useEditorState, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';

import { t } from '@/i18n';
import { Botao } from '@/ui/Botao';
import { Modal } from '@/ui/Modal';
import { FOCO, RAIO, cls } from '@/ui/tokens';
import { paraHtml } from './descricaoRica';

/**
 * O EDITOR DA DESCRIÇÃO DE UMA LINHA — proformas de venda e orçamentos.
 *
 * Numa proposta, a descrição é onde se explica o trabalho: o âmbito, as fases,
 * o que está incluído e o que não está. Uma caixa de uma linha, cortada a
 * meio da primeira frase, não chegava (pedido de 23/09/2026). Aqui escreve-se
 * com títulos, listas, tabelas e alinhamento, e sai assim no PDF.
 *
 * Só o que o papel sabe desenhar: sem imagens, sem cores e sem ligações. O
 * servidor volta a limpar o que chega (DescricaoRica::limpar), porque o HTML
 * vem do browser.
 *
 * Carrega-se à parte (`lazy`): o editor pesa, e só vem quando se abre.
 */
export default function EditorDeDescricao({
    artigo,
    valor,
    aoGuardar,
    aoFechar,
}: {
    /** O artigo da linha, para o título do modal. */
    artigo: string;
    valor: string;
    aoGuardar: (html: string) => void;
    aoFechar: () => void;
}) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                // O papel não segue ligações nem mostra código: fora do editor.
                link: false,
                code: false,
                codeBlock: false,
            }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Table.configure({ resizable: false }),
            TableRow,
            TableHeader,
            TableCell,
        ],
        content: paraHtml(valor),
        immediatelyRender: true,
        editorProps: {
            attributes: {
                'aria-label': t('Descrição da linha'),
                'aria-multiline': 'true',
                role: 'textbox',
            },
        },
    });

    const palavras = useEditorState({
        editor,
        selector: ({ editor: e }) => (e ? e.getText().trim().split(/\s+/).filter(Boolean).length : 0),
    });

    function guardar() {
        if (!editor) return;
        aoGuardar(editor.isEmpty ? '' : editor.getHTML());
    }

    return (
        <Modal
            aberto
            aoFechar={aoFechar}
            titulo={t('Descrição da linha')}
            subtitulo={artigo}
            icone="fa-file-pen"
            largura="xl"
            rodape={
                <>
                    <span className="mr-auto text-xs text-slate-400">
                        {t(':n palavra(s)', { n: palavras ?? 0 })}
                    </span>
                    <Botao onClick={aoFechar}>{t('Cancelar')}</Botao>
                    <Botao cor="primaria" tom="solida" icone="fa-check" onClick={guardar}>
                        {t('Guardar descrição')}
                    </Botao>
                </>
            }
        >
            <p className="mb-3 text-sm text-slate-500">
                {t('Escreva aqui tudo o que a proposta deve explicar sobre esta linha. Sai no PDF da proforma e do orçamento tal como fica aqui; na factura vai só o texto.')}
            </p>

            {editor && <BarraDeFerramentas editor={editor} />}

            <div
                className={cls(
                    'mt-2 max-h-[55vh] min-h-[320px] overflow-y-auto border border-slate-300 bg-white px-4 py-3 text-sm text-slate-800',
                    'focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-100',
                    RAIO,
                    CONTEUDO,
                )}
                onClick={() => editor?.commands.focus()}
            >
                <EditorContent editor={editor} />
            </div>
        </Modal>
    );
}

/** O aspecto do texto dentro do editor: perto do que sai no papel. */
const CONTEUDO = [
    '[&_.ProseMirror]:min-h-[290px] [&_.ProseMirror]:outline-none',
    '[&_.ProseMirror_p]:my-1.5',
    '[&_.ProseMirror_h2]:mb-1.5 [&_.ProseMirror_h2]:mt-3 [&_.ProseMirror_h2]:text-xl [&_.ProseMirror_h2]:font-bold',
    '[&_.ProseMirror_h3]:mb-1 [&_.ProseMirror_h3]:mt-2.5 [&_.ProseMirror_h3]:text-lg [&_.ProseMirror_h3]:font-semibold',
    '[&_.ProseMirror_h4]:mb-1 [&_.ProseMirror_h4]:mt-2 [&_.ProseMirror_h4]:font-semibold',
    '[&_.ProseMirror_ul]:list-disc [&_.ProseMirror_ul]:pl-6 [&_.ProseMirror_ol]:list-decimal [&_.ProseMirror_ol]:pl-6',
    '[&_.ProseMirror_li_p]:my-0.5',
    '[&_.ProseMirror_blockquote]:my-2 [&_.ProseMirror_blockquote]:border-l-4 [&_.ProseMirror_blockquote]:border-slate-300 [&_.ProseMirror_blockquote]:pl-3 [&_.ProseMirror_blockquote]:text-slate-600',
    '[&_.ProseMirror_hr]:my-3 [&_.ProseMirror_hr]:border-slate-300',
    '[&_.ProseMirror_table]:my-2 [&_.ProseMirror_table]:w-full [&_.ProseMirror_table]:border-collapse',
    '[&_.ProseMirror_td]:border [&_.ProseMirror_td]:border-slate-300 [&_.ProseMirror_td]:p-1.5 [&_.ProseMirror_td]:align-top',
    '[&_.ProseMirror_th]:border [&_.ProseMirror_th]:border-slate-300 [&_.ProseMirror_th]:bg-slate-100 [&_.ProseMirror_th]:p-1.5 [&_.ProseMirror_th]:text-left [&_.ProseMirror_th]:font-semibold',
    '[&_.selectedCell]:bg-indigo-50',
].join(' ');

type Accao = {
    rotulo: string;
    icone: string;
    activo?: boolean;
    pode?: boolean;
    fazer: () => void;
};

function BarraDeFerramentas({ editor }: { editor: Editor }) {
    // A barra redesenha-se quando a selecção muda: é o que acende o «negrito».
    const e = useEditorState({
        editor,
        selector: ({ editor: ed }) => ({
            negrito: ed.isActive('bold'),
            italico: ed.isActive('italic'),
            sublinhado: ed.isActive('underline'),
            riscado: ed.isActive('strike'),
            h2: ed.isActive('heading', { level: 2 }),
            h3: ed.isActive('heading', { level: 3 }),
            lista: ed.isActive('bulletList'),
            numerada: ed.isActive('orderedList'),
            citacao: ed.isActive('blockquote'),
            esquerda: ed.isActive({ textAlign: 'left' }),
            centro: ed.isActive({ textAlign: 'center' }),
            direita: ed.isActive({ textAlign: 'right' }),
            justificado: ed.isActive({ textAlign: 'justify' }),
            naTabela: ed.isActive('table'),
            desfazer: ed.can().undo(),
            refazer: ed.can().redo(),
        }),
    });

    const c = () => editor.chain().focus();

    const grupos: Accao[][] = [
        [
            { rotulo: t('Desfazer'), icone: 'fa-rotate-left', pode: e.desfazer, fazer: () => c().undo().run() },
            { rotulo: t('Refazer'), icone: 'fa-rotate-right', pode: e.refazer, fazer: () => c().redo().run() },
        ],
        [
            { rotulo: t('Título'), icone: 'fa-heading', activo: e.h2, fazer: () => c().toggleHeading({ level: 2 }).run() },
            { rotulo: t('Subtítulo'), icone: 'fa-text-height', activo: e.h3, fazer: () => c().toggleHeading({ level: 3 }).run() },
        ],
        [
            { rotulo: t('Negrito'), icone: 'fa-bold', activo: e.negrito, fazer: () => c().toggleBold().run() },
            { rotulo: t('Itálico'), icone: 'fa-italic', activo: e.italico, fazer: () => c().toggleItalic().run() },
            { rotulo: t('Sublinhado'), icone: 'fa-underline', activo: e.sublinhado, fazer: () => c().toggleUnderline().run() },
            { rotulo: t('Riscado'), icone: 'fa-strikethrough', activo: e.riscado, fazer: () => c().toggleStrike().run() },
        ],
        [
            { rotulo: t('Lista'), icone: 'fa-list-ul', activo: e.lista, fazer: () => c().toggleBulletList().run() },
            { rotulo: t('Lista numerada'), icone: 'fa-list-ol', activo: e.numerada, fazer: () => c().toggleOrderedList().run() },
            { rotulo: t('Citação'), icone: 'fa-quote-left', activo: e.citacao, fazer: () => c().toggleBlockquote().run() },
            { rotulo: t('Linha separadora'), icone: 'fa-minus', fazer: () => c().setHorizontalRule().run() },
        ],
        [
            { rotulo: t('Alinhar à esquerda'), icone: 'fa-align-left', activo: e.esquerda, fazer: () => c().setTextAlign('left').run() },
            { rotulo: t('Centrar'), icone: 'fa-align-center', activo: e.centro, fazer: () => c().setTextAlign('center').run() },
            { rotulo: t('Alinhar à direita'), icone: 'fa-align-right', activo: e.direita, fazer: () => c().setTextAlign('right').run() },
            { rotulo: t('Justificar'), icone: 'fa-align-justify', activo: e.justificado, fazer: () => c().setTextAlign('justify').run() },
        ],
        [
            { rotulo: t('Inserir tabela'), icone: 'fa-table', fazer: () => c().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
            ...(e.naTabela
                ? [
                    { rotulo: t('Linha abaixo'), icone: 'fa-arrow-down', fazer: () => c().addRowAfter().run() },
                    { rotulo: t('Coluna à direita'), icone: 'fa-arrow-right', fazer: () => c().addColumnAfter().run() },
                    { rotulo: t('Apagar a linha da tabela'), icone: 'fa-grip-lines', fazer: () => c().deleteRow().run() },
                    { rotulo: t('Apagar a coluna'), icone: 'fa-grip-lines-vertical', fazer: () => c().deleteColumn().run() },
                    { rotulo: t('Apagar a tabela'), icone: 'fa-trash', fazer: () => c().deleteTable().run() },
                ]
                : []),
        ],
        [
            { rotulo: t('Limpar formatação'), icone: 'fa-eraser', fazer: () => c().unsetAllMarks().clearNodes().run() },
        ],
    ];

    return (
        <div role="toolbar" aria-label={t('Formatação')} className={cls('flex flex-wrap items-center gap-1 border border-slate-200 bg-slate-50 p-1.5', RAIO)}>
            {grupos.map((grupo, g) => (
                <div key={g} className="flex items-center gap-0.5 border-r border-slate-200 pr-1 last:border-r-0">
                    {grupo.map((a) => (
                        <button
                            key={a.rotulo}
                            type="button"
                            title={a.rotulo}
                            aria-label={a.rotulo}
                            aria-pressed={a.activo ?? undefined}
                            disabled={a.pode === false}
                            // Sem roubar o foco ao texto: o comando aplica-se onde está o cursor.
                            onMouseDown={(ev) => ev.preventDefault()}
                            onClick={a.fazer}
                            className={cls(
                                'grid h-8 w-8 place-items-center text-sm transition-colors duration-150',
                                a.activo ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-indigo-700',
                                'disabled:cursor-not-allowed disabled:opacity-40',
                                'rounded-lg',
                                FOCO,
                            )}
                        >
                            <i className={cls('fas', a.icone)} aria-hidden="true" />
                        </button>
                    ))}
                </div>
            ))}
        </div>
    );
}
