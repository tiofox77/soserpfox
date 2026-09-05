<?php

namespace App\Services\CRM;

use App\Models\Client;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\CRM\Stage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A conversão: o momento em que um lead vira cliente.
 *
 * É A COSTURA COM O RESTO DO SISTEMA, e por isso vive num serviço: o cliente
 * criado é um cliente DA FACTURAÇÃO (invoicing_clients) — o mesmo que factura,
 * recebe e aparece no portal. Um CRM com a sua própria lista de clientes dava
 * duas listas a divergir, e a pergunta «este cliente veio de onde?» ficava
 * sem resposta para sempre.
 */
class ConversaoDeLead
{
    /**
     * Converte o lead num cliente, e opcionalmente já abre a oportunidade.
     *
     * Idempotente: um lead já convertido devolve o cliente que já tem — o
     * botão carregado duas vezes não pode criar dois clientes.
     */
    public function converter(Lead $lead, int $tenantId, ?int $userId, array $oportunidade = []): Client
    {
        if ($lead->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Lead de outra empresa.');
        }

        if ($lead->status === 'perdido') {
            throw new InvalidArgumentException('Um lead perdido não se converte — reabra-o primeiro.');
        }

        return DB::transaction(function () use ($lead, $tenantId, $userId, $oportunidade) {
            if ($lead->converted_client_id) {
                $existente = Client::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->find($lead->converted_client_id);

                if ($existente) {
                    return $existente;
                }
            }

            // Se já houver um cliente com este email ou telefone, é ELE — um
            // lead de alguém que já é cliente acontece a toda a hora (ligou
            // outra vez, veio à feira), e criar um segundo cadastro partia a
            // história de facturação ao meio.
            $cliente = Client::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($lead) {
                    $q->when($lead->email, fn ($w) => $w->orWhere('email', $lead->email))
                        ->when($lead->phone, fn ($w) => $w->orWhere('phone', $lead->phone));
                })
                ->when(! $lead->email && ! $lead->phone, fn ($q) => $q->whereRaw('1 = 0'))
                ->first();

            if (! $cliente) {
                $cliente = Client::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'name' => $lead->company ?: $lead->name,
                    'type' => $lead->company ? 'pessoa_juridica' : 'pessoa_fisica',
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    // Código ISO, não o nome: este cliente vai ser facturado e
                    // o país segue para a AGT em `customerCountry`.
                    'country' => \App\Support\Geografia::PAIS_PADRAO,
                    'tax_regime' => 'geral',
                    'is_active' => true,
                ]);
            }

            $lead->update([
                'status' => 'convertido',
                'converted_client_id' => $cliente->id,
            ]);

            // A oportunidade nasce já com o cliente certo, na primeira etapa.
            if (! empty($oportunidade['title'])) {
                $etapa = Stage::doTenant($tenantId)->first();

                Opportunity::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'title' => trim($oportunidade['title']),
                    'stage_id' => $etapa->id,
                    'client_id' => $cliente->id,
                    'lead_id' => $lead->id,
                    'amount' => max(0, (float) ($oportunidade['amount'] ?? 0)),
                    'probability' => (int) $etapa->probability,
                    'expected_close_date' => $oportunidade['expected_close_date'] ?? null,
                    'status' => 'open',
                    'assigned_to' => $lead->assigned_to,
                    'created_by' => $userId,
                ]);
            }

            return $cliente;
        });
    }
}
