import { useState } from 'react';

import Catalogo from '@/ecras/facturacao/Catalogo';
import Clientes from '@/ecras/facturacao/Clientes';
import { PainelDoSeparador, Separadores } from '@/ui/Separadores';
import { t } from '@/i18n';

/**
 * CLIENTES E FORNECEDORES DO RESTAURANTE.
 *
 * NÃO É UMA CÓPIA REDUZIDA. O ecrã em Blade era um formulário que só CRIAVA —
 * não editava, não apagava, e mostrava cem registos sem paginação nem procura
 * — ao lado de dois ecrãs completos que a Facturação já tinha. Quem precisava
 * de corrigir um NIF tinha de sair do restaurante.
 *
 * Aqui abrem-se os ecrãs verdadeiros em separadores. São os mesmos registos
 * (`invoicing_clients`, `invoicing_suppliers`) que o resto da casa usa — e
 * assim um campo novo no cliente aparece aqui no mesmo dia, em vez de ficar a
 * faltar numa segunda cópia que ninguém se lembra de actualizar.
 */

export default function Contactos({ separador }: { separador?: string }) {
    const [aba, porAba] = useState(separador === 'fornecedores' ? 'fornecedores' : 'clientes');

    return (
        <div className="space-y-5">
            <Separadores
                activa={aba}
                aoMudar={porAba}
                abas={[
                    { chave: 'clientes', rotulo: t('Clientes'), icone: 'fa-users' },
                    { chave: 'fornecedores', rotulo: t('Fornecedores'), icone: 'fa-truck-field' },
                ]}
            />

            <PainelDoSeparador chave="clientes" activa={aba}>
                <Clientes />
            </PainelDoSeparador>

            <PainelDoSeparador chave="fornecedores" activa={aba}>
                <Catalogo tipo="fornecedores" />
            </PainelDoSeparador>
        </div>
    );
}
