import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { pin } from '@/api/offline';
import { ErroDaApi } from '@/api/cliente';
import { AvisoDeErro } from '@/ui/AvisoDeErro';
import { Botao } from '@/ui/Botao';
import { Campo, entrada } from '@/ui/Campo';
import { Cartao } from '@/ui/Cartao';
import { Carregando } from '@/ui/Carregando';
import { Etiqueta } from '@/ui/Etiqueta';
import { RAIO, cls } from '@/ui/tokens';
import { t } from '@/i18n';

/**
 * O PIN DE TURNO: a credencial de chão de loja que abre turno offline no
 * POS. Confirma-se com a palavra-passe para ninguém o definir a partir de
 * uma sessão deixada aberta; o que fica guardado é só o bcrypt, que segue
 * para os tablets na próxima sincronização.
 */
export default function DefinirPin() {
    const cache = useQueryClient();
    const q = useQuery({ queryKey: ['pin'], queryFn: pin.estado });
    const [forma, porForma] = useState({ pin: '', pin_confirmation: '', password: '' });
    const [erros, porErros] = useState<Record<string, string[]>>({});
    const [recado, porRecado] = useState('');

    const guardar = useMutation({
        mutationFn: () => pin.definir(forma),
        onSuccess: (r) => { porRecado(r.message); porErros({}); porForma({ pin: '', pin_confirmation: '', password: '' }); void cache.invalidateQueries({ queryKey: ['pin'] }); },
        onError: (e) => { porErros(e instanceof ErroDaApi ? e.erros : {}); porForma((f) => ({ ...f, pin: '', pin_confirmation: '' })); },
    });

    if (q.isPending) return <Carregando linhas={4} />;
    if (q.isError) {
        return (
            <div className={cls('border border-red-200 bg-red-50 p-6', RAIO)} role="alert">
                <h2 className="mb-2 text-lg font-bold text-red-900">{t('Não foi possível abrir o PIN')}</h2>
                <p className="text-sm text-red-800">{q.error instanceof ErroDaApi ? q.error.message : t('Verifique a ligação.')}</p>
            </div>
        );
    }

    const m = (chave: keyof typeof forma) => (e: React.ChangeEvent<HTMLInputElement>) => porForma({ ...forma, [chave]: e.target.value });

    return (
        <div className="mx-auto max-w-lg space-y-4" data-definir-pin>
            {recado && <p role="status" className={cls('border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900', RAIO)}><i className="fas fa-circle-check mr-2" aria-hidden="true" />{recado}</p>}
            <Cartao titulo={<span className="flex items-center gap-2"><i className="fas fa-key text-slate-400" aria-hidden="true" />{t('PIN de turno')}</span>} accoes={q.data.ja_tem_pin ? <Etiqueta cor="bom" icone="fa-check">{t('Já tem PIN')}</Etiqueta> : <Etiqueta cor="aviso" icone="fa-triangle-exclamation">{t('Sem PIN')}</Etiqueta>}>
                <AvisoDeErro erro={guardar.error} />
                <p className="mb-4 text-sm text-slate-600">{t(':nome, o PIN abre o seu turno no POS quando não há internet. Quatro a seis dígitos, e nada de óbvio.', { nome: q.data.nome })}</p>
                <form className="grid gap-4" onSubmit={(e) => { e.preventDefault(); guardar.mutate(); }}>
                    <Campo etiqueta={t('PIN novo')} erro={erros.pin} obrigatorio><input type="password" inputMode="numeric" pattern="[0-9]*" maxLength={6} autoComplete="off" value={forma.pin} onChange={m('pin')} className={cls(entrada, 'font-mono tracking-widest')} /></Campo>
                    <Campo etiqueta={t('Repita o PIN')} erro={erros.pin_confirmation} obrigatorio><input type="password" inputMode="numeric" pattern="[0-9]*" maxLength={6} autoComplete="off" value={forma.pin_confirmation} onChange={m('pin_confirmation')} className={cls(entrada, 'font-mono tracking-widest')} /></Campo>
                    <Campo etiqueta={t('A sua palavra-passe')} erro={erros.password} obrigatorio><input type="password" autoComplete="current-password" value={forma.password} onChange={m('password')} className={entrada} /></Campo>
                    <div><Botao cor="primaria" tom="solida" icone="fa-floppy-disk" aTrabalhar={guardar.isPending}>{q.data.ja_tem_pin ? t('Mudar o PIN') : t('Definir o PIN')}</Botao></div>
                </form>
            </Cartao>
        </div>
    );
}
