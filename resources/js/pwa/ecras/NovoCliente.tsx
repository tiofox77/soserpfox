import { useRef, useState, type FormEvent, type ReactNode } from 'react';

import { t } from '@/i18n';

import { usePwa } from '../contexto';
import { useEstadoDoMotor } from '../ganchos';
import { db } from '../motor/base';
import { createClientOffline } from '../motor/vendas';
import { avisar, confirmar, Nota } from '../ui/Dialogos';
import { CAMPO, ROTULO } from '../ui/Folha';

/**
 * NOVO CLIENTE — guarda no aparelho e sincroniza ao reconectar.
 *
 * Guarda-se SEMPRE local primeiro, mesmo com rede: o motor põe o cliente na
 * base e na fila, e a fila leva-o ao servidor. Com rede sobe já; sem rede,
 * quando voltar. É o mesmo caminho nos dois casos — um só sítio para falhar.
 */

type TipoDeCliente = 'pessoa_fisica' | 'pessoa_juridica';

interface Formulario {
    type: TipoDeCliente;
    name: string;
    nif: string;
    email: string;
    phone: string;
    mobile: string;
    address: string;
    city: string;
    province: string;
    country: string;
    tax_regime: string;
    is_iva_subject: boolean;
}

const VAZIO: Formulario = {
    type: 'pessoa_fisica',
    name: '',
    nif: '',
    email: '',
    phone: '',
    mobile: '',
    address: '',
    city: '',
    province: '',
    country: 'Angola',
    tax_regime: 'regime_geral',
    is_iva_subject: false,
};

/** Os NIF que não identificam ninguém — o servidor trata-os como «sem NIF» (ClientController). */
const NIF_GENERICOS = ['999999999', '999999998', '000000000'];

/**
 * Para onde se volta. Por omissão, a lista de clientes (como no Blade).
 *
 * Aceita `?voltar=` para quem abre este formulário a meio de outro trabalho
 * (o documento novo, por exemplo) — mas só um caminho DESTE site e dentro do
 * PWA: um `?voltar=https://outro-sitio` num link partilhado levava o operador,
 * depois de guardar, para onde quem escreveu o link quisesse.
 */
function destinoDeVolta(padrao: string, inicio: string): string {
    const pedido = new URLSearchParams(window.location.search).get('voltar');
    if (!pedido) return padrao;

    try {
        const url = new URL(pedido, window.location.origin);
        const base = new URL(inicio, window.location.origin).pathname.replace(/\/+$/, '');

        if (url.origin === window.location.origin && (url.pathname === base || url.pathname.startsWith(`${base}/`))) {
            return url.pathname + url.search + url.hash;
        }
    } catch { /* endereço inválido: vai para a lista */ }

    return padrao;
}

function Campo({ id, rotulo, obrigatorio, erro, ajuda, children }: {
    id: string;
    rotulo: string;
    obrigatorio?: boolean;
    erro?: string;
    ajuda?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div>
            <label htmlFor={id} className={ROTULO}>
                {rotulo} {obrigatorio && <span className="text-red-500" aria-hidden="true">*</span>}
            </label>
            {children}
            {erro ? (
                <p id={`${id}-erro`} className="pwa-entra text-xs text-red-600 font-semibold mt-1">
                    <i className="fas fa-circle-exclamation mr-1" aria-hidden="true" />{erro}
                </p>
            ) : ajuda}
        </div>
    );
}

