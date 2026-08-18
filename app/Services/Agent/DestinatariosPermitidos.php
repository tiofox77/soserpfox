<?php

namespace App\Services\Agent;

use App\Models\Client;
use App\Models\Tenant;

/**
 * Quem o agente pode contactar.
 *
 * O corpo do pedido NUNCA transporta um email ou um telefone: transporta
 * um handle ('responsavel', 'empresa', 'cliente:123') que é aqui resolvido
 * contra a base. Sem isto, a API seria uma máquina de exfiltrar contactos
 * e de enviar, em nome do soserp, para onde quer que alguém indicasse.
 *
 * Para fora, os contactos vão sempre mascarados — o agente não precisa do
 * endereço, porque não é ele que escolhe para onde a mensagem vai.
 */
class DestinatariosPermitidos
{
    /** Lista os destinatários possíveis de uma empresa, mascarados. */
    public function paraTenant(Tenant $tenant): array
    {
        $lista = [];

        if ($tenant->email || $tenant->phone) {
            $lista[] = [
                'handle'  => 'empresa',
                'quem'    => $tenant->name,
                'email'   => $this->mascararEmail($tenant->email),
                'telefone' => $this->mascararTelefone($tenant->phone),
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
                'email'    => $this->mascararEmail($responsavel->email),
                'telefone' => $this->mascararTelefone($responsavel->phone ?? null),
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
            'nome'      => $nome,
            'email'     => $email,
            'telefone'  => $telefone,
            'mascarado' => $this->mascararEmail($email) ?: $this->mascararTelefone($telefone) ?: '—',
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

    /** ca***@dominio.ao */
    public function mascararEmail(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) {
            return null;
        }

        [$utilizador, $dominio] = explode('@', $email, 2);

        $visivel = mb_substr($utilizador, 0, min(2, mb_strlen($utilizador)));

        return $visivel . str_repeat('*', max(3, mb_strlen($utilizador) - 2)) . '@' . $dominio;
    }

    /** 9****9902 */
    public function mascararTelefone(?string $telefone): ?string
    {
        if (!$telefone) {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $telefone);

        if (strlen($digitos) < 5) {
            return str_repeat('*', strlen($digitos));
        }

        return substr($digitos, 0, 1)
            . str_repeat('*', strlen($digitos) - 5)
            . substr($digitos, -4);
    }
}
