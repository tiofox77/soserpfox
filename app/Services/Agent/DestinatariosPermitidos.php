<?php

namespace App\Services\Agent;

use App\Models\Client;
use App\Models\Tenant;

/**
 * Quem o agente pode contactar.
 *
 * O corpo do pedido NUNCA transporta um email ou um telefone: transporta um
 * handle ('responsavel', 'empresa', 'cliente:123') que é aqui resolvido contra
 * a base. É esta a fronteira que interessa — sem ela, a API seria uma máquina
 * de enviar, em nome do soserp, para onde quer que alguém indicasse.
 *
 * OS CONTACTOS VÃO POR INTEIRO
 * ----------------------------
 * Iam mascarados ('ca***@dominio.ao', '9****9902'). Foi retirado a pedido de
 * quem gere a plataforma: o agente contacta os clientes por canais próprios
 * (WhatsApp, chamada) e um número cortado não serve para nada — não se marca
 * meio número.
 *
 * O que protege isto não é a máscara: é o token, a lista de IPs, os escopos, e
 * o registo de cada pedido (AgentRequest). A máscara era uma segunda camada e
 * quem gere a plataforma decidiu que o custo dela — um agente que vê os dados
 * e não os consegue usar — era maior do que o que ela dava.
 */
class DestinatariosPermitidos
{
    /** Lista os destinatários possíveis de uma empresa. */
    public function paraTenant(Tenant $tenant): array
    {
        $lista = [];

        if ($tenant->email || $tenant->phone) {
            $lista[] = [
                'handle'  => 'empresa',
                'quem'    => $tenant->name,
                'email'   => $tenant->email,
                'telefone' => $tenant->phone,
                'canais'  => $this->canais($tenant->email, $tenant->phone),
            ];
        }

        // users.is_active QUALIFICADO: users e a pivot tenant_user tem, ambas,
        // uma coluna is_active, e sem prefixo o MySQL recusa por ambiguidade
        // (500 "Column 'is_active' in WHERE is ambiguous"). Queremos a conta
        // activa (users), e a ligacao activa ao tenant (wherePivot).
        $responsavel = $tenant->users()
            ->where('users.is_active', true)
            ->wherePivot('is_active', true)
            ->first();
        if ($responsavel) {
            $lista[] = [
                'handle'   => 'responsavel',
                'quem'     => $responsavel->name,
                'email'    => $responsavel->email,
                'telefone' => $responsavel->phone ?? null,
                'canais'   => $this->canais($responsavel->email, $responsavel->phone ?? null),
            ];
        }

        return $lista;
    }

    /**
     * Resolve um handle no contacto real.
     *
     * Devolve null quando o handle não existe ou quando o cliente indicado
     * não pertence à empresa indicada — um agente não atravessa fronteiras
     * de tenant nem por engano nem de propósito.
     */
    public function resolver(Tenant $tenant, string $handle): ?array
    {
        if ($handle === 'empresa') {
            return $this->contacto($tenant->name, $tenant->email, $tenant->phone);
        }

        if ($handle === 'responsavel') {
            $u = $tenant->users()
                ->where('users.is_active', true)
                ->wherePivot('is_active', true)
                ->first();

            return $u ? $this->contacto($u->name, $u->email, $u->phone ?? null) : null;
        }

        if (str_starts_with($handle, 'cliente:')) {
            $id = (int) substr($handle, strlen('cliente:'));

            $cliente = Client::where('id', $id)
                ->where('tenant_id', $tenant->id)   // a fronteira
                ->first();

            return $cliente
                ? $this->contacto($cliente->name, $cliente->email, $cliente->phone ?? null)
                : null;
        }

        return null;
    }

    private function contacto(?string $nome, ?string $email, ?string $telefone): array
    {
        return [
            'nome'     => $nome,
            'email'    => $email,
            'telefone' => $telefone,
            // Por onde a mensagem sai de facto. Chamava-se 'mascarado' e
            // guardava 'ca***@dominio.ao'; manter o nome a guardar o valor
            // inteiro seria uma mentira no código.
            'contacto' => $email ?: $telefone ?: '—',
        ];
    }

    private function canais(?string $email, ?string $telefone): array
    {
        $canais = [];
        if ($email) {
            $canais[] = 'email';
        }
        if ($telefone) {
            $canais[] = 'sms';
        }

        return $canais;
    }
}