export function NovoCliente() {
    const { rotas, provincias = [] } = usePwa();
    const motor = useEstadoDoMotor();

    const [form, setForm] = useState<Formulario>(VAZIO);
    const [erros, setErros] = useState<Partial<Record<keyof Formulario, string>>>({});
    const [aGuardar, setAGuardar] = useState(false);
    const [guardado, setGuardado] = useState(false);
    // O formulário abana quando falta alguma coisa — o olho vai ao sítio antes de ler a frase.
    const [abanar, setAbanar] = useState(false);

    const campoNome = useRef<HTMLInputElement>(null);
    const campoNif = useRef<HTMLInputElement>(null);
    const campoEmail = useRef<HTMLInputElement>(null);

    const voltar = destinoDeVolta(rotas.clientes, rotas.inicio);
    const semRede = !motor.online || !motor.realOnline;

    const mudar = <K extends keyof Formulario>(chave: K, valor: Formulario[K]) => {
        setForm((f) => ({ ...f, [chave]: valor }));
        if (erros[chave]) setErros((e) => ({ ...e, [chave]: undefined }));
    };

    const validar = (): boolean => {
        const novos: typeof erros = {};

        if (!form.name.trim()) novos.name = t('Nome obrigatório');

        /*
         * O NIF passa a ser obrigatório AQUI, e não só no servidor.
         * Sem ele o cliente era aceite, ficava na fila, e a sincronização
         * dava erro contra a base de dados — horas depois, longe do balcão
         * onde a pessoa ainda estava e podia dar o número. Um erro que só se
         * pode corrigir no momento tem de ser apanhado no momento.
         *
         * E com `trim()`: o `required` do Blade aceitava um NIF feito de
         * espaços, que chegava ao servidor vazio.
         */
        if (!form.nif.trim()) novos.nif = t('NIF obrigatório');

        // O Blade tinha a validação nativa do `type="email"`; aqui o formulário
        // valida por si (sem as bolhas do navegador), e não a pode perder.
        if (form.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) novos.email = t('Email inválido');

        setErros(novos);

        if (novos.name) campoNome.current?.focus();
        else if (novos.nif) campoNif.current?.focus();
        else if (novos.email) campoEmail.current?.focus();

        if (Object.keys(novos).length) {
            setAbanar(true);

            return false;
        }

        return true;
    };

    const guardar = async (e: FormEvent) => {
        e.preventDefault();
        if (aGuardar || guardado) return;
        if (!validar()) return;

        setAGuardar(true);

        const dados = {
            ...form,
            name: form.name.trim(),
            nif: form.nif.trim(),
            email: form.email.trim(),
            phone: form.phone.trim(),
            mobile: form.mobile.trim(),
            address: form.address.trim(),
            city: form.city.trim(),
        };

        try {
            /*
             * O MESMO NIF JÁ ESTÁ NO APARELHO.
             *
             * O servidor não cria um segundo cliente com o mesmo NIF: devolve o
             * que já existe, e o nome escrito aqui não fica gravado. Sem rede
             * isso só se descobria depois — e o documento saía em nome do outro.
             * Pergunta-se agora, ao balcão, onde ainda se pode corrigir.
             */
            if (!NIF_GENERICOS.includes(dados.nif)) {
                const igual = await db.clients.where('nif').equals(dados.nif).first();

                if (igual && !(await confirmar(t('Já existe um cliente com este NIF'), {
                    texto: t('«:nome» já está neste aparelho com o NIF :nif. Ao sincronizar, fica a valer o cliente que já existe. Guardar mesmo assim?', {
                        nome: String(igual.name || ''),
                        nif: dados.nif,
                    }),
                    sim: t('Guardar mesmo assim'),
                    icone: 'fa-id-card',
                }))) {
                    setAGuardar(false);
                    campoNif.current?.focus();

                    return;
                }
            }

            // 1) Guarda local sempre (mesmo se online — segurança).
            await createClientOffline(dados);

            // 2) Feedback.
            avisar(
                navigator.onLine
                    ? t('Cliente guardado — a sincronizar com o servidor...')
                    : t('Cliente guardado localmente. Será enviado quando voltar online.'),
                'ok',
            );
            setGuardado(true);

            // 3) Volta à lista (ou a quem abriu o formulário) depois de se ler o aviso.
            setTimeout(() => { window.location.href = voltar; }, 1500);
        } catch (err) {
            console.error(err);
            avisar(t('Erro ao guardar: :erro', { erro: err instanceof Error ? err.message : String(err) }), 'erro');
            setAGuardar(false);
        }
    };

    /** As classes do campo sem duas regras da mesma propriedade a disputar: no Tailwind ganha a ordem da folha, não a da lista. */
    const campo = (chave: keyof Formulario | null, recuo = '') => {
        let c = recuo ? CAMPO.replace('px-3', `pr-3 ${recuo}`) : CAMPO;
        if (chave && erros[chave]) c = c.replace('border-slate-200', 'border-red-400').replace('bg-white', 'bg-red-50/40');

        return c;
    };
    const descrito = (chave: keyof Formulario, id: string) => (erros[chave] ? `${id}-erro` : undefined);

    return (
        <div className="max-w-xl mx-auto">
            <div className="pwa-entra mb-4 flex items-center gap-3">
                <a href={voltar} aria-label={t('Voltar')}
                   className="pwa-toque w-10 h-10 bg-white rounded-xl shadow flex items-center justify-center text-slate-600 hover:bg-slate-50">
                    <i className="fas fa-arrow-left" aria-hidden="true" />
                </a>
                <span className="w-10 h-10 shrink-0 rounded-xl bg-gradient-to-br from-emerald-500 to-green-600 text-white flex items-center justify-center shadow-md shadow-emerald-600/20">
                    <i className="fas fa-user-plus" aria-hidden="true" />
                </span>
                <div className="min-w-0">
                    <h1 className="text-xl font-bold text-slate-900 leading-tight">{t('Novo Cliente')}</h1>
                    <p className="text-xs text-slate-500">{t('Guarda local + sincroniza ao reconectar')}</p>
                </div>
            </div>

            {/* `noValidate`: as bolhas do navegador saem por cima do teclado no
                telemóvel e não se traduzem; a validação é do formulário (em `validar`).
                A entrada anima o invólucro e o abanão anima o formulário: na mesma
                caixa, tirar o abanão devolvia a classe da entrada e ela repetia-se. */}
            <div className="pwa-entra">
            <form onSubmit={guardar} noValidate
                  onAnimationEnd={(e) => { if (e.target === e.currentTarget && e.animationName === 'pwaAbana') setAbanar(false); }}
                  className={`${abanar ? 'pwa-abana ' : ''}bg-white rounded-2xl shadow-lg p-5 space-y-4`}>
                {/* Tipo */}
                <fieldset>
                    <legend className={`${ROTULO} mb-2`}>{t('Tipo de Cliente')}</legend>
                    <input type="hidden" name="type" value={form.type} />
                    <div className="grid grid-cols-2 gap-2">
                        {([
                            { valor: 'pessoa_fisica', rotulo: t('Singular'), icone: 'fa-user', activo: 'bg-purple-600 text-white border-purple-600 shadow-md shadow-purple-600/25' },
                            { valor: 'pessoa_juridica', rotulo: t('Empresa'), icone: 'fa-building', activo: 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-600/25' },
                        ] as const).map((op) => (
                            <button key={op.valor} type="button" onClick={() => mudar('type', op.valor)} aria-pressed={form.type === op.valor}
                                    className={`pwa-toque border-2 rounded-xl py-3 text-sm font-bold transition ${form.type === op.valor ? op.activo : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'}`}>
                                <i className={`fas ${op.icone} block mb-1 text-base`} aria-hidden="true" />{op.rotulo}
                            </button>
                        ))}
                    </div>
                </fieldset>

                {/* Nome */}
                <Campo id="cliente-nome" rotulo={t('Nome')} obrigatorio erro={erros.name}>
                    <input ref={campoNome} id="cliente-nome" name="name" type="text" required maxLength={255} autoComplete="off"
                           value={form.name} onChange={(e) => mudar('name', e.target.value)}
                           aria-invalid={!!erros.name} aria-describedby={descrito('name', 'cliente-nome')}
                           placeholder={t('Nome ou Designação Social')}
                           className={campo('name')} />
                </Campo>

                {/* NIF */}
                <Campo id="cliente-nif" rotulo={t('NIF / BI')} obrigatorio erro={erros.nif}
                       ajuda={(
                           <p className="text-xs text-slate-400 mt-1">
                               {t('Obrigatório. Para vendas sem cliente identificado, use o Consumidor Final no POS.')}
                           </p>
                       )}>
                    <input ref={campoNif} id="cliente-nif" name="nif" type="text" required maxLength={50} inputMode="numeric" autoComplete="off"
                           value={form.nif} onChange={(e) => mudar('nif', e.target.value)}
                           aria-invalid={!!erros.nif} aria-describedby={descrito('nif', 'cliente-nif')}
                           placeholder={t('Ex: 5417654321')}
                           className={campo('nif')} />
                </Campo>

                {/* Contactos */}
                <div className="grid grid-cols-2 gap-3">
                    <Campo id="cliente-telefone" rotulo={t('Telefone')}>
                        <div className="relative">
                            <i className="fas fa-phone absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 text-xs" aria-hidden="true" />
                            <input id="cliente-telefone" name="phone" type="tel" maxLength={50} autoComplete="off"
                                   value={form.phone} onChange={(e) => mudar('phone', e.target.value)}
                                   className={campo(null, 'pl-8')} />
                        </div>
                    </Campo>
                    <Campo id="cliente-telemovel" rotulo={t('Telemóvel')}>
                        <div className="relative">
                            <i className="fas fa-mobile-screen absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 text-xs" aria-hidden="true" />
                            <input id="cliente-telemovel" name="mobile" type="tel" maxLength={50} autoComplete="off"
                                   value={form.mobile} onChange={(e) => mudar('mobile', e.target.value)}
                                   className={campo(null, 'pl-8')} />
                        </div>
                    </Campo>
                </div>

                <Campo id="cliente-email" rotulo={t('Email')} erro={erros.email}>
                    <div className="relative">
                        <i className="fas fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-300 text-xs" aria-hidden="true" />
                        <input ref={campoEmail} id="cliente-email" name="email" type="email" maxLength={255} autoComplete="off" inputMode="email"
                               value={form.email} onChange={(e) => mudar('email', e.target.value)}
                               aria-invalid={!!erros.email} aria-describedby={descrito('email', 'cliente-email')}
                               className={campo('email', 'pl-9')} />
                    </div>
                </Campo>

                {/* Localização */}
                <Campo id="cliente-morada" rotulo={t('Endereço')}>
                    <textarea id="cliente-morada" name="address" rows={2} maxLength={500}
                              value={form.address} onChange={(e) => mudar('address', e.target.value)}
                              className={`${CAMPO} resize-none`} />
                </Campo>

                <div className="grid grid-cols-2 gap-3">
                    <Campo id="cliente-cidade" rotulo={t('Cidade')}>
                        <input id="cliente-cidade" name="city" type="text" maxLength={120} autoComplete="off"
                               value={form.city} onChange={(e) => mudar('city', e.target.value)}
                               className={CAMPO} />
                    </Campo>
                    <Campo id="cliente-provincia" rotulo={t('Província')}>
                        {/* As 21 províncias da reforma de 2024, vindas do servidor
                            (Geografia::provincias). O Blade tinha as 18 de antes
                            escritas à mão: quem vendia no Icolo e Bengo, no Cuando,
                            no Cubango ou no Moxico Leste não encontrava a sua. */}
                        <select id="cliente-provincia" name="province" value={form.province} onChange={(e) => mudar('province', e.target.value)}
                                className={CAMPO}>
                            <option value="">—</option>
                            {provincias.map((p) => <option key={p} value={p}>{p}</option>)}
                        </select>
                    </Campo>
                </div>

                {/* Regime fiscal */}
                <div className="grid grid-cols-2 gap-3">
                    <Campo id="cliente-regime" rotulo={t('Regime Fiscal')}>
                        <select id="cliente-regime" name="tax_regime" value={form.tax_regime} onChange={(e) => mudar('tax_regime', e.target.value)}
                                className={CAMPO}>
                            <option value="regime_geral">{t('Regime Geral')}</option>
                            <option value="regime_simplificado">{t('Simplificado')}</option>
                            <option value="regime_exclusao">{t('Exclusão')}</option>
                            <option value="nao_sujeito">{t('Não Sujeito')}</option>
                        </select>
                    </Campo>
                    <div className="flex items-end">
                        <label htmlFor="cliente-iva"
                               className={`pwa-toque w-full flex items-center gap-2 text-sm px-3 py-2.5 rounded-xl border-2 cursor-pointer transition ${form.is_iva_subject ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white'}`}>
                            <input id="cliente-iva" name="is_iva_subject" type="checkbox" checked={form.is_iva_subject}
                                   onChange={(e) => mudar('is_iva_subject', e.target.checked)}
                                   className="w-5 h-5 rounded accent-blue-600" />
                            <span className="font-semibold text-slate-700">{t('Sujeito a IVA')}</span>
                        </label>
                    </div>
                </div>

                {/* O país não se escolhe aqui — o PWA vende em Angola. Vai no registo como ia no Blade. */}
                <input type="hidden" name="country" value={form.country} />

                {/* Alerta offline — vivo: muda quando a rede cai ou volta com o formulário aberto. */}
                {semRede && (
                    <Nota tipo="aviso" icone="fa-wifi">
                        <strong>{t('Sem conexão.')}</strong>{' '}
                        {t('O cliente será guardado localmente e enviado ao servidor automaticamente quando voltar online.')}
                    </Nota>
                )}

                <div className="flex gap-3 pt-2">
                    <a href={voltar}
                       className="pwa-toque flex-1 text-center py-3 border-2 border-slate-300 text-slate-700 rounded-xl font-bold text-sm hover:bg-slate-50">
                        {t('Cancelar')}
                    </a>
                    <button type="submit" disabled={aGuardar || guardado}
                            className="pwa-toque flex-1 bg-gradient-to-r from-emerald-500 to-green-600 text-white py-3 rounded-xl font-bold text-sm shadow-lg shadow-emerald-600/25 hover:shadow-xl transition disabled:opacity-60">
                        <i className={`fas ${guardado ? 'fa-check' : aGuardar ? 'fa-spinner fa-spin' : 'fa-floppy-disk'} mr-1`} aria-hidden="true" />
                        {/* «Guardado» e não «Cliente guardado»: essa frase é a do aviso, e duas iguais no ecrã confundem quem a procura. */}
                        {guardado ? t('Guardado') : aGuardar ? t('A guardar...') : t('Guardar Cliente')}
                    </button>
                </div>
            </form>
            </div>
        </div>
    );
}
