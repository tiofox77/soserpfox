<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    /**
     * Cria cliente vindo do PWA offline.
     * Se já existir um cliente com mesmo NIF + tenant, devolve esse (idempotente).
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = activeTenantId();
        if (!$tenantId) {
            return response()->json(['error' => 'No active tenant'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:20',
            // O NIF É OPCIONAL — e isto dizia 'required'.
            //
            // O comentário que aqui estava dizia que a coluna era NOT NULL.
            // Deixou de ser: é nula, e este mesmo controlador grava NULL nos
            // NIF genéricos mais abaixo. O 'required' ficou para trás e fazia
            // isto: o cliente rápido do POS (só com o nome, como a maioria ao
            // balcão) chegava aqui sem NIF → 422 → o aparelho marcava-o como
            // recusado de vez → a venda que o referenciava ficava «a
            // reagendar» cinco vezes e morria na fila. A queixa foi literal:
            // «cliente offline não sincroniza e a factura sai como consumidor
            // final». Provado no browser: HTTP 422 "O campo nif é obrigatório".
            'nif' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:120',
            'province' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:120',
            'tax_regime' => 'nullable|string|max:50',
            'is_iva_subject' => 'nullable|boolean',
            'local_uuid' => 'nullable|string|max:80',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $localUuid = $data['local_uuid'] ?? null;
        unset($data['local_uuid']);

        // Idempotência pelo IDENTIFICADOR LOCAL, antes do NIF.
        //
        // O local_uuid já vinha do PWA e já era devolvido na resposta — mas
        // nunca era usado para nada. A desduplicação só olhava para o NIF, e
        // um cliente de balcão não tem NIF: bastava a resposta perder-se
        // (pedido chega, cliente é criado, ligação cai antes da resposta) para
        // o PWA reenviar e criar um segundo registo da mesma pessoa. As vendas
        // seguintes ficavam divididas entre os dois.
        //
        // O guarda ao esquema existe para o código poder ir para produção
        // antes da migração: sem a coluna, mantém-se o comportamento antigo em
        // vez de rebentar.
        if ($localUuid && $this->temColunaLocalUuid()) {
            $jaCriado = Client::where('tenant_id', $tenantId)
                ->where('local_uuid', $localUuid)
                ->first();

            if ($jaCriado) {
                return response()->json([
                    'id' => $jaCriado->id,
                    'local_uuid' => $localUuid,
                    'name' => $jaCriado->name,
                    'nif' => $jaCriado->nif,
                    'duplicated' => true,
                ]);
            }
        }

        // Idempotência pelo NIF — MAS O NIF GENÉRICO NÃO IDENTIFICA NINGUÉM.
        //
        // O 999999999 é o NIF de consumidor final: é o que fica em toda a
        // venda de balcão a quem não dá contribuinte, e é o que o POS envia
        // por omissão. Tratá-lo como identificador fazia isto:
        //
        //   1. o operador cria "Maria da esquina" sem NIF;
        //   2. o PWA envia 999999999, porque é o valor por omissão;
        //   3. o servidor encontra o Consumidor Final com esse NIF e devolve-o
        //      com `duplicated: true`;
        //   4. a Maria NUNCA é criada, o aparelho fica com o Consumidor Final
        //      no lugar dela — e ninguém vê erro nenhum.
        //
        // Cada cliente novo sem contribuinte era engolido em silêncio. Ficam
        // de fora todos os NIF genéricos: são marcadores de "não identificado",
        // não identidades.
        $nifGenericos = ['999999999', '999999998', '000000000'];
        $nifIdentifica = !empty($data['nif']) && !in_array($data['nif'], $nifGenericos, true);

        // E NÃO SE GUARDA O MARCADOR COMO SE FOSSE UM CONTRIBUINTE.
        //
        // Guardá-lo colidia com o índice único `(tenant_id, nif)` — dois
        // clientes de balcão sem contribuinte não cabiam. Nulo é o que eles
        // são: não identificados. O MySQL aceita muitos NULL num índice
        // único, e um NIF a sério continua a ser único.
        if (!$nifIdentifica) {
            $data['nif'] = null;
        }

        if ($nifIdentifica) {
            $existing = Client::where('tenant_id', $tenantId)
                ->where('nif', $data['nif'])
                ->first();
            if ($existing) {
                return response()->json([
                    'id' => $existing->id,
                    'local_uuid' => $localUuid,
                    'name' => $existing->name,
                    'nif' => $existing->nif,
                    'duplicated' => true,
                ]);
            }
        }

        $client = new Client();
        $client->tenant_id = $tenantId;

        if ($localUuid && $this->temColunaLocalUuid()) {
            $client->local_uuid = $localUuid;
        }

        // ENUM da BD: ['pessoa_fisica','pessoa_juridica']. Mapeia valores legados
        // do PWA (singular/empresa) para evitar "Data truncated for column 'type'".
        $client->type = $this->normalizeType($data['type'] ?? null);
        $client->name = $data['name'];
        $client->nif = $data['nif'] ?? null;
        $client->email = $data['email'] ?? null;
        $client->phone = $data['phone'] ?? null;
        $client->mobile = $data['mobile'] ?? null;
        $client->address = $data['address'] ?? null;
        $client->city = $data['city'] ?? null;
        $client->province = $data['province'] ?? null;
        // Código ISO, não o nome: este cliente vai ser facturado e o país
        // segue para a AGT. O PWA antigo manda «Angola» por extenso.
        $client->country = \App\Support\Geografia::normalizarPais($data['country'] ?? null)
            ?? \App\Support\Geografia::PAIS_PADRAO;
        $client->tax_regime = $data['tax_regime'] ?? 'geral';
        $client->is_iva_subject = $data['is_iva_subject'] ?? false;
        $client->is_active = true;
        $client->save();

        return response()->json([
            'id' => $client->id,
            'local_uuid' => $localUuid,
            'name' => $client->name,
            'nif' => $client->nif,
            'created' => true,
        ], 201);
    }

    /**
     * A coluna do identificador local já existe?
     *
     * Guardado em estático: um Schema::hasColumn é uma ida à base de dados, e
     * este método é chamado duas vezes por cliente sincronizado — numa fila de
     * cem clientes seriam duzentas idas para responder sempre o mesmo.
     */
    private function temColunaLocalUuid(): bool
    {
        static $tem = null;

        if ($tem === null) {
            $tem = \Illuminate\Support\Facades\Schema::hasColumn('invoicing_clients', 'local_uuid');
        }

        return $tem;
    }

    /**
     * Normaliza o tipo de cliente para os valores válidos do ENUM da BD.
     */
    private function normalizeType(?string $type): string
    {
        return match ($type) {
            'pessoa_juridica', 'empresa', 'juridica' => 'pessoa_juridica',
            default => 'pessoa_fisica', // singular, null, etc.
        };
    }
}
