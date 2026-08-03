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

        // Idempotência: se já existe cliente com mesmo NIF, devolver
        if (!empty($data['nif'])) {
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
        $client->country = $data['country'] ?? 'Angola';
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
