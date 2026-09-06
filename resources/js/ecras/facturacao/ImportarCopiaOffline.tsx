import { useRef, useState } from 'react';
import { useMutation } from '@tanstack/react-query';

import { copiaOffline, type Inventario, type Resultado } from '@/api/offline';
import { ErroDaApi } from '@/api/cliente';
import { Botao } from '@/ui/Botao';
import { Cartao } from '@/ui/Cartao';
import { RAIO, cls } from '@/ui/tokens';

/**
 * RECUPERAR UMA CÓPIA DE SEGURANÇA DO PWA.
 *
 * Em dois passos, de propósito: primeiro mostra-se o que está no ficheiro,
 * depois é que se importa. Quem recupera dados está normalmente com pressa
 * e a fazê-lo pela primeira vez, e não pode ser o clique a decidir.
 */
export default function ImportarCopiaOffline() {
    const ficheiro = useRef<HTMLInputElement>(null);
    const [nome, porNome] = useState('');
    const [inventario, porInventario] = useState<Inventario | null>(null);
    const [resultado, porResultado] = useState<{ r: Resultado; message: string } | null>(null);
    const [erro, porErro] = useState('');

    const corpo = () => { const f = new FormData(); const escolhido = ficheiro.current?.files?.[0]; if (escolhido) f.append('ficheiro', escolhido); return f; };
    const falhou = (e: unknown) => porErro(e instanceof ErroDaApi ? e.message : 'Não foi possível ler o ficheiro.');

    const analisar = useMutation({ mutationFn: () => copiaOffline.analisar(corpo()), onSuccess: (r) => { porInventario(r.inventario); porErro(''); }, onError: (e) => { porInventario(null); falhou(e); } });
    const importar = useMutation({ mutationFn: () => copiaOffline.importar(corpo()), onSuccess: (r) => { porResultado({ r: r.resultado, message: r.message }); porInventario(null); porErro(''); }, onError: falhou });

    const escolher = () => {
        porResultado(null); porInventario(null); porErro('');
        const f = ficheiro.current?.files?.[0];
        porNome(f?.name ?? '');
        if (f) analisar.mutate();
    };

    return (
        <div className="space-y-4" data-copia-offline>
            <Cartao titulo={<span className="flex items-center gap-2"><i className="fas fa-file-import text-slate-400" aria-hidden="true" />Importar Cópia Offline</span>}>
                <p className="mb-4 text-sm text-slate-600">O caminho normal é o aparelho sincronizar sozinho. Isto é para quando não sincronizou e já não vai: o telemóvel partiu-se, o navegador limpou os dados, alguém reiniciou tudo antes de enviar. As vendas que lá estavam já aconteceram.</p>
                <label className={cls('flex cursor-pointer flex-col items-center justify-center gap-2 border-2 border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-600 hover:border-indigo-400', RAIO)}>
                    <i className="fas fa-cloud-arrow-up text-2xl text-slate-400" aria-hidden="true" />
                    <span>{nome ? <strong>{nome}</strong> : 'Escolha o ficheiro .json exportado do PWA'}</span>
                    <input ref={ficheiro} type="file" accept=".json,application/json" onChange={escolher} className="sr-only" aria-label="Ficheiro da cópia" />
                </label>
                {analisar.isPending && <p className="mt-3 text-sm text-slate-500"><i className="fas fa-spinner fa-spin mr-2" aria-hidden="true" />A ler o ficheiro…</p>}
                {erro && <p role="alert" className={cls('mt-3 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900', RAIO)} data-erro>{erro}</p>}
            </Cartao>

            {inventario && (
                <Cartao titulo="O que está no ficheiro" accoes={<Botao cor="primaria" tom="solida" icone="fa-file-import" aTrabalhar={importar.isPending} onClick={() => importar.mutate()}>Importar</Botao>}>
                    <div className="grid gap-3 sm:grid-cols-5" data-inventario>
                        {([['Vendas', inventario.vendas], ['Clientes', inventario.clientes], ['Rascunhos', inventario.rascunhos], ['Comandas', inventario.comandas], ['Turnos', inventario.turnos]] as Array<[string, number]>).map(([r, v]) => (
                            <div key={r} className={cls('border border-slate-200 bg-white p-4', RAIO)}><p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{r}</p><p className="mt-1 text-2xl font-bold tabular-nums text-slate-900">{v}</p></div>
                        ))}
                    </div>
                    <p className="mt-4 text-xs text-slate-500">Gerada {inventario.gerado_em ?? '—'} · aparelho {inventario.dispositivo ?? '—'} · operador {inventario.operador ?? '—'}. Nada é gravado até carregar em Importar; o que já existir no servidor não entra a dobrar.</p>
                </Cartao>
            )}

            {resultado && (
                <Cartao titulo="Importação concluída">
                    <p role="status" className={cls('border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)} data-resultado><i className="fas fa-circle-check mr-2" aria-hidden="true" />{resultado.message}</p>
                    <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-4">
                        {Object.entries(resultado.r).filter(([, v]) => typeof v !== 'object').map(([k, v]) => <div key={k}><dt className="text-xs uppercase tracking-wider text-slate-500">{k.replace(/_/g, ' ')}</dt><dd className="font-semibold text-slate-900">{String(v)}</dd></div>)}
                    </dl>
                </Cartao>
            )}
        </div>
    );
}
